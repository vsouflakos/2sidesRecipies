import { Head, router } from '@inertiajs/react';
import { SearchIcon } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { UserTable } from '@/components/admin/user-table';
import type { AdminUserRow } from '@/components/admin/user-table';
import { Input } from '@/components/ui/input';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { index } from '@/routes/admin/users';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedUsers {
    data: AdminUserRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface UsersPageProps {
    users: PaginatedUsers;
    filters: {
        search: string;
    };
}

export default function UsersPage({ users, filters }: UsersPageProps) {
    const { t } = useLaravelReactI18n();
    useFlashToast();

    const [search, setSearch] = useState(filters.search ?? '');
    const [debounceTimer, setDebounceTimer] = useState<ReturnType<
        typeof setTimeout
    > | null>(null);

    const navigateToPage = useCallback(
        (url: string | null) => {
            if (!url) {
                return;
            }
            router.get(url, search ? { search } : {}, {
                preserveState: true,
                replace: true,
            });
        },
        [search],
    );

    const handleSearchChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>) => {
            const value = e.target.value;
            setSearch(value);

            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }

            const timer = setTimeout(() => {
                router.get(index().url, value ? { search: value } : {}, {
                    preserveState: true,
                    replace: true,
                });
            }, 300);

            setDebounceTimer(timer);
        },
        [debounceTimer],
    );

    const showPagination = users.last_page > 1;

    return (
        <>
            <Head title={t('app.admin.page_heading')} />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <h1 className="text-[28px] leading-tight font-semibold">
                    {t('app.admin.page_heading')}
                </h1>

                {/* Search row */}
                <div className="flex items-center gap-4">
                    <div className="relative max-w-sm flex-1">
                        <SearchIcon className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            placeholder={t('app.admin.search_placeholder')}
                            value={search}
                            onChange={handleSearchChange}
                            className="pl-9"
                        />
                    </div>
                    <p className="ml-auto text-sm text-muted-foreground">
                        {t('app.admin.result_count', {
                            shown: users.data.length,
                            total: users.total,
                        })}
                    </p>
                </div>

                {/* User table */}
                <UserTable users={users.data} />

                {/* Pagination */}
                {showPagination && (
                    <Pagination>
                        <PaginationContent>
                            <PaginationItem>
                                <PaginationPrevious
                                    href="#"
                                    onClick={(e) => {
                                        e.preventDefault();
                                        const prev = users.links.find(
                                            (l) =>
                                                l.label === '&laquo; Previous',
                                        );
                                        navigateToPage(prev?.url ?? null);
                                    }}
                                    aria-disabled={users.current_page === 1}
                                    className={
                                        users.current_page === 1
                                            ? 'pointer-events-none opacity-50'
                                            : ''
                                    }
                                />
                            </PaginationItem>

                            {users.links
                                .filter(
                                    (link) =>
                                        link.label !== '&laquo; Previous' &&
                                        link.label !== 'Next &raquo;',
                                )
                                .map((link, i) => (
                                    <PaginationItem key={i}>
                                        {link.label === '...' ? (
                                            <PaginationEllipsis />
                                        ) : (
                                            <PaginationLink
                                                href="#"
                                                isActive={link.active}
                                                onClick={(e) => {
                                                    e.preventDefault();
                                                    navigateToPage(link.url);
                                                }}
                                            >
                                                {link.label}
                                            </PaginationLink>
                                        )}
                                    </PaginationItem>
                                ))}

                            <PaginationItem>
                                <PaginationNext
                                    href="#"
                                    onClick={(e) => {
                                        e.preventDefault();
                                        const next = users.links.find(
                                            (l) => l.label === 'Next &raquo;',
                                        );
                                        navigateToPage(next?.url ?? null);
                                    }}
                                    aria-disabled={
                                        users.current_page === users.last_page
                                    }
                                    className={
                                        users.current_page === users.last_page
                                            ? 'pointer-events-none opacity-50'
                                            : ''
                                    }
                                />
                            </PaginationItem>
                        </PaginationContent>
                    </Pagination>
                )}
            </div>
        </>
    );
}

UsersPage.layout = {
    breadcrumbs: [
        {
            title: 'User Management',
            href: index().url,
        },
    ],
};
