import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

type PaginationStripProps = {
    currentPage: number;
    lastPage: number;
    from: number | null;
    to: number | null;
    total: number;
    previousPageUrl: string | null;
    nextPageUrl: string | null;
    className?: string;
};

export function PaginationStrip({
    currentPage,
    lastPage,
    from,
    to,
    total,
    previousPageUrl,
    nextPageUrl,
    className,
}: PaginationStripProps) {
    if (lastPage <= 1) {
        return null;
    }

    return (
        <div className={cn('flex flex-col gap-3 border-t border-white/8 pt-5 sm:flex-row sm:items-center sm:justify-between', className)}>
            <div className="text-sm text-[#8fa0bf]">
                Showing {from ?? 0}-{to ?? 0} of {total}
            </div>
            <div className="flex items-center gap-2">
                {previousPageUrl ? (
                    <Link
                        href={previousPageUrl}
                        preserveScroll
                        className="inline-flex h-10 items-center rounded-[14px] border border-[#2b3544] bg-[#171d28] px-4 text-sm text-white"
                    >
                        Previous
                    </Link>
                ) : (
                    <span className="inline-flex h-10 items-center rounded-[14px] border border-[#2b3544] bg-[#111723] px-4 text-sm text-[#64748b]">
                        Previous
                    </span>
                )}
                <div className="px-2 text-sm text-[#8fa0bf]">
                    Page {currentPage} of {lastPage}
                </div>
                {nextPageUrl ? (
                    <Link
                        href={nextPageUrl}
                        preserveScroll
                        className="inline-flex h-10 items-center rounded-[14px] border border-[#2b3544] bg-[#171d28] px-4 text-sm text-white"
                    >
                        Next
                    </Link>
                ) : (
                    <span className="inline-flex h-10 items-center rounded-[14px] border border-[#2b3544] bg-[#111723] px-4 text-sm text-[#64748b]">
                        Next
                    </span>
                )}
            </div>
        </div>
    );
}
