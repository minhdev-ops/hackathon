<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * FR-AUTH-04: GET /api/v1/me — thông tin user hiện tại (không trả password).
 */
class MeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user === null) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Chưa đăng nhập hoặc phiên đã hết hạn.',
                ],
            ], 401);
        }

        return response()->json([
            'data' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tide_sub' => $user->tide_sub,
                'vault_kdf_salt' => $user->vault_kdf_salt,
                'email_verified' => $user->hasVerifiedEmail(),
            ],
        ]);
    }
}
