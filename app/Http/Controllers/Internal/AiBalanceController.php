<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Resources\Internal\AiBalanceResource;
use App\Services\Ai\AiBalances;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class AiBalanceController
{
    public function __construct(private readonly AiBalances $balances) {}

    /**
     * `zero=1` keeps the exhausted Accounts, `zero=0` the ones with credits
     * left; absent keeps all. The page size is the configured one, never a
     * request value.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $rows = $this->balances->rows();
        $zero = $request->query('zero');

        if ($zero !== null) {
            $onlyZero = (string) $zero === '1';

            $rows = $rows->filter(fn (array $row): bool => $onlyZero
                ? $row['remaining'] === 0
                : $row['remaining'] > 0);
        }

        $perPage = (int) config('app.pagination.default');
        $page = max(1, (int) $request->query('page', 1));
        $rows = $rows->values();

        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values()->all(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return AiBalanceResource::collection($paginator);
    }
}
