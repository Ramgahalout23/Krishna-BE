<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletAdjustment extends Model
{
    use HasUuids;
    protected $fillable = ['wallet_id', 'admin_id', 'amount', 'reason'];
    protected $casts = ['amount' => 'decimal:2'];
    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function admin(): BelongsTo { return $this->belongsTo(User::class, 'admin_id'); }
}
