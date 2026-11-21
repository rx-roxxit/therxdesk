/**
 * A price in USD cents, written in the reader's language with two decimals:
 * "$11.90" in English, "11,90 $US" in French, "US$ 11,90" in pt-BR. The
 * currency is always USD, so a locale that also uses the dollar sign never
 * reads the amount as its own currency (pt-BR never shows R$).
 */
export const formatMoney = (cents: number, locale: string): string =>
    new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(cents / 100);
