<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ThermalPrinter;
use App\Services\ThermalPrinterService;
use App\Services\ThermalPrintTemplateService;
use App\Services\BluetoothBridgeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controller for handling thermal printer operations
 * Manages printing requests from POS interface
 */
class ThermalPrintController extends Controller
{
    private ThermalPrinterService $printerService;
    private BluetoothBridgeService $bluetoothService;

    public function __construct(
        ThermalPrinterService $printerService,
        BluetoothBridgeService $bluetoothService
    ) {
        $this->printerService = $printerService;
        $this->bluetoothService = $bluetoothService;
    }

    /**
     * Print order receipt to thermal printer
     */
    public function printReceipt(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'order_id' => 'required|exists:orders,id',
                'printer_id' => 'nullable|exists:thermal_printers,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $order = Order::with(['orderItems.menuItem', 'customer', 'table', 'restaurant'])
                          ->findOrFail($request->order_id);

            // Get printer (specified or default)
            $printer = $this->getPrinter($request->printer_id, $order->restaurant_id);
            
            if (!$printer) {
                return response()->json([
                    'success' => false,
                    'message' => 'No thermal printer configured'
                ], 400);
            }

            // Generate receipt template
            $templateService = new ThermalPrintTemplateService($printer);
            $printData = $templateService->generateReceipt($order);

            // Send to printer
            $result = $this->sendToPrinter($printer, $printData, 'receipt');

            if ($result['success']) {
                Log::info('Receipt printed successfully', [
                    'order_id' => $order->id,
                    'printer_id' => $printer->id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Receipt printed successfully',
                    'job_id' => $result['job_id'] ?? null
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to print receipt'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to print receipt: ' . $e->getMessage(), [
                'order_id' => $request->order_id,
                'printer_id' => $request->printer_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to print receipt: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print KOT (Kitchen Order Ticket) to thermal printer
     */
    public function printKOT(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'order_id' => 'required|exists:orders,id',
                'printer_id' => 'nullable|exists:thermal_printers,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $order = Order::with(['orderItems.menuItem', 'table', 'restaurant', 'waiter'])
                          ->findOrFail($request->order_id);

            // Get KOT printer (specified or default kitchen printer)
            $printer = $this->getPrinter($request->printer_id, $order->restaurant_id, 'kot');
            
            if (!$printer) {
                return response()->json([
                    'success' => false,
                    'message' => 'No KOT printer configured'
                ], 400);
            }

            // Generate KOT template
            $templateService = new ThermalPrintTemplateService($printer);
            $printData = $templateService->generateKOT($order);

            // Send to printer
            $result = $this->sendToPrinter($printer, $printData, 'kot');

            if ($result['success']) {
                Log::info('KOT printed successfully', [
                    'order_id' => $order->id,
                    'printer_id' => $printer->id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'KOT printed successfully',
                    'job_id' => $result['job_id'] ?? null
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to print KOT'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to print KOT: ' . $e->getMessage(), [
                'order_id' => $request->order_id,
                'printer_id' => $request->printer_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to print KOT: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test thermal printer connection and print test page
     */
    public function testPrint(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'printer_id' => 'required|exists:thermal_printers,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $printer = ThermalPrinter::findOrFail($request->printer_id);

            // Generate test print template
            $templateService = new ThermalPrintTemplateService($printer);
            $printData = $templateService->generateTestPrint();

            // Send to printer
            $result = $this->sendToPrinter($printer, $printData, 'test');

            if ($result['success']) {
                Log::info('Test print successful', ['printer_id' => $printer->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Test print completed successfully',
                    'job_id' => $result['job_id'] ?? null
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Test print failed'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Test print failed: ' . $e->getMessage(), [
                'printer_id' => $request->printer_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Test print failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available thermal printers for restaurant
     */
    public function getAvailablePrinters(Request $request): JsonResponse
    {
        try {
            $restaurantId = auth()->user()?->restaurant_id ?? 1;
            
            $printers = ThermalPrinter::getAvailableForRestaurant($restaurantId)
                                    ->map(function ($printer) {
                                        return [
                                            'id' => $printer->id,
                                            'name' => $printer->name,
                                            'paper_size' => $printer->paper_size,
                                            'connection_type' => $printer->connection_type,
                                            'is_default' => $printer->is_default,
                                            'is_active' => $printer->is_active
                                        ];
                                    });

            return response()->json([
                'success' => true,
                'data' => $printers
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get available printers: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get available printers'
            ], 500);
        }
    }

    /**
     * Get print job status
     */
    public function getJobStatus(Request $request, string $jobId): JsonResponse
    {
        try {
            $status = $this->bluetoothService->getJobStatus($jobId);

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get job status: ' . $e->getMessage(), [
                'job_id' => $jobId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get job status'
            ], 500);
        }
    }

    /**
     * Get printer based on ID or default for restaurant
     */
    private function getPrinter(?int $printerId, int $restaurantId, string $type = 'receipt'): ?ThermalPrinter
    {
        if ($printerId) {
            return ThermalPrinter::where('id', $printerId)
                                ->where('restaurant_id', $restaurantId)
                                ->where('is_active', true)
                                ->first();
        }

        // Get default printer for the type
        return ThermalPrinter::getDefaultForRestaurant($restaurantId, $type);
    }

    /**
     * Send print data to thermal printer
     */
    private function sendToPrinter(ThermalPrinter $printer, string $printData, string $type): array
    {
        try {
            switch ($printer->connection_type) {
                case 'bluetooth':
                    return $this->bluetoothService->sendPrintJob(
                        $printer->device_address,
                        $printData,
                        [
                            'type' => $type,
                            'printer_name' => $printer->name,
                            'paper_size' => $printer->paper_size
                        ]
                    );

                case 'network':
                    return $this->printerService->printViaNetwork(
                        $printer->device_address,
                        $printData,
                        $printer->settings ?? []
                    );

                case 'usb':
                    return $this->printerService->printViaUSB(
                        $printer->device_address,
                        $printData,
                        $printer->settings ?? []
                    );

                default:
                    throw new \Exception('Unsupported connection type: ' . $printer->connection_type);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send to printer: ' . $e->getMessage(), [
                'printer_id' => $printer->id,
                'connection_type' => $printer->connection_type
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}