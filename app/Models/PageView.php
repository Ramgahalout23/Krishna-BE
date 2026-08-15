<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageView extends Model
{
    use HasUuids;
    protected $fillable = ['user_id', 'session_id', 'url', 'title', 'referrer', 'source', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'duration', 'ip_address', 'user_agent', 'device', 'metadata'];
    protected $casts = ['metadata' => 'json'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
