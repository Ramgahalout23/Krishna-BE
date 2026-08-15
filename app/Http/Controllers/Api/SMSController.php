<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SMSService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SMSController extends Controller
{
    public function __construct(
        protected SMSService $smsService
    ) {}

    /**
     * Check SMS service health.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->smsService->checkHealth(),
        ]);
    }

    /**
     * Check if SMS is enabled.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['enabled' => $this->smsService->isSmsEnabled()],
        ]);
    }

    /**
     * Send an OTP via SMS.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
            'otp' => 'required|string|size:6',
        ]);

        $sent = $this->smsService->sendOTP($validated['phone'], $validated['otp']);
        if ($sent) {
            return response()->json(['success' => true, 'message' => 'OTP sent successfully']);
        }
        return response()->json(['success' => false, 'message' => 'Failed to send OTP'], 500);
    }

    /**
     * Send a custom SMS (admin only).
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to' => 'required|string|max:20',
            'message' => 'required|string|max:1600',
        ]);

        $sent = $this->smsService->sendSMS($validated['to'], $validated['message']);
        if ($sent) {
            return response()->json(['success' => true, 'message' => 'SMS sent successfully']);
        }
        return response()->json(['success' => false, 'message' => 'Failed to send SMS'], 500);
    }
}
