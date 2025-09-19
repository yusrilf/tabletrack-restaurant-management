<?php

namespace App\Services;

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\EscposImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

/**
 * Thermal Printer Service for handling bluetooth and direct printing
 * 
 * This service provides comprehensive thermal printing capabilities including:
 * - Bluetooth connectivity management
 * - Multiple paper size support (58mm, 80mm)
 * - ESC/POS command generation
 * - Print job queue management
 * - Error handling and logging
 */
class ThermalPrinterService
{
    private ?Printer $printer = null;
    private array $config = [];
    private string $paperSize = '80mm';
    private array $supportedSizes = ['58mm', '80mm'];
    
    /**
     * Initialize thermal printer service
     * 
     * @param array $config Printer configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'connection_type' => 'bluetooth',
            'device_address' => null,
            'paper_size' => '80mm',
            'charset' => 'UTF-8',
            'timeout' => 30,
            'retry_attempts' => 3
        ], $config);
        
        $this->paperSize = $this->config['paper_size'];
        
        Log::info('ThermalPrinterService initialized', [
            'config' => $this->config
        ]);
    }
    
    /**
     * Connect to bluetooth thermal printer
     * 
     * @param string $deviceAddress Bluetooth device address (MAC)
     * @return bool Connection success status
     * @throws Exception When connection fails
     */
    public function connectBluetooth(string $deviceAddress): bool
    {
        try {
            Log::info('Attempting bluetooth connection', [
                'device_address' => $deviceAddress
            ]);
            
            // For bluetooth connection, we'll use network connector with bluetooth bridge
            // This requires a desktop bridge application or bluetooth-to-network bridge
            $connector = $this->createBluetoothConnector($deviceAddress);
            
            if (!$connector) {
                throw new Exception('Failed to create bluetooth connector');
            }
            
            $this->printer = new Printer($connector);
            
            // Test connection with a simple command
            $this->printer->initialize();
            
            Log::info('Bluetooth printer connected successfully', [
                'device_address' => $deviceAddress
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Log::error('Bluetooth connection failed', [
                'device_address' => $deviceAddress,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception('Failed to connect to bluetooth printer: ' . $e->getMessage());
        }
    }
    
    /**
     * Create bluetooth connector based on platform
     * 
     * @param string $deviceAddress Bluetooth device address
     * @return mixed Connector instance or null
     */
    private function createBluetoothConnector(string $deviceAddress)
    {
        try {
            // Check if running on Windows
            if (PHP_OS_FAMILY === 'Windows') {
                // Use Windows bluetooth connector if available
                return $this->createWindowsBluetoothConnector($deviceAddress);
            }
            
            // For macOS/Linux, use network bridge approach
            return $this->createNetworkBridgeConnector($deviceAddress);
            
        } catch (Exception $e) {
            Log::error('Failed to create bluetooth connector', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Create Windows bluetooth connector
     * 
     * @param string $deviceAddress Bluetooth device address
     * @return WindowsPrintConnector|null
     */
    private function createWindowsBluetoothConnector(string $deviceAddress): ?WindowsPrintConnector
    {
        try {
            // Windows bluetooth printer connection
            $printerName = "Bluetooth_" . str_replace(':', '', $deviceAddress);
            return new WindowsPrintConnector($printerName);
            
        } catch (Exception $e) {
            Log::warning('Windows bluetooth connector failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Create network bridge connector for bluetooth
     * 
     * @param string $deviceAddress Bluetooth device address
     * @return NetworkPrintConnector|null
     */
    private function createNetworkBridgeConnector(string $deviceAddress): ?NetworkPrintConnector
    {
        try {
            // Use localhost bridge service (requires desktop bridge app)
            $bridgePort = 9100; // Default thermal printer port
            $bridgeHost = '127.0.0.1';
            
            return new NetworkPrintConnector($bridgeHost, $bridgePort);
            
        } catch (Exception $e) {
            Log::warning('Network bridge connector failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Set paper size for printing
     * 
     * @param string $size Paper size (58mm, 80mm)
     * @return bool Success status
     * @throws Exception When invalid size provided
     */
    public function setPaperSize(string $size): bool
    {
        if (!in_array($size, $this->supportedSizes)) {
            throw new Exception('Unsupported paper size: ' . $size);
        }
        
        $this->paperSize = $size;
        $this->config['paper_size'] = $size;
        
        Log::info('Paper size set', ['size' => $size]);
        
        return true;
    }
    
    /**
     * Get maximum characters per line based on paper size
     * 
     * @return int Characters per line
     */
    public function getMaxCharsPerLine(): int
    {
        return match($this->paperSize) {
            '58mm' => 32,
            '80mm' => 48,
            default => 48
        };
    }
    
    /**
     * Print receipt with order data
     * 
     * @param array $orderData Order information
     * @return bool Print success status
     * @throws Exception When printing fails
     */
    public function printReceipt(array $orderData): bool
    {
        try {
            if (!$this->printer) {
                throw new Exception('Printer not connected');
            }
            
            Log::info('Starting receipt print', [
                'order_id' => $orderData['id'] ?? 'unknown'
            ]);
            
            $this->printer->initialize();
            $this->printer->selectPrintMode(Printer::MODE_FONT_A);
            
            // Print header
            $this->printReceiptHeader($orderData);
            
            // Print order items
            $this->printOrderItems($orderData['items'] ?? []);
            
            // Print totals
            $this->printReceiptTotals($orderData);
            
            // Print footer
            $this->printReceiptFooter($orderData);
            
            // Cut paper
            $this->printer->cut();
            $this->printer->close();
            
            Log::info('Receipt printed successfully', [
                'order_id' => $orderData['id'] ?? 'unknown'
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Log::error('Receipt printing failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderData['id'] ?? 'unknown'
            ]);
            
            throw new Exception('Failed to print receipt: ' . $e->getMessage());
        }
    }
    
    /**
     * Print receipt header
     * 
     * @param array $orderData Order data
     */
    private function printReceiptHeader(array $orderData): void
    {
        $maxChars = $this->getMaxCharsPerLine();
        
        // Restaurant name
        $restaurantName = $orderData['restaurant']['name'] ?? 'Restaurant';
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        $this->printer->setEmphasis(true);
        $this->printer->text($this->centerText($restaurantName, $maxChars) . "\n");
        $this->printer->setEmphasis(false);
        
        // Address and contact
        if (isset($orderData['restaurant']['address'])) {
            $this->printer->text($this->centerText($orderData['restaurant']['address'], $maxChars) . "\n");
        }
        
        if (isset($orderData['restaurant']['phone'])) {
            $this->printer->text($this->centerText($orderData['restaurant']['phone'], $maxChars) . "\n");
        }
        
        // Separator
        $this->printer->text(str_repeat('-', $maxChars) . "\n");
        
        // Order info
        $this->printer->setJustification(Printer::JUSTIFY_LEFT);
        $this->printer->text("Order #: " . ($orderData['order_number'] ?? 'N/A') . "\n");
        $this->printer->text("Date: " . date('Y-m-d H:i:s') . "\n");
        
        if (isset($orderData['table']['name'])) {
            $this->printer->text("Table: " . $orderData['table']['name'] . "\n");
        }
        
        if (isset($orderData['waiter']['name'])) {
            $this->printer->text("Waiter: " . $orderData['waiter']['name'] . "\n");
        }
        
        $this->printer->text(str_repeat('-', $maxChars) . "\n");
    }
    
    /**
     * Print order items
     * 
     * @param array $items Order items
     */
    private function printOrderItems(array $items): void
    {
        $maxChars = $this->getMaxCharsPerLine();
        
        foreach ($items as $item) {
            $name = $item['name'] ?? 'Item';
            $quantity = $item['quantity'] ?? 1;
            $price = $item['price'] ?? 0;
            $total = $quantity * $price;
            
            // Item name
            $this->printer->text($name . "\n");
            
            // Quantity x Price = Total
            $qtyPriceText = sprintf("%d x %.2f", $quantity, $price);
            $totalText = sprintf("%.2f", $total);
            $spacing = $maxChars - strlen($qtyPriceText) - strlen($totalText);
            
            $this->printer->text($qtyPriceText . str_repeat(' ', $spacing) . $totalText . "\n");
            
            // Add notes if any
            if (isset($item['notes']) && !empty($item['notes'])) {
                $this->printer->text("  Note: " . $item['notes'] . "\n");
            }
            
            $this->printer->text("\n");
        }
    }
    
    /**
     * Print receipt totals
     * 
     * @param array $orderData Order data
     */
    private function printReceiptTotals(array $orderData): void
    {
        $maxChars = $this->getMaxCharsPerLine();
        
        $this->printer->text(str_repeat('-', $maxChars) . "\n");
        
        // Subtotal
        if (isset($orderData['subtotal'])) {
            $this->printTotalLine('Subtotal:', $orderData['subtotal'], $maxChars);
        }
        
        // Tax
        if (isset($orderData['tax']) && $orderData['tax'] > 0) {
            $this->printTotalLine('Tax:', $orderData['tax'], $maxChars);
        }
        
        // Discount
        if (isset($orderData['discount']) && $orderData['discount'] > 0) {
            $this->printTotalLine('Discount:', -$orderData['discount'], $maxChars);
        }
        
        // Total
        $this->printer->setEmphasis(true);
        $this->printTotalLine('TOTAL:', $orderData['total'] ?? 0, $maxChars);
        $this->printer->setEmphasis(false);
        
        $this->printer->text(str_repeat('-', $maxChars) . "\n");
    }
    
    /**
     * Print receipt footer
     * 
     * @param array $orderData Order data
     */
    private function printReceiptFooter(array $orderData): void
    {
        $maxChars = $this->getMaxCharsPerLine();
        
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        $this->printer->text("\n");
        $this->printer->text($this->centerText("Thank you for your visit!", $maxChars) . "\n");
        $this->printer->text($this->centerText("Please come again!", $maxChars) . "\n");
        $this->printer->text("\n\n");
    }
    
    /**
     * Print a total line with proper alignment
     * 
     * @param string $label Label text
     * @param float $amount Amount value
     * @param int $maxChars Maximum characters per line
     */
    private function printTotalLine(string $label, float $amount, int $maxChars): void
    {
        $amountText = sprintf("%.2f", $amount);
        $spacing = $maxChars - strlen($label) - strlen($amountText);
        $this->printer->text($label . str_repeat(' ', $spacing) . $amountText . "\n");
    }
    
    /**
     * Center text within given width
     * 
     * @param string $text Text to center
     * @param int $width Total width
     * @return string Centered text
     */
    private function centerText(string $text, int $width): string
    {
        $textLength = strlen($text);
        if ($textLength >= $width) {
            return substr($text, 0, $width);
        }
        
        $padding = ($width - $textLength) / 2;
        return str_repeat(' ', floor($padding)) . $text . str_repeat(' ', ceil($padding));
    }
    
    /**
     * Test printer connection
     * 
     * @return bool Connection test result
     */
    public function testConnection(): bool
    {
        try {
            if (!$this->printer) {
                return false;
            }
            
            $this->printer->initialize();
            $this->printer->text("Connection Test\n");
            $this->printer->text("Printer is working!\n");
            $this->printer->feed(2);
            $this->printer->cut();
            
            Log::info('Printer connection test successful');
            
            return true;
            
        } catch (Exception $e) {
            Log::error('Printer connection test failed', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Disconnect from printer
     * 
     * @return bool Disconnect success status
     */
    public function disconnect(): bool
    {
        try {
            if ($this->printer) {
                $this->printer->close();
                $this->printer = null;
            }
            
            Log::info('Printer disconnected successfully');
            
            return true;
            
        } catch (Exception $e) {
            Log::error('Printer disconnect failed', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Get printer status
     * 
     * @return array Printer status information
     */
    public function getStatus(): array
    {
        return [
            'connected' => $this->printer !== null,
            'paper_size' => $this->paperSize,
            'max_chars_per_line' => $this->getMaxCharsPerLine(),
            'config' => $this->config
        ];
    }
}