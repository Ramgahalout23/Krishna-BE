<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEvent extends Model
{
    use HasUuids;
    protected $fillable = ['user_id', 'session_id', 'event_type', 'event_name', 'category', 'label', 'value', 'url', 'metadata', 'ip_address'];
    protected $casts = ['metadata' => 'json'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
