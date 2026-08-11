<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendCampaignJob;
use App\Models\NotificationCampaign;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationCampaignController extends Controller
{
    public function index(): JsonResponse
    {
        $campaigns = NotificationCampaign::orderByDesc('created_at')->get();

        return ApiResponse::success($campaigns);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:160',
            'body' => 'required|string|max:500',
            'action_url' => 'nullable|url|max:255',
            'audience' => 'required|in:all,programme,level,inactive,custom',
            'audience_filter' => 'nullable|array',
            'scheduled_for' => 'nullable|date|after:now',
        ]);

        $campaign = NotificationCampaign::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'status' => $validated['scheduled_for'] ? 'scheduled' : 'draft',
        ]);

        return ApiResponse::success($campaign, 'Campaign created.', Response::HTTP_CREATED);
    }

    public function send(int $id): JsonResponse
    {
        $campaign = NotificationCampaign::findOrFail($id);

        if (! in_array($campaign->status, ['draft', 'scheduled'])) {
            return ApiResponse::error('Campaign cannot be sent in current state.', Response::HTTP_CONFLICT);
        }

        SendCampaignJob::dispatch($campaign);

        return ApiResponse::success(null, 'Campaign queued for delivery.', Response::HTTP_ACCEPTED);
    }

    public function cancel(int $id): JsonResponse
    {
        $campaign = NotificationCampaign::findOrFail($id);

        if ($campaign->status === 'sent') {
            return ApiResponse::error('Cannot cancel a sent campaign.', Response::HTTP_CONFLICT);
        }

        $campaign->update(['status' => 'cancelled']);

        return ApiResponse::success(null, 'Campaign cancelled.');
    }

    public function stats(int $id): JsonResponse
    {
        $campaign = NotificationCampaign::findOrFail($id);

        return ApiResponse::success([
            'target_count' => $campaign->target_count,
            'sent_count' => $campaign->sent_count,
            'failed_count' => $campaign->failed_count,
            'status' => $campaign->status,
            'batch_id' => $campaign->batch_id,
        ]);
    }
}
