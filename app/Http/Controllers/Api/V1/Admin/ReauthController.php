<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ReauthController extends Controller
{
    /**
     * POST /admin/reauth — xác thực lại bằng mật khẩu (FR-QRM-04 fallback).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            throw new ApiException('REAUTH_FAILED', 'Mật khẩu không đúng.', 422);
        }

        $minutes = max(1, (int) config('quorum.reauth_minutes', 5));

        $request->session()->put('admin_reauth_at', now()->getTimestamp());

        return response()->json([
            'data' => [
                'reauthenticated_until' => now()->addMinutes($minutes)->toIso8601String(),
                'reauth_minutes' => $minutes,
            ],
        ]);
    }
}
