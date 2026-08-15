<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SeoScoreHistory extends Model
{
    use HasUuids;

    protected $table = 'seo_score_history';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'score',
        'breakdown',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'breakdown' => 'array',
        'score' => 'integer',
    ];

    public function scopeForEntity($query, string $entityType, string $entityId)
    {
        return $query->where('entity_type', $entityType)->where('entity_id', $entityId);
    }

    public function scopeTrend($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'asc');
    }
}
