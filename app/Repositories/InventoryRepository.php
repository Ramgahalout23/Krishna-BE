<?php

namespace App\Repositories;

use App\Models\Inventory;
use App\Models\InventoryHistory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class InventoryRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return Inventory::class;
    }

    public function findByProduct(string $productId): ?Inventory
    {
        return Inventory::with('product')->where('product_id', $productId)->first();
    }

    public function getAllWithProduct(array $filters = []): LengthAwarePaginator
    {
        $query = Inventory::with('product');
        if (!empty($filters['low_stock'])) {
            $query->where('available_quantity', '<=', $filters['low_stock']);
        }
        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function addStock(string $productId, int $quantity, ?string $notes = null): Inventory
    {
        $inventory = Inventory::firstOrCreate(
            ['product_id' => $productId],
            ['total_quantity' => 0, 'available_quantity' => 0, 'reserved_quantity' => 0, 'damaged_quantity' => 0]
        );
        $inventory->increment('total_quantity', $quantity);
        $inventory->increment('available_quantity', $quantity);

        InventoryHistory::create([
            'inventory_id' => $inventory->id,
            'type' => 'INBOUND',
            'quantity' => $quantity,
            'notes' => $notes ?? 'Stock added',
        ]);

        return $inventory->fresh();
    }

    public function reduceStock(string $productId, int $quantity, ?string $notes = null): Inventory
    {
        $inventory = Inventory::where('product_id', $productId)->firstOrFail();
        $inventory->decrement('available_quantity', $quantity);

        InventoryHistory::create([
            'inventory_id' => $inventory->id,
            'type' => 'OUTBOUND',
            'quantity' => $quantity,
            'notes' => $notes ?? 'Stock reduced',
        ]);

        return $inventory->fresh();
    }

    public function getMovementHistory(string $productId): Collection
    {
        $inventory = Inventory::where('product_id', $productId)->first();
        if (!$inventory) return collect();
        return InventoryHistory::where('inventory_id', $inventory->id)
            ->latest()
            ->take(50)
            ->get();
    }

    public function getLowStockItems(int $threshold = 5): Collection
    {
        return Inventory::with('product')
            ->where('available_quantity', '<=', $threshold)
            ->where('available_quantity', '>', 0)
            ->get();
    }

    public function getOutOfStockItems(): Collection
    {
        return Inventory::with('product')
            ->where('available_quantity', '<=', 0)
            ->get();
    }
}
