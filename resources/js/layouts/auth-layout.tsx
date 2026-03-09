import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    children,
    title,
    description,
    variant,
    ...props
}: {
    children: React.ReactNode;
    title: string;
    description: string;
    variant?: 'full' | 'form-only';
}) {
    return (
        <AuthLayoutTemplate
            title={title}
            description={description}
            variant={variant}
            {...props}
        >
            {children}
        </AuthLayoutTemplate>
    );
}
