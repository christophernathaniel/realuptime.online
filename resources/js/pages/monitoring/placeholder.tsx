import { Head } from '@inertiajs/react';
import { PageCard } from '@/components/monitoring/page-card';
import MonitoringLayout from '@/layouts/monitoring-layout';

type PlaceholderPageProps = {
    title: string;
    description: string;
    stats: Array<{
        label: string;
        value: string;
    }>;
};

export default function PlaceholderPage({ title, description, stats }: PlaceholderPageProps) {
    return (
        <MonitoringLayout>
            <Head title={title} />
            <div className="space-y-6">
                <h1 className="text-[56px] font-semibold tracking-[-0.06em] text-white">
                    {title}
                    <span className="text-[#7c8cff]">.</span>
                </h1>
                <div className="max-w-[880px] text-[20px] text-[#8fa0bf]">{description}</div>
                <div className="grid gap-4 md:grid-cols-3">
                    {stats.map((stat) => (
                        <PageCard key={stat.label} className="p-7">
                            <div className="text-lg text-[#8fa0bf]">{stat.label}</div>
                            <div className="mt-3 text-[52px] font-semibold tracking-[-0.05em] text-white">{stat.value}</div>
                        </PageCard>
                    ))}
                </div>
            </div>
        </MonitoringLayout>
    );
}
