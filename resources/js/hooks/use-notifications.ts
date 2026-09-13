import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { echo } from '@/echo';

export type UiNotification = {
    id: string;
    message: string;
    link?: string | null;
    read: boolean;
    created_at?: string | null;
};

type NotificationsProps = {
    unread_count: number;
    latest: UiNotification[];
};

type BroadcastPayload = {
    message?: string;
    link?: string | null;
};

export function useNotifications() {
    const page = usePage();
    const props = (page.props as { notifications?: NotificationsProps }).notifications;
    const userId = (page.props as { auth?: { user?: { id?: number } } }).auth?.user?.id;

    const [items, setItems] = useState<UiNotification[]>(props?.latest ?? []);
    const [unread, setUnread] = useState<number>(props?.unread_count ?? 0);

    useEffect(() => {
        setItems(props?.latest ?? []);
        setUnread(props?.unread_count ?? 0);
    }, [props?.unread_count, props?.latest]);

    useEffect(() => {
        if (!userId) {
            return;
        }

        const channel = echo().private(`App.Models.User.${userId}`);

        const handler = (event: BroadcastPayload) => {
            const notification: UiNotification = {
                id: crypto.randomUUID(),
                message: event.message ?? 'New notification',
                link: event.link ?? null,
                read: false,
                created_at: new Date().toISOString(),
            };

            let counted = false;

            const commitToBell = () => {
                if (counted) {
                    return;
                }

                counted = true;
                setItems((previous) => [notification, ...previous].slice(0, 20));
                setUnread((count) => count + 1);
            };

            toast('🔔 ' + notification.message, {
                duration: 6000,
                onDismiss: commitToBell,
                onAutoClose: commitToBell,
            });
        };

        channel.listen('.system.notification', handler);

        return () => {
            channel.stopListening('.system.notification');
            echo().leaveChannel(channel.name);
        };
    }, [userId]);

    const markRead = (id: string) => {
        setItems((previous) =>
            previous.map((item) => (item.id === id ? { ...item, read: true } : item)),
        );
        setUnread((count) => Math.max(0, count - 1));
        router.post(`/notifications/${id}/read`, {}, {
            preserveScroll: true,
            preserveState: true,
            only: ['notifications'],
        });
    };

    const markAllRead = () => {
        setItems((previous) => previous.map((item) => ({ ...item, read: true })));
        setUnread(0);
        router.post('/notifications/read-all', {}, {
            preserveScroll: true,
            preserveState: true,
            only: ['notifications'],
        });
    };

    return { items, unread, markRead, markAllRead };
}