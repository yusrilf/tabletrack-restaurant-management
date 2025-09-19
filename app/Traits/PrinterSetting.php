<?php

namespace App\Traits;

use Exception;
use App\Models\Kot;
use App\Models\Order;
use App\Models\KotPlace;
use App\Models\MultipleOrder;
use App\Models\Payment;
use Mike42\Escpos\Printer;
use App\Models\RestaurantTax;
use App\Models\ReceiptSetting;
use Mike42\Escpos\EscposImage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Printer as PrinterSettings;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use App\Models\PrintJob;
use App\Traits\InMemoryConnector;
use App\Events\PrintJobCreated;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\KotController;
use App\Helper\Files;

trait PrinterSetting
{
    protected $printer;
    protected $charPerLine;
    protected $indentSize;
    protected $connector;
    protected $htmlContent = null;
    protected $imagePath = null;
    protected $imageFilename = null;
    protected $printerSetting;
    protected $restaurant;

    private const COLS = 20; // 80mm @ 512 dots ≈ 42 chars in Font A

    public function getPrinterSettingProperty()
    {
        return PrinterSettings::where('is_active', 1)->get();
    }

    public function getPrinterConnector($printerSetting)
    {
        return new InMemoryConnector();

        try {
            $connector = match ($printerSetting->type) {
                'windows' => new CupsPrintConnector($printerSetting->share_name),
                'network' => new NetworkPrintConnector($printerSetting->ip_address, $printerSetting->port ?? 9100),
                default => new CupsPrintConnector($printerSetting->share_name),
            };

            $this->logPrinterConnection($printerSetting);
            return $connector;
        } catch (\Exception $e) {
            Log::error('Printer connection failed: ' . $e->getMessage());
            $this->alert('error', __('messages.printerNotFound', ['error' => $e->getMessage()]));
        }
    }

    private function logPrinterConnection($printerSetting)
    {
        $message = match ($printerSetting->type) {
            'windows' => 'Using Windows Print Connector with share name: ' . $printerSetting->share_name,
            'network' => 'Using Network Print Connector with IP: ' . $printerSetting->ip_address . ' and port: 9100',
            default => 'Using Linux (CUPS) Print Connector with share name: ' . $printerSetting->share_name,
        };
        Log::info($message);
    }

    public function hasModule($module)
    {
        return in_array($module, restaurant_modules());
    }

    public function handleKotPrint($kotId, $kotPlaceId = null, $alsoPrintOrder = false)
    {
        $kotPlace = KotPlace::findOrFail($kotPlaceId);
        $printerSetting = $this->getActivePrinter($kotPlace->printer_id);
        $this->printerSetting = $printerSetting;


        // First generate the KOT image
        $this->generateKotImage($kotId, $kotPlaceId);

        // Then proceed with the original print logic
        $this->executeKotPrint($kotId, $kotPlaceId, $alsoPrintOrder);
    }

    /**
     * Generate KOT image using html-to-image JavaScript approach
     */
    public function generateKotImage($kotId, $kotPlaceId = null)
    {
        Log::info("generateKotImage called for KOT ID: {$kotId}, Place ID: {$kotPlaceId}");

        try {
            // Add a small delay to prevent race conditions when multiple KOTs are printed simultaneously
            usleep(200000); // 200ms delay

            $kotPlaceId = $kotPlaceId ?? 1;
            $width = $this->getPrintWidth(); // 80mm for fullWidth approach
            $thermal = true;

            // Generate the KOT content using KotController to avoid duplication
            $content = (new KotController())->printKot($kotId, $kotPlaceId, $width, $thermal)->render();

            // Use html-to-image approach by dispatching a JavaScript event
            // This will trigger the image capture in the frontend
            $this->dispatch('saveKotImageFromPrint', $kotId, $kotPlaceId, $content);

            // Log success
            Log::info("KOT image save event dispatched for KOT ID: {$kotId}");
        } catch (\Exception $e) {
            Log::error("Failed to dispatch KOT image save event: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            // Don't throw exception to avoid breaking the print process
        }
    }

    /**
     * Execute the original KOT print logic
     */
    private function executeKotPrint($kotId, $kotPlaceId = null, $alsoPrintOrder = false)
    {
        $kotPlace = KotPlace::findOrFail($kotPlaceId);
        $printerSetting = $this->getActivePrinter($kotPlace->printer_id);
        $this->printerSetting = $printerSetting;

        if (!$printerSetting) {
            throw new \Exception(__('messages.noActiveKotPrinterConfigured'));
        }

        $kot = Kot::with('items', 'order.waiter', 'table')->find($kotId);

        $this->imageFilename = 'kot-' . $kotId . '.png';

        $this->printKotThermalDefault($kotId, $printerSetting, $kotPlaceId);

        if ($alsoPrintOrder) {
            $kot = Kot::findOrFail($kotId);
            $this->handleOrderPrint($kot->order_id);
        }
    }

    public function printKotAsPdf($kotId)
    {

        $kot = Kot::with('items.menuItem', 'items.menuItemVariation', 'items.modifierOptions', 'order.table')->findOrFail($kotId);


        $pdf = Pdf::loadView('pos.printKot', ['kot' => $kot])
            ->setPaper('A4')
            ->setWarnings(false);

        $filename = 'kot_' . $kotId . '.pdf';
        $path = storage_path('app/temp/' . $filename);
        $pdf->save($path);
        Storage::put('app/temp/' . $filename, $pdf->output());
        return $path;
    }

    public function printKotThermalDefault($kotId, $printerSetting, $kotPlaceId)
    {
        $kot = $this->loadKotWithRelations($kotId);

        $kotPlace = KotPlace::findOrFail($kotPlaceId);
        $order = $kot->order;
        $restaurant = restaurant();

        $items = $this->filterKotItemsByPlace($kot, $kotPlaceId);

        $this->initializePrinter($printerSetting);

        $this->printKotHeader($restaurant, $kotPlace);
        $this->printKotOrderInfo($order, $kot);
        $this->printKotItems($items, $printerSetting);
        $this->printKotNotes($kot);

        $this->createPrintJob($order->branch_id);
        $this->alert('success', __('modules.kot.print_success'));
    }

    private function loadKotWithRelations($kotId)
    {
        return Kot::with([
            'items.menuItem',
            'items.menuItemVariation',
            'items.modifierOptions',
            'order.table',
            'order.customer',
            'order.waiter',
            'order.items.menuItem',
            'order.items.menuItemVariation',
            'order.items.modifierOptions',
            'order.charges.charge',
            'order.taxes.tax',
            'order.payments'
        ])->findOrFail($kotId);
    }

    private function filterKotItemsByPlace($kot, $kotPlaceId)
    {
        return isset($kotPlaceId)
            ? $kot->items->filter(fn($item) => $item->menuItem && $item->menuItem->kot_place_id == $kotPlaceId)
            : $kot->items;
    }

    private function initializePrinter($printerSetting)
    {
        $connector = $this->getPrinterConnector($printerSetting);
        $this->printer = new Printer($connector);
        $this->printer->initialize();

        $this->charPerLine = $this->getCharPerLine($printerSetting);
        $this->indentSize = $this->getIndentSize($printerSetting);

        $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        $this->printer->setTextSize(1, 1);
        $this->printer->setEmphasis(true);
    }

    private function printKotHeader($restaurant, $kotPlace)
    {

        // $this->printer->text($restaurant->name . "\n");
        // $this->printer->setEmphasis(true);

        // if ($restaurant->address) {
        //     $this->printWrappedText($restaurant->address, $this->charPerLine);
        // }

        $this->printer->setEmphasis(false);

        if ($this->hasModule('KOT') && $kotPlace->name) {
            $this->printer->text($kotPlace->name . "\n");
            $this->printSeparator();
        }


        $this->printer->setEmphasis(true);
        $this->printer->text(__('modules.kot.kitchen_order_ticket') . "\n");
        $this->printSeparator();
        $this->printer->setEmphasis(false);
    }

    private function printSeparator()
    {
        $this->printer->text(str_repeat('-', $this->charPerLine) . "\n");
    }

    private function printWrappedText($text, $maxWidth)
    {
        $addressLines = explode("\n", wordwrap($text, $maxWidth, "\n", true));

        foreach ($addressLines as $line) {
            $this->printer->text($line . "\n");
        }
    }

    private function printKotOrderInfo($order, $kot)
    {
        $orderNumber = $order->show_formatted_order_number;
        $kotNumber = __('modules.kot.kot_number', ['number' => $kot->kot_number]);
        $date = __('modules.kot.date', ['date' => $kot->created_at->timezone(restaurant()->timezone)->format('d-m-Y')]);
        $time = __('modules.kot.time', ['time' => $kot->created_at->timezone(restaurant()->timezone)->format('h:i A')]);
        $waiter = __('modules.order.waiter') . ': ' . ($order->waiter->name ?? '--');
        $table = __('modules.table.table') . ': ' . ($order->table->table_code ?? '--');

        // First line: order number and KOT number, aligned
        $space = $this->charPerLine - mb_strlen($orderNumber, 'UTF-8') - mb_strlen($kotNumber, 'UTF-8');
        $line1 = $orderNumber . str_repeat(' ', max(1, $space)) . $kotNumber;
        $this->printer->text($line1 . "\n");

        // Second line: date and time, aligned
        $space2 = $this->charPerLine - mb_strlen($date, 'UTF-8') - mb_strlen($time, 'UTF-8');
        $line2 = $date . str_repeat(' ', max(1, $space2)) . $time;
        // $line2 = $date . str_repeat(' ', max(1, $space2)) . $time;
        $this->printer->text($line2 . "\n\n");


        if ($order->waiter) {

            // Second line: date and time, aligned
            $space3 = $this->charPerLine - mb_strlen($table, 'UTF-8') - mb_strlen($waiter, 'UTF-8');
            $line3 = $table . str_repeat(' ', max(1, $space3)) . $waiter;
            $this->printer->text($line3 . "\n");
        }

        $this->printKotItemsHeader();
    }

    private function printKotItemsHeader()
    {
        $itemText = __('modules.kot.item');
        $qtyText = __('modules.kot.qty');

        $itemHeader = str_pad($itemText, $this->charPerLine - mb_strlen($qtyText), ' ') . $qtyText;
        $this->printer->text($itemHeader . "\n");
        $this->printer->text(str_repeat('-', $this->charPerLine) . "\n");
    }

    private function printKotItems($items, $printerSetting)
    {
        foreach ($items as $item) {
            $this->printKotItem($item, $printerSetting);
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text(str_repeat('-', $this->charPerLine));
            $this->printer->text("\n");
        }
    }

    private function printKotItem($item, $printerSetting)
    {
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);

        $itemName = $this->formatItemName($item->menuItem->item_name);
        $this->printer->setEmphasis(true);
        $this->printer->text($itemName . str_repeat(' ', max(0, $this->getItemNameSpaces($itemName))) . $item->quantity . "\n");
        $this->printer->setEmphasis(false);

        $this->printKotItemVariations($item, $printerSetting);
    }

    private function formatItemName($itemName)
    {
        $itemWidth = $this->getItemNameWidth();

        if (mb_strlen($itemName, 'UTF-8') > ($itemWidth - 2)) {
            return mb_substr($itemName, 0, $itemWidth - 5, 'UTF-8') . '...';
        }

        return $itemName;
    }

    private function getItemNameSpaces($itemName)
    {
        $itemWidth = $this->getItemNameWidth();
        return $itemWidth - mb_strlen($itemName, 'UTF-8');
    }

    private function getItemNameWidth()
    {
        $totalWidth = $this->charPerLine;
        $qtyWidth = 3;
        return $totalWidth - $qtyWidth;
    }

    private function printKotItemVariations($item, $printerSetting)
    {
        $indentSize = $this->getIndentSize($printerSetting);
        $subIndent = str_repeat(' ', 4);

        $this->printer->setJustification(Printer::JUSTIFY_LEFT);

        if (isset($item->menuItemVariation)) {
            $variation = '• ' . $item->menuItemVariation->variation;
            $this->printer->text($subIndent . $variation . "\n");
        }

        foreach ($item->modifierOptions as $modifier) {
            $modText = '• ' . $modifier->name;
            if (isset($modifier->price) && $modifier->price > 0) {
                $modText .= ' (+' . currency_format($modifier->price, restaurant()->currency_id) . ')';
            }
            $this->printer->text($subIndent . $modText . "\n");
        }
    }

    private function printKotNotes($kot)
    {
        if ($kot->note) {
            $this->printer->setEmphasis(true);
            $this->printer->text(__('modules.kot.special_instructions') . "\n");
            $this->printer->setEmphasis(false);
            $this->printer->text($kot->note . "\n");
            $this->printer->text(str_repeat('-', $this->charPerLine));
        }
    }

    public function handleOrderPrint($orderId)
    {
        Log::info("handleOrderPrint called for Order ID: {$orderId}");

        // Load the order to verify what we're actually printing
        $order = Order::find($orderId);
        if ($order) {
            Log::info("Order details - ID: {$order->id}, Order Number: {$order->order_number}, Created: {$order->created_at}");
        } else {
            Log::error("Order with ID {$orderId} not found!");
        }

        $orderPlace = MultipleOrder::first();
        $printerSetting = $this->getActivePrinter($orderPlace->printer_id);

        $this->printerSetting = $printerSetting;


        // First generate the Order image
        $this->generateOrderImage($orderId);

        // Then proceed with the original print logic
        $this->executeOrderPrint($orderId);
    }

    /**
     * Generate Order image using html-to-image JavaScript approach
     */
    private function generateOrderImage($orderId)
    {
        Log::info("generateOrderImage called for Order ID: {$orderId}");

        try {
            // Add a delay to prevent conflicts with KOT image generation
            usleep(500000); // 500ms delay

            $width = $this->getPrintWidth(); // 80mm for fullWidth approach
            $thermal = true;

            // Generate the Order content using OrderController to avoid duplication
            $content = (new OrderController())->printOrder($orderId, $width, $thermal)->render();


            // Use html-to-image approach by dispatching a JavaScript event
            // This will trigger the image capture in the frontend
            $this->dispatch('saveOrderImageFromPrint', $orderId, $content);

            // Log success
            Log::info("Order image save event dispatched for Order ID: {$orderId}");
        } catch (\Exception $e) {
            Log::error("Failed to dispatch Order image save event: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            // Don't throw exception to avoid breaking the print process
        }
    }

    /**
     * Execute the original Order print logic
     */
    private function executeOrderPrint($orderId)
    {
        $orderPlace = MultipleOrder::first();
        $printerSetting = $this->getActivePrinter($orderPlace->printer_id);

        $this->printerSetting = $printerSetting;

        if (!$printerSetting) {
            throw new \Exception('No active order printer configured.');
        }



        $this->printOrderThermal($orderId, $printerSetting);
    }

    public function printOrderThermal($orderId, $printerSetting)
    {
        $this->imageFilename = 'order-' . $orderId . '.png';

        $order = $this->loadOrderWithRelations($orderId);
        $restaurant = restaurant();
        $receiptSettings = $this->getReceiptSettings($restaurant->id);


        $this->initializeOrderPrinter($printerSetting);
        $this->printOrderHeader($restaurant, $receiptSettings, $printerSetting);
        $this->printOrderInfo($order, $receiptSettings);
        $this->printOrderItems($order, $printerSetting);
        $this->printOrderSummary($order);
        $this->printOrderFooter($receiptSettings, $order, $printerSetting);

        $this->createOrderPrintJob($order->branch_id);
        $this->alert('success', __('modules.kot.print_success'));
    }

    private function loadOrderWithRelations($orderId)
    {
        return Order::with([
            'table',
            'customer',
            'waiter',
            'items.menuItem',
            'items.menuItemVariation',
            'items.modifierOptions',
            'charges.charge',
            'taxes.tax',
            'payments'
        ])->findOrFail($orderId);
    }

    private function getReceiptSettings($restaurantId)
    {
        return ReceiptSetting::where('restaurant_id', $restaurantId)->first();
    }

    private function getActivePrinter($printerId)
    {
        return PrinterSettings::where('is_active', 1)
            ->where('id', $printerId)
            ->first();
    }

    private function initializeOrderPrinter($printerSetting)
    {
        $connector = $this->getPrinterConnector($printerSetting);
        $this->printer = new Printer($connector);
        $this->printer->initialize();

        $this->charPerLine = $this->getCharPerLine($printerSetting);
        $this->indentSize = $this->getIndentSize($printerSetting);

        $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        $this->printer->setTextSize(1, 1);
        $this->printer->setEmphasis(true);
    }

    private function printOrderHeader($restaurant, $receiptSettings, $printerSetting)
    {
        $this->printRestaurantLogo($receiptSettings, $restaurant, $printerSetting);
        $this->printRestaurantInfo($restaurant);
        $this->printer->text(str_repeat('-', $this->charPerLine) . "\n");
    }

    private function printRestaurantLogo($receiptSettings, $restaurant, $printerSetting)
    {
        if (!$receiptSettings->show_restaurant_logo || !$restaurant->logo) {
            return;
        }

        $logoPath = $restaurant->logo_url;
        if (!file_exists($logoPath) || !is_readable($logoPath)) {
            return;
        }

        $printableWidth = $this->getPrintableWidth($printerSetting);
        $this->printImage($logoPath, $printableWidth);
    }

    private function getPrintableWidth($printerSetting)
    {
        return match ($printerSetting->print_format ?? 'thermal80mm') {
            'thermal56mm' => 384,
            'thermal112mm' => 832,
            default => 525,
        };
    }

    private function printImage($imagePath, $printableWidth)
    {
        $desiredWidth = min(200, $printableWidth);
        $sourceImage = imagecreatefromstring(file_get_contents($imagePath));
        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);
        $aspectRatio = $origHeight / $origWidth;
        $newHeight = intval($desiredWidth * $aspectRatio);

        $resizedImage = imagecreatetruecolor($desiredWidth, $newHeight);
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $desiredWidth, $newHeight, $origWidth, $origHeight);

        $paddedImage = imagecreatetruecolor($printableWidth, $newHeight);
        $white = imagecolorallocate($paddedImage, 255, 255, 255);
        imagefill($paddedImage, 0, 0, $white);

        $x = intval(($printableWidth - $desiredWidth) / 2);
        imagecopy($paddedImage, $resizedImage, $x, 0, 0, 0, $desiredWidth, $newHeight);

        $tmpPath = sys_get_temp_dir() . '/resized_image.png';
        imagepng($paddedImage, $tmpPath);

        $img = EscposImage::load($tmpPath);
        $this->printer->bitImageColumnFormat($img);

        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        imagedestroy($paddedImage);
        unlink($tmpPath);
    }

    private function printRestaurantInfo($restaurant)
    {

        $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        $this->printSeparator();
        $this->printer->setEmphasis(true);
        $this->printWrappedText($restaurant->name ?? 'Restaurant', $this->charPerLine);
        $this->printer->setEmphasis(false);

        if ($restaurant->address) {
            $this->printWrappedText($restaurant->address, $this->charPerLine);
        }

        if ($restaurant->phone_number) {
            $this->printer->text(__('modules.customer.phone') . ': ' . $restaurant->phone_number . "\n");
        }
    }



    private function printOrderInfo($order, $receiptSettings)
    {
        $this->printOrderNumberAndDate($order);
        $this->printSeparator();
        $this->printOrderDetails($order, $receiptSettings);
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        $this->printSeparator();
    }

    private function printOrderNumberAndDate($order)
    {
        $orderNo = $order->show_formatted_order_number;
        $date = $order->date_time->timezone(restaurant()->timezone)->format('d M Y h:i A');
        $this->printer->text(str_pad($orderNo, $this->charPerLine - strlen($date)) . $date . "\n");
    }

    private function printOrderDetails($order, $receiptSettings)
    {
        $indentSize = ($this->getIndentSize($this->printer) + 2);
        $leftPad = str_repeat(' ', $this->getIndentSize($this->printer) - 1);
        $qtyWidth = $this->getColumnWidths($this->charPerLine)[0];

        $this->printer->setJustification(Printer::JUSTIFY_LEFT);

        $this->printOrderDetailIfEnabled($receiptSettings->show_table_number, $order->table, 'modules.settings.tableNumber', $order->table->table_code ?? '', $leftPad, $qtyWidth);
        $this->printOrderDetailIfEnabled($receiptSettings->show_total_guest, $order->number_of_pax, 'modules.order.noOfPax', $order->number_of_pax ?? '', $leftPad, $qtyWidth);
        $this->printOrderDetailIfEnabled($receiptSettings->show_waiter, $order->waiter, 'modules.order.waiter', $order->waiter->name ?? '', $leftPad, $qtyWidth);
        $this->printOrderDetailIfEnabled($receiptSettings->show_customer_name, $order->customer, 'modules.customer.customer', $order->customer->name ?? '', $leftPad, $qtyWidth);

        $this->printCustomerAddress($order, $receiptSettings, $leftPad);
    }

    private function printOrderDetailIfEnabled($setting, $value, $label, $displayValue, $leftPad, $qtyWidth)
    {
        if ($setting && $value) {
            $this->printer->text($leftPad . str_pad(__($label), $qtyWidth, ' ', STR_PAD_LEFT) . ': ' . $displayValue . "\n");
        }
    }

    private function printCustomerAddress($order, $receiptSettings, $leftPad)
    {
        if (!$receiptSettings->show_customer_address || !$order->customer) {
            return;
        }

        $label = __('modules.customer.customerAddress') . ': ';
        $address = $order->customer->delivery_address;
        $maxWidth = $this->charPerLine - strlen($leftPad) - strlen($label);

        $lines = explode("\n", wordwrap($address, $maxWidth, "\n", true));

        if (count($lines) > 0) {
            $this->printer->text($leftPad . $label . array_shift($lines) . "\n");

            foreach ($lines as $line) {
                $this->printer->setJustification(Printer::JUSTIFY_LEFT);
                $this->printer->text($leftPad . str_repeat(' ', strlen($label)) . $line . "\n");
            }
        }
    }

    private function printOrderItems($order, $printerSetting)
    {
        $this->printItemsHeader($printerSetting);
        $this->printItemsList($order->items, $printerSetting);
    }

    private function printItemsHeader($printerSetting)
    {
        list($qtyWidth, $priceWidth, $amountWidth) = $this->getColumnWidths($this->charPerLine);
        $itemNameWidth = $this->charPerLine - ($qtyWidth + $priceWidth + $amountWidth + 3);

        $header = str_pad(__('modules.order.qty'), $qtyWidth) . ' ' .
            str_pad(__('modules.menu.itemName'), $itemNameWidth) . ' ' .
            str_pad(__('modules.order.price'), $priceWidth, ' ', STR_PAD_LEFT) . ' ' .
            str_pad(__('modules.order.amount'), $amountWidth, ' ', STR_PAD_LEFT);

        $this->printer->setEmphasis(true);
        $this->printer->text($header . "\n");
        $this->printer->setEmphasis(false);
        $this->printer->text(str_repeat('-', $this->charPerLine) . "\n");
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);
    }

    private function printItemsList($items, $printerSetting)
    {
        list($qtyWidth, $priceWidth, $amountWidth) = $this->getColumnWidths($this->charPerLine);
        $itemNameWidth = $this->charPerLine - ($qtyWidth + $priceWidth + $amountWidth + 3);
        $indentSize = ($this->getIndentSize($printerSetting) + 2);

        foreach ($items as $item) {
            $this->printOrderItem($item, $qtyWidth, $priceWidth, $amountWidth, $itemNameWidth, $indentSize);
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        }
    }

    private function printOrderItem($item, $qtyWidth, $priceWidth, $amountWidth, $itemNameWidth, $indentSize)
    {
        $qty = str_pad($item->quantity, $qtyWidth);
        $name = $item->menuItem->item_name;
        $price = str_pad(currency_format($item->price, restaurant()->currency_id), $priceWidth, ' ', STR_PAD_LEFT);
        $amount = str_pad(currency_format($item->amount, restaurant()->currency_id), $amountWidth, ' ', STR_PAD_LEFT);

        $nameLines = explode("\n", wordwrap($name, $itemNameWidth, "\n", true));

        $this->printer->text($qty . ' ' . str_pad($nameLines[0], $itemNameWidth) . ' ' . $price . ' ' . $amount . "\n");

        $indent = str_repeat(' ', strlen($qty) + 4);
        $this->printer->setJustification(Printer::JUSTIFY_LEFT);

        for ($i = 1; $i < count($nameLines); $i++) {
            $this->printer->text($indent . $nameLines[$i] . "\n");
        }

        $this->printItemVariations($item, $indentSize);
    }

    private function printItemVariations($item, $indentSize)
    {
        $this->printer->setJustification(Printer::JUSTIFY_LEFT);

        if ($item->menuItemVariation) {
            $variation = '• ' . $item->menuItemVariation->variation;
            $subIndent = str_repeat(' ', $indentSize);
            $this->printer->text($subIndent . $variation . "\n");
        }

        foreach ($item->modifierOptions as $modifier) {
            $modText = '- ' . $modifier->name;
            if (isset($modifier->price) && $modifier->price > 0) {
                $modText .= ' (+' . currency_format($modifier->price, restaurant()->currency_id) . ')';
            }
            $subIndent = str_repeat(' ', ($indentSize + 2));
            $this->printer->text($subIndent . $modText . "\n");
        }
    }

    private function printOrderSummary($order)
    {
        $summary = $this->buildOrderSummary($order);

        $this->printSummaryLines($summary, $order);
    }

    private function buildOrderSummary($order)
    {
        $summary = [
            __('modules.order.subTotal') => currency_format($order->sub_total, restaurant()->currency_id),
        ];

        if (!is_null($order->discount_amount)) {
            $discountLabel = $this->buildDiscountLabel($order);
            $summary[$discountLabel] = '-' . currency_format($order->discount_amount, restaurant()->currency_id);
        }

        foreach ($order->charges as $charge) {
            $label = $this->buildChargeLabel($charge);
            $amount = currency_format($charge->charge->getAmount($order->sub_total - ($order->discount_amount ?? 0)), restaurant()->currency_id);
            $summary[$label] = $amount;
        }

        if ($order->tip_amount > 0) {
            $summary[__('modules.order.tip')] = currency_format($order->tip_amount, restaurant()->currency_id);
        }


        $this->printer->text("\n");

        $this->addDeliveryFeeToSummary($order, $summary);
        $this->addTaxesToSummary($order, $summary);
        $this->addBalanceReturnToSummary($order, $summary);

        return $summary;
    }

    private function buildDiscountLabel($order)
    {
        $discountLabel = __('modules.order.discount');
        if (isset($order->discount_type) && $order->discount_type == 'percent') {
            $discountValue = rtrim(rtrim($order->discount_value, '0'), '.');
            $discountLabel .= ' (' . $discountValue . '%)';
        }
        return $discountLabel;
    }

    private function buildChargeLabel($charge)
    {
        $label = $charge->charge->charge_name;
        if ($charge->charge->charge_type === 'percent') {
            $label .= ' (' . $charge->charge->charge_value . '%)';
        }
        return $label;
    }

    private function addDeliveryFeeToSummary($order, &$summary)
    {
        if (isset($order->order_type) && $order->order_type === 'delivery' && !is_null($order->delivery_fee)) {
            if ($order->delivery_fee > 0) {
                $summary[__('modules.delivery.deliveryFee')] = currency_format($order->delivery_fee, restaurant()->currency_id);
            } else {
                $summary[__('modules.delivery.deliveryFee')] = __('modules.delivery.freeDelivery');
            }
        }
    }

    private function addTaxesToSummary($order, &$summary)
    {
        foreach ($order->taxes as $taxItem) {
            $label = $taxItem->tax->tax_name . ' (' . $taxItem->tax->tax_percent . '%)';
            $amount = currency_format(($taxItem->tax->tax_percent / 100) * ($order->sub_total - ($order->discount_amount ?? 0)));
            $summary[$label] = $amount;
        }
    }

    private function addBalanceReturnToSummary($order, &$summary)
    {
        if ($order->payments->first()?->balance > 0) {
            $summary[__('modules.order.balanceReturn')] = currency_format($order->payments->first()->balance, restaurant()->currency_id);
        }
    }

    private function printSummaryLines($summary, $order)
    {
        foreach ($summary as $label => $value) {
            $this->printer->text(str_pad($label . ':', $this->charPerLine - strlen($value), ' ') . $value . "\n");
        }

        $this->printer->setEmphasis(true);

        $this->printer->text(str_pad(__('modules.order.total') . ':', $this->charPerLine - strlen(currency_format($order->total, restaurant()->currency_id)), ' ') . currency_format($order->total, restaurant()->currency_id) . "\n");
        $this->printer->setEmphasis(false);
    }

    private function printOrderFooter($receiptSettings, $order, $printerSetting)
    {
        $this->printer->text(str_repeat('-', $this->charPerLine) . "\n");
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);

        $this->printPaymentQRCode($receiptSettings, $order, $printerSetting);
        $this->printThankYouMessage();
        $this->printPaymentDetails($receiptSettings, $order);
    }

    private function printPaymentQRCode($receiptSettings, $order, $printerSetting)
    {
        if (!$receiptSettings->show_payment_qr_code || $order->status == 'paid') {
            return;
        }

        $logoPath = $receiptSettings->payment_qr_code_url;

        if (!is_null($logoPath) && !empty($logoPath)) {
            $printableWidth = $this->getPrintableWidth($printerSetting);
            $this->printImage($logoPath, $printableWidth);
        }

        $this->printer->text(__('modules.settings.payFromYourPhone') . "\n");
        $this->printer->text(__('modules.settings.scanQrCode') . "\n");
    }

    private function printThankYouMessage()
    {
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        $this->printer->text(__('messages.thankYouVisit') . "\n");
    }

    private function printPaymentDetails($receiptSettings, $order)
    {
        if (!$receiptSettings->show_payment_details || !$order->payments->count()) {
            return;
        }

        $separator = str_repeat('-', $this->charPerLine);
        $this->printer->text($separator);
        $this->printer->setEmphasis(true);
        $this->printer->text("\n" . __('modules.order.paymentDetails') . "\n");
        $this->printer->setEmphasis(false);
        $this->printer->text($separator);

        $this->printPaymentHeader();
        $this->printer->text($separator);

        foreach ($order->payments as $payment) {
            $this->printPaymentLine($payment);
        }
        $this->printer->text($separator);
    }

    private function printPaymentHeader()
    {
        list($amountWidth, $methodWidth, $dateWidth) = $this->getPaymentColumnWidths($this->charPerLine);

        // Create a combined header for payment method and date
        $methodDateHeader = str_pad(__('modules.order.method'), $methodWidth) . ' ' .
            str_pad(__('app.dateTime'), $dateWidth, ' ', STR_PAD_LEFT);

        $header = "\n" . str_pad(__('modules.order.amount'), $amountWidth) . ' ' . $methodDateHeader;

        $this->printer->setEmphasis(true);
        $this->printer->text($header . "\n");
        $this->printer->setEmphasis(false);
    }

    private function printPaymentLine($payment)
    {
        list($amountWidth, $methodWidth, $dateWidth) = $this->getPaymentColumnWidths($this->charPerLine);

        $amount = str_pad(currency_format($payment->amount, restaurant()->currency_id), $amountWidth);
        $method = str_pad(__('modules.order.' . $payment->payment_method), $methodWidth - 3);
        $date = '';

        if ($payment->payment_method != 'due') {
            $date = $payment->created_at->timezone(config('app.timezone'))
                ->format('d M Y h:i A');
        }
        $date = str_pad($date, $dateWidth, ' ', STR_PAD_LEFT);

        // Combine method and date on the same line
        $methodDate = $method . ' ' . $date;
        $this->printer->text("\n " . $amount . ' ' . $methodDate . "\n");
    }

    private function createPrintJob($branchId = null)
    {
        $this->printer->feed(1);
        return $this->createPrintJobRecord($branchId);
    }

    private function createOrderPrintJob($branchId = null)
    {

        $this->printer->feed(1);

        try {
            return $this->createPrintJobRecord($branchId);
        } catch (\Exception $e) {
            Log::error('Failed to create print job: ' . $e->getMessage());
            throw $e;
        }
    }

    private function createPrintJobRecord($branchId = null)
    {
        $buffer = $this->getPrinterBuffer();

        $printerSetting = $this->printerSetting;

        $printJob = PrintJob::create([
            'image_path' => asset(Files::UPLOAD_FOLDER . '/print/' . $this->imageFilename),
            'image_filename' => $this->imageFilename,
            'restaurant_id' => restaurant()->id,
            'branch_id' => $branchId,
            'status' => 'pending',
            'printer_id' => $printerSetting->id ?? null,
            'payload' => [
                'text' => $buffer,
                'cutPaper' => true,
            ],
        ]);

        // Dispatch event for print job creation
        event(new PrintJobCreated($printJob));


        return $printJob;
    }

    private function getPrinterBuffer()
    {
        $connector = $this->printer->getPrintConnector();
        $reflection = new \ReflectionClass($connector);
        $property = $reflection->getProperty('buffer');
        $property->setAccessible(true);
        $buffer = $property->getValue($connector);

        if (!mb_check_encoding($buffer, 'UTF-8')) {
            $buffer = mb_convert_encoding($buffer, 'UTF-8', 'UTF-8,ISO-8859-1,ASCII');
        }

        return $buffer;
    }

    public function printOrderAsPdf($orderId)
    {
        $order = Order::with('items.menuItem')->findOrFail($orderId);
        $receiptSettings = restaurant()->receiptSetting;
        $payment = Payment::where('order_id', $orderId)->first();
        $taxDetails = RestaurantTax::where('restaurant_id', restaurant()->id)->get();
        $taxMode = $order?->tax_mode ?? (restaurant()->tax_mode ?? 'order');
        $pdf = Pdf::loadView('order.print', [
            'order' => $order,
            'receiptSettings' => $receiptSettings,
            'taxDetails' => $taxDetails,
            'payment' => $payment,
            'taxMode' => $taxMode,
        ])
            ->setPaper('A4')
            ->setWarnings(false);

        $filename = 'order_' . $orderId . '.pdf';
        $path = storage_path('app/temp/' . $filename);
        $pdf->save($path);
        Storage::put('app/temp/' . $filename, $pdf->output());

        return $path;
    }

    private function getCharPerLine($printerSetting)
    {
        return match ($printerSetting->print_format ?? 'thermal80mm') {
            'thermal56mm' => 32,
            'thermal112mm' => 58,
            default => 42,
        };
    }

    private function getIndentSize($printerSetting)
    {
        return match ($printerSetting->print_format ?? 'thermal80mm') {
            'thermal56mm' => 10,
            'thermal112mm' => 2,
            default => 4,
        };
    }

    private function getPrintWidth()
    {

        return match ($this->printerSetting->print_format ?? 'thermal80mm') {
            'thermal56mm' => 56,
            'thermal112mm' => 112,
            default => 80,
        };
    }

    private function getColumnWidths($charPerLine)
    {
        return match (true) {
            $charPerLine <= 32 => [3, 7, 7],    // 58mm
            $charPerLine <= 48 => [4, 10, 10],  // 80mm
            default => [5, 12, 12],             // 112mm or larger
        };
    }

    private function getPaymentColumnWidths($charPerLine)
    {
        return match (true) {
            $charPerLine <= 32 => [10, 12, 10],   // 58mm - optimized for payment details
            $charPerLine <= 48 => [12, 15, 15],   // 80mm
            default => [15, 20, 23],              // 112mm or larger
        };
    }

    /**
     * Extract content from full HTML document for image generation
     */
    private function extractContentFromHtml($html)
    {
        // Remove DOCTYPE, html, head, and body tags, keeping only the content
        $content = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);
        $content = preg_replace('/<html[^>]*>/i', '', $content);
        $content = preg_replace('/<\/html>/i', '', $content);
        $content = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $content);
        $content = preg_replace('/<body[^>]*>/i', '', $content);
        $content = preg_replace('/<\/body>/i', '', $content);

        // Remove any script tags that might trigger print dialogs
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);

        // Remove any onclick, onload, or other event handlers that might trigger print
        $content = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $content);

        // Add script to disable print functionality
        $content = '<script>window.print = function() { console.log("Print disabled for image generation"); };</script>' . $content;

        // Clean up any extra whitespace
        $content = trim($content);

        return $content;
    }
}
