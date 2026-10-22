<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlaybookResource;
use App\Models\Playbook;
use Illuminate\Http\JsonResponse;

/**
 * FR-PB-02: GET /playbooks cho Owner xem. Admin chỉ sửa qua Change Request.
 */
class PlaybookController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $playbooks = Playbook::query()->where('active', true)->latest()->get();

        return response()->json(['data' => PlaybookResource::collection($playbooks)]);
    }
}
