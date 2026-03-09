import { Link } from '@inertiajs/react';
import { Check, CircleAlert, X } from 'lucide-react';
import { marketingPlans } from '@/lib/marketing-content';
import { cn } from '@/lib/utils';

export function PricingTable({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    return (
        <div className="grid gap-6 xl:grid-cols-3">
            {marketingPlans.map((plan) => (
                <div
                    key={plan.key}
                    className={cn(
                        'relative overflow-hidden rounded-[30px] border p-7',
                        plan.featured
                            ? 'border-[#44d97b] bg-[#f5f7fb] text-[#16233c]'
                            : 'border-[#d6dce8] bg-[#f5f7fb] text-[#16233c]',
                    )}
                >
                    {plan.badge ? (
                        <div className="absolute inset-x-0 top-0 bg-[#44d97b] px-5 py-3 text-center text-[15px] font-semibold text-[#082039]">
                            {plan.badge}
                        </div>
                    ) : null}
                    <div className={cn(plan.badge && 'pt-8')}>
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <div className="text-[16px] font-semibold uppercase tracking-[0.22em] text-[#71809b]">Plan</div>
                                <h3 className="mt-3 text-[38px] font-semibold tracking-[-0.07em] text-[#16233c] sm:text-[40px]">
                                    {plan.name}
                                    <span className="text-[#44d97b]">.</span>
                                </h3>
                            </div>
                            <div className="shrink-0 rounded-full bg-[#dff8e8] px-3 py-1.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-[#1d7e49] sm:px-4 sm:text-[11px]">
                                {plan.interval}
                            </div>
                        </div>
                        <div className="mt-6 flex items-end gap-2">
                            <span className="text-[16px] text-[#71809b]">from</span>
                            <span className="text-[46px] font-semibold leading-none tracking-[-0.08em] text-[#16233c] sm:text-[50px]">
                                {plan.monthlyPriceLabel}
                            </span>
                            <span className="pb-1.5 text-[16px] text-[#71809b]">/ month</span>
                        </div>
                        <p className="mt-3 max-w-[38ch] text-[18px] leading-8 text-[#60708d]">{plan.description}</p>
                        <div className="mt-8 rounded-[22px] border border-[#d6dce8] bg-white px-5 py-5">
                            <div className="text-[30px] font-semibold tracking-[-0.07em] text-[#16233c] sm:text-[32px]">{plan.monitors}</div>
                            <div className="mt-2 text-[16px] text-[#60708d]">Monthly billing is live today with self-serve upgrades from inside the app.</div>
                        </div>
                        <Link
                            href={canRegister ? '/register' : '/login'}
                            className={cn(
                                'mt-8 inline-flex h-14 w-full items-center justify-center rounded-full text-[16px] font-semibold transition sm:text-[17px]',
                                plan.featured ? 'bg-[#44d97b] text-[#082039] hover:bg-[#37c86a]' : 'bg-[#18253e] text-white hover:bg-[#223251]',
                            )}
                        >
                            {plan.cta}
                        </Link>
                        <div className="mt-8 space-y-4">
                            {plan.features.map((feature) => (
                                <div key={feature.label} className="flex items-start gap-3 text-[17px] leading-7 text-[#16233c]">
                                    {feature.included ? (
                                        <span className="mt-1 inline-flex size-6 items-center justify-center rounded-full bg-[#44d97b] text-[#082039]">
                                            <Check className="size-4" />
                                        </span>
                                    ) : feature.muted ? (
                                        <span className="mt-1 inline-flex size-6 items-center justify-center rounded-full bg-[#ffe7b0] text-[#86600a]">
                                            <CircleAlert className="size-4" />
                                        </span>
                                    ) : (
                                        <span className="mt-1 inline-flex size-6 items-center justify-center rounded-full bg-[#ffd7da] text-[#b33d46]">
                                            <X className="size-4" />
                                        </span>
                                    )}
                                    <span className={cn(feature.muted && 'text-[#60708d]')}>{feature.label}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}
