<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

/**
 * Thermal Printer Model
 * 
 * Manages thermal printer configurations and settings for restaurants
 * 
 * @property int $id
 * @property int $restaurant_id
 * @property string $name
 * @property string $device_address
 * @property string $connection_type
 * @property string $paper_size
 * @property array $settings
 * @property bool $is_default
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * 
 * @property-read Restaurant $restaurant
 */
class ThermalPrinter extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'restaurant_id',
        'name',
        'device_address',
        'connection_type',
        'paper_size',
        'settings',
        'is_default',
        'is_active'
    ];

    protected $casts = [
        'settings' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean'
    ];

    protected $attributes = [
        'settings' => '{}',
        'is_default' => false,
        'is_active' => true
    ];

    /**
     * Available connection types
     */
    public const CONNECTION_TYPES = [
        'bluetooth' => 'Bluetooth',
        'network' => 'Network',
        'usb' => 'USB',
        'web_bluetooth' => 'Web Bluetooth'
    ];

    /**
     * Available paper sizes
     */
    public const PAPER_SIZES = [
        '58mm' => '58mm (32 chars)',
        '80mm' => '80mm (48 chars)'
    ];

    /**
     * Default printer settings
     */
    public const DEFAULT_SETTINGS = [
        'charset' => 'UTF-8',
        'timeout' => 30,
        'retry_attempts' => 3,
        'auto_cut' => true,
        'print_logo' => false,
        'print_header' => true,
        'print_footer' => true,
        'font_size' => 'normal',
        'line_spacing' => 'normal',
        'receipt_width' => null, // Auto-detect based on paper size
        'kot_enabled' => true,
        'receipt_enabled' => true,
        'order_enabled' => true
    ];

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($printer) {
            // Ensure settings have default values
            $printer->settings = array_merge(
                self::DEFAULT_SETTINGS,
                $printer->settings ?? []
            );

            // Set receipt width based on paper size
            if (!isset($printer->settings['receipt_width'])) {
                $printer->settings = array_merge($printer->settings, [
                    'receipt_width' => $printer->paper_size === '58mm' ? 32 : 48
                ]);
            }

            Log::info('Creating thermal printer', [
                'name' => $printer->name,
                'restaurant_id' => $printer->restaurant_id
            ]);
        });

        static::updating(function ($printer) {
            // Handle default printer logic
            if ($printer->is_default && $printer->isDirty('is_default')) {
                // Remove default flag from other printers
                static::where('restaurant_id', $printer->restaurant_id)
                    ->where('id', '!=', $printer->id)
                    ->update(['is_default' => false]);
            }

            Log::info('Updating thermal printer', [
                'id' => $printer->id,
                'name' => $printer->name
            ]);
        });

        static::deleted(function ($printer) {
            Log::info('Thermal printer deleted', [
                'id' => $printer->id,
                'name' => $printer->name
            ]);
        });
    }

    /**
     * Get the restaurant that owns the printer
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Scope to get active printers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get default printer for restaurant
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to get printers by connection type
     */
    public function scopeByConnectionType($query, string $type)
    {
        return $query->where('connection_type', $type);
    }

    /**
     * Scope to get printers by paper size
     */
    public function scopeByPaperSize($query, string $size)
    {
        return $query->where('paper_size', $size);
    }

    /**
     * Get maximum characters per line based on paper size
     */
    public function getMaxCharsPerLineAttribute(): int
    {
        return match($this->paper_size) {
            '58mm' => 32,
            '80mm' => 48,
            default => 48
        };
    }

    /**
     * Get connection type label
     */
    public function getConnectionTypeLabelAttribute(): string
    {
        return self::CONNECTION_TYPES[$this->connection_type] ?? $this->connection_type;
    }

    /**
     * Get paper size label
     */
    public function getPaperSizeLabelAttribute(): string
    {
        return self::PAPER_SIZES[$this->paper_size] ?? $this->paper_size;
    }

    /**
     * Check if printer supports KOT printing
     */
    public function supportsKot(): bool
    {
        return $this->settings['kot_enabled'] ?? true;
    }

    /**
     * Check if printer supports receipt printing
     */
    public function supportsReceipt(): bool
    {
        return $this->settings['receipt_enabled'] ?? true;
    }

    /**
     * Check if printer supports order printing
     */
    public function supportsOrder(): bool
    {
        return $this->settings['order_enabled'] ?? true;
    }

    /**
     * Get printer setting value
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set printer setting value
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings;
        $settings[$key] = $value;
        $this->settings = $settings;
    }

    /**
     * Update multiple settings at once
     */
    public function updateSettings(array $newSettings): bool
    {
        try {
            $this->settings = array_merge($this->settings, $newSettings);
            return $this->save();
        } catch (\Exception $e) {
            Log::error('Failed to update printer settings', [
                'printer_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Test printer connection
     */
    public function testConnection(): bool
    {
        try {
            // This would integrate with ThermalPrinterService
            $service = app(\App\Services\ThermalPrinterService::class, [
                'config' => [
                    'connection_type' => $this->connection_type,
                    'device_address' => $this->device_address,
                    'paper_size' => $this->paper_size,
                    'timeout' => $this->getSetting('timeout', 30)
                ]
            ]);

            if ($this->connection_type === 'bluetooth') {
                return $service->connectBluetooth($this->device_address);
            }

            return $service->testConnection();

        } catch (\Exception $e) {
            Log::error('Printer connection test failed', [
                'printer_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get printer status information
     */
    public function getStatusInfo(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'device_address' => $this->device_address,
            'connection_type' => $this->connection_type,
            'connection_type_label' => $this->connection_type_label,
            'paper_size' => $this->paper_size,
            'paper_size_label' => $this->paper_size_label,
            'max_chars_per_line' => $this->max_chars_per_line,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'supports_kot' => $this->supportsKot(),
            'supports_receipt' => $this->supportsReceipt(),
            'supports_order' => $this->supportsOrder(),
            'settings' => $this->settings
        ];
    }

    /**
     * Set as default printer for restaurant
     */
    public function setAsDefault(): bool
    {
        try {
            // Remove default flag from other printers
            static::where('restaurant_id', $this->restaurant_id)
                ->where('id', '!=', $this->id)
                ->update(['is_default' => false]);

            // Set this printer as default
            $this->is_default = true;
            return $this->save();

        } catch (\Exception $e) {
            Log::error('Failed to set printer as default', [
                'printer_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get default printer for restaurant
     */
    public static function getDefaultForRestaurant(int $restaurantId): ?self
    {
        return static::where('restaurant_id', $restaurantId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get available printers for restaurant
     */
    public static function getAvailableForRestaurant(int $restaurantId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create default printer configuration
     */
    public static function createDefault(int $restaurantId, array $attributes = []): self
    {
        $defaultAttributes = [
            'restaurant_id' => $restaurantId,
            'name' => 'Default Thermal Printer',
            'device_address' => '00:00:00:00:00:00',
            'connection_type' => 'bluetooth',
            'paper_size' => '80mm',
            'is_default' => true,
            'is_active' => true,
            'settings' => self::DEFAULT_SETTINGS
        ];

        return static::create(array_merge($defaultAttributes, $attributes));
    }

    /**
     * Validate printer configuration
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        // Validate connection type
        if (!array_key_exists($this->connection_type, self::CONNECTION_TYPES)) {
            $errors[] = 'Invalid connection type';
        }

        // Validate paper size
        if (!array_key_exists($this->paper_size, self::PAPER_SIZES)) {
            $errors[] = 'Invalid paper size';
        }

        // Validate device address for bluetooth
        if ($this->connection_type === 'bluetooth' && !$this->isValidBluetoothAddress($this->device_address)) {
            $errors[] = 'Invalid bluetooth device address';
        }

        // Validate settings
        if (!is_array($this->settings)) {
            $errors[] = 'Invalid settings format';
        }

        return $errors;
    }

    /**
     * Check if bluetooth address is valid
     */
    private function isValidBluetoothAddress(string $address): bool
    {
        return preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $address);
    }

    /**
     * Export printer configuration
     */
    public function exportConfiguration(): array
    {
        return [
            'name' => $this->name,
            'device_address' => $this->device_address,
            'connection_type' => $this->connection_type,
            'paper_size' => $this->paper_size,
            'settings' => $this->settings,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active
        ];
    }

    /**
     * Import printer configuration
     */
    public static function importConfiguration(int $restaurantId, array $config): self
    {
        $config['restaurant_id'] = $restaurantId;
        
        // Ensure settings have default values
        $config['settings'] = array_merge(
            self::DEFAULT_SETTINGS,
            $config['settings'] ?? []
        );

        return static::create($config);
    }
}