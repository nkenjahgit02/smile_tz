import { Link, usePage } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useNotifications } from '@/hooks/use-notifications';
import { formatDateTime } from '@/lib/format';

export function NotificationBell() {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const { items, unread, markRead, markAllRead } = useNotifications();

    if (!auth.user) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="group relative h-9 w-9 cursor-pointer"
                    aria-label={t('Notifications')}
                >
                    <Bell className="size-5 opacity-80 group-hover:opacity-100" />
                    {unread > 0 && (
                        <span className="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white">
                            {unread > 99 ? '99+' : unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <div className="flex items-center justify-between px-2 py-1.5">
                    <p className="text-sm font-semibold">{t('Notifications')}</p>
                    {unread > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 gap-1 px-2 text-xs text-blue-600 hover:underline dark:text-blue-400"
                            onClick={markAllRead}
                        >
                            <CheckCheck className="size-3.5" />
                            {t('Mark all read')}
                        </Button>
                    )}
                </div>
                <DropdownMenuSeparator />
                <div className="max-h-80 overflow-y-auto">
                    {items.length === 0 && (
                        <p className="px-3 py-8 text-center text-sm text-muted-foreground">
                            {t('No notifications yet.')}
                        </p>
                    )}
                    {items.map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => !item.read && item.id && markRead(item.id)}
                            className="flex w-full items-start gap-2.5 px-3 py-2.5 text-left transition-colors hover:bg-accent"
                        >
                            {!item.read && (
                                <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-blue-600" />
                            )}
                            <div className="min-w-0 flex-1">
                                <p className="text-sm leading-snug break-words">{item.message}</p>
                                {item.created_at && (
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {formatDateTime(item.created_at)}
                                    </p>
                                )}
                            </div>
                        </button>
                    ))}
                </div>
                <DropdownMenuSeparator />
                <div className="p-1">
                    <Link
                        href="/notifications"
                        className="block rounded-md px-2 py-1.5 text-center text-sm font-medium text-blue-600 hover:bg-accent dark:text-blue-400"
                    >
                        {t('View all notifications')}
                    </Link>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}