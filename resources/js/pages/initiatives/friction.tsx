import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import InitiativeLayout from '@/layouts/initiative-layout';
import { cn } from '@/lib/utils';
import { type InitiativeSummary, type Option } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, X } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

interface StageRef {
    id: number;
    name: string;
}

interface Dimension {
    id: number;
    name: string;
    description: string | null;
}

interface FrictionPoint {
    id: number;
    title: string;
    description: string | null;
    workflow_stage_id: number | null;
    stage: string | null;
    frequency: string | null;
    frequency_label: string | null;
    pain: number | null;
    affected: string | null;
    dimension_ids: number[];
}

interface Heatmap {
    rows: { stage_id: number; stage: string; total: number; cells: { dimension_id: number; count: number; avg_pain: number | null }[] }[];
    max_count: number;
    unassigned: number;
}

interface Props {
    initiative: InitiativeSummary;
    canManage: boolean;
    stages: StageRef[];
    dimensions: Dimension[];
    frequencies: Option[];
    heatmap: Heatmap;
    frictionPoints: FrictionPoint[];
}

export default function Friction(props: Props) {
    const { initiative, canManage, stages, dimensions, frictionPoints } = props;
    const [editing, setEditing] = useState<FrictionPoint | 'new' | null>(null);
    const [stageFilter, setStageFilter] = useState<string>('all');

    const visible = useMemo(
        () =>
            frictionPoints.filter((p) =>
                stageFilter === 'all' ? true : stageFilter === 'none' ? p.workflow_stage_id === null : String(p.workflow_stage_id) === stageFilter,
            ),
        [frictionPoints, stageFilter],
    );

    const dimensionName = (id: number) => dimensions.find((d) => d.id === id)?.name ?? '?';

    return (
        <InitiativeLayout initiative={initiative} title="Friction log">
            <HeatmapCard heatmap={props.heatmap} dimensions={dimensions} />

            <DimensionsCard initiativeId={initiative.id} dimensions={dimensions} canManage={canManage} />

            <div className="flex flex-wrap items-center gap-3">
                <h2 className="text-lg font-semibold">Friction points</h2>
                <NativeSelect
                    className="ml-auto w-48"
                    value={stageFilter}
                    onChange={(e) => setStageFilter(e.target.value)}
                    aria-label="Filter by stage"
                >
                    <option value="all">All stages</option>
                    {stages.map((s) => (
                        <option key={s.id} value={s.id}>
                            {s.name}
                        </option>
                    ))}
                    <option value="none">No stage</option>
                </NativeSelect>
                {canManage && (
                    <Button onClick={() => setEditing('new')}>
                        <Plus /> Log friction
                    </Button>
                )}
            </div>

            {visible.length === 0 ? (
                <div className="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm">No friction points logged yet.</div>
            ) : (
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Friction</th>
                                <th className="px-3 py-2 font-medium">Stage</th>
                                <th className="px-3 py-2 font-medium">How often</th>
                                <th className="px-3 py-2 font-medium">Pain</th>
                                <th className="px-3 py-2 font-medium">Who it affects</th>
                                <th className="px-3 py-2 font-medium">Dimensions</th>
                                {canManage && <th className="px-3 py-2" />}
                            </tr>
                        </thead>
                        <tbody>
                            {visible.map((point) => (
                                <tr key={point.id} className="border-t align-top">
                                    <td className="px-3 py-2">
                                        <div className="font-medium">{point.title}</div>
                                        {point.description && (
                                            <div className="text-muted-foreground mt-0.5 whitespace-pre-line">{point.description}</div>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">{point.stage ?? '—'}</td>
                                    <td className="px-3 py-2">{point.frequency_label ?? '—'}</td>
                                    <td className="px-3 py-2">
                                        <PainBadge pain={point.pain} />
                                    </td>
                                    <td className="px-3 py-2">{point.affected ?? '—'}</td>
                                    <td className="px-3 py-2">
                                        <div className="flex flex-wrap gap-1">
                                            {point.dimension_ids.map((id) => (
                                                <Badge key={id} variant="outline">
                                                    {dimensionName(id)}
                                                </Badge>
                                            ))}
                                        </div>
                                    </td>
                                    {canManage && (
                                        <td className="px-3 py-2 whitespace-nowrap">
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                className="size-7"
                                                onClick={() => setEditing(point)}
                                                aria-label="Edit"
                                            >
                                                <Pencil />
                                            </Button>
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                className="size-7"
                                                aria-label="Delete"
                                                onClick={() => {
                                                    if (window.confirm('Delete this friction point?')) {
                                                        router.delete(
                                                            route('initiatives.friction.destroy', {
                                                                initiative: initiative.id,
                                                                frictionPoint: point.id,
                                                            }),
                                                            { preserveScroll: true },
                                                        );
                                                    }
                                                }}
                                            >
                                                <Trash2 />
                                            </Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {editing !== null && <FrictionDialog {...props} point={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
        </InitiativeLayout>
    );
}

function PainBadge({ pain }: { pain: number | null }) {
    if (pain === null) return <span className="text-muted-foreground">—</span>;
    const tone =
        pain >= 4
            ? 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200'
            : pain >= 3
              ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200'
              : 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200';
    return <span className={cn('inline-flex size-7 items-center justify-center rounded-md font-semibold tabular-nums', tone)}>{pain}</span>;
}

function HeatmapCard({ heatmap, dimensions }: { heatmap: Heatmap; dimensions: Dimension[] }) {
    const intensity = (count: number) => (heatmap.max_count === 0 ? 0 : count / heatmap.max_count);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Where friction concentrates</CardTitle>
                <CardDescription>
                    Each cell shows how many friction points a stage has for that dimension, and their average pain. Darker cells have more.
                    {heatmap.unassigned > 0 && ` ${heatmap.unassigned} point(s) have no stage and are not shown.`}
                </CardDescription>
            </CardHeader>
            <CardContent className="overflow-x-auto">
                {dimensions.length === 0 ? (
                    <p className="text-muted-foreground text-sm">Add at least one dimension to see the heatmap.</p>
                ) : (
                    <table className="w-full border-separate border-spacing-1 text-sm">
                        <thead>
                            <tr>
                                <th className="text-muted-foreground px-2 text-left font-medium">Stage</th>
                                {dimensions.map((d) => (
                                    <th key={d.id} className="text-muted-foreground px-2 text-center font-medium">
                                        {d.name}
                                    </th>
                                ))}
                                <th className="text-muted-foreground px-2 text-center font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {heatmap.rows.map((row) => (
                                <tr key={row.stage_id}>
                                    <td className="px-2 py-1 font-medium whitespace-nowrap">{row.stage}</td>
                                    {row.cells.map((cell) => (
                                        <td
                                            key={cell.dimension_id}
                                            className="h-12 min-w-24 rounded-md text-center"
                                            style={{
                                                backgroundColor:
                                                    cell.count > 0
                                                        ? `color-mix(in oklab, rgb(220 38 38) ${15 + intensity(cell.count) * 65}%, transparent)`
                                                        : undefined,
                                            }}
                                        >
                                            {cell.count > 0 ? (
                                                <div className="leading-tight">
                                                    <div className="font-semibold tabular-nums">{cell.count}</div>
                                                    {cell.avg_pain !== null && <div className="text-xs opacity-80">pain {cell.avg_pain}</div>}
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground/40">·</span>
                                            )}
                                        </td>
                                    ))}
                                    <td className="text-center font-semibold tabular-nums">{row.total}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </CardContent>
        </Card>
    );
}

function DimensionsCard({ initiativeId, dimensions, canManage }: { initiativeId: number; dimensions: Dimension[]; canManage: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('initiatives.dimensions.store', initiativeId), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <div className="flex flex-wrap items-center gap-2">
            <span className="text-muted-foreground text-sm">Dimensions:</span>
            {dimensions.map((d) => (
                <Badge key={d.id} variant="secondary" className="gap-1">
                    {d.name}
                    {canManage && (
                        <button
                            type="button"
                            aria-label={`Remove ${d.name}`}
                            className="hover:text-destructive"
                            onClick={() => {
                                if (window.confirm(`Remove the "${d.name}" dimension? Tags on friction points are removed too.`)) {
                                    router.delete(route('initiatives.dimensions.destroy', { initiative: initiativeId, dimension: d.id }), {
                                        preserveScroll: true,
                                    });
                                }
                            }}
                        >
                            <X className="size-3" />
                        </button>
                    )}
                </Badge>
            ))}
            {canManage && (
                <form onSubmit={submit} className="flex items-center gap-1.5">
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Add dimension" className="h-8 w-40" />
                    <Button
                        type="submit"
                        size="icon"
                        variant="outline"
                        className="size-8"
                        disabled={processing || !data.name}
                        aria-label="Add dimension"
                    >
                        <Plus />
                    </Button>
                    <InputError message={errors.name} />
                </form>
            )}
        </div>
    );
}

function FrictionDialog({
    initiative,
    stages,
    dimensions,
    frequencies,
    point,
    onClose,
}: Props & { point: FrictionPoint | null; onClose: () => void }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        title: point?.title ?? '',
        description: point?.description ?? '',
        workflow_stage_id: point?.workflow_stage_id ? String(point.workflow_stage_id) : '',
        frequency: point?.frequency ?? '',
        pain: point?.pain ? String(point.pain) : '',
        affected: point?.affected ?? '',
        dimension_ids: point?.dimension_ids ?? ([] as number[]),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (point) {
            patch(route('initiatives.friction.update', { initiative: initiative.id, frictionPoint: point.id }), options);
        } else {
            post(route('initiatives.friction.store', initiative.id), options);
        }
    };

    const toggleDimension = (id: number, checked: boolean) =>
        setData('dimension_ids', checked ? [...data.dimension_ids, id] : data.dimension_ids.filter((d) => d !== id));

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-xl">
                <DialogTitle>{point ? 'Edit friction point' : 'Log a friction point'}</DialogTitle>
                <DialogDescription>Where developers lose time or get frustrated.</DialogDescription>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="title">What's the friction?</Label>
                        <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} required autoFocus />
                        <InputError message={errors.title} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="description">Details</Label>
                        <Textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        <InputError message={errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="stage">Stage</Label>
                        <NativeSelect id="stage" value={data.workflow_stage_id} onChange={(e) => setData('workflow_stage_id', e.target.value)}>
                            <option value="">No stage</option>
                            {stages.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </NativeSelect>
                        <InputError message={errors.workflow_stage_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="frequency">How often</Label>
                        <NativeSelect id="frequency" value={data.frequency} onChange={(e) => setData('frequency', e.target.value)}>
                            <option value="">Unknown</option>
                            {frequencies.map((f) => (
                                <option key={f.value} value={f.value}>
                                    {f.label}
                                </option>
                            ))}
                        </NativeSelect>
                        <InputError message={errors.frequency} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Pain (1 = minor, 5 = severe)</Label>
                        <div className="flex gap-1.5">
                            {[1, 2, 3, 4, 5].map((n) => (
                                <Button
                                    key={n}
                                    type="button"
                                    size="icon"
                                    variant={data.pain === String(n) ? 'default' : 'outline'}
                                    onClick={() => setData('pain', data.pain === String(n) ? '' : String(n))}
                                >
                                    {n}
                                </Button>
                            ))}
                        </div>
                        <InputError message={errors.pain} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="affected">Who it affects</Label>
                        <Input
                            id="affected"
                            value={data.affected}
                            onChange={(e) => setData('affected', e.target.value)}
                            placeholder="e.g. Mobile devs"
                        />
                        <InputError message={errors.affected} />
                    </div>
                    {dimensions.length > 0 && (
                        <div className="grid gap-2 sm:col-span-2">
                            <Label>DevEx dimensions affected</Label>
                            <div className="flex flex-wrap gap-4">
                                {dimensions.map((d) => (
                                    <label key={d.id} className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={data.dimension_ids.includes(d.id)}
                                            onCheckedChange={(checked) => toggleDimension(d.id, checked === true)}
                                        />
                                        {d.name}
                                    </label>
                                ))}
                            </div>
                            <InputError message={errors.dimension_ids} />
                        </div>
                    )}
                    <DialogFooter className="sm:col-span-2">
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {point ? 'Save' : 'Log friction'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
