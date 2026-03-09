const markers: Record<string, { x: number; y: number }> = {
    'North America': { x: 76, y: 82 },
    Europe: { x: 162, y: 68 },
    'Asia Pacific': { x: 228, y: 90 },
};

export function RegionMap({ region }: { region: string }) {
    const activeRegion = Object.hasOwn(markers, region) ? region : 'North America';
    const active = markers[activeRegion];

    return (
        <svg viewBox="0 0 300 180" className="w-full" aria-label={`${region} monitoring region`}>
            <g fill="none" stroke="#7b88a8" strokeWidth="2.2" opacity="0.9">
                <path d="M24 56c12-10 28-16 48-18l18 8 16 2 8 10 10 2 8 16-10 12-14 4-12 12-6 18-14-4-10-20-16-6-10-20Z" />
                <path d="M154 52l18-8 18 8 16-4 28 10 26-2 8 12-6 12-20 2-12 10 2 20-16 16-12-2-10-18-22-8-10-16-8-2-4-18 8-8Z" />
                <path d="M114 118l12 8 8 20 18 20-10 8-18-6-10-20-6-22Z" />
                <path d="M218 116l12 10 8 18-12 18-16-8-4-20Z" />
            </g>
            {Object.entries(markers).map(([label, marker]) => (
                <circle
                    key={label}
                    cx={marker.x}
                    cy={marker.y}
                    r={label === activeRegion ? 8 : 4}
                    fill={label === activeRegion ? '#3ee072' : '#7b88a8'}
                    opacity={label === activeRegion ? 1 : 0.6}
                />
            ))}
            <circle cx={active.x} cy={active.y} r="18" fill="#3ee072" opacity="0.16" />
            <circle cx={active.x} cy={active.y} r="10" fill="#3ee072" opacity="0.28" />
        </svg>
    );
}
