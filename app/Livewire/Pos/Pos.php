<?php

namespace App\Livewire\Pos;

use App\Models\Kot;
use App\Models\Tax;
use App\Models\User;
use App\Models\Order;
use App\Models\Table;
use App\Models\KotItem;
use App\Models\Printer;
use Livewire\Component;
use App\Models\KotPlace;
use App\Models\MenuItem;
use App\Models\OrderTax;
use App\Models\OrderItem;
use App\Models\OrderType;
use App\Models\OrderCharge;
use App\Scopes\BranchScope;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use App\Models\ItemCategory;
use App\Models\ModifierOption;
use App\Traits\PrinterSetting;
use Illuminate\Support\Carbon;
use App\Events\NewOrderCreated;
use App\Models\KotCancelReason;
use Illuminate\Validation\Rule;
use App\Models\RestaurantCharge;
use App\Models\DeliveryExecutive;
use App\Models\MenuItemVariation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Jantinnerezo\LivewireAlert\LivewireAlert;

class Pos extends Component
{
    use LivewireAlert, PrinterSetting;

    protected $listeners = ['refreshPos' => '$refresh'];


    public $categoryList;
    public $search;
    public $filterCategories;
    public $menuItem;
    public $subTotal;
    public $total;
    public $orderNumber;
    public $kotNumber;
    public $tableNo;
    public $tableId;
    public $users;
    public $noOfPax = 1;
    public $selectWaiter;
    public $taxes;
    public $orderNote;
    public $tableOrder;
    public $tableOrderID;
    public $orderType;
    public $orderTypeSlug;
    public $kotList = [];
    public $showVariationModal = false;
    public $showKotNote = false;
    public $showTableModal = false;
    public $showErrorModal = true;
    public $showNewKotButton = false;
    public $orderDetail = null;
    public $showReservationModal = false;
    public $reservationId = null;
    public $reservationCustomer = null;
    public $reservation = null;
    public $isSameCustomer = false;
    public $intendedOrderAction = null;
    public $orderItemList = [];
    public $orderItemVariation = [];
    public $orderItemQty = [];
    public $orderItemAmount = [];
    public $deliveryExecutives;
    public $selectDeliveryExecutive;
    public $orderID;
    public $discountType;
    public $discountValue;
    public $discountAmount;
    public $restaurantSetting;
    public $showDiscountModal = false;
    public $selectedModifierItem;
    public $modifiers;
    public $showModifiersModal = false;
    public $itemModifiersSelected = [];
    public $orderItemModifiersPrice = [];
    public $extraCharges;
    public $discountedTotal;
    public $tipAmount = 0;
    public $orderStatus;
    public $printerSettings;
    public $deliveryFee = 0;
    public $itemNotes = [];
    public $orderPlaces;
    public $cancelReasons;
    public $confirmDeleteModal = false;
    public $deleteOrderModal = false;
    public $cancelReason;
    public $cancelReasonText;
    public $orderTypeId;
    public $deliveryDateTime;
    public $customerDisplayStatus = 'idle';
    public $totalTaxAmount = 0;
    public $orderItemTaxDetails = [];
    public $taxMode;
    public $pickupRange;
    public $now;
    public $minDate;
    public $maxDate;
    public $defaultDate;
    public $formattedOrderNumber;
    
    // Thermal printer properties
    public $thermalPrinters = [];
    public $selectedThermalPrinter = null;
    public $showThermalPrintModal = false;
    public $thermalPrintType = 'receipt'; // receipt, kot, test

    public function mount()
    {
        $this->printerSettings = $this->getPrinterSettingProperty();
        $this->total = 0;
        $this->subTotal = 0;
        $this->categoryList = ItemCategory::all();
        $this->pickupRange = restaurant()->pickup_days_range ?? 1;
        $this->minDate = now()->format('Y-m-d\TH:i');
        $this->maxDate = now()->addDays($this->pickupRange - 1)->endOfDay()->format('Y-m-d\TH:i');
        $this->defaultDate = old('deliveryDateTime', $this->deliveryDateTime ?? $this->minDate);

        // Load thermal printers for current restaurant
        $this->loadThermalPrinters();

        $this->users = User::withoutGlobalScope(BranchScope::class)
            ->where(function ($q) {
                return $q->where('branch_id', branch()->id)
                    ->orWhereNull('branch_id');
            })
            ->role('waiter_' . restaurant()->id)
            ->where('restaurant_id', restaurant()->id)
            ->get();

        $this->taxMode = restaurant()->tax_mode;

        $this->taxes = Tax::all();

        $this->selectWaiter = user()->id;
        $orderType = OrderType::where('branch_id', branch()->id)->where('is_active', true)->first();

        $this->orderType = $orderType->type;
        $this->orderTypeId = $orderType->id;
        $this->orderTypeSlug = $orderType->slug;
        $this->deliveryExecutives = DeliveryExecutive::where('status', 'available')->get();

        if ($this->tableOrderID) {
            $this->tableId = $this->tableOrderID;
            $this->tableOrder = Table::find($this->tableOrderID);
            $this->tableNo = $this->tableOrder->table_code;
            $this->orderID = $this->tableOrder->activeOrder ? $this->tableOrder->activeOrder->id : null;

            if ($this->tableOrder->activeOrder) {

                $this->orderNumber = $this->tableOrder->activeOrder->order_number;
                $this->formattedOrderNumber = $this->tableOrder->activeOrder->formatted_order_number;
                $this->tipAmount = $this->tableOrder->activeOrder->tip_amount;
                $this->deliveryFee = $this->tableOrder->activeOrder->delivery_fee;
                $this->showTableOrder();

                if ($this->orderDetail) {
                    $this->showOrderDetail();
                }
            } elseif ($this->orderDetail) {
                return $this->redirect(route('pos.index'), navigate: true);
            }
        }

        if ($this->orderID) {
            $order = Order::find($this->orderID);

            if (!$order || $order->status === 'canceled') {
                return $this->redirect(route('pos.index'), navigate: true);
            }

            $this->orderNumber = $order->order_number;
            $this->formattedOrderNumber = $order->formatted_order_number;
            $this->noOfPax = $order->number_of_pax;
            $this->selectWaiter = $order->waiter_id ?? null;
            $this->tableNo = $order->table->table_code ?? null;
            $this->tableId = $order->table->id ?? null;
            $this->discountAmount = $order->discount_amount;
            $this->discountValue = $order->discount_type === 'percent' ? rtrim(rtrim($order->discount_value, '0'), '.') : $order->discount_value;
            $this->discountType = $order->discount_type;
            $this->tipAmount = $order->tip_amount;
            $this->deliveryFee = $order->delivery_fee;
            $this->orderStatus = $order->order_status;
            $this->orderTypeId = $order->order_type_id;
            $this->orderType = $order->order_type;
            $this->deliveryDateTime = $order->pickup_date;
            $this->taxMode = $order->tax_mode ?? $this->taxMode;

            if ($this->orderDetail) {

                $this->orderDetail = $order;

                $this->selectDeliveryExecutive = $order->delivery_executive_id;
                $this->setupOrderItems();
            }
        }

        $this->updatedOrderTypeId($this->orderTypeId);

        if ($this->orderID) {
            $this->extraCharges = ($order->status === 'kot' && !$this->orderDetail) ? [] : $order->extraCharges;
        }

        $this->cancelReasons = KotCancelReason::where('cancel_order', true)->get();
    }

    public function updatedOrderTypeId($value)
    {
        // Get the order type information efficiently
        $orderType = OrderType::select('slug', 'type')->find($value);

        // Update the local variables to keep them in sync
        $this->orderTypeSlug = $orderType ? $orderType->slug : $this->orderType;
        $this->orderType = $orderType ? $orderType->type : $this->orderType;

        $mainExtraCharges = RestaurantCharge::whereJsonContains('order_types', $this->orderTypeSlug)
            ->where('is_enabled', true)
            ->get();

        // Handle new orders or table orders without active orders
        if ((!$this->orderID && !$this->tableOrderID) || ($this->tableOrderID && !$this->tableOrder->activeOrder)) {
            $this->extraCharges = $mainExtraCharges;
            $this->orderStatus = 'preparing';

            // Set default delivery fee for delivery orders
            if ($value === 'delivery') {
                $this->deliveryFee = $this->getDefaultDeliveryFee();
            } else {
                $this->deliveryFee = 0;
            }

            $this->calculateTotal();
            return;
        }

        $order = $this->tableOrderID ? $this->tableOrder->activeOrder : Order::find($this->orderID);

        // Early return if no valid order or order is paid
        if (!$order || $order->status === 'paid') {
            return;
        }

        // Efficiently get the slug from the order's order type ID
        $orderTypeSlugFromOrder = $order->order_type_id
            ? OrderType::where('id', $order->order_type_id)->value('slug') ?? $order->order_type
            : $order->order_type;

        // Keep existing charges if order type is unchanged, otherwise set new ones
        $this->extraCharges = $orderTypeSlugFromOrder === $this->orderTypeSlug ? $order->extraCharges : $mainExtraCharges;

        $this->orderStatus = $order->order_status;
        $this->calculateTotal();
    }

    /**
     * Get the default delivery fee from branch settings
     */
    private function getDefaultDeliveryFee(): float
    {
        $branch = branch();
        if (!$branch) {
            return 0;
        }

        $deliverySettings = $branch->deliverySetting;
        if (!$deliverySettings || !$deliverySettings->is_enabled) {
            return 0;
        }

        // Return fixed fee if fee type is fixed
        if ($deliverySettings->fee_type->value === 'fixed') {
            return $deliverySettings->fixed_fee ?? 0;
        }

        // For other fee types, return 0 as they need distance calculation
        return 0;
    }

    /**
     * Update delivery fee and recalculate total
     */
    public function updatedDeliveryFee()
    {
        $this->calculateTotal();
    }

    public function updatedOrderStatus($value)
    {
        if ((!$this->orderID && !$this->tableOrderID) || !$this->orderDetail instanceof Order || is_null($value)) {

            return;
        }

        $this->orderDetail->update(['order_status' => $value]);

        if ($value->value === 'confirmed') {
            $this->orderDetail->kot->each(function ($kot) {
                $kot->update(['status' => 'in_kitchen']);
            });
        }
    }

    public function showTableOrder()
    {
        $this->selectWaiter = $this->tableOrder->activeOrder->waiter_id;
        $this->noOfPax = $this->tableOrder->activeOrder->number_of_pax;
    }

    public function showOrderDetail()
    {
        $this->orderDetail = $this->tableOrder->activeOrder;
        $this->orderType = $this->orderDetail->order_type;
        $this->orderTypeId = $this->orderDetail->order_type_id;

        // Update orderTypeSlug based on order_type_id if available
        if ($this->orderDetail->order_type_id) {
            $orderType = OrderType::select('slug')->find($this->orderDetail->order_type_id);
            $this->orderTypeSlug = $orderType ? $orderType->slug : $this->orderDetail->order_type;
        } else {
            $this->orderTypeSlug = $this->orderDetail->order_type;
        }
        $this->setupOrderItems();
    }

    public function showPayment($id)
    {
        $order = Order::find($id);

        $this->dispatch('showPaymentModal', id: $order->id);
    }

    public function setupOrderItems()
    {
        if ($this->orderDetail) {

            foreach ($this->orderDetail->kot as $kot) {
                $this->kotList['kot_' . $kot->id] = $kot;

                foreach ($kot->items as $item) {
                    $this->orderItemList['"kot_' . $kot->id . '_' . $item->id . '"'] = $item->menuItem;
                    $this->orderItemQty['"kot_' . $kot->id . '_' . $item->id . '"'] = $item->quantity;
                    $this->orderItemModifiersPrice['"kot_' . $kot->id . '_' . $item->id . '"'] = $item->modifierOptions->sum('price');
                    $this->itemModifiersSelected['"kot_' . $kot->id . '_' . $item->id . '"'] = $item->modifierOptions->pluck('id')->toArray();
                    $basePrice = $item->menuItemVariation ? $item->menuItemVariation->price : $item->menuItem->price;
                    $this->orderItemAmount['"kot_' . $kot->id . '_' . $item->id . '"'] = $this->orderItemQty['"kot_' . $kot->id . '_' . $item->id . '"'] * ($basePrice + ($this->orderItemModifiersPrice['"kot_' . $kot->id . '_' . $item->id . '"'] ?? 0));

                    if ($item->menuItemVariation) {
                        $this->orderItemVariation['"kot_' . $kot->id . '_' . $item->id . '"'] = $item->menuItemVariation;
                    }

                    if ($item->note) {
                        $this->itemNotes['"kot_' . $kot->id . '_' . $item->id . '"'] = $item->note;
                    }
                }
            }

            // Calculate tax details for existing items after setting up all items
            if ($this->taxMode === 'item') {
                $this->updateOrderItemTaxDetails();
            }

            $this->calculateTotal();
        }
    }

    public function addCartItems($id, $variationCount, $modifierCount)
    {
        if (($this->orderID && !user_can('Update Order')) || (!$this->orderID && !user_can('Create Order'))) {
            return;
        }

        if ($this->orderID && $this->orderDetail && $this->orderDetail->status === 'kot') {
            $this->addError('error', __('messages.errorWantToCreateNewKot'));
            $this->showNewKotButton = true;
            $this->showErrorModal = true;
            return;
        }

        $this->dispatch('play_beep');
        $this->menuItem = MenuItem::find($id);

        // Initialize item note if it doesn't exist
        if (!isset($this->itemNotes[$id])) {
            $this->itemNotes[$id] = '';
        }

        if ($variationCount > 0) {
            $this->showVariationModal = true;
        } elseif ($modifierCount > 0) {
            $this->selectedModifierItem = $id;
            $this->showModifiersModal = true;
        } else {
            $this->syncCart($id);
        }
    }

    #[On('setTable')]
    public function setTable(Table $table)
    {
        if ($this->tableId) {
            Table::where('id', $this->tableId)->update([
                'available_status' => 'available'
            ]);
        }

        $this->tableNo = $table->table_code;
        $this->tableId = $table->id;

        if ($this->orderID) {
            Order::where('id', $this->orderID)->update(['table_id' => $table->id]);

            // Refresh orderDetail to ensure it's the latest object
            $this->orderDetail = Order::find($this->orderID);

            if (
                $this->orderDetail && is_object($this->orderDetail) && $this->orderDetail->date_time &&
                $this->orderDetail->date_time instanceof \Carbon\Carbon &&
                $this->orderDetail->date_time->format('d-m-Y') == now()->format('d-m-Y')
            ) {
                Table::where('id', $this->tableId)->update([
                    'available_status' => 'running'
                ]);
            }

            $this->orderDetail->fresh();
        }

        $this->showTableModal = false;
    }

    #[On('setPosVariation')]
    public function setPosVariation($variationId)
    {
        $this->showVariationModal = false;
        $menuItemVariation = MenuItemVariation::find($variationId);
        $modifiersAvailable = $menuItemVariation->menuItem->modifiers->count();

        if ($modifiersAvailable) {
            $this->selectedModifierItem = $menuItemVariation->menu_item_id . '_' . $variationId;
            $this->showModifiersModal = true;
        } else {
            $this->orderItemVariation['"' . $menuItemVariation->menu_item_id . '_' . $variationId . '"'] = $menuItemVariation;
            $this->syncCart('"' . $menuItemVariation->menu_item_id . '_' . $variationId . '"');
        }
    }

    public function syncCart($id)
    {
        if (!isset($this->orderItemList[$id])) {
            $this->orderItemList[$id] = $this->menuItem;
            $this->orderItemQty[$id] = $this->orderItemQty[$id] ?? 1;
            $basePrice = $this->orderItemVariation[$id]->price ?? $this->orderItemList[$id]->price;
            $this->orderItemAmount[$id] = $this->orderItemQty[$id] * ($basePrice + ($this->orderItemModifiersPrice[$id] ?? 0));
            $this->calculateTotal();
        } else {
            $this->addQty($id);
        }
    }

    public function deleteCartItems($id)
    {
        // Remove from session arrays
        unset($this->orderItemList[$id]);
        unset($this->orderItemQty[$id]);
        unset($this->orderItemAmount[$id]);
        unset($this->orderItemVariation[$id]);
        unset($this->itemModifiersSelected[$id]);
        unset($this->itemNotes[$id]);
        unset($this->orderItemModifiersPrice[$id]);
        unset($this->orderItemTaxDetails[$id]);

        // Early return if no order detail or not a valid object
        if (!$this->orderDetail || !is_object($this->orderDetail)) {
            $this->calculateTotal();
            return;
        }

        $parts = explode('_', str_replace('"', '', $id));

        // Early return if not a KOT item
        if (count($parts) < 3 || $parts[0] !== 'kot') {
            $this->calculateTotal();
            return;
        }

        $kotId = $parts[1];
        $itemId = $parts[2];

        KotItem::where('kot_id', $kotId)
            ->where('id', $itemId)
            ->delete();

        // Early return if there are still items in the cart
        if (!empty($this->orderItemList)) {
            $this->calculateTotal();
            return;
        }

        $kot = Kot::find($kotId);
        if (!$kot) {
            $this->calculateTotal();
            return;
        }

        $order = $this->orderDetail;
        $kot->delete();

        // Early return if order is not valid
        if (!$order || !($order instanceof Order)) {
            $this->calculateTotal();
            return;
        }

        // Free up table and delete order
        if ($order->table_id) {
            Table::where('id', $order->table_id)->update(['available_status' => 'available']);
        }

        $order->delete();

        $this->orderDetail = null;
        $this->orderID = null;

        $this->alert('success', __('messages.orderDeleted'), [
            'toast' => true,
            'position' => 'top-end',
            'showCancelButton' => false,
            'cancelButtonText' => __('app.close')
        ]);

        $this->redirect(route('pos.index'), navigate: true);
    }

    public function deleteOrderItems($id)
    {
        $orderItem = OrderItem::find($id);

        if ($orderItem) {
            $kotItems = KotItem::where('menu_item_id', $orderItem->menu_item_id)
                ->where('menu_item_variation_id', $orderItem->menu_item_variation_id)
                ->where('quantity', $orderItem->quantity)
                ->whereHas('kot', function ($query) use ($orderItem) {
                    $query->where('order_id', $orderItem->order_id);
                })
                ->get();

            foreach ($kotItems as $kotItem) {
                $kotItem->delete();
            }
        }

        OrderItem::destroy($id);

        if ($this->orderDetail && $this->orderDetail instanceof Order) {
            $this->orderDetail->refresh();

            if ($this->orderDetail->items->count() === 0) {
                $this->deleteOrder($this->orderDetail->id);
                $this->orderDetail = null;
                $this->orderID = null;

                $this->alert('success', __('messages.orderDeleted'), [
                    'toast' => true,
                    'position' => 'top-end',
                    'showCancelButton' => false,
                    'cancelButtonText' => __('app.close')
                ]);

                return $this->redirect(route('pos.index'), navigate: true);
            }

            $this->total = 0;
            $this->subTotal = 0;



            $this->discountedTotal = $this->total;

            $this->recalculateTaxTotals();

            foreach ($this->extraCharges ?? [] as $value) {
                $this->total += $value->getAmount($this->subTotal);
            }

            Order::where('id', $this->orderDetail->id)->update([
                'sub_total' => $this->subTotal,
                'total' => $this->total
            ]);
        }
    }

    public function deleteOrder($id)
    {
        $order = Order::find($id);

        if (!$order) {
            $this->alert('error', __('messages.orderNotFound'), [
                'toast' => true,
                'position' => 'top-end',
                'showCancelButton' => false,
                'cancelButtonText' => __('app.close')
            ]);
            return;
        }

        if ($order->table_id) {
            Table::where('id', $order->table_id)->update(['available_status' => 'available']);
        }

        // Delete associated KOT records
        $order->kot()->delete();

        $order->delete();

        $this->alert('success', __('messages.orderDeleted'), [
            'toast' => true,
            'position' => 'top-end',
            'showCancelButton' => false,
            'cancelButtonText' => __('app.close')
        ]);

        return $this->redirect(route('pos.index'), navigate: true);
    }

    public function addQty($id)
    {
        if (($this->orderID && !user_can('Update Order')) || (!$this->orderID && !user_can('Create Order'))) {
            return;
        }

        $this->orderItemQty[$id] = isset($this->orderItemQty[$id]) ? ($this->orderItemQty[$id] + 1) : 1;
        $basePrice = $this->orderItemVariation[$id]->price ?? $this->orderItemList[$id]->price;
        $this->orderItemAmount[$id] = $this->orderItemQty[$id] * ($basePrice + ($this->orderItemModifiersPrice[$id] ?? 0));
        $this->calculateTotal();
    }

    public function subQty($id)
    {
        if (($this->orderID && !user_can('Update Order')) || (!$this->orderID && !user_can('Create Order'))) {
            return;
        }

        $this->orderItemQty[$id] = (isset($this->orderItemQty[$id]) && $this->orderItemQty[$id] > 1) ? ($this->orderItemQty[$id] - 1) : 1;
        $basePrice = $this->orderItemVariation[$id]->price ?? $this->orderItemList[$id]->price;
        $this->orderItemAmount[$id] = $this->orderItemQty[$id] * ($basePrice + ($this->orderItemModifiersPrice[$id] ?? 0));
        $this->calculateTotal();
    }

    public function calculateTotal()
    {
        $this->total = 0;
        $this->subTotal = 0;
        $this->totalTaxAmount = 0;

        if (is_array($this->orderItemAmount)) {
            // Calculate item taxes first for proper subtotal calculation
            if ($this->taxMode === 'item') {
                $this->updateOrderItemTaxDetails();
            }

            foreach ($this->orderItemAmount as $key => $value) {
                $this->total += $value;

                // For inclusive taxes, subtract tax from subtotal
                if ($this->taxMode === 'item' && isset($this->orderItemTaxDetails[$key])) {
                    $taxDetail = $this->orderItemTaxDetails[$key];
                    $isInclusive = restaurant()->tax_inclusive ?? false;

                    if ($isInclusive) {
                        // For inclusive tax: subtotal = item amount - tax amount
                        $this->subTotal += ($value - ($taxDetail['tax_amount'] ?? 0));
                    } else {
                        // For exclusive tax: subtotal = item amount (tax will be added later)
                        $this->subTotal += $value;
                    }
                } else {
                    // No item taxes or order-level taxes
                    $this->subTotal += $value;
                }
            }
        }

        $this->discountedTotal = $this->total;

        // Apply discounts
        if ($this->discountValue > 0 && $this->discountType) {
            if ($this->discountType === 'percent') {
                $this->discountAmount = round(($this->subTotal * $this->discountValue) / 100, 2);
            } elseif ($this->discountType === 'fixed') {
                $this->discountAmount = min($this->discountValue, $this->subTotal);
            }

            $this->total -= $this->discountAmount;
        }

        $this->discountedTotal = $this->total;

        // Calculate taxes using centralized method
        $this->recalculateTaxTotals();

        // Apply extra charges
        if (!empty($this->orderItemAmount) && $this->extraCharges) {
            foreach ($this->extraCharges ?? [] as $charge) {
                $this->total += $charge->getAmount($this->discountedTotal);
            }
        }

        // Add tip and delivery fees
        if ($this->tipAmount > 0) {
            $this->total += $this->tipAmount;
        }

        if ($this->deliveryFee > 0) {
            $this->total += $this->deliveryFee;
        }

        // Calculate tax and charge amounts for display
        $taxesForDisplay = $this->taxes->map(function ($tax) {
            $amount = (($tax->tax_percent / 100) * $this->discountedTotal);
            return [
                'name' => $tax->tax_name,
                'percent' => $tax->tax_percent,
                'amount' => $amount,
            ];
        })->toArray();
        $chargesForDisplay = collect($this->extraCharges ?? [])->map(function ($charge) {
            return [
                'name' => $charge->name,
                'amount' => $charge->getAmount($this->discountedTotal),
            ];
        })->toArray();

        $paymentGateway = restaurant()->paymentGateways;
        $qrCodeImageUrl = $paymentGateway && $paymentGateway->is_qr_payment_enabled ? $paymentGateway->qr_code_image_url : null;

        $customerDisplayData = [
            'order_number' => $this->orderNumber,
            'formatted_order_number' => $this->formattedOrderNumber,
            'items' => $this->getCustomerDisplayItems(),
            'sub_total' => $this->subTotal,
            'discount' => $this->discountAmount ?? 0,
            'total' => $this->total,
            'taxes' => $taxesForDisplay,
            'extra_charges' => $chargesForDisplay,
            'tip' => $this->tipAmount,
            'delivery_fee' => $this->deliveryFee,
            'order_type' => $this->orderType,
            'status' => $this->customerDisplayStatus ?? 'idle',
            'cash_due' => ($this->customerDisplayStatus ?? null) === 'billed' ? $this->total : null,
            'qr_code_image_url' => $qrCodeImageUrl,
        ];

        Cache::put('customer_display_cart', $customerDisplayData, now()->addMinutes(30));

        // Broadcast customer display update if Pusher is enabled
        if (pusherSettings()->is_enabled_pusher_broadcast) {
            broadcast(new \App\Events\CustomerDisplayUpdated($customerDisplayData));
        }

        // Optionally, still dispatch browser event
        $this->dispatch('orderUpdated', [
            'order_number' => $this->orderNumber,
            'formatted_order_number' => $this->formattedOrderNumber,
            'items' => $this->getCustomerDisplayItems(),
            'sub_total' => $this->subTotal,
            'discount' => $this->discountAmount ?? 0,
            'total' => $this->total,
        ]);
    }

    private function recalculateTaxTotals()
    {
        $this->totalTaxAmount = 0;

        if ($this->taxMode === 'order') {
            foreach ($this->taxes as $tax) {
                $taxAmount = ($tax->tax_percent / 100) * $this->discountedTotal;
                $this->totalTaxAmount += $taxAmount;
                $this->total += $taxAmount;
            }
        } elseif ($this->taxMode === 'item' && !empty($this->orderItemAmount)) {
            // Item-based taxation - taxes are already calculated in calculateTotal()
            $totalInclusiveTax = 0;
            $totalExclusiveTax = 0;
            $isInclusive = restaurant()->tax_inclusive ?? false;

            // Calculate total tax amounts
            foreach ($this->orderItemTaxDetails as $itemTaxDetail) {
                $taxAmount = $itemTaxDetail['tax_amount'] ?? 0;

                if ($isInclusive) {
                    $totalInclusiveTax += $taxAmount;
                } else {
                    $totalExclusiveTax += $taxAmount;
                }
            }

            $this->totalTaxAmount = $totalInclusiveTax + $totalExclusiveTax;

            // For exclusive taxes, add them to the total
            // (Inclusive taxes are already included in the item prices)
            if ($totalExclusiveTax > 0) {
                $this->total += $totalExclusiveTax;
            }
        }
    }

    public function addDiscounts()
    {
        $this->validate([
            'discountValue' => 'required|numeric|min:0',
            'discountType' => 'required|in:fixed,percent',
        ]);

        if ($this->discountType === 'percent' && $this->discountValue > 100) {
            $this->alert('error', __('messages.discountPercentError'), [
                'toast' => true,
                'position' => 'top-end',
                'showCancelButton' => false,
                'cancelButtonText' => __('app.close')
            ]);
            return;
        }

        $order = $this->tableOrderID ? $this->tableOrder->activeOrder : $this->orderDetail;

        if ($order) {
            $order->update([
                'discount_type' => $this->discountType,
                'discount_value' => $this->discountValue,
                'discount_amount' => $this->discountAmount,
                'total' => $this->total,
            ]);
        }

        $this->calculateTotal();

        $this->showDiscountModal = false;
    }

    public function removeCurrentDiscount()
    {
        $order = $this->tableOrderID ? $this->tableOrder->activeOrder : $this->orderDetail;

        if ($order) {
            $order->update([
                'discount_type' => null,
                'discount_value' => null,
                'discount_amount' => null,
            ]);
        }

        $this->discountType = null;
        $this->discountValue = null;
        $this->discountAmount = null;
        $this->calculateTotal();
    }

    public function removeExtraCharge($chargeId, $orderType)
    {
        $order = $this->tableOrderID ? $this->tableOrder->activeOrder : $this->orderDetail;

        if ($order) {
            $extraCharge = $this->extraCharges->firstWhere('id', $chargeId);
            if ($extraCharge) {
                $order->extraCharges()->detach($chargeId);
                $this->total -= $extraCharge->getAmount($this->discountedTotal);
                $order->update(['total' => $this->total]);
            }
        }

        $this->extraCharges = $this->extraCharges->filter(function ($charge) use ($chargeId) {
            return $charge->id != $chargeId;
        });

        $this->calculateTotal();
    }

    public function saveOrder($action, $secondAction = null, $thirdAction = null)
    {


        $this->showErrorModal = true;

        $rules = [
            // 'noOfPax' => 'required_if:orderType,dine_in|numeric',
            // 'tableNo' => 'required_if:orderType,dine_in',
            'selectDeliveryExecutive' => Rule::requiredIf($action !== 'cancel' && $this->orderType === 'delivery'),
            'orderItemList' => 'required',
            'deliveryFee' => 'nullable|numeric|min:0',
        ];

        if (!$this->orderID && !$this->tableOrderID) {
            $rules['selectWaiter'] = 'required_if:orderType,dine_in';
        }

        $messages = [
            'noOfPax.required_if' => __('messages.enterPax'),
            'tableNo.required_if' => __('messages.setTableNo'),
            'selectWaiter.required_if' => __('messages.selectWaiter'),
            'orderItemList.required' => __('messages.orderItemRequired'),
        ];

        $this->validate($rules, $messages);

        switch ($action) {
            case 'bill':
                $successMessage = __('messages.billedSuccess');
                $status = 'billed';
                $tableStatus = 'running';
                break;

            case 'kot':
                $successMessage = __('messages.kotGenerated');
                $status = 'kot';
                $tableStatus = 'running';
                break;


            case 'cancel':
                $successMessage = __('messages.orderCanceled');
                $status = 'canceled';
                $tableStatus = 'available';
                break;
        }

        // Get order type name if not already set
        $orderTypeName = $this->orderType;
        if ($this->orderTypeId) {
            $orderType = OrderType::select('order_type_name')->find($this->orderTypeId);
            $orderTypeName = $orderType->order_type_name ?? $orderTypeName;
        }

        if ((!$this->tableOrderID && !$this->orderID) || ($this->tableOrderID && !$this->tableOrder->activeOrder)) {

            $orderNumberData = Order::generateOrderNumber(branch());
            $table = Table::find($this->tableId);
            $reservationId = $table?->activeReservation?->id;

            // Check if there's an active reservation and show confirmation modal
            if ($reservationId && $this->orderType === 'dine_in' && !$this->isSameCustomer && !$this->intendedOrderAction) {
                $this->reservationId = $reservationId;
                $this->reservationCustomer = $table->activeReservation->customer;
                $this->reservation = $table->activeReservation;
                $this->showReservationModal = true;
                $this->intendedOrderAction = $action; // Store the intended action
                return;
            }
            $order = Order::create([
                'order_number' => $orderNumberData['order_number'],
                'formatted_order_number' => $orderNumberData['formatted_order_number'],
                'date_time' => now(),
                'table_id' => $this->tableId,
                'number_of_pax' => $this->noOfPax,
                'discount_type' => $this->discountType,
                'discount_value' => $this->discountValue,
                'discount_amount' => $this->discountAmount,
                'waiter_id' => $this->selectWaiter,
                'sub_total' => $this->subTotal,
                'total' => $this->total,
                'order_type' => $this->orderType,
                'order_type_id' => $this->orderTypeId,
                'custom_order_type_name' => $orderTypeName,
                'pickup_date' => $this->orderType === 'pickup' ? $this->deliveryDateTime : null,
                'delivery_executive_id' => ($this->orderType == 'delivery' ? $this->selectDeliveryExecutive : null),
                'delivery_fee' => ($this->orderType == 'delivery' ? $this->deliveryFee : 0),
                'status' => $status,
                'order_status' => $this->orderStatus ?? 'preparing',
                'placed_via' => 'pos',
                'tax_mode' => $this->taxMode,
                'reservation_id' => $this->isSameCustomer ? $this->reservationId : null,
                'customer_id' => $this->isSameCustomer ? $this->reservationCustomer->id : null,
            ]);

            if (!empty($this->extraCharges)) {
                $chargesData = collect($this->extraCharges)
                    ->map(fn($charge) => [
                        'charge_id' => $charge->id,
                    ])->toArray();

                $order->charges()->createMany($chargesData);
            }

            // Reset reservation properties after order creation
            $this->resetReservationProperties();
        } else {

            if ($this->orderID) {
                $this->orderDetail = Order::find($this->orderID);
            }

            $order = ($this->tableOrderID ? $this->tableOrder->activeOrder : $this->orderDetail);
            Order::where('id', $order->id)->update([
                'date_time' => now(),
                'order_type' => $this->orderType,
                'order_type_id' => $this->orderTypeId,
                'custom_order_type_name' => $orderTypeName,
                'delivery_executive_id' => ($this->orderType == 'delivery' ? $this->selectDeliveryExecutive : null),
                'number_of_pax' => $this->noOfPax,
                'waiter_id' => $this->selectWaiter,
                'pickup_date' => $this->orderType === 'pickup' ? $this->deliveryDateTime : null,
                'table_id' => $this->tableId ?? $order->table_id,
                'sub_total' => $this->subTotal,
                'total' => $this->total,
                'delivery_fee' => ($this->orderType == 'delivery' ? $this->deliveryFee : 0),
                'status' => $status,
                'order_status' => $this->orderStatus ?? 'preparing'
            ]);

            $order->items()->delete();
            $order->taxes()->delete();
        }

        if ($status == 'canceled') {
            $order->delete();

            Table::where('id', $this->tableId)->update([
                'available_status' => $tableStatus
            ]);
            return $this->redirect(route('pos.index'), navigate: true);
        }

        // Handle KOT creation and totals calculation

        $kot = null;
        $kotIds = [];
        if ($status == 'kot') {
            if (in_array('Kitchen', restaurant_modules()) && in_array('kitchen', custom_module_plugins())) {
                // Group items by kot_place_id
                $groupedItems = [];

                foreach ($this->orderItemList as $key => $item) {
                    $menuItem = $this->orderItemVariation[$key]->menuItem ?? $item;
                    $kotPlaceId = $menuItem->kot_place_id ?? null;

                    if (!$kotPlaceId) {
                        continue;
                    }

                    $groupedItems[$kotPlaceId][] = [
                        'key' => $key,
                        'menu_item_id' => $menuItem->id,
                        'variation_id' => $this->orderItemVariation[$key]->id ?? null,
                        'quantity' => $this->orderItemQty[$key],
                        'modifiers' => $this->itemModifiersSelected[$key] ?? [],
                        'note' => $this->itemNotes[$key] ?? null,
                    ];
                }

                foreach ($groupedItems as $kotPlaceId => $items) {
                    $kot = Kot::create([
                        'kot_number' => Kot::generateKotNumber($order->branch),
                        'order_id' => $order->id,
                        'kitchen_place_id' => $kotPlaceId,
                        'note' => $this->orderNote,
                    ]);

                    $kotIds[] = $kot->id;

                    foreach ($items as $item) {
                        $kotItem = KotItem::create([
                            'kot_id' => $kot->id,
                            'menu_item_id' => $item['menu_item_id'],
                            'menu_item_variation_id' => $item['variation_id'],
                            'quantity' => $item['quantity'],
                            'note' => $item['note'],
                            'order_type_id' => $order->order_type_id ?? null,
                            'order_type' => $order->order_type ?? null,
                            'note' => $item['note']
                        ]);
                        $kotItem->modifierOptions()->sync($item['modifiers']);
                    }
                }
            } else {
                // No kitchen module: single KOT for all items
                $kot = Kot::create([
                    'kot_number' => Kot::generateKotNumber($order->branch) + 1,
                    'order_id' => $order->id,
                    'note' => $this->orderNote
                ]);

                foreach ($this->orderItemList as $key => $value) {
                    $kotItem = KotItem::create([
                        'kot_id' => $kot->id,
                        'menu_item_id' => $this->orderItemVariation[$key]->menu_item_id ?? $value->id,
                        'menu_item_variation_id' => $this->orderItemVariation[$key]->id ?? null,
                        'quantity' => $this->orderItemQty[$key],
                        'note' => $this->itemNotes[$key] ?? null,
                        'order_type_id' => $order->order_type_id ?? null,
                        'order_type' => $order->order_type ?? null,
                    ]);
                    $kotItem->modifierOptions()->sync($this->itemModifiersSelected[$key] ?? []);
                }
            }

            // Recalculate totals after KOT creation if editing an existing order
            if ($this->orderID) {
                $this->total = 0;
                $this->subTotal = 0;

                foreach ($order->kot as $kot) {
                    foreach ($kot->items as $item) {
                        $menuItemPrice = $item->menuItem->price ?? 0;

                        // Add modifier prices if any
                        $modifierPrice = 0;
                        if ($item->modifierOptions->isNotEmpty()) {
                            $modifierPrice = $item->modifierOptions->sum('price');
                        }

                        $this->subTotal += ($menuItemPrice + $modifierPrice) * $item->quantity;
                        $this->total += ($menuItemPrice + $modifierPrice) * $item->quantity;
                    }
                }

                // Discount calculation
                $this->discountAmount = 0;

                if ($order->discount_type === 'percent') {
                    $this->discountAmount = round(($this->subTotal * $order->discount_value) / 100, 2);
                }
                elseif ($order->discount_type === 'fixed') {
                    $this->discountAmount = min($order->discount_value, $this->subTotal);
                }
                $this->discountedTotal = $this->total - $this->discountAmount;

                // Extra charges
                foreach ($order->extraCharges ?? [] as $charge) {
                    $this->total += $charge->getAmount($this->discountedTotal);
                }

                // Tip and delivery
                if ($this->tipAmount > 0) {
                    $this->total += $this->tipAmount;
                }

                if ($this->deliveryFee > 0) {
                    $this->total += $this->deliveryFee;
                }

                $this->total -= $this->discountAmount;

                // Calculate taxes using centralized method
                $this->recalculateTaxTotals();

                Order::where('id', $order->id)->update([
                    'sub_total' => $this->subTotal,
                    'total' => $this->total,
                    'discount_amount' => $this->discountAmount,
                    'total_tax_amount' => $this->totalTaxAmount,
                    'tax_mode' => $this->taxMode,
                ]);
            }

            if ($secondAction == 'bill' && $thirdAction == 'payment') {
                // Update order status to billed
                Order::where('id', $order->id)->update([
                    'status' => 'billed'
                ]);

                // Now bill the order
                foreach ($this->orderItemList as $key => $value) {
                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'menu_item_id' => (isset($this->orderItemVariation[$key]) ? $this->orderItemVariation[$key]->menu_item_id : $this->orderItemList[$key]->id),
                        'menu_item_variation_id' => (isset($this->orderItemVariation[$key]) ? $this->orderItemVariation[$key]->id : null),
                        'quantity' => $this->orderItemQty[$key],
                        'price' => (isset($this->orderItemVariation[$key]) ? $this->orderItemVariation[$key]->price : $value->price),
                        'amount' => $this->orderItemAmount[$key],
                    ]);
                    $this->itemModifiersSelected[$key] = $this->itemModifiersSelected[$key] ?? [];
                    $orderItem->modifierOptions()->sync($this->itemModifiersSelected[$key]);
                }

                if ($this->taxMode === 'order') {
                    foreach ($this->taxes as $key => $value) {
                        OrderTax::create([
                            'order_id' => $order->id,
                            'tax_id' => $value->id
                        ]);
                    }
                }

                // ... (repeat the billing total calculation logic as in the 'billed' case)
                // Then show the payment modal
                $this->dispatch('showPaymentModal', id: $order->id);

                $this->printKot($order, $kot);
                $this->printOrder($order);
                $this->resetPos();
                return;
            }
        }

        if ($status == 'billed') {

            foreach ($this->orderItemList as $key => $value) {
                $taxBreakup = isset($this->orderItemTaxDetails[$key]['tax_breakup']) ? json_encode($this->orderItemTaxDetails[$key]['tax_breakup']) : null;

                $orderItem = OrderItem::create([
                    'order_type' => $this->orderType,
                    'order_type_id' => $this->orderTypeId,
                    'order_id' => $order->id,
                    'menu_item_id' => (isset($this->orderItemVariation[$key]) ? $this->orderItemVariation[$key]->menu_item_id : $this->orderItemList[$key]->id),
                    'menu_item_variation_id' => (isset($this->orderItemVariation[$key]) ? $this->orderItemVariation[$key]->id : null),
                    'quantity' => $this->orderItemQty[$key],
                    'price' => (isset($this->orderItemVariation[$key]) ? $this->orderItemVariation[$key]->price : $value->price),
                    'amount' => $this->orderItemAmount[$key],
                    'note' => $this->itemNotes[$key] ?? null,
                    'tax_amount' => $this->orderItemTaxDetails[$key]['tax_amount'] ?? null,
                    'tax_percentage' => $this->orderItemTaxDetails[$key]['tax_percent'] ?? null,
                    'tax_breakup' => $taxBreakup,
                ]);

                $this->itemModifiersSelected[$key] = $this->itemModifiersSelected[$key] ?? [];
                $orderItem->modifierOptions()->sync($this->itemModifiersSelected[$key]);
            }

            if ($this->taxMode === 'order') {
                foreach ($this->taxes as $key => $value) {
                    OrderTax::create([
                        'order_id' => $order->id,
                        'tax_id' => $value->id
                    ]);
                }
            }

            $order->load('charges');

            $validCharges = collect($this->extraCharges ?? [])
                ->filter(fn($charge) => in_array($this->orderTypeSlug, $charge->order_types));

            $currentChargeIds = $order->charges->pluck('charge_id');
            $validChargeIds = $validCharges->pluck('id');

            // Remove invalid charges and add new valid charges
            $order->charges()->whereNotIn('charge_id', $validChargeIds)->delete();

            $validChargeIds->diff($currentChargeIds)->each(
                fn($chargeId) =>
                OrderCharge::create(['order_id' => $order->id, 'charge_id' => $chargeId])
            );

            $this->total = 0;
            $this->subTotal = 0;

            foreach ($order->load('items')->items as $value) {
                $this->subTotal = ($this->subTotal + $value->amount);
                $this->total = ($this->total + $value->amount);
            }

            $this->discountedTotal = $this->total;

            if ($order->discount_type === 'percent') {
                $this->discountAmount = round(($this->subTotal * $order->discount_value) / 100, 2);
            }
            elseif ($order->discount_type === 'fixed') {
                $this->discountAmount = min($order->discount_value, $this->subTotal);
            }

            $this->total -= $this->discountAmount;
            $this->discountedTotal = $this->total;
            // Use centralized tax calculation
            $this->recalculateTaxTotals();

            if ($this->taxMode === 'item' && (restaurant()->tax_inclusive ?? false)) {
                $this->subTotal -= $this->totalTaxAmount;
            }

            foreach ($this->extraCharges ?? [] as $value) {
                $this->total += $value->getAmount($this->discountedTotal);
            }

            if ($this->tipAmount > 0) {
                $this->total += $this->tipAmount;
            }

            if ($this->deliveryFee > 0) {
                $this->total += $this->deliveryFee;
            }

            Order::where('id', $order->id)->update([
                'sub_total' => $this->subTotal,
                'total' => $this->total,
                'discount_amount' => $this->discountAmount,
                'total_tax_amount' => $this->totalTaxAmount,
                'tax_mode' => $this->taxMode,
            ]);

            if ($order->placed_via == null || $order->placed_via == 'pos') {
                NewOrderCreated::dispatch($order);
            }

            // Do NOT call $this->resetPos() here!
            // The customer display will now show the thank you/payment screen.

            // Update customer display cache to set status to 'billed'
            $this->setCustomerDisplayStatus('billed');
        }

        Table::where('id', $this->tableId)->update([
            'available_status' => $tableStatus
        ]);

        $this->dispatch('posOrderSuccess');

        $this->alert('success', $successMessage, [
            'toast' => true,
            'position' => 'top-end',
            'showCancelButton' => false,
            'cancelButtonText' => __('app.close')
        ]);

        if ($status == 'kot') {
            if ($secondAction == 'print') {
                // Check if the 'kitchen' package is enabled
                $this->printKot($order, $kot, $kotIds);
            }

            if ($this->orderID) {
                return $this->redirect(route('pos.kot', $order->id) . '?show-order-detail=true', navigate: true);
            }

            $this->dispatch('resetPos');
            $this->dispatch('refreshPos');
            // return $this->redirect(route('kots.index'), navigate: true);
        }

        if ($status == 'billed') {
            // return $this->redirect(route('orders.index'), navigate: true);
            switch ($secondAction) {

                case 'payment':
                    $this->dispatch('showPaymentModal', id: $order->id);
                    break;
                case 'print':

                    $orderPlaces = \App\Models\MultipleOrder::with('printerSetting')->get();

                    foreach ($orderPlaces as $orderPlace) {
                        $printerSetting = $orderPlace->printerSetting;
                    }

                    switch ($printerSetting?->printing_choice) {
                        case 'directPrint':
                            $this->handleOrderPrint($order->id);
                            break;
                        default:
                            $url = route('orders.print', $order->id);
                            $this->dispatch('print_location', $url);
                            break;
                    }

                    $this->dispatch('resetPos');

                    try {

                        // switch ($printerSetting?->printing_choice) {
                        //     case 'directPrint':
                        //         $this->handleOrderPrint($order->id);
                        //         break;
                        //     default:
                        //         $url = route('orders.print', $order->id);
                        //         $this->dispatch('print_location', $url);
                        //         break;
                        // }
                    } catch (\Throwable $e) {
                        Log::info($e->getMessage());
                        $this->alert('error', __('messages.printerNotConnected') . ' ' . $e->getMessage(), [
                            'toast' => true,
                            'position' => 'top-end',
                            'showCancelButton' => false,
                            'cancelButtonText' => __('app.close')
                        ]);
                    }
            }

            // change
            if (!in_array($secondAction, ['payment', 'print'])) {
                $this->dispatch('showOrderDetail', id: $order->id, fromPos: true);
            }
        }

        // Handle default case outside the switch block

    }

    public function printOrder($order)
    {
        Log::info("printOrder called with Order ID: {$order->id}, Order Number: {$order->order_number}");

        $orderPlace = \App\Models\MultipleOrder::with('printerSetting')->first();

        $printerSetting = $orderPlace->printerSetting;

        switch ($printerSetting->printing_choice) {

            case 'directPrint':
                $this->handleOrderPrint($order->id);
                break;
            default:
                $url = route('orders.print', $order);
                $this->dispatch('print_location', $url);
                break;
        }
    }

    public function printKot($order, $kot = null, $kotIds = [])
    {
        // Check if the 'kitchen' package is enabled
        if (in_array('Kitchen', restaurant_modules()) && in_array('kitchen', custom_module_plugins())) {
            // Get all KOTs for this order (created above)

            if ($kotIds) {
                $kots = $order->kot()->whereIn('id', $kotIds)->with('items')->get();
            } else {
                $kots = $order->kot()->with('items')->get();
            }

            foreach ($kots as $kot) {
                $kotPlaceItems = [];

                foreach ($kot->items as $kotItem) {
                    if ($kotItem->menuItem && $kotItem->menuItem->kot_place_id) {
                        $kotPlaceId = $kotItem->menuItem->kot_place_id;

                        if (!isset($kotPlaceItems[$kotPlaceId])) {
                            $kotPlaceItems[$kotPlaceId] = [];
                        }

                        $kotPlaceItems[$kotPlaceId][] = $kotItem;
                    }
                }

                // Get the kot places and their printer settings
                $kotPlaceIds = array_keys($kotPlaceItems);
                $kotPlaces = KotPlace::with('printerSetting')->whereIn('id', $kotPlaceIds)->get();

                foreach ($kotPlaces as $kotPlace) {
                    $printerSetting = $kotPlace->printerSetting;

                    if ($printerSetting && $printerSetting->is_active == 0) {
                        $printerSetting = Printer::where('is_default', true)->first();
                    }

                    // If no printer is set, fallback to print URL dispatch
                    if (!$printerSetting) {
                        $url = route('kot.print', [$kot->id, $kotPlace?->id]);
                        $this->dispatch('print_location', $url);
                        continue;
                    }

                    try {
                        switch ($printerSetting->printing_choice) {
                            case 'directPrint':
                                $this->handleKotPrint($kot->id, $kotPlace->id);
                                break;
                            default:
                                $url = route('kot.print', [$kot->id, $kotPlace?->id]);
                                $this->dispatch('print_location', $url);
                                break;
                        }
                    } catch (\Throwable $e) {
                        $this->alert('error', __('messages.printerNotConnected') . ' ' . $e->getMessage(), [
                            'toast' => true,
                            'position' => 'top-end',
                            'showCancelButton' => false,
                            'cancelButtonText' => __('app.close')
                        ]);
                    }
                }
            }
        } else {
            $kotPlace = KotPlace::where('is_default', 1)->first();
            $printerSetting = $kotPlace->printerSetting;

            // Get the KOT for this order
            $kot = $kot ?? $order->kot()->first();

            // If no printer is set, fallback to print URL dispatch
            if (!$printerSetting) {
                $url = route('kot.print', [$kot->id, $kotPlace?->id]);
                $this->dispatch('print_location', $url);
            }

            try {
                switch ($printerSetting->printing_choice) {
                    case 'directPrint':
                        $this->handleKotPrint($kot->id, $kotPlace->id);
                        break;

                    default:
                        $url = route('kot.print', [$kot->id]);
                        $this->dispatch('print_location', $url);
                        break;
                }
            } catch (\Throwable $e) {
                $this->alert('error', __('messages.printerNotConnected') . ' ' . $e->getMessage(), [
                    'toast' => true,
                    'position' => 'top-end',
                    'showCancelButton' => false,
                    'cancelButtonText' => __('app.close')
                ]);
            }
        }
    }

        #[On('resetPos')]
    public function resetPos()
    {
        $this->search = null;
        $this->filterCategories = null;
        $this->menuItem = null;
        $this->subTotal = 0;
        $this->total = 0;
        $this->orderNumber = null;
        $this->formattedOrderNumber = null;
        $this->discountedTotal = 0;
        $this->tipAmount = 0;
        $this->deliveryFee = 0;
        $this->tableNo = null;
        $this->tableId = null;
        $this->noOfPax = null;
        $this->selectWaiter = user()->id;
        $this->orderItemList = [];
        $this->orderItemVariation = [];
        $this->orderItemQty = [];
        $this->orderItemAmount = [];
        // Set default order type to Dine In
        $defaultOrderType = OrderType::where('type', 'dine_in')
        ->where('is_active', true)
        ->first();

        if ($defaultOrderType) {
        $this->orderType = $defaultOrderType->type;
        $this->orderTypeSlug = $defaultOrderType->slug;
        $this->orderTypeId = $defaultOrderType->id;
        } else {
        //  if no default order type found
        $this->orderType = 'dine_in';
        $this->orderTypeSlug = 'dine_in';
        $this->orderTypeId = null;
        }

        $this->discountType = null;
        $this->discountValue = null;
        $this->showDiscountModal = false;
        $this->selectedModifierItem = null;
        $this->modifiers = null;
        $this->itemModifiersSelected = [];
        $this->discountAmount = null;
        $this->orderStatus;
        $this->showNewKotButton = false;
        $this->itemNotes = []; // Reset item notes
        $this->orderItemTaxDetails = [];
        $this->totalTaxAmount = 0;
        // Save empty cart state to cache for customer display
        $taxesForDisplay = $this->taxes->map(function ($tax) {
            return [
                'name' => $tax->tax_name,
                'percent' => $tax->tax_percent,
                'amount' => 0,
            ];
        })->toArray();
        $customerDisplayData = [
            'order_number' => $this->orderNumber,
            'formatted_order_number' => $this->formattedOrderNumber,
            'items' => [],
            'sub_total' => 0,
            'discount' => 0,
            'total' => 0,
            'taxes' => $taxesForDisplay,
            'extra_charges' => [],
            'tip' => 0,
            'delivery_fee' => 0,
            'order_type' => $this->orderType,
            'status' => $this->customerDisplayStatus ?? 'idle',
            'cash_due' => null,
        ];

        Cache::put('customer_display_cart', $customerDisplayData, now()->addMinutes(30));

        // Broadcast customer display update if Pusher is enabled
        if (pusherSettings()->is_enabled_pusher_broadcast) {
            broadcast(new \App\Events\CustomerDisplayUpdated($customerDisplayData));
        }
        // Optionally, still dispatch browser event
        $this->dispatch('orderUpdated', [
            'order_number' => $this->orderNumber,
            'formatted_order_number' => $this->formattedOrderNumber,
            'items' => [],
            'sub_total' => 0,
            'discount' => 0,
            'total' => 0,
        ]);
    }

    public function showAddDiscount()
    {
        $orderDetail = Order::find($this->orderID);
        $this->discountType = $orderDetail->discount_type ?? $this->discountType ?? 'fixed';
        $this->discountValue = $orderDetail->discount_value ?? $this->discountValue ?? null;
        $this->showDiscountModal = true;
    }

        #[On('closeModifiersModal')]
    public function closeModifiersModal()
    {
        $this->selectedModifierItem = null;
        $this->showModifiersModal = false;
    }

        #[On('setPosModifier')]
    public function setPosModifier($modifierIds)
    {
        $this->showModifiersModal = false;

        $sortNumber = Str::of(implode('', Arr::flatten($modifierIds)))
            ->split(1)->sort()->implode('');

        $keyId = $this->selectedModifierItem . '-' . $sortNumber;
        if (isset(explode('_', $this->selectedModifierItem)[1])) {
            $menuItemVariation = MenuItemVariation::find(explode('_', $this->selectedModifierItem)[1]);
            $this->orderItemVariation[$keyId] = $menuItemVariation;
            $this->selectedModifierItem = explode('_', $this->selectedModifierItem)[0];
            $this->orderItemAmount[$keyId] = 1 * ($this->orderItemVariation[$keyId]->price ?? $this->orderItemList[$keyId]->price);
        }

        $this->itemModifiersSelected[$keyId] = Arr::flatten($modifierIds);
        $this->orderItemQty[$this->selectedModifierItem] = isset($this->orderItemQty[$this->selectedModifierItem]) ? ($this->orderItemQty[$this->selectedModifierItem] + 1) : 1;

        $modifierTotal = collect($this->itemModifiersSelected[$keyId])
            ->sum(fn($modifierId) => $this->getModifierOptionsProperty()[$modifierId]->price);

        $this->orderItemModifiersPrice[$keyId] = (1 * (isset($this->itemModifiersSelected[$keyId]) ? $modifierTotal : 0));

        $this->syncCart($keyId);
    }

    public function getModifierOptionsProperty()
    {
        return ModifierOption::whereIn('id', collect($this->itemModifiersSelected)->flatten()->all())->get()->keyBy('id');
    }

    public function saveDeliveryExecutive()
    {
        $this->orderDetail->update(['delivery_executive_id' => $this->selectDeliveryExecutive]);
        $this->orderDetail->refresh();
        $this->alert('success', __('messages.deliveryExecutiveAssigned'), [
            'toast' => true,
            'position' => 'top-end',
            'showCancelButton' => false,
            'cancelButtonText' => __('app.close')
        ]);
    }

    public function cancelOrder()
    {
        if (!$this->cancelReason && !$this->cancelReasonText) {
            $this->alert('error', __('modules.settings.cancelReasonRequired'), [
            'toast' => true,
            'position' => 'top-end',
            'showCancelButton' => false,
            'cancelButtonText' => __('app.close'),
            ]);

            return;
        }

        if ($this->orderID) {
            $order = Order::find($this->orderID);

            if ($order) {

                $order->update([
                'status' => 'canceled',
                'order_status' => 'cancelled',
                'cancel_reason_id' => $this->cancelReason,
                'cancel_reason_text' => $this->cancelReasonText ?? null ,
                ]);

                Table::where('id', $order->table_id)->update([
                    'available_status' => 'available',
                ]);

                $this->alert('success', __('messages.orderCanceled'), [
                    'toast' => true,
                    'position' => 'top-end',
                    'showCancelButton' => false,
                    'cancelButtonText' => __('app.close'),
                ]);

                $this->confirmDeleteModal = false;
                $this->cancelReason = null;
                $this->cancelReasonText = null;

                return $this->redirect(route('pos.index'), navigate: true);
            }
        }
    }

    public function updatedSelectWaiter($value)
    {

        if ($this->orderID) {
            $order = Order::find($this->orderID);


            if ($order) {
                $order->update(['waiter_id' => is_int($value) ? $value : null]);
                $this->alert('success', __('messages.waiterUpdated'), [
                    'toast' => true,
                    'position' => 'top-end',
                    'showCancelButton' => false,
                    'cancelButtonText' => __('app.close'),
                ]);
            } else {
                $this->selectWaiter = $order->waiter_id;
            }
        }
    }

    public function closeErrorModal()
    {
        $this->showErrorModal = false;
        $this->showNewKotButton = false;
    }

    public function render()
    {
        // Only generate order number if there is no existing order or table order without active order
        if ((!$this->orderID && !$this->tableOrderID) || ($this->tableOrderID && !$this->tableOrder->activeOrder)) {
            $orderNumberData = Order::generateOrderNumber(branch());
            $this->orderNumber = $orderNumberData['order_number'];
            $this->formattedOrderNumber = $orderNumberData['formatted_order_number'];
        }

        $query = MenuItem::withCount('variations', 'modifierGroups');

        if (!empty($this->filterCategories)) {
            $query = $query->where('item_category_id', $this->filterCategories);
        }

        $query = $query->search('item_name', $this->search)->get();
        $showCustomOrderTypes = restaurant()->show_order_type_options;
        $orderTypes = OrderType::where('branch_id', branch()->id)
            ->where('is_active', true)
            ->when(!$showCustomOrderTypes, fn($q) => $q->where('is_default', true))
            ->get();

        return view('livewire.pos.pos', [
        'menuItems' => $query,
        'orderTypes' => $orderTypes
        ]);
    }

        // Update item notes and save to database if applicable
    public function updateItemNote($itemId, $note)
    {
        $this->itemNotes[$itemId] = $note;

        if (!$this->orderDetail) {
            return;
        }

        // Extract the KOT ID and item ID from the itemId string
        $parts = explode('_', str_replace('"', '', $itemId));

        if (count($parts) < 3 || $parts[0] !== 'kot') {
            return;
        }

        KotItem::where('kot_id', $parts[1])
            ->where('id', $parts[2])
            ->update(['note' => $note]);
    }

    public function updateOrderItemTaxDetails()
    {
        $this->orderItemTaxDetails = [];

        if ($this->taxMode !== 'item' || !is_array($this->orderItemAmount)) {
            return;
        }

        foreach ($this->orderItemAmount as $key => $value) {
            $menuItem = isset($this->orderItemVariation[$key]) ? $this->orderItemVariation[$key]->menuItem : $this->orderItemList[$key];
            $qty = $this->orderItemQty[$key] ?? 1;
            $basePrice = isset($this->orderItemVariation[$key]) ? $this->orderItemVariation[$key]->price : $menuItem->price;
            $modifierPrice = $this->orderItemModifiersPrice[$key] ?? 0;
            $itemPriceWithModifiers = $basePrice + $modifierPrice;
            $taxes = $menuItem->taxes ?? collect();
            $isInclusive = restaurant()->tax_inclusive;
            $taxResult = MenuItem::calculateItemTaxes($itemPriceWithModifiers, $taxes, $isInclusive);
            $this->orderItemTaxDetails[$key] = [
                'tax_amount' => $taxResult['tax_amount'] * $qty,
                'tax_percent' => $taxResult['tax_percentage'],
                'tax_breakup' => $taxResult['tax_breakdown'],
                'tax_type' => $taxResult['inclusive'],
                'base_price' => $itemPriceWithModifiers,
                'display_price' => $isInclusive ? ($itemPriceWithModifiers - ($taxResult['tax_amount'] ?? 0)) : $itemPriceWithModifiers,
                'qty' => $qty,
            ];
        }
    }

    /**
     * Get the display price for an item (base price without tax for inclusive items)
     */
    public function getItemDisplayPrice($key)
    {
        if ($this->taxMode === 'item' && isset($this->orderItemTaxDetails[$key])) {
            return $this->orderItemTaxDetails[$key]['display_price'] ?? 0;
        }

        // Check if we have session data arrays (for active POS session)
        if (isset($this->orderItemList[$key])) {
            $basePrice = isset($this->orderItemVariation[$key]) ? $this->orderItemVariation[$key]->price : $this->orderItemList[$key]->price;
            $modifierPrice = $this->orderItemModifiersPrice[$key] ?? 0;
            return $basePrice + $modifierPrice;
        }

        // For existing order items (when viewing order details), calculate from the order item itself
        if ($this->orderDetail && isset($this->orderDetail->items[$key])) {
            $orderItem = $this->orderDetail->items[$key];
            $basePrice = !is_null($orderItem->menuItemVariation) ? $orderItem->menuItemVariation->price : $orderItem->menuItem->price;
            $modifierPrice = $orderItem->modifierOptions->sum('price');

            // If tax is inclusive, calculate the display price without tax
            if (restaurant()->tax_inclusive && restaurant()->tax_mode === 'item') {
                $menuItem = $orderItem->menuItem;
                $taxes = $menuItem->taxes ?? collect();
                $itemPriceWithModifiers = $basePrice + $modifierPrice;

                if ($taxes->isNotEmpty()) {
                    $taxPercent = $taxes->sum('tax_percent');
                    $displayPrice = $itemPriceWithModifiers / (1 + $taxPercent / 100);
                    return $displayPrice;
                }
            }

            return $basePrice + $modifierPrice;
        }

        return 0;
    }

    // Add a helper to format items for customer display
    private function getCustomerDisplayItems()
    {
        $items = [];
        foreach ($this->orderItemList as $key => $item) {
            $variation = $this->orderItemVariation[$key] ?? null;
            $basePrice = $variation->price ?? $item->price ?? 0;
            $modifiers = [];
            $modifierTotal = 0;
            if (!empty($this->itemModifiersSelected[$key])) {
                foreach ($this->itemModifiersSelected[$key] as $modifierId) {
                    $modifier = \App\Models\ModifierOption::find($modifierId);
                    if ($modifier) {
                        $modifiers[] = [
                            'name' => $modifier->name,
                            'price' => $modifier->price,
                        ];
                        $modifierTotal += $modifier->price;
                    }
                }
            }
            $totalUnitPrice = $basePrice + $modifierTotal;
            $items[] = [
                'name' => $item->item_name ?? ($item['name'] ?? 'Item'),
                'qty' => $this->orderItemQty[$key] ?? 1,
                'price' => $basePrice, // keep for reference
                'total_unit_price' => $totalUnitPrice, // <-- add this
                'variation' => $variation ? [
                    'name' => $variation->variation ?? null,
                    'price' => $variation->price ?? null,
                ] : null,
                'modifiers' => $modifiers,
                'notes' => $this->itemNotes[$key] ?? null,
            ];
        }
        return $items;
    }

    public function newOrder()
    {
        $this->resetPos();

        // Set the default order type after reset
        $defaultOrderType = OrderType::where('branch_id', branch()->id)
            ->where('is_active', true)
            ->first();

        if ($defaultOrderType) {
            $this->orderTypeId = $defaultOrderType->id;
            $this->orderType = $defaultOrderType->type;
            $this->orderTypeSlug = $defaultOrderType->slug;
        }

        $this->setCustomerDisplayStatus('idle');
        $this->calculateTotal();
    }

    public function updateQty($id)
    {
        if (($this->orderID && !user_can('Update Order')) || (!$this->orderID && !user_can('Create Order'))) {
            return;
        }
        // Ensure quantity is at least 1
        $this->orderItemQty[$id] = max(1, intval($this->orderItemQty[$id]));

        // Update the amount based on the new quantity
        $basePrice = $this->orderItemVariation[$id]->price ?? $this->orderItemList[$id]->price;
        $this->orderItemAmount[$id] = $this->orderItemQty[$id] * ($basePrice + ($this->orderItemModifiersPrice[$id] ?? 0));

        // Recalculate the total
        $this->calculateTotal();
    }

    /**
     * Set the customer display status and immediately update the cache.
     */
    public function setCustomerDisplayStatus($status)
    {
        $this->customerDisplayStatus = $status;
        $this->calculateTotal();
    }

    /**
     * Confirm that the customer is the same as the reservation
     */
    public function confirmSameCustomer()
    {
        $this->isSameCustomer = true;
        $this->showReservationModal = false;
        $this->saveOrder($this->intendedOrderAction ?? 'kot');
    }

    /**
     * Confirm that the customer is different from the reservation
     */
    public function confirmDifferentCustomer()
    {
        $this->isSameCustomer = false;
        $this->showReservationModal = false;
        $this->saveOrder($this->intendedOrderAction ?? 'kot');
    }

    /**
     * Close the reservation modal
     */
    public function closeReservationModal()
    {
        $this->showReservationModal = false;
        $this->reservationId = null;
        $this->reservationCustomer = null;
        $this->reservation = null;
        $this->isSameCustomer = false;
        $this->intendedOrderAction = null;
    }

    /**
     * Reset reservation properties
     */
    public function resetReservationProperties()
    {
        $this->reservationId = null;
        $this->reservationCustomer = null;
        $this->reservation = null;
        $this->isSameCustomer = false;
        $this->intendedOrderAction = null;
    }

    /**
     * Load thermal printers for current restaurant
     */
    public function loadThermalPrinters()
    {
        try {
            $this->thermalPrinters = \App\Models\ThermalPrinter::where('restaurant_id', auth()->user()?->restaurant_id ?? 1)
                ->where('is_active', true)
                ->orderBy('is_default', 'desc')
                ->orderBy('name')
                ->get()
                ->toArray();

            // Set default printer if available
            $defaultPrinter = collect($this->thermalPrinters)->where('is_default', true)->first();
            if ($defaultPrinter) {
                $this->selectedThermalPrinter = $defaultPrinter['id'];
            }

            Log::info('Thermal printers loaded', [
                'count' => count($this->thermalPrinters),
                'default_printer' => $this->selectedThermalPrinter
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to load thermal printers: ' . $e->getMessage());
            $this->thermalPrinters = [];
        }
    }

    /**
     * Show thermal print modal
     */
    public function showThermalPrintModal($type = 'receipt', $orderId = null)
    {
        try {
            $this->thermalPrintType = $type;
            $this->showThermalPrintModal = true;

            // Reload printers to ensure we have the latest data
            $this->loadThermalPrinters();

            Log::info('Thermal print modal opened', [
                 'type' => $type,
                 'order_id' => $orderId,
                 'available_printers' => count($this->thermalPrinters)
             ]);

        } catch (\Exception $e) {
            Log::error('Failed to show thermal print modal: ' . $e->getMessage());
            $this->alert('error', 'Failed to open print dialog');
        }
    }

    /**
     * Print via thermal printer
     */
    public function printViaThermalPrinter($orderId = null)
    {
        try {
            if (!$this->selectedThermalPrinter) {
                $this->alert('error', 'Please select a thermal printer');
                return;
            }

            $printer = \App\Models\ThermalPrinter::find($this->selectedThermalPrinter);
            if (!$printer) {
                $this->alert('error', 'Selected printer not found');
                return;
            }

            // Use current order if no specific order ID provided
            $targetOrderId = $orderId ?? $this->orderID;
            if (!$targetOrderId) {
                $this->alert('error', 'No order selected for printing');
                return;
            }

            // Call thermal print controller
            $response = app(\App\Http\Controllers\ThermalPrintController::class)
                ->printReceipt($targetOrderId, $this->selectedThermalPrinter);

            if ($response->getStatusCode() === 200) {
                $this->alert('success', 'Print job sent successfully');
                $this->showThermalPrintModal = false;
            } else {
                $this->alert('error', 'Failed to send print job');
            }

            Log::info('Thermal print initiated', [
                'order_id' => $targetOrderId,
                'printer_id' => $this->selectedThermalPrinter,
                'type' => $this->thermalPrintType
            ]);

        } catch (\Exception $e) {
            Log::error('Thermal print failed: ' . $e->getMessage());
            $this->alert('error', 'Print failed: ' . $e->getMessage());
        }
    }

    /**
     * Print KOT via thermal printer
     */
    public function printKotViaThermalPrinter($kotId = null)
    {
        try {
            if (!$this->selectedThermalPrinter) {
                $this->alert('error', 'Please select a thermal printer');
                return;
            }

            $printer = \App\Models\ThermalPrinter::find($this->selectedThermalPrinter);
            if (!$printer) {
                $this->alert('error', 'Selected printer not found');
                return;
            }

            // Call thermal print controller for KOT
            $response = app(\App\Http\Controllers\ThermalPrintController::class)
                ->printKot($kotId ?? $this->orderID, $this->selectedThermalPrinter);

            if ($response->getStatusCode() === 200) {
                $this->alert('success', 'KOT print job sent successfully');
                $this->showThermalPrintModal = false;
            } else {
                $this->alert('error', 'Failed to send KOT print job');
            }

            Log::info('Thermal KOT print initiated', [
                'kot_id' => $kotId,
                'printer_id' => $this->selectedThermalPrinter
            ]);

        } catch (\Exception $e) {
            Log::error('Thermal KOT print failed: ' . $e->getMessage());
            $this->alert('error', 'KOT print failed: ' . $e->getMessage());
        }
    }

    /**
     * Test thermal printer
     */
    public function testThermalPrinter()
    {
        try {
            if (!$this->selectedThermalPrinter) {
                $this->alert('error', 'Please select a thermal printer');
                return;
            }

            $printer = \App\Models\ThermalPrinter::find($this->selectedThermalPrinter);
            if (!$printer) {
                $this->alert('error', 'Selected printer not found');
                return;
            }

            // Call thermal print controller for test print
            $response = app(\App\Http\Controllers\ThermalPrintController::class)
                ->testPrint($this->selectedThermalPrinter);

            if ($response->getStatusCode() === 200) {
                $this->alert('success', 'Test print sent successfully');
            } else {
                $this->alert('error', 'Test print failed');
            }

            Log::info('Thermal printer test initiated', [
                'printer_id' => $this->selectedThermalPrinter
            ]);

        } catch (\Exception $e) {
            Log::error('Thermal printer test failed: ' . $e->getMessage());
            $this->alert('error', 'Test print failed: ' . $e->getMessage());
        }
    }

    /**
     * Close thermal print modal
     */
    public function closeThermalPrintModal()
    {
        $this->showThermalPrintModal = false;
        $this->thermalPrintType = 'receipt';
    }
}
