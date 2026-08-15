<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasUuids;
    protected $fillable = ['title', 'slug', 'content', 'meta_title', 'meta_description', 'is_published'];
    protected $casts = ['is_published' => 'boolean'];
}
