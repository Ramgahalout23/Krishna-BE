<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    use HasUuids;
    protected $table = 'notifications';
    protected $fillable = ['user_id', 'type', 'title', 'message', 'data', 'read_at'];
    protected $casts = ['data' => 'json', 'is_read' => 'boolean'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
