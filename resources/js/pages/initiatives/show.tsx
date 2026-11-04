import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import InitiativeLayout from '@/layouts/initiative-layout';
import { cn } from '@/lib/utils';
import { type InitiativeSummary, type Option, type SharedData, type WalkthroughActivity } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Progress = Record<string, { count: number; label: string }>;

export default function InitiativeShow({
    initiative,
    canManage,
    phases,
    progress,
}: {
    initiative: InitiativeSummary;
    canManage: boolean;
    phases: Option[];
    progress: Progress;
}) {
    const { guide } = usePage<SharedData>().props;
    const activities = guide?.activities ?? [];
    const phaseOrder = phases.filter((p) => activities.some((a) => a.phase === p.value));

    return (
        <InitiativeLayout initiative={initiative} title="Overview">
            {initiative.description && <p className="text-muted-foreground max-w-3xl text-sm whitespace-pre-line">{initiative.description}</p>}

            <div className="space-y-6">
                {phaseOrder.map((phase) => (
                    <section key={phase.value}>
                        <h2
                            className={cn(
                                'mb-2 text-sm font-semibold tracking-wide uppercase',
                                phase.value === initiative.phase ? 'text-teal-600 dark:text-teal-400' : 'text-muted-foreground',
                            )}
                        >
                            {phase.label}
                            {phase.value === initiative.phase && ' · current phase'}
                        </h2>
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            {activities
                                .filter((a) => a.phase === phase.value)
                                .map((activity) => (
                                    <LadderCard
                                        key={activity.key}
                                        activity={activity}
                                        initiativeId={initiative.id}
                                        progress={progress[activity.key]}
                                    />
                                ))}
                        </div>
                    </section>
                ))}
            </div>

            {canManage && <EditInitiative initiative={initiative} phases={phases} />}
        </InitiativeLayout>
    );
}

function LadderCard({
    activity,
    initiativeId,
    progress,
}: {
    activity: WalkthroughActivity;
    initiativeId: number;
    progress?: { count: number; label: string };
}) {
    const body = (
        <Card className={cn('h-full gap-2 py-4 transition-colors', activity.route ? 'hover:border-primary/50' : 'opacity-50')}>
            <CardHeader className="px-4">
                <CardDescription>Step {activity.step}</CardDescription>
                <CardTitle className="text-base">{activity.title}</CardTitle>
            </CardHeader>
            <CardContent className="text-muted-foreground px-4 text-sm">
                {activity.route ? (
                    progress ? (
                        <span>
                            <span className="text-foreground font-semibold tabular-nums">{progress.count}</span> {progress.label}
                        </span>
                    ) : (
                        'Open'
                    )
                ) : (
                    'Not built yet'
                )}
            </CardContent>
        </Card>
    );

    return activity.route ? (
        <Link href={route(activity.route, initiativeId)} prefetch>
            {body}
        </Link>
    ) : (
        body
    );
}

function EditInitiative({ initiative, phases }: { initiative: InitiativeSummary; phases: Option[] }) {
    const { data, setData, patch, processing, errors, isDirty } = useForm({
        name: initiative.name,
        scope: initiative.scope ?? '',
        description: initiative.description ?? '',
        phase: initiative.phase,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('initiatives.update', initiative.id), { preserveScroll: true });
    };

    const destroy = () => {
        if (window.confirm(`Delete "${initiative.name}" and everything in it?`)) {
            router.delete(route('initiatives.destroy', initiative.id));
        }
    };

    return (
        <Card className="max-w-3xl">
            <CardHeader>
                <CardTitle>Settings</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="scope">Scope</Label>
                        <Input id="scope" value={data.scope} onChange={(e) => setData('scope', e.target.value)} />
                        <InputError message={errors.scope} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        <InputError message={errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="phase">Current phase</Label>
                        <NativeSelect id="phase" value={data.phase} onChange={(e) => setData('phase', e.target.value)}>
                            {phases.map((phase) => (
                                <option key={phase.value} value={phase.value}>
                                    {phase.label}
                                </option>
                            ))}
                        </NativeSelect>
                        <InputError message={errors.phase} />
                    </div>
                    <div className="flex items-end justify-end gap-2 sm:col-span-2">
                        <Button type="button" variant="ghost" className="text-destructive mr-auto" onClick={destroy}>
                            Delete initiative
                        </Button>
                        <Button type="submit" disabled={processing || !isDirty}>
                            Save
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
