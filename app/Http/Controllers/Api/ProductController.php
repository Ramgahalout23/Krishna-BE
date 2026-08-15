<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AppError;
use App\Http\Controllers\Api\Traits\MapsCamelCaseFields;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessProductImportJob;
use App\Services\ProductImportService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    use MapsCamelCaseFields;

    public function __construct(
        protected ProductService $productService,
        protected ProductImportService $productImportService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $products = $this->productService->getAll($request->all());

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function featured(Request $request): JsonResponse
    {
        $products = $this->productService->getFeatured($request->limit ?? 8);

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function newArrivals(Request $request): JsonResponse
    {
        $products = $this->productService->getNewArrivals($request->limit ?? 8);

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function bestSellers(Request $request): JsonResponse
    {
        // Check master toggle — cached for 5 minutes
        $bestSellersEnabled = Cache::remember('setting_bestSellersEnabled', 300, function () {
            return \App\Models\Setting::where('key', 'bestSellersEnabled')->value('value');
        });
        if ($bestSellersEnabled === 'false' || $bestSellersEnabled === '0') {
            return response()->json(['success' => true, 'data' => []]);
        }

        $products = $this->productService->getBestSellers($request->limit ?? 8);

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $results = $this->productService->search($request->q, $request->limit ?? 10);

            return response()->json(['success' => true, 'data' => $results]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function byCategory(Request $request, string $categoryId): JsonResponse
    {
        $products = $this->productService->getByCategory($categoryId, $request->all());

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $product = $this->productService->getById($id);

            return response()->json(['success' => true, 'data' => $product]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function checkAvailability(Request $request, string $id): JsonResponse
    {
        $available = $this->productService->checkAvailability($id, $request->quantity ?? 1);

        return response()->json(['success' => true, 'data' => ['available' => $available]]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $input = $this->mapCamelCase($request->all(), [
                'categoryId' => 'category_id',
                'hoverImageUrl' => 'hover_image_url',
            ]);
            $request->replace($input);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'sku' => 'nullable|string|unique:products',
                'category_id' => 'nullable|exists:categories,id',
                'quantity' => 'nullable|integer|min:0',
                'status' => 'nullable|string|in:DRAFT,PUBLISHED,ARCHIVED',
            ]);

            $product = $this->productService->create($validated);

            return response()->json(['success' => true, 'message' => 'Product created', 'data' => $product], 201);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $input = $this->mapCamelCase($request->all(), [
                'categoryId' => 'category_id',
                'hoverImageUrl' => 'hover_image_url',
            ]);
            $request->replace($input);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'price' => 'sometimes|numeric|min:0',
                'sku' => 'nullable|string|unique:products,sku,'.$id,
                'status' => 'nullable|string|in:DRAFT,PUBLISHED,ARCHIVED',
            ]);

            $product = $this->productService->update($id, $validated);

            return response()->json(['success' => true, 'message' => 'Product updated', 'data' => $product]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->productService->delete($id);

            return response()->json(['success' => true, 'message' => 'Product deleted']);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function publishProduct(string $id): JsonResponse
    {
        try {
            $product = $this->productService->publish($id);

            return response()->json(['success' => true, 'message' => 'Product published', 'data' => $product]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function archiveProduct(string $id): JsonResponse
    {
        try {
            $product = $this->productService->archive($id);

            return response()->json(['success' => true, 'message' => 'Product archived', 'data' => $product]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function getLowStockProducts(Request $request): JsonResponse
    {
        $threshold = $request->threshold ?? 5;
        $products = $this->productService->getLowStock((int) $threshold);

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function importProductsFromCSV(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:5120',
            ]);

            $file = $request->file('file');
            $csvContent = file_get_contents($file->getPathname());

            // Apply column mapping if provided — remap headers before saving/storing
            $columnMapping = $request->input('column_mapping');
            $mapping = $columnMapping ? (is_string($columnMapping) ? json_decode($columnMapping, true) : $columnMapping) : [];
            if (! empty($mapping)) {
                $csvContent = $this->productImportService->remapCSVHeaders($csvContent, $mapping);
            }

            // Save the (potentially remapped) CSV to storage
            $importId = ProcessProductImportJob::generateImportId();
            $csvFilePath = "imports/pending/{$importId}.csv";
            Storage::disk('local')->put($csvFilePath, $csvContent);

            // Dispatch async job with just the file path (not the raw content)
            ProcessProductImportJob::dispatch($csvFilePath, $importId);

            return response()->json([
                'success' => true,
                'message' => 'Import queued for processing.',
                'data' => [
                    'import_id' => $importId,
                    'status' => 'queued',
                ],
            ]);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Preview a CSV import — parse and validate on the server without persisting.
     * Accepts optional column_mapping JSON to remap CSV columns before parsing.
     * Returns parsed rows with per-row validation status.
     */
    public function previewImport(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:5120',
            ]);

            $csvContent = file_get_contents($request->file('file')->getPathname());
            $columnMapping = $request->input('column_mapping');
            $mapping = $columnMapping ? (is_string($columnMapping) ? json_decode($columnMapping, true) : $columnMapping) : [];

            $preview = $this->productImportService->previewImport($csvContent, $mapping ?: []);

            return response()->json([
                'success' => true,
                'data' => $preview,
            ]);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Check the status of an async product import.
     */
    public function related(Request $request, string $id): JsonResponse
    {
        $products = $this->productService->getRelated($id, $request->limit ?? 8);
        return response()->json(['success' => true, 'data' => $products]);
    }

    public function importStatus(string $importId): JsonResponse
    {
        $status = ProcessProductImportJob::getStatus($importId);
        $result = ProcessProductImportJob::getResult($importId);

        return response()->json([
            'success' => true,
            'data' => [
                'import_id' => $importId,
                'status' => $status,
                'result' => $result,
            ],
        ]);
    }
}
