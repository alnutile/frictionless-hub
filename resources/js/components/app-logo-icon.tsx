import { SVGAttributes, useId } from 'react';

/**
 * Brand mark: a puck gliding over a smooth surface, motion with nothing slowing it down.
 * Keep in sync with public/favicon.svg.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    const id = useId();
    const bg = `${id}-bg`;
    const streak = `${id}-streak`;

    return (
        <svg {...props} viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Frictionless Hub">
            <defs>
                <linearGradient id={bg} x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stopColor="#14b8a6" />
                    <stop offset="1" stopColor="#2563eb" />
                </linearGradient>
                <linearGradient id={streak} x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0" stopColor="#fff" stopOpacity="0" />
                    <stop offset="1" stopColor="#fff" stopOpacity="1" />
                </linearGradient>
            </defs>
            <rect width="512" height="512" rx="116" fill={`url(#${bg})`} />
            <rect x="56" y="206" width="228" height="36" rx="18" fill={`url(#${streak})`} />
            <rect x="104" y="276" width="180" height="36" rx="18" fill={`url(#${streak})`} opacity=".75" />
            <circle cx="332" cy="266" r="104" fill="#fff" />
            <rect x="56" y="384" width="400" height="24" rx="12" fill="#fff" opacity=".5" />
        </svg>
    );
}
