import { useLaravelReactI18n } from 'laravel-react-i18n';
import { UserActionsMenu } from '@/components/admin/user-actions-menu';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export interface AdminUserRow {
    id: number;
    name: string;
    email: string;
    role: string;
    account_status: string;
    created_at: string;
    is_self: boolean;
    is_last_admin: boolean;
}

interface UserTableProps {
    users: AdminUserRow[];
    isLoading?: boolean;
}

function roleBadgeVariant(role: string): 'default' | 'secondary' | 'outline' {
    if (role === 'Admin') return 'default';
    if (role === 'Moderator') return 'secondary';
    return 'outline';
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

function LoadingRows() {
    return (
        <>
            {Array.from({ length: 5 }).map((_, i) => (
                <TableRow key={i}>
                    <TableCell>
                        <div className="flex items-center gap-3">
                            <Skeleton className="size-8 rounded-full" />
                            <div className="flex flex-col gap-1">
                                <Skeleton className="h-4 w-32" />
                                <Skeleton className="h-3 w-48" />
                            </div>
                        </div>
                    </TableCell>
                    <TableCell className="hidden md:table-cell">
                        <Skeleton className="h-5 w-20" />
                    </TableCell>
                    <TableCell className="hidden lg:table-cell">
                        <Skeleton className="h-4 w-24" />
                    </TableCell>
                    <TableCell>
                        <Skeleton className="size-8 rounded-md" />
                    </TableCell>
                </TableRow>
            ))}
        </>
    );
}

export function UserTable({ users, isLoading = false }: UserTableProps) {
    const { t } = useLaravelReactI18n();

    return (
        <Table>
            <TableHeader>
                <TableRow className="bg-muted">
                    <TableHead scope="col">User</TableHead>
                    <TableHead
                        scope="col"
                        className="hidden w-[120px] md:table-cell"
                    >
                        Role
                    </TableHead>
                    <TableHead
                        scope="col"
                        className="hidden w-[140px] lg:table-cell"
                    >
                        Joined
                    </TableHead>
                    <TableHead scope="col" className="w-[48px]">
                        <span className="sr-only">Actions</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {isLoading ? (
                    <LoadingRows />
                ) : users.length === 0 ? (
                    <TableRow>
                        <TableCell colSpan={4}>
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <p className="text-xl font-semibold">
                                    {t('app.admin.empty_heading')}
                                </p>
                                <p className="mt-2 text-base text-muted-foreground">
                                    {t('app.admin.empty_body')}
                                </p>
                            </div>
                        </TableCell>
                    </TableRow>
                ) : (
                    users.map((user) => (
                        <TableRow
                            key={user.id}
                            className="h-[52px] hover:bg-muted"
                        >
                            <TableCell>
                                <div className="flex items-center gap-3">
                                    <Avatar className="size-8">
                                        <AvatarFallback className="text-xs">
                                            {getInitials(user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="flex flex-col">
                                        <span className="text-base">
                                            {user.name}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {user.email}
                                        </span>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell className="hidden w-[120px] md:table-cell">
                                <Badge
                                    variant={roleBadgeVariant(user.role ?? '')}
                                >
                                    {user.role}
                                </Badge>
                            </TableCell>
                            <TableCell className="hidden w-[140px] text-sm text-muted-foreground lg:table-cell">
                                {user.created_at
                                    ? formatDate(user.created_at)
                                    : '—'}
                            </TableCell>
                            <TableCell className="w-[48px]">
                                <UserActionsMenu user={user} />
                            </TableCell>
                        </TableRow>
                    ))
                )}
            </TableBody>
        </Table>
    );
}
