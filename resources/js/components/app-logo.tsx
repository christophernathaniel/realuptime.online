import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <div className="flex items-center gap-3">
            <AppLogoIcon className="h-8 w-8 shrink-0" />
            <div className="grid flex-1 text-left">
                <span className="truncate text-[20px] leading-none font-semibold tracking-[-0.05em] text-[#e9eef7]">
                    RealUptime
                </span>
            </div>
        </div>
    );
}
