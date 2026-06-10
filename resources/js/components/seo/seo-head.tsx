import { Head, usePage } from '@inertiajs/react';

export type SeoStructuredData = Record<string, unknown>;

export type SeoBreadcrumb = {
    name: string;
    path: string;
};

type SeoHeadProps = {
    title: string;
    description?: string;
    canonicalPath?: string;
    imagePath?: string;
    imageAlt?: string;
    type?: 'website' | 'article';
    keywords?: string[];
    noIndex?: boolean;
    breadcrumbs?: SeoBreadcrumb[];
    structuredData?: SeoStructuredData | SeoStructuredData[];
    includeSiteSchemas?: boolean;
};

type SharedSeoProps = {
    appUrl?: string;
    name?: string;
};

function absoluteUrl(baseUrl: string, path: string): string {
    try {
        return new URL(path, baseUrl).toString();
    } catch {
        return path;
    }
}

export function SeoHead({
    title,
    description,
    canonicalPath,
    imagePath = '/apple-touch-icon.png',
    imageAlt = 'RealUptime preview',
    type = 'website',
    keywords,
    noIndex = false,
    breadcrumbs,
    structuredData,
    includeSiteSchemas = false,
}: SeoHeadProps) {
    const page = usePage<SharedSeoProps>();
    const siteName = page.props.name ?? 'RealUptime';
    const configuredBaseUrl = page.props.appUrl?.trim() || (typeof window !== 'undefined' ? window.location.origin : 'http://localhost');
    const baseUrl = configuredBaseUrl.endsWith('/') ? configuredBaseUrl : `${configuredBaseUrl}/`;
    const pagePath = canonicalPath ?? page.url;
    const canonicalUrl = new URL(pagePath, baseUrl);

    canonicalUrl.search = '';
    canonicalUrl.hash = '';

    const resolvedDescription = description ?? 'RealUptime helps teams monitor websites, services, incidents, and public status communication from one operational workspace.';
    const fullTitle = title.includes(siteName) ? title : `${title} | ${siteName}`;
    const imageUrl = absoluteUrl(baseUrl, imagePath);
    const robots = noIndex
        ? 'noindex, nofollow'
        : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    const breadcrumbSchema = breadcrumbs && breadcrumbs.length > 0
        ? {
            '@context': 'https://schema.org',
            '@type': 'BreadcrumbList',
            itemListElement: breadcrumbs.map((breadcrumb, index) => ({
                '@type': 'ListItem',
                position: index + 1,
                name: breadcrumb.name,
                item: absoluteUrl(baseUrl, breadcrumb.path),
            })),
        }
        : null;
    const defaultSchemas: SeoStructuredData[] = includeSiteSchemas
        ? [
            {
                '@context': 'https://schema.org',
                '@type': 'Organization',
                name: siteName,
                url: configuredBaseUrl,
                logo: imageUrl,
            },
            {
                '@context': 'https://schema.org',
                '@type': 'WebSite',
                name: siteName,
                url: configuredBaseUrl,
            },
        ]
        : [];
    const customSchemas = structuredData
        ? (Array.isArray(structuredData) ? structuredData : [structuredData])
        : [];
    const jsonLd = [...defaultSchemas, ...customSchemas, ...(breadcrumbSchema ? [breadcrumbSchema] : [])];

    return (
        <Head title={fullTitle}>
            <meta head-key="meta-description" name="description" content={resolvedDescription} />
            {keywords && keywords.length > 0 ? (
                <meta head-key="meta-keywords" name="keywords" content={keywords.join(', ')} />
            ) : null}
            <meta head-key="meta-robots" name="robots" content={robots} />
            <link head-key="canonical" rel="canonical" href={canonicalUrl.toString()} />

            <meta head-key="og-type" property="og:type" content={type} />
            <meta head-key="og-title" property="og:title" content={fullTitle} />
            <meta head-key="og-description" property="og:description" content={resolvedDescription} />
            <meta head-key="og-url" property="og:url" content={canonicalUrl.toString()} />
            <meta head-key="og-site-name" property="og:site_name" content={siteName} />
            <meta head-key="og-locale" property="og:locale" content="en_GB" />
            <meta head-key="og-image" property="og:image" content={imageUrl} />
            <meta head-key="og-image-alt" property="og:image:alt" content={imageAlt} />
            <meta head-key="og-image-width" property="og:image:width" content="180" />
            <meta head-key="og-image-height" property="og:image:height" content="180" />

            <meta head-key="twitter-card" name="twitter:card" content="summary_large_image" />
            <meta head-key="twitter-title" name="twitter:title" content={fullTitle} />
            <meta head-key="twitter-description" name="twitter:description" content={resolvedDescription} />
            <meta head-key="twitter-image" name="twitter:image" content={imageUrl} />
            <meta head-key="twitter-image-alt" name="twitter:image:alt" content={imageAlt} />

            {jsonLd.map((item, index) => (
                <script
                    key={index}
                    head-key={`json-ld-${index}`}
                    type="application/ld+json"
                    dangerouslySetInnerHTML={{ __html: JSON.stringify(item) }}
                />
            ))}
        </Head>
    );
}
