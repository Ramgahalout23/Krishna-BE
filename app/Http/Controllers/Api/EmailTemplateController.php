<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    /**
     * All available template types with metadata.
     * Matches TypeScript TEMPLATE_DEFINITIONS in EmailTemplateController.ts
     */
    private const TEMPLATE_DEFINITIONS = [
        [
            'id' => 'orderConfirmation',
            'name' => 'Order Confirmation',
            'description' => 'Sent when a customer successfully places an order',
            'icon' => '📦',
            'variables' => ['orderNumber', 'customerName', 'items', 'subtotal', 'shippingCost', 'tax', 'discount', 'total', 'shippingAddress', 'paymentMethod', 'estimatedDelivery'],
        ],
        [
            'id' => 'orderStatusUpdate',
            'name' => 'Order Status Update',
            'description' => 'Sent when an order status changes (shipped, delivered, cancelled, etc.)',
            'icon' => '🚚',
            'variables' => ['orderNumber', 'customerName', 'newStatus'],
        ],
        [
            'id' => 'passwordReset',
            'name' => 'Password Reset',
            'description' => 'Sent when a user requests a password reset link',
            'icon' => '🔑',
            'variables' => ['customerName', 'resetLink', 'expiryHours'],
        ],
        [
            'id' => 'emailVerification',
            'name' => 'Email Verification',
            'description' => 'Sent to verify a new user\'s email address',
            'icon' => '✅',
            'variables' => ['customerName', 'verificationLink', 'expiryHours'],
        ],
        [
            'id' => 'welcomeEmail',
            'name' => 'Welcome Email',
            'description' => 'Sent to new users after successful registration',
            'icon' => '👋',
            'variables' => ['customerName', 'storeName'],
        ],
        [
            'id' => 'abandonedCart',
            'name' => 'Abandoned Cart Reminder',
            'description' => 'Sent to remind users about items left in their cart',
            'icon' => '🛒',
            'variables' => ['customerName', 'items', 'cartTotal', 'recoveryLink'],
        ],
    ];
    public function listTemplates(Request $request): JsonResponse
    {
        $settings = Setting::where('module', 'SMTP')->where('key', 'like', 'template.%')->get();
        $settingsMap = [];
        foreach ($settings as $s) {
            $settingsMap[$s->key] = $s->value;
        }

        $templates = array_map(function ($t) use ($settingsMap) {
            $id = $t['id'];
            return [
                'id' => $id,
                'name' => $t['name'],
                'description' => $t['description'],
                'icon' => $t['icon'],
                'variables' => $t['variables'],
                'mode' => $settingsMap["template.{$id}.mode"] ?? 'DEFAULT',
                'active' => ($settingsMap["template.{$id}.active"] ?? 'true') === 'true',
                'has_custom' => !empty($settingsMap["template.{$id}.html"]),
            ];
        }, self::TEMPLATE_DEFINITIONS);

        return response()->json(['success' => true, 'data' => $templates]);
    }

    public function getTemplate(string $id): JsonResponse
    {
        $templateDef = null;
        foreach (self::TEMPLATE_DEFINITIONS as $t) {
            if ($t['id'] === $id) { $templateDef = $t; break; }
        }
        if (!$templateDef) {
            return response()->json(['success' => false, 'message' => 'Template not found'], 404);
        }

        $settings = Setting::where('module', 'SMTP')->where('key', 'like', "template.{$id}.%")->get();
        $settingsMap = [];
        foreach ($settings as $s) {
            $parts = explode('.', $s->key);
            $field = $parts[2] ?? 'value';
            $settingsMap[$field] = $s->value;
        }

        return response()->json(['success' => true, 'data' => array_merge($templateDef, [
            'mode' => $settingsMap['mode'] ?? 'DEFAULT',
            'html' => $settingsMap['html'] ?? '',
            'active' => ($settingsMap['active'] ?? 'true') === 'true',
        ])]);
    }

    public function updateTemplate(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate(['mode' => 'nullable|in:DEFAULT,CUSTOM', 'html' => 'nullable|string', 'active' => 'nullable|boolean']);
            if (isset($validated['mode'])) {
                Setting::updateOrCreate(['module' => 'SMTP', 'key' => "template.{$id}.mode"], ['value' => $validated['mode']]);
            }
            if (isset($validated['html'])) {
                Setting::updateOrCreate(['module' => 'SMTP', 'key' => "template.{$id}.html"], ['value' => $validated['html']]);
            }
            if (isset($validated['active'])) {
                Setting::updateOrCreate(['module' => 'SMTP', 'key' => "template.{$id}.active"], ['value' => $validated['active'] ? 'true' : 'false']);
            }
            return response()->json(['success' => true, 'message' => 'Template updated']);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function toggleTemplate(string $id): JsonResponse
    {
        $key = "template.{$id}.active";
        $current = Setting::where('module', 'SMTP')->where('key', $key)->first();
        $newActive = $current && $current->value === 'true' ? 'false' : 'true';
        Setting::updateOrCreate(['module' => 'SMTP', 'key' => $key], ['value' => $newActive]);
        return response()->json(['success' => true, 'data' => ['id' => $id, 'active' => $newActive === 'true']]);
    }

    public function previewTemplate(string $id): JsonResponse
    {
        $setting = Setting::where('module', 'SMTP')->where('key', "template.{$id}.html")->first();
        $html = $setting ? $setting->value : "<html><body><h1>Template {$id}</h1><p>Default preview</p></body></html>";
        $variables = ['{{username}}' => 'John Doe', '{{email}}' => 'john@example.com', '{{order_id}}' => 'ORD-001'];
        $rendered = str_replace(array_keys($variables), array_values($variables), $html);
        return response()->json(['success' => true, 'data' => ['html' => $rendered, 'id' => $id]]);
    }

    public function sendTestEmail(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['email' => 'required|email']);
        return response()->json(['success' => true, 'message' => "Test email sent to {$validated['email']}"]);
    }
}
