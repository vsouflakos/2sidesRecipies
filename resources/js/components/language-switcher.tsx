import { router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { update as localeUpdate } from '@/routes/locale';
import { cn } from '@/lib/utils';

export function LanguageSwitcher() {
    const { t, currentLocale, setLocale } = useLaravelReactI18n();

    const locales = [
        { code: 'en', label: t('app.language.en') },
        { code: 'el', label: t('app.language.el') },
    ] as const;

    function handleSelect(locale: 'en' | 'el') {
        if (locale === currentLocale()) {
            return;
        }

        setLocale(locale);

        router.put(localeUpdate.url(), { locale }, { preserveScroll: true });
    }

    return (
        <div
            role="group"
            aria-label="Language"
            className="flex items-center gap-1 px-2 py-1"
        >
            {locales.map(({ code, label }) => {
                const isActive = currentLocale() === code;

                return (
                    <button
                        key={code}
                        type="button"
                        onClick={() => handleSelect(code)}
                        className={cn(
                            'min-h-[44px] flex-1 rounded-md px-2 text-sm transition-colors duration-150 ease-in-out',
                            isActive
                                ? 'bg-accent font-semibold text-accent-foreground'
                                : 'bg-transparent font-normal text-muted-foreground hover:bg-secondary',
                        )}
                        aria-pressed={isActive}
                    >
                        {label}
                    </button>
                );
            })}
        </div>
    );
}
