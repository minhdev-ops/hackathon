<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Grant;
use App\Services\GrantService;
use Illuminate\Http\JsonResponse;

class GrantOpenController extends Controller
{
    public function __construct(private readonly GrantService $grants) {}

    /**
     * POST /agent/grants/{id}/open — 410 GRANT_UNAVAILABLE once revoked/expired/denied.
     */
    public function __invoke(Grant $grant): JsonResponse
    {
        return response()->json(['data' => $this->grants->open($grant)]);
    }
}
