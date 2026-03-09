import MarketingLayout from '@/layouts/marketing-layout';

const sections = [
    {
        title: '1. Scope and acceptance',
        body: 'These terms govern access to the RealUptime website, hosted application, related APIs, status pages, billing interfaces, and support workflows. By using the service you agree to these terms on your own behalf or, if acting for an organisation, on behalf of that organisation.',
    },
    {
        title: '2. Accounts, credentials, and collaborators',
        body: 'You are responsible for maintaining the confidentiality of your credentials, recovery methods, connected sign-in providers, API tokens, and any devices or inboxes used to access the service. You are also responsible for the actions of users invited into your workspace. RealUptime should be notified promptly if you become aware of suspected unauthorised access or account compromise.',
    },
    {
        title: '3. Authorised use only',
        body: 'You may only create monitors or send traffic to targets you own, control, or are authorised to test. You must not use RealUptime to disrupt third-party services, evade access controls, generate abusive load, scrape unlawfully, distribute malware, harass others, or breach sanctions, export controls, or other applicable laws.',
    },
    {
        title: '4. Service changes, availability, and suspension',
        body: 'RealUptime may change, improve, suspend, or discontinue features, limits, integrations, or plan structures at any time. We aim to operate the service reliably, but the service is not guaranteed to be uninterrupted, error-free, or available in every region or network condition. We may suspend or restrict access where reasonably necessary for security, maintenance, legal compliance, abuse prevention, non-payment, or protection of other users and the platform.',
    },
    {
        title: '5. Customer data, public content, and backups',
        body: 'You remain responsible for the legality, accuracy, and appropriateness of the targets, contacts, monitor configurations, incident posts, and status-page content you submit. RealUptime is not a substitute for your own backup, retention, export, disaster recovery, or business continuity processes. You should maintain independent copies of any data or records you cannot afford to lose.',
    },
    {
        title: '6. Billing, renewals, and cancellations',
        body: 'Paid plans are billed on a recurring subscription basis until cancelled. Fees, taxes, billing periods, and plan entitlements are shown in the product or checkout flow. Unless otherwise stated, subscriptions renew automatically. You may cancel future renewals through the billing settings, but fees already paid are generally non-refundable except where required by law or expressly stated otherwise.',
    },
    {
        title: '7. Security and unauthorised access',
        body: 'We use measures intended to protect the service and customer data, but no hosted service can guarantee immunity from intrusion, credential theft, denial-of-service events, internet failures, or failures in third-party infrastructure. To the fullest extent permitted by law, RealUptime is not responsible for unauthorised access resulting from compromised customer credentials, insecure customer devices, insecure third-party email inboxes, customer-side integrations, or failures in systems outside our reasonable control unless the loss directly results from our own breach of a legal duty that cannot lawfully be excluded.',
    },
    {
        title: '8. Warranties and service disclaimer',
        body: 'Except as expressly stated, RealUptime is provided on an “as is” and “as available” basis. We do not warrant that monitoring results will always be complete, timely, or free from false positives or false negatives, or that the service will detect every outage, latency event, expiry issue, or security incident. You are responsible for deciding whether the service is suitable for your operational and regulatory needs.',
    },
    {
        title: '9. Limitation of liability',
        body: 'Nothing in these terms excludes or limits liability for death or personal injury caused by negligence, fraud, fraudulent misrepresentation, or any liability that cannot lawfully be excluded or limited. Subject to that, RealUptime will not be liable for any indirect, incidental, special, consequential, exemplary, or punitive loss, or for loss of profit, revenue, savings, goodwill, data, business opportunity, or reputation. For business users, our total aggregate liability arising out of or in connection with the service will not exceed the greater of the fees paid by you to RealUptime in the 12 months before the event giving rise to the claim and £100. Consumer users retain any mandatory statutory rights that cannot lawfully be limited, but we are not responsible for losses that were not reasonably foreseeable.',
    },
    {
        title: '10. Indemnity for business users',
        body: 'If you use RealUptime on behalf of a business, you agree to indemnify and hold harmless the operator of RealUptime against losses, claims, damages, liabilities, and reasonable costs arising from your unlawful use of the service, your breach of these terms, your monitoring of targets without authority, or your published status-page or notification content.',
    },
    {
        title: '11. Termination',
        body: 'You may stop using the service at any time. We may suspend or terminate accounts, workspaces, subscriptions, or access where there is a breach of these terms, a legal requirement to do so, non-payment, prolonged inactivity, or conduct that creates security, operational, or reputational risk to the service or other users.',
    },
    {
        title: '12. Governing law and jurisdiction',
        body: 'These terms are governed by the laws of England and Wales, except where mandatory local consumer law requires otherwise. Business users agree that the courts of England and Wales will have exclusive jurisdiction over disputes arising from the service. Consumer users may also bring claims in the courts of their home jurisdiction where local law gives them that right.',
    },
    {
        title: '13. Changes and contact',
        body: 'We may amend these terms from time to time to reflect legal, operational, security, or product changes. Updated terms will be posted on the website or in the product. Questions about these terms should be sent using the contact details published on realuptime.online.',
    },
];

export default function TermsPage() {
    return (
        <MarketingLayout title="Terms & Conditions" description="RealUptime terms and conditions.">
            <section className="mx-auto max-w-[1180px] px-6 py-16 lg:px-10 lg:py-22">
                <div className="max-w-[860px]">
                    <div className="text-[14px] font-semibold uppercase tracking-[0.22em] text-[#7083a2]">Terms & conditions</div>
                    <h1 className="mt-5 text-[40px] font-semibold leading-[0.9] tracking-[-0.08em] text-white sm:text-[50px] lg:text-[62px]">
                        Terms for using the RealUptime product and public website<span className="text-[#7c8cff]">.</span>
                    </h1>
                    <p className="mt-6 text-[20px] leading-9 text-[#97aac6]">
                        These terms set out how the RealUptime service may be used, where responsibility sits for credentials and published content, and how liability is allocated to the fullest extent permitted by law.
                    </p>
                    <div className="mt-4 text-[14px] uppercase tracking-[0.18em] text-[#7083a2]">Last updated: 9 March 2026</div>
                </div>
                <div className="mt-12 space-y-5">
                    {sections.map((section) => (
                        <div key={section.title} className="rounded-[30px] border border-white/8 bg-[#0a1730] p-8">
                            <h2 className="text-[30px] font-semibold tracking-[-0.06em] text-white">{section.title}</h2>
                            <p className="mt-4 text-[17px] leading-8 text-[#8ea0bf]">{section.body}</p>
                        </div>
                    ))}
                </div>
            </section>
        </MarketingLayout>
    );
}
