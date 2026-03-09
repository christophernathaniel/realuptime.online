export type FeatureSlug =
    | 'website-monitoring'
    | 'port-monitoring'
    | 'ping-monitoring'
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
        intro: 'Use RealUptime to probe any HTTP or HTTPS page, validate its response, measure latency, and confirm that the service your users depend on is actually available.',
        bullets: [
            'HTTP and HTTPS website checks',
            'Expected status code validation',
            'Redirect handling and response time tracking',
            'Optional authentication and headers on paid workspaces',
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
                question: 'Can I follow redirects?',
                answer: 'Yes. Redirect handling is configurable on HTTP-style monitors so you can monitor the final destination your visitors reach.',
            },
            {
                question: 'Is website monitoring available on the Free plan?',
                answer: 'Yes. Free workspaces can create website checks, but they use the standard check profile with fixed timing and alert defaults.',
            },
        ],
    },
    {
        slug: 'port-monitoring',
        label: 'Port Monitoring',
        shortLabel: 'Port monitoring',
        menuDescription: 'Track TCP ports for databases, brokers, SSH, mail relays, and internal services.',
        icon: 'server',
        eyebrow: 'Port monitoring',
        title: 'Know when the service port is open before users feel the outage.',
        summary: 'Watch raw TCP ports for infrastructure and internal services that do not expose a friendly website endpoint.',
        intro: 'RealUptime port monitoring is built for the operational layer below your website checks. Use it for SSH, databases, cache nodes, brokers, and any TCP service that needs to stay reachable.',
        bullets: [
            'Host and port checks in the format host:port',
            'TCP connect time measurement',
            'Latency thresholds with degraded-performance incidents',
            'Useful alongside HTTP checks for faster root-cause direction',
        ],
        benefitCards: [
            {
                title: 'Infrastructure-first visibility',
                description: 'Validate that the actual service port is reachable even when there is no website to probe.',
            },
            {
                title: 'Separate transport failures from app failures',
                description: 'Pair port checks with website checks to tell whether the outage is at the socket or application layer.',
            },
            {
                title: 'Operationally lightweight',
                description: 'Port checks give teams a fast signal for hosts and services where HTTP assertions would be the wrong abstraction.',
            },
        ],
        useCases: [
            'PostgreSQL and MySQL ports',
            'SSH, SMTP, and IMAP services',
            'Redis, RabbitMQ, and broker ports',
            'Internal TCP services behind VPNs or load balancers',
        ],
        faqs: [
            {
                question: 'How should I format a port target?',
                answer: 'Use a host and port in the format `host:port`, for example `db.example.com:5432`.',
            },
            {
                question: 'Can I alert when a port stays slow?',
                answer: 'Yes. Paid workspaces can apply latency thresholds to port checks and open degraded-performance incidents when connect times stay elevated.',
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
                description: 'Watch routers, gateways, firewalls, VMs, or dedicated hosts that need a direct reachability check.',
            },
            {
                title: 'Fits alongside HTTP monitors',
                description: 'Pair ping with website or HTTP checks to distinguish network loss from application failure.',
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
            'Downtime and degraded-performance incidents',
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
                answer: 'Downtime and degraded-performance incidents are supported today, each with its own severity and timeline.',
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
        description: 'Start with the essentials and monitor the services that matter most with fixed defaults.',
        monthlyPriceLabel: '£0',
        monitors: '10 monitors',
        interval: '5 minute checks',
        cta: 'Get started for free',
        features: [
            { label: 'HTTP, port, and ping checks', included: true },
            { label: 'Fixed defaults for timing and alert policy', included: true },
            { label: 'Response-time charts and incident history', included: true },
            { label: 'Public status pages, maintenance, team, and integrations', included: false },
            { label: 'Custom check configuration', included: false },
            { label: 'Downtime webhooks', included: false },
        ],
    },
    {
        key: 'premium',
        name: 'Premium',
        description: 'Unlock full check customization and the rest of the operational workspace.',
        monthlyPriceLabel: '£5.99',
        badge: 'Most popular',
        monitors: '50 monitors',
        interval: '30 second checks',
        featured: true,
        cta: 'Start Premium',
        features: [
            { label: 'Everything in Free', included: true },
            { label: 'Custom HTTP, port, and ping configuration', included: true },
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
        answer: 'RealUptime supports HTTP(S), port, and ping checks. Paid workspaces can customize those checks in depth, while Free workspaces use a fixed operational profile.',
    },
    {
        question: 'Do I need a paid plan to start?',
        answer: 'No. The Free plan includes up to 10 monitors with the standard check profile. Premium and Ultra unlock full check customization, advanced workspace features, and higher monitor limits.',
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
            { label: 'Port Monitoring', href: '/features/port-monitoring' },
            { label: 'Ping Monitoring', href: '/features/ping-monitoring' },
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
            { label: 'Port Checks', href: '/features/port-monitoring' },
        ],
    },
];
