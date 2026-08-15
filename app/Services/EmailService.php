<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use App\Models\Setting;
use App\Traits\CacheKeyRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    use CacheKeyRegistry;

    public function sendEmail(string $to, string $subject, string $html, ?string $text = null): bool
    {
        try {
            SendEmailJob::dispatch($to, $subject, $html, $text);
            Log::info("[EmailService] Dispatched email job for {$to}: {$subject}");
            return true;
        } catch (\Exception $e) {
            Log::error("[EmailService] Failed to dispatch email job for {$to}: {$subject} - {$e->getMessage()}");
            return false;
        }
    }

    public function sendEmailSync(string $to, string $subject, string $html, ?string $text = null): bool
    {
        try {
            $settings = $this->fetchSmtpSettings();
            Mail::send([], [], function ($message) use ($to, $subject, $html, $text, $settings) {
                $message->to($to)->subject($subject)->html($html);
                if ($text) { $message->text($text); }
                $storeName = $this->getStoreName();
                $fromName = $settings['fromName'] ?? config('mail.from.name', $storeName);
                $emailDomain = preg_replace('/[^a-z0-9.-]/', '', strtolower($storeName));
                $fromEmail = $settings['fromEmail'] ?? config('mail.from.address', "noreply@{$emailDomain}.com");
                $message->from($fromEmail, $fromName);
            });
            Log::info("[EmailService] Email sent to {$to}: {$subject}");
            return true;
        } catch (\Exception $e) {
            Log::error("[EmailService] Failed to send email to {$to}: {$subject} - {$e->getMessage()}");
            return false;
        }
    }

    public function isEmailEnabled(): bool
    {
        try {
            return $this->cacheWithTracking('email_enabled', 300, function () {
                $setting = Setting::where('module', 'SITE')->where('key', 'emailEnabled')->first();
                return !($setting && $setting->value === 'false');
            });
        } catch (\Exception $e) { return true; }
    }

    public function checkHealth(): array
    {
        $settings = $this->fetchSmtpSettings();
        if (empty($settings['host']) || empty($settings['username'])) {
            return ['status' => 'not_configured', 'message' => 'SMTP not configured'];
        }
        return ['status' => 'connected', 'message' => "Configured for {$settings['host']}:{$settings['port']}"];
    }

    public function sendOrderConfirmation(string $userEmail, string $userName, array $data): bool
    {
        $subject = "Order Confirmed — {$data['orderNumber']}";
        $html = $this->buildOrderConfirmationHtml(array_merge($data, ['customerName' => $userName]));
        return $this->sendEmail($userEmail, $subject, $html);
    }

    public function sendPasswordResetEmail(string $userEmail, string $userName, string $resetLink, int $expiryHours = 1): bool
    {
        if (!$this->isTemplateActive('passwordReset')) return false;
        return $this->sendEmail($userEmail, 'Reset Your Password', $this->getTemplateHtml('passwordReset', [
            'customerName' => $userName, 'resetLink' => $resetLink, 'expiryHours' => (string) $expiryHours,
        ]));
    }

    public function sendVerificationEmail(string $userEmail, string $userName, string $verificationLink, int $expiryHours = 24): bool
    {
        if (!$this->isTemplateActive('emailVerification')) return false;
        return $this->sendEmail($userEmail, 'Verify Your Email Address', $this->getTemplateHtml('emailVerification', [
            'customerName' => $userName, 'verificationLink' => $verificationLink, 'expiryHours' => (string) $expiryHours,
        ]));
    }

    public function sendWelcomeEmail(string $userEmail, string $userName, ?string $storeName = null): bool
    {
        if (!$this->isTemplateActive('welcomeEmail')) return false;
        $storeName = $storeName ?: $this->getStoreName();
        return $this->sendEmail($userEmail, "Welcome to {$storeName}! 🎉", $this->getTemplateHtml('welcomeEmail', [
            'customerName' => $userName, 'storeName' => $storeName,
        ]));
    }

    public function sendAbandonedCartEmail(string $userEmail, string $userName, string $items, string $cartTotal, string $recoveryLink): bool
    {
        if (!$this->isTemplateActive('abandonedCart')) return false;
        return $this->sendEmail($userEmail, 'You Left Something Behind! 🛒', $this->getTemplateHtml('abandonedCart', [
            'customerName' => $userName, 'items' => $items, 'cartTotal' => $cartTotal, 'recoveryLink' => $recoveryLink,
        ]));
    }

    public function sendOrderStatusUpdate(string $userEmail, string $userName, string $orderNumber, string $newStatus): bool
    {
        if (!$this->isTemplateActive('orderStatusUpdate')) return false;
        $statusLabels = ['PENDING' => 'Pending', 'CONFIRMED' => 'Confirmed', 'PROCESSING' => 'Processing', 'SHIPPED' => 'Shipped', 'DELIVERED' => 'Delivered', 'CANCELLED' => 'Cancelled', 'RETURNED' => 'Returned', 'RETURN_REQUESTED' => 'Return Requested'];
        $label = $statusLabels[$newStatus] ?? $newStatus;
        return $this->sendEmail($userEmail, "Order {$label} — {$orderNumber}", $this->getTemplateHtml('orderStatusUpdate', [
            'customerName' => $userName, 'orderNumber' => $orderNumber, 'newStatus' => $newStatus,
        ]));
    }

    public function sendReturnApproved(string $userEmail, string $userName, string $requestId, string $resolution, ?string $adminResponse = null): bool
    {
        if (!$this->isTemplateActive('returnApproved')) return false;
        $resolutionLabels = ['exchange' => 'Exchange', 'replacement' => 'Replacement', 'refund' => 'Refund'];
        $label = $resolutionLabels[$resolution] ?? $resolution;
        return $this->sendEmail($userEmail, "Return Request #{$requestId} Approved", $this->getTemplateHtml('returnApproved', [
            'customerName' => $userName, 'requestId' => $requestId, 'resolution' => $label, 'adminResponse' => $adminResponse ?? '',
        ]));
    }

    public function sendReturnRejected(string $userEmail, string $userName, string $requestId, ?string $adminResponse = null): bool
    {
        if (!$this->isTemplateActive('returnRejected')) return false;
        return $this->sendEmail($userEmail, "Return Request #{$requestId} Update", $this->getTemplateHtml('returnRejected', [
            'customerName' => $userName, 'requestId' => $requestId, 'adminResponse' => $adminResponse ?? '',
        ]));
    }

    public function sendReturnCompleted(string $userEmail, string $userName, string $requestId, string $refundAmount): bool
    {
        if (!$this->isTemplateActive('returnCompleted')) return false;
        return $this->sendEmail($userEmail, "Return Complete — Refund Processed for #{$requestId}", $this->getTemplateHtml('returnCompleted', [
            'customerName' => $userName, 'requestId' => $requestId, 'refundAmount' => $refundAmount,
        ]));
    }

    public function isTemplateActive(string $templateId): bool
    {
        try {
            if (!$this->isEmailEnabled()) return false;
            return $this->cacheWithTracking("email_template_active_{$templateId}", 300, function () use ($templateId) {
                $setting = Setting::where('module', 'SMTP')->where('key', "template.{$templateId}.active")->first();
                return $setting?->value !== 'false';
            });
        } catch (\Exception $e) { return true; }
    }

    public function getTemplateHtml(string $templateId, array $data = []): string
    {
        $modeSetting = Setting::where('module', 'SMTP')->where('key', "template.{$templateId}.mode")->first();
        $mode = $modeSetting?->value ?? 'default';
        if ($mode === 'custom') {
            $customHtml = Setting::where('module', 'SMTP')->where('key', "template.{$templateId}.customHtml")->first();
            if ($customHtml?->value) {
                $html = $customHtml->value;
                foreach ($data as $key => $value) {
                    $html = str_replace("{{{$key}}}", (string) $value, $html);
                }
                return $html;
            }
        }
        return $this->buildBuiltInTemplate($templateId, $data);
    }

    public function generatePreviewHtml(): string
    {
        $customHtml = Setting::where('module', 'SMTP')->where('key', 'template.orderConfirmation.customHtml')->first();
        if ($customHtml?->value) return $customHtml->value;
        return $this->buildOrderConfirmationHtml([
            'orderNumber' => 'ORD-PREVIEW-001', 'customerName' => 'John Doe',
            'items' => [['name' => 'Classic White T-Shirt', 'quantity' => 2, 'price' => 29.99, 'total' => 59.98], ['name' => 'Premium Hoodie', 'quantity' => 1, 'price' => 79.99, 'total' => 79.99]],
            'subtotal' => 139.97, 'shippingCost' => 9.99, 'tax' => 14.00, 'discount' => 10.00, 'total' => 153.96,
            'shippingAddress' => '123 Main St, New York, NY 10001', 'paymentMethod' => 'Credit Card', 'estimatedDelivery' => '3-5 business days',
        ]);
    }

    public function generateTemplatePreview(string $templateId): string
    {
        $sampleData = [
            'orderConfirmation' => ['orderNumber' => 'ORD-PREVIEW-001', 'customerName' => 'John Doe', 'items' => [['name' => 'Classic White T-Shirt', 'quantity' => 2, 'price' => 29.99, 'total' => 59.98]], 'subtotal' => 59.98, 'shippingCost' => 5.00, 'tax' => 6.00, 'discount' => 0, 'total' => 70.98, 'shippingAddress' => '123 Main St, NY 10001', 'paymentMethod' => 'Credit Card', 'estimatedDelivery' => '3-5 days'],
            'orderStatusUpdate' => ['customerName' => 'John Doe', 'orderNumber' => 'ORD-001', 'newStatus' => 'SHIPPED'],
            'passwordReset' => ['customerName' => 'John Doe', 'resetLink' => '#', 'expiryHours' => '1'],
            'emailVerification' => ['customerName' => 'John Doe', 'verificationLink' => '#', 'expiryHours' => '24'],
            'welcomeEmail' => ['customerName' => 'John Doe', 'storeName' => $this->getStoreName()],
            'abandonedCart' => ['customerName' => 'John Doe', 'items' => '2 items', 'cartTotal' => '$139.97', 'recoveryLink' => '#'],
            'returnApproved' => ['customerName' => 'John Doe', 'requestId' => 'REQ-001', 'resolution' => 'Exchange', 'adminResponse' => 'We can do an exchange for the correct size.'],
            'returnRejected' => ['customerName' => 'John Doe', 'requestId' => 'REQ-001', 'adminResponse' => 'Unable to process as the item has been worn.'],
            'returnCompleted' => ['customerName' => 'John Doe', 'requestId' => 'REQ-001', 'refundAmount' => '$49.99'],
        ];
        return $this->getTemplateHtml($templateId, $sampleData[$templateId] ?? []);
    }

    private function fetchSmtpSettings(): array
    {
        return $this->cacheWithTracking('smtp_settings', 300, function () {
            try {
                $settings = Setting::where('module', 'SMTP')->pluck('value', 'key')->toArray();
                return [
                    'host' => $settings['smtpHost'] ?? config('mail.mailers.smtp.host', 'smtp.gmail.com'),
                    'port' => $settings['smtpPort'] ?? config('mail.mailers.smtp.port', '587'),
                    'username' => $settings['smtpUsername'] ?? config('mail.mailers.smtp.username', ''),
                    'password' => $settings['smtpPassword'] ?? config('mail.mailers.smtp.password', ''),
                    'fromEmail' => $settings['fromEmailAddress'] ?? config('mail.from.address', 'noreply@example.com'),
                    'fromName' => $settings['fromName'] ?? config('mail.from.name', $this->getStoreName()),
                ];
            } catch (\Exception $e) {
                Log::warning('Failed to fetch SMTP settings from DB, using config fallback');
                $storeName = $this->getStoreName();
                $emailDomain = preg_replace('/[^a-z0-9.-]/', '', strtolower($storeName));
                return [
                    'host' => config('mail.mailers.smtp.host', 'smtp.gmail.com'),
                    'port' => config('mail.mailers.smtp.port', '587'),
                    'username' => config('mail.mailers.smtp.username', ''),
                    'password' => config('mail.mailers.smtp.password', ''),
                    'fromEmail' => config('mail.from.address', "noreply@{$emailDomain}.com"),
                    'fromName' => config('mail.from.name', $storeName),
                ];
            }
        });
    }

    private function buildOrderConfirmationHtml(array $data): string
    {
        $customerName = $data['customerName'] ?? 'Customer';
        $orderNumber = $data['orderNumber'] ?? 'N/A';
        $subtotal = number_format($data['subtotal'] ?? 0, 2);
        $shippingCost = number_format($data['shippingCost'] ?? 0, 2);
        $tax = number_format($data['tax'] ?? 0, 2);
        $discount = number_format($data['discount'] ?? 0, 2);
        $total = number_format($data['total'] ?? 0, 2);
        $shippingAddress = $data['shippingAddress'] ?? '';
        $paymentMethod = $data['paymentMethod'] ?? '';
        $estimatedDelivery = $data['estimatedDelivery'] ?? '';
        $hasDiscount = !empty($data['discount']) && $data['discount'] > 0;

        $itemsHtml = '';
        foreach ($data['items'] ?? [] as $item) {
            $name = $item['name'] ?? '';
            $qty = $item['quantity'] ?? 0;
            $price = number_format($item['price'] ?? 0, 2);
            $total = number_format($item['total'] ?? 0, 2);
            $itemsHtml .= '<tr><td style="padding:10px;border-bottom:1px solid #eee;">' . $name . '</td><td style="padding:10px;border-bottom:1px solid #eee;text-align:center;">' . $qty . '</td><td style="padding:10px;border-bottom:1px solid #eee;text-align:right;">$' . $price . '</td><td style="padding:10px;border-bottom:1px solid #eee;text-align:right;">$' . $total . '</td></tr>';
        }

        $discountRow = $hasDiscount ? '<tr><td style="padding:5px;color:#666;">Discount</td><td style="padding:5px;text-align:right;color:#22c55e;">-$' . $discount . '</td></tr>' : '';
        $deliveryRow = $estimatedDelivery ? '<p style="margin:4px 0 0;color:#555;">Estimated Delivery: <strong>' . $estimatedDelivery . '</strong></p>' : '';

        return $this->buildEmailWrapper('Order Confirmed! 🎉',
            '<p style="color:#333;font-size:16px;">Hi <strong>' . $customerName . '</strong>,</p>' .
            '<p style="color:#555;line-height:1.6;">Your order has been placed successfully.</p>' .
            '<div style="background:#f8f9fa;border-radius:6px;padding:15px;margin:20px 0;text-align:center;"><p style="margin:0;color:#666;font-size:14px;">Order Number</p><p style="margin:4px 0 0;font-size:20px;font-weight:bold;color:#1a1a2e;">' . $orderNumber . '</p></div>' .
            '<table style="width:100%;border-collapse:collapse;margin:20px 0;"><thead><tr style="background:#f8f9fa;"><th style="padding:10px;text-align:left;font-size:14px;color:#666;">Item</th><th style="padding:10px;text-align:center;font-size:14px;color:#666;">Qty</th><th style="padding:10px;text-align:right;font-size:14px;color:#666;">Price</th><th style="padding:10px;text-align:right;font-size:14px;color:#666;">Total</th></tr></thead><tbody>' . $itemsHtml . '</tbody></table>' .
            '<div style="border-top:2px solid #eee;padding-top:15px;"><table style="width:100%;"><tr><td style="padding:5px;color:#666;">Subtotal</td><td style="padding:5px;text-align:right;">$' . $subtotal . '</td></tr>' . $discountRow . '<tr><td style="padding:5px;color:#666;">Shipping</td><td style="padding:5px;text-align:right;">$' . $shippingCost . '</td></tr><tr><td style="padding:5px;color:#666;">Tax</td><td style="padding:5px;text-align:right;">$' . $tax . '</td></tr><tr style="font-weight:bold;font-size:18px;"><td style="padding:10px 5px;border-top:2px solid #333;">Total</td><td style="padding:10px 5px;border-top:2px solid #333;text-align:right;">$' . $total . '</td></tr></table></div>' .
            '<div style="background:#f8f9fa;border-radius:6px;padding:15px;margin:20px 0;"><h3 style="margin:0 0 10px;font-size:16px;color:#333;">Shipping Address</h3><p style="margin:0;color:#555;">' . $shippingAddress . '</p><p style="margin:8px 0 0;color:#555;">Payment: <strong>' . $paymentMethod . '</strong></p>' . $deliveryRow . '</div>'
        );
    }

    private function buildBuiltInTemplate(string $templateId, array $data): string
    {
        $customerName = $data['customerName'] ?? 'Customer';
        $storeName = $data['storeName'] ?? $this->getStoreName();

        switch ($templateId) {
            case 'orderConfirmation':
                return $this->buildOrderConfirmationHtml($data);
            case 'orderStatusUpdate':
                $statusLabels = ['PENDING' => 'Pending', 'CONFIRMED' => 'Confirmed', 'PROCESSING' => 'Processing', 'SHIPPED' => 'Shipped', 'DELIVERED' => 'Delivered', 'CANCELLED' => 'Cancelled', 'RETURNED' => 'Returned', 'RETURN_REQUESTED' => 'Return Requested'];
                $status = $data['newStatus'] ?? 'UPDATED';
                $label = $statusLabels[$status] ?? $status;
                $orderNum = $data['orderNumber'] ?? '';
                return $this->buildSimpleEmail('Order Update', $customerName . ', your order ' . $orderNum . ' status has been updated to: ' . $label . '.');
            case 'passwordReset':
                $resetLink = $data['resetLink'] ?? '#';
                $expiryHours = $data['expiryHours'] ?? 1;
                return $this->buildSimpleEmail('Reset Your Password',
                    'Hi ' . $customerName . ',<br><br>You requested a password reset. Click the link below to reset your password. This link expires in ' . $expiryHours . ' hour(s).<br><br><a href="' . $resetLink . '" style="background:#1a1a2e;color:white;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;">Reset Password</a>');
            case 'emailVerification':
                $verificationLink = $data['verificationLink'] ?? '#';
                $expiryHours = $data['expiryHours'] ?? 24;
                return $this->buildSimpleEmail('Verify Your Email',
                    'Hi ' . $customerName . ',<br><br>Please verify your email address by clicking the link below. This link expires in ' . $expiryHours . ' hour(s).<br><br><a href="' . $verificationLink . '" style="background:#1a1a2e;color:white;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;">Verify Email</a>');
            case 'welcomeEmail':
                return $this->buildSimpleEmail('Welcome to ' . $storeName . '! 🎉',
                    'Hi ' . $customerName . ',<br><br>Welcome to ' . $storeName . '! We\'re thrilled to have you on board.<br><br>Start exploring our premium collection and enjoy exclusive offers.');
            case 'abandonedCart':
                $recoveryLink = $data['recoveryLink'] ?? '#';
                return $this->buildSimpleEmail('You Left Something Behind! 🛒',
                    'Hi ' . $customerName . ',<br><br>You have items waiting in your cart. Don\'t miss out — complete your purchase now!<br><br><a href="' . $recoveryLink . '" style="background:#1a1a2e;color:white;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;">Complete Order</a>');
            case 'returnApproved':
                $resolution = $data['resolution'] ?? '';
                $adminResponse = $data['adminResponse'] ?? '';
                $requestId = $data['requestId'] ?? '';
                $resolutionMsg = $resolution
                    ? 'We\'ve approved your return with the following resolution: <strong>' . $resolution . '</strong>.'
                    : 'Your return request has been approved.';
                $responseBlock = $adminResponse
                    ? '<p style="margin:12px 0 0;padding:12px;background:#f0fdf4;border-radius:6px;color:#333;font-size:14px;"><strong>Admin Note:</strong><br>' . $adminResponse . '</p>'
                    : '';
                return $this->buildSimpleEmail('Return Request Approved ✅',
                    'Hi ' . $customerName . ',<br><br>' . $resolutionMsg . '<br><br>Request ID: <strong>' . $requestId . '</strong>' . $responseBlock . '<br><p style="margin-top:12px;color:#555;">We\'ll be in touch with next steps shortly. If you have questions, reply to this email.</p>');
            case 'returnRejected':
                $adminResponse = $data['adminResponse'] ?? '';
                $requestId = $data['requestId'] ?? '';
                $responseBlock = $adminResponse
                    ? '<p style="margin:12px 0 0;padding:12px;background:#fef2f2;border-radius:6px;color:#333;font-size:14px;"><strong>Reason:</strong><br>' . $adminResponse . '</p>'
                    : '';
                return $this->buildSimpleEmail('Return Request Update',
                    'Hi ' . $customerName . ',<br><br>Unfortunately, your return request <strong>#' . $requestId . '</strong> could not be approved at this time.' . $responseBlock . '<br><p style="margin-top:12px;color:#555;">If you believe this is an error or would like further clarification, please contact our support team.</p>');
            case 'returnCompleted':
                $refundAmount = $data['refundAmount'] ?? '';
                $requestId = $data['requestId'] ?? '';
                $amountBlock = $refundAmount
                    ? '<p style="margin:12px 0;padding:12px;background:#f0fdf4;border-radius:6px;font-size:16px;text-align:center;">💰 Refund Amount: <strong>' . $refundAmount . '</strong></p>'
                    : '';
                return $this->buildSimpleEmail('Return Complete — Refund Processed 🎉',
                    'Hi ' . $customerName . ',<br><br>Your return request <strong>#' . $requestId . '</strong> has been completed and your refund has been processed.' . $amountBlock . '<br><p style="color:#555;">The refund will be credited to your original payment method within 5–7 business days.</p>');
            default:
                return $this->buildSimpleEmail('Notification', 'Hello ' . $customerName . ',<br><br>This is an automated message from ' . $storeName . '.');
        }
    }

    private function getStoreName(): string
    {
        try {
            return $this->cacheWithTracking('store_name', 300, function () {
                return Setting::where('module', 'SITE')->where('key', 'storeName')->value('value') ?? 'THREVOLT';
            });
        } catch (\Exception $e) { return 'THREVOLT'; }
    }

    private function buildEmailWrapper(string $title, string $bodyHtml, string $storeName = ''): string
    {
        $year = now()->year;
        if (empty($storeName)) { $storeName = $this->getStoreName(); }
        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0;background-color:#f4f4f4;"><div style="max-width:600px;margin:20px auto;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);"><div style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%);padding:30px;text-align:center;"><h1 style="color:#ffffff;margin:0;font-size:24px;">' . $title . '</h1></div><div style="padding:30px;">' . $bodyHtml . '</div><div style="background:#f8f9fa;padding:20px 30px;text-align:center;border-top:1px solid #eee;"><p style="margin:0;color:#999;font-size:13px;">&copy; ' . $year . ' ' . $storeName . '. All rights reserved.</p></div></div></body></html>';
    }

    private function buildSimpleEmail(string $title, string $bodyHtml): string
    {
        return $this->buildEmailWrapper($title, '<p style="color:#333;font-size:16px;">' . $bodyHtml . '</p>');
    }
}
