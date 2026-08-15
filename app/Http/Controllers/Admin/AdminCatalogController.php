<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use Illuminate\Http\Request;

/**
 * @deprecated Blade admin views are no longer used (admin is React SPA).
 *             This controller is kept for reference only.
 */
class AdminCatalogController extends Controller
{
    public function __construct(
        protected AdminService $adminService
    ) {
        $this->middleware('auth');
        $this->middleware('admin');
    }

    // ── Products ──
    public function productsIndex(Request $request)
    {
        $products = $this->adminService->getProducts($request->only(['search', 'status']));
        return view('admin.products.index', compact('products'));
    }

    public function productsCreate()
    {
        $data = $this->adminService->getProductFormData();
        return view('admin.products.form', [
            'categories' => $data['categories'],
            'brands' => $data['brands'],
        ]);
    }

    public function productsStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku',
            'price' => 'required|numeric|min:0',
            'old_price' => 'nullable|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'quantity' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:DRAFT,PUBLISHED,ARCHIVED',
            'is_featured' => 'nullable|boolean',
            'is_new' => 'nullable|boolean',
        ]);

        $product = $this->adminService->createProduct($validated);
        return redirect()->route('admin.products.show', $product->id)
            ->with('success', 'Product created successfully');
    }

    public function productsShow(string $id)
    {
        $product = $this->adminService->getProductModel($id);
        return view('admin.products.show', compact('product'));
    }

    public function productsEdit(string $id)
    {
        $product = $this->adminService->getProductModel($id);
        $data = $this->adminService->getProductFormData();
        return view('admin.products.form', [
            'product' => $product,
            'categories' => $data['categories'],
            'brands' => $data['brands'],
        ]);
    }

    public function productsUpdate(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku,' . $id,
            'price' => 'required|numeric|min:0',
            'old_price' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'quantity' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:DRAFT,PUBLISHED,ARCHIVED',
            'is_featured' => 'nullable|boolean',
            'is_new' => 'nullable|boolean',
        ]);

        $this->adminService->updateProduct($id, $validated);
        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated successfully');
    }

    public function productsDestroy(string $id)
    {
        $this->adminService->deleteProduct($id);
        return redirect()->route('admin.products.index')
            ->with('success', 'Product deleted successfully');
    }

    // ── Low Stock Products ──
    public function lowStockProducts()
    {
        $products = $this->adminService->getLowStockProducts();
        return view('admin.products.low-stock', compact('products'));
    }

    // ── Product Variants ──
    public function variantsByProduct(string $productId)
    {
        $product = $this->adminService->getProductModel($productId);
        $variants = $this->adminService->getVariantsByProduct($productId);
        return view('admin.variants.index', compact('product', 'variants'));
    }

    public function variantsStore(Request $request, string $productId)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:product_variants,sku',
            'price' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
        ]);
        $validated['product_id'] = $productId;

        $this->adminService->createVariant($validated);
        return redirect()->route('admin.variants.by-product', $productId)
            ->with('success', 'Variant created successfully');
    }

    public function variantsUpdate(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:product_variants,sku,' . $id,
            'price' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
        ]);

        $this->adminService->updateVariant($id, $validated);
        return redirect()->back()->with('success', 'Variant updated successfully');
    }

    public function variantsDestroy(string $id)
    {
        $this->adminService->deleteVariant($id);
        return redirect()->back()->with('success', 'Variant deleted successfully');
    }

    // ── Categories ──
    public function categoriesIndex()
    {
        $categories = $this->adminService->getCategories();
        return view('admin.categories.index', compact('categories'));
    }

    public function categoriesCreate()
    {
        return view('admin.categories.form');
    }

    public function categoriesStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'is_active' => 'nullable|boolean',
        ]);

        $this->adminService->createCategory($validated);
        return redirect()->route('admin.categories.index')
            ->with('success', 'Category created successfully');
    }

    public function categoriesEdit(string $id)
    {
        $category = $this->adminService->getCategoryById($id);
        abort_unless($category, 404, 'Category not found');
        $categories = $this->adminService->getCategories();
        return view('admin.categories.form', compact('category', 'categories'));
    }

    public function categoriesUpdate(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'is_active' => 'nullable|boolean',
        ]);

        $this->adminService->updateCategory($id, $validated);
        return redirect()->route('admin.categories.index')
            ->with('success', 'Category updated successfully');
    }

    public function categoriesDestroy(string $id)
    {
        $this->adminService->deleteCategory($id);
        return redirect()->route('admin.categories.index')
            ->with('success', 'Category deleted successfully');
    }

    // ── Brands ──
    public function brandsIndex()
    {
        $brands = $this->adminService->getBrands();
        return view('admin.brands.index', compact('brands'));
    }

    public function brandsCreate()
    {
        return view('admin.brands.form');
    }

    public function brandsStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|string',
        ]);

        $this->adminService->createBrand($validated);
        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand created successfully');
    }

    public function brandsEdit(string $id)
    {
        $brand = $this->adminService->getBrandById($id);
        abort_unless($brand, 404, 'Brand not found');
        return view('admin.brands.form', compact('brand'));
    }

    public function brandsUpdate(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|string',
        ]);

        $this->adminService->updateBrand($id, $validated);
        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand updated successfully');
    }

    public function brandsDestroy(string $id)
    {
        $this->adminService->deleteBrand($id);
        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand deleted successfully');
    }
}
