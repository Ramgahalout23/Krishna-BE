<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomDesign extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'item_index',
        'order_item_id',
        'user_id',
        'customer_name',
        'customer_email',
        'design_file_path',
        'design_file_url',
        'design_filename',
        'design_mime',
        'design_file_size',
        'color',
        'size',
        'quantity',
        'placement',
        'price',
        'design_notes',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'design_file_size' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Status constants matching the frontend DESIGN_STATUSES.
     */
    const STATUS_PENDING_REVIEW = 'PENDING_REVIEW';
    const STATUS_APPROVED = 'APPROVED';
    const STATUS_IN_PRODUCTION = 'IN_PRODUCTION';
    const STATUS_SHIPPED = 'SHIPPED';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_REJECTED = 'REJECTED';

    /**
     * Valid status transitions.
     */
    public static array $transitions = [
        self::STATUS_PENDING_REVIEW => [self::STATUS_APPROVED, self::STATUS_REJECTED],
        self::STATUS_APPROVED => [self::STATUS_IN_PRODUCTION, self::STATUS_REJECTED],
        self::STATUS_IN_PRODUCTION => [self::STATUS_SHIPPED, self::STATUS_REJECTED],
        self::STATUS_SHIPPED => [self::STATUS_COMPLETED],
        self::STATUS_COMPLETED => [],
        self::STATUS_REJECTED => [],
    ];

    /**
     * Get the order that owns this custom design.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who submitted this design.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who last reviewed this design.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Check if the status transition is valid.
     */
    public static function isValidTransition(string $from, string $to): bool
    {
        return in_array($to, self::$transitions[$from] ?? []);
    }
}
