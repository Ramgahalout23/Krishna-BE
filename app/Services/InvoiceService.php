<?php

namespace App\Services;

use App\Jobs\GenerateInvoiceJob;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceService
{
    /**
     * Generate a PDF invoice for a given order.
     */
    public function generateInvoice(string $orderId): \Barryvdh\DomPDF\PDF
    {
        $order = Order::with([
            'user',
            'items.product',
            'shippingAddress',
            'billingAddress',
            'payment',
        ])->findOrFail($orderId);

        $storeName = '';
        $storeEmail = '';
        $brandTagline = '';
        try {
            $storeName = Setting::where('module', 'SITE')->where('key', 'storeName')->value('value') ?? 'THREVOLT';
            $storeEmail = Setting::where('module', 'SITE')->where('key', 'storeEmail')->value('value') ?? 'support@threvolt.com';
            $brandTagline = Setting::where('module', 'SITE')->where('key', 'brandTagline')->value('value') ?? 'Premium Fashion & Lifestyle';
        } catch (\Exception $e) {
            $storeName = 'THREVOLT';
            $storeEmail = 'support@threvolt.com';
            $brandTagline = 'Premium Fashion & Lifestyle';
        }

        $data = [
            'order' => $order,
            'company' => [
                'name' => $storeName,
                'tagline' => $brandTagline,
                'email' => $storeEmail,
                'logo' => '',
            ],
            'invoiceNumber' => 'INV-' . ($order->order_number ?? strtoupper(substr($order->id, 0, 12))),
            'generatedAt' => now()->format(config('app.date_format')),
        ];

        $pdf = Pdf::loadView('invoices.standard', $data);
        $pdf->setPaper('A4');
        $pdf->setOptions([
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
        ]);

        return $pdf;
    }

    /**
     * Generate invoice PDF and return as download response.
     * Serves from cached storage if available, otherwise generates synchronously.
     */
    public function downloadInvoice(string $orderId): \Illuminate\Http\Response
    {
        // Serve from cached file if already generated (no extra DB query needed)
        if (GenerateInvoiceJob::exists($orderId)) {
            $path = GenerateInvoiceJob::invoicePath($orderId);
            return response()->download(
                Storage::disk('local')->path($path),
                "invoice-{$orderId}.pdf",
                ['Content-Type' => 'application/pdf']
            );
        }

        // Fall back to synchronous generation
        $pdf = $this->generateInvoice($orderId);
        $order = Order::findOrFail($orderId);
        return $pdf->download("invoice-{$order->order_number}.pdf");
    }

    /**
     * Generate invoice PDF and stream to browser.
     * Serves from cached storage if available, otherwise generates synchronously.
     */
    public function streamInvoice(string $orderId): \Illuminate\Http\Response
    {
        // Serve from cached file if already generated (no extra DB query needed)
        if (GenerateInvoiceJob::exists($orderId)) {
            $path = GenerateInvoiceJob::invoicePath($orderId);
            return response()->file(
                Storage::disk('local')->path($path),
                ['Content-Type' => 'application/pdf']
            );
        }

        // Fall back to synchronous generation
        $pdf = $this->generateInvoice($orderId);
        return $pdf->stream();
    }

    /**
     * Dispatch async invoice generation for a given order.
     * Useful to pre-generate invoices when orders are placed.
     */
    public function generateInvoiceAsync(string $orderId): void
    {
        GenerateInvoiceJob::dispatch($orderId);
        Log::info("[InvoiceService] Dispatched async invoice generation for order {$orderId}");
    }
}
