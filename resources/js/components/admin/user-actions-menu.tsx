import { router } from '@inertiajs/react';
import { MoreHorizontalIcon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ConfirmActionDialog } from '@/components/admin/confirm-action-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    role as roleRoute,
    status as statusRoute,
    destroy as destroyRoute,
} from '@/routes/admin/users';

interface AdminUser {
    id: number;
    name: string;
    role: string;
    account_status: string;
    is_self: boolean;
    is_last_admin: boolean;
}

interface UserActionsMenuProps {
    user: AdminUser;
}

type DialogType = 'deactivate' | 'delete' | null;

export function UserActionsMenu({ user }: UserActionsMenuProps) {
    const { t } = useLaravelReactI18n();
    const [activeDialog, setActiveDialog] = useState<DialogType>(null);
    const [isLoading, setIsLoading] = useState(false);

    const isGuarded = user.is_self || user.is_last_admin;

    const getGuardTooltip = (
        action: 'role' | 'deactivate' | 'delete',
    ): string => {
        if (user.is_self) {
            if (action === 'role') return t('app.admin.guard_self_role');
            if (action === 'deactivate')
                return t('app.admin.guard_self_deactivate');
            return t('app.admin.guard_self_delete');
        }
        if (action === 'role') return t('app.admin.guard_last_admin_role');
        if (action === 'deactivate')
            return t('app.admin.guard_last_admin_deactivate');
        return t('app.admin.guard_last_admin_delete');
    };

    const handleRoleChange = (newRole: string) => {
        router.put(
            roleRoute(user.id).url,
            { role: newRole },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        t('app.admin.toast_role_changed', {
                            name: user.name,
                            role: newRole,
                        }),
                    );
                },
                onError: () => {
                    toast.error(t('app.admin.toast_error'));
                },
            },
        );
    };

    const handleDeactivateConfirm = () => {
        setIsLoading(true);
        router.put(
            statusRoute(user.id).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        t('app.admin.toast_deactivated', {
                            name: user.name,
                        }),
                    );
                },
                onError: () => {
                    toast.error(t('app.admin.toast_error'));
                },
                onFinish: () => {
                    setIsLoading(false);
                    setActiveDialog(null);
                },
            },
        );
    };

    const handleDeleteConfirm = () => {
        setIsLoading(true);
        router.delete(destroyRoute(user.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    t('app.admin.toast_deleted', { name: user.name }),
                );
            },
            onError: () => {
                toast.error(t('app.admin.toast_error'));
            },
            onFinish: () => {
                setIsLoading(false);
                setActiveDialog(null);
            },
        });
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        aria-label={`Actions for ${user.name}`}
                    >
                        <MoreHorizontalIcon className="size-4" />
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="end">
                    {isGuarded ? (
                        <>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <DropdownMenuItem disabled>
                                        Change Role
                                    </DropdownMenuItem>
                                </TooltipTrigger>
                                <TooltipContent>
                                    {getGuardTooltip('role')}
                                </TooltipContent>
                            </Tooltip>

                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <DropdownMenuItem disabled>
                                        Deactivate
                                    </DropdownMenuItem>
                                </TooltipTrigger>
                                <TooltipContent>
                                    {getGuardTooltip('deactivate')}
                                </TooltipContent>
                            </Tooltip>

                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <DropdownMenuItem disabled>
                                        Delete account
                                    </DropdownMenuItem>
                                </TooltipTrigger>
                                <TooltipContent>
                                    {getGuardTooltip('delete')}
                                </TooltipContent>
                            </Tooltip>
                        </>
                    ) : (
                        <>
                            <DropdownMenuItem asChild>
                                <div className="flex flex-col gap-1 px-2 py-1.5">
                                    <span className="text-sm font-medium">
                                        Change Role
                                    </span>
                                    <Select
                                        defaultValue={user.role}
                                        onValueChange={handleRoleChange}
                                    >
                                        <SelectTrigger
                                            size="sm"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="User">
                                                User
                                            </SelectItem>
                                            <SelectItem value="Moderator">
                                                Moderator
                                            </SelectItem>
                                            <SelectItem value="Admin">
                                                Admin
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </DropdownMenuItem>

                            <DropdownMenuItem
                                onSelect={() => setActiveDialog('deactivate')}
                            >
                                Deactivate
                            </DropdownMenuItem>

                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => setActiveDialog('delete')}
                            >
                                Delete account
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <ConfirmActionDialog
                open={activeDialog === 'deactivate'}
                onOpenChange={(open) => !open && setActiveDialog(null)}
                title={t('app.admin.deactivate_title')}
                body={t('app.admin.deactivate_body', { name: user.name })}
                dismissLabel={t('app.admin.deactivate_dismiss')}
                confirmLabel={t('app.admin.deactivate_confirm')}
                onConfirm={handleDeactivateConfirm}
                isLoading={isLoading}
            />

            <ConfirmActionDialog
                open={activeDialog === 'delete'}
                onOpenChange={(open) => !open && setActiveDialog(null)}
                title={t('app.admin.delete_title')}
                body={t('app.admin.delete_body', { name: user.name })}
                dismissLabel={t('app.admin.delete_dismiss')}
                confirmLabel={t('app.admin.delete_confirm')}
                onConfirm={handleDeleteConfirm}
                isLoading={isLoading}
            />
        </>
    );
}
