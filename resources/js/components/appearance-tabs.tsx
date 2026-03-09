import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

export default function AppearanceToggleTab({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: 'Light' },
        { value: 'dark', icon: Moon, label: 'Dark' },
        { value: 'system', icon: Monitor, label: 'System' },
    ];

    return (
        <div
            className={cn(
                'grid gap-3 sm:grid-cols-3',
                className,
            )}
            {...props}
        >
            {tabs.map(({ value, icon: Icon, label }) => {
                const active = appearance === value;

                return (
                    <button
                        key={value}
                        type="button"
                        onClick={() => updateAppearance(value)}
                        className={cn(
                            'flex items-center gap-3 rounded-[20px] border px-4 py-4 text-left transition',
                            active
                                ? 'border-[#3ee072]/25 bg-[#10273a] text-white shadow-[inset_0_0_0_1px_rgba(62,224,114,0.06)]'
                                : 'border-white/8 bg-[#101b2f] text-[#c9d5ec] hover:bg-[#16253f] hover:text-white',
                        )}
                    >
                        <span
                            className={cn(
                                'inline-flex size-10 items-center justify-center rounded-[14px] border',
                                active
                                    ? 'border-[#3ee072]/30 bg-[#0a1324] text-[#3ee072]'
                                    : 'border-white/8 bg-[#081428] text-[#7687a8]',
                            )}
                        >
                            <Icon className="size-4" />
                        </span>
                        <span>
                            <span className="block text-[14px] font-semibold">
                                {label}
                            </span>
                            <span className="mt-1 block text-[12px] text-[#7383a3]">
                                {value === 'system'
                                    ? 'Follow device preference'
                                    : `Use ${label.toLowerCase()} mode`}
                            </span>
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
