<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasUuids;
    protected $fillable = ['webhook_id', 'event', 'payload', 'response_status', 'response_body', 'success', 'attempted_at'];
    protected $casts = ['success' => 'boolean', 'attempted_at' => 'datetime'];
}
