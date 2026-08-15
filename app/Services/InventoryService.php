<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Repositories\InventoryRepository;
use App\Repositories\ProductRepository;
use App\Exceptions\AppError;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        protected InventoryRepository $inventoryRepository,
        protected ProductRepository $productRepository
    ) {}

    /**
     * Compute effective stock from variants when they exist.
     * Matches TS InventoryRepository.computeEffectiveStock() behavior.
     */
    private function computeEffectiveStock(string $productId): int
    {
        $variantCount = ProductVariant::where('product_id', $productId)->count();
        if ($variantCount > 0) {
            return (int) ProductVariant::where('product_id', $productId)->sum('quantity');
        }
        $product = $this->productRepository->findById($productId);
        return $product ? (int) $product->quantity : 0;
    }

    public function getAll(array $filters = []): array
    {
        $result = $this->inventoryRepository->getAllWithProduct($filters)->toArray();

        // Batch enrich with effective_stock + variants — single query for all items
        if (!empty($result['data'])) {
            $productIds = collect($result['data'])->pluck('product_id')->filter()->unique()->toArray();

            if (!empty($productIds)) {
                $variantStock = ProductVariant::whereIn('product_id', $productIds)
                    ->selectRaw('product_id, SUM(quantity) as total_stock')
                    ->groupBy('product_id')
                    ->pluck('total_stock', 'product_id')
                    ->toArray();

                $productStock = \App\Models\Product::whereIn('id', $productIds)
                    ->pluck('quantity', 'id')
                    ->toArray();

                // Load all variants for these products in a single query
                $allVariants = ProductVariant::whereIn('product_id', $productIds)
                    ->get()
                    ->groupBy('product_id');

                // Load product names and SKUs
                $productNames = \App\Models\Product::whereIn('id', $productIds)
                    ->pluck('name', 'id')
                    ->toArray();
                $productSkus = \App\Models\Product::whereIn('id', $productIds)
                    ->pluck('sku', 'id')
                    ->toArray();

                foreach ($result['data'] as &$item) {
                    $pid = $item['product_id'] ?? null;

                    // Effective stock calculation
                    if ($pid && isset($variantStock[$pid])) {
                        $item['effective_stock'] = (int) $variantStock[$pid];
                    } elseif ($pid && isset($productStock[$pid])) {
                        $item['effective_stock'] = (int) $productStock[$pid];
                    } else {
                        $item['effective_stock'] = $item['available_quantity'] ?? 0;
                    }

                    // Frontend expects camelCase aliases
                    $item['productId'] = $pid;
                    $item['productName'] = $productNames[$pid] ?? ($item['product']['name'] ?? '—');
                    $item['sku'] = $productSkus[$pid] ?? ($item['product']['sku'] ?? '—');
                    $item['stock'] = $item['available_quantity'] ?? 0;
                    $item['quantity'] = $item['available_quantity'] ?? 0;

                    // Load variants for expandable rows
                    $item['variants'] = [];
                    if ($pid && isset($allVariants[$pid])) {
                        $item['variants'] = $allVariants[$pid]->map(function ($v) {
                            $attrs = $v->attributes ?? [];
                            if (is_string($attrs)) {
                                $attrs = json_decode($attrs, true) ?? [];
                            }
                            return [
                                'id' => $v->id,
                                'name' => $v->name,
                                'sku' => $v->sku,
                                'quantity' => (int) ($v->quantity ?? 0),
                                'stock' => (int) ($v->quantity ?? 0),
                                'attributes' => $attrs,
                                'color' => $attrs['color'] ?? $attrs['Color'] ?? null,
                                'size' => $attrs['size'] ?? $attrs['Size'] ?? null,
                            ];
                        })->values()->toArray();
                    }
                }
            }
        }

        return $result;
    }

    public function getByProduct(string $productId): array
    {
        $inv = $this->inventoryRepository->findByProduct($productId);
        if (!$inv) throw AppError::notFound('Inventory not found');
        $data = $inv->load('product')->toArray();
        $data['effective_stock'] = $this->computeEffectiveStock($productId);
        return $data;
    }

    /**
     * Add stock — stock must be managed at the variant level.
     * Matches TS InventoryService.addStock() behavior.
     */
    public function addStock(string $productId, int $quantity, ?string $notes = null): array
    {
        $product = $this->productRepository->findById($productId);
        $name = $product ? $product->name : 'Product';
        throw AppError::validation(
            "Stock is managed at the variant level for all products. Use the Variants management page to update stock for individual variants of \"{$name}\".",
        );
    }

    /**
     * Reduce stock — stock must be managed at the variant level.
     * Matches TS InventoryService.reduceStock() behavior.
     */
    public function reduceStock(string $productId, int $quantity, ?string $notes = null): array
    {
        $product = $this->productRepository->findById($productId);
        $name = $product ? $product->name : 'Product';
        throw AppError::validation(
            "Stock is managed at the variant level for all products. Use the Variants management page to update stock for individual variants of \"{$name}\".",
        );
    }

    public function getMovement(string $productId): array
    {
        return $this->inventoryRepository->getMovementHistory($productId)->toArray();
    }

    /**
     * Get low stock items — checks variant-level stock when variants exist.
     * Preserves backward-compatible response shape with added variant info.
     */
    public function getLowStock(int $threshold = 5): array
    {
        // Check variant-level low stock
        $lowVariantProducts = ProductVariant::select('product_id')
            ->selectRaw('SUM(quantity) as total_stock')
            ->groupBy('product_id')
            ->having('total_stock', '<=', $threshold)
            ->having('total_stock', '>', 0)
            ->pluck('product_id')
            ->toArray();

        // Also include inventory items for products without variants (backward-compatible)
        $inventoryLow = $this->inventoryRepository->getLowStockItems($threshold);
        $inventoryIds = $inventoryLow->pluck('product_id')->toArray();

        $allIds = array_unique(array_merge($lowVariantProducts, $inventoryIds));

        if (empty($allIds)) return [];

        $products = \App\Models\Product::whereIn('id', $allIds)
            ->with(['variants', 'inventory'])
            ->get();

        $result = $inventoryLow->toArray();
        $existingIds = collect($result)->pluck('product_id')->toArray();

        // Add variant-only items that weren't in inventory results
        foreach ($products as $p) {
            if (in_array($p->id, $existingIds)) {
                // Enrich existing inventory entry with variant info + flat name/quantity
                foreach ($result as &$entry) {
                    if (isset($entry['product_id']) && $entry['product_id'] === $p->id) {
                        $entry['name'] = $p->name;
                        $entry['quantity'] = $p->variants->isNotEmpty()
                            ? $p->variants->sum('quantity')
                            : ($entry['available_quantity'] ?? 0);
                        $entry['effective_stock'] = $entry['quantity'];
                        $entry['variants_count'] = $p->variants->count();
                        break;
                    }
                }
            } else {
                $totalStock = $p->variants->sum('quantity');
                // Add entry for variant-only product in backward-compatible shape
                $result[] = [
                    'product_id' => $p->id,
                    'name' => $p->name,
                    'product_name' => $p->name,
                    'quantity' => $totalStock,
                    'effective_stock' => $totalStock,
                    'available_quantity' => $totalStock,
                    'variants_count' => $p->variants->count(),
                ];
            }
        }

        return $result;
    }

    /**
     * Get out of stock items — checks variant-level stock when variants exist.
     * Preserves backward-compatible response shape with added variant info.
     */
    public function getOutOfStock(): array
    {
        // Products with variants that have zero total stock
        $outVariantProducts = ProductVariant::select('product_id')
            ->selectRaw('SUM(quantity) as total_stock')
            ->groupBy('product_id')
            ->having('total_stock', '<=', 0)
            ->pluck('product_id')
            ->toArray();

        // Products without variants that have zero inventory
        $inventoryOut = $this->inventoryRepository->getOutOfStockItems();
        $inventoryIds = $inventoryOut->pluck('product_id')->toArray();

        $allIds = array_unique(array_merge($outVariantProducts, $inventoryIds));

        if (empty($allIds)) return [];

        $products = \App\Models\Product::whereIn('id', $allIds)
            ->with(['variants', 'inventory'])
            ->get();

        $result = $inventoryOut->toArray();
        $existingIds = collect($result)->pluck('product_id')->toArray();

        foreach ($products as $p) {
            if (in_array($p->id, $existingIds)) {
                foreach ($result as &$entry) {
                    if (isset($entry['product_id']) && $entry['product_id'] === $p->id) {
                        $entry['name'] = $p->name;
                        $entry['quantity'] = 0;
                        $entry['effective_stock'] = 0;
                        $entry['variants_count'] = $p->variants->count();
                        break;
                    }
                }
            } else {
                $result[] = [
                    'product_id' => $p->id,
                    'name' => $p->name,
                    'product_name' => $p->name,
                    'quantity' => 0,
                    'effective_stock' => 0,
                    'available_quantity' => 0,
                    'variants_count' => $p->variants->count(),
                ];
            }
        }

        return $result;
    }

    /**
     * Reserve stock — works at variant level when variants exist.
     * Tracks which variants were deducted from for symmetrical release.
     */
    public function reserveStock(string $productId, int $quantity): array
    {
        return DB::transaction(function () use ($productId, $quantity) {
            $variants = ProductVariant::where('product_id', $productId)->get();
            $reservation = ['variant_ids' => [], 'quantities' => []];

            if ($variants->isNotEmpty()) {
                // Variant-first: reserve from variants
                $totalAvailable = $variants->sum('quantity');
                if ($totalAvailable < $quantity) {
                    throw AppError::validation('Insufficient stock across variants to reserve');
                }

                $remaining = $quantity;
                foreach ($variants as $variant) {
                    if ($remaining <= 0) break;
                    $deduct = min($variant->quantity, $remaining);
                    if ($deduct > 0) {
                        $variant->decrement('quantity', $deduct);
                        $reservation['variant_ids'][] = $variant->id;
                        $reservation['quantities'][] = $deduct;
                        $remaining -= $deduct;
                    }
                }
            } else {
                // Fallback to product-level stock
                $product = $this->productRepository->findByIdOrFail($productId);
                if ($product->quantity < $quantity) {
                    throw AppError::validation('Insufficient stock to reserve');
                }
                $product->decrement('quantity', $quantity);
            }

            // Track in inventory model for reporting
            $inventory = $this->inventoryRepository->findByProduct($productId);
            if ($inventory) {
                $inventory->decrement('available_quantity', $quantity);
                $inventory->increment('reserved_quantity', $quantity);
            }

            return $reservation;
        });
    }

    /**
     * Release stock — works at variant level when variants exist.
     * Restores proportionally to the variants that were reserved.
     */
    public function releaseStock(string $productId, int $quantity, array $reservationData = []): void
    {
        DB::transaction(function () use ($productId, $quantity, $reservationData) {
            $variants = ProductVariant::where('product_id', $productId)->get();

            if ($variants->isNotEmpty() && !empty($reservationData['variant_ids'])) {
                // Restore to specific variants that were reserved (symmetrical release)
                foreach ($reservationData['variant_ids'] as $i => $variantId) {
                    $qty = $reservationData['quantities'][$i] ?? 0;
                    if ($qty > 0) {
                        ProductVariant::where('id', $variantId)->increment('quantity', $qty);
                    }
                }
            } elseif ($variants->isNotEmpty()) {
                // No reservation data — distribute proportionally across all variants
                $totalVariantQty = $variants->sum('quantity');
                $remaining = $quantity;
                foreach ($variants as $variant) {
                    if ($remaining <= 0) break;
                    $restoreQty = (int) round(($variant->quantity / max($totalVariantQty, 1)) * $quantity);
                    $restoreQty = min($restoreQty, $remaining);
                    if ($restoreQty > 0) {
                        $variant->increment('quantity', $restoreQty);
                        $remaining -= $restoreQty;
                    }
                }
                // Distribute any remainder to the last variant
                if ($remaining > 0 && $variants->isNotEmpty()) {
                    $variants->last()->increment('quantity', $remaining);
                }
            } else {
                $product = $this->productRepository->findByIdOrFail($productId);
                $product->increment('quantity', $quantity);
            }

            $inventory = $this->inventoryRepository->findByProduct($productId);
            if ($inventory) {
                $inventory->increment('available_quantity', $quantity);
                $inventory->decrement('reserved_quantity', $quantity);
            }
        });
    }

    /**
     * Check availability — checks variant-level stock when variants exist.
     * Matches TS InventoryService.checkAvailability() behavior.
     */
    public function checkAvailability(string $productId): bool
    {
        return $this->computeEffectiveStock($productId) > 0;
    }

    /**
     * Get inventory statistics — computed from variant data as source of truth.
     */
    public function getStats(): array
    {
        // Single query: get all variant product IDs + their stock aggregates at once
        $variantStats = ProductVariant::select('product_id')
            ->selectRaw('SUM(quantity) as total_stock')
            ->groupBy('product_id')
            ->get();

        $variantProductIds = $variantStats->pluck('product_id');
        $productsWithVariants = $variantProductIds->count();

        // Count total products
        $totalProducts = \App\Models\Product::count();
        $productsWithoutVariants = $totalProducts - $productsWithVariants;

        // Total available = variant stock + product.quantity for non-variant products
        $totalVariantStock = (int) $variantStats->sum('total_stock');
        $totalSimpleStock = (int) \App\Models\Product::whereNotIn('id', $variantProductIds)->sum('quantity');
        $totalAvailable = $totalVariantStock + $totalSimpleStock;

        // Use the already-fetched $variantStats to classify low/out-of-stock (no extra query)
        $lowStockVariants = $variantStats->filter(fn($s) => $s->total_stock > 0 && $s->total_stock <= 5)->count();
        $outOfStockVariants = $variantStats->filter(fn($s) => $s->total_stock <= 0)->count();

        // Non-variant product counts — single combined query with conditional aggregation
        $simpleProductCounts = \App\Models\Product::whereNotIn('id', $variantProductIds)
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN quantity > 0 AND quantity <= 5 THEN 1 ELSE 0 END) as low_stock")
            ->selectRaw("SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock")
            ->first();

        return [
            'total_products' => $totalProducts,
            'total_available' => $totalAvailable,
            'low_stock' => $lowStockVariants + (int) ($simpleProductCounts->low_stock ?? 0),
            'out_of_stock' => $outOfStockVariants + (int) ($simpleProductCounts->out_of_stock ?? 0),
            'variant_managed' => $productsWithVariants,
            'product_managed' => $productsWithoutVariants,
        ];
    }
}
