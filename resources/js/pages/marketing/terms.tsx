import MarketingLayout from '@/layouts/marketing-layout';

const sections = [
    {
        title: '1. Scope and acceptance',
        paragraphs: [
            'These terms govern access to the RealUptime website, hosted application, status pages, APIs, billing flows, support interactions, and any related services or content we make available.',
            'By creating an account, purchasing a subscription, accessing a workspace, or otherwise using the service, you agree to these terms on your own behalf or, if you are acting for a company or other organisation, on behalf of that organisation.',
        ],
    },
    {
        title: '2. Accounts, credentials, and workspace responsibility',
        paragraphs: [
            'You are responsible for maintaining the confidentiality and security of your credentials, recovery methods, devices, inboxes, tokens, OAuth connections, and any collaborators or automation connected to your workspace.',
            'You are responsible for all activity that occurs through your account or workspace unless and to the extent a loss directly results from our own breach of a legal duty that cannot lawfully be excluded or limited.',
        ],
        bullets: [
            'Keep passwords, passkeys, recovery codes, tokens, and connected email accounts secure.',
            'Review invited users, workspace members, and connected services regularly.',
            'Notify RealUptime promptly if you suspect unauthorised access, credential compromise, or abuse.',
        ],
    },
    {
        title: '3. Authorised use only',
        paragraphs: [
            'You may only monitor targets, networks, ports, hosts, and services that you own, operate, administer, or are otherwise expressly authorised to test.',
            'You must not use RealUptime to generate abusive traffic, bypass access controls, probe targets without permission, harass others, distribute malware, overload third-party systems, or breach applicable law, sanctions, export controls, or contractual restrictions.',
        ],
    },
    {
        title: '4. Monitoring results, notifications, and operational reliance',
        paragraphs: [
            'RealUptime is an operational aid, not a guaranteed emergency, safety-critical, dispatch, or compliance notification system. Monitoring data, status signals, and alerts may be delayed, filtered, throttled, blocked, duplicated, misclassified, or not delivered at all.',
            'You remain responsible for deciding whether and how to rely on the service in your own environment. You should maintain independent escalation paths, on-call procedures, backups, and business continuity arrangements appropriate to your use case.',
        ],
        bullets: [
            'We do not guarantee that any notification will be generated, transmitted, delivered, received, opened, or acted upon.',
            'We are not responsible for missed, delayed, filtered, bounced, quarantined, blocked, or undelivered notifications.',
            'We are not responsible for customer losses caused by reliance on incomplete, inaccurate, delayed, duplicated, or missing monitoring or alert data.',
        ],
    },
    {
        title: '5. Customer data, exports, retention, and backups',
        paragraphs: [
            'You remain responsible for the legality, accuracy, and appropriateness of the configuration data, target addresses, contact details, incident updates, and public status content you submit to the service.',
            'RealUptime is not an archival or records-management service and is not a substitute for your own backup, export, disaster recovery, legal hold, or retention processes.',
        ],
        bullets: [
            'You should keep independent copies of any data or records you cannot afford to lose.',
            'We may apply retention windows, pruning rules, aggregation, or deletion policies to logs, check history, or operational records.',
            'We are not responsible for data loss, corruption, deletion, overwrite, retention expiry, or inability to recover historical data except to the extent liability cannot lawfully be excluded.',
        ],
    },
    {
        title: '6. Service changes, feature withdrawal, and shutdown',
        paragraphs: [
            'RealUptime may add, remove, suspend, replace, redesign, or discontinue any feature, integration, plan entitlement, API field, notification path, or service component at any time.',
            'We are under no obligation to maintain any particular feature set, backwards compatibility, interface, workflow, integration, or roadmap item unless we have expressly agreed otherwise in writing.',
        ],
        bullets: [
            'We may pause or discontinue the service, or any part of it, at any time.',
            'We may shut down, migrate, restrict, or take the service offline without advance notice where reasonably necessary for security, maintenance, abuse prevention, supplier issues, legal compliance, or operational reasons.',
            'Where commercially practicable, we may choose to provide notice of material permanent shutdown or plan changes, but we are not obliged to do so.',
        ],
    },
    {
        title: '7. Third-party services and infrastructure',
        paragraphs: [
            'The service depends on networks, registrars, DNS providers, certificate authorities, email providers, OAuth platforms, payment processors, cloud infrastructure, and other third-party services that are outside our control.',
            'We are not responsible for failures, delays, data loss, outages, interception, filtering, suspension, or security events occurring within third-party systems or on the public internet.',
        ],
        bullets: [
            'This includes email delivery providers and recipient mail systems.',
            'This includes DNS, SSL/TLS, routing, ISP, hosting, and cloud provider failures.',
            'This includes third-party APIs, webhook receivers, and connected integrations.',
        ],
    },
    {
        title: '8. Billing, renewals, and cancellations',
        paragraphs: [
            'Paid plans are billed on a recurring subscription basis until cancelled. Fees, taxes, and plan entitlements are shown in the checkout flow, account settings, or pricing page in force at the time of purchase or renewal.',
            'Unless otherwise stated, subscriptions renew automatically. You may cancel future renewals through the account billing interface. Fees already paid are generally non-refundable except where required by applicable law or expressly stated otherwise.',
        ],
    },
    {
        title: '9. Security and unauthorised access',
        paragraphs: [
            'We use measures intended to protect the platform, but no hosted internet service can guarantee immunity from intrusion, denial-of-service events, credential theft, phishing, misconfiguration, malicious insiders, malware, software defects, or third-party infrastructure failure.',
            'To the fullest extent permitted by law, we are not responsible for unauthorised access, account takeover, or data exposure caused by compromised customer credentials, insecure customer devices, insecure recipient inboxes, customer-side integrations, shared credentials, or failures in systems outside our reasonable control.',
        ],
    },
    {
        title: '10. No warranty and no guaranteed fitness for purpose',
        paragraphs: [
            'Except as expressly stated, the service is provided on an “as is” and “as available” basis. We do not warrant that the service will be uninterrupted, error-free, secure, complete, current, compatible with every environment, or suitable for any particular operational, legal, or regulatory requirement.',
            'We do not warrant that the service will detect every outage, latency event, certificate issue, routing problem, dependency failure, or security incident, or that it will avoid false positives, false negatives, duplicate alerts, or delayed updates.',
        ],
    },
    {
        title: '11. Limitation of liability',
        paragraphs: [
            'Nothing in these terms excludes or limits liability for death or personal injury caused by negligence, fraud, fraudulent misrepresentation, or any other liability that cannot lawfully be excluded or limited under applicable law.',
            'Subject to that, to the fullest extent permitted by law, RealUptime will not be liable for any indirect, incidental, special, consequential, exemplary, punitive, or pure economic loss, including loss of profit, revenue, savings, business opportunity, goodwill, reputation, use, contracts, customers, or data.',
        ],
        bullets: [
            'We are not liable for missed, delayed, blocked, or undelivered notifications.',
            'We are not liable for outages, downtime, performance degradation, or withdrawal of the RealUptime service itself.',
            'We are not liable for data loss, corruption, deletion, or inability to recover data.',
            'We are not liable for reliance placed on monitoring outputs, incident states, status pages, or notification workflows.',
            'We are not liable for failures, acts, or omissions of third-party providers or recipient systems.',
            'For business users, our total aggregate liability arising out of or in connection with the service will not exceed the greater of the fees paid by you to RealUptime in the 12 months before the event giving rise to the claim and £100.',
        ],
    },
    {
        title: '12. Indemnity for business users',
        paragraphs: [
            'If you use RealUptime for business purposes or on behalf of an organisation, you agree to indemnify and hold harmless the operator of RealUptime against losses, liabilities, damages, claims, and reasonable costs arising out of your unlawful use of the service, your breach of these terms, your monitoring of targets without authority, your published content, or claims brought by your own users, customers, or counterparties arising from your use of the service.',
        ],
    },
    {
        title: '13. Suspension and termination',
        paragraphs: [
            'You may stop using the service at any time. We may suspend, restrict, or terminate accounts, workspaces, plans, or access where reasonably necessary for abuse prevention, security, suspected fraud, legal compliance, non-payment, supplier issues, inactivity, or protection of the service, our systems, or other users.',
            'Termination or suspension may result in immediate loss of access to workspace data, logs, configuration, or history, subject to any legal obligations we may have that cannot be excluded.',
        ],
    },
    {
        title: '14. Governing law and jurisdiction',
        paragraphs: [
            'These terms are governed by the laws of England and Wales, except where mandatory local consumer law requires otherwise.',
            'Business users agree that the courts of England and Wales will have exclusive jurisdiction over disputes arising out of or in connection with the service. Consumer users may also bring claims in the courts of their home jurisdiction to the extent mandatory law gives them that right.',
        ],
    },
    {
        title: '15. Changes to these terms and contact',
        paragraphs: [
            'We may update these terms from time to time to reflect legal, commercial, operational, security, or product changes. Updated terms will be posted on the website or in the application and will take effect from the date stated on the page, except where applicable law requires a different process.',
            'Questions about these terms should be sent using the contact details published on realuptime.online.',
        ],
    },
];

export default function TermsPage() {
    return (
        <MarketingLayout title="Terms & Conditions" description="RealUptime terms and conditions.">
            <section className="mx-auto max-w-[1180px] px-6 py-16 lg:px-10 lg:py-22">
                <div className="max-w-[900px]">
                    <div className="text-[14px] font-semibold uppercase tracking-[0.22em] text-[#7083a2]">Terms & conditions</div>
                    <h1 className="mt-5 text-[40px] font-semibold leading-[0.9] tracking-[-0.08em] text-white sm:text-[50px] lg:text-[62px]">
                        Terms for using the RealUptime product, public website, and related services<span className="text-[#7c8cff]">.</span>
                    </h1>
                    <p className="mt-6 text-[20px] leading-9 text-[#97aac6]">
                        These terms allocate risk, explain the limits of the service, and make clear that monitoring, notifications, and hosted features are
                        provided subject to internet, supplier, and platform constraints.
                    </p>
                    <div className="mt-4 text-[14px] uppercase tracking-[0.18em] text-[#7083a2]">Last updated: 10 March 2026</div>
                </div>
                <div className="mt-12 grid gap-5">
                    {sections.map((section) => (
                        <div key={section.title} className="rounded-[30px] border border-white/8 bg-[#0a1730] p-8">
                            <h2 className="text-[30px] font-semibold tracking-[-0.06em] text-white">{section.title}</h2>
                            <div className="mt-4 space-y-4">
                                {section.paragraphs.map((paragraph) => (
                                    <p key={paragraph} className="text-[17px] leading-8 text-[#8ea0bf]">
                                        {paragraph}
                                    </p>
                                ))}
                            </div>
                            {section.bullets ? (
                                <ul className="mt-5 space-y-3 text-[16px] leading-7 text-[#d6deee]">
                                    {section.bullets.map((bullet) => (
                                        <li key={bullet} className="flex items-start gap-3">
                                            <span className="mt-2 size-1.5 shrink-0 rounded-full bg-[#7c8cff]" />
                                            <span>{bullet}</span>
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                        </div>
                    ))}
                </div>
            </section>
        </MarketingLayout>
    );
}
