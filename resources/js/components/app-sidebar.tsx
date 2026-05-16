import { usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { BookOpen, Carrot, FolderGit2, LayoutGrid, Users } from 'lucide-react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import AppLogo from '@/components/app-logo';
import { LanguageSwitcher } from '@/components/language-switcher';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as ingredientsIndex } from '@/routes/ingredients';
import { index as adminUsersIndex } from '@/routes/admin/users';
import type { Auth, NavItem } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { t } = useLaravelReactI18n();
    const auth = usePage().props.auth as Auth;
    const permissions = auth.permissions ?? [];
    const canManageUsers = permissions.includes('manage-users');

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: t('app.nav.ingredients'),
            href: ingredientsIndex().url,
            icon: Carrot,
        },
        ...(canManageUsers
            ? [
                  {
                      title: t('app.nav.users'),
                      href: adminUsersIndex().url,
                      icon: Users,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <LanguageSwitcher />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
