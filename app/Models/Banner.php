<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Banner extends Model
{
    use HasUuids;
    protected $fillable = [
        'title', 'subtitle', 'tagline', 'description', 'image_url', 'link_url',
        'type', 'position', 'is_active', 'start_date', 'end_date', 'show_on_mobile',
        'show_on_desktop', 'background_color', 'text_color', 'cta', 'align',
        'text_dark', 'display_mode', 'button_text', 'button_link', 'created_by'
    ];
    protected $casts = ['is_active' => 'boolean', 'show_on_mobile' => 'boolean', 'show_on_desktop' => 'boolean', 'text_dark' => 'boolean', 'start_date' => 'datetime', 'end_date' => 'datetime'];

    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
