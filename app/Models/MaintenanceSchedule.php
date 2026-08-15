<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MaintenanceSchedule extends Model
{
    use HasUuids;
    protected $fillable = [
        'title', 'message', 'starts_at', 'ends_at', 'is_active', 'is_completed',
        'is_recurring', 'recurring_days', 'time_start', 'time_end', 'last_activated_at'
    ];
    protected $casts = [
        'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean',
        'is_completed' => 'boolean', 'is_recurring' => 'boolean', 'last_activated_at' => 'datetime'
    ];
}
