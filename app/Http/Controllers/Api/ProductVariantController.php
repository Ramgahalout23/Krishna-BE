<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductVariantService;
use App\Exceptions\AppError;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    public function __construct(protected ProductVariantService $variantService) {}

    // ── Public Routes ──

    public function byProduct(string $productId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->variantService->getByProduct($productId)]);
    }

    public function getVariantByAttributes(Request $request, string $productId): JsonResponse
    {
        $query = ProductVariant::where('product_id', $productId);
        if ($request->has('color')) { $query->where('attributes->color', $request->color); }
        if ($request->has('size')) { $query->where('attributes->size', $request->size); }
        if ($request->has('sku')) { $query->where('sku', $request->sku); }
        return response()->json(['success' => true, 'data' => $query->first()]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->variantService->getById($id)]);
        } catch (AppError $e) { return $e->render(); }
    }

    // ── Admin Routes ──

    /**
     * Validation rules covering everything the admin variant form submits.
     * `stock`, `color` and `size` are frontend-side aliases for the real
     * `quantity` / `attributes` columns and are mapped in mapVariantPayload().
     */
    private const VARIANT_RULES = [
        'name' => 'required|string|max:255',
        'sku' => 'required|string|max:255',
        'price' => 'nullable|numeric|min:0',
        'quantity' => 'nullable|integer|min:0',
        'stock' => 'nullable|integer|min:0',
        'color' => 'nullable|string|max:255',
        'size' => 'nullable|string|max:255',
        'attributes' => 'nullable|array',
        'images' => 'nullable|array',
        'images.*' => 'nullable',
    ];

    /**
     * Map the admin form payload onto the product_variants columns.
     * Without this, color/size/stock/variant images were dropped by validation
     * and the variants table always rendered empty swatches and no photos.
     */
    private function mapVariantPayload(array $validated): array
    {
        $data = $validated;

        // Rebuild `attributes` only when the caller actually supplied colour,
        // size or an attributes object — otherwise a partial update (e.g. a
        // stock tweak) would blank out the existing swatches.
        $touchedAttributes = array_key_exists('attributes', $validated)
            || !empty($validated['color'])
            || !empty($validated['size']);

        if (array_key_exists('stock', $data)) {
            $data['quantity'] = $data['stock'];
        }
        unset($data['stock']);

        if ($touchedAttributes) {
            $attributes = is_array($validated['attributes'] ?? null) ? $validated['attributes'] : [];
            if (!empty($validated['color'])) {
                $attributes['color'] = $validated['color'];
            }
            if (!empty($validated['size'])) {
                $attributes['size'] = $validated['size'];
            }
            $data['attributes'] = $attributes;
        } else {
            unset($data['attributes']);
        }

        unset($data['color'], $data['size']);

        if (array_key_exists('images', $data)) {
            $data['images'] = is_array($data['images']) ? array_values($data['images']) : [];
        }

        // Only forward columns that actually exist on the variants table.
        return array_intersect_key($data, array_flip(['name', 'sku', 'price', 'quantity', 'attributes', 'images']));
    }

    public function store(Request $request, string $productId): JsonResponse
    {
        try {
            $input = $request->all();
            if (empty($input['name'])) {
                $parts = array_filter([$input['color'] ?? null, $input['size'] ?? null]);
                if (!empty($parts)) {
                    $input['name'] = implode(' / ', $parts);
                    $request->replace($input);
                }
            }

            $validated = $request->validate(self::VARIANT_RULES);
            return response()->json(['success' => true, 'message' => 'Variant created', 'data' => $this->variantService->create($productId, $this->mapVariantPayload($validated))], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $rules = array_merge(self::VARIANT_RULES, [
                'name' => 'sometimes|string|max:255',
                'sku' => 'sometimes|string|max:255',
            ]);
            $validated = $request->validate($rules);
            return response()->json(['success' => true, 'data' => $this->variantService->update($id, $this->mapVariantPayload($validated))]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->variantService->delete($id);
            return response()->json(['success' => true, 'message' => 'Variant deleted']);
        } catch (AppError $e) { return $e->render(); }
    }

    public function lowStock(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->variantService->getLowStock()]);
    }

    public function getAllVariants(Request $request): JsonResponse
    {
        $query = ProductVariant::with('product:id,name');
        if ($request->has('search')) { $s = $request->search; $query->where(function ($q) use ($s) { $q->where('sku', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"); }); }
        if ($request->has('product_id')) { $query->where('product_id', $request->product_id); }
        return response()->json(['success' => true, 'data' => $query->latest()->paginate($request->input('per_page', 20))]);
    }

    /**
     * Look up a variant (or product) by SKU — used by barcode scanner.
     * GET /api/v1/admin/variants/lookup-sku/{sku}
     */
    public function lookupBySku(string $sku): JsonResponse
    {
        try {
            // First try variant-level SKU (most granular)
            $variant = ProductVariant::with('product:id,name,sku,price,quantity')
                ->where('sku', $sku)
                ->first();

            if ($variant) {
                $attrs = $variant->attributes ?? [];
                if (is_string($attrs)) $attrs = json_decode($attrs, true) ?? [];

                return response()->json([
                    'success' => true,
                    'data' => [
                        'type' => 'variant',
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'sku' => $variant->sku,
                        'quantity' => (int) $variant->quantity,
                        'price' => $variant->price,
                        'attributes' => $attrs,
                        'product_id' => $variant->product_id,
                        'product_name' => $variant->product?->name,
                        'product_sku' => $variant->product?->sku,
                    ],
                ]);
            }

            // Fallback: try product-level SKU
            $product = Product::where('sku', $sku)->first();

            if ($product) {
                // Check if product has variants — if so, return the first variant or indicate multi-variant
                $variants = ProductVariant::where('product_id', $product->id)->get();

                if ($variants->isNotEmpty()) {
                    // Return product with variants list for selection
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'type' => 'product_with_variants',
                            'id' => $product->id,
                            'name' => $product->name,
                            'sku' => $product->sku,
                            'quantity' => (int) $variants->sum('quantity'),
                            'variants' => $variants->map(fn($v) => [
                                'id' => $v->id,
                                'name' => $v->name,
                                'sku' => $v->sku,
                                'quantity' => (int) $v->quantity,
                                'price' => $v->price,
                            ]),
                        ],
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'type' => 'product',
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'quantity' => (int) $product->quantity,
                        'price' => $product->price,
                    ],
                ]);
            }

            throw AppError::notFound("No variant or product found with SKU: {$sku}");
        } catch (AppError $e) { return $e->render(); } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function bulkCreateVariants(Request $request, string $productId): JsonResponse
    {
        try {
            $validated = $request->validate(['variants' => 'required|array', 'variants.*.name' => 'required|string', 'variants.*.sku' => 'required|string', 'variants.*.price' => 'nullable|numeric', 'variants.*.quantity' => 'nullable|integer']);
            $created = [];
            foreach ($validated['variants'] as $v) {
                $v['product_id'] = $productId;
                $created[] = ProductVariant::create($v);
            }
            return response()->json(['success' => true, 'data' => $created], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    public function updateQuantity(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate(['quantity' => 'required|integer|min:0']);
            $variant = ProductVariant::findOrFail($id);
            $variant->update(['quantity' => $validated['quantity']]);
            \Illuminate\Support\Facades\Cache::increment('products_cache_version');
            \Illuminate\Support\Facades\Cache::forget('homepage_all');
            return response()->json(['success' => true, 'data' => $variant]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function bulkUpdateQuantities(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['variants' => 'required|array', 'variants.*.id' => 'required|string', 'variants.*.quantity' => 'required|integer|min:0']);
            foreach ($validated['variants'] as $v) {
                ProductVariant::where('id', $v['id'])->update(['quantity' => $v['quantity']]);
            }
            \Illuminate\Support\Facades\Cache::increment('products_cache_version');
            \Illuminate\Support\Facades\Cache::forget('homepage_all');
            return response()->json(['success' => true, 'message' => 'Quantities updated']);
        } catch (AppError $e) { return $e->render(); }
    }
}
