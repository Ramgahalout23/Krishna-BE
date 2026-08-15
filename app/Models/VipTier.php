<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VipTier extends Model
{
    use HasUuids;
    protected $fillable = ['name', 'min_points', 'benefits'];
    protected $casts = ['min_points' => 'integer'];
    public function users(): HasMany { return $this->hasMany(User::class, 'vip_tier_id'); }
}
