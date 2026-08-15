<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    use HasUuids;
    protected $fillable = ['name', 'body', 'header', 'footer', 'buttons', 'language', 'status'];
}
