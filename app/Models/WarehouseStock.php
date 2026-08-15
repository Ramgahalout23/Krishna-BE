<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStock extends Model
{
    use HasUuids;
    protected $fillable = ['inventory_id', 'warehouse_id', 'quantity'];
    protected $casts = ['quantity' => 'integer'];
    public function inventory(): BelongsTo { return $this->belongsTo(Inventory::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
}
