<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartRecommendation extends Model
{
    use HasUuids;
    protected $fillable = ['user_id', 'recommended_products'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
