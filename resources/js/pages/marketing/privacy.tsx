import MarketingLayout from '@/layouts/marketing-layout';

const sections = [
    {
        title: '1. Controller and scope',
        body: 'The operator of realuptime.online acts as the controller for personal data processed through the RealUptime website, account system, billing flow, and monitoring workspace. This notice covers the public website and the hosted product. If you publish a public status page, you remain responsible for the information you choose to make public through that page.',
    },
    {
        title: '2. Categories of data we process',
        body: 'We may process account details such as name, email address, login identifiers, connected sign-in providers, session and security records, workspace members, monitor configuration, target addresses you submit for monitoring, check results, incident history, maintenance windows, notification logs, billing and subscription metadata, support communications, and essential technical logs used to operate and secure the service.',
    },
    {
        title: '3. How and why we use personal data',
        body: 'We use personal data to create and secure accounts, authenticate users, run monitors, deliver alerts, present uptime history, process payments, prevent abuse, troubleshoot service issues, and communicate product or billing matters. The main lawful bases relied on are performance of a contract, legitimate interests in running and protecting the service, compliance with legal obligations, and consent where optional cookies or similar technologies are used.',
    },
    {
        title: '4. Cookies and similar technologies',
        body: 'RealUptime uses essential cookies and local storage for sign-in, session continuity, fraud prevention, security, and account functionality. Optional website experience cookies may be used only if you choose to accept them through the cookie banner. You can decline optional cookies without preventing core account access.',
    },
    {
        title: '5. Sharing, subprocessors, and international transfers',
        body: 'We may share data with infrastructure, hosting, email delivery, authentication, payment, analytics, support, and security providers where reasonably necessary to operate RealUptime. Data may also be disclosed where required by law, regulation, court order, or the protection of legal rights. Where personal data is transferred outside the UK, EEA, or your home jurisdiction, we aim to use recognised safeguards such as adequacy decisions, standard contractual clauses, or equivalent transfer mechanisms.',
    },
    {
        title: '6. Security, retention, and public content',
        body: 'We use technical and organisational measures intended to protect personal data, but no internet service can guarantee absolute security or prevent every unauthorised access event. Data is retained for as long as reasonably necessary to operate the account, provide historical monitoring records, meet legal obligations, resolve disputes, enforce agreements, and prevent abuse. Information you choose to publish through a public status page may be visible to anyone with access to that page.',
    },
    {
        title: '7. Your rights',
        body: 'Depending on your location and applicable law, you may have rights to access, correct, delete, restrict, object to, or port certain personal data, and to withdraw consent where processing relies on consent. You may also have the right to complain to the UK Information Commissioner’s Office or another competent supervisory authority if you believe your data has been handled unlawfully.',
    },
    {
        title: '8. Contact and updates',
        body: 'Privacy and data protection requests should be sent using the contact details made available on realuptime.online or inside the product workspace. This notice may be updated from time to time to reflect legal, operational, or product changes. Material changes will be published on the website or in-product where appropriate.',
    },
];

export default function PrivacyPage() {
    return (
        <MarketingLayout title="Privacy Policy" description="RealUptime privacy policy.">
            <section className="mx-auto max-w-[1180px] px-6 py-16 lg:px-10 lg:py-22">
                <div className="max-w-[860px]">
                    <div className="text-[14px] font-semibold uppercase tracking-[0.22em] text-[#7083a2]">Privacy policy</div>
                    <h1 className="mt-5 text-[40px] font-semibold leading-[0.9] tracking-[-0.08em] text-white sm:text-[50px] lg:text-[62px]">
                        How RealUptime handles account, monitoring, and public status data<span className="text-[#42df79]">.</span>
                    </h1>
                    <p className="mt-6 text-[20px] leading-9 text-[#97aac6]">
                        This notice explains what personal data RealUptime processes, why it is used, how it may be shared, and the rights available under applicable privacy laws.
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
