<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CampaignTemplate extends Model
{
    use HasUuids;
    protected $fillable = ['name', 'description', 'category', 'thumbnail', 'content_html', 'variables', 'status', 'is_default', 'created_by'];
    protected $casts = ['is_default' => 'boolean'];
}
