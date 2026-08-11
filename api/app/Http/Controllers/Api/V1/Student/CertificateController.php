<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CertificateController extends Controller
{
    /**
     * GET /student/certificates — List student's certificates.
     */
    public function index(Request $request): JsonResponse
    {
        $certificates = Certificate::where('user_id', $request->user()->id)
            ->where('status', 'issued')
            ->orderByDesc('issued_at')
            ->get(['uuid', 'serial', 'level_id', 'percentage', 'status', 'issued_at']);

        return ApiResponse::success($certificates);
    }

    /**
     * GET /student/certificates/{uuid}/download — 302 to signed URL.
     */
    public function download(Request $request, string $uuid): JsonResponse|Response
    {
        $certificate = Certificate::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($certificate->status !== 'issued') {
            return ApiResponse::error(
                'Certificate is not ready for download.',
                Response::HTTP_CONFLICT,
            );
        }

        if (! $certificate->storage_path) {
            return ApiResponse::error(
                'Certificate file not found.',
                Response::HTTP_NOT_FOUND,
            );
        }

        $disk = 'local';
        $fullPath = Storage::disk($disk)->path($certificate->storage_path);

        return response()->download($fullPath, "certificate-{$certificate->serial}.pdf");
    }
}
