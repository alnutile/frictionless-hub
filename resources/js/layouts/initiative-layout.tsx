import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { activityHref } from '@/lib/walkthrough';
import { type InitiativeSummary, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { type ReactNode, useEffect, useState } from 'react';

export default function InitiativeLayout({ initiative, title, children }: { initiative: InitiativeSummary; title: string; children: ReactNode }) {
    const { url, props } = usePage<SharedData>();
    const activities = props.guide?.activities ?? [];
    const current = activities.find((a) => a.key === props.guide?.key);
    const phase = current?.phase ?? initiative.phase;
    const tabs = [
        { key: 'overview', title: 'Overview', href: route('initiatives.show', initiative.id) as string, planned: false },
        ...activities
            .filter((a) => a.phase === phase)
            .map((a) => ({ key: a.key, title: `${a.step}. ${a.title}`, href: activityHref(a, initiative.id), planned: a.route === null })),
    ];
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
                    {tabs.map((tab) => (
                        <Link
                            key={tab.key}
                            href={tab.href}
                            prefetch
                            title={tab.planned ? 'Planned: see what this step will do' : undefined}
                            className={cn(
                                '-mb-px border-b-2 px-3 py-2 text-sm whitespace-nowrap transition-colors',
                                (props.guide?.key ?? 'overview') === tab.key
                                    ? 'border-primary text-foreground font-medium'
                                    : tab.planned
                                      ? 'text-muted-foreground/50 hover:text-muted-foreground border-transparent'
                                      : 'text-muted-foreground hover:text-foreground border-transparent',
                            )}
                        >
                            {tab.title}
                        </Link>
                    ))}
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
