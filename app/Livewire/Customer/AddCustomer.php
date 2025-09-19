<?php
namespace App\Livewire\Customer;

use App\Models\Order;
use Livewire\Component;
use App\Models\Customer;
use Livewire\Attributes\On;
use Jantinnerezo\LivewireAlert\LivewireAlert;

class AddCustomer extends Component
{
    use LivewireAlert;

    public $order;
    public $customerName;
    public $customerPhone;
    public $customerEmail;
    public $availableResults = [];
    public $customerAddress;
    public $showAddCustomerModal = false;
    public $fromPos;
    public $selectedCustomerId = null;

    #[On('showAddCustomerModal')]
    public function showAddCustomer($id = null , $customerId = null, $fromPos = false)
    {
        if (!is_null($id)) {
            $this->order = Order::find($id);
        }

        if (!is_null($customerId)) {
            $customer = Customer::find($customerId);
            if ($customer) {
                $this->customerName = $customer->name;
                $this->customerPhone = $customer->phone;
                $this->customerEmail = $customer->email;
                $this->customerAddress = $customer->delivery_address;
            }
        }
        $this->fromPos = $fromPos ?? false;
        $this->showAddCustomerModal = true;
    }

    public function updatedCustomerName()
    {
        if (strlen($this->customerName) >= 2) {
            $this->availableResults = $this->fetchSearchResults();
        } else {
            $this->availableResults = [];
        }
    }

    public function fetchSearchResults()
    {

        $results = Customer::where('restaurant_id', restaurant()->id)
            ->where(function ($query) {
                $query->where('name', 'like', '%' . $this->customerName . '%')
                    ->orWhere('phone', 'like', '%' . $this->customerName . '%')
                    ->orWhere('email', 'like', '%' . $this->customerName . '%');
            })->get();

        return $results;
    }

    public function selectCustomer($customerId)
    {
        $customer = Customer::find($customerId);

        if ($customer) {
            $this->selectedCustomerId = $customer->id;
            $this->customerName = $customer->name;
            $this->customerPhone = $customer->phone;
            $this->customerEmail = $customer->email;
            $this->customerAddress = $customer->delivery_address;
            $this->resetSearch();
        }
    }

    public function submitForm()
    {
        $this->validate([
            'customerName' => 'required'
        ]);

        // Optimized: Find existing customer by priority (email > phone > id/name)
        $existingCustomer = null;
        $query = Customer::where('restaurant_id', restaurant()->id);

        if (!empty($this->customerEmail)) {
            $query->where('email', $this->customerEmail);
        } elseif (!empty($this->customerPhone)) {
            $query->where('phone', $this->customerPhone);
        } elseif (!empty($this->selectedCustomerId)) {
            $query->where('name', $this->customerName);
        } else {
            $query = null;
        }

        if ($query) {
            $existingCustomer = $query->first();
        }


        $customerData = [
            'name' => $this->customerName,
        ];

        foreach ([
            'phone' => $this->customerPhone,
            'email' => $this->customerEmail,
            'delivery_address' => $this->customerAddress
        ] as $field => $value) {
            if (!empty($value)) {
                $customerData[$field] = $value;
            }
        }

        // Update or create the customer
        $customer = $existingCustomer
            ? tap($existingCustomer)->update($customerData)
            : Customer::create($customerData);

        if (!is_null($this->order)) {
            $this->order->customer_id = $customer->id;
            $this->order->delivery_address = $this->customerAddress;
            $this->order->save();

            if (!$this->fromPos) {
                $this->dispatch('showOrderDetail', id: $this->order->id);
            }
            $this->dispatch('refreshOrders');
            $this->dispatch('refreshPos');
        }

        $this->resetForm();
    }

    public function resetSearch()
    {
        $this->availableResults = [];
    }

    public function resetForm()
    {
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerEmail = '';
        $this->customerAddress = '';
        $this->showAddCustomerModal = false;
    }

    public function render()
    {
        return view('livewire.customer.add-customer');
    }
}
