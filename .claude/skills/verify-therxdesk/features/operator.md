# Operator

The legal entity that sells The RX Desk. One current row, an audited history, a CLI and the internal API the admin
app writes it with. The row feeds one line in every email footer and the customer-facing fields on the website;
nothing is shown inside the signed-in app. Selling mode only.

The current row is cached under `rxdesk:operator:current`; every write goes through
`App\Services\Operator\OperatorCatalog`, which ends the previous row at the new `effective_from`, clears the cache
after the commit and records `operator.create` / `operator.update` in the audit log. `effective_from` may never be
in the future.

## CLI

`show` prints the current row and, when an earlier row exists, the history. `set` creates the first row or edits
the current one with only the options given. `--vat-id=` and `--phone=` with an empty value clear that field;
omitting them keeps the stored value.

```sh
compose run --rm -T -u app app php artisan rxdesk:operator show
compose run --rm -T -u app app php artisan rxdesk:operator set \
    --legal-name-th='บริษัท ทดสอบ จำกัด' \
    --legal-name-latin='Test Operator Co., Ltd.' \
    --address-th='123 ถนนทดสอบ กรุงเทพมหานคร 10110' \
    --address-latin='123 Test Road, Bangkok 10110' \
    --tax-id=1234567890123 \
    --contact-email=billing@example.com
```

`show` on an empty install prints `No Operator is recorded.` A future `--effective-from` prints the validation
error and exits with failure, writing nothing.

## Internal API

- `GET /internal/v1/operator` (admin): `{ "current": {...}|null, "history": [...] }`. A row has `id`,
  `legal_name_th`, `legal_name_latin`, `address_th`, `address_latin`, `tax_id`, `vat_id`, `contact_email`, `phone`,
  `effective_from`, `ended_at`, `is_current` and timestamps.
- `POST /internal/v1/operator` (admin): the required fields plus optional `vat_id`, `phone` and `effective_from`.
  Creates the first row, or ends the current row at `effective_from` (default now) and creates the next. `201` with
  the overview. A future `effective_from` or a `tax_id` that is not 13 digits answers `422`.
- `PATCH /internal/v1/operator` (admin): any of the same fields except `ended_at` (`ended_at` is never accepted).
  Edits the current row; `200` with the overview. No current row answers `422`.
- `GET /internal/v1/site` now carries `operator` (or `null`): `legal_name_latin`, `address_latin`, `legal_name_th`,
  `address_th`, `tax_id`, `vat_id`, `contact_email`, `effective_from`.
- The site token answers `404` on every `/operator` endpoint.

```sh
$V/internal.sh GET /operator --token admin
$V/internal.sh POST /operator --token admin --body '{"legal_name_th":"บริษัท ทดสอบ จำกัด","legal_name_latin":"Test Operator Co., Ltd.","address_th":"123 ถนนทดสอบ กรุงเทพมหานคร 10110","address_latin":"123 Test Road, Bangkok 10110","tax_id":"1234567890123","contact_email":"billing@example.com"}'
$V/internal.sh PATCH /operator --token admin --body '{"phone":"555-0100"}'
$V/internal.sh GET /site --token site
```

## Proof checklist

Run from the dev machine with the stack up. `$V` is `.claude/skills/verify-therxdesk/scripts`.

1. `$V/selling.sh up`. `compose run --rm -T -u app app php artisan rxdesk:operator show` prints
   `No Operator is recorded.`
2. `rxdesk:operator set` with the fields above creates the row. `show` prints the field table and `GET /operator`
   answers `HTTP 200` with `current` filled and `history` empty. `GET /site --token site` carries
   `operator.legal_name_latin`.
3. `PATCH /operator` changes only what it was sent; `GET /internal/v1/audit-log` shows `operator.update` with the
   admin email and the before/after values. A second `POST` ends the previous row: `/operator` answers `current`
   as the new row and one `history` row, and `show` prints the history table.
4. A future `effective_from` (CLI or API) fails and writes nothing.
5. Real email proof. Create an Operator with the Latin name and address the footer uses, clear the inbox, then
   force a capacity alert with the threshold trick `features/admin-alerts.md` documents:

   ```sh
   $V/mail.sh clear
   compose run --rm -T -u app \
       -e RXDESK_CAPACITY_LOAD=0.001 \
       -e RXDESK_CAPACITY_DISK_FREE=0GB \
       -e RXDESK_CAPACITY_DISK_URGENT_MARGIN=0GB \
       -e RXDESK_DISK_GUARD_THRESHOLD=100TB \
       app php artisan rxdesk:capacity
   $V/mail.sh wait --to admin@therxdesk.com --subject '[TODAY]'
   $V/mail.sh show <id>
   ```

   The body ends with `The RX Desk is operated by Test Operator Co., Ltd., 123 Test Road, Bangkok 10110.`
6. Repeat with no Operator. Delete the row, clear the alert state and the inbox, and force the alert again:

   ```sh
   compose run --rm -T -u app app php artisan tinker --execute='App\Models\Operator::query()->delete(); App\Models\Operator::forgetCurrent(); App\Models\AdminAlert::query()->delete();'
   $V/mail.sh clear
   compose run --rm -T -u app \
       -e RXDESK_CAPACITY_LOAD=0.001 \
       -e RXDESK_CAPACITY_DISK_FREE=0GB \
       -e RXDESK_CAPACITY_DISK_URGENT_MARGIN=0GB \
       -e RXDESK_DISK_GUARD_THRESHOLD=100TB \
       app php artisan rxdesk:capacity
   $V/mail.sh wait --to admin@therxdesk.com --subject '[TODAY]'
   $V/mail.sh show <id>
   ```

   The operator sentence is absent, and the footer carries no empty line where it was.
7. `$V/selling.sh down`.
