import { Minus, Plus } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

export type FaqItem = {
    question: string;
    answer: string;
};

export function FaqAccordion({
    items,
    dark = true,
    title = 'Frequently asked questions.',
}: {
    items: FaqItem[];
    dark?: boolean;
    title?: string;
}) {
    const [openIndex, setOpenIndex] = useState(0);

    return (
        <section className={cn('rounded-[34px] px-6 py-8 sm:px-8 sm:py-10 lg:px-12 lg:py-14', dark ? 'bg-[#081326]' : 'bg-[#f4f6fb]')}>
            <h2 className={cn('text-[28px] font-semibold leading-[0.92] tracking-[-0.06em] sm:text-[36px]', dark ? 'text-white' : 'text-[#16233c]')}>
                {title}
                <span className="text-[#40dd79]">.</span>
            </h2>
            <div className="mt-8 divide-y divide-white/10">
                {items.map((item, index) => {
                    const isOpen = index === openIndex;

                    return (
                        <div key={item.question} className={cn('py-6', !dark && 'border-[#d6dce8]')}>
                            <button
                                type="button"
                                className="flex w-full items-center justify-between gap-4 text-left"
                                onClick={() => setOpenIndex(isOpen ? -1 : index)}
                            >
                                <span className={cn('text-[22px] font-semibold tracking-[-0.04em] sm:text-[28px]', dark ? 'text-white' : 'text-[#16233c]')}>
                                    {item.question}
                                </span>
                                {isOpen ? (
                                    <Minus className={cn('size-6 shrink-0', dark ? 'text-[#40dd79]' : 'text-[#16233c]')} />
                                ) : (
                                    <Plus className={cn('size-6 shrink-0', dark ? 'text-[#8fa0bf]' : 'text-[#54627d]')} />
                                )}
                            </button>
                            {isOpen ? (
                                <p className={cn('mt-4 max-w-[70ch] text-[17px] leading-8', dark ? 'text-[#9eacc7]' : 'text-[#4f5f7c]')}>
                                    {item.answer}
                                </p>
                            ) : null}
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
