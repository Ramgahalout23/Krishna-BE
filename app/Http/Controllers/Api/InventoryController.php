<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateBarcodeLabelsJob;
use App\Services\BarcodeLabelService;
use App\Services\InventoryService;
use App\Exceptions\AppError;
use App\Models\ProductVariant;
use App\Models\VariantStockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    public function __construct(protected InventoryService $inventoryService, protected BarcodeLabelService $barcodeLabelService) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->inventoryService->getAll($request->all());
        return response()->json([
            'success' => true,
            'data' => [
                'items' => $result['data'] ?? [],
                'pagination' => [
                    'page' => $result['current_page'] ?? 1,
                    'pages' => $result['last_page'] ?? 1,
                    'total' => $result['total'] ?? 0,
                    'per_page' => $result['per_page'] ?? 15,
                ],
            ],
        ]);
    }

    public function show(string $productId): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->inventoryService->getByProduct($productId)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function check(string $productId): JsonResponse
    {
        try {
            $available = $this->inventoryService->checkAvailability($productId);
            return response()->json(['success' => true, 'data' => ['available' => $available]]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function addStock(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['product_id' => 'required|string', 'quantity' => 'required|integer|min:1', 'notes' => 'nullable|string']);
            return response()->json(['success' => true, 'message' => 'Stock added', 'data' => $this->inventoryService->addStock($validated['product_id'], $validated['quantity'], $validated['notes'] ?? null)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function reduceStock(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['product_id' => 'required|string', 'quantity' => 'required|integer|min:1', 'notes' => 'nullable|string']);
            return response()->json(['success' => true, 'message' => 'Stock reduced', 'data' => $this->inventoryService->reduceStock($validated['product_id'], $validated['quantity'], $validated['notes'] ?? null)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function lowStock(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->inventoryService->getLowStock()]);
    }

    public function movement(string $productId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->inventoryService->getMovement($productId)]);
    }

    /**
     * Get inventory statistics (Admin).
     * GET /api/v1/inventory/stats
     */
    public function stats(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->inventoryService->getStats()]);
    }

    /**
     * Batch update stock (Admin).
     * POST /api/v1/inventory/batch-update
     */
    /**
     * Update stock for a specific inventory item.
     * PATCH /api/v1/admin/inventory/{id}/stock
     */
    public function updateStock(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'quantity' => 'required|integer|min:0',
                'notes' => 'nullable|string',
            ]);

            $inventory = \App\Models\Inventory::findOrFail($id);
            $oldQty = (int) $inventory->available_quantity;
            $newQty = (int) $validated['quantity'];
            $diff = $newQty - $oldQty;

            if ($diff > 0) {
                $inventory->increment('total_quantity', $diff);
                $inventory->increment('available_quantity', $diff);
            } elseif ($diff < 0) {
                $reduceBy = abs($diff);
                $inventory->decrement('total_quantity', $reduceBy);
                $inventory->decrement('available_quantity', $reduceBy);
            }

            $inventory->refresh();

            \App\Models\InventoryHistory::create([
                'inventory_id' => $inventory->id,
                'type' => $diff >= 0 ? 'ADD' : 'REMOVE',
                'quantity' => abs($diff),
                'reason' => $validated['notes'] ?: 'Manual stock adjustment via admin',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stock updated',
                'data' => $inventory->load('product'),
            ]);
        } catch (AppError $e) { return $e->render(); } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function batchUpdate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'updates' => 'required|array|min:1',
                'updates.*.product_id' => 'required|string',
                'updates.*.quantity' => 'required|integer',
                'updates.*.type' => 'required|string|in:add,reduce',
            ]);
            $results = [];
            foreach ($validated['updates'] as $update) {
                try {
                    if ($update['type'] === 'add') {
                        $results[] = $this->inventoryService->addStock($update['product_id'], abs($update['quantity']));
                    } else {
                        $results[] = $this->inventoryService->reduceStock($update['product_id'], abs($update['quantity']));
                    }
                } catch (\Exception $e) {
                    $results[] = ['product_id' => $update['product_id'], 'error' => $e->getMessage()];
                }
            }
            return response()->json(['success' => true, 'data' => ['updated_count' => count($results), 'results' => $results]]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Adjust stock for a specific variant (add/reduce/set).
     * POST /api/v1/admin/variants/{id}/adjust-stock
     */
    public function adjustVariantStock(Request $request, string $variantId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => 'required|string|in:add,reduce,set',
                'quantity' => 'required|integer|min:0',
                'reason' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            $variant = ProductVariant::findOrFail($variantId);
            $stockBefore = (int) $variant->quantity;
            $adminUser = Auth::user();

            if ($validated['type'] === 'add') {
                $qty = (int) $validated['quantity'];
                $variant->increment('quantity', $qty);
                $stockAfter = $stockBefore + $qty;
            } elseif ($validated['type'] === 'reduce') {
                $qty = (int) $validated['quantity'];
                if ($qty > $stockBefore) {
                    throw AppError::validation("Cannot reduce stock below 0. Current stock: {$stockBefore}");
                }
                $variant->decrement('quantity', $qty);
                $stockAfter = $stockBefore - $qty;
            } else { // set
                $newQty = (int) $validated['quantity'];
                $variant->update(['quantity' => $newQty]);
                $stockAfter = $newQty;
            }

            // Log the movement
            VariantStockMovement::create([
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'type' => strtoupper($validated['type']),
                'quantity' => abs($stockAfter - $stockBefore),
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reason' => $validated['reason'] ?? 'manual_adjustment',
                'notes' => $validated['notes'] ?? null,
                'reference_type' => 'manual',
                'created_by' => $adminUser ? $adminUser->id : null,
            ]);

            $variant->refresh();

            // Invalidate product + homepage caches so all pages reflect fresh stock
            \Illuminate\Support\Facades\Cache::increment('products_cache_version');
            \Illuminate\Support\Facades\Cache::forget('homepage_all');

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'data' => [
                    'id' => $variant->id,
                    'quantity' => $variant->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'difference' => $stockAfter - $stockBefore,
                ],
            ]);
        } catch (AppError $e) { return $e->render(); } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get stock movement history for a specific variant.
     * GET /api/v1/admin/variants/{id}/stock-movements
     */
    public function variantStockMovements(string $variantId): JsonResponse
    {
        try {
            $variant = ProductVariant::findOrFail($variantId);
            $movements = VariantStockMovement::where('variant_id', $variantId)
                ->orderBy('created_at', 'desc')
                ->take(50)
                ->get()
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'variant' => [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'sku' => $variant->sku,
                        'current_stock' => (int) $variant->quantity,
                    ],
                    'movements' => $movements,
                ],
            ]);
        } catch (AppError $e) { return $e->render(); } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Bulk adjust stock for multiple variants.
     * POST /api/v1/admin/variants/bulk-adjust-stock
     */
    /**
     * Generate PDF with printable barcode labels for all products & variants.
     * GET /api/v1/admin/inventory/barcode-labels
     */
    public function barcodeLabels(Request $request): \Illuminate\Http\Response|JsonResponse
    {
        try {
            $print = $request->query('print') === '1';
            return $this->barcodeLabelService->downloadLabels($print);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Download or print a single variant barcode label as PDF.
     * GET /api/v1/admin/variants/{id}/barcode-label
     * GET /api/v1/admin/variants/{id}/barcode-label?print=1  → opens in browser tab for printing
     */
    public function variantBarcodeLabel(Request $request, string $variantId): \Illuminate\Http\Response|JsonResponse
    {
        try {
            $print = $request->query('print') === '1';
            return $this->barcodeLabelService->downloadVariantLabel($variantId, $print);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Dispatch a background job to generate barcode labels for selected variants.
     * POST /api/v1/admin/variants/batch-barcode-labels
     */
    public function batchVariantBarcodeLabels(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'variant_ids' => 'required|array|min:1',
                'variant_ids.*' => 'required|string',
            ]);

            $batchId = (string) Str::uuid();
            $variantIds = $validated['variant_ids'];

            GenerateBarcodeLabelsJob::dispatch($variantIds, $batchId);

            return response()->json([
                'success' => true,
                'data' => [
                    'batch_id' => $batchId,
                    'variant_count' => count($variantIds),
                    'status' => 'processing',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Check the status of a barcode batch generation.
     * GET /api/v1/admin/barcode-batches/{batchId}/status
     */
    public function barcodeBatchStatus(string $batchId): JsonResponse
    {
        try {
            if (GenerateBarcodeLabelsJob::isReady($batchId)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'batch_id' => $batchId,
                        'status' => 'ready',
                    ],
                ]);
            }

            if (GenerateBarcodeLabelsJob::hasFailed($batchId)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'batch_id' => $batchId,
                        'status' => 'failed',
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'batch_id' => $batchId,
                    'status' => 'processing',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Download or print a completed barcode batch PDF.
     * GET /api/v1/admin/barcode-batches/{batchId}/download
     * GET /api/v1/admin/barcode-batches/{batchId}/download?print=1  → opens in browser tab for printing
     */
    public function barcodeBatchDownload(Request $request, string $batchId): \Illuminate\Http\Response|JsonResponse
    {
        try {
            if (!GenerateBarcodeLabelsJob::isReady($batchId)) {
                return response()->json(['success' => false, 'message' => 'PDF not ready yet'], 404);
            }

            $print = $request->query('print') === '1';
            $path = GenerateBarcodeLabelsJob::getPdfPath($batchId);
            $filename = "barcode-batch-{$batchId}.pdf";

            if ($print) {
                return response()->file($path, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $filename . '"',
                ]);
            }

            return response()->download($path, $filename, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Export inventory as CSV download.
     * GET /api/v1/admin/inventory/export
     */
    public function exportInventory(Request $request): \Illuminate\Http\Response
    {
        try {
            $filters = $request->only(['search', 'stockStatus']);
            $filters['per_page'] = 100000; // effectively unlimited for CSV export
            $result = $this->inventoryService->getAll($filters);

            $items = $result['data'] ?? [];

            // Build CSV
            $headers = [
                'Product Name', 'SKU', 'Total Stock', 'Available Quantity',
                'Reserved Quantity', 'Damaged Quantity', 'Effective Stock',
                'Variant Count', 'Status', 'Product ID'
            ];

            $rows = [];
            foreach ($items as $item) {
                $qty = $item['available_quantity'] ?? $item['stock'] ?? $item['quantity'] ?? 0;
                $effectiveStock = $item['effective_stock'] ?? $qty;

                $status = match (true) {
                    $effectiveStock <= 0 => 'Out of Stock',
                    $effectiveStock < 5 => 'Low Stock',
                    default => 'In Stock',
                };

                $row = [
                    $item['productName'] ?? $item['product']['name'] ?? '—',
                    $item['sku'] ?? $item['product']['sku'] ?? '—',
                    (int) ($item['total_quantity'] ?? 0),
                    (int) ($item['available_quantity'] ?? 0),
                    (int) ($item['reserved_quantity'] ?? 0),
                    (int) ($item['damaged_quantity'] ?? 0),
                    $effectiveStock,
                    $item['variants'] ? count($item['variants']) : 0,
                    $status,
                    $item['product_id'] ?? $item['productId'] ?? '—',
                ];
                $rows[] = $row;

                // Add variant rows if they exist
                if (!empty($item['variants'])) {
                    foreach ($item['variants'] as $v) {
                        $vQty = $v['quantity'] ?? $v['stock'] ?? 0;
                        $vStatus = match (true) {
                            $vQty <= 0 => 'Out of Stock',
                            $vQty < 5 => 'Low Stock',
                            default => 'In Stock',
                        };
                        $rows[] = [
                            '  └ ' . ($v['name'] ?? 'Variant'),
                            $v['sku'] ?? '—',
                            '',
                            '',
                            '',
                            '',
                            $vQty,
                            '—',
                            $vStatus,
                            $v['id'] ?? '—',
                        ];
                    }
                }
            }

            $csv = $this->arrayToCsv($headers, $rows);
            $filename = 'inventory-export-' . now()->format('Y-m-d') . '.csv';

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Convert array data to CSV string with UTF-8 BOM for Excel compatibility.
     */
    private function arrayToCsv(array $headers, array $rows): string
    {
        // UTF-8 BOM ensures Excel renders special characters correctly
        $csv = "\xEF\xBB\xBF";
        $csv .= implode(',', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $headers)) . "\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', $row)) . "\n";
        }
        return $csv;
    }

    public function bulkAdjustVariantStock(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'adjustments' => 'required|array|min:1',
                'adjustments.*.variant_id' => 'required|string',
                'adjustments.*.type' => 'required|string|in:add,reduce,set',
                'adjustments.*.quantity' => 'required|integer|min:0',
                'adjustments.*.reason' => 'nullable|string',
            ]);

            $adminUser = Auth::user();
            $results = [];

            foreach ($validated['adjustments'] as $adj) {
                try {
                    $variant = ProductVariant::findOrFail($adj['variant_id']);
                    $stockBefore = (int) $variant->quantity;

                    if ($adj['type'] === 'add') {
                        $variant->increment('quantity', (int) $adj['quantity']);
                    } elseif ($adj['type'] === 'reduce') {
                        if ((int) $adj['quantity'] > $stockBefore) {
                            throw new \Exception("Cannot reduce stock below 0. Current: {$stockBefore}");
                        }
                        $variant->decrement('quantity', (int) $adj['quantity']);
                    } else {
                        $variant->update(['quantity' => (int) $adj['quantity']]);
                    }

                    $stockAfter = (int) $variant->quantity;

                    VariantStockMovement::create([
                        'variant_id' => $variant->id,
                        'product_id' => $variant->product_id,
                        'type' => strtoupper($adj['type']),
                        'quantity' => abs($stockAfter - $stockBefore),
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'reason' => $adj['reason'] ?? 'bulk_adjustment',
                        'reference_type' => 'bulk',
                        'created_by' => $adminUser ? $adminUser->id : null,
                    ]);

                    $results[] = [
                        'variant_id' => $variant->id,
                        'success' => true,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'variant_id' => $adj['variant_id'],
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Invalidate product + homepage caches after bulk stock changes
            \Illuminate\Support\Facades\Cache::increment('products_cache_version');
            \Illuminate\Support\Facades\Cache::forget('homepage_all');

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => count($results),
                    'succeeded' => count(array_filter($results, fn($r) => $r['success'])),
                    'failed' => count(array_filter($results, fn($r) => !$r['success'])),
                    'results' => $results,
                ],
            ]);
        } catch (AppError $e) { return $e->render(); } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
