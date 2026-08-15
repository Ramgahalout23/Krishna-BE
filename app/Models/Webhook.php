<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    use HasUuids;
    protected $fillable = ['name', 'url', 'events', 'secret', 'is_active'];
    protected $casts = ['events' => 'array', 'is_active' => 'boolean'];

    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }
}
