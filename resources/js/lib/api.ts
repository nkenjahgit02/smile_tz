export async function api<T>(
    url: string,
    method: 'GET' | 'POST' = 'POST',
    data?: Record<string, unknown>,
): Promise<T> {
    const headers: HeadersInit = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const xsrf = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    if (xsrf) {
        headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf);
    }

    const res = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        body: data ? JSON.stringify(data) : undefined,
    });

    if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        throw new Error((body as { message?: string }).message ?? `Request failed (${res.status})`);
    }

    return res.json() as Promise<T>;
}