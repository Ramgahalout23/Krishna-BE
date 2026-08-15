<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdSuggestion extends Model
{
    use HasUuids;
    protected $fillable = ['campaign_id', 'platform', 'suggestion_type', 'original_prompt', 'generated_content', 'metadata', 'was_used', 'used_at'];
    protected $casts = ['was_used' => 'boolean', 'used_at' => 'datetime'];
    public function campaign(): BelongsTo { return $this->belongsTo(AdCampaign::class, 'campaign_id'); }
}
