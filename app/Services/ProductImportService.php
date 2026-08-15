<?php

namespace App\Services;

use App\Exceptions\AppError;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductImportService
{
    /**
     * The list of expected/internal field names for product import.
     */
    public function getExpectedFields(): array
    {
        return [
            'name', 'description', 'short_description', 'price', 'old_price', 'cost',
            'quantity', 'sku', 'barcode', 'category', 'brand', 'images', 'tags', 'status',
            'badge', 'is_featured', 'hover_image_url', 'seo_title', 'seo_description', 'seo_keywords',
            'variant_sku', 'variant_color', 'variant_size', 'variant_price', 'variant_quantity',
        ];
    }

    /**
     * Build a suggested automatic column mapping based on CSV headers.
     * Matches CSV headers to expected fields case-insensitively.
     */
    public function suggestColumnMapping(array $csvHeaders): array
    {
        $expected = $this->getExpectedFields();
        $mapping = [];
        $headerIndex = [];
        foreach ($csvHeaders as $h) {
            $headerIndex[strtolower(trim($h))] = $h;
        }

        // Try to match each expected field to a CSV header
        foreach ($expected as $field) {
            $normalized = strtolower(preg_replace('/[^a-z0-9]/', '', $field));
            $bestMatch = null;
            $bestScore = 0;

            foreach ($headerIndex as $lowerH => $origH) {
                $normalizedH = strtolower(preg_replace('/[^a-z0-9]/', '', $origH));
                // Exact match
                if ($lowerH === $field || $lowerH === str_replace('_', '', $field)) {
                    $bestMatch = $origH;
                    $bestScore = 100;
                    break;
                }
                // Contains match
                if (str_contains($normalizedH, $normalized) || str_contains($normalized, $normalizedH)) {
                    $score = strlen($normalized) / max(strlen($normalizedH), strlen($normalized)) * 80;
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestMatch = $origH;
                    }
                }
            }

            if ($bestMatch && $bestScore >= 60) {
                $mapping[$bestMatch] = $field;
            }
        }

        return $mapping;
    }

    /**
     * Remap CSV header row according to a column mapping.
     * The mapping is {csv_column_name: expected_field_name}.
     * Returns modified CSV content with remapped headers.
     */
    public function remapCSVHeaders(string $csvContent, array $columnMapping): string
    {
        $lines = explode("\n", $csvContent);
        if (empty($lines)) {
            return $csvContent;
        }

        $headers = $this->parseCSVLine($lines[0]);
        $remapped = [];
        foreach ($headers as $h) {
            $trimmed = trim($h);
            if (isset($columnMapping[$trimmed])) {
                $remapped[] = $columnMapping[$trimmed];
            } else {
                $remapped[] = $trimmed;
            }
        }
        $lines[0] = implode(',', $remapped);

        return implode("\n", $lines);
    }

    /**
     * Parse a single CSV line, respecting quoted fields.
     */
    private function parseCSVLine(string $line): array
    {
        $result = [];
        $current = '';
        $inQuotes = false;

        for ($i = 0; $i < strlen($line); $i++) {
            $char = $line[$i];
            if ($char === '"') {
                $inQuotes = ! $inQuotes;
            } elseif ($char === ',' && ! $inQuotes) {
                $result[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }
        $result[] = trim($current);

        return $result;
    }

    /**
     * Parse CSV content into rows.
     */
    public function parseCSV(string $csvContent): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $csvContent)));
        if (count($lines) < 2) {
            throw AppError::validation('CSV must have a header row and at least one data row');
        }

        $headers = $this->parseCSVLine($lines[0]);
        $headerMap = [];
        foreach ($headers as $i => $h) {
            $headerMap[strtolower(trim($h))] = $i;
        }

        // Validate required columns
        $required = ['name', 'price', 'quantity'];
        $missing = [];
        foreach ($required as $col) {
            if (! isset($headerMap[$col])) {
                $missing[] = $col;
            }
        }
        if (! empty($missing)) {
            throw AppError::validation('CSV missing required columns: '.implode(', ', $missing));
        }

        $rows = [];
        for ($i = 1; $i < count($lines); $i++) {
            $cols = $this->parseCSVLine($lines[$i]);
            $get = function (string $key) use ($headerMap, $cols) {
                $key = strtolower($key);

                return isset($headerMap[$key]) && $headerMap[$key] < count($cols)
                    ? trim($cols[$headerMap[$key]])
                    : null;
            };

            $name = $get('name');
            if (empty($name)) {
                continue;
            }

            $imageUrls = $get('images');
            $rows[] = [
                'name' => $name,
                'description' => $get('description') ?? '',
                'short_description' => $get('short_description') ?? $get('shortdescription'),
                'price' => (float) ($get('price') ?? 0),
                'old_price' => $get('old_price') ? (float) $get('old_price') : null,
                'cost' => $get('cost') ? (float) $get('cost') : null,
                'quantity' => (int) ($get('quantity') ?? 0),
                'sku' => $get('sku'),
                'barcode' => $get('barcode'),
                'category' => $get('category'),
                'brand' => $get('brand'),
                'images' => $imageUrls ? array_filter(array_map('trim', explode(',', $imageUrls))) : [],
                'tags' => $get('tags'),
                'status' => $get('status'),
                'is_featured' => strtolower($get('is_featured') ?? 'false') === 'true',
                'hover_image_url' => $get('hover_image_url') ?? $get('hover_image'),
                'badge' => $get('badge'),
                'seo_title' => $get('seo_title') ?? $get('seotitle'),
                'seo_description' => $get('seo_description') ?? $get('seodescription'),
                'seo_keywords' => $get('seo_keywords') ?? $get('seokeywords'),
                'variant_sku' => $get('variant_sku') ?? $get('variantsku'),
                'variant_color' => $get('variant_color') ?? $get('variantcolor'),
                'variant_size' => $get('variant_size') ?? $get('variantsize'),
                'variant_price' => $get('variant_price') ? (float) $get('variant_price') : null,
                'variant_quantity' => $get('variant_quantity') ? (int) $get('variant_quantity') : null,
            ];
        }

        return $rows;
    }

    /**
     * Import parsed CSV rows as products.
     */
    public function importProducts(array $rows): array
    {
        $result = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_details' => [],
            'imported_products' => [],
        ];

        // Pre-fetch all categories and brands for name resolution
        $allCategories = Category::select('id', 'name', 'slug')->get();
        $allBrands = Brand::select('id', 'name')->get();

        $categoryByName = [];
        $categoryBySlug = [];
        foreach ($allCategories as $c) {
            $categoryByName[strtolower($c->name)] = $c->id;
            $categoryBySlug[strtolower($c->slug)] = $c->id;
        }
        $brandByName = [];
        foreach ($allBrands as $b) {
            $brandByName[strtolower($b->name)] = $b->id;
        }

        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 2;

            try {
                // Validate required fields
                if (empty($row['name'])) {
                    $result['skipped']++;
                    $result['error_details'][] = ['row' => $rowNum, 'message' => 'Missing product name'];

                    continue;
                }
                if ($row['price'] <= 0) {
                    $result['skipped']++;
                    $result['error_details'][] = ['row' => $rowNum, 'message' => "Invalid price for \"{$row['name']}\""];

                    continue;
                }
                if ($row['quantity'] < 0) {
                    $result['skipped']++;
                    $result['error_details'][] = ['row' => $rowNum, 'message' => "Invalid quantity for \"{$row['name']}\""];

                    continue;
                }

                // Check for duplicate SKU
                if (! empty($row['sku'])) {
                    $existing = Product::where('sku', $row['sku'])->exists();
                    if ($existing) {
                        $result['skipped']++;
                        $result['error_details'][] = ['row' => $rowNum, 'message' => "SKU \"{$row['sku']}\" already exists"];

                        continue;
                    }
                }

                // Resolve or create category
                $categoryId = $this->resolveCategory($row['category'], $categoryByName, $categoryBySlug);
                // Resolve or create brand
                $brandId = $this->resolveBrand($row['brand'], $brandByName);

                // Generate slug
                $baseSlug = Str::slug($row['name']);
                $slug = $baseSlug.'-'.substr(md5(uniqid()), 0, 8);

                // Generate SKU if not provided
                $sku = ! empty($row['sku']) ? $row['sku'] : strtoupper(substr($row['name'], 0, 3)).'-'.substr(md5(uniqid()), 0, 6);

                DB::beginTransaction();

                try {
                    $product = Product::create([
                        'name' => $row['name'],
                        'slug' => $slug,
                        'description' => $row['description'] ?? '',
                        'short_description' => $row['short_description'] ?? null,
                        'price' => $row['price'],
                        'old_price' => $row['old_price'] ?? null,
                        'cost' => $row['cost'] ?? null,
                        'quantity' => $row['quantity'] ?? 0,
                        'sku' => $sku,
                        'barcode' => $row['barcode'] ?? null,
                        'badge' => $row['badge'] ?? null,
                        'hover_image_url' => $row['hover_image_url'] ?? null,
                        'tags' => $row['tags'] ?? null,
                        'is_featured' => $row['is_featured'] ?? false,
                        'status' => strtoupper($row['status'] ?? 'DRAFT'),
                        'seo_title' => $row['seo_title'] ?? null,
                        'seo_description' => $row['seo_description'] ?? null,
                        'seo_keywords' => $row['seo_keywords'] ?? null,
                        'category_id' => $categoryId,
                        'brand_id' => $brandId,
                    ]);

                    // Create product images
                    if (! empty($row['images'])) {
                        foreach ($row['images'] as $order => $url) {
                            ProductImage::create([
                                'product_id' => $product->id,
                                'url' => $url,
                                'display_order' => $order,
                            ]);
                        }
                    }

                    // Create a default variant
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'name' => $row['name'],
                        'sku' => $sku.'-DEFAULT',
                        'attributes' => json_encode([]),
                        'price' => $row['price'],
                        'quantity' => $row['quantity'] ?? 0,
                        'images' => json_encode($row['images'] ?? []),
                    ]);

                    // Create variant row if variant columns were provided
                    if (! empty($row['variant_sku']) || ! empty($row['variant_color']) || ! empty($row['variant_size'])) {
                        $variantAttrs = [];
                        if (! empty($row['variant_color'])) {
                            $variantAttrs['color'] = $row['variant_color'];
                        }
                        if (! empty($row['variant_size'])) {
                            $variantAttrs['size'] = $row['variant_size'];
                        }

                        ProductVariant::create([
                            'product_id' => $product->id,
                            'name' => implode(' / ', array_filter([$row['variant_color'] ?? null, $row['variant_size'] ?? null])) ?: 'Variant',
                            'sku' => $row['variant_sku'] ?? $sku.'-VAR',
                            'attributes' => json_encode($variantAttrs),
                            'price' => $row['variant_price'] ?? $row['price'],
                            'quantity' => $row['variant_quantity'] ?? $row['quantity'] ?? 0,
                            'images' => '[]',
                        ]);
                    }

                    DB::commit();

                    $result['imported']++;
                    $result['imported_products'][] = ['name' => $product->name, 'sku' => $product->sku];
                    Log::info("CSV import: Created product \"{$product->name}\" ({$product->sku})");
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            } catch (\Exception $e) {
                $result['errors']++;
                $result['error_details'][] = [
                    'row' => $rowNum,
                    'message' => $e->getMessage(),
                ];
                Log::error("CSV import row {$rowNum} error: {$e->getMessage()}");
            }
        }

        return $result;
    }

    /**
     * Resolve category by name/slug or create on the fly.
     */
    private function resolveCategory(?string $name, array &$byName, array &$bySlug): ?string
    {
        if (empty($name)) {
            return null;
        }

        $lower = strtolower(trim($name));

        if (isset($byName[$lower])) {
            return $byName[$lower];
        }
        if (isset($bySlug[$lower])) {
            return $bySlug[$lower];
        }

        // Create on the fly
        try {
            $slug = Str::slug($name);
            $category = Category::create(['name' => trim($name), 'slug' => $slug ?: 'cat-'.uniqid()]);
            $byName[strtolower($category->name)] = $category->id;
            $bySlug[strtolower($category->slug)] = $category->id;

            return $category->id;
        } catch (\Exception $e) {
            Log::warning("Could not create category \"{$name}\": {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Resolve brand by name or create on the fly.
     */
    private function resolveBrand(?string $name, array &$byName): ?string
    {
        if (empty($name)) {
            return null;
        }

        $lower = strtolower(trim($name));

        if (isset($byName[$lower])) {
            return $byName[$lower];
        }

        // Create on the fly
        try {
            $slug = Str::slug($name);
            $brand = Brand::create(['name' => trim($name), 'slug' => $slug ?: 'brand-'.uniqid()]);
            $byName[strtolower($brand->name)] = $brand->id;

            return $brand->id;
        } catch (\Exception $e) {
            Log::warning("Could not create brand \"{$name}\": {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Preview a CSV import — parse and validate each row WITHOUT persisting anything.
     * Optionally accepts a column_mapping to remap CSV columns before parsing.
     * Returns parsed rows with per-row validation status and issues.
     */
    public function previewImport(string $csvContent, array $columnMapping = []): array
    {
        // Apply column remapping if provided
        if (! empty($columnMapping)) {
            $csvContent = $this->remapCSVHeaders($csvContent, $columnMapping);
        }
        $rows = $this->parseCSV($csvContent);

        // Pre-fetch categories and brands for resolution hints
        $allCategories = Category::select('id', 'name', 'slug')->get();
        $allBrands = Brand::select('id', 'name')->get();

        $categoryByName = [];
        $categoryBySlug = [];
        foreach ($allCategories as $c) {
            $categoryByName[strtolower($c->name)] = $c->id;
            $categoryBySlug[strtolower($c->slug)] = $c->id;
        }
        $brandByName = [];
        foreach ($allBrands as $b) {
            $brandByName[strtolower($b->name)] = $b->id;
        }

        $previewRows = [];
        $validCount = 0;
        $warningCount = 0;
        $errorCount = 0;

        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 2;
            $issues = [];

            // Validate required fields
            if (empty($row['name'])) {
                $issues[] = ['type' => 'error', 'message' => 'Missing product name'];
            }
            if ($row['price'] <= 0) {
                $issues[] = ['type' => 'error', 'message' => "Invalid price: {$row['price']}"];
            }
            if ($row['quantity'] < 0) {
                $issues[] = ['type' => 'error', 'message' => "Invalid quantity: {$row['quantity']}"];
            }

            // Check for duplicate SKU
            if (! empty($row['sku'])) {
                $existing = Product::where('sku', $row['sku'])->exists();
                if ($existing) {
                    $issues[] = ['type' => 'error', 'message' => "SKU \"{$row['sku']}\" already exists"];
                }
            }

            // Check category resolution
            if (! empty($row['category'])) {
                $lower = strtolower(trim($row['category']));
                if (! isset($categoryByName[$lower]) && ! isset($categoryBySlug[$lower])) {
                    $issues[] = ['type' => 'warning', 'message' => "Category \"{$row['category']}\" will be auto-created"];
                }
            }

            // Check brand resolution
            if (! empty($row['brand'])) {
                $lower = strtolower(trim($row['brand']));
                if (! isset($brandByName[$lower])) {
                    $issues[] = ['type' => 'warning', 'message' => "Brand \"{$row['brand']}\" will be auto-created"];
                }
            }

            // Determine row status
            $rowStatus = 'valid';
            foreach ($issues as $issue) {
                if ($issue['type'] === 'error') {
                    $rowStatus = 'error';
                    break;
                }
                if ($issue['type'] === 'warning') {
                    $rowStatus = 'warning';
                }
            }

            // Count summary
            if ($rowStatus === 'valid') {
                $validCount++;
            } elseif ($rowStatus === 'warning') {
                $warningCount++;
            } else {
                $errorCount++;
            }

            $previewRows[] = [
                'row_number' => $rowNum,
                'status' => $rowStatus,
                'issues' => $issues,
                'data' => $row,
            ];
        }

        // Determine headers from the first row's keys
        $headers = ! empty($rows) ? array_keys($rows[0]) : [];

        // Build suggested mapping for the frontend
        $originalHeaders = ! empty($columnMapping) ? array_keys($columnMapping) : $headers;
        $suggestedMapping = $this->suggestColumnMapping($originalHeaders);

        return [
            'headers' => $headers,
            'rows' => $previewRows,
            'total_rows' => count($rows),
            'suggested_mapping' => $suggestedMapping,
            'validation' => [
                'valid' => $validCount,
                'warnings' => $warningCount,
                'errors' => $errorCount,
            ],
        ];
    }

    /**
     * Import products from CSV file content with optional column mapping.
     */
    public function importFromCSV(string $csvContent, array $columnMapping = []): array
    {
        if (! empty($columnMapping)) {
            $csvContent = $this->remapCSVHeaders($csvContent, $columnMapping);
        }
        $rows = $this->parseCSV($csvContent);

        return $this->importProducts($rows);
    }
}
