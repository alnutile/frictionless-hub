import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import InitiativeLayout from '@/layouts/initiative-layout';
import { type InitiativeSummary, type Option } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Stats {
    stages: number;
    tools: number;
    friction_points: number;
    avg_pain: number;
}

export default function InitiativeShow({
    initiative,
    canManage,
    phases,
    stats,
}: {
    initiative: InitiativeSummary;
    canManage: boolean;
    phases: Option[];
    stats: Stats;
}) {
    const tiles = [
        { label: 'Workflow stages', value: stats.stages, href: route('initiatives.workflow', initiative.id) },
        { label: 'Tools mapped', value: stats.tools, href: route('initiatives.workflow', initiative.id) },
        { label: 'Friction points', value: stats.friction_points, href: route('initiatives.friction', initiative.id) },
        { label: 'Avg. pain (1–5)', value: stats.friction_points ? stats.avg_pain : '—', href: route('initiatives.friction', initiative.id) },
    ];

    return (
        <InitiativeLayout initiative={initiative} title="Overview">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {tiles.map((tile) => (
                    <Link key={tile.label} href={tile.href} prefetch>
                        <Card className="hover:border-primary/50 transition-colors">
                            <CardHeader>
                                <CardDescription>{tile.label}</CardDescription>
                                <CardTitle className="text-3xl tabular-nums">{tile.value}</CardTitle>
                            </CardHeader>
                        </Card>
                    </Link>
                ))}
            </div>

            {initiative.description && <p className="text-muted-foreground max-w-3xl text-sm whitespace-pre-line">{initiative.description}</p>}

            {canManage && <EditInitiative initiative={initiative} phases={phases} />}
        </InitiativeLayout>
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
