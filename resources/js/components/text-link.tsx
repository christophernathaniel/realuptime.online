import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type Props = ComponentProps<typeof Link>;

export default function TextLink({
    className = '',
    children,
    ...props
}: Props) {
    return (
        <Link
            className={cn(
                'text-[#3ee072] underline decoration-[#3ee072]/35 underline-offset-4 transition-colors duration-200 ease-out hover:text-[#7ff3a0]',
                className,
            )}
            {...props}
        >
            {children}
        </Link>
    );
}
