<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Barryvdh\DomPDF\Facade\Pdf;
use Picqer\Barcode\BarcodeGeneratorPNG;

class BarcodeLabelService
{
    /**
     * Generate a PDF with printable barcode labels for all products & variants.
     * Layout: 3 columns x 8 rows = 24 labels per page (A4).
     */
    public function generateLabelsPdf(): \Barryvdh\DomPDF\PDF
    {
        $labels = $this->collectLabels();

        $pdf = Pdf::loadView('barcodes.labels', [
            'labels' => $labels,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ]);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
        ]);

        return $pdf;
    }

    /**
     * Collect all labels (products + variants) with barcode PNGs.
     */
    private function collectLabels(): array
    {
        $generator = new BarcodeGeneratorPNG();
        $labels = [];

        // Product-level labels
        $products = Product::select(['id', 'name', 'sku', 'barcode'])
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('name')
            ->get();

        foreach ($products as $product) {
            $sku = $product->sku;
            $barcodeValue = !empty($product->barcode) ? $product->barcode : $sku;

            $labels[] = [
                'type' => 'product',
                'name' => $product->name,
                'sku' => $sku,
                'barcode_img' => $this->generateBarcodeImg($generator, $barcodeValue),
            ];
        }

        // Variant-level labels
        $variants = ProductVariant::with('product:id,name')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('sku')
            ->get();

        foreach ($variants as $variant) {
            $sku = $variant->sku;
            $barcodeValue = $sku;

            $labels[] = [
                'type' => 'variant',
                'name' => $variant->name,
                'product_name' => $variant->product?->name ?? '',
                'sku' => $sku,
                'barcode_img' => $this->generateBarcodeImg($generator, $barcodeValue),
            ];
        }

        return $labels;
    }

    /**
     * Generate a base64-encoded PNG barcode image string using Code128 encoding.
     * Returns an <img> tag with inline data:image/png for reliable PDF rendering.
     * DomPDF handles PNG images via <img> tags much better than inline SVG.
     */
    private function generateBarcodeImg(BarcodeGeneratorPNG $generator, string $value): string
    {
        try {
            $png = $generator->getBarcode($value, $generator::TYPE_CODE_128, 1.5, 45);
            $base64 = base64_encode($png);
            return '<img src="data:image/png;base64,' . $base64 . '" alt="' . e($value) . '" style="width:100%;height:auto;max-width:180px;" />';
        } catch (\Exception $e) {
            // Return a placeholder if barcode generation fails (e.g., invalid chars)
            return '<div style="color:#999;font-size:9px;text-align:center;">Invalid SKU</div>';
        }
    }

    /**
     * Generate a PDF with a single barcode label for a specific variant.
     * Used when the user wants to download a barcode for one variant at a time.
     */
    public function generateSingleVariantLabelPdf(ProductVariant $variant): \Barryvdh\DomPDF\PDF
    {
        $generator = new BarcodeGeneratorPNG();
        $sku = $variant->sku;
        $barcodeValue = $sku;

        $label = [
            'type' => 'variant',
            'name' => $variant->name,
            'product_name' => $variant->product?->name ?? '',
            'sku' => $sku,
            'barcode_img' => $this->generateBarcodeImg($generator, $barcodeValue),
        ];

        $pdf = Pdf::loadView('barcodes.labels', [
            'labels' => [$label],
            'generatedAt' => now()->format('Y-m-d H:i'),
        ]);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
        ]);

        return $pdf;
    }

    /**
     * Generate labels PDF and return as download or stream response.
     */
    public function downloadLabels(bool $print = false): \Illuminate\Http\Response
    {
        $pdf = $this->generateLabelsPdf();
        $filename = 'barcode-labels-' . now()->format('Y-m-d') . '.pdf';
        return $print ? $pdf->stream($filename) : $pdf->download($filename);
    }

    /**
     * Download or stream a single variant barcode label as PDF.
     */
    public function downloadVariantLabel(string $variantId, bool $print = false): \Illuminate\Http\Response
    {
        $variant = ProductVariant::with('product:id,name')->findOrFail($variantId);
        $pdf = $this->generateSingleVariantLabelPdf($variant);
        $sku = $variant->sku ?? 'variant';
        $filename = 'barcode-' . $sku . '-' . now()->format('Y-m-d') . '.pdf';
        return $print ? $pdf->stream($filename) : $pdf->download($filename);
    }

    /**
     * Generate a PDF with barcode labels for multiple selected variants.
     * Accepts an array of variant IDs.
     */
    public function generateBatchVariantLabelsPdf(array $variantIds): \Barryvdh\DomPDF\PDF
    {
        $generator = new BarcodeGeneratorPNG();
        $labels = [];

        $variants = ProductVariant::with('product:id,name')
            ->whereIn('id', $variantIds)
            ->get();

        foreach ($variants as $variant) {
            $sku = $variant->sku;
            $barcodeValue = $sku;

            $labels[] = [
                'type' => 'variant',
                'name' => $variant->name,
                'product_name' => $variant->product?->name ?? '',
                'sku' => $sku,
                'barcode_img' => $this->generateBarcodeImg($generator, $barcodeValue),
            ];
        }

        $pdf = Pdf::loadView('barcodes.labels', [
            'labels' => $labels,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ]);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
        ]);

        return $pdf;
    }

    /**
     * Download or stream a PDF with barcode labels for multiple selected variants.
     */
    public function downloadBatchVariantLabels(array $variantIds, bool $print = false): \Illuminate\Http\Response
    {
        $pdf = $this->generateBatchVariantLabelsPdf($variantIds);
        $count = count($variantIds);
        $filename = 'barcode-variants-' . $count . '-' . now()->format('Y-m-d') . '.pdf';
        return $print ? $pdf->stream($filename) : $pdf->download($filename);
    }
}
