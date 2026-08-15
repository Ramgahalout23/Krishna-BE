<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasUuids;
    protected $fillable = ['wallet_id', 'type', 'amount', 'reason', 'reference_id'];
    protected $casts = ['amount' => 'decimal:2'];
    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
}
