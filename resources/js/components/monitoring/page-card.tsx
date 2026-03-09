import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

export function PageCard({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn(
                'rounded-[20px] border border-[#2a3342] bg-[linear-gradient(180deg,rgba(20,26,37,0.97)_0%,rgba(17,22,31,0.97)_100%)]',
                className,
            )}
            {...props}
        />
    );
}
