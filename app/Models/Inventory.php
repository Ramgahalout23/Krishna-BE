<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasUuids;
    protected $fillable = ['product_id', 'total_quantity', 'available_quantity', 'reserved_quantity', 'damaged_quantity'];
    protected $casts = ['total_quantity' => 'integer', 'available_quantity' => 'integer', 'reserved_quantity' => 'integer', 'damaged_quantity' => 'integer'];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function history(): HasMany { return $this->hasMany(InventoryHistory::class); }
    public function warehouseStocks(): HasMany { return $this->hasMany(WarehouseStock::class); }
}
