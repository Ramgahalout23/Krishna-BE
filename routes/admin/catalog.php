<?php

// ── Admin Catalog Routes (Products, Categories, Brands, Inventory, Variants) ──
// Included from routes/api.php within Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductAdminController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\ExportController;

// ── Products ──
Route::get('/products', [ProductAdminController::class, 'productsList']);
Route::get('/products/export', [ExportController::class, 'exportProducts']);
Route::post('/products', [ProductController::class, 'store']);
Route::post('/products/import/preview', [ProductController::class, 'previewImport']);
Route::post('/products/import', [ProductController::class, 'importProductsFromCSV']);
Route::post('/products/bulk-delete', [ProductAdminController::class, 'bulkDeleteProducts']);
Route::get('/products/import/{importId}/status', [ProductController::class, 'importStatus']);
Route::put('/products/{id}', [ProductController::class, 'update']);
Route::delete('/products/{id}', [ProductController::class, 'destroy']);
Route::get('/products/low-stock', [InventoryController::class, 'lowStock']);
Route::patch('/products/{id}/publish', [ProductController::class, 'publishProduct']);
Route::patch('/products/{id}/archive', [ProductController::class, 'archiveProduct']);

// ── Categories ──
Route::get('/categories', [ProductAdminController::class, 'categoriesList']);
Route::post('/categories', [CategoryController::class, 'store']);
Route::put('/categories/{id}', [CategoryController::class, 'update']);
Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

// ── Brands ──
Route::get('/brands', [ProductAdminController::class, 'brandsList']);
Route::post('/brands', [BrandController::class, 'store']);
Route::put('/brands/{id}', [BrandController::class, 'update']);
Route::delete('/brands/{id}', [BrandController::class, 'destroy']);

// ── Inventory (specific routes BEFORE generic {productId})
Route::get('/inventory/stats', [InventoryController::class, 'stats']);
Route::get('/inventory/barcode-labels', [InventoryController::class, 'barcodeLabels']);
Route::get('/inventory/export', [InventoryController::class, 'exportInventory']);
Route::post('/inventory/batch-update', [InventoryController::class, 'batchUpdate']);
Route::get('/inventory', [InventoryController::class, 'index']);
Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock']);
Route::post('/inventory/add', [InventoryController::class, 'addStock']);
Route::patch('/inventory/{id}/stock', [InventoryController::class, 'updateStock']);
Route::post('/inventory/reduce', [InventoryController::class, 'reduceStock']);
Route::get('/inventory/{productId}', [InventoryController::class, 'show']);
Route::get('/inventory/{productId}/movement', [InventoryController::class, 'movement']);

// ── Product Variants ──
Route::get('/products/{productId}/variants', [ProductVariantController::class, 'byProduct']);
Route::post('/products/{productId}/variants', [ProductVariantController::class, 'store']);
Route::post('/products/{productId}/variants/bulk', [ProductVariantController::class, 'bulkCreateVariants']);
Route::get('/variants', [ProductVariantController::class, 'getAllVariants']);
Route::put('/variants/{id}', [ProductVariantController::class, 'update']);
Route::delete('/variants/{id}', [ProductVariantController::class, 'destroy']);
// ── Barcode Scanner Lookup (must be before generic /variants/{id})
Route::get('/variants/lookup-sku/{sku}', [ProductVariantController::class, 'lookupBySku']);
Route::get('/variants/low-stock', [ProductVariantController::class, 'lowStock']);
Route::patch('/variants/{id}/quantity', [ProductVariantController::class, 'updateQuantity']);
Route::patch('/variants/bulk-quantity', [ProductVariantController::class, 'bulkUpdateQuantities']);

// ── Variant Stock Movement (Advanced Inventory) ──
Route::post('/variants/{id}/adjust-stock', [InventoryController::class, 'adjustVariantStock']);
Route::get('/variants/{id}/stock-movements', [InventoryController::class, 'variantStockMovements']);
Route::get('/variants/{id}/barcode-label', [InventoryController::class, 'variantBarcodeLabel']);
Route::post('/variants/batch-barcode-labels', [InventoryController::class, 'batchVariantBarcodeLabels']);
Route::get('/barcode-batches/{batchId}/status', [InventoryController::class, 'barcodeBatchStatus']);
Route::get('/barcode-batches/{batchId}/download', [InventoryController::class, 'barcodeBatchDownload']);
Route::post('/variants/bulk-adjust-stock', [InventoryController::class, 'bulkAdjustVariantStock']);
