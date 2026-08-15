<?php

namespace Tests\Feature\E2E;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Banner;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class ProductCatalogE2ETest extends TestCase
{
    use RefreshDatabase;

    protected string $apiPrefix = '/api/v1';

    /** @test */
    public function test_browse_products_catalog()
    {
        // ── Setup: Create categories, brands, and products ──
        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'description' => 'Electronic gadgets and devices',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'TechBrand',
            'slug' => 'techbrand',
            'description' => 'Premium tech products',
        ]);

        $products = [];
        for ($i = 1; $i <= 5; $i++) {
            $products[] = Product::create([
                'name' => "Product {$i}",
                'slug' => "product-{$i}",
                'description' => "Description for product {$i}",
                'short_description' => "Short desc {$i}",
                'price' => 99.99 + ($i * 10),
                'quantity' => 50,
                'sku' => "SKU-00{$i}",
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'status' => 'PUBLISHED',
                'is_featured' => $i <= 2,
                'is_new' => $i <= 3,
            ]);
        }

        // ── Step 1: List all published products ──
        $listResponse = $this->getJson("{$this->apiPrefix}/products");

        $listResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => ['id', 'name', 'slug', 'price', 'status'],
                    ],
                ],
            ])
            ->assertJson(['success' => true]);

        $this->assertCount(5, $listResponse->json('data.data'));

        // ── Step 2: Get single product details ──
        $productId = $products[0]->id;

        $showResponse = $this->getJson("{$this->apiPrefix}/products/{$productId}");

        $showResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $productId,
                    'name' => 'Product 1',
                    'slug' => 'product-1',
                ],
            ]);

        // ── Step 3: Featured products ──
        $featuredResponse = $this->getJson("{$this->apiPrefix}/products/featured?limit=5");

        $featuredResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(2, $featuredResponse->json('data'));

        // ── Step 4: New arrivals ──
        $newResponse = $this->getJson("{$this->apiPrefix}/products/new-arrivals?limit=5");

        $newResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(5, $newResponse->json('data'));

        // ── Step 5: Search products ──
        $searchResponse = $this->getJson("{$this->apiPrefix}/products/search?q=Product&limit=10");

        $searchResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(5, $searchResponse->json('data'));

        // Search with specific term
        $searchSpecificResponse = $this->getJson("{$this->apiPrefix}/products/search?q=Product+3&limit=10");

        $searchSpecificResponse->assertStatus(200);

        // ── Step 6: Products by category ──
        $byCategoryResponse = $this->getJson("{$this->apiPrefix}/products/category/{$category->id}");

        $byCategoryResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(5, $byCategoryResponse->json('data.data'));

        // ── Step 7: Product availability check ──
        $availResponse = $this->getJson("{$this->apiPrefix}/products/{$productId}/availability?quantity=5");

        $availResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['available' => true],
            ]);

        // Check with insufficient quantity
        $availResponse2 = $this->getJson("{$this->apiPrefix}/products/{$productId}/availability?quantity=100");

        $availResponse2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['available' => false],
            ]);
    }

    /** @test */
    public function test_categories_and_brands()
    {
        // ── Setup ──
        $parentCategory = Category::create([
            'name' => 'Clothing',
            'slug' => 'clothing',
            'description' => 'All clothing items',
            'is_active' => true,
        ]);

        $childCategory = Category::create([
            'name' => 'Men',
            'slug' => 'men',
            'description' => 'Men\'s clothing',
            'parent_id' => $parentCategory->id,
            'is_active' => true,
        ]);

        $brand1 = Brand::create([
            'name' => 'Nike',
            'slug' => 'nike',
            'description' => 'Sportswear brand',
        ]);

        $brand2 = Brand::create([
            'name' => 'Adidas',
            'slug' => 'adidas',
            'description' => 'Athletic brand',
        ]);

        // ── Step 1: List categories ──
        $categoriesResponse = $this->getJson("{$this->apiPrefix}/categories");

        $categoriesResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'slug'],
                ],
            ]);

        // ── Step 2: Get single category ──
        $this->getJson("{$this->apiPrefix}/categories/{$parentCategory->id}")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Clothing',
                    'slug' => 'clothing',
                ],
            ]);

        // ── Step 3: Category hierarchy ──
        $hierarchyResponse = $this->getJson("{$this->apiPrefix}/categories/hierarchy");

        $hierarchyResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 4: Subcategories ──
        $this->getJson("{$this->apiPrefix}/categories/{$parentCategory->id}/subcategories")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 5: List brands ──
        $brandsResponse = $this->getJson("{$this->apiPrefix}/products/brand");

        $brandsResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(2, $brandsResponse->json('data'));
    }

    /** @test */
    public function test_banners_and_promotions()
    {
        // ── Setup active banners ──
        Banner::create([
            'title' => 'Hero Banner',
            'subtitle' => 'Big Sale',
            'image_url' => 'https://example.com/banner1.jpg',
            'type' => 'HERO',
            'position' => 1,
            'is_active' => true,
            'show_on_mobile' => true,
            'show_on_desktop' => true,
        ]);

        Banner::create([
            'title' => 'Sale Banner',
            'subtitle' => '50% Off',
            'image_url' => 'https://example.com/banner2.jpg',
            'type' => 'SALE',
            'position' => 2,
            'is_active' => true,
            'show_on_mobile' => true,
            'show_on_desktop' => true,
        ]);

        // Inactive banner (should not appear)
        Banner::create([
            'title' => 'Inactive Banner',
            'subtitle' => 'Hidden',
            'image_url' => 'https://example.com/banner3.jpg',
            'type' => 'HERO',
            'position' => 3,
            'is_active' => false,
        ]);

        // ── Step 1: Get active banners ──
        $this->getJson("{$this->apiPrefix}/banners")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 2: Get hero banners specifically ──
        $this->getJson("{$this->apiPrefix}/banners/hero")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 3: Get sale banners ──
        $this->getJson("{$this->apiPrefix}/banners/sale")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 4: Get single banner ──
        $banner = Banner::first();
        $this->getJson("{$this->apiPrefix}/banners/{$banner->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 5: List promotions ──
        Promotion::create([
            'title' => 'Summer Sale',
            'description' => 'Big summer discounts',
            'type' => 'SEASONAL',
            'discount' => 20,
            'status' => 'ACTIVE',
            'is_active' => true,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);

        $this->getJson("{$this->apiPrefix}/promotions")
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function test_product_not_found_scenarios()
    {
        $fakeId = (string) Str::uuid();

        // Non-existent product
        $this->getJson("{$this->apiPrefix}/products/{$fakeId}")
            ->assertStatus(404);

        // Non-existent category
        $this->getJson("{$this->apiPrefix}/categories/{$fakeId}")
            ->assertStatus(404);

        // Non-existent banner
        $this->getJson("{$this->apiPrefix}/banners/{$fakeId}")
            ->assertStatus(404);

        // Search with no results
        $searchResponse = $this->getJson("{$this->apiPrefix}/products/search?q=NonExistentProductXYZ&limit=10");
        $searchResponse->assertStatus(200);
        $this->assertCount(0, $searchResponse->json('data'));
    }
}
