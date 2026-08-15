<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\User;
use App\Services\EmailService;
use App\Services\NotificationService;
use App\Services\NotificationTemplateService;
use App\Services\SMSService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOrderStatusUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $orderId,
        protected string $newStatus,
        protected ?string $previousStatus = null
    ) {}

    /**
     * Execute the job — sends SMS and email notifications for order status changes.
     */
    public function handle(
        EmailService $emailService,
        SMSService $smsService,
        NotificationTemplateService $notificationTemplateService,
        NotificationService $notificationService
    ): void {
        $order = Order::find($this->orderId);
        if (!$order) {
            Log::warning('[ProcessOrderStatusUpdate] Order not found', ['order_id' => $this->orderId]);
            return;
        }

        $user = User::find($order->user_id);
        if (!$user) {
            Log::warning('[ProcessOrderStatusUpdate] User not found', ['user_id' => $order->user_id]);
            return;
        }

        $newStatus = $this->newStatus;

        // ── Send status update SMS ──
        $this->sendOrderStatusUpdateSMS($order, $user, $newStatus, $smsService, $notificationTemplateService);

        // ── Send status update email ──
        $this->sendOrderStatusUpdateEmail($order, $user, $newStatus, $emailService);

        // ── Create in-app notification for order status change ──
        $this->createInAppNotification($order, $user, $newStatus, $notificationService, $notificationTemplateService);

        Log::info('[ProcessOrderStatusUpdate] Completed status update notifications', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'new_status' => $newStatus,
        ]);
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
     * Create an in-app notification for the order status change.
     */
    private function createInAppNotification(
        Order $order,
        User $user,
        string $newStatus,
        NotificationService $notificationService,
        NotificationTemplateService $notificationTemplateService
    ): void {
        try {
            $statusLabels = [
                'CONFIRMED' => 'Confirmed ✅',
                'PROCESSING' => 'Processing ⏳',
                'SHIPPED' => 'Shipped 🚚',
                'DELIVERED' => 'Delivered ✅',
                'CANCELLED' => 'Cancelled ❌',
                'RETURNED' => 'Returned ↩️',
                'RETURN_REQUESTED' => 'Return Requested 📋',
                'FAILED' => 'Failed ❌',
            ];

            $label = $statusLabels[$newStatus] ?? $newStatus;

            // Try to render from template first
            $templateId = match ($newStatus) {
                'SHIPPED' => 'notif_order_shipped',
                'DELIVERED' => 'notif_order_delivered',
                'CANCELLED' => 'notif_order_cancelled',
                default => null,
            };

            $notifTitle = "Order {$label}";
            $notifMessage = "Order #{$order->order_number} status updated to {$newStatus}.";

            if ($templateId) {
                try {
                    $rendered = $notificationTemplateService->renderTemplate($templateId, [
                        'customerName' => $user->first_name . ' ' . $user->last_name,
                        'orderNumber' => $order->order_number,
                    ]);
                    if ($rendered['rendered'] ?? false) {
                        $notifTitle = $rendered['title'] ?? $notifTitle;
                        $notifMessage = $rendered['message'] ?? $notifMessage;
                    }
                } catch (\Exception $e) {
                    // Fall back to default title/message
                }
            }

            \App\Jobs\SendNotificationJob::dispatch(
                $order->user_id,
                'ORDER',
                $notifTitle,
                $notifMessage,
                ['orderId' => $order->id, 'orderNumber' => $order->order_number]
            );

            Log::info('[ProcessOrderStatusUpdate] Created in-app notification', [
                'order_id' => $order->id,
                'new_status' => $newStatus,
            ]);
        } catch (\Exception $e) {
            Log::error('[ProcessOrderStatusUpdate] Failed to create in-app notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[ProcessOrderStatusUpdate] Permanently failed', [
            'order_id' => $this->orderId,
            'new_status' => $this->newStatus,
            'error' => $exception->getMessage(),
        ]);
    }
}
