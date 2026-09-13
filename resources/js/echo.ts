import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher?: typeof Pusher;
        Echo?: Echo<'reverb'>;
    }
}

window.Pusher = window.Pusher ?? Pusher;

export function createEcho(): Echo<'reverb'> {
    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
    const host = import.meta.env.VITE_REVERB_HOST ?? window.location.hostname;
    const port = import.meta.env.VITE_REVERB_PORT ?? '8080';

    const echo = new Echo<'reverb'>({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        authEndpoint: '/broadcasting/auth',
        wsHost: host,
        wsPort: Number(port),
        wssPort: Number(port),
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    window.Echo = echo;

    return echo;
}

export function echo(): Echo<'reverb'> {
    if (window.Echo) {
        return window.Echo;
    }

    return createEcho();
}

export { Pusher };