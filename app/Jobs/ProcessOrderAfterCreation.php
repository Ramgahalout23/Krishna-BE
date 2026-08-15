<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\EmailService;
use App\Services\NotificationService;
use App\Services\NotificationTemplateService;
use App\Services\SMSService;
use App\Services\SocketService;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOrderAfterCreation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $orderId,
        protected string $userId,
        protected array $items,
        protected float $total,
        protected ?string $paymentMethod
    ) {}

    /**
     * Execute the job — runs all post-order processing asynchronously.
     */
    public function handle(
        EmailService $emailService,
        SMSService $smsService,
        NotificationService $notificationService,
        NotificationTemplateService $notificationTemplateService,
        SocketService $socketService,
        WebhookService $webhookService
    ): void {
        $order = Order::find($this->orderId);
        if (!$order) {
            Log::warning('[ProcessOrderAfterCreation] Order not found', ['order_id' => $this->orderId]);
            return;
        }

        $user = User::find($this->userId);
        if (!$user) {
            Log::warning('[ProcessOrderAfterCreation] User not found', ['user_id' => $this->userId]);
            return;
        }

        $items = $this->items;
        $total = $this->total;
        $paymentMethod = $this->paymentMethod;

        // ── Dispatch webhook: order.created ──
        DispatchWebhookJob::dispatch('order.created', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'user_id' => $this->userId,
            'total' => (float) $total,
            'status' => $order->status,
            'items_count' => count($items),
        ]);

        // ── Send order confirmation email ──
        $this->sendOrderConfirmationEmail($order, $user, $items, $total, $emailService, $notificationService, $notificationTemplateService);

        // ── Send order confirmation SMS ──
        $this->sendOrderConfirmationSMS($order, $user, $total, $smsService, $notificationTemplateService);

        // ── If COD auto-confirmed, send status update notifications ──
        if ($paymentMethod === 'COD') {
            $this->sendOrderStatusUpdateSMS($order, $user, 'CONFIRMED', $smsService, $notificationTemplateService);
            $this->sendOrderStatusUpdateEmail($order, $user, 'CONFIRMED', $emailService);
        }

        // ── Emit real-time socket event for admin badge updates ──
        EmitSocketEventJob::dispatch('order:created', [
            'orderId' => $order->id,
            'orderNumber' => $order->order_number,
            'status' => $order->status,
            'userId' => $this->userId,
            'timestamp' => now()->toIso8601String(),
            'summary' => ['total' => (float) $total],
        ]);

        // ── Fire the local Laravel event for internal listeners ──
        try {
            event(new \App\Events\OrderCreated(['orderId' => $order->id, 'orderNumber' => $order->order_number]));
        } catch (\Exception $e) {
            Log::error('Failed to fire OrderCreated event', ['error' => $e->getMessage()]);
        }

        Log::info('[ProcessOrderAfterCreation] Completed post-order processing', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);
    }

    /**
     * Send order confirmation email.
     */
    private function sendOrderConfirmationEmail(
        Order $order,
        User $user,
        array $items,
        float $total,
        EmailService $emailService,
        NotificationService $notificationService,
        NotificationTemplateService $notificationTemplateService
    ): void {
        try {
            if (!$emailService->isEmailEnabled()) return;
            if (!$emailService->isTemplateActive('orderConfirmation')) return;

            // Get product names
            $productIds = array_column($items, 'product_id');
            $products = Product::whereIn('id', $productIds)->pluck('name', 'id')->toArray();

            $formattedItems = array_map(function ($item) use ($products) {
                return [
                    'name' => $products[$item['product_id']] ?? 'Product',
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => ($item['price'] ?? 0) * ($item['quantity'] ?? 1),
                ];
            }, $items);

            $emailService->sendOrderConfirmation(
                $user->email,
                $user->first_name . ' ' . $user->last_name,
                [
                    'orderNumber' => $order->order_number,
                    'customerName' => $user->first_name . ' ' . $user->last_name,
                    'items' => $formattedItems,
                    'subtotal' => $total,
                    'shippingCost' => 0,
                    'tax' => 0,
                    'discount' => 0,
                    'total' => $total,
                    'shippingAddress' => 'N/A',
                    'paymentMethod' => 'N/A',
                ]
            );

            // Create in-app notification from template
            $notifRendered = $notificationTemplateService->renderTemplate('notif_order_confirmed', [
                'customerName' => $user->first_name . ' ' . $user->last_name,
                'orderNumber' => $order->order_number,
                'total' => number_format($total, 2),
            ]);
            $notifTitle = ($notifRendered['rendered'] ?? false) ? $notifRendered['title'] : 'Order Confirmed 🎉';
            $notifMessage = ($notifRendered['rendered'] ?? false) ? $notifRendered['message'] : "Your order {$order->order_number} has been placed successfully. Total: $" . number_format($total, 2);

            SendNotificationJob::dispatch(
                $order->user_id,
                'ORDER',
                $notifTitle,
                $notifMessage,
                ['orderId' => $order->id, 'orderNumber' => $order->order_number]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation email', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send order confirmation SMS.
     */
    private function sendOrderConfirmationSMS(
        Order $order,
        User $user,
        float $total,
        SMSService $smsService,
        NotificationTemplateService $notificationTemplateService
    ): void {
        try {
            if (!$smsService->isSmsEnabled()) return;
            if (!$user->phone_number) return;
            if (!$notificationTemplateService->isTemplateActive('sms_order_confirmation')) return;

            $smsService->sendOrderConfirmationSMS(
                $user->phone_number,
                $user->first_name . ' ' . $user->last_name,
                $order->order_number,
                $total
            );
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation SMS', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send order status update SMS.
     */
    private function sendOrderStatusUpdateSMS(
        Order $order,
        User $user,
        string $newStatus,
        SMSService $smsService,
        NotificationTemplateService $notificationTemplateService
    ): void {
        try {
            if (!$smsService->isSmsEnabled()) return;
            if (!$user->phone_number) return;

            $templateId = match ($newStatus) {
                'SHIPPED' => 'sms_order_shipped',
                'DELIVERED' => 'sms_order_delivered',
                'CANCELLED' => 'sms_order_cancelled',
                default => 'sms_order_status_update',
            };
            if (!$notificationTemplateService->isTemplateActive($templateId)) return;

            $smsService->sendOrderStatusUpdateSMS(
                $user->phone_number,
                $user->first_name . ' ' . $user->last_name,
                $order->order_number,
                $newStatus
            );
        } catch (\Exception $e) {
            Log::error('Failed to send order status update SMS', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send order status update email.
     */
    private function sendOrderStatusUpdateEmail(
        Order $order,
        User $user,
        string $newStatus,
        EmailService $emailService
    ): void {
        try {
            if (!$emailService->isEmailEnabled()) return;
            if (!$emailService->isTemplateActive('orderStatusUpdate')) return;

            $emailService->sendOrderStatusUpdate(
                $user->email,
                $user->first_name . ' ' . $user->last_name,
                $order->order_number,
                $newStatus
            );
        } catch (\Exception $e) {
            Log::error('Failed to send order status update email', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[ProcessOrderAfterCreation] Permanently failed', [
            'order_id' => $this->orderId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
