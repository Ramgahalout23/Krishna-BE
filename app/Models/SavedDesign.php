<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedDesign extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'color',
        'size',
        'design_id',
        'accent_color',
        'design_data',
    ];

    protected $casts = [
        'design_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
