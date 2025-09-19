<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ThermalPrinter;
use Mike42\Escpos\PrintConnectors\InMemoryPrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Service for generating thermal printer templates
 * Handles formatting for receipts, KOT, and other documents
 */
class ThermalPrintTemplateService
{
    private ThermalPrinter $printer;
    private int $paperWidth;
    private array $settings;

    /**
     * Initialize template service with printer configuration
     */
    public function __construct(ThermalPrinter $printer)
    {
        $this->printer = $printer;
        $this->paperWidth = $this->getPaperWidth($printer->paper_size);
        $this->settings = $printer->settings ?? [];
    }

    /**
     * Get paper width in characters based on paper size
     */
    private function getPaperWidth(string $paperSize): int
    {
        return match ($paperSize) {
            '58mm' => 32,
            '80mm' => 48,
            '110mm' => 64,
            default => 48
        };
    }

    /**
     * Generate receipt template for customer orders
     */
    public function generateReceipt(Order $order): string
    {
        try {
            $connector = new InMemoryPrintConnector();
            /** @var Printer $printer */
            $printer = new Printer($connector);

            // Header
            $this->printReceiptHeader($printer, $order);
            
            // Order details
            $this->printOrderDetails($printer, $order);
            
            // Items
            $this->printOrderItems($printer, $order);
            
            // Totals
            $this->printOrderTotals($printer, $order);
            
            // Footer
            $this->printReceiptFooter($printer, $order);

            $printer->close();
            
            return $connector->getData();
            
        } catch (\Exception $e) {
            Log::error('Failed to generate receipt template: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate KOT (Kitchen Order Ticket) template
     */
    public function generateKOT(Order $order): string
    {
        try {
            $connector = new InMemoryPrintConnector();
            $printer = new Printer($connector);

            // KOT Header
            $this->printKOTHeader($printer, $order);
            
            // Order info
            $this->printKOTOrderInfo($printer, $order);
            
            // Kitchen items only
            $this->printKOTItems($printer, $order);
            
            // Special instructions
            $this->printKOTInstructions($printer, $order);
            
            // Footer
            $this->printKOTFooter($printer);

            $printer->close();
            
            return $connector->getData();
            
        } catch (\Exception $e) {
            Log::error('Failed to generate KOT template: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate test print template
     */
    public function generateTestPrint(): string
    {
        try {
            $connector = new InMemoryPrintConnector();
            $printer = new Printer($connector);

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text("THERMAL PRINTER TEST\n");
            $printer->setEmphasis(false);
            
            $printer->text(str_repeat("-", $this->paperWidth) . "\n");
            
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Printer: " . $this->printer->name . "\n");
            $printer->text("Paper Size: " . $this->printer->paper_size . "\n");
            $printer->text("Connection: " . ucfirst($this->printer->connection_type) . "\n");
            $printer->text("Date: " . Carbon::now()->format('Y-m-d H:i:s') . "\n");
            
            $printer->text(str_repeat("-", $this->paperWidth) . "\n");
            
            // Character width test
            $printer->text("Width Test:\n");
            $printer->text(str_repeat("1234567890", ceil($this->paperWidth / 10)) . "\n");
            $printer->text(str_repeat("-", $this->paperWidth) . "\n");
            
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("Test completed successfully!\n");
            
            $printer->feed(3);
            $printer->cut();
            $printer->close();
            
            return $connector->getData();
            
        } catch (\Exception $e) {
            Log::error('Failed to generate test print: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Print receipt header with restaurant info
     */
    private function printReceiptHeader(Printer $printer, Order $order): void
    {
        $restaurant = $order->restaurant;
        
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->setTextSize(2, 1);
        $printer->text($this->wrapText($restaurant->name, $this->paperWidth / 2) . "\n");
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);
        
        if ($restaurant->address) {
            $printer->text($this->wrapText($restaurant->address, $this->paperWidth) . "\n");
        }
        
        if ($restaurant->phone) {
            $printer->text("Tel: " . $restaurant->phone . "\n");
        }
        
        $printer->text(str_repeat("=", $this->paperWidth) . "\n");
        $printer->setEmphasis(true);
        $printer->text("RECEIPT\n");
        $printer->setEmphasis(false);
        $printer->text(str_repeat("=", $this->paperWidth) . "\n");
    }

    /**
     * Print order details section
     */
    private function printOrderDetails(Printer $printer, Order $order): void
    {
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        
        $printer->text("Order #: " . $order->order_number . "\n");
        $printer->text("Date: " . $order->created_at->format('Y-m-d H:i:s') . "\n");
        
        if ($order->table) {
            $printer->text("Table: " . $order->table->name . "\n");
        }
        
        if ($order->customer) {
            $printer->text("Customer: " . $order->customer->name . "\n");
        }
        
        if ($order->waiter) {
            $printer->text("Waiter: " . $order->waiter->name . "\n");
        }
        
        $printer->text(str_repeat("-", $this->paperWidth) . "\n");
    }

    /**
     * Print order items with formatting
     */
    private function printOrderItems(Printer $printer, Order $order): void
    {
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        
        foreach ($order->orderItems as $item) {
            // Item name and quantity
            $itemLine = sprintf("%-" . ($this->paperWidth - 8) . "s %2dx", 
                $this->truncateText($item->menuItem->name, $this->paperWidth - 8), 
                $item->quantity
            );
            $printer->text($itemLine . "\n");
            
            // Price line
            $unitPrice = number_format($item->price, 2);
            $totalPrice = number_format($item->price * $item->quantity, 2);
            $priceLine = sprintf("%" . ($this->paperWidth - strlen($totalPrice)) . "s%s", 
                "@" . $unitPrice . " ", $totalPrice
            );
            $printer->text($priceLine . "\n");
            
            // Special instructions
            if ($item->special_instructions) {
                $printer->text("  Note: " . $this->wrapText($item->special_instructions, $this->paperWidth - 8) . "\n");
            }
            
            $printer->text("\n");
        }
        
        $printer->text(str_repeat("-", $this->paperWidth) . "\n");
    }

    /**
     * Print order totals section
     */
    private function printOrderTotals(Printer $printer, Order $order): void
    {
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        
        // Subtotal
        $subtotal = number_format($order->subtotal ?? $order->total, 2);
        $this->printTotalLine($printer, "Subtotal:", $subtotal);
        
        // Tax
        if ($order->tax_amount > 0) {
            $tax = number_format($order->tax_amount, 2);
            $this->printTotalLine($printer, "Tax:", $tax);
        }
        
        // Discount
        if ($order->discount_amount > 0) {
            $discount = number_format($order->discount_amount, 2);
            $this->printTotalLine($printer, "Discount:", "-" . $discount);
        }
        
        $printer->text(str_repeat("=", $this->paperWidth) . "\n");
        
        // Total
        $printer->setEmphasis(true);
        $total = number_format($order->total, 2);
        $this->printTotalLine($printer, "TOTAL:", $total);
        $printer->setEmphasis(false);
        
        $printer->text(str_repeat("=", $this->paperWidth) . "\n");
    }

    /**
     * Print receipt footer
     */
    private function printReceiptFooter(Printer $printer, Order $order): void
    {
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        
        $printer->text("\n");
        $printer->text("Thank you for your visit!\n");
        $printer->text("Please come again\n");
        
        if ($order->restaurant->website) {
            $printer->text($order->restaurant->website . "\n");
        }
        
        $printer->feed(3);
        $printer->cut();
    }

    /**
     * Print KOT header
     */
    private function printKOTHeader(Printer $printer, Order $order): void
    {
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->setTextSize(2, 1);
        $printer->text("KITCHEN ORDER\n");
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);
        $printer->text(str_repeat("=", $this->paperWidth) . "\n");
    }

    /**
     * Print KOT order information
     */
    private function printKOTOrderInfo(Printer $printer, Order $order): void
    {
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        
        $printer->setEmphasis(true);
        $printer->text("Order #: " . $order->order_number . "\n");
        $printer->setEmphasis(false);
        
        $printer->text("Time: " . $order->created_at->format('H:i:s') . "\n");
        
        if ($order->table) {
            $printer->setEmphasis(true);
            $printer->text("Table: " . $order->table->name . "\n");
            $printer->setEmphasis(false);
        }
        
        if ($order->waiter) {
            $printer->text("Waiter: " . $order->waiter->name . "\n");
        }
        
        $printer->text(str_repeat("-", $this->paperWidth) . "\n");
    }

    /**
     * Print KOT items (kitchen items only)
     */
    private function printKOTItems(Printer $printer, Order $order): void
    {
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        
        foreach ($order->orderItems as $item) {
            // Skip beverages or non-kitchen items if configured
            if ($this->shouldSkipKOTItem($item)) {
                continue;
            }
            
            // Quantity and item name
            $printer->setEmphasis(true);
            $printer->setTextSize(1, 1);
            $printer->text(sprintf("%2dx %s\n", $item->quantity, $item->menuItem->name));
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);
            
            // Special instructions
            if ($item->special_instructions) {
                $printer->text("     ** " . $this->wrapText($item->special_instructions, $this->paperWidth - 8) . " **\n");
            }
            
            $printer->text("\n");
        }
    }

    /**
     * Print KOT special instructions
     */
    private function printKOTInstructions(Printer $printer, Order $order): void
    {
        if ($order->special_instructions) {
            $printer->text(str_repeat("-", $this->paperWidth) . "\n");
            $printer->setEmphasis(true);
            $printer->text("SPECIAL INSTRUCTIONS:\n");
            $printer->setEmphasis(false);
            $printer->text($this->wrapText($order->special_instructions, $this->paperWidth) . "\n");
        }
    }

    /**
     * Print KOT footer
     */
    private function printKOTFooter(Printer $printer): void
    {
        $printer->text(str_repeat("=", $this->paperWidth) . "\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("*** END OF KOT ***\n");
        $printer->feed(3);
        $printer->cut();
    }

    /**
     * Helper method to print total lines with proper alignment
     */
    private function printTotalLine(Printer $printer, string $label, string $amount): void
    {
        $line = sprintf("%-" . ($this->paperWidth - strlen($amount)) . "s%s", $label, $amount);
        $printer->text($line . "\n");
    }

    /**
     * Wrap text to fit paper width
     */
    private function wrapText(string $text, int $width): string
    {
        return wordwrap($text, $width, "\n", true);
    }

    /**
     * Truncate text to fit width
     */
    private function truncateText(string $text, int $width): string
    {
        return strlen($text) > $width ? substr($text, 0, $width - 3) . '...' : $text;
    }

    /**
     * Check if item should be skipped in KOT
     */
    private function shouldSkipKOTItem($item): bool
    {
        // Skip beverages or items marked as non-kitchen
        $skipCategories = $this->settings['skip_kot_categories'] ?? ['beverages', 'drinks'];
        
        if ($item->menuItem->category && in_array(strtolower($item->menuItem->category->name), $skipCategories)) {
            return true;
        }
        
        return false;
    }
}