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
    const columns = [
        items.filter((_, index) => index % 2 === 0).map((item, position) => ({ item, index: position * 2 })),
        items.filter((_, index) => index % 2 === 1).map((item, position) => ({ item, index: position * 2 + 1 })),
    ];

    return (
        <section className={cn('rounded-[34px] px-6 py-8 sm:px-8 sm:py-10 lg:px-12 lg:py-14', dark ? 'bg-[#081326]' : 'bg-[#f4f6fb]')}>
            <h2 className={cn('text-[28px] font-semibold leading-[0.92] tracking-[-0.06em] sm:text-[36px]', dark ? 'text-white' : 'text-[#16233c]')}>
                {title}
                <span className="text-[#7c8cff]">.</span>
            </h2>
            <div className="mt-8 grid gap-5 lg:grid-cols-2 lg:items-start">
                {columns.map((column, columnIndex) => (
                    <div key={`faq-column-${columnIndex}`} className="space-y-5">
                        {column.map(({ item, index }) => {
                            const isOpen = index === openIndex;

                            return (
                                <div
                                    key={item.question}
                                    className={cn(
                                        'rounded-[26px] border px-5 py-5 transition sm:px-6',
                                        dark ? 'border-white/10 bg-white/[0.03]' : 'border-[#d8dfeb] bg-white',
                                    )}
                                >
                                    <button
                                        type="button"
                                        className="flex w-full items-start justify-between gap-4 text-left"
                                        onClick={() => setOpenIndex(isOpen ? -1 : index)}
                                    >
                                        <span className={cn('text-[20px] font-semibold tracking-[-0.04em] sm:text-[24px]', dark ? 'text-white' : 'text-[#16233c]')}>
                                            {item.question}
                                        </span>
                                        <span
                                            className={cn(
                                                'mt-1 inline-flex size-9 shrink-0 items-center justify-center rounded-full border',
                                                dark ? 'border-white/10 bg-white/[0.04]' : 'border-[#d8dfeb] bg-[#f7f9fd]',
                                            )}
                                        >
                                            {isOpen ? (
                                                <Minus className={cn('size-5 shrink-0', dark ? 'text-[#7c8cff]' : 'text-[#54627d]')} />
                                            ) : (
                                                <Plus className={cn('size-5 shrink-0', dark ? 'text-[#8fa0bf]' : 'text-[#54627d]')} />
                                            )}
                                        </span>
                                    </button>
                                    {isOpen ? (
                                        <p className={cn('mt-4 text-[16px] leading-8', dark ? 'text-[#9eacc7]' : 'text-[#4f5f7c]')}>
                                            {item.answer}
                                        </p>
                                    ) : null}
                                </div>
                            );
                        })}
                    </div>
                ))}
            </div>
        </section>
    );
}
