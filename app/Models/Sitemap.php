<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Sitemap extends Model
{
    use HasUuids;
    protected $table = 'sitemaps';
    protected $fillable = ['url', 'last_modified'];
    protected $casts = ['last_modified' => 'datetime'];
}
