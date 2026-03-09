import { useMemo } from 'react';
import type { ResponsePoint } from '@/types/monitoring';

const padding = { top: 18, right: 18, bottom: 42, left: 62 };

export function ResponseTimeChart({ points }: { points: ResponsePoint[] }) {
    const width = 760;
    const height = 260;

    const { circles, max, labels, segments } = useMemo(() => {
        type PositionedPoint = ResponsePoint & { x: number; y: number };

        if (points.length === 0) {
            return {
                circles: [] as PositionedPoint[],
                max: 4000,
                labels: [] as PositionedPoint[],
                segments: [] as Array<{ key: string; x1: number; y1: number; x2: number; y2: number; stroke: string }>,
            };
        }

        const maxValue = Math.max(...points.map((point) => point.value ?? 0), 4000);
        const xStep = points.length > 1 ? (width - padding.left - padding.right) / (points.length - 1) : 0;
        const yScale = (height - padding.top - padding.bottom) / maxValue;

        const positioned: PositionedPoint[] = points.map((point, index) => {
            const x = padding.left + index * xStep;
            const fallbackValue = point.status === 'down' ? maxValue : 0;
            const value = point.value ?? fallbackValue;
            const y = height - padding.bottom - value * yScale;
            return { ...point, x, y };
        });

        const segments = positioned.slice(1).map((point, index) => {
            const previous = positioned[index];

            return {
                key: `${previous.label}-${point.label}`,
                x1: previous.x,
                y1: previous.y,
                x2: point.x,
                y2: point.y,
                stroke: point.status === 'down' || previous.status === 'down'
                    ? '#ff6269'
                    : (point.status === 'warning' || previous.status === 'warning' ? '#f7b84b' : '#3ee072'),
            };
        });

        return {
            circles: positioned,
            max: maxValue,
            labels: positioned.filter((_, index) => index === 0 || index === Math.floor((positioned.length - 1) / 2) || index === positioned.length - 1),
            segments,
        };
    }, [points]);

    const yLabels = [0, Math.round(max * 0.25), Math.round(max * 0.5), Math.round(max * 0.75), max].reverse();

    return (
        <div className="rounded-[28px] border border-white/6 bg-[#1a2339]/95 p-6 shadow-[0_24px_60px_rgba(0,0,0,0.22)]">
            <svg viewBox={`0 0 ${width} ${height}`} className="h-[260px] w-full overflow-visible lg:h-[280px]">
                {yLabels.map((label, index) => {
                    const y = padding.top + ((height - padding.top - padding.bottom) / (yLabels.length - 1)) * index;
                    return (
                        <g key={label}>
                            <line x1={padding.left} x2={width - padding.right} y1={y} y2={y} stroke="rgba(151, 165, 192, 0.18)" />
                            <text x={14} y={y + 4} fill="#7582a0" fontSize="14">
                                {label}ms
                            </text>
                        </g>
                    );
                })}

                {circles.length > 0 ? (
                    <>
                        <polyline fill="none" stroke="#2a3652" strokeWidth="3" points={`${padding.left},${height - padding.bottom} ${width - padding.right},${height - padding.bottom}`} />
                        {circles.map((point) =>
                            point.status === 'up' ? null : (
                                <rect
                                    key={`bar-${point.label}`}
                                    x={point.x - 7}
                                    y={padding.top}
                                    width="14"
                                    height={height - padding.top - padding.bottom}
                                    rx="7"
                                    fill={point.status === 'down' ? 'rgba(255,98,105,0.18)' : 'rgba(247,184,75,0.16)'}
                                />
                            ),
                        )}
                        {segments.map((segment) => (
                            <line
                                key={segment.key}
                                x1={segment.x1}
                                y1={segment.y1}
                                x2={segment.x2}
                                y2={segment.y2}
                                stroke={segment.stroke}
                                strokeWidth="3.5"
                                strokeLinecap="round"
                            />
                        ))}
                        {circles.map((point) => (
                            <circle
                                key={point.label}
                                cx={point.x}
                                cy={point.y}
                                r="5.5"
                                fill={point.status === 'down' ? '#ffe3e5' : point.status === 'warning' ? '#fff2d7' : '#d8ffdf'}
                                stroke={point.status === 'down' ? '#ff6269' : point.status === 'warning' ? '#f7b84b' : '#3ee072'}
                                strokeWidth="3"
                            />
                        ))}
                    </>
                ) : null}

                {labels.map((point) => (
                    <text key={point.label} x={point.x} y={height - 8} fill="#7582a0" fontSize="14" textAnchor={point.x === padding.left ? 'start' : point.x === width - padding.right ? 'end' : 'middle'}>
                        {point.shortLabel}
                    </text>
                ))}
            </svg>
        </div>
    );
}
