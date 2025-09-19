<div class="p-6 bg-white rounded-lg shadow-lg">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">{{ __('Thermal Printer Settings') }}</h2>
        <button wire:click="openAddModal" 
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            {{ __('Add Printer') }}
        </button>
    </div>

    <!-- Printers List -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @forelse($printers as $printer)
            <div class="border rounded-lg p-4 {{ $printer['is_default'] ? 'border-green-500 bg-green-50' : 'border-gray-200' }}">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <h3 class="font-semibold text-lg">{{ $printer['name'] }}</h3>
                        @if($printer['is_default'])
                            <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">
                                {{ __('Default') }}
                            </span>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="editPrinter({{ $printer['id'] }})" 
                                class="text-blue-600 hover:text-blue-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button wire:click="deletePrinter({{ $printer['id'] }})" 
                                class="text-red-600 hover:text-red-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="space-y-2 text-sm text-gray-600">
                    <div class="flex justify-between">
                        <span>{{ __('Connection') }}:</span>
                        <span class="capitalize">{{ $printer['connection_type'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>{{ __('Paper Size') }}:</span>
                        <span>{{ $printer['paper_size'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>{{ __('Status') }}:</span>
                        <span class="{{ $printer['is_active'] ? 'text-green-600' : 'text-red-600' }}">
                            {{ $printer['is_active'] ? __('Active') : __('Inactive') }}
                        </span>
                    </div>
                    @if($printer['device_address'])
                        <div class="flex justify-between">
                            <span>{{ __('Address') }}:</span>
                            <span class="font-mono text-xs">{{ $printer['device_address'] }}</span>
                        </div>
                    @endif
                </div>

                <div class="mt-4 flex gap-2">
                    <button wire:click="testConnection({{ $printer['id'] }})" 
                            class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded text-sm">
                        {{ __('Test Print') }}
                    </button>
                    @if(!$printer['is_default'])
                        <button wire:click="setAsDefault({{ $printer['id'] }})" 
                                class="flex-1 bg-green-100 hover:bg-green-200 text-green-700 px-3 py-2 rounded text-sm">
                            {{ __('Set Default') }}
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-8 text-gray-500">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                <p class="text-lg font-medium">{{ __('No thermal printers configured') }}</p>
                <p class="text-sm">{{ __('Add your first thermal printer to get started') }}</p>
            </div>
        @endforelse
    </div>

    <!-- Add/Edit Modal -->
    @if($showModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">
                        {{ $editingPrinterId ? __('Edit Printer') : __('Add New Printer') }}
                    </h3>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="savePrinter" class="space-y-4">
                    <!-- Printer Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Printer Name') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" wire:model="name" 
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="{{ __('e.g., Kitchen Printer') }}">
                        @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Connection Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Connection Type') }} <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="connection_type" wire:change="onConnectionTypeChange"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">{{ __('Select connection type') }}</option>
                            <option value="bluetooth">{{ __('Bluetooth') }}</option>
                            <option value="network">{{ __('Network (IP)') }}</option>
                            <option value="usb">{{ __('USB') }}</option>
                        </select>
                        @error('connection_type') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Device Address -->
                    @if($connection_type)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                @if($connection_type === 'bluetooth')
                                    {{ __('Bluetooth Address') }}
                                @elseif($connection_type === 'network')
                                    {{ __('IP Address') }}
                                @else
                                    {{ __('Device Path') }}
                                @endif
                                <span class="text-red-500">*</span>
                            </label>
                            <div class="flex gap-2">
                                <input type="text" wire:model="device_address" 
                                       class="flex-1 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="{{ $connection_type === 'bluetooth' ? '00:11:22:33:44:55 or tap Scan to discover' : ($connection_type === 'network' ? '192.168.1.100:9100' : '/dev/usb/lp0') }}">
                                @if($connection_type === 'bluetooth')
                                    <button type="button" wire:click="discoverBluetoothDevices" 
                                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors duration-200"
                                            wire:loading.attr="disabled"
                                            wire:target="discoverBluetoothDevices">
                                        <span wire:loading.remove wire:target="discoverBluetoothDevices">
                                            <i class="fas fa-search mr-1"></i>{{ __('Scan Devices') }}
                                        </span>
                                        <span wire:loading wire:target="discoverBluetoothDevices">
                                            <i class="fas fa-spinner fa-spin mr-1"></i>{{ __('Scanning...') }}
                                        </span>
                                    </button>
                                @endif
                            </div>
                            @if($connection_type === 'bluetooth')
                                <div class="text-xs text-gray-600 mt-1">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    {{ __('For tablets/phones: Use the Scan button to discover nearby Bluetooth printers automatically. No need to enter the address manually.') }}
                                </div>
                            @endif
                            @error('device_address') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <!-- Paper Size -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Paper Size') }} <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="paper_size" 
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">{{ __('Select paper size') }}</option>
                            <option value="58mm">58mm</option>
                            <option value="80mm">80mm</option>
                            <option value="110mm">110mm</option>
                        </select>
                        @error('paper_size') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Settings -->
                    <div class="flex items-center space-x-4">
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="is_default" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">{{ __('Set as default printer') }}</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">{{ __('Active') }}</span>
                        </label>
                    </div>

                    <!-- Bluetooth Devices List -->
                    @if($connection_type === 'bluetooth' && !empty($bluetoothDevices))
                        <div class="border rounded-lg p-4 bg-blue-50 border-blue-200">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-sm font-medium text-blue-800">
                                    <i class="fas fa-bluetooth-b mr-2"></i>{{ __('modules.bluetoothPrinter') }}
                                </h4>
                                <span class="text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded-full">
                                    {{ count($bluetoothDevices) }} {{ __('found') }}
                                </span>
                            </div>
                            
                            <div class="space-y-2 max-h-40 overflow-y-auto">
                                @foreach($bluetoothDevices as $device)
                                    <button type="button" 
                                            wire:click="selectBluetoothDevice('{{ $device['address'] }}', '{{ $device['name'] }}')"
                                            class="w-full text-left p-3 hover:bg-blue-100 rounded-lg border border-blue-200 bg-white transition-colors duration-200 group">
                                        <div class="flex items-center justify-between">
                                            <div class="flex-1">
                                                <div class="font-medium text-gray-900 group-hover:text-blue-800">
                                                    <i class="fas fa-print mr-2 text-blue-500"></i>{{ $device['name'] }}
                                                </div>
                                                <div class="text-xs text-gray-500 font-mono mt-1">
                                                    {{ $device['address'] }}
                                                </div>
                                                @if(isset($device['status']))
                                                    <div class="text-xs mt-1">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                            {{ $device['status'] === 'available' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                            {{ ucfirst($device['status']) }}
                                                        </span>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="text-blue-500 group-hover:text-blue-700">
                                                <i class="fas fa-chevron-right"></i>
                                            </div>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                            
                            <div class="mt-3 p-2 bg-blue-100 rounded text-xs text-blue-700">
                                <i class="fas fa-info-circle mr-1"></i>
                                {{ __('Tap on a device to automatically fill the connection details. Make sure the device is powered on and in pairing mode.') }}
                            </div>
                        </div>
                    @endif

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" wire:click="closeModal" 
                                class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg"
                                wire:loading.attr="disabled">
                            <span wire:loading.remove>
                                {{ $editingPrinterId ? __('Update') : __('Add Printer') }}
                            </span>
                            <span wire:loading>{{ __('Saving...') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Loading Overlay -->
    @if($isLoading)
        <div class="fixed inset-0 bg-black bg-opacity-25 flex items-center justify-center z-40">
            <div class="bg-white rounded-lg p-6 flex items-center space-x-3">
                <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ __('Processing...') }}</span>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    // Listen for Livewire events
    document.addEventListener('livewire:init', () => {
        Livewire.on('printer-saved', (event) => {
            // Show success notification
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: event.message || 'Printer saved successfully',
                    timer: 3000,
                    showConfirmButton: false
                });
            }
        });

        Livewire.on('printer-deleted', (event) => {
            // Show success notification
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: event.message || 'Printer deleted successfully',
                    timer: 3000,
                    showConfirmButton: false
                });
            }
        });

        Livewire.on('printer-error', (event) => {
            // Show error notification
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: event.message || 'An error occurred',
                    confirmButtonText: 'OK'
                });
            }
        });

        Livewire.on('test-print-result', (event) => {
            // Show test result notification
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: event.success ? 'success' : 'error',
                    title: event.success ? 'Test Successful!' : 'Test Failed!',
                    text: event.message,
                    confirmButtonText: 'OK'
                });
            }
        });
    });
</script>
@endpush