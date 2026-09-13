import { Head, Link, router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';

type NotificationItem = {
    id: string;
    message: string;
    link?: string | null;
    read: boolean;
    created_at?: string | null;
};

type Props = {
    notifications: NotificationItem[];
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
    };
    unread_count: number;
};

export default function NotificationsIndex({
    notifications,
    pagination,
    unread_count,
}: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props;

    const markAllRead = () =>
        router.post(
            '/notifications/read-all',
            {},
            { preserveScroll: true, preserveState: true, only: ['notifications'] },
        );

    const markRead = (id: string) =>
        router.post(
            `/notifications/${id}/read`,
            {},
            { preserveScroll: true, preserveState: true, only: ['notifications'] },
        );

    const goToPage = (page: number) =>
        router.get('/notifications', { page }, { preserveScroll: true, preserveState: true });

    return (
        <div className="flex flex-col gap-6">
            <Head title={t('Notifications')} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="space-y-1">
                    <Heading
                        title={t('Notifications')}
                        description={t('All system notifications')}
                    />
                    {unread_count > 0 && (
                        <Badge variant="destructive">{t('{{count}} unread', { count: unread_count })}</Badge>
                    )}
                </div>
                {unread_count > 0 && (
                    <Button variant="outline" size="sm" onClick={markAllRead}>
                        <CheckCheck className="size-4" />
                        {t('Mark all read')}
                    </Button>
                )}
            </div>

            <div className="flex flex-col gap-3">
                {notifications.length === 0 && (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-lg border bg-background p-10 text-center">
                        <Bell className="size-10 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">{t('No notifications yet.')}</p>
                    </div>
                )}

                {notifications.map((item) => (
                    <div
                        key={item.id}
                        className={`flex items-start justify-between gap-4 rounded-lg border bg-background p-4 ${
                            !item.read ? 'border-blue-300 dark:border-blue-700' : ''
                        }`}
                    >
                        <button
                            type="button"
                            onClick={() => !item.read && markRead(item.id)}
                            className="flex min-w-0 flex-1 items-start gap-3 text-left"
                            disabled={item.read}
                        >
                            <span
                                className={`mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${
                                    item.read ? 'bg-neutral-300 dark:bg-neutral-700' : 'bg-blue-600'
                                }`}
                            />
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium break-words">{item.message}</p>
                                {item.created_at && (
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {formatDateTime(item.created_at)}
                                    </p>
                                )}
                            </div>
                        </button>
                        {!item.read && (
                            <Badge variant="outline" className="shrink-0">
                                {t('New')}
                            </Badge>
                        )}
                        {item.link && (
                            <Link
                                href={item.link}
                                className="shrink-0 text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                            >
                                {t('View')}
                            </Link>
                        )}
                    </div>
                ))}

                {pagination.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            {t('Page {{current}} of {{total}} · {{count}} total', {
                                current: pagination.current_page,
                                total: pagination.last_page,
                                count: pagination.total,
                            })}
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={pagination.current_page <= 1}
                                onClick={() => goToPage(pagination.current_page - 1)}
                            >
                                {t('Previous')}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={pagination.current_page >= pagination.last_page}
                                onClick={() => goToPage(pagination.current_page + 1)}
                            >
                                {t('Next')}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}