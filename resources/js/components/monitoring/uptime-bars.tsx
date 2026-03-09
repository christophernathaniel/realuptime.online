import { cn } from '@/lib/utils';
import type { MonitorBarState } from '@/types/monitoring';

const toneMap: Record<MonitorBarState, string> = {
    up: 'bg-[#57c7c2]',
    down: 'bg-[#ff7a72]',
    unknown: 'bg-[#2a3344]',
};

export function UptimeBars({ bars, compact = false }: { bars: MonitorBarState[]; compact?: boolean }) {
    return (
        <div className={cn('flex items-end gap-1', compact ? 'h-6' : 'h-8')}>
            {bars.map((bar, index) => (
                <span
                    key={`${bar}-${index}`}
                    className={cn(
                        'inline-flex flex-1 rounded-full',
                        compact ? 'h-[18px] max-w-[5px]' : 'h-5 max-w-[7px]',
                        toneMap[bar],
                    )}
                />
            ))}
        </div>
    );
}
