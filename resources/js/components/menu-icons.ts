import {
    Archive,
    ArrowLeftRight,
    BarChart3,
    Building2,
    ClipboardCheck,
    ClipboardList,
    Clock,
    Coins,
    Gift,
    HandCoins,
    LayoutDashboard,
    Package,
    PackageCheck,
    Printer,
    Receipt,
    ScrollText,
    Settings,
    Shield,
    ShoppingCart,
    Sparkles,
    Tags,
    TicketPercent,
    Truck,
    UserCog,
    Users,
    Warehouse,
    type LucideIcon,
} from 'lucide-react';

const menuIcons: Record<string, LucideIcon> = {
    'layout-dashboard': LayoutDashboard,
    'shopping-cart': ShoppingCart,
    receipt: Receipt,
    clock: Clock,
    archive: Archive,
    package: Package,
    'package-check': PackageCheck,
    tags: Tags,
    warehouse: Warehouse,
    'arrow-left-right': ArrowLeftRight,
    'clipboard-check': ClipboardCheck,
    truck: Truck,
    'clipboard-list': ClipboardList,
    'ticket-percent': TicketPercent,
    users: Users,
    'hand-coins': HandCoins,
    gift: Gift,
    coins: Coins,
    'bar-chart-3': BarChart3,
    sparkles: Sparkles,
    'scroll-text': ScrollText,
    'user-cog': UserCog,
    shield: Shield,
    'building-2': Building2,
    printer: Printer,
    settings: Settings,
};

export interface MenuItem {
    title: string;
    href: string;
    icon?: string | null;
    badge?: number;
}

export interface MenuSection {
    title: string;
    items: MenuItem[];
}

export function getMenuIcon(name?: string | null): LucideIcon | null {
    return name ? menuIcons[name] ?? null : null;
}