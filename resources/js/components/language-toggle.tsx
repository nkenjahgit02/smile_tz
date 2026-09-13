import { Globe } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { isSupportedLocale, persistLocale, type Locale } from '@/i18n';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

export function LanguageToggle() {
    const { i18n, t } = useTranslation();

    const current: Locale = isSupportedLocale(i18n.resolvedLanguage)
        ? i18n.resolvedLanguage
        : 'en';
    const isSw = current === 'sw';
    const next: Locale = isSw ? 'en' : 'sw';

    const toggle = () => {
        void i18n.changeLanguage(next);
        persistLocale(next);
    };

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative h-9 w-9 cursor-pointer"
                    onClick={toggle}
                    aria-label={t('Language')}
                >
                    <Globe className="size-5 opacity-80 group-hover:opacity-100" />
                    <span className="absolute right-0.5 bottom-0 text-[8px] font-bold">
                        {isSw ? 'SW' : 'EN'}
                    </span>
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                {t('Language')}: {isSw ? 'English' : 'Kiswahili'}
            </TooltipContent>
        </Tooltip>
    );
}