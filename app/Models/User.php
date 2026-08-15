<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'password', 'phone_number', 'avatar',
        'role', 'custom_permissions', 'is_email_verified', 'email_verification_token',
        'email_verification_expiry', 'is_phone_verified', 'phone_verification_token',
        'phone_verification_expiry', 'password_reset_token', 'password_reset_expiry',
        'otp_code', 'otp_expiry', 'otp_attempts', 'is_active', 'is_blocked',
        'last_login_at', 'vip_tier_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'email_verification_expiry' => 'datetime',
        'phone_verification_expiry' => 'datetime',
        'otp_expiry' => 'datetime',
        'password_reset_expiry' => 'datetime',
        'custom_permissions' => 'json',
        'is_email_verified' => 'boolean',
        'is_phone_verified' => 'boolean',
        'is_active' => 'boolean',
        'is_blocked' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function addresses(): HasMany { return $this->hasMany(Address::class); }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
    public function cartItems(): HasMany { return $this->hasMany(CartItem::class); }
    public function wishlistItems(): HasMany { return $this->hasMany(WishlistItem::class); }
    public function reviews(): HasMany { return $this->hasMany(Review::class); }
    public function notifications(): HasMany { return $this->hasMany(UserNotification::class); }
    public function wallet(): HasOne { return $this->hasOne(Wallet::class); }
    public function loyaltyPoints(): HasOne { return $this->hasOne(LoyaltyPoint::class); }
    public function vipTier(): BelongsTo { return $this->belongsTo(VipTier::class); }
    public function supportTickets(): HasMany { return $this->hasMany(SupportTicket::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function refunds(): HasMany { return $this->hasMany(Refund::class); }
    public function returnRequests(): HasMany { return $this->hasMany(ReturnRequest::class); }
    public function refundRequests(): HasMany { return $this->hasMany(RefundRequest::class); }
    public function pageViews(): HasMany { return $this->hasMany(PageView::class); }
    public function userSessions(): HasMany { return $this->hasMany(UserSession::class); }
    public function userEvents(): HasMany { return $this->hasMany(UserEvent::class); }
    public function recentlyViewed(): HasMany { return $this->hasMany(RecentlyViewedProduct::class); }
    public function reelLikes(): HasMany { return $this->hasMany(ReelLike::class); }
    public function walletAdjustments(): HasMany { return $this->hasMany(WalletAdjustment::class, 'admin_id'); }
    public function activityLogs(): HasMany { return $this->hasMany(ActivityLog::class); }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['SUPER_ADMIN', 'ADMIN']);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'SUPER_ADMIN';
    }
}
