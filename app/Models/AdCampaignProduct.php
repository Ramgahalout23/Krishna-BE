<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdCampaignProduct extends Model
{
    use HasUuids;
    protected $fillable = ['ad_campaign_id', 'product_id', 'ad_copy', 'ad_headline', 'ad_description', 'call_to_action', 'discount_offered', 'sort_order'];
    protected $casts = ['discount_offered' => 'decimal:10'];
    public function campaign(): BelongsTo { return $this->belongsTo(AdCampaign::class, 'ad_campaign_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
