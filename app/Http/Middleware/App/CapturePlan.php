<?php

declare(strict_types=1);

namespace App\Http\Middleware\App;

use App\Enums\Billing\Interval;
use App\Models\Plan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the Plan and interval a guest picked from a `/register?plan=…&interval=…`
 * link until they reach the plan step. A value that names nothing real is
 * dropped silently and never replaces one already kept.
 */
class CapturePlan
{
    public const string PLAN_SESSION_KEY = 'pending_plan';

    public const string INTERVAL_SESSION_KEY = 'pending_interval';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (blank($request->user())) {
            $this->capturePlan($request);
            $this->captureInterval($request);
        }

        return $next($request);
    }

    private function capturePlan(Request $request): void
    {
        $slug = $this->queryValue($request, 'plan');

        if (blank($slug)) {
            return;
        }

        if (blank($this->activePlan($slug))) {
            return;
        }

        $request->session()->put(self::PLAN_SESSION_KEY, $slug);
    }

    private function captureInterval(Request $request): void
    {
        $value = $this->queryValue($request, 'interval');

        if (blank($value)) {
            return;
        }

        if (blank(Interval::tryFrom($value))) {
            return;
        }

        $request->session()->put(self::INTERVAL_SESSION_KEY, $value);
    }

    /**
     * The active Plan with exactly this slug. The rows are read and matched
     * here, where both engines agree, because MySQL compares without case.
     */
    private function activePlan(string $slug): ?Plan
    {
        return Plan::query()
            ->active()
            ->get()
            ->firstWhere('slug', $slug);
    }

    /**
     * The query value when it is a plain non-empty string; `?plan[]=` and
     * `?plan=` are ignored instead of failing.
     */
    private function queryValue(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && filled($value) ? $value : null;
    }
}
