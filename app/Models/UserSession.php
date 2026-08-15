<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    use HasUuids;
    protected $fillable = [
        'user_id', 'session_id', 'ip_address', 'user_agent', 'device', 'browser', 'os',
        'location', 'referrer', 'source', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'landing_page', 'start_time', 'end_time', 'duration', 'page_views', 'is_active'
    ];
    protected $casts = ['start_time' => 'datetime', 'end_time' => 'datetime', 'is_active' => 'boolean'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
