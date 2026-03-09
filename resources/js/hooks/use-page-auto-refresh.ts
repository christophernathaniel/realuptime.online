import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

type Options = {
    enabled?: boolean;
    intervalMs?: number;
    only?: string[];
};

export function usePageAutoRefresh({
    enabled = true,
    intervalMs = 15000,
    only,
}: Options) {
    const reloading = useRef(false);

    useEffect(() => {
        if (!enabled || typeof window === 'undefined') {
            return;
        }

        const interval = window.setInterval(() => {
            if (document.visibilityState !== 'visible' || reloading.current) {
                return;
            }

            reloading.current = true;

            router.visit(`${window.location.pathname}${window.location.search}`, {
                method: 'get',
                only,
                preserveScroll: true,
                preserveState: true,
                replace: true,
                onFinish: () => {
                    reloading.current = false;
                },
            });
        }, intervalMs);

        return () => window.clearInterval(interval);
    }, [enabled, intervalMs, only]);
}
