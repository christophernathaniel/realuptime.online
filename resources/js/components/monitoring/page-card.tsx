import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

export function PageCard({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn(
                'rounded-[18px] border border-white/6 bg-[#1a2339]/95 shadow-[0_15px_36px_rgba(0,0,0,0.18)] backdrop-blur-sm',
                className,
            )}
            {...props}
        />
    );
}
