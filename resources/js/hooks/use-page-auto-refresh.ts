import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

type Options = {
    enabled?: boolean;
    intervalMs?: number;
    only?: string[];
};

export function usePageAutoRefresh({
    enabled = true,
    intervalMs = 30000,
    only,
}: Options) {
    const reloading = useRef(false);

    useEffect(() => {
        if (!enabled || typeof window === 'undefined') {
            return;
        }

        let cancelled = false;
        let timeoutId: number | null = null;
        const schedule = () => {
            if (cancelled) {
                return;
            }

            const jitterMs = Math.floor(Math.random() * Math.max(1000, intervalMs * 0.2));

            timeoutId = window.setTimeout(() => {
                if (cancelled) {
                    return;
                }

                if (document.visibilityState !== 'visible' || reloading.current || navigator.onLine === false) {
                    schedule();

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
                        schedule();
                    },
                });
            }, intervalMs + jitterMs);
        };

        schedule();

        return () => {
            cancelled = true;

            if (timeoutId !== null) {
                window.clearTimeout(timeoutId);
            }
        };
    }, [enabled, intervalMs, only]);
}
