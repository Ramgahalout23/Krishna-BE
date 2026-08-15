<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasUuids;
    protected $fillable = ['order_id', 'transaction_id', 'method', 'amount', 'currency', 'status', 'gateway_response', 'metadata'];
    protected $casts = [
        'amount' => 'decimal:2', 'gateway_response' => 'json', 'metadata' => 'json'
    ];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function refunds(): HasMany { return $this->hasMany(Refund::class); }
    public function transactionLogs(): HasMany { return $this->hasMany(TransactionLog::class); }
}
