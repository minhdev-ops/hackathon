<?php

use App\Http\Controllers\Api\V1\Admin\ChangeRequestController;
use App\Http\Controllers\Api\V1\Admin\DirectChangeController;
use App\Http\Controllers\Api\V1\Admin\QuorumMetaController;
use App\Http\Controllers\Api\V1\Admin\ReauthController;
use App\Http\Controllers\Api\V1\Agent\DocumentRequestController;
use App\Http\Controllers\Api\V1\Agent\GrantOpenController;
use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\DemoController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\GrantController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PlaybookController;
use App\Http\Controllers\Api\V1\TasksController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| TrustGate API (v1)
|--------------------------------------------------------------------------
|
| `auth.api` chọn chế độ theo `AUTH_MODE` (config/tidecloak.php):
|   - session (mặc định, demo tạm): session guard + cookie same-origin.
|   - tide: VerifyTideJwt — Bearer JWT verify RS256 theo JWKS realm.
| Hợp đồng `$request->user()` giữ nguyên ở cả hai chế độ.
|
*/

Route::prefix('v1')->middleware(['auth.api'])->group(function () {
    // FR-AUTH-04: GET /api/v1/me — luôn trả 200 khi đã đăng nhập (kể cả chưa
    // xác thực email) để UI biết trạng thái `email_verified` và điều hướng đúng.
    Route::get('/me', MeController::class)->middleware('throttle:60,1');

    // Các endpoint nghiệp vụ yêu cầu email đã xác thực (403 EMAIL_NOT_VERIFIED).
    Route::middleware('email.verified')->group(function () {
        /*
    |--------------------------------------------------------------------------
    | PLAYBOOK — Lĩnh vực (FR-PB-01..02), owner/admin xem
    |--------------------------------------------------------------------------
    */

        Route::get('/playbooks', PlaybookController::class)
            ->middleware('role:owner,admin', 'throttle:60,1');

        /*
        |--------------------------------------------------------------------------
        | VAULT — Két tài liệu (FR-VAULT-01..05), owner only
        |--------------------------------------------------------------------------
        */

        Route::post('/documents', [DocumentController::class, 'store'])->middleware('throttle:60,1');
        Route::get('/documents', [DocumentController::class, 'index'])->middleware('throttle:60,1');
        Route::get('/documents/{document}/ciphertext', [DocumentController::class, 'ciphertext'])->middleware('throttle:60,1');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->middleware('throttle:60,1');

        Route::get('/grants/{grant}', [GrantController::class, 'show']);
        Route::post('/grants/{grant}/approve', [GrantController::class, 'approve'])
            ->middleware('throttle:60,1');
        Route::post('/grants/{grant}/deny', [GrantController::class, 'deny'])
            ->middleware('throttle:60,1');
        Route::post('/grants/{grant}/revoke', [GrantController::class, 'revoke'])
            ->middleware('throttle:60,1');

        Route::post('/agent/grants/{grant}/open', GrantOpenController::class)
            ->middleware('throttle:30,1');

        // FR-AGENT-04: agent xin tài liệu ngoài phạm vi (demo) → 403 OUT_OF_SCOPE.
        Route::post('/agent/documents/request', [DocumentRequestController::class, '__invoke'])
            ->middleware('role:agent', 'throttle:30,1');

        /*
        |--------------------------------------------------------------------------
        | TASK — Giao việc cho agent (FR-TASK-01, FR-AGENT-01)
        |--------------------------------------------------------------------------
        */

        Route::post('/tasks', [TasksController::class, 'store'])
            ->middleware('throttle:60,1');
        Route::get('/tasks', [TasksController::class, 'index'])
            ->middleware('throttle:60,1');
        Route::get('/tasks/{task}', [TasksController::class, 'show'])
            ->middleware('throttle:60,1');

        /*
        |--------------------------------------------------------------------------
        | AUDIT — Nhật ký hash-chain (FR-AUD-01..03)
        |--------------------------------------------------------------------------
        */

        Route::middleware('role:owner,admin')->group(function () {
            Route::get('/audit', [AuditController::class, 'index'])->middleware('throttle:60,1');
            Route::get('/audit/verify', [AuditController::class, 'verify'])->middleware('throttle:60,1');
        });

        /*
        |--------------------------------------------------------------------------
        | QRM — Quorum quản trị (FR-QRM-01..05), admin only
        |--------------------------------------------------------------------------
        */

        Route::prefix('admin')->middleware('role:admin')->group(function () {
            Route::get('/quorum/meta', QuorumMetaController::class);
            Route::post('/reauth', ReauthController::class)->middleware('throttle:10,1');

            Route::get('/change-requests', [ChangeRequestController::class, 'index']);
            Route::post('/change-requests', [ChangeRequestController::class, 'store']);
            Route::post('/change-requests/{changeRequest}/approve', [ChangeRequestController::class, 'approve'])
                ->middleware('throttle:60,1');
            Route::post('/change-requests/{changeRequest}/reject', [ChangeRequestController::class, 'reject'])
                ->middleware('throttle:60,1');

            // FR-QRM-01: đổi trực tiếp luôn bị chặn bằng 409 QUORUM_REQUIRED.
            Route::post('/direct/roles/grant', [DirectChangeController::class, 'grantRole']);
            Route::post('/direct/roles/revoke', [DirectChangeController::class, 'revokeRole']);
            Route::patch('/direct/playbooks/{slug}', [DirectChangeController::class, 'updatePlaybook']);
            Route::patch('/direct/agents/{clientId}/allowlist', [DirectChangeController::class, 'updateAgentAllowlist']);
            Route::patch('/direct/quorum-threshold', [DirectChangeController::class, 'updateQuorumThreshold']);
        });

        /*
        |--------------------------------------------------------------------------
        | Demo «Làm admin xấu» (FR-DEMO-01..05) — chỉ bật khi APP_DEMO=true
        |--------------------------------------------------------------------------
        */

        Route::prefix('demo')->middleware(['demo.enabled', 'role:admin'])->group(function () {
            Route::post('/evil/dump-db', [DemoController::class, 'dumpDb']);
            Route::post('/evil/self-elevate', [DemoController::class, 'selfElevate']);
            Route::post('/evil/out-of-scope', [DemoController::class, 'outOfScope']);
            Route::post('/evil/slow-run', [DemoController::class, 'slowRun']);
            Route::get('/evil/slow-run-log/{task}', [DemoController::class, 'slowRunLog']);
            Route::post('/reset', [DemoController::class, 'reset']);
            Route::post('/agent-token', [DemoController::class, 'agentToken']);
        });
    });
});
