<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Assign products with NULL or invalid category_id to the best-matching category
     * based on the product's name (case-insensitive fuzzy match).
     *
     * Strategy (tried in order):
     *   1. Exact substring: product name contains the category name (or vice versa)
     *   2. Keyword match: product name contains a keyword from the category name
     *   3. Fallback: assign to the first available "General" category or the default category
     *
     * This migration is idempotent — only products with NULL or orphaned category_id are touched.
     */
    public function up(): void
    {
        // Gather all valid category IDs
        $validCategoryIds = Category::pluck('id')->toArray();
        if (empty($validCategoryIds)) {
            echo "⚠️  No categories found — skipping migration.\n";
            return;
        }

        $categories = Category::select('id', 'name', 'slug')->get();
        $defaultCategoryId = $categories->first()?->id;

        // Find products with NULL or orphaned category_id
        $products = Product::whereNull('category_id')
            ->orWhereNotIn('category_id', $validCategoryIds)
            ->get();

        if ($products->isEmpty()) {
            echo "✅ All products already have valid category assignments.\n";
            return;
        }

        $updated = 0;

        foreach ($products as $product) {
            $productName = mb_strtolower($product->name);
            $matchedCategory = null;
            $bestScore = 0;

            foreach ($categories as $category) {
                $catName = mb_strtolower($category->name);
                $score = 0;

                // Strategy 1: Exact substring match (product name contains category name or vice versa)
                if (str_contains($productName, $catName) || str_contains($catName, $productName)) {
                    // Longer match = more specific = higher score
                    $score = max(
                        strlen($catName) * 2,  // category name is within product name
                        strlen($productName)    // product name is within category name
                    );
                }

                // Strategy 2: Word-level matching — check if any word from category name appears in product name
                if ($score === 0) {
                    $catWords = explode(' ', $catName);
                    $matchedWords = 0;
                    foreach ($catWords as $word) {
                        if (strlen($word) > 2 && str_contains($productName, $word)) {
                            $matchedWords++;
                        }
                    }
                    if ($matchedWords > 0) {
                        $score = $matchedWords * 10 + strlen($catName);
                    }
                }

                // Strategy 3: Check badge field
                if ($score === 0 && !empty($product->badge)) {
                    $badge = mb_strtolower($product->badge);
                    if (str_contains($catName, $badge) || str_contains($badge, $catName)) {
                        $score = 5 + strlen($catName);
                    }
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $matchedCategory = $category;
                }
            }

            if ($matchedCategory && $bestScore > 3) {
                $product->update(['category_id' => $matchedCategory->id]);
                $updated++;
                echo "  ✓ \"{$product->name}\" → {$matchedCategory->name} (score: {$bestScore})\n";
            } else {
                // Fallback: assign to default category
                $product->update(['category_id' => $defaultCategoryId]);
                $updated++;
                echo "  ⚠ \"{$product->name}\" → default category \"{$categories->firstWhere('id', $defaultCategoryId)?->name}\" (no strong match)\n";
            }
        }

        echo ($updated > 0)
            ? "✅ Assigned categories to {$updated} product(s).\n"
            : "ℹ️  No products needed category assignment.\n";
    }

    /**
     * Reverse is not available since this fixes data integrity.
     * We cannot know what the previous NULL values meant.
     */
    public function down(): void
    {
        echo "⚠️  This migration cannot be reversed. Products' category_id values have been set.\n";
        echo "   To revert, manually update products' category_id to NULL as needed.\n";
    }
};
