import { usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { BookOpen, Carrot, ChefHat, ClipboardList, FolderGit2, Globe, LayoutGrid, Users } from 'lucide-react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import AppLogo from '@/components/app-logo';
import { IngredientNotifications } from '@/components/ingredient-notifications';
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
import { index as libraryIndex } from '@/routes/library';
import { index as recipesIndex } from '@/routes/recipes';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as adminIngredientsIndex } from '@/routes/admin/ingredients';
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
    const canReviewIngredients = permissions.includes('review-ingredients');
    const pendingIngredientReviewCount = (usePage().props.pendingIngredientReviewCount as number | null) ?? null;

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
        {
            title: t('app.recipes.nav'),
            href: recipesIndex().url,
            icon: ChefHat,
        },
        {
            title: t('app.library.nav'),
            href: libraryIndex().url,
            icon: Globe,
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
        ...(canReviewIngredients
            ? [
                  {
                      title: t('app.nav.ingredient_review'),
                      href: adminIngredientsIndex().url,
                      icon: ClipboardList,
                      badge:
                          pendingIngredientReviewCount && pendingIngredientReviewCount > 0
                              ? String(pendingIngredientReviewCount)
                              : undefined,
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
                <IngredientNotifications />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
