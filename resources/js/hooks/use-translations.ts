import { useCallback } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';

/**
 * Drop-in replacement for `useLaravelReactI18n()` that guarantees every
 * replacement value passed to `t()` or `tChoice()` is a safe string before
 * delegating to the underlying library.
 *
 * WHY: `laravel-react-i18n/dist/utils/replacer.js` calls `value.toString()`
 * on every replacement without a null guard. When a draft field is null (e.g.
 * `section.name`, `line.quantity`, `selling_price`) and `babel-plugin-react-
 * compiler` eagerly pre-computes a `t(key, {x: null})` call, the page crashes
 * with "Cannot read properties of null (reading 'toString')".
 *
 * USAGE: Replace every `import { useLaravelReactI18n }` in recipe components
 * with `import { useTranslations }` and call it the same way:
 *
 *   const { t, tChoice, ...rest } = useTranslations();
 */
export function useTranslations() {
    const { t: rawT, tChoice: rawTChoice, ...rest } = useLaravelReactI18n();

    /**
     * Coerce every replacement value to a safe string.
     * null / undefined → ''
     * number / boolean / string → String(value)
     */
    function sanitize(replacements: Record<string, unknown>): Record<string, string> {
        const safe: Record<string, string> = {};
        for (const [key, value] of Object.entries(replacements)) {
            safe[key] = value == null ? '' : String(value);
        }
        return safe;
    }

    const t = useCallback(
        (key: string, replacements: Record<string, unknown> = {}): string => {
            return rawT(key, sanitize(replacements));
        },
        [rawT],
    );

    const tChoice = useCallback(
        (key: string, number: number, replacements: Record<string, unknown> = {}): string => {
            return rawTChoice(key, number, sanitize(replacements));
        },
        [rawTChoice],
    );

    return { t, tChoice, ...rest };
}
