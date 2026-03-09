import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <rect x="6.5" y="6.5" width="27" height="27" rx="8.5" stroke="#EAF0F9" strokeWidth="3" />
            <path
                d="M12 25.5L17.5 20L22 22.8L28.5 14.8"
                stroke="#57C7C2"
                strokeWidth="3.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <circle cx="28.5" cy="14.8" r="2.8" fill="#7C8CFF" />
        </svg>
    );
}
