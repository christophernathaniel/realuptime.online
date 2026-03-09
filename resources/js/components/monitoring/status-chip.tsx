import { ChevronDown, ChevronUp, Pause } from 'lucide-react';
import { cn } from '@/lib/utils';

const config = {
    up: {
        icon: ChevronUp,
        dot: 'bg-[#3ee072]',
        text: 'text-[#3ee072]',
        fill: 'bg-[#3ee072]',
    },
    down: {
        icon: ChevronDown,
        dot: 'bg-[#ff6269]',
        text: 'text-[#ff6269]',
        fill: 'bg-[#ff6269]',
    },
    paused: {
        icon: Pause,
        dot: 'bg-[#8c97b2]',
        text: 'text-[#8c97b2]',
        fill: 'bg-[#8c97b2]',
    },
} as const;

export function StatusChip({ status, large = false }: { status: string; large?: boolean }) {
    const variant = config[status as keyof typeof config] ?? config.paused;
    const Icon = variant.icon;

    return (
        <span
            className={cn(
                'inline-flex items-center justify-center rounded-full',
                variant.fill,
                large ? 'size-9' : 'size-6',
            )}
        >
            <Icon className={cn('text-[#0b1730]', large ? 'size-[16px]' : 'size-[12px]')} strokeWidth={2.8} />
        </span>
    );
}

export function StatusDot({ status }: { status: string }) {
    const variant = config[status as keyof typeof config] ?? config.paused;

    return <span className={cn('inline-flex size-[7px] rounded-full', variant.dot)} />;
}
