<?php

declare(strict_types=1);

namespace App\Services\Operator;

use App\Actions\Audit\RecordAuditEntry;
use App\Models\Operator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of the Operator. Creating a row ends the current one at the
 * new row's `effective_from`; the transaction and its row lock keep at most
 * one row current. Both writes clear the cached current row after the commit
 * and record an audit entry (`operator.create` / `operator.update`).
 */
class OperatorCatalog
{
    /**
     * The fields a write may set. `ended_at` is never accepted: only the
     * service ends a row, and it does so inside its own transaction.
     *
     * @var list<string>
     */
    private const array FIELDS = [
        'legal_name_th',
        'legal_name_latin',
        'address_th',
        'address_latin',
        'tax_id',
        'vat_id',
        'contact_email',
        'phone',
        'effective_from',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, string $actor): Operator
    {
        $this->ensureEffectiveFromIsNotInTheFuture($attributes);
        $effectiveFrom = $this->effectiveFrom($attributes);

        [$operator, $before] = DB::transaction(function () use ($attributes, $effectiveFrom): array {
            $current = Operator::query()
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->get();

            $before = $current->first()?->toArray() ?? [];

            foreach ($current as $row) {
                $row->update(['ended_at' => $effectiveFrom]);
            }

            $operator = Operator::create([
                ...Arr::only($attributes, self::FIELDS),
                'effective_from' => $effectiveFrom,
            ]);

            return [$operator, $before];
        });

        Operator::forgetCurrent();
        RecordAuditEntry::execute($actor, 'operator.create', $operator, $before, $operator->toArray());

        return $operator;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateCurrent(array $attributes, string $actor): Operator
    {
        $this->ensureEffectiveFromIsNotInTheFuture($attributes);

        [$operator, $before] = DB::transaction(function () use ($attributes): array {
            $operator = Operator::query()
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($operator === null) {
                throw ValidationException::withMessages([
                    'operator' => 'No Operator is recorded.',
                ]);
            }

            $before = $operator->toArray();
            $operator->update(Arr::only($attributes, self::FIELDS));

            return [$operator, $before];
        });

        Operator::forgetCurrent();
        RecordAuditEntry::execute($actor, 'operator.update', $operator, $before, $operator->toArray());

        return $operator;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ensureEffectiveFromIsNotInTheFuture(array $attributes): void
    {
        $effectiveFrom = data_get($attributes, 'effective_from');

        if (blank($effectiveFrom)) {
            return;
        }

        if (Carbon::parse($effectiveFrom)->isAfter(now())) {
            throw ValidationException::withMessages([
                'effective_from' => 'The effective date cannot be in the future.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function effectiveFrom(array $attributes): mixed
    {
        $effectiveFrom = data_get($attributes, 'effective_from');

        if (blank($effectiveFrom)) {
            return now();
        }

        return $effectiveFrom;
    }
}
