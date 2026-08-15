<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    use HasUuids;
    protected $fillable = ['name', 'countries', 'states'];
    public function rates(): HasMany { return $this->hasMany(ShippingRate::class, 'zone_id'); }
}
