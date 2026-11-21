<?php

declare(strict_types=1);

namespace App\Actions\Nightwatch;

use App\Enums\Alerts\AdminAlertKey;
use App\Models\NightwatchIssue;
use App\Services\Alerts\AdminAlertNotifier;

/**
 * Records a Nightwatch webhook: an opened issue is upserted by its id and
 * counted for the burst alert, a resolved one is marked resolved. Any other
 * event is ignored.
 */
class RecordNightwatchEvent
{
    public function __construct(private readonly AdminAlertNotifier $notifier) {}

    /**
     * @param  array<string, mixed>  $payload  the webhook's `payload` object
     */
    public function execute(string $event, array $payload): void
    {
        if ($event === 'issue.opened') {
            $this->opened($payload);

            return;
        }

        if ($event === 'issue.resolved') {
            $this->resolved($payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function opened(array $payload): void
    {
        $issue = (array) data_get($payload, 'issue', []);

        NightwatchIssue::query()->updateOrCreate(
            ['issue_id' => (string) data_get($issue, 'id')],
            [
                'ref' => data_get($issue, 'ref'),
                'type' => data_get($issue, 'type'),
                'title' => (string) data_get($issue, 'title', ''),
                'status' => data_get($issue, 'status'),
                'priority' => data_get($issue, 'priority'),
                'url' => data_get($issue, 'url'),
                'environment' => $this->environment($issue, $payload),
                'details' => $issue,
                'opened_at' => now(),
                'resolved_at' => null,
            ],
        );

        $threshold = (int) config('rxdesk.alerts.nightwatch.burst_threshold');
        $count = NightwatchIssue::query()
            ->where('opened_at', '>=', now()->subHour())
            ->count();

        if ($threshold > 0 && $count >= $threshold) {
            $this->notifier->raise(AdminAlertKey::NightwatchBurst, ['count' => $count]);
        }
    }

    /**
     * Nightwatch sends the environment as an object beside the issue, but an
     * older payload may carry a plain string.
     *
     * @param  array<string, mixed>  $issue
     * @param  array<string, mixed>  $payload
     */
    private function environment(array $issue, array $payload): ?string
    {
        $environment = data_get($issue, 'environment', data_get($payload, 'environment'));

        if (is_string($environment)) {
            return $environment;
        }

        if (is_array($environment)) {
            $name = data_get($environment, 'name');

            return is_string($name) ? $name : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolved(array $payload): void
    {
        $issue = (array) data_get($payload, 'issue', []);

        $record = NightwatchIssue::query()
            ->where('issue_id', (string) data_get($issue, 'id'))
            ->first();

        if ($record === null) {
            return;
        }

        $record->forceFill([
            'status' => data_get($issue, 'status', 'resolved'),
            'resolved_at' => now(),
        ])->save();
    }
}
