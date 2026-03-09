import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import { PageCard } from '@/components/monitoring/page-card';
import MonitoringLayout from '@/layouts/monitoring-layout';
import SettingsLayout from '@/layouts/settings/layout';

export default function Appearance() {
    return (
        <MonitoringLayout>
            <Head title="Appearance settings" />

            <SettingsLayout
                title="Appearance"
                description="Choose how RealUptime should render the interface on this device."
            >
                <PageCard className="p-6 sm:p-7">
                    <div className="space-y-6">
                        <div>
                            <h2 className="text-[20px] font-semibold tracking-[-0.04em] text-white">
                                Theme preference
                            </h2>
                            <p className="mt-2 text-[14px] leading-6 text-[#8fa0bf]">
                                Pick the interface mode that feels right for long sessions in the monitoring dashboard.
                            </p>
                        </div>

                        <AppearanceTabs />
                    </div>
                </PageCard>
            </SettingsLayout>
        </MonitoringLayout>
    );
}
