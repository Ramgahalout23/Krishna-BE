<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RobotsTxt extends Model
{
    use HasUuids;
    protected $table = 'robots_txt';
    protected $fillable = ['content'];
}
