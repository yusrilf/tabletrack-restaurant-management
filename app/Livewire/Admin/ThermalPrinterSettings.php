<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\ThermalPrinter;
use App\Services\ThermalPrinterService;
use App\Services\BluetoothBridgeService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Thermal Printer Settings Component
 * 
 * Manages thermal printer configurations for restaurants including:
 * - Adding/editing/deleting printers
 * - Testing printer connections
 * - Discovering bluetooth printers
 * - Managing printer settings
 */
class ThermalPrinterSettings extends Component
{
    // Component state
    public $printers = [];
    public $selectedPrinter = null;
    public $showModal = false;
    public $modalMode = 'create'; // create, edit, test
    public $editingPrinterId = null;
    public $isLoading = false;
    public $testResult = null;
    public $discoveredPrinters = [];
    public $bluetoothDevices = [];
    public $showDiscovery = false;
    
    // Form fields
    public $name = '';
    public $device_address = '';
    public $connection_type = 'bluetooth';
    public $paper_size = '80mm';
    public $is_default = false;
    public $is_active = true;
    
    // Settings
    public $settings = [
        'charset' => 'UTF-8',
        'timeout' => 30,
        'retry_attempts' => 3,
        'auto_cut' => true,
        'print_logo' => false,
        'print_header' => true,
        'print_footer' => true,
        'font_size' => 'normal',
        'line_spacing' => 'normal',
        'kot_enabled' => true,
        'receipt_enabled' => true,
        'order_enabled' => true
    ];
    
    protected $rules = [
        'name' => 'required|string|max:255',
        'device_address' => 'nullable|string|max:255',
        'connection_type' => 'required|in:bluetooth,network,usb,web_bluetooth',
        'paper_size' => 'required|in:58mm,80mm',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'settings.charset' => 'required|string',
        'settings.timeout' => 'required|integer|min:5|max:120',
        'settings.retry_attempts' => 'required|integer|min:1|max:10',
        'settings.auto_cut' => 'boolean',
        'settings.print_logo' => 'boolean',
        'settings.print_header' => 'boolean',
        'settings.print_footer' => 'boolean',
        'settings.font_size' => 'required|in:small,normal,large',
        'settings.line_spacing' => 'required|in:tight,normal,loose',
        'settings.kot_enabled' => 'boolean',
        'settings.receipt_enabled' => 'boolean',
        'settings.order_enabled' => 'boolean'
    ];
    
    protected $messages = [
        'name.required' => 'Printer name is required',
        'connection_type.required' => 'Connection type is required',
        'paper_size.required' => 'Paper size is required',
        'settings.timeout.min' => 'Timeout must be at least 5 seconds',
        'settings.timeout.max' => 'Timeout cannot exceed 120 seconds',
        'settings.retry_attempts.min' => 'Retry attempts must be at least 1',
        'settings.retry_attempts.max' => 'Retry attempts cannot exceed 10'
    ];

    /**
     * Component initialization
     */
    public function mount(): void
    {
        $this->loadPrinters();
        
        Log::info('ThermalPrinterSettings component mounted', [
            'restaurant_id' => auth()->user()->restaurant_id
        ]);
    }

    /**
     * Load printers for current restaurant
     */
    public function loadPrinters(): void
    {
        try {
            $this->printers = ThermalPrinter::getAvailableForRestaurant(
                auth()->user()?->restaurant_id ?? 1
            )->toArray();
            
        } catch (\Exception $e) {
            Log::error('Failed to load thermal printers', [
                'error' => $e->getMessage()
            ]);
            
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Failed to load printers: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Open modal for creating new printer
     */
    public function createPrinter(): void
    {
        $this->resetForm();
        $this->modalMode = 'create';
        $this->editingPrinterId = null;
        $this->showModal = true;
        
        Log::info('Create printer modal opened');
    }

    /**
     * Open add modal (alias for createPrinter)
     */
    public function openAddModal(): void
    {
        $this->createPrinter();
    }

    /**
     * Open modal for editing printer
     */
    public function editPrinter(int $printerId): void
    {
        try {
            $printer = ThermalPrinter::findOrFail($printerId);
            
            $this->selectedPrinter = $printer;
            $this->editingPrinterId = $printerId;
            $this->name = $printer->name;
            $this->device_address = $printer->device_address;
            $this->connection_type = $printer->connection_type;
            $this->paper_size = $printer->paper_size;
            $this->is_default = $printer->is_default;
            $this->is_active = $printer->is_active;
            $this->settings = array_merge($this->settings, $printer->settings);
            
            $this->modalMode = 'edit';
            $this->showModal = true;
            
            Log::info('Edit printer modal opened', ['printer_id' => $printerId]);
            
        } catch (\Exception $e) {
            Log::error('Failed to load printer for editing', [
                'printer_id' => $printerId,
                'error' => $e->getMessage()
            ]);
            
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Failed to load printer: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Save printer (create or update)
     */
    public function savePrinter(): void
    {
        $this->validate();
        
        try {
            $this->isLoading = true;
            
            $data = [
                'restaurant_id' => auth()->user()?->restaurant_id ?? 1,
                'name' => $this->name,
                'device_address' => $this->device_address,
                'connection_type' => $this->connection_type,
                'paper_size' => $this->paper_size,
                'is_default' => $this->is_default,
                'is_active' => $this->is_active,
                'settings' => $this->settings
            ];
            
            if ($this->modalMode === 'create') {
                $printer = ThermalPrinter::create($data);
                
                Log::info('Thermal printer created', [
                    'printer_id' => $printer->id,
                    'name' => $printer->name
                ]);
                
                $this->dispatch('show-toast', [
                    'type' => 'success',
                    'message' => 'Printer created successfully'
                ]);
                
            } else {
                $this->selectedPrinter->update($data);
                
                Log::info('Thermal printer updated', [
                    'printer_id' => $this->selectedPrinter->id,
                    'name' => $this->selectedPrinter->name
                ]);
                
                $this->dispatch('show-toast', [
                    'type' => 'success',
                    'message' => 'Printer updated successfully'
                ]);
            }
            
            $this->loadPrinters();
            $this->closeModal();
            
        } catch (\Exception $e) {
            Log::error('Failed to save thermal printer', [
                'mode' => $this->modalMode,
                'error' => $e->getMessage()
            ]);
            
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Failed to save printer: ' . $e->getMessage()
            ]);
            
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Delete printer
     */
    public function deletePrinter(int $printerId): void
    {
        try {
            $printer = ThermalPrinter::findOrFail($printerId);
            
            // Prevent deleting default printer if it's the only one
            if ($printer->is_default && count($this->printers) === 1) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Cannot delete the only printer'
                ]);
                return;
            }
            
            $printer->delete();
            
            Log::info('Thermal printer deleted', [
                'printer_id' => $printerId,
                'name' => $printer->name
            ]);
            
            $this->dispatch('show-toast', [
                'type' => 'success',
                'message' => 'Printer deleted successfully'
            ]);
            
            $this->loadPrinters();
            
        } catch (\Exception $e) {
            Log::error('Failed to delete thermal printer', [
                'printer_id' => $printerId,
                'error' => $e->getMessage()
            ]);
            
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Failed to delete printer: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Set printer as default
     */
    public function setAsDefault(int $printerId): void
    {
        try {
            $printer = ThermalPrinter::findOrFail($printerId);
            $printer->setAsDefault();
            
            Log::info('Printer set as default', [
                'printer_id' => $printerId,
                'name' => $printer->name
            ]);
            
            $this->dispatch('show-toast', [
                'type' => 'success',
                'message' => 'Default printer updated'
            ]);
            
            $this->loadPrinters();
            
        } catch (\Exception $e) {
            Log::error('Failed to set default printer', [
                'printer_id' => $printerId,
                'error' => $e->getMessage()
            ]);
            
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Failed to set default printer: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Test printer connection
     */
    public function testPrinter(int $printerId): void
    {
        try {
            $this->isLoading = true;
            $this->testResult = null;
            
            $printer = ThermalPrinter::findOrFail($printerId);
            
            Log::info('Testing printer connection', [
                'printer_id' => $printerId,
                'name' => $printer->name
            ]);
            
            $result = $printer->testConnection();
            
            $this->testResult = [
                'success' => $result,
                'message' => $result ? 'Printer connection successful' : 'Printer connection failed',
                'printer_name' => $printer->name
            ];
            
            $this->dispatch('show-toast', [
                'type' => $result ? 'success' : 'error',
                'message' => $this->testResult['message']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Printer test failed', [
                'printer_id' => $printerId,
                'error' => $e->getMessage()
            ]);
            
            $this->testResult = [
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
                'printer_name' => 'Unknown'
            ];
            
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => $this->testResult['message']
            ]);
            
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Discover bluetooth printers
     */
    public function discoverPrinters(): void
    {
        try {
            $this->isLoading = true;
            $this->discoveredPrinters = [];
            
            Log::info('Starting bluetooth printer discovery');
            
            $bridgeService = new BluetoothBridgeService([
                'bridge_type' => 'desktop' // Default to desktop bridge
            ]);
            
            $this->discoveredPrinters = $bridgeService->discoverPrinters();
            $this->showDiscovery = true;
            
            Log::info('Bluetooth printer discovery completed', [
                'printers_found' => count($this->discoveredPrinters)
            ]);
            
            if (empty($this->discoveredPrinters)) {
                $this->dispatch('show-toast', [
                    'type' => 'warning',
                    'message' => 'No bluetooth printers found. Make sure printers are powered on and in pairing mode.'
                ]);
            } else {
                $this->dispatch('show-toast', [
                    'type' => 'success',
                    'message' => 'Found ' . count($this->discoveredPrinters) . ' bluetooth printer(s)'
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Bluetooth printer discovery failed', [
                'error' => $e->getMessage()
            ]);
            
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Discovery failed: ' . $e->getMessage()
            ]);
            
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Discover Bluetooth devices for tablet/mobile usage
     */
    public function discoverBluetoothDevices(): void
    {
        try {
            $this->isLoading = true;
            $this->bluetoothDevices = [];
            
            Log::info('Starting Bluetooth device discovery for tablet/mobile');
            
            // Try multiple bridge types for better compatibility
            $bridgeTypes = ['web_bluetooth', 'mobile', 'desktop'];
            $allDevices = [];
            
            foreach ($bridgeTypes as $bridgeType) {
                try {
                    $bridgeService = new BluetoothBridgeService([
                        'bridge_type' => $bridgeType
                    ]);
                    
                    $devices = $bridgeService->discoverPrinters();
                    $allDevices = array_merge($allDevices, $devices);
                    
                } catch (\Exception $e) {
                    Log::warning("Bridge type {$bridgeType} failed", [
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            // Remove duplicates and format for UI
            $uniqueDevices = [];
            foreach ($allDevices as $device) {
                $key = $device['address'] ?? $device['id'];
                if (!isset($uniqueDevices[$key])) {
                    $uniqueDevices[$key] = [
                        'name' => $device['name'],
                        'address' => $device['address'] ?? $device['id'],
                        'type' => $device['type'] ?? 'thermal',
                        'connection_type' => $device['connection_type'] ?? 'bluetooth',
                        'status' => $device['status'] ?? 'available'
                    ];
                }
            }
            
            $this->bluetoothDevices = array_values($uniqueDevices);
            
            Log::info('Bluetooth device discovery completed', [
                'devices_found' => count($this->bluetoothDevices)
            ]);
            
            if (empty($this->bluetoothDevices)) {
                $this->dispatch('show-toast', [
                    'type' => 'info',
                    'message' => 'No Bluetooth devices found. Make sure devices are powered on and discoverable. For tablets, you may need to enable Web Bluetooth in your browser settings.'
                ]);
            } else {
                $this->dispatch('show-toast', [
                    'type' => 'success',
                    'message' => 'Found ' . count($this->bluetoothDevices) . ' Bluetooth device(s). Select one to auto-fill the connection details.'
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Bluetooth device discovery failed', [
                'error' => $e->getMessage()
            ]);
            
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Device discovery failed: ' . $e->getMessage()
            ]);
            
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Select discovered Bluetooth device
     */
    public function selectBluetoothDevice(string $address, string $name): void
    {
        try {
            $this->device_address = $address;
            $this->name = $name ?: 'Bluetooth Printer';
            $this->connection_type = 'bluetooth';
            
            // Clear the devices list after selection
            $this->bluetoothDevices = [];
            
            Log::info('Bluetooth device selected', [
                'name' => $name,
                'address' => $address
            ]);
            
            $this->dispatch('show-toast', [
                'type' => 'success',
                'message' => "Device '{$name}' selected. You can now save the printer configuration."
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to select Bluetooth device', [
                'error' => $e->getMessage()
            ]);
            
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Failed to select device: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Add discovered printer
     */
    public function addDiscoveredPrinter(array $printerData): void
    {
        try {
            $this->name = $printerData['name'];
            $this->device_address = $printerData['address'];
            $this->connection_type = $printerData['connection_type'];
            $this->paper_size = $printerData['supported_sizes'][0] ?? '80mm';
            
            $this->showDiscovery = false;
            $this->modalMode = 'create';
            $this->showModal = true;
            
            Log::info('Adding discovered printer', [
                'name' => $this->name,
                'address' => $this->device_address
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to add discovered printer', [
                'error' => $e->getMessage()
            ]);
            
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Failed to add printer: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Close modal and reset form
     */
    public function closeModal(): void
    {
        $this->showModal = false;
        $this->showDiscovery = false;
        $this->resetForm();
    }

    /**
     * Reset form fields
     */
    private function resetForm(): void
    {
        $this->selectedPrinter = null;
        $this->editingPrinterId = null;
        $this->name = '';
        $this->device_address = '';
        $this->connection_type = 'bluetooth';
        $this->paper_size = '80mm';
        $this->is_default = false;
        $this->is_active = true;
        $this->testResult = null;
        $this->bluetoothDevices = [];
        $this->discoveredPrinters = [];
        $this->showDiscovery = false;
        
        // Reset settings to defaults
        $this->settings = [
            'charset' => 'UTF-8',
            'timeout' => 30,
            'retry_attempts' => 3,
            'auto_cut' => true,
            'print_logo' => false,
            'print_header' => true,
            'print_footer' => true,
            'font_size' => 'normal',
            'line_spacing' => 'normal',
            'kot_enabled' => true,
            'receipt_enabled' => true,
            'order_enabled' => true
        ];
        
        $this->resetValidation();
    }

    /**
     * Get available connection types
     */
    public function getConnectionTypesProperty(): array
    {
        return ThermalPrinter::CONNECTION_TYPES;
    }

    /**
     * Get available paper sizes
     */
    public function getPaperSizesProperty(): array
    {
        return ThermalPrinter::PAPER_SIZES;
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.admin.thermal-printer-settings');
    }
}