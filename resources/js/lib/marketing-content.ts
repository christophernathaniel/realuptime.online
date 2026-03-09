export type FeatureSlug =
    | 'website-monitoring'
    | 'endpoint-monitoring'
    | 'ping-monitoring'
    | 'ssl-monitoring'
    | 'incidents-management'
    | 'public-status-pages'
    | 'it-alerting-software';

export type MarketingFeature = {
    slug: FeatureSlug;
    label: string;
    shortLabel: string;
    menuDescription: string;
    icon: string;
    eyebrow: string;
    title: string;
    summary: string;
    intro: string;
    bullets: string[];
    benefitCards: Array<{ title: string; description: string }>;
    useCases: string[];
    faqs: Array<{ question: string; answer: string }>;
};

export type MarketingPlan = {
    key: 'free' | 'premium' | 'ultra';
    name: string;
    description: string;
    monthlyPriceLabel: string;
    badge?: string;
    monitors: string;
    interval: string;
    featured?: boolean;
    cta: string;
    features: Array<{ label: string; included: boolean; muted?: boolean }>;
};

export const marketingFeatures: MarketingFeature[] = [
    {
        slug: 'website-monitoring',
        label: 'Website Monitoring',
        shortLabel: 'Website monitoring',
        menuDescription: 'Watch websites, landing pages, and customer-facing flows for uptime and performance.',
        icon: 'globe',
        eyebrow: 'Website monitoring',
        title: 'Catch downtime before your visitors do.',
        summary: 'Monitor public websites, storefronts, docs portals, and sign-in pages with fast HTTP checks and clear incident history.',
        intro: 'Use RealUptime to probe any HTTP or HTTPS page, validate its response, measure latency, and confirm that the content your users depend on is actually available.',
        bullets: [
            'HTTP and HTTPS endpoint checks',
            'Expected status code validation',
            'Redirect handling and response time tracking',
            'Keyword checks for rendered content markers',
        ],
        benefitCards: [
            {
                title: 'Monitor the page, not just the host',
                description: 'Confirm that a live page returns the right status code and expected content rather than assuming the server is healthy.',
            },
            {
                title: 'Fast incident timelines',
                description: 'Every failed check is recorded with timing, status details, and incident history so you can explain what happened.',
            },
            {
                title: 'Clear uptime reporting',
                description: 'Track last 24 hours, 7 days, 30 days, and response-time ranges from the same monitor detail page.',
            },
        ],
        useCases: [
            'Marketing sites and homepages',
            'Sign-in and checkout pages',
            'Customer portals and dashboards',
            'Documentation and status portals',
        ],
        faqs: [
            {
                question: 'Can I validate page content as well as uptime?',
                answer: 'Yes. Website monitors can check for expected status codes and, when needed, validate expected keywords so you know the right content is present.',
            },
            {
                question: 'Can I follow redirects?',
                answer: 'Yes. Redirect handling is configurable on HTTP-style monitors so you can monitor the final destination your visitors reach.',
            },
        ],
    },
    {
        slug: 'endpoint-monitoring',
        label: 'Endpoint Monitoring',
        shortLabel: 'Endpoint monitoring',
        menuDescription: 'Track APIs and service endpoints with methods, headers, auth, latency thresholds, and assertions.',
        icon: 'webhook',
        eyebrow: 'Endpoint monitoring',
        title: 'Keep APIs, webhooks, and service endpoints healthy.',
        summary: 'Monitor the application edge your systems depend on with request methods, headers, auth credentials, timeout rules, and performance alerts.',
        intro: 'RealUptime endpoint monitoring is built for JSON APIs, internal services, webhook receivers, and any HTTP endpoint that needs to stay fast and available.',
        bullets: [
            'GET and POST request support',
            'Custom headers and basic auth',
            'Latency thresholds with degraded-performance incidents',
            'Expected status code and keyword matching',
        ],
        benefitCards: [
            {
                title: 'Useful for internal and public APIs',
                description: 'Track REST endpoints, webhook receivers, health endpoints, and machine-to-machine services from one workflow.',
            },
            {
                title: 'Alert on slowdowns, not just outages',
                description: 'Performance incidents open when latency stays above your threshold for consecutive checks.',
            },
            {
                title: 'Flexible request configuration',
                description: 'Set headers, auth, timeouts, and validation rules to match the contract your clients rely on.',
            },
        ],
        useCases: [
            'REST and JSON APIs',
            'Webhook receivers',
            'Authentication endpoints',
            'Health checks for containers and services',
        ],
        faqs: [
            {
                question: 'Can I monitor authenticated endpoints?',
                answer: 'Yes. Endpoint monitors support custom headers and basic authentication for endpoints that are not publicly open.',
            },
            {
                question: 'Can I alert on slow APIs?',
                answer: 'Yes. You can set a latency threshold and require multiple slow checks before opening a degraded-performance incident.',
            },
        ],
    },
    {
        slug: 'ping-monitoring',
        label: 'Ping Monitoring',
        shortLabel: 'Ping monitoring',
        menuDescription: 'Track host reachability and latency for servers, gateways, edge devices, and infrastructure targets.',
        icon: 'activity',
        eyebrow: 'Ping monitoring',
        title: 'Know when a server or network target stops responding.',
        summary: 'Use ICMP checks to confirm that a host is reachable and to measure baseline latency for infrastructure you depend on.',
        intro: 'Ping monitoring gives you a direct way to watch network reachability for hosts, appliances, and infrastructure where HTTP is not the right signal.',
        bullets: [
            'Hostname or IP-based reachability checks',
            'Packet count and timeout controls',
            'Average latency tracking',
            'Optional degraded-performance incidents for high latency',
        ],
        benefitCards: [
            {
                title: 'Simple infrastructure signal',
                description: 'Track whether a host answers at all before you dig into application-layer diagnostics.',
            },
            {
                title: 'Useful for bare-metal and network edges',
                description: 'Watch routers, gateways, firewalls, VMs, or dedicated hosts that need a reachability heartbeat.',
            },
            {
                title: 'Fits alongside HTTP monitors',
                description: 'Pair ping with website or endpoint monitoring to distinguish network loss from application failure.',
            },
        ],
        useCases: [
            'Public servers and virtual machines',
            'VPN gateways and bastions',
            'Load balancers and edge devices',
            'Infrastructure providers and upstream hosts',
        ],
        faqs: [
            {
                question: 'Does RealUptime track ping latency?',
                answer: 'Yes. Ping monitors record average latency and can open degraded-performance incidents when that latency exceeds your threshold.',
            },
            {
                question: 'Should I use ping or HTTP checks?',
                answer: 'Use ping when you need network reachability. Use HTTP when you need to validate application responses. Many teams use both together.',
            },
        ],
    },
    {
        slug: 'ssl-monitoring',
        label: 'SSL Monitoring',
        shortLabel: 'SSL monitoring',
        menuDescription: 'Track TLS certificate expiry, issuer details, and domain expiry thresholds from the same monitor.',
        icon: 'shield',
        eyebrow: 'SSL monitoring',
        title: 'Stay ahead of certificate and domain expiry.',
        summary: 'RealUptime records certificate validity, issuer details, domain expiry windows, and raises incidents before expiry becomes a production issue.',
        intro: 'SSL monitoring is built for teams that want certificate visibility without another tool. Track upcoming expiry windows and see issuer and registrar context directly in the monitor page.',
        bullets: [
            'SSL certificate expiry thresholds',
            'Certificate issuer tracking',
            'Domain expiry thresholds',
            'Expiry incidents and notifications',
        ],
        benefitCards: [
            {
                title: 'Prevent silent certificate failures',
                description: 'Receive alerts before TLS expiry interrupts your website, API, or internal service.',
            },
            {
                title: 'Track domain ownership metadata',
                description: 'Keep registrar and domain expiry details visible from the same place you track uptime.',
            },
            {
                title: 'Use a single monitor view',
                description: 'Combine uptime, SSL validity, and domain-expiry information in the same operational workflow.',
            },
        ],
        useCases: [
            'HTTPS websites and APIs',
            'Custom domains for customer instances',
            'Renewal planning for shared infrastructure',
            'Operational checks for DNS and TLS handovers',
        ],
        faqs: [
            {
                question: 'Can I alert before a certificate expires?',
                answer: 'Yes. You can set the threshold in days and RealUptime will open an SSL expiry incident when the monitor crosses it.',
            },
            {
                question: 'Does RealUptime also track domain expiry?',
                answer: 'Yes. HTTP, keyword, SSL, and synthetic monitors can also evaluate domain expiry and raise a separate incident when it approaches.',
            },
        ],
    },
    {
        slug: 'incidents-management',
        label: 'Incidents Management',
        shortLabel: 'Incident management',
        menuDescription: 'Track first failure, retries, recovery, notes, root cause, and notification history in one place.',
        icon: 'siren',
        eyebrow: 'Incident management',
        title: 'Turn failed checks into incidents your team can work from.',
        summary: 'When checks fail, RealUptime opens incidents, records retries, stores notification history, and gives operators a place to capture notes and root cause.',
        intro: 'RealUptime treats incidents as first-class records. That means your team can review what happened, when the first failure appeared, which retries happened, and how alerts were sent.',
        bullets: [
            'Downtime, degraded-performance, SSL, and domain-expiry incidents',
            'Retry timeline and latest check details',
            'Operator notes and root cause summary fields',
            'Notification history linked to each incident',
        ],
        benefitCards: [
            {
                title: 'Useful detail, not just a red status',
                description: 'Open incidents include first failed checks, last good checks, retry sequences, and the latest recovery state.',
            },
            {
                title: 'Clear operational handoff',
                description: 'Use operator notes and root cause summaries to keep follow-up work attached to the incident itself.',
            },
            {
                title: 'Built for auditability',
                description: 'Notification history is stored alongside the incident so you can see exactly what was sent and when.',
            },
        ],
        useCases: [
            'Post-incident review',
            'Hand-off between responders',
            'Customer communication workflows',
            'Tracking recurring failures and recoveries',
        ],
        faqs: [
            {
                question: 'What types of incidents can RealUptime open?',
                answer: 'Downtime, degraded-performance, SSL-expiry, and domain-expiry incidents are supported today, each with its own severity and timeline.',
            },
            {
                question: 'Can I add notes to an incident?',
                answer: 'Yes. Incident detail pages include operator notes and a root cause summary field for postmortem-style context.',
            },
        ],
    },
    {
        slug: 'public-status-pages',
        label: 'Public Status Pages',
        shortLabel: 'Public status pages',
        menuDescription: 'Publish uptime, incidents, and maintenance updates on branded status pages for your users.',
        icon: 'broadcast',
        eyebrow: 'Public status pages',
        title: 'Publish real-time service health without another tool.',
        summary: 'Create public status pages that reflect your live monitor state, active incidents, and scheduled maintenance windows from the same workspace.',
        intro: 'Status pages in RealUptime are connected directly to your monitors. That means service health, incident updates, and maintenance windows stay in sync with your operational view.',
        bullets: [
            'Per-user public status pages',
            'Published or draft page controls',
            'Manual incident updates and lifecycle posts',
            'Maintenance windows included in the page view',
        ],
        benefitCards: [
            {
                title: 'Share the same truth your team sees',
                description: 'Public pages pull from monitor health, active incidents, and maintenance schedules so customers see the current operational state.',
            },
            {
                title: 'Manual updates when nuance matters',
                description: 'Post investigating, identified, monitoring, and resolved updates when your team needs to add context.',
            },
            {
                title: 'Built into the product flow',
                description: 'No separate service is required to publish live status information from the monitors you already manage.',
            },
        ],
        useCases: [
            'Customer-facing uptime pages',
            'Internal service health dashboards',
            'Maintenance and rollout notices',
            'Operational status communication during incidents',
        ],
        faqs: [
            {
                question: 'Can I publish incident updates manually?',
                answer: 'Yes. Status pages support incident posts and updates such as investigating, identified, monitoring, and resolved.',
            },
            {
                question: 'Are status pages tied to specific users?',
                answer: 'Yes. Public status pages are scoped per user workspace so the same slug can exist safely under different accounts.',
            },
        ],
    },
    {
        slug: 'it-alerting-software',
        label: 'IT Alerting Software',
        shortLabel: 'IT alerting software',
        menuDescription: 'Send email alerts, escalations, and downtime webhooks when checks fail or performance degrades.',
        icon: 'bell',
        eyebrow: 'IT alerting software',
        title: 'Escalate outages with alerts your team can act on.',
        summary: 'RealUptime sends email alerts, recovery alerts, critical downtime escalations, and premium webhook notifications so responders get the right signal quickly.',
        intro: 'The alerting workflow is built around operational incidents. That means teams see what failed, how long it has been down, and what changed when recovery happens.',
        bullets: [
            'Email alerts and recovery notifications',
            'Critical downtime escalation after a chosen threshold',
            'Degraded-performance alerts for sustained slow responses',
            'Premium and Ultra downtime webhooks per monitor',
        ],
        benefitCards: [
            {
                title: 'Signal with context',
                description: 'Alerts tie directly back to incidents, monitors, and notification history so the message is only the first step.',
            },
            {
                title: 'Escalate when downtime continues',
                description: 'Critical alerts can trigger after a monitor remains down past your configured downtime threshold.',
            },
            {
                title: 'Webhook delivery for paid workspaces',
                description: 'Premium and Ultra workspaces can POST downtime payloads to operational systems whenever a monitor goes down.',
            },
        ],
        useCases: [
            'Operations and support teams',
            'Escalations to incident tooling',
            'Fallback workflows for maintenance and recovery',
            'Alerting on slow endpoints before a full outage',
        ],
        faqs: [
            {
                question: 'Which channels are supported today?',
                answer: 'Email is built in for all workspaces. Premium and Ultra workspaces can also configure monitor-level downtime webhooks.',
            },
            {
                question: 'Can I alert on slow services before they go down?',
                answer: 'Yes. RealUptime supports degraded-performance incidents driven by latency thresholds and consecutive slow checks.',
            },
        ],
    },
];

export const marketingFeatureMap = Object.fromEntries(marketingFeatures.map((feature) => [feature.slug, feature])) as Record<FeatureSlug, MarketingFeature>;

export const marketingPlans: MarketingPlan[] = [
    {
        key: 'free',
        name: 'Free',
        description: 'Start with the essentials and monitor the services that matter most.',
        monthlyPriceLabel: '£0',
        monitors: '3 monitors',
        interval: '5 minute checks',
        cta: 'Get started for free',
        features: [
            { label: 'HTTP, ping, keyword, SSL, heartbeat, and synthetic monitors', included: true },
            { label: 'Response-time charts and incident history', included: true },
            { label: 'Email alerts to your workspace email', included: true },
            { label: 'Public status pages, maintenance, team, and integrations', included: false },
            { label: 'Downtime webhooks', included: false },
        ],
    },
    {
        key: 'premium',
        name: 'Premium',
        description: 'Unlock the full workspace for growing production systems and teams.',
        monthlyPriceLabel: '£5.99',
        badge: 'Most popular',
        monitors: '25 monitors',
        interval: '30 second checks',
        featured: true,
        cta: 'Start Premium',
        features: [
            { label: 'Everything in Free', included: true },
            { label: 'Public status pages and incident updates', included: true },
            { label: 'Maintenance windows and notification contacts', included: true },
            { label: 'Shared workspaces, API tokens, and downtime webhooks', included: true },
            { label: 'Built for small production teams', included: true, muted: true },
        ],
    },
    {
        key: 'ultra',
        name: 'Ultra',
        description: 'Scale the same workflow across larger estates without changing how you operate.',
        monthlyPriceLabel: '£15.99',
        monitors: '200 monitors',
        interval: '30 second checks',
        cta: 'Start Ultra',
        features: [
            { label: 'Everything in Premium', included: true },
            { label: '200 monitor capacity', included: true },
            { label: 'The same alerting and status-page workflow at higher scale', included: true },
            { label: 'Ideal for agencies, portfolios, and larger estates', included: true, muted: true },
        ],
    },
];

export const marketingFaqs = [
    {
        question: 'What can I monitor with RealUptime?',
        answer: 'RealUptime supports HTTP(S), endpoint, ping, keyword, SSL certificate, heartbeat, and synthetic transaction monitors from the same workspace.',
    },
    {
        question: 'Do I need a paid plan to start?',
        answer: 'No. The Free plan includes core monitoring with up to 3 monitors. Premium and Ultra unlock advanced workspace features and higher monitor limits.',
    },
    {
        question: 'How are alerts delivered?',
        answer: 'Email alerts are built in. Premium and Ultra workspaces can also add monitor-level downtime webhooks for operational systems.',
    },
    {
        question: 'Can I share service status publicly?',
        answer: 'Yes. Paid workspaces can publish public status pages with live monitor health, incident updates, and maintenance notices.',
    },
    {
        question: 'Can I cancel later?',
        answer: 'Yes. You can manage or cancel your subscription from the billing settings area. Your workspace will return to the Free plan at the end of the billing period.',
    },
    {
        question: 'How am I billed?',
        answer: 'Paid plans are billed monthly. RealUptime does not currently offer annual billing on the public self-serve plans.',
    },
];

export const footerGroups = [
    {
        title: 'Monitoring',
        links: [
            { label: 'Website Monitoring', href: '/features/website-monitoring' },
            { label: 'Endpoint Monitoring', href: '/features/endpoint-monitoring' },
            { label: 'Ping Monitoring', href: '/features/ping-monitoring' },
            { label: 'SSL Monitoring', href: '/features/ssl-monitoring' },
            { label: 'Incident Management', href: '/features/incidents-management' },
            { label: 'Public Status Pages', href: '/features/public-status-pages' },
        ],
    },
    {
        title: 'Product',
        links: [
            { label: 'Pricing', href: '/pricing' },
            { label: 'Features', href: '/features' },
            { label: 'IT Alerting Software', href: '/features/it-alerting-software' },
            { label: 'Login', href: '/login' },
            { label: 'Get Started', href: '/register' },
        ],
    },
    {
        title: 'Company',
        links: [
            { label: 'About', href: '/about' },
            { label: 'Careers', href: '/careers' },
            { label: 'Privacy Policy', href: '/privacy-policy' },
            { label: 'Terms & Conditions', href: '/terms-and-conditions' },
        ],
    },
    {
        title: 'Resources',
        links: [
            { label: 'FAQs', href: '/pricing#faqs' },
            { label: 'Status Pages', href: '/features/public-status-pages' },
            { label: 'Alerting', href: '/features/it-alerting-software' },
            { label: 'Synthetic Monitoring', href: '/features/endpoint-monitoring' },
        ],
    },
];
