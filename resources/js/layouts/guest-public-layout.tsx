import { Link, usePage } from '@inertiajs/react';
import { useTranslations } from '@/hooks/use-translations';
import AppLogo from '@/components/app-logo';
import { index as libraryIndex } from '@/routes/library';

interface GuestPublicLayoutProps {
    children: React.ReactNode;
}

/**
 * Minimal guest-safe layout for public pages (library index and library show).
 * Renders a simple top bar with the app logo, a Library nav link, and an
 * optional Sign in link shown only to unauthenticated visitors.
 */
export default function GuestPublicLayout({ children }: GuestPublicLayoutProps) {
    const { t } = useTranslations();
    // auth.user is null for unauthenticated guests
    const { auth } = usePage().props as unknown as { auth: { user: unknown } };
    const isGuest = auth.user === null;

    return (
        <div className="min-h-screen bg-background">
            {/* Top bar */}
            <header className="border-b border-border">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
                    {/* Logo */}
                    <Link href={libraryIndex().url} className="flex items-center">
                        <AppLogo />
                    </Link>

                    {/* Nav links */}
                    <nav className="flex items-center gap-4">
                        <Link
                            href={libraryIndex().url}
                            className="text-sm font-medium text-foreground hover:underline"
                        >
                            {t('app.library.nav')}
                        </Link>

                        {isGuest && (
                            <Link
                                href="/login"
                                className="text-sm font-medium text-foreground hover:underline"
                            >
                                {t('app.library.sign_in')}
                            </Link>
                        )}
                    </nav>
                </div>
            </header>

            {/* Page content */}
            <main className="mx-auto max-w-7xl px-4 py-6">
                {children}
            </main>
        </div>
    );
}
