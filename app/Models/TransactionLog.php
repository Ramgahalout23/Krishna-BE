<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionLog extends Model
{
    use HasUuids;
    protected $fillable = ['payment_id', 'action', 'status', 'message', 'metadata'];
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
}
