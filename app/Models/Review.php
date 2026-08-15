<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasUuids;
    protected $fillable = [
        'product_id', 'user_id', 'order_id', 'type', 'rating', 'title', 'comment',
        'images', 'is_verified', 'is_moderated', 'is_flagged', 'helpful', 'unhelpful',
        'name', 'email'
    ];
    protected $casts = [
        'images' => 'json', 'is_verified' => 'boolean', 'is_moderated' => 'boolean',
        'is_flagged' => 'boolean', 'helpful' => 'integer', 'unhelpful' => 'integer', 'rating' => 'integer'
    ];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
