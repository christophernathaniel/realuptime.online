import type { LucideIcon } from 'lucide-react';
import { Activity, BellRing, Globe2, ServerCog, ShieldCheck, TriangleAlert, Wifi } from 'lucide-react';
import { cn } from '@/lib/utils';

const icons: Record<string, LucideIcon> = {
    activity: Activity,
    bell: BellRing,
    broadcast: Wifi,
    globe: Globe2,
    shield: ShieldCheck,
    siren: TriangleAlert,
    webhook: ServerCog,
};

export function FeatureIcon({ icon, className }: { icon: string; className?: string }) {
    const Icon = icons[icon] ?? Globe2;

    return <Icon className={cn('aspect-square shrink-0', className)} />;
}
