<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Enums\Post\Status;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['user', 'approvalRequestedBy', 'approver', 'coverOutcome']);

        $requester = $this->approval_requested_by !== null
            ? $this->approvalRequestedBy
            : ($this->status === Status::PendingApproval ? $this->user : null);

        $coverOutcome = $this->coverOutcomePayload();

        return [
            'id' => $this->id,
            'post_group_id' => $this->post_group_id,
            'author' => $this->person($this->user),
            'content' => $this->content,
            'media' => $this->media,
            'cover_outcome' => $this->when($coverOutcome !== null, $coverOutcome),
            'status' => $this->status?->value,
            'schedule_mode' => $this->schedule_mode?->value,
            'scheduled_at' => $this->scheduled_at?->format('Y-m-d H:i:s'),
            'published_at' => $this->published_at?->format('Y-m-d H:i:s'),
            'approval_requested_by' => $this->person($requester),
            'approval_requested_at' => $this->approval_requested_at?->format('Y-m-d H:i:s'),
            'approved_by' => $this->person($this->approver),
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'recurrence' => $this->recurrence_frequency !== null && $this->recurrence_interval !== null ? [
                'interval' => $this->recurrence_interval,
                'frequency' => $this->recurrence_frequency->value,
                'remaining' => $this->recurrence_remaining,
            ] : null,
            'origin' => $this->origin->value,
            'publish_status' => $this->publish_status->value,
            'publish_block_reason' => $this->publish_block_reason?->value,
            'platform' => $this->platform?->value,
            'content_type' => $this->content_type?->value,
            'meta' => $this->meta,
            'platform_url' => $this->platform_url,
            'error_message' => $this->error_message,
            'display_name' => $this->display_name,
            'display_username' => $this->display_username,
            'display_avatar' => $this->display_avatar,
            'social_account' => new SocialAccountResource($this->whenLoaded('socialAccount')),
            'labels' => LabelResource::collection($this->whenLoaded('labels')),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Whether a network took the video's Cover after the post published
     * (YouTube and Facebook): `{outcome: set|failed}`. A video's Cover itself
     * is `media.*.meta.cover_image`. The key is left out while no outcome is
     * recorded (a post without a Cover, or one still trying).
     *
     * @return array{outcome: string}|null
     */
    private function coverOutcomePayload(): ?array
    {
        $record = $this->resource->coverOutcome;

        if ($record === null) {
            return null;
        }

        return ['outcome' => $record->outcome->value];
    }

    /**
     * Only the id and name of a workspace member, never their email or other personal data.
     *
     * @return array{id: string, name: string}|null
     */
    private function person(?User $user): ?array
    {
        return $user === null ? null : [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }
}
