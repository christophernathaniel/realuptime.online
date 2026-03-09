import { AlertCircleIcon } from 'lucide-react';

export default function AlertError({
    errors,
    title,
}: {
    errors: string[];
    title?: string;
}) {
    return (
        <div className="rounded-[20px] border border-[#ff6b75]/18 bg-[#2a1621] p-4 text-[#ffe6e8]">
            <div className="flex items-start gap-3">
                <AlertCircleIcon className="mt-0.5 size-4 shrink-0 text-[#ff8d95]" />
                <div>
                    <div className="text-sm font-semibold">
                        {title || 'Something went wrong.'}
                    </div>
                    <ul className="mt-2 list-inside list-disc space-y-1 text-sm text-[#f4c7ca]">
                        {Array.from(new Set(errors)).map((error, index) => (
                            <li key={index}>{error}</li>
                        ))}
                    </ul>
                </div>
            </div>
        </div>
    );
}
