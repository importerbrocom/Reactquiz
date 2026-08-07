<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * The single JSON envelope for every API response, success or failure.
 *
 *   { "success": bool, "message": string, "data": mixed|null,
 *     "errors": object|null, "meta": object|null }
 *
 * Nothing else in the application should construct a top-level JSON body, so that
 * clients (web + React Native) can rely on one shape everywhere.
 */
final class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
        ?array $meta = null,
        array $headers = [],
    ): JsonResponse {
        return response()->json(array_filter([
            'success' => true,
            'message' => $message,
            'data' => self::resolve($data),
            'errors' => null,
            'meta' => $meta,
        ], static fn (string $key): bool => $key !== 'meta' || $meta !== null, ARRAY_FILTER_USE_KEY), $status, $headers);
    }

    public static function created(mixed $data = null, string $message = 'Created successfully.'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function accepted(mixed $data = null, string $message = 'Accepted for processing.'): JsonResponse
    {
        return self::success($data, $message, 202);
    }

    public static function noContent(string $message = 'Done.'): JsonResponse
    {
        return self::success(null, $message, 200);
    }

    /**
     * Answer-evaluation and auth responses must never be stored by a proxy, a CDN
     * or the service worker, so they always carry no-store.
     */
    public static function sensitive(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return self::success($data, $message, $status, null, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }

    public static function error(
        string $message = 'Something went wrong.',
        int $status = 400,
        ?string $code = null,
        ?array $errors = null,
        ?array $meta = null,
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors ?? ($code !== null ? ['code' => $code] : null),
        ];

        if ($code !== null && $errors !== null && ! isset($errors['code'])) {
            $payload['errors'] = ['code' => $code] + $errors;
        }

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function validationError(array $errors, string $message = 'The given data was invalid.'): JsonResponse
    {
        return self::error($message, 422, 'VALIDATION_FAILED', $errors);
    }

    /** Offset pagination — only where a total page count is genuinely useful. */
    public static function paginated(
        Paginator|ResourceCollection $paginator,
        string $message = 'OK',
        array $extraMeta = [],
    ): JsonResponse {
        $p = $paginator instanceof ResourceCollection ? $paginator->resource : $paginator;

        return self::success(
            self::resolve($paginator instanceof ResourceCollection ? $paginator : $p->items()),
            $message,
            200,
            array_merge([
                'pagination' => [
                    'current_page' => $p->currentPage(),
                    'per_page' => $p->perPage(),
                    'total' => method_exists($p, 'total') ? $p->total() : null,
                    'last_page' => method_exists($p, 'lastPage') ? $p->lastPage() : null,
                ],
            ], $extraMeta),
        );
    }

    /** Cursor pagination — the default for large or fast-moving lists. */
    public static function cursorPaginated(
        CursorPaginator|ResourceCollection $paginator,
        string $message = 'OK',
        array $extraMeta = [],
    ): JsonResponse {
        $p = $paginator instanceof ResourceCollection ? $paginator->resource : $paginator;

        return self::success(
            self::resolve($paginator instanceof ResourceCollection ? $paginator : $p->items()),
            $message,
            200,
            array_merge([
                'cursor' => [
                    'next' => $p->nextCursor()?->encode(),
                    'prev' => $p->previousCursor()?->encode(),
                    'per_page' => $p->perPage(),
                ],
            ], $extraMeta),
        );
    }

    private static function resolve(mixed $data): mixed
    {
        // ResourceCollection extends JsonResource, so this covers both.
        if ($data instanceof JsonResource) {
            return $data->resolve();
        }

        return $data;
    }
}
