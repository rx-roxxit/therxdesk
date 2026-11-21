<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Enums\Alerts\AdminAlertKey;
use App\Enums\Alerts\AdminAlertSeverity;
use App\Enums\Alerts\AlertLevel;
use App\Mail\AdminAlert;
use App\Models\AdminAlert as AdminAlertModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Raises, clears and digests the System admin's email alerts. One
 * `admin_alerts` row per condition is the state machine: an alert is open or
 * closed, and the severity comes from the key.
 */
class AdminAlertNotifier
{
    /**
     * Open the alert (or keep it open) and notify: URGENT only on the first
     * raise, TODAY only when its quiet period has passed.
     *
     * @param  array<string, mixed>  $context
     */
    public function raise(AdminAlertKey $key, array $context = []): void
    {
        $this->locked($key, function () use ($key, $context): void {
            $alert = AdminAlertModel::query()->firstOrNew(['key' => $key->value]);
            $wasOpen = (bool) $alert->is_open;
            $now = now();

            if (! $wasOpen) {
                $alert->opened_at = $now;
                $alert->resolved_at = null;
                $alert->is_open = true;
            }

            $alert->last_raised_at = $now;
            $alert->context = $context;
            $alert->save();

            if ($key->severity() === AdminAlertSeverity::Urgent) {
                if (! $wasOpen) {
                    $this->send(AlertLevel::Urgent, $key, $context);
                }

                return;
            }

            if ($this->withinQuietPeriod($alert->last_notified_at, $now)) {
                return;
            }

            $this->send(AlertLevel::Today, $key, $context);
            $alert->forceFill(['last_notified_at' => $now])->save();
        });
    }

    /**
     * Close the alert. URGENT conditions also send a RESOLVED mail; TODAY
     * conditions close silently and surface in the digest.
     */
    public function clear(AdminAlertKey $key): void
    {
        $this->locked($key, function () use ($key): void {
            $alert = AdminAlertModel::query()->where('key', $key->value)->first();

            if ($alert === null) {
                return;
            }

            if (! $alert->is_open) {
                return;
            }

            $alert->is_open = false;
            $alert->resolved_at = now();
            $alert->save();

            if ($key->severity() === AdminAlertSeverity::Urgent) {
                $this->send(AlertLevel::Resolved, $key, $alert->context ?? [], isResolved: true);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $items
     */
    public function sendDigest(array $items): void
    {
        $this->send(AlertLevel::Digest, null, [], $items);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $items
     */
    private function send(AlertLevel $level, ?AdminAlertKey $key, array $context, array $items = [], bool $isResolved = false): void
    {
        $recipient = (string) config('rxdesk.alerts.recipient');

        Mail::to($recipient)
            ->locale((string) config('app.locale'))
            ->send(new AdminAlert($level, $key, $context, $items, $isResolved));
    }

    private function withinQuietPeriod(mixed $lastNotifiedAt, mixed $now): bool
    {
        if ($lastNotifiedAt === null) {
            return false;
        }

        $quietUntil = $now->copy()->subHours((int) config('rxdesk.alerts.today_dedupe_hours'));

        return $lastNotifiedAt->greaterThan($quietUntil);
    }

    /**
     * Runs the callback under a per-key lock so two workers cannot open or
     * notify the same alert at once.
     */
    private function locked(AdminAlertKey $key, callable $callback): void
    {
        Cache::lock("rxdesk:alert:{$key->value}", 10)->block(5, $callback);
    }
}
