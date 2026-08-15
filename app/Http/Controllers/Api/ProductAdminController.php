<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessProductImportJob;
use App\Models\Product;
use App\Services\AdminService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductAdminController extends Controller
{
    public function __construct(
        protected AdminService $adminService
    ) {}

    public function productsList(Request $request): JsonResponse
    {
        $paginator = $this->adminService->getProducts($request->all());
        return response()->json([
            'success' => true,
            'data' => [
                'products' => $paginator->items(),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'pages' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }

    public function categoriesList(Request $request): JsonResponse
    {
        $paginator = $this->adminService->getCategoriesPaginated($request->all());
        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $paginator->items(),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'pages' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }

    public function brandsList(Request $request): JsonResponse
    {
        $paginator = $this->adminService->getBrandsPaginated($request->all());
        return response()->json([
            'success' => true,
            'data' => [
                'brands' => $paginator->items(),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'pages' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }

    /**
     * Bulk delete products.
     * POST /api/v1/admin/products/bulk-delete
     */
    public function bulkDeleteProducts(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'string']);
            $deleted = 0;
            $errors = [];
            foreach ($validated['ids'] as $id) {
                try {
                    $product = Product::find($id);
                    if ($product) {
                        $product->delete();
                        $deleted++;
                    } else {
                        $errors[] = "Product {$id} not found";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Product {$id}: {$e->getMessage()}";
                }
            }
            return response()->json([
                'success' => true,
                'message' => "{$deleted} product(s) deleted",
                'data' => ['deleted' => $deleted, 'errors' => $errors],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Product Import ──

    public function importProducts(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'csv' => 'required|string',
            ]);

            // Save CSV to storage and dispatch async job
            $importId = ProcessProductImportJob::generateImportId();
            $csvFilePath = "imports/pending/{$importId}.csv";
            Storage::disk('local')->put($csvFilePath, $validated['csv']);

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
}
