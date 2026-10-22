<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FR-VAULT-01..05: Két tài liệu — upload, danh sách, tải bản mã, xoá (soft delete).
 * Mã hoá đã diễn ra ở trình duyệt; server chỉ nhận và lưu bản mã.
 */
class DocumentController extends Controller
{
    /**
     * FR-VAULT-01: Owner tải lên tài liệu đã mã hoá.
     */
    public function store(StoreDocumentRequest $request): JsonResponse
    {
        Gate::authorize('create', Document::class);

        $user = $request->user();

        $file = $request->file('file');
        $storedPath = $file->store(
            'documents/' . $user->getKey(),
            ['disk' => config('trustgate.grant_bundle_disk')]
        );

        $document = Document::create([
            'owner_id' => $user->getKey(),
            'doc_type' => $request->string('doc_type'),
            'title_ct' => $request->string('title_ct'),
            'ciphertext_path' => $storedPath,
            'iv' => $request->string('iv'),
            'sha256' => $request->string('sha256'),
            'size_bytes' => $file->getSize(),
            'enc_mode' => $request->string('enc_mode'),
        ]);

        return response()->json(['data' => new DocumentResource($document)], 201);
    }

    /**
     * FR-VAULT-02: Danh sách tài liệu của Owner hiện tại (tên đã giải mã ở trình duyệt).
     */
    public function index(): JsonResponse
    {
        $documents = Document::query()
            ->where('owner_id', auth()->id())
            ->whereNull('deleted_at')
            ->latest()
            ->get();

        return response()->json(['data' => DocumentResource::collection($documents)]);
    }

    /**
     * FR-VAULT-03: Tải bản mã của một tài liệu (giải mã ở trình duyệt).
     */
    public function ciphertext(Request $request, Document $document): StreamedResponse|JsonResponse
    {
        Gate::authorize('download', $document);

        $disk = config('trustgate.grant_bundle_disk');

        if (! Storage::disk($disk)->exists($document->ciphertext_path)) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Không tìm thấy bản mã của tài liệu.',
                ],
            ], 404);
        }

        return Storage::disk($disk)->download($document->ciphertext_path, 'document.bin');
    }

    /**
     * FR-VAULT-04: Xoá tài liệu (soft delete). Grant đang chạy vẫn giữ bundle.
     */
    public function destroy(Request $request, Document $document): JsonResponse
    {
        Gate::authorize('delete', $document);

        $document->delete();

        return response()->json([
            'data' => [
                'id' => $document->getKey(),
                'status' => 'deleted',
            ],
        ]);
    }
}
