<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BroadcastingController extends Controller
{
    /**
     * Return Pusher config for the frontend (public-safe data only).
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'key' => config('services.pusher.key') ?? env('PUSHER_APP_KEY'),
                'cluster' => config('services.pusher.options.cluster') ?? env('PUSHER_APP_CLUSTER', 'mt1'),
            ],
        ]);
    }
}
