import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-muted p-4">
            <div className="flex w-full max-w-sm flex-col gap-3">
                <Card className="rounded-xl border shadow-sm">
                    {/* Padding ya juu na chini imepunguzwa (pt-5 pb-1) */}
                    <CardHeader className="px-6 pt-5 pb-1 text-center">
                        <CardTitle className="text-lg font-semibold">{title}</CardTitle>
                        {description && (
                            <CardDescription className="text-xs text-muted-foreground mt-0.5">
                                {description}
                            </CardDescription>
                        )}
                    </CardHeader>
                    {/* Padding ya ndani imepunguzwa (px-6 py-4) */}
                    <CardContent className="px-6 py-4 pt-2">
                        {children}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}