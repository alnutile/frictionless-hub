import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InitiativeLayout from '@/layouts/initiative-layout';
import { cn } from '@/lib/utils';
import { type InitiativeSummary } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Plus, Sparkles, Trash2, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Tool {
    id: number;
    name: string;
    is_ai: boolean;
    notes: string | null;
}

interface Stage {
    id: number;
    name: string;
    notes: string | null;
    ai_assisted: boolean;
    friction_points_count: number;
    avg_pain: number | null;
    tools: Tool[];
}

export default function Workflow({ initiative, canManage, stages }: { initiative: InitiativeSummary; canManage: boolean; stages: Stage[] }) {
    return (
        <InitiativeLayout initiative={initiative} title="Workflow map">
            <p className="text-muted-foreground max-w-3xl text-sm">
                Map each stage of the developer workflow, the tools used in it, and where AI assistance is available. Friction counts come from the
                friction log.
            </p>

            <div className="flex gap-4 overflow-x-auto pb-4">
                {stages.map((stage, index) => (
                    <StageColumn
                        key={stage.id}
                        initiativeId={initiative.id}
                        stage={stage}
                        canManage={canManage}
                        isFirst={index === 0}
                        isLast={index === stages.length - 1}
                    />
                ))}
                {canManage && <AddStage initiativeId={initiative.id} />}
            </div>
        </InitiativeLayout>
    );
}

function painClass(avg: number | null): string {
    if (avg === null) return 'bg-muted text-muted-foreground';
    if (avg >= 4) return 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200';
    if (avg >= 3) return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200';
    return 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200';
}

function StageColumn({
    initiativeId,
    stage,
    canManage,
    isFirst,
    isLast,
}: {
    initiativeId: number;
    stage: Stage;
    canManage: boolean;
    isFirst: boolean;
    isLast: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const params = { initiative: initiativeId, stage: stage.id };

    const move = (direction: 'up' | 'down') => router.post(route('initiatives.stages.move', params), { direction }, { preserveScroll: true });

    const destroy = () => {
        if (window.confirm(`Remove the "${stage.name}" stage and its tools? Friction points stay but become unassigned.`)) {
            router.delete(route('initiatives.stages.destroy', params), { preserveScroll: true });
        }
    };

    return (
        <div className="bg-card flex w-72 shrink-0 flex-col gap-3 rounded-xl border p-4">
            {editing ? (
                <EditStage initiativeId={initiativeId} stage={stage} onDone={() => setEditing(false)} />
            ) : (
                <>
                    <div className="flex items-start justify-between gap-2">
                        <div>
                            <h3 className="font-semibold">{stage.name}</h3>
                            {stage.ai_assisted && (
                                <Badge variant="outline" className="mt-1 gap-1">
                                    <Sparkles className="size-3" /> AI-assisted
                                </Badge>
                            )}
                        </div>
                        {canManage && (
                            <div className="flex">
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    className="size-7"
                                    disabled={isFirst}
                                    onClick={() => move('up')}
                                    aria-label="Move left"
                                >
                                    <ChevronLeft />
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    className="size-7"
                                    disabled={isLast}
                                    onClick={() => move('down')}
                                    aria-label="Move right"
                                >
                                    <ChevronRight />
                                </Button>
                                <Button size="icon" variant="ghost" className="size-7" onClick={() => setEditing(true)} aria-label="Edit stage">
                                    <Pencil />
                                </Button>
                                <Button size="icon" variant="ghost" className="size-7" onClick={destroy} aria-label="Remove stage">
                                    <Trash2 />
                                </Button>
                            </div>
                        )}
                    </div>

                    <div className={cn('rounded-md px-2 py-1 text-xs', painClass(stage.avg_pain))}>
                        {stage.friction_points_count} friction point{stage.friction_points_count === 1 ? '' : 's'}
                        {stage.avg_pain !== null && ` · avg pain ${stage.avg_pain}`}
                    </div>

                    {stage.notes && <p className="text-muted-foreground text-sm whitespace-pre-line">{stage.notes}</p>}
                </>
            )}

            <div className="space-y-2">
                <div className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Tools</div>
                <div className="flex flex-wrap gap-1.5">
                    {stage.tools.length === 0 && <span className="text-muted-foreground text-sm">None yet</span>}
                    {stage.tools.map((tool) => (
                        <Badge key={tool.id} variant="secondary" className="gap-1" title={tool.notes ?? undefined}>
                            {tool.is_ai && <Sparkles className="size-3" />}
                            {tool.name}
                            {canManage && (
                                <button
                                    type="button"
                                    aria-label={`Remove ${tool.name}`}
                                    className="hover:text-destructive"
                                    onClick={() =>
                                        router.delete(route('initiatives.tools.destroy', { ...params, tool: tool.id }), { preserveScroll: true })
                                    }
                                >
                                    <X className="size-3" />
                                </button>
                            )}
                        </Badge>
                    ))}
                </div>
                {canManage && <AddTool initiativeId={initiativeId} stageId={stage.id} />}
            </div>
        </div>
    );
}

function AddTool({ initiativeId, stageId }: { initiativeId: number; stageId: number }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', is_ai: false as boolean });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('initiatives.tools.store', { initiative: initiativeId, stage: stageId }), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-1.5">
            <div className="flex gap-1.5">
                <Input
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    placeholder="Add a tool"
                    className="h-8"
                    data-test={`tool-input-${stageId}`}
                />
                <Button
                    type="submit"
                    size="icon"
                    variant="outline"
                    className="size-8 shrink-0"
                    disabled={processing || !data.name}
                    aria-label="Add tool"
                >
                    <Plus />
                </Button>
            </div>
            <label className="text-muted-foreground flex items-center gap-2 text-xs">
                <Checkbox checked={data.is_ai} onCheckedChange={(checked) => setData('is_ai', checked === true)} /> AI tool
            </label>
            <InputError message={errors.name} />
        </form>
    );
}

function EditStage({ initiativeId, stage, onDone }: { initiativeId: number; stage: Stage; onDone: () => void }) {
    const { data, setData, patch, processing, errors } = useForm({
        name: stage.name,
        notes: stage.notes ?? '',
        ai_assisted: stage.ai_assisted,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('initiatives.stages.update', { initiative: initiativeId, stage: stage.id }), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <form onSubmit={submit} className="space-y-2">
            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
            <InputError message={errors.name} />
            <Textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} placeholder="Notes about this stage" />
            <label className="flex items-center gap-2 text-sm">
                <Checkbox checked={data.ai_assisted} onCheckedChange={(checked) => setData('ai_assisted', checked === true)} /> AI assistance
                available
            </label>
            <div className="flex justify-end gap-2">
                <Button type="button" variant="ghost" size="sm" onClick={onDone}>
                    Cancel
                </Button>
                <Button type="submit" size="sm" disabled={processing}>
                    Save
                </Button>
            </div>
        </form>
    );
}

function AddStage({ initiativeId }: { initiativeId: number }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('initiatives.stages.store', initiativeId), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="flex w-72 shrink-0 flex-col gap-2 rounded-xl border border-dashed p-4">
            <Label htmlFor="new-stage">Add a stage</Label>
            <Input id="new-stage" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. Onboarding" />
            <InputError message={errors.name} />
            <Button type="submit" variant="outline" disabled={processing || !data.name}>
                <Plus /> Add stage
            </Button>
        </form>
    );
}
