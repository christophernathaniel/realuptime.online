import { ChevronDown, ChevronUp, Pause } from 'lucide-react';
import { cn } from '@/lib/utils';

const config = {
    up: {
        icon: ChevronUp,
        dot: 'bg-[#57c7c2]',
        text: 'text-[#57c7c2]',
        fill: 'bg-[#57c7c2]',
    },
    down: {
        icon: ChevronDown,
        dot: 'bg-[#ff7a72]',
        text: 'text-[#ff7a72]',
        fill: 'bg-[#ff7a72]',
    },
    paused: {
        icon: Pause,
        dot: 'bg-[#7f899d]',
        text: 'text-[#7f899d]',
        fill: 'bg-[#7f899d]',
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
                large ? 'size-10' : 'size-7',
            )}
        >
            <Icon className={cn('text-[#091119]', large ? 'size-[16px]' : 'size-[12px]')} strokeWidth={2.8} />
        </span>
    );
}

export function StatusDot({ status }: { status: string }) {
    const variant = config[status as keyof typeof config] ?? config.paused;

    return <span className={cn('inline-flex size-[7px] rounded-full', variant.dot)} />;
}
