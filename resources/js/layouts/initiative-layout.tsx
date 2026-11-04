import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type InitiativeSummary, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { type ReactNode, useEffect, useState } from 'react';

interface Tab {
    title: string;
    href: string | null;
    step: string;
}

function tabs(id: number): Tab[] {
    return [
        { title: 'Overview', href: `/initiatives/${id}`, step: '' },
        { title: 'Workflow map', href: `/initiatives/${id}/workflow`, step: 'Discover' },
        { title: 'Friction log', href: `/initiatives/${id}/friction`, step: 'Discover' },
        { title: 'Listening tour', href: null, step: 'Discover' },
        { title: 'Stakeholders', href: null, step: 'Discover' },
        { title: 'Quick wins', href: null, step: 'Quick wins' },
        { title: 'Surveys & data', href: null, step: 'Data' },
        { title: 'Prioritize', href: null, step: 'Prioritize' },
    ];
}

export default function InitiativeLayout({ initiative, title, children }: { initiative: InitiativeSummary; title: string; children: ReactNode }) {
    const { url, props } = usePage<SharedData>();
    const [flash, setFlash] = useState<string | null>(null);

    useEffect(() => {
        if (props.flash?.success) {
            setFlash(props.flash.success);
            const timer = setTimeout(() => setFlash(null), 3000);
            return () => clearTimeout(timer);
        }
    }, [props.flash?.success]);

    const breadcrumbs = [
        { title: 'Initiatives', href: '/initiatives' },
        { title: initiative.name, href: `/initiatives/${initiative.id}` },
        ...(title !== 'Overview' ? [{ title, href: url }] : []),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${title} · ${initiative.name}`} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">{initiative.name}</h1>
                        <p className="text-muted-foreground text-sm">
                            {initiative.scope ?? 'No scope set'} · owned by {initiative.owner}
                        </p>
                    </div>
                    <Badge variant="secondary">Phase: {initiative.phase_label}</Badge>
                </div>

                <nav className="flex gap-1 overflow-x-auto border-b">
                    {tabs(initiative.id).map((tab) =>
                        tab.href ? (
                            <Link
                                key={tab.title}
                                href={tab.href}
                                prefetch
                                className={cn(
                                    '-mb-px border-b-2 px-3 py-2 text-sm whitespace-nowrap transition-colors',
                                    url.split('?')[0] === tab.href
                                        ? 'border-primary text-foreground font-medium'
                                        : 'text-muted-foreground hover:text-foreground border-transparent',
                                )}
                            >
                                {tab.title}
                            </Link>
                        ) : (
                            <span
                                key={tab.title}
                                title="Coming in a later step"
                                className="text-muted-foreground/50 -mb-px cursor-not-allowed border-b-2 border-transparent px-3 py-2 text-sm whitespace-nowrap"
                            >
                                {tab.title}
                            </span>
                        ),
                    )}
                </nav>

                {flash && (
                    <div className="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                        {flash}
                    </div>
                )}

                {children}
            </div>
        </AppLayout>
    );
}
