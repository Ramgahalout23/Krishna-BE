<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasUuids;
    protected $fillable = ['user_id', 'action', 'entity', 'entity_id', 'old_values', 'new_values', 'ip_address', 'user_agent'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
