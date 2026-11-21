/**
 * Runs `formatMoney()` of `resources/js/lib/money.ts` over `locale:cents`
 * cases and prints the answers as JSON, so `MoneyFormatTest.php` can check
 * them. Node strips the types, so the source is loaded as-is.
 */
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const [sourceFile, ...cases] = process.argv.slice(2);

const { formatMoney } = await import(
    pathToFileURL(path.resolve(sourceFile)).href
);

console.log(
    JSON.stringify(
        cases.map((entry) => {
            const [locale, cents] = entry.split(':');

            return formatMoney(Number(cents), locale);
        }),
    ),
);
