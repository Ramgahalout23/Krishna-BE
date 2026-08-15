<?php

namespace App\Services;

use App\Repositories\AbandonedCartRepository;
use App\Exceptions\AppError;
use App\Models\AbandonedCart;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AbandonedCartService
{
    public function __construct(
        protected AbandonedCartRepository $abandonedCartRepository,
        protected EmailService $emailService,
        protected NotificationService $notificationService,
        protected NotificationTemplateService $notificationTemplateService,
        protected WebhookService $webhookService
    ) {}

    public function getUserCarts(string $userId): array
    {
        return $this->abandonedCartRepository->getUserAbandonedCarts($userId)->toArray();
    }

    public function getAll(array $filters = []): array
    {
        return $this->abandonedCartRepository->paginate($filters['per_page'] ?? 15)->toArray();
    }

    public function getById(string $id, ?string $userId = null): array
    {
        $cart = $this->abandonedCartRepository->findById($id);
        if (!$cart) throw AppError::notFound('Abandoned cart not found');
        if ($userId !== null && $cart->user_id !== $userId) {
            throw AppError::notFound('Abandoned cart not found');
        }
        return $cart->toArray();
    }

    public function create(string $userId, array $data): array
    {
        $cart = AbandonedCart::create([
            'user_id' => $userId,
            'cart_data' => $data['cart_data'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'last_active_at' => now(),
            'reminder_sent' => false,
            'recovered' => false,
        ]);
        return $cart->toArray();
    }

    public function delete(string $id): void
    {
        $cart = $this->abandonedCartRepository->findById($id);
        if (!$cart) throw AppError::notFound('Abandoned cart not found');
        $this->abandonedCartRepository->delete($id);
    }

    public function getStats(): array
    {
        return $this->abandonedCartRepository->getRecoveryStats();
    }

    /**
     * Send a reminder for an abandoned cart (admin-triggered).
     * Sends email + database notification + webhook event.
     */
    public function sendReminder(string $id): array
    {
        $cart = $this->abandonedCartRepository->findById($id);
        if (!$cart) throw AppError::notFound('Abandoned cart not found');

        $user = $cart->user;
        if (!$user) throw AppError::notFound('User not found for this cart');

        $cartData = is_string($cart->cart_data) ? json_decode($cart->cart_data, true) : $cart->cart_data ?? [];
        $itemCount = is_array($cartData) ? count($cartData) : 0;
        $frontendUrl = config('app.frontend_url', url('/'));
        $recoveryLink = rtrim($frontendUrl, '/') . "/cart?recover={$cart->id}";

        // ── Send email ──
        try {
            $emailEnabled = $this->emailService->isEmailEnabled();
            if ($emailEnabled) {
                $this->emailService->sendAbandonedCartEmail(
                    $user->email,
                    $user->first_name . ' ' . $user->last_name,
                    "{$itemCount} item(s)",
                    '$--.--',
                    $recoveryLink
                );
            }
        } catch (\Exception $e) {
            Log::error("[AbandonedCart] Email send failed for cart {$cart->id}: {$e->getMessage()}");
        }

        // ── Database notification (from template) ──
        try {
            $notifRendered = $this->notificationTemplateService->renderTemplate('notif_abandoned_cart', [
                'customerName' => $user->first_name . ' ' . $user->last_name,
                'itemCount' => (string) $itemCount,
                'recoveryLink' => $recoveryLink,
            ]);
            $notifTitle = ($notifRendered['rendered'] ?? false) ? $notifRendered['title'] : 'You left something behind! 🛒';
            $notifMessage = ($notifRendered['rendered'] ?? false) ? $notifRendered['message'] : "You have {$itemCount} item(s) waiting in your cart. Complete your purchase now!";

            $this->notificationService->create(
                $user->id,
                'CART',
                $notifTitle,
                $notifMessage,
                [
                    'cartId' => $cart->id,
                    'recoveryLink' => $recoveryLink,
                    'itemCount' => $itemCount,
                ]
            );
        } catch (\Exception $e) {
            Log::error("[AbandonedCart] Notification failed for cart {$cart->id}: {$e->getMessage()}");
        }

        // ── Webhook event ──
        try {
            $this->webhookService->dispatch('cart.abandoned', [
                'cart_id' => $cart->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->first_name . ' ' . $user->last_name,
                'item_count' => $itemCount,
                'recovery_link' => $recoveryLink,
            ]);
        } catch (\Exception $e) {
            Log::error("[AbandonedCart] Webhook dispatch failed for cart {$cart->id}: {$e->getMessage()}");
        }

        // Mark as reminded
        $this->abandonedCartRepository->markReminded($id);

        return ['message' => 'Reminder sent successfully'];
    }
}
