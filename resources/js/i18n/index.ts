import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import en from './locales/en';
import sw from './locales/sw';

export const SUPPORTED_LOCALES = ['en', 'sw'] as const;
export type Locale = (typeof SUPPORTED_LOCALES)[number];

const STORAGE_KEY = 'locale';

export function isSupportedLocale(value: string | null | undefined): value is Locale {
    return value !== null && value !== undefined && (SUPPORTED_LOCALES as readonly string[]).includes(value);
}

export function getInitialLocale(): Locale {
    if (typeof window === 'undefined') {
        return 'en';
    }

    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        if (isSupportedLocale(stored)) {
            return stored;
        }
    } catch {
        // Local storage may be unavailable (private mode, tests, etc.)
    }

    return 'en';
}

export function persistLocale(locale: Locale): void {
    try {
        window.localStorage.setItem(STORAGE_KEY, locale);
    } catch {
        // Ignore failures; the in-memory locale still applies for the session.
    }
}

void i18n.use(initReactI18next).init({
    resources: {
        en: { translation: en },
        sw: { translation: sw },
    },
    lng: getInitialLocale(),
    fallbackLng: 'en',
    supportedLngs: [...SUPPORTED_LOCALES],
    load: 'currentOnly',
    interpolation: {
        escapeValue: false,
    },
    keySeparator: false,
    nsSeparator: false,
    returnNull: false,
});

export default i18n;