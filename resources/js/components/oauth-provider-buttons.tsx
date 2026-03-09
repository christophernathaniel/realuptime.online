import { Chrome, Github } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const providerIcons = {
    google: Chrome,
    github: Github,
} as const;

type OAuthProvider = {
    key: string;
    label: string;
    enabled: boolean;
    redirectUrl: string;
};

export function OAuthProviderButtons({
    providers,
    className,
}: {
    providers: OAuthProvider[];
    className?: string;
}) {
    if (providers.length === 0) {
        return null;
    }

    return (
        <div className={cn('grid gap-3', className)}>
            {providers.map((provider) => {
                const Icon =
                    providerIcons[provider.key as keyof typeof providerIcons] ??
                    Chrome;

                return provider.enabled ? (
                    <a key={provider.key} href={provider.redirectUrl} className="block">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-12 w-full justify-center gap-2 rounded-[18px] border-white/10 bg-[#101b2f] text-[#dce6fb] shadow-none hover:bg-[#15233d] hover:text-white"
                        >
                            <Icon className="size-4" />
                            Continue with {provider.label}
                        </Button>
                    </a>
                ) : (
                    <Button
                        key={provider.key}
                        type="button"
                        variant="outline"
                        disabled
                        className="h-12 w-full justify-center gap-2 rounded-[18px] border-white/8 bg-[#101b2f]/60 text-[#8fa0bf] shadow-none"
                    >
                        <Icon className="size-4" />
                        {provider.label} not configured
                    </Button>
                );
            })}
        </div>
    );
}
