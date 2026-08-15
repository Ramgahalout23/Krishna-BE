<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasUuids;
    protected $fillable = [
        'name', 'subject', 'preheader', 'from_name', 'from_email', 'content_html', 'content_text',
        'type', 'status', 'scheduled_at', 'sent_at', 'total_recipients', 'sent_count',
        'opened_count', 'clicked_count', 'bounced_count', 'unsubscribed_count', 'complained_count', 'failed_count', 'created_by'
    ];
    protected $casts = [
        'scheduled_at' => 'datetime', 'sent_at' => 'datetime', 'total_recipients' => 'integer',
        'sent_count' => 'integer', 'opened_count' => 'integer', 'clicked_count' => 'integer',
        'bounced_count' => 'integer', 'unsubscribed_count' => 'integer', 'complained_count' => 'integer', 'failed_count' => 'integer'
    ];
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function recipients(): HasMany { return $this->hasMany(CampaignRecipient::class); }
}
