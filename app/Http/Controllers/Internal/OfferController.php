<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Middleware\Internal\AuthenticateInternalApi;
use App\Http\Requests\Internal\StoreOfferRequest;
use App\Http\Requests\Internal\UpdateOfferRequest;
use App\Http\Resources\Internal\OfferAdminResource;
use App\Models\Offer;
use App\Services\Catalog\OfferCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class OfferController
{
    public function __construct(private readonly OfferCatalog $offers) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $term = trim((string) $request->query('search', ''));
        $ended = $this->endedFilter($request);
        $reach = $request->query('reach');

        $offers = Offer::query()
            ->withCount('redemptions')
            ->when($term !== '', function (Builder $query) use ($term): void {
                $query->where(function (Builder $query) use ($term): void {
                    $query->whereLike('name', "%{$term}%")
                        ->orWhereLike('slug', "%{$term}%");
                });
            })
            ->when(in_array($reach, ['general', 'link'], true), fn (Builder $query) => $query->where('reach', $reach))
            ->when($ended === true, fn (Builder $query) => $query->whereNotNull('ends_at')->wherePast('ends_at'))
            ->when($ended === false, fn (Builder $query) => $query->where(fn (Builder $query): Builder => $query->whereNull('ends_at')->orWhereFuture('ends_at')))
            ->orderByDesc('starts_at')
            ->orderByDesc('created_at')
            ->paginate((int) config('app.pagination.default'));

        return OfferAdminResource::collection($offers);
    }

    public function store(StoreOfferRequest $request): JsonResponse
    {
        $offer = $this->offers->create($request->validated(), AuthenticateInternalApi::actor($request));

        return (new OfferAdminResource($offer->loadCount('redemptions')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateOfferRequest $request, string $offer): OfferAdminResource
    {
        $offer = $this->offers->update($this->offer($offer), $request->validated(), AuthenticateInternalApi::actor($request));

        return new OfferAdminResource($offer->loadCount('redemptions'));
    }

    public function end(Request $request, string $offer): OfferAdminResource
    {
        $offer = $this->offers->end($this->offer($offer), AuthenticateInternalApi::actor($request));

        return new OfferAdminResource($offer->loadCount('redemptions'));
    }

    /**
     * `?ended=1` lists offers whose end has come, `0` the running ones, and
     * anything else (the default) lists both.
     */
    private function endedFilter(Request $request): ?bool
    {
        $value = $request->query('ended');

        if (in_array($value, ['1', 'true'], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false'], true)) {
            return false;
        }

        return null;
    }

    /**
     * The internal group has no SubstituteBindings middleware, so the
     * controller resolves the id itself.
     */
    private function offer(string $id): Offer
    {
        return Offer::query()->findOrFail($id);
    }
}
