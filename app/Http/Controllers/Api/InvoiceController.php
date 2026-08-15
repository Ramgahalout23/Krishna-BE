<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Download invoice PDF.
     */
    public function download(string $orderId): \Illuminate\Http\Response|JsonResponse
    {
        try {
            return $this->invoiceService->downloadInvoice($orderId);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Stream invoice PDF to browser.
     */
    public function show(string $orderId): \Illuminate\Http\Response|JsonResponse
    {
        try {
            return $this->invoiceService->streamInvoice($orderId);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
