<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdCampaign extends Model
{
    use HasUuids;
    protected $fillable = [
        'name', 'description', 'platform', 'objective', 'target_audience', 'budget', 'spent',
        'start_date', 'end_date', 'status', 'creative_url', 'creative_type', 'landing_url',
        'impressions', 'clicks', 'conversions', 'reach', 'ctr', 'cpc', 'notes', 'created_by',
        'platform_campaign_id', 'platform_adset_id', 'platform_creative_id', 'platform_ad_id',
        'platform_status', 'platform_url', 'synced_at', 'last_synced_at'
    ];
    protected $casts = [
        'budget' => 'decimal:12', 'spent' => 'decimal:12', 'ctr' => 'decimal:8',
        'cpc' => 'decimal:10', 'start_date' => 'datetime', 'end_date' => 'datetime',
        'synced_at' => 'datetime', 'last_synced_at' => 'datetime'
    ];
    public function products(): HasMany { return $this->hasMany(AdCampaignProduct::class, 'ad_campaign_id'); }
    public function suggestions(): HasMany { return $this->hasMany(AdSuggestion::class, 'campaign_id'); }
}
