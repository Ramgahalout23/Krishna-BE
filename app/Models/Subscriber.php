<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscriber extends Model
{
    use HasUuids;
    protected $fillable = ['email', 'name', 'phone', 'status', 'source', 'tags', 'metadata', 'unsubscribed_at'];
    protected $casts = ['unsubscribed_at' => 'datetime'];
    public function campaignRecipients(): HasMany { return $this->hasMany(CampaignRecipient::class); }
}
