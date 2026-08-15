<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AdminAnalytics extends Model
{
    use HasUuids;
    protected $fillable = ['type', 'data'];
}
