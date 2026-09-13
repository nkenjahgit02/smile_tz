export type * from './auth';
export type * from './navigation';
export type * from './ui';

export type SharedNavItem = {
    title: string;
    href: string;
    icon?: string | null;
    badge?: number;
};

export type SharedNavSection = {
    title: string;
    items: SharedNavItem[];
};
