<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportJob extends Model
{
    use HasUuids;

    protected $table = 'export_jobs';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'filters',
        'columns',
        'file_path',
        'file_name',
        'error_message',
        'completed_at',
        'cleaned_up_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'columns' => 'array',
        'completed_at' => 'datetime',
        'cleaned_up_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
