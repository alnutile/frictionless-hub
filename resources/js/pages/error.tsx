import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';

interface Props {
    status: number;
    isInitiative: boolean;
    signedIn: boolean;
}

function copy(status: number, isInitiative: boolean): { title: string; body: string } {
    if (status === 404 && isInitiative) {
        return {
            title: "This initiative doesn't exist",
            body: 'It may have been deleted, or the link may be out of date. Your other initiatives are still there.',
        };
    }

    return (
        {
            403: { title: "You don't have access to this page", body: 'Ask an admin or the initiative owner if you think you should.' },
            404: { title: 'Page not found', body: "The page you're looking for doesn't exist or has moved." },
            419: { title: 'Your session expired', body: 'Refresh the page and try again.' },
            503: { title: 'Down for maintenance', body: "We'll be back shortly." },
        }[status] ?? { title: 'Something went wrong', body: 'Please try again in a moment.' }
    );
}

/**
 * Standalone error page. It doesn't use the app layout because shared page data
 * may not be available when the error happens before the app has loaded the user.
 */
export default function ErrorPage({ status, isInitiative, signedIn }: Props) {
    const { title, body } = copy(status, isInitiative);

    return (
        <>
            <Head title={title} />
            <div className="bg-background text-foreground flex min-h-screen items-center justify-center p-6">
                <div className="max-w-md space-y-5 text-center" data-test="error-page">
                    <AppLogoIcon className="mx-auto size-12" />
                    <p className="text-muted-foreground text-sm tabular-nums">Error {status}</p>
                    <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                    <p className="text-muted-foreground">{body}</p>
                    <Button asChild>{signedIn ? <a href="/initiatives">Go to your initiatives</a> : <Link href="/login">Log in</Link>}</Button>
                </div>
            </div>
        </>
    );
}
