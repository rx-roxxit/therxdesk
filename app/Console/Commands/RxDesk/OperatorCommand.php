<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use App\Models\Operator;
use App\Services\Operator\OperatorCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The System admin's way to see the Operator, or to create and change it.
 * Everything writes through `OperatorCatalog`, so a future `effective_from`
 * is refused here exactly as the internal API refuses it.
 */
class OperatorCommand extends Command
{
    /**
     * @var list<string>
     */
    private const array ACTIONS = ['show', 'set'];

    /**
     * @var array<string, string>
     */
    private const array OPTION_FIELDS = [
        'legal-name-th' => 'legal_name_th',
        'legal-name-latin' => 'legal_name_latin',
        'address-th' => 'address_th',
        'address-latin' => 'address_latin',
        'tax-id' => 'tax_id',
        'vat-id' => 'vat_id',
        'contact-email' => 'contact_email',
        'phone' => 'phone',
        'effective-from' => 'effective_from',
    ];

    protected $signature = 'rxdesk:operator
        {action=show : show or set}
        {--legal-name-th= : The legal name in Thai}
        {--legal-name-latin= : The legal name in Latin script}
        {--address-th= : The address in Thai}
        {--address-latin= : The address in Latin script}
        {--tax-id= : The 13-digit tax id}
        {--vat-id= : The VAT id; pass an empty value to clear it}
        {--contact-email= : The contact email}
        {--phone= : The phone; pass an empty value to clear it}
        {--effective-from= : When it took effect, ISO 8601}';

    protected $description = 'Show the Operator, or create and change it';

    public function handle(OperatorCatalog $operators): int
    {
        $action = (string) $this->argument('action');

        if (! in_array($action, self::ACTIONS, true)) {
            $this->error("Unknown action \"{$action}\". Use show or set.");

            return self::FAILURE;
        }

        if ($action === 'set') {
            return $this->set($operators);
        }

        return $this->show();
    }

    private function show(): int
    {
        $current = Operator::current();

        if ($current === null) {
            $this->line('No Operator is recorded.');
        } else {
            $this->table(['Field', 'Value'], $this->rows($current));
        }

        $history = Operator::history();

        if ($history->isNotEmpty()) {
            $this->newLine();
            $this->line('History');
            $this->table(
                ['Legal name (Latin)', 'Effective from', 'Ended at'],
                $history->map(fn (Operator $operator): array => [
                    $operator->legal_name_latin,
                    $operator->effective_from?->toDateTimeString(),
                    $operator->ended_at?->toDateTimeString(),
                ])->all(),
            );
        }

        return self::SUCCESS;
    }

    private function set(OperatorCatalog $operators): int
    {
        $attributes = $this->attributes();

        if ($attributes === []) {
            $this->error('Pass at least one field to set.');

            return self::FAILURE;
        }

        $current = Operator::current();
        $validator = Validator::make($attributes, $current === null ? $this->createRules() : $this->updateRules());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            if ($current === null) {
                $operator = $operators->create($attributes, 'cli');
                $this->info("Operator created: {$operator->legal_name_latin}");

                return self::SUCCESS;
            }

            $operator = $operators->updateCurrent($attributes, 'cli');
            $this->info("Operator updated: {$operator->legal_name_latin}");
        } catch (ValidationException $exception) {
            foreach (Arr::flatten($exception->errors()) as $error) {
                $this->error((string) $error);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Only the options the caller passed. An empty value becomes null: it
     * clears `vat_id` and `phone`, and fails validation for the required
     * fields.
     *
     * @return array<string, string|null>
     */
    private function attributes(): array
    {
        $attributes = [];

        foreach (self::OPTION_FIELDS as $option => $field) {
            $value = $this->option($option);

            if ($value === null) {
                continue;
            }

            $attributes[$field] = $value === '' ? null : $value;
        }

        return $attributes;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function rows(Operator $operator): array
    {
        return [
            ['Legal name (Thai)', $operator->legal_name_th],
            ['Legal name (Latin)', $operator->legal_name_latin],
            ['Address (Thai)', $operator->address_th],
            ['Address (Latin)', $operator->address_latin],
            ['Tax id', $operator->tax_id],
            ['VAT id', $operator->vat_id ?? '—'],
            ['Contact email', $operator->contact_email],
            ['Phone', $operator->phone ?? '—'],
            ['Effective from', $operator->effective_from?->toDateTimeString() ?? '—'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function createRules(): array
    {
        return [
            'legal_name_th' => ['required', 'string', 'max:255'],
            'legal_name_latin' => ['required', 'string', 'max:255'],
            'address_th' => ['required', 'string', 'max:1000'],
            'address_latin' => ['required', 'string', 'max:1000'],
            'tax_id' => ['required', 'digits:13'],
            'vat_id' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'effective_from' => ['sometimes', 'date', 'before_or_equal:now'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function updateRules(): array
    {
        return [
            'legal_name_th' => ['sometimes', 'string', 'max:255'],
            'legal_name_latin' => ['sometimes', 'string', 'max:255'],
            'address_th' => ['sometimes', 'string', 'max:1000'],
            'address_latin' => ['sometimes', 'string', 'max:1000'],
            'tax_id' => ['sometimes', 'digits:13'],
            'vat_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'contact_email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'effective_from' => ['sometimes', 'date', 'before_or_equal:now'],
        ];
    }
}
