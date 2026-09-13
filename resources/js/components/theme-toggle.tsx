import { Moon, Sun } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/hooks/use-appearance';

export function ThemeToggle() {
    const { t } = useTranslation();
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    const toggle = () => updateAppearance(isDark ? 'light' : 'dark');

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative h-9 w-9 cursor-pointer"
                    onClick={toggle}
                    aria-label={
                        isDark ? t('Switch to light mode') : t('Switch to dark mode')
                    }
                >
                    <Sun className="size-5 scale-100 opacity-80 transition-all dark:scale-0 dark:opacity-0" />
                    <Moon className="absolute size-5 scale-0 opacity-0 transition-all dark:scale-100 dark:opacity-80" />
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                {isDark ? t('Switch to light mode') : t('Switch to dark mode')}
            </TooltipContent>
        </Tooltip>
    );
}