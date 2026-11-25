<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Account\CreateReviewerAccount;
use App\Actions\Billing\ShowAccountBalance;
use App\Enums\Billing\RefundDenial;
use App\Http\Middleware\Internal\AuthenticateInternalApi;
use App\Http\Requests\Internal\StoreAccountRequest;
use App\Http\Resources\Internal\CustomerDetailResource;
use App\Http\Resources\Internal\CustomerResource;
use App\Http\Resources\Internal\ReviewerAccountResource;
use App\Models\Account;
use App\Models\Plan;
use App\Services\Storage\AccountStorage;
use App\Support\Billing\RefundEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice as StripeInvoice;
use Symfony\Component\HttpFoundation\Response;

class AccountController
{
    public function __construct(private readonly AccountStorage $storage) {}

    public function store(StoreAccountRequest $request, CreateReviewerAccount $create): JsonResponse
    {
        $plan = Plan::query()->findOrFail($request->validated('plan_id'));

        $account = $create->execute(
            (string) $request->validated('email'),
            (string) $request->validated('password'),
            $plan,
            $this->complimentaryUntil($request->validated('complimentary_until')),
            $request->boolean('review_access'),
            AuthenticateInternalApi::actor($request),
        );

        return (new ReviewerAccountResource($account))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $term = trim((string) $request->query('search', ''));

        $accounts = Account::query()
            ->whereNotNull('owner_id')
            ->with(['owner', 'plan', 'subscriptions'])
            ->withCount('users')
            ->when($term !== '', function (Builder $query) use ($term): void {
                $query->whereHas('users', fn (Builder $users): Builder => $users->whereLike('email', "%{$term}%"));
            })
            ->latest()
            ->paginate((int) config('app.pagination.default'));

        return CustomerResource::collection($accounts);
    }

    public function show(string $account): CustomerDetailResource
    {
        $account = Account::query()
            ->with(['owner', 'plan.prices', 'subscriptions', 'lifecycleEmails'])
            ->withCount('users')
            ->findOrFail($account);

        $recentPosts = $account->posts()
            ->latest('posts.created_at')
            ->limit(10)
            ->get([
                'posts.id',
                'posts.platform',
                'posts.status',
                'posts.publish_status',
                'posts.error_message',
                'posts.scheduled_at',
                'posts.published_at',
                'posts.created_at',
            ]);

        [$accountBalance, $refund] = $this->billingFacts($account);

        return new CustomerDetailResource([
            'account' => $account,
            'recent_posts' => $recentPosts,
            'storage' => $this->storage->usage($account),
            'account_balance' => $accountBalance,
            'refund' => $refund,
        ]);
    }

    /**
     * The Stripe facts the billing screen needs, read here so the admin app
     * never calls Stripe. A Stripe failure leaves both empty instead of
     * failing the whole detail read.
     *
     * @return array{0: int|null, 1: array{allowed: bool, reason: string|null, charge: array<string, mixed>|null}}
     */
    private function billingFacts(Account $account): array
    {
        try {
            $balance = ShowAccountBalance::creditCents($account);
            $eligibility = RefundEligibility::for($account);
        } catch (ApiErrorException) {
            return [null, [
                'allowed' => false,
                'reason' => RefundDenial::StripeUnavailable->value,
                'charge' => null,
            ]];
        }

        return [$balance, $this->refundBlock($eligibility)];
    }

    /**
     * @param  array{
     *     denial: RefundDenial|null,
     *     invoice: StripeInvoice|null,
     *     amount_paid: int,
     *     refundable: bool,
     *     currency: string|null,
     *     paid_at: CarbonImmutable|null,
     *     payment: array{payment_intent: string}|array{charge: string}|null
     * }  $eligibility
     * @return array{allowed: bool, reason: string|null, charge: array<string, mixed>|null}
     */
    private function refundBlock(array $eligibility): array
    {
        if ($eligibility['denial'] !== null) {
            return [
                'allowed' => false,
                'reason' => $eligibility['denial']->value,
                'charge' => null,
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'charge' => [
                'invoice_id' => $eligibility['invoice']->id,
                'amount_paid' => $eligibility['amount_paid'],
                'refundable' => $eligibility['refundable'],
                'currency' => $eligibility['currency'],
                'paid_at' => $eligibility['paid_at']?->toIso8601String(),
            ],
        ];
    }

    private function complimentaryUntil(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse((string) $value);
    }
}
