<?php

namespace App\Jobs;

use App\Models\AbandonedCart;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CuratedLook;
use App\Models\ExportJob;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Reel;
use App\Models\Review;
use App\Models\Shipping;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateExportJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 600;

    public function __construct(
        public string $exportJobId
    ) {}

    public function handle(): void
    {
        $exportJob = ExportJob::find($this->exportJobId);
        if (!$exportJob) return;

        $exportJob->update(['status' => 'processing']);

        try {
            $csv = match ($exportJob->type) {
                'products'       => $this->generateProductsCsv($exportJob),
                'orders'         => $this->generateOrdersCsv($exportJob),
                'users'          => $this->generateUsersCsv($exportJob),
                'coupons'        => $this->generateCouponsCsv($exportJob),
                'banners'        => $this->generateBannersCsv($exportJob),
                'brands'         => $this->generateBrandsCsv($exportJob),
                'categories'     => $this->generateCategoriesCsv($exportJob),
                'reviews'        => $this->generateReviewsCsv($exportJob),
                'pages'          => $this->generatePagesCsv($exportJob),
                'promotions'     => $this->generatePromotionsCsv($exportJob),
                'shipments'      => $this->generateShipmentsCsv($exportJob),
                'tickets'        => $this->generateTicketsCsv($exportJob),
                'abandoned-carts'=> $this->generateAbandonedCartsCsv($exportJob),
                'notifications'  => $this->generateNotificationsCsv($exportJob),
                'curated-looks'  => $this->generateCuratedLooksCsv($exportJob),
                'reels'          => $this->generateReelsCsv($exportJob),
                'variants'       => $this->generateVariantsCsv($exportJob),
                'inventory'      => $this->generateInventoryCsv($exportJob),
                default          => throw new \InvalidArgumentException("Unknown export type: {$exportJob->type}"),
            };

            $fileName = $exportJob->type . '-export-' . now()->format('Y-m-d-His') . '.csv';
            Storage::disk('local')->put('exports/' . $fileName, $csv);

            $exportJob->update([
                'status'       => 'completed',
                'file_path'    => 'exports/' . $fileName,
                'file_name'    => $fileName,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $exportJob->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
        }
    }

    private function generateProductsCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        $q = Product::with(['category:id,name'])
            ->select(['id', 'name', 'sku', 'price', 'old_price', 'cost', 'quantity', 'status', 'rating', 'badge', 'description', 'short_description', 'created_at']);

        if (!empty($filters['status'])) $q->where('status', $filters['status']);
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $q->where(fn($q) => $q->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%"));
        }

        $items = $q->latest()->get();

        $keys = ['name', 'sku', 'category', 'price', 'oldPrice', 'cost', 'stock', 'status', 'rating', 'badge', 'description', 'shortDescription', 'createdAt'];
        $headers = ['Product Name', 'SKU', 'Category', 'Price', 'Old Price', 'Cost', 'Stock', 'Status', 'Rating', 'Badge', 'Description', 'Short Description', 'Created Date'];

        $rows = $items->map(fn($p) => [
            $p->name, $p->sku ?? '', $p->category->name ?? '',
            (string)($p->price ?? ''), (string)($p->old_price ?? ''), (string)($p->cost ?? ''),
            (string)($p->quantity ?? 0), $p->status ?? '', (string)($p->rating ?? ''),
            $p->badge ?? '', strip_tags((string)($p->description ?? '')), $p->short_description ?? '',
            $p->created_at?->format('Y-m-d H:i:s') ?? '',
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    private function generateOrdersCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        $q = Order::with(['user' => fn($q) => $q->select(['id', 'first_name', 'last_name', 'email'])])
            ->select(['id', 'order_number', 'user_id', 'total', 'status', 'payment_method', 'created_at']);

        if (!empty($filters['status'])) $q->where('status', $filters['status']);
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) $q->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);

        $items = $q->latest()->get();

        $keys = ['orderNumber', 'customerName', 'email', 'total', 'status', 'paymentMethod', 'createdAt'];
        $headers = ['Order Number', 'Customer', 'Email', 'Total', 'Status', 'Payment Method', 'Date'];

        $rows = $items->map(fn($o) => [
            $o->order_number,
            ($o->user->first_name ?? '') . ' ' . ($o->user->last_name ?? ''),
            $o->user->email ?? '',
            (string)$o->total, $o->status, $o->payment_method ?? '',
            $o->created_at->format('Y-m-d H:i:s'),
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    private function generateUsersCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        $q = User::select(['id', 'first_name', 'last_name', 'email', 'role', 'phone_number', 'is_email_verified', 'is_active', 'is_blocked', 'created_at']);

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $q->where(fn($q) => $q->where('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }
        if (!empty($filters['role'])) $q->where('role', $filters['role']);

        $items = $q->latest()->get();

        $keys = ['id', 'firstName', 'lastName', 'email', 'role', 'phone', 'emailVerified', 'blocked', 'createdAt'];
        $headers = ['ID', 'First Name', 'Last Name', 'Email', 'Role', 'Phone', 'Email Verified', 'Blocked', 'Created At'];

        $rows = $items->map(fn($u) => [
            $u->id, $u->first_name ?? '', $u->last_name ?? '', $u->email ?? '', $u->role ?? '',
            $u->phone_number ?? '', $u->is_email_verified ? 'Yes' : 'No',
            $u->is_blocked ? 'Yes' : 'No',
            $u->created_at?->format('Y-m-d H:i:s') ?? '',
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    private function generateCouponsCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        $q = Coupon::select(['id', 'code', 'discount_type', 'discount_value', 'min_order_value', 'max_discount', 'usage_limit', 'usage_count', 'is_active', 'start_date', 'expiry_date', 'is_auto_apply', 'is_stackable', 'is_new_user_only', 'description', 'created_at']);

        if (!empty($filters['search'])) $q->where('code', 'like', "%{$filters['search']}%");
        if (isset($filters['isActive'])) $q->where('is_active', $filters['isActive']);

        $items = $q->latest()->get();

        $keys = ['code', 'discountType', 'discountValue', 'minOrderValue', 'maxDiscount', 'usageLimit', 'usageCount', 'isActive', 'startDate', 'expiryDate', 'isAutoApply', 'isStackable', 'isNewUserOnly', 'description', 'createdAt'];
        $headers = ['Code', 'Discount Type', 'Discount Value', 'Min Order Value', 'Max Discount', 'Usage Limit', 'Usage Count', 'Active', 'Start Date', 'Expiry Date', 'Auto Apply', 'Stackable', 'New User Only', 'Description', 'Created Date'];

        $rows = $items->map(fn($c) => [
            $c->code, $c->discount_type ?? '', (string)($c->discount_value ?? ''), (string)($c->min_order_value ?? ''),
            (string)($c->max_discount ?? ''), (string)($c->usage_limit ?? ''), (string)($c->usage_count ?? '0'),
            $c->is_active ? 'Yes' : 'No', $c->start_date?->format('Y-m-d H:i:s') ?? '', $c->expiry_date?->format('Y-m-d H:i:s') ?? '',
            $c->is_auto_apply ? 'Yes' : 'No', $c->is_stackable ? 'Yes' : 'No', $c->is_new_user_only ? 'Yes' : 'No',
            $c->description ?? '', $c->created_at?->format('Y-m-d H:i:s') ?? '',
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    private function generateBannersCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        $q = Banner::select(['id', 'title', 'subtitle', 'description', 'type', 'position', 'is_active', 'display_mode', 'link_url', 'start_date', 'end_date', 'show_on_mobile', 'show_on_desktop', 'background_color', 'text_color', 'created_at']);

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $q->where(fn($q) => $q->where('title', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%"));
        }
        if (!empty($filters['type'])) $q->where('type', $filters['type']);

        $items = $q->latest()->get();

        $keys = ['title', 'subtitle', 'description', 'type', 'position', 'isActive', 'displayMode', 'linkUrl', 'startDate', 'endDate', 'showOnMobile', 'showOnDesktop', 'backgroundColor', 'textColor', 'createdAt'];
        $headers = ['Title', 'Subtitle', 'Description', 'Type', 'Position', 'Active', 'Display Mode', 'Link URL', 'Start Date', 'End Date', 'Show on Mobile', 'Show on Desktop', 'Background Color', 'Text Color', 'Created Date'];

        $rows = $items->map(fn($b) => [
            $b->title ?? '', $b->subtitle ?? '', $b->description ?? '', $b->type ?? '', (string)($b->position ?? '0'),
            $b->is_active ? 'Yes' : 'No', $b->display_mode ?? 'DEFAULT', $b->link_url ?? '',
            $b->start_date?->format('Y-m-d H:i:s') ?? '', $b->end_date?->format('Y-m-d H:i:s') ?? '',
            $b->show_on_mobile ? 'Yes' : 'No', $b->show_on_desktop ? 'Yes' : 'No',
            $b->background_color ?? '', $b->text_color ?? '', $b->created_at?->format('Y-m-d H:i:s') ?? '',
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    // ── NEW GENERATORS ──

    private function generateBrandsCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        $q = Brand::select(['id', 'name', 'slug', 'description', 'logo', 'created_at']);
        if (!empty($filters['search'])) $q->where('name', 'like', "%{$filters['search']}%");

        $items = $q->latest()->get();
        $keys = ['name', 'slug', 'description', 'isActive', 'createdAt'];
        $headers = ['Brand Name', 'Slug', 'Description', 'Active', 'Created Date'];
        $rows = $items->map(fn($b) => [
            $b->name, $b->slug ?? '', $b->description ?? '',
            $b->active !== false ? 'Yes' : 'No',
            $b->created_at?->format('Y-m-d H:i:s') ?? '',
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    private function generateCategoriesCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        $q = Category::select(['id', 'name', 'slug', 'description', 'image', 'parent_id', 'is_active', 'created_at']);
        if (!empty($filters['search'])) $q->where('name', 'like', "%{$filters['search']}%");
        if (isset($filters['isActive'])) $q->where('is_active', $filters['isActive']);

        $items = $q->with('parent:id,name')->latest()->get();
        $keys = ['name', 'slug', 'description', 'parent', 'isActive', 'createdAt'];
        $headers = ['Category Name', 'Slug', 'Description', 'Parent Category', 'Active', 'Created Date'];
        $rows = $items->map(fn($c) => [
            $c->name, $c->slug ?? '', $c->description ?? '',
            $c->parent?->name ?? '', $c->is_active !== false ? 'Yes' : 'No',
            $c->created_at?->format('Y-m-d H:i:s') ?? '',
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    private function generateReviewsCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        $q = Review::with(['user:id,first_name,last_name,email', 'product:id,name'])
            ->select(['id', 'product_id', 'user_id', 'rating', 'title', 'comment', 'is_verified', 'is_moderated', 'is_flagged', 'created_at']);

        if (!empty($filters['status'])) {
            match ($filters['status']) {
                'approved' => $q->where('is_moderated', true)->where('is_flagged', false),
                'pending'  => $q->where('is_moderated', false)->where('is_flagged', false),
                'rejected' => $q->where('is_flagged', true),
                default    => null,
            };
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $q->where(fn($q) => $q->where('comment', 'like', "%{$s}%")->orWhere('title', 'like', "%{$s}%"));
        }

        $items = $q->latest()->get();
        $keys = ['customerName', 'customerEmail', 'productName', 'rating', 'title', 'comment', 'isVerified', 'isModerated', 'isFlagged', 'createdAt'];
        $headers = ['Customer', 'Email', 'Product', 'Rating', 'Title', 'Comment', 'Verified Purchase', 'Moderated', 'Flagged', 'Created Date'];
        $rows = $items->map(fn($r) => [
            ($r->user->first_name ?? '') . ' ' . ($r->user->last_name ?? ''),
            $r->user->email ?? '', $r->product->name ?? '', (string)$r->rating,
            $r->title ?? '', $r->comment ?? '',
            $r->is_verified ? 'Yes' : 'No', $r->is_moderated ? 'Yes' : 'No', $r->is_flagged ? 'Yes' : 'No',
            $r->created_at?->format('Y-m-d H:i:s') ?? '',
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    private function generatePagesCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        $q = Page::select(['id', 'title', 'slug', 'content', 'meta_title', 'meta_description', 'is_published', 'created_at', 'updated_at']);
        $items = $q->latest()->get();

        $keys = ['title', 'slug', 'metaTitle', 'metaDescription', 'isPublished', 'createdAt', 'updatedAt'];
        $headers = ['Title', 'Slug', 'Meta Title', 'Meta Description', 'Published', 'Created Date', 'Updated Date'];
        $rows = $items->map(fn($p) => [
            $p->title, $p->slug ?? '', $p->meta_title ?? '', $p->meta_description ?? '',
            $p->is_published ? 'Yes' : 'No',
            $p->created_at?->format('Y-m-d H:i:s') ?? '', $p->updated_at?->format('Y-m-d H:i:s') ?? '',
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    private function generatePromotionsCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        $q = Promotion::select(['id', 'title', 'description', 'type', 'image_url', 'discount', 'status', 'start_date', 'end_date', 'is_active', 'created_at']);
        if (!empty($filters['search'])) $q->where('title', 'like', "%{$filters['search']}%");
        if (!empty($filters['status'])) $q->where('status', $filters['status']);

        $items = $q->latest()->get();
        $keys = ['title', 'description', 'type', 'discount', 'status', 'startDate', 'endDate', 'isActive', 'createdAt'];
        $headers = ['Title', 'Description', 'Type', 'Discount', 'Status', 'Start Date', 'End Date', 'Active', 'Created Date'];
        $rows = $items->map(fn($p) => [
            $p->title ?? '', $p->description ?? '', $p->type ?? '', (string)($p->discount ?? ''),
            $p->status ?? '', $p->start_date?->format('Y-m-d H:i:s') ?? '', $p->end_date?->format('Y-m-d H:i:s') ?? '',
            $p->is_active !== false ? 'Yes' : 'No', $p->created_at?->format('Y-m-d H:i:s') ?? '',
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    private function generateShipmentsCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        $q = Shipping::select(['id', 'order_id', 'carrier', 'tracking_number', 'cost', 'status', 'estimated_delivery', 'actual_delivery', 'notes', 'created_at']);
        if (!empty($filters['status'])) $q->where('status', $filters['status']);
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $q->where(fn($q) => $q->where('tracking_number', 'like', "%{$s}%")->orWhere('order_id', 'like', "%{$s}%"));
        }

        $items = $q->latest()->get();
        $keys = ['orderId', 'carrier', 'trackingNumber', 'cost', 'status', 'estimatedDelivery', 'actualDelivery', 'notes', 'createdAt'];
        $headers = ['Order ID', 'Carrier', 'Tracking Number', 'Cost', 'Status', 'Estimated Delivery', 'Actual Delivery', 'Notes', 'Created Date'];
        $rows = $items->map(fn($s) => [
            $s->order_id, $s->carrier ?? '', $s->tracking_number ?? '', (string)($s->cost ?? ''),
            $s->status ?? '', $s->estimated_delivery?->format('Y-m-d H:i:s') ?? '', $s->actual_delivery?->format('Y-m-d H:i:s') ?? '',
            $s->notes ?? '', $s->created_at?->format('Y-m-d H:i:s') ?? '',
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    private function generateTicketsCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        $q = SupportTicket::with(['user:id,first_name,last_name,email'])
            ->select(['id', 'ticket_number', 'subject', 'description', 'category', 'priority', 'status', 'user_id', 'created_at']);

        if (!empty($filters['status'])) $q->where('status', $filters['status']);
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $q->where(fn($q) => $q->where('subject', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%"));
        }

        $items = $q->latest()->get();
        $keys = ['ticketNumber', 'customerName', 'customerEmail', 'subject', 'description', 'category', 'priority', 'status', 'createdAt'];
        $headers = ['Ticket #', 'Customer', 'Email', 'Subject', 'Description', 'Category', 'Priority', 'Status', 'Created Date'];
        $rows = $items->map(fn($t) => [
            $t->ticket_number ?? $t->id,
            ($t->user->first_name ?? '') . ' ' . ($t->user->last_name ?? ''),
            $t->user->email ?? '', $t->subject ?? '', $t->description ?? '',
            $t->category ?? '', $t->priority ?? '', $t->status ?? '',
            $t->created_at?->format('Y-m-d H:i:s') ?? '',
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    private function generateAbandonedCartsCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        $q = AbandonedCart::with(['user:id,first_name,last_name,email'])
            ->select(['id', 'user_id', 'session_id', 'last_active_at', 'reminder_sent', 'created_at']);

        $items = $q->latest()->get();
        $keys = ['customerName', 'customerEmail', 'sessionId', 'itemsCount', 'lastActiveAt', 'reminderSent', 'createdAt'];
        $headers = ['Customer', 'Email', 'Session ID', 'Items', 'Last Active', 'Reminder Sent', 'Created Date'];
        $rows = $items->map(fn($c) => [
            ($c->user->first_name ?? '') . ' ' . ($c->user->last_name ?? ''),
            $c->user->email ?? '', $c->session_id ?? '',
            (string)(is_array($c->cart_data) ? count($c->cart_data) : 0),
            $c->last_active_at?->format('Y-m-d H:i:s') ?? '',
            $c->reminder_sent ? 'Yes' : 'No',
            $c->created_at?->format('Y-m-d H:i:s') ?? '',
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    private function generateNotificationsCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        $q = UserNotification::with(['user:id,first_name,last_name,email'])
            ->select(['id', 'user_id', 'type', 'title', 'message', 'read_at', 'created_at']);

        $items = $q->latest()->get();
        $keys = ['userName', 'userEmail', 'type', 'title', 'message', 'isRead', 'createdAt'];
        $headers = ['User', 'Email', 'Type', 'Title', 'Message', 'Read', 'Created Date'];
        $rows = $items->map(fn($n) => [
            ($n->user->first_name ?? '') . ' ' . ($n->user->last_name ?? ''),
            $n->user->email ?? '', $n->type ?? '', $n->title ?? '', $n->message ?? '',
            $n->read_at ? 'Yes' : 'No',
            $n->created_at?->format('Y-m-d H:i:s') ?? '',
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    private function generateCuratedLooksCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        $q = CuratedLook::select(['id', 'name', 'slug', 'description', 'display_order', 'is_active', 'created_at']);
        if (!empty($filters['search'])) $q->where('name', 'like', "%{$filters['search']}%");
        if (isset($filters['is_active'])) $q->where('is_active', $filters['is_active']);

        $items = $q->latest()->get();
        $keys = ['name', 'slug', 'description', 'displayOrder', 'isActive', 'createdAt'];
        $headers = ['Name', 'Slug', 'Description', 'Display Order', 'Active', 'Created Date'];
        $rows = $items->map(fn($l) => [
            $l->name, $l->slug ?? '', $l->description ?? '', (string)($l->display_order ?? 0),
            $l->is_active !== false ? 'Yes' : 'No',
            $l->created_at?->format('Y-m-d H:i:s') ?? '',
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    private function generateReelsCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        $q = Reel::select(['id', 'title', 'description', 'video_url', 'image_url', 'link_url', 'display_order', 'is_active', 'created_at']);
        if (!empty($filters['search'])) $q->where('title', 'like', "%{$filters['search']}%");
        if (isset($filters['is_active'])) $q->where('is_active', $filters['is_active']);

        $items = $q->latest()->get();
        $keys = ['title', 'description', 'videoUrl', 'imageUrl', 'linkUrl', 'displayOrder', 'isActive', 'createdAt'];
        $headers = ['Title', 'Description', 'Video URL', 'Image URL', 'Link URL', 'Display Order', 'Active', 'Created Date'];
        $rows = $items->map(fn($r) => [
            $r->title, $r->description ?? '', $r->video_url ?? '', $r->image_url ?? '',
            $r->link_url ?? '', (string)($r->display_order ?? 0),
            $r->is_active !== false ? 'Yes' : 'No',
            $r->created_at?->format('Y-m-d H:i:s') ?? '',
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    private function generateVariantsCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        $q = ProductVariant::with(['product:id,name,sku'])
            ->select(['id', 'product_id', 'name', 'sku', 'attributes', 'price', 'quantity', 'created_at']);

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $q->where(fn($q) => $q->where('sku', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"));
        }
        if (!empty($filters['product_id'])) $q->where('product_id', $filters['product_id']);

        $items = $q->latest()->get();
        $keys = ['productName', 'variantName', 'sku', 'price', 'stock', 'color', 'size', 'createdAt'];
        $headers = ['Product', 'Variant', 'SKU', 'Price', 'Stock', 'Color', 'Size', 'Created Date'];
        $rows = $items->map(fn($v) => [
            $v->product->name ?? '',
            $v->name ?? '',
            $v->sku ?? '',
            (string)($v->price ?? ''),
            (string)($v->quantity ?? 0),
            $v->attributes['color'] ?? '',
            $v->attributes['size'] ?? '',
            $v->created_at?->format('Y-m-d H:i:s') ?? '',
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    private function generateInventoryCsv(ExportJob $exportJob): string
    {
        $filters = $exportJob->filters ?? [];
        $columns = $exportJob->columns ?? [];

        // Inventory uses Product model with effective_stock concept
        $q = Product::with(['variants' => fn($q) => $q->select(['id', 'product_id', 'name', 'sku', 'quantity', 'attributes', 'price'])])
            ->select(['id', 'name', 'sku', 'quantity', 'created_at']);

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $q->where(fn($q) => $q->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%"));
        }

        $items = $q->latest()->get();
        $keys = ['productName', 'sku', 'stock', 'variantCount', 'status', 'createdAt'];
        $headers = ['Product', 'SKU', 'Stock', 'Variants', 'Status', 'Created Date'];
        $rows = $items->map(fn($p) => [
            $p->name, $p->sku ?? '', (string)($p->quantity ?? 0),
            (string)$p->variants->count(),
            ($p->quantity ?? 0) < 5 ? 'Low Stock' : (($p->quantity ?? 0) === 0 ? 'Out of Stock' : 'In Stock'),
            $p->created_at?->format('Y-m-d H:i:s') ?? '',
        ])->toArray();

        return $this->buildCsv($headers, $keys, $rows, $columns);
    }

    private function buildCsv(array $allHeaders, array $allKeys, array $allRows, array $columns): string
    {
        if (!empty($columns)) {
            $desiredIndices = [];
            $filteredHeaders = [];
            foreach ($columns as $key) {
                $idx = array_search($key, $allKeys);
                if ($idx !== false) {
                    $desiredIndices[] = $idx;
                    $filteredHeaders[] = $allHeaders[$idx];
                }
            }
            if (!empty($desiredIndices)) {
                $allHeaders = $filteredHeaders;
                $allRows = array_map(fn($row) => array_map(fn($i) => $row[$i] ?? '', $desiredIndices), $allRows);
            }
        }

        $csv = "\xEF\xBB\xBF";
        $csv .= implode(',', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $allHeaders)) . "\n";
        foreach ($allRows as $row) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', $row)) . "\n";
        }

        return $csv;
    }
}
