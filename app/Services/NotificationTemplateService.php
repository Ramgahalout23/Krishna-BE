<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotificationTemplateService
{
    /**
     * All notification template types with metadata, variables, and default text.
     */
    public static function getTemplateDefinitions(): array
    {
        return [
        // ── SMS Templates ──
        [
            'id' => 'sms_order_confirmation',
            'channel' => 'sms',
            'name' => 'Order Confirmation SMS',
            'description' => 'Sent when a customer successfully places an order',
            'icon' => '📦',
            'variables' => ['customerName', 'orderNumber', 'total'],
            'default_template' => "Hi {customerName}, your order {orderNumber} is confirmed! Total: \${total}. We'll notify you when it ships. Thank you for shopping with us!",
        ],
        [
            'id' => 'sms_order_shipped',
            'channel' => 'sms',
            'name' => 'Order Shipped SMS',
            'description' => 'Sent when an order is shipped',
            'icon' => '🚚',
            'variables' => ['customerName', 'orderNumber'],
            'default_template' => "Hi {customerName}, your order {orderNumber} has been shipped! Track your package in your account dashboard.",
        ],
        [
            'id' => 'sms_order_delivered',
            'channel' => 'sms',
            'name' => 'Order Delivered SMS',
            'description' => 'Sent when an order is delivered',
            'icon' => '✅',
            'variables' => ['customerName', 'orderNumber'],
            'default_template' => "Hi {customerName}, your order {orderNumber} has been delivered! We hope you love your purchase.",
        ],
        [
            'id' => 'sms_order_cancelled',
            'channel' => 'sms',
            'name' => 'Order Cancelled SMS',
            'description' => 'Sent when an order is cancelled',
            'icon' => '❌',
            'variables' => ['customerName', 'orderNumber'],
            'default_template' => "Hi {customerName}, your order {orderNumber} has been cancelled. Contact support for more details.",
        ],
        [
            'id' => 'sms_order_status_update',
            'channel' => 'sms',
            'name' => 'Order Status Update SMS',
            'description' => 'Sent when an order status changes (generic)',
            'icon' => '🔄',
            'variables' => ['customerName', 'orderNumber', 'newStatus', 'statusLabel'],
            'default_template' => "Hi {customerName}, your order {orderNumber} status updated to: {statusLabel}.",
        ],
        [
            'id' => 'sms_otp',
            'channel' => 'sms',
            'name' => 'OTP Verification SMS',
            'description' => 'Sent with a verification code',
            'icon' => '🔑',
            'variables' => ['otpCode', 'expiryMinutes'],
            'default_template' => "Your verification code is: {otpCode}. This code expires in {expiryMinutes} minutes. Do not share this with anyone.",
        ],

        // ── In-App Notification Templates ──
        [
            'id' => 'notif_order_confirmed',
            'channel' => 'in_app',
            'name' => 'Order Confirmed Notification',
            'description' => 'In-app notification when an order is placed',
            'icon' => '🎉',
            'variables' => ['customerName', 'orderNumber', 'total'],
            'default_title' => 'Order Confirmed 🎉',
            'default_message' => "Your order {orderNumber} has been placed successfully. Total: \${total}",
        ],
        [
            'id' => 'notif_order_shipped',
            'channel' => 'in_app',
            'name' => 'Order Shipped Notification',
            'description' => 'In-app notification when an order ships',
            'icon' => '🚚',
            'variables' => ['customerName', 'orderNumber'],
            'default_title' => 'Order Shipped! 🚚',
            'default_message' => "Your order {orderNumber} is on its way! Track your package in your dashboard.",
        ],
        [
            'id' => 'notif_order_delivered',
            'channel' => 'in_app',
            'name' => 'Order Delivered Notification',
            'description' => 'In-app notification when an order is delivered',
            'icon' => '✅',
            'variables' => ['customerName', 'orderNumber'],
            'default_title' => 'Order Delivered ✅',
            'default_message' => "Your order {orderNumber} has been delivered. We hope you love it!",
        ],
        [
            'id' => 'notif_order_cancelled',
            'channel' => 'in_app',
            'name' => 'Order Cancelled Notification',
            'description' => 'In-app notification when an order is cancelled',
            'icon' => '❌',
            'variables' => ['customerName', 'orderNumber'],
            'default_title' => 'Order Cancelled',
            'default_message' => "Your order {orderNumber} has been cancelled.",
        ],
        [
            'id' => 'notif_abandoned_cart',
            'channel' => 'in_app',
            'name' => 'Abandoned Cart Notification',
            'description' => 'In-app notification when reminding about abandoned cart',
            'icon' => '🛒',
            'variables' => ['customerName', 'itemCount', 'recoveryLink'],
            'default_title' => 'You left something behind! 🛒',
            'default_message' => "You have {itemCount} item(s) waiting in your cart. Complete your purchase now!",
        ],

        // ── Return Request Notifications ──
        [
            'id' => 'notif_return_approved',
            'channel' => 'in_app',
            'name' => 'Return Approved Notification',
            'description' => 'In-app notification when a return request is approved',
            'icon' => '✅',
            'variables' => ['customerName', 'requestId', 'resolution'],
            'default_title' => 'Return Approved ✅',
            'default_message' => "Your return request #{requestId} has been approved. Resolution: {resolution}.",
        ],
        [
            'id' => 'notif_return_rejected',
            'channel' => 'in_app',
            'name' => 'Return Rejected Notification',
            'description' => 'In-app notification when a return request is rejected',
            'icon' => '❌',
            'variables' => ['customerName', 'requestId'],
            'default_title' => 'Return Request Update',
            'default_message' => "Your return request #{requestId} could not be approved. Please contact support for more details.",
        ],
        [
            'id' => 'notif_return_completed',
            'channel' => 'in_app',
            'name' => 'Return Completed Notification',
            'description' => 'In-app notification when a return is completed and refund processed',
            'icon' => '💰',
            'variables' => ['customerName', 'requestId', 'refundAmount'],
            'default_title' => 'Refund Processed 🎉',
            'default_message' => "Your return #{requestId} has been completed. Refund of {refundAmount} has been processed.",
        ],
        ];
    }

    /**
     * Get all template definitions with their stored configuration.
     */
    public function getAllTemplates(): array
    {
        $settings = Setting::where('module', 'NOTIFICATION_TEMPLATE')
            ->where('key', 'like', 'nt.%')
            ->get();

        $settingsMap = [];
        foreach ($settings as $s) {
            $parts = explode('.', $s->key);
            $id = $parts[1] ?? '';
            $field = $parts[2] ?? '';
            if ($id && $field) {
                $settingsMap[$id][$field] = $s->value;
            }
        }

        return array_map(function ($t) use ($settingsMap) {
            $id = $t['id'];
            $stored = $settingsMap[$id] ?? [];
            $result = [
                'id' => $id,
                'channel' => $t['channel'],
                'name' => $t['name'],
                'description' => $t['description'],
                'icon' => $t['icon'],
                'variables' => $t['variables'],
                'mode' => $stored['mode'] ?? 'DEFAULT',
                'active' => ($stored['active'] ?? 'true') === 'true',
            ];

            if ($t['channel'] === 'sms') {
                $result['default_template'] = $t['default_template'];
                $result['custom_template'] = $stored['template'] ?? '';
            } else {
                $result['default_title'] = $t['default_title'];
                $result['default_message'] = $t['default_message'];
                $result['custom_title'] = $stored['title'] ?? '';
                $result['custom_message'] = $stored['message'] ?? '';
            }

            return $result;
        }, self::getTemplateDefinitions());
    }

    /**
     * Get a single template definition with its stored configuration.
     */
    public function getTemplate(string $id): ?array
    {
        foreach (self::getTemplateDefinitions() as $t) {
            if ($t['id'] === $id) {
                $settings = Setting::where('module', 'NOTIFICATION_TEMPLATE')
                    ->where('key', 'like', "nt.{$id}.%")
                    ->get();
                $settingsMap = [];
                foreach ($settings as $s) {
                    $parts = explode('.', $s->key);
                    $field = $parts[2] ?? 'value';
                    $settingsMap[$field] = $s->value;
                }

                $result = [
                    'id' => $t['id'],
                    'channel' => $t['channel'],
                    'name' => $t['name'],
                    'description' => $t['description'],
                    'icon' => $t['icon'],
                    'variables' => $t['variables'],
                    'mode' => $settingsMap['mode'] ?? 'DEFAULT',
                    'active' => ($settingsMap['active'] ?? 'true') === 'true',
                ];

                if ($t['channel'] === 'sms') {
                    $result['default_template'] = $t['default_template'];
                    $result['custom_template'] = $settingsMap['template'] ?? '';
                } else {
                    $result['default_title'] = $t['default_title'];
                    $result['default_message'] = $t['default_message'];
                    $result['custom_title'] = $settingsMap['title'] ?? '';
                    $result['custom_message'] = $settingsMap['message'] ?? '';
                }

                return $result;
            }
        }
        return null;
    }

    /**
     * Update a template's configuration.
     */
    public function updateTemplate(string $id, array $data): array
    {
        $definition = null;
        foreach (self::getTemplateDefinitions() as $t) {
            if ($t['id'] === $id) {
                $definition = $t;
                break;
            }
        }
        if (!$definition) {
            throw new \App\Exceptions\AppError('Template not found', 404);
        }

        if (isset($data['mode'])) {
            Setting::updateOrCreate(
                ['module' => 'NOTIFICATION_TEMPLATE', 'key' => "nt.{$id}.mode"],
                ['value' => $data['mode']]
            );
        }

        if (isset($data['active'])) {
            Setting::updateOrCreate(
                ['module' => 'NOTIFICATION_TEMPLATE', 'key' => "nt.{$id}.active"],
                ['value' => $data['active'] ? 'true' : 'false']
            );
        }

        if ($definition['channel'] === 'sms') {
            if (isset($data['template'])) {
                Setting::updateOrCreate(
                    ['module' => 'NOTIFICATION_TEMPLATE', 'key' => "nt.{$id}.template"],
                    ['value' => $data['template']]
                );
            }
        } else {
            if (isset($data['title'])) {
                Setting::updateOrCreate(
                    ['module' => 'NOTIFICATION_TEMPLATE', 'key' => "nt.{$id}.title"],
                    ['value' => $data['title']]
                );
            }
            if (isset($data['message'])) {
                Setting::updateOrCreate(
                    ['module' => 'NOTIFICATION_TEMPLATE', 'key' => "nt.{$id}.message"],
                    ['value' => $data['message']]
                );
            }
        }

        // Clear cached template active status so changes take effect immediately
        Cache::forget("nt_template_active_{$id}");

        return $this->getTemplate($id);
    }

    /**
     * Toggle a template's active status.
     */
    public function toggleTemplate(string $id): array
    {
        $current = Setting::where('module', 'NOTIFICATION_TEMPLATE')
            ->where('key', "nt.{$id}.active")
            ->first();
        $newActive = $current && $current->value === 'true' ? 'false' : 'true';
        Setting::updateOrCreate(
            ['module' => 'NOTIFICATION_TEMPLATE', 'key' => "nt.{$id}.active"],
            ['value' => $newActive]
        );

        // Clear cached template active status so toggle takes effect immediately
        Cache::forget("nt_template_active_{$id}");

        return ['id' => $id, 'active' => $newActive === 'true'];
    }

    /**
     * Render a template with data substitution.
     * For SMS: returns the compiled text.
     * For in-app: returns ['title' => ..., 'message' => ...]
     */
    public function renderTemplate(string $templateId, array $data = []): array
    {
        $template = $this->getTemplate($templateId);
        if (!$template) {
            throw new \App\Exceptions\AppError("Template '{$templateId}' not found", 404);
        }

        if (!$template['active']) {
            return ['rendered' => false, 'active' => false];
        }

        if ($template['channel'] === 'sms') {
            $text = $template['mode'] === 'CUSTOM' && !empty($template['custom_template'])
                ? $template['custom_template']
                : $template['default_template'];

            foreach ($data as $key => $value) {
                $text = str_replace("{{$key}}", (string) $value, $text);
            }

            return ['rendered' => true, 'channel' => 'sms', 'body' => $text];
        } else {
            $title = $template['mode'] === 'CUSTOM' && !empty($template['custom_title'])
                ? $template['custom_title']
                : $template['default_title'];

            $message = $template['mode'] === 'CUSTOM' && !empty($template['custom_message'])
                ? $template['custom_message']
                : $template['default_message'];

            foreach ($data as $key => $value) {
                $title = str_replace("{{$key}}", (string) $value, $title);
                $message = str_replace("{{$key}}", (string) $value, $message);
            }

            return ['rendered' => true, 'channel' => 'in_app', 'title' => $title, 'message' => $message];
        }
    }

    /**
     * Check if a specific template is active.
     */
    public function isTemplateActive(string $templateId): bool
    {
        try {
            return Cache::remember("nt_template_active_{$templateId}", 300, function () use ($templateId) {
                $setting = Setting::where('module', 'NOTIFICATION_TEMPLATE')
                    ->where('key', "nt.{$templateId}.active")
                    ->first();
                return $setting?->value !== 'false';
            });
        } catch (\Exception $e) {
            return true;
        }
    }
}
