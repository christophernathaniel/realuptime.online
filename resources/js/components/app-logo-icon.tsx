import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
            <rect x="7" y="8" width="10" height="32" rx="5" fill="#57C7C2" />
            <rect x="19" y="8" width="10" height="32" rx="5" fill="#7C8CFF" />
            <rect x="31" y="8" width="10" height="32" rx="5" fill="#EAF0F9" />
        </svg>
    );
}
