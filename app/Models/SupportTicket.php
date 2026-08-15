<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasUuids;
    protected $fillable = ['ticket_number', 'subject', 'description', 'category', 'priority', 'status', 'user_id', 'order_id', 'assigned_to'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function messages(): HasMany { return $this->hasMany(TicketMessage::class, 'ticket_id'); }
    public function attachments(): HasMany { return $this->hasMany(TicketAttachment::class, 'ticket_id'); }
}
