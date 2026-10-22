<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveGrantRequest;
use App\Http\Resources\GrantResource;
use App\Models\Grant;
use App\Services\GrantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GrantController extends Controller
{
    public function __construct(private readonly GrantService $grants) {}

    /**
     * FR-GRANT-01: agent, purpose, proposed documents (checkbox list) and TTL choices.
     */
    public function show(Grant $grant): GrantResource
    {
        Gate::authorize('view', $grant);

        return new GrantResource($grant);
    }

    /**
     * FR-GRANT-02: approve with doc_ids, ttl_minutes and the encrypted bundle.
     */
    public function approve(ApproveGrantRequest $request, Grant $grant): GrantResource
    {
        Gate::authorize('approve', $grant);

        $validated = $request->validated();

        $approved = $this->grants->approve(
            $grant,
            $validated['doc_ids'],
            (int) $validated['ttl_minutes'],
            $validated['bundle_iv'],
            $validated['grant_key_b64'],
            $request->file('bundle'),
        );

        return new GrantResource($approved);
    }

    /**
     * FR-GRANT-06: deny the grant; the agent can no longer open it.
     */
    public function deny(Request $request, Grant $grant): GrantResource
    {
        Gate::authorize('deny', $grant);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return new GrantResource($this->grants->deny($grant, $validated['reason'] ?? null));
    }

    /**
     * FR-GRANT-03: revoke an active grant and shred K_g.
     */
    public function revoke(Request $request, Grant $grant): GrantResource
    {
        Gate::authorize('revoke', $grant);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return new GrantResource($this->grants->revoke($grant, $validated['reason'] ?? null));
    }
}
