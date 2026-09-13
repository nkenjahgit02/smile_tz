import { Link } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { getMenuIcon, type MenuSection } from '@/components/menu-icons';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePage } from '@inertiajs/react';
import { dashboard } from '@/routes';
import { useTranslation } from 'react-i18next';

export function AppSidebar() {
    const { t } = useTranslation();
    const { nav } = usePage().props as {
        nav?: { sections?: MenuSection[] };
    };

    const sections = (nav?.sections ?? []).map((section) => ({
        title: t(section.title),
        items: section.items.map((item) => ({
            title: t(item.title),
            href: item.href,
            icon: getMenuIcon(item.icon),
            badge: item.badge,
        })),
    }));

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
                {sections.map((section) => (
                    <NavMain
                        key={section.title}
                        title={section.title}
                        items={section.items}
                    />
                ))}
            </SidebarContent>
        </Sidebar>
    );
}