/**
 * Web Bluetooth API integration for thermal printer discovery and connection
 * Provides tablet/mobile support for Bluetooth printer connectivity
 */

class WebBluetoothManager {
    constructor() {
        this.device = null;
        this.server = null;
        this.service = null;
        this.characteristic = null;
        this.isConnected = false;
        
        // Thermal printer service UUID (generic)
        this.serviceUuid = '000018f0-0000-1000-8000-00805f9b34fb';
        this.characteristicUuid = '00002af1-0000-1000-8000-00805f9b34fb';
        
        this.init();
    }

    /**
     * Initialize Web Bluetooth manager
     */
    init() {
        // Check if Web Bluetooth is supported
        if (!navigator.bluetooth) {
            console.warn('Web Bluetooth API not supported in this browser');
            return;
        }

        console.log('Web Bluetooth Manager initialized');
        
        // Listen for Livewire events
        document.addEventListener('livewire:init', () => {
            this.setupLivewireListeners();
        });
    }

    /**
     * Setup Livewire event listeners
     */
    setupLivewireListeners() {
        // Listen for bluetooth discovery requests
        Livewire.on('web-bluetooth-discover', () => {
            this.discoverDevices();
        });

        // Listen for bluetooth connection requests
        Livewire.on('web-bluetooth-connect', (data) => {
            this.connectToDevice(data.deviceId);
        });

        // Listen for print job requests
        Livewire.on('web-bluetooth-print', (data) => {
            this.printData(data.printData);
        });
    }

    /**
     * Check if Web Bluetooth is available
     */
    isAvailable() {
        return navigator.bluetooth && navigator.bluetooth.getAvailability;
    }

    /**
     * Discover nearby Bluetooth devices
     */
    async discoverDevices() {
        try {
            console.log('Starting Web Bluetooth device discovery...');
            
            if (!this.isAvailable()) {
                throw new Error('Web Bluetooth not available');
            }

            // Request device with thermal printer filters
            const device = await navigator.bluetooth.requestDevice({
                filters: [
                    { services: [this.serviceUuid] },
                    { namePrefix: 'Thermal' },
                    { namePrefix: 'POS' },
                    { namePrefix: 'Receipt' },
                    { namePrefix: 'Printer' }
                ],
                optionalServices: [this.serviceUuid],
                acceptAllDevices: false
            });

            console.log('Device discovered:', device);

            // Format device data for Livewire
            const deviceData = {
                id: device.id,
                name: device.name || 'Unknown Bluetooth Device',
                address: device.id, // Web Bluetooth uses device ID instead of MAC
                type: 'thermal',
                connection_type: 'web_bluetooth',
                status: 'available'
            };

            // Send discovered device to Livewire component
            Livewire.dispatch('bluetooth-device-discovered', { device: deviceData });

            return [deviceData];

        } catch (error) {
            console.error('Bluetooth discovery failed:', error);
            
            // Send error to Livewire
            Livewire.dispatch('bluetooth-discovery-error', { 
                error: error.message 
            });
            
            return [];
        }
    }

    /**
     * Connect to a specific Bluetooth device
     */
    async connectToDevice(deviceId) {
        try {
            console.log('Connecting to device:', deviceId);

            if (!this.device || this.device.id !== deviceId) {
                throw new Error('Device not found or not selected');
            }

            // Connect to GATT server
            this.server = await this.device.gatt.connect();
            console.log('Connected to GATT server');

            // Get the thermal printer service
            this.service = await this.server.getPrimaryService(this.serviceUuid);
            console.log('Got thermal printer service');

            // Get the characteristic for writing print data
            this.characteristic = await this.service.getCharacteristic(this.characteristicUuid);
            console.log('Got print characteristic');

            this.isConnected = true;

            // Notify Livewire of successful connection
            Livewire.dispatch('bluetooth-connected', { 
                deviceId: deviceId,
                deviceName: this.device.name 
            });

            return true;

        } catch (error) {
            console.error('Bluetooth connection failed:', error);
            
            // Send error to Livewire
            Livewire.dispatch('bluetooth-connection-error', { 
                error: error.message 
            });
            
            return false;
        }
    }

    /**
     * Print data to connected thermal printer
     */
    async printData(printData) {
        try {
            if (!this.isConnected || !this.characteristic) {
                throw new Error('Not connected to printer');
            }

            console.log('Printing data:', printData);

            // Convert print data to ESC/POS commands
            const escposData = this.convertToEscPos(printData);
            
            // Convert string to Uint8Array
            const encoder = new TextEncoder();
            const data = encoder.encode(escposData);

            // Write data to characteristic
            await this.characteristic.writeValue(data);

            console.log('Print data sent successfully');

            // Notify Livewire of successful print
            Livewire.dispatch('bluetooth-print-success', { 
                message: 'Print job completed successfully' 
            });

            return true;

        } catch (error) {
            console.error('Bluetooth print failed:', error);
            
            // Send error to Livewire
            Livewire.dispatch('bluetooth-print-error', { 
                error: error.message 
            });
            
            return false;
        }
    }

    /**
     * Convert print data to ESC/POS commands
     */
    convertToEscPos(printData) {
        let escpos = '';
        
        // Initialize printer
        escpos += '\x1B\x40'; // ESC @
        
        // Set character set
        escpos += '\x1B\x74\x00'; // ESC t 0 (PC437)
        
        // Process print data
        if (printData.text) {
            escpos += printData.text;
        }
        
        if (printData.lines) {
            printData.lines.forEach(line => {
                escpos += line + '\n';
            });
        }
        
        // Add line feeds
        escpos += '\n\n';
        
        // Cut paper (if supported)
        escpos += '\x1D\x56\x00'; // GS V 0
        
        return escpos;
    }

    /**
     * Disconnect from current device
     */
    async disconnect() {
        try {
            if (this.server && this.server.connected) {
                await this.server.disconnect();
            }
            
            this.device = null;
            this.server = null;
            this.service = null;
            this.characteristic = null;
            this.isConnected = false;
            
            console.log('Disconnected from Bluetooth device');
            
            // Notify Livewire
            Livewire.dispatch('bluetooth-disconnected');
            
        } catch (error) {
            console.error('Disconnect error:', error);
        }
    }

    /**
     * Get connection status
     */
    getStatus() {
        return {
            isAvailable: this.isAvailable(),
            isConnected: this.isConnected,
            deviceName: this.device ? this.device.name : null,
            deviceId: this.device ? this.device.id : null
        };
    }
}

// Initialize Web Bluetooth Manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.webBluetoothManager = new WebBluetoothManager();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = WebBluetoothManager;
}