import AppLogoIcon from '@/components/app-logo-icon';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Activity, ClipboardList, Map, Scale } from 'lucide-react';

const features = [
    { icon: Map, title: 'Map the workflow', body: 'Lay out every stage, the tools used in it, and where AI already helps.' },
    { icon: Activity, title: 'Log the friction', body: 'Record how often it happens and how much it hurts, and see where it piles up.' },
    { icon: ClipboardList, title: 'Gather the data', body: 'Run interviews and surveys, with anonymity built in from the start.' },
    { icon: Scale, title: 'Prioritize & report', body: 'Score projects, plan your communications, and show the value of the work.' },
];

export default function Welcome() {
    const { auth, registrationOpen } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="bg-background text-foreground flex min-h-screen flex-col">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between p-6">
                    <div className="flex items-center gap-2 font-semibold">
                        <AppLogoIcon className="size-8" /> Frictionless Hub
                    </div>
                    <nav className="flex items-center gap-2 text-sm">
                        {auth.user ? (
                            <Link href={route('initiatives.index')} className="bg-primary text-primary-foreground rounded-md px-4 py-2">
                                Open initiatives
                            </Link>
                        ) : (
                            <>
                                <Link href={route('login')} className="hover:bg-accent rounded-md px-4 py-2">
                                    Log in
                                </Link>
                                {registrationOpen && (
                                    <Link href={route('register')} className="bg-primary text-primary-foreground rounded-md px-4 py-2">
                                        Create admin account
                                    </Link>
                                )}
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center gap-12 px-6 py-12">
                    <div className="max-w-2xl space-y-4">
                        <h1 className="text-4xl font-semibold tracking-tight md:text-5xl">Remove what slows your developers down.</h1>
                        <p className="text-muted-foreground text-lg">
                            Find the friction in your developer workflow, measure it, and remove it, one initiative at a time.
                        </p>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {features.map((feature) => (
                            <div key={feature.title} className="rounded-xl border p-5">
                                <feature.icon className="mb-3 size-5 text-teal-500" />
                                <h2 className="mb-1 font-medium">{feature.title}</h2>
                                <p className="text-muted-foreground text-sm">{feature.body}</p>
                            </div>
                        ))}
                    </div>
                </main>
            </div>
        </>
    );
}
