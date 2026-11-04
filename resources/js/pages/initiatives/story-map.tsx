import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import InitiativeLayout from '@/layouts/initiative-layout';
import { cn } from '@/lib/utils';
import { type InitiativeSummary, type Option } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface MapCard {
    id: number;
    title: string;
    kind: string;
}

interface Task {
    id: number;
    title: string;
    cards: MapCard[];
}

interface Activity {
    id: number;
    title: string;
    tasks: Task[];
}

const kindStyles: Record<string, string> = {
    story: 'border-slate-300 bg-slate-50 dark:border-slate-700 dark:bg-slate-900',
    friction: 'border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950',
    tool: 'border-sky-300 bg-sky-50 dark:border-sky-900 dark:bg-sky-950',
    gap: 'border-amber-300 bg-amber-50 dark:border-amber-900 dark:bg-amber-950',
    team: 'border-violet-300 bg-violet-50 dark:border-violet-900 dark:bg-violet-950',
};

const dotStyles: Record<string, string> = {
    story: 'bg-slate-400',
    friction: 'bg-red-500',
    tool: 'bg-sky-500',
    gap: 'bg-amber-500',
    team: 'bg-violet-500',
};

export default function StoryMap({
    initiative,
    canManage,
    kinds,
    activities,
}: {
    initiative: InitiativeSummary;
    canManage: boolean;
    kinds: Option[];
    activities: Activity[];
}) {
    const [layers, setLayers] = useState<string[]>(kinds.map((k) => k.value));
    const toggle = (kind: string) => setLayers((current) => (current.includes(kind) ? current.filter((k) => k !== kind) : [...current, kind]));

    return (
        <InitiativeLayout initiative={initiative} title="Story map">
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-muted-foreground text-sm">Layers:</span>
                {kinds.map((kind) => (
                    <button
                        key={kind.value}
                        type="button"
                        onClick={() => toggle(kind.value)}
                        aria-pressed={layers.includes(kind.value)}
                        className={cn(
                            'flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs transition-opacity',
                            !layers.includes(kind.value) && 'opacity-40',
                        )}
                    >
                        <span className={cn('size-2 rounded-full', dotStyles[kind.value])} />
                        {kind.label}
                    </button>
                ))}
            </div>

            <div className="flex items-start gap-4 overflow-x-auto pb-4">
                {activities.map((activity, index) => (
                    <ActivityColumn
                        key={activity.id}
                        initiativeId={initiative.id}
                        activity={activity}
                        kinds={kinds}
                        layers={layers}
                        canManage={canManage}
                        isFirst={index === 0}
                        isLast={index === activities.length - 1}
                    />
                ))}
                {canManage && (
                    <AddItem
                        initiativeId={initiative.id}
                        parentId={null}
                        placeholder="New activity"
                        className="w-56 shrink-0 rounded-xl border border-dashed p-3"
                    />
                )}
                {activities.length === 0 && !canManage && <p className="text-muted-foreground text-sm">No story map yet.</p>}
            </div>
        </InitiativeLayout>
    );
}

function MoveButtons({ initiativeId, id, isFirst, isLast }: { initiativeId: number; id: number; isFirst: boolean; isLast: boolean }) {
    const move = (direction: 'up' | 'down') =>
        router.post(route('initiatives.story-map.move', { initiative: initiativeId, storyMapItem: id }), { direction }, { preserveScroll: true });

    return (
        <>
            <Button size="icon" variant="ghost" className="size-6" disabled={isFirst} onClick={() => move('up')} aria-label="Move left">
                <ChevronLeft className="size-3.5" />
            </Button>
            <Button size="icon" variant="ghost" className="size-6" disabled={isLast} onClick={() => move('down')} aria-label="Move right">
                <ChevronRight className="size-3.5" />
            </Button>
        </>
    );
}

function RemoveButton({ initiativeId, id, label, confirm }: { initiativeId: number; id: number; label: string; confirm?: string }) {
    return (
        <Button
            size="icon"
            variant="ghost"
            className="size-6"
            aria-label={`Remove ${label}`}
            onClick={() => {
                if (!confirm || window.confirm(confirm)) {
                    router.delete(route('initiatives.story-map.destroy', { initiative: initiativeId, storyMapItem: id }), { preserveScroll: true });
                }
            }}
        >
            <X className="size-3.5" />
        </Button>
    );
}

function ActivityColumn({
    initiativeId,
    activity,
    kinds,
    layers,
    canManage,
    isFirst,
    isLast,
}: {
    initiativeId: number;
    activity: Activity;
    kinds: Option[];
    layers: string[];
    canManage: boolean;
    isFirst: boolean;
    isLast: boolean;
}) {
    return (
        <div className="bg-card shrink-0 rounded-xl border p-3">
            <div className="mb-3 flex items-center gap-1 rounded-lg bg-teal-600 px-3 py-2 text-white">
                <span className="flex-1 font-semibold">{activity.title}</span>
                {canManage && (
                    <div className="flex text-white [&_button]:text-white [&_button:hover]:bg-white/20">
                        <MoveButtons initiativeId={initiativeId} id={activity.id} isFirst={isFirst} isLast={isLast} />
                        <RemoveButton
                            initiativeId={initiativeId}
                            id={activity.id}
                            label={activity.title}
                            confirm={`Remove "${activity.title}" and all of its tasks and cards?`}
                        />
                    </div>
                )}
            </div>

            <div className="flex items-start gap-3">
                {activity.tasks.map((task, index) => (
                    <div key={task.id} className="w-52 space-y-2">
                        <div className="flex items-center gap-1 rounded-md bg-teal-100 px-2 py-1.5 text-sm font-medium text-teal-900 dark:bg-teal-950 dark:text-teal-100">
                            <span className="flex-1">{task.title}</span>
                            {canManage && (
                                <>
                                    <MoveButtons
                                        initiativeId={initiativeId}
                                        id={task.id}
                                        isFirst={index === 0}
                                        isLast={index === activity.tasks.length - 1}
                                    />
                                    <RemoveButton
                                        initiativeId={initiativeId}
                                        id={task.id}
                                        label={task.title}
                                        confirm={`Remove "${task.title}" and its cards?`}
                                    />
                                </>
                            )}
                        </div>
                        {task.cards
                            .filter((card) => layers.includes(card.kind))
                            .map((card) => (
                                <div
                                    key={card.id}
                                    className={cn('group flex items-start gap-1 rounded-md border px-2 py-1.5 text-sm', kindStyles[card.kind])}
                                >
                                    <span className="flex-1">{card.title}</span>
                                    {canManage && (
                                        <span className="opacity-0 group-hover:opacity-100 focus-within:opacity-100">
                                            <RemoveButton initiativeId={initiativeId} id={card.id} label={card.title} />
                                        </span>
                                    )}
                                </div>
                            ))}
                        {canManage && <AddItem initiativeId={initiativeId} parentId={task.id} placeholder="Add card" kinds={kinds} />}
                    </div>
                ))}
                {canManage && <AddItem initiativeId={initiativeId} parentId={activity.id} placeholder="Add task" className="w-40" />}
            </div>
        </div>
    );
}

function AddItem({
    initiativeId,
    parentId,
    placeholder,
    kinds,
    className,
}: {
    initiativeId: number;
    parentId: number | null;
    placeholder: string;
    kinds?: Option[];
    className?: string;
}) {
    const { data, setData, post, processing, reset } = useForm({ title: '', parent_id: parentId, kind: kinds ? 'story' : '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('initiatives.story-map.store', initiativeId), { preserveScroll: true, onSuccess: () => reset('title') });
    };

    return (
        <form onSubmit={submit} className={cn('space-y-1.5', className)}>
            <div className="flex gap-1">
                <Input value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder={placeholder} className="h-8 text-sm" />
                <Button
                    type="submit"
                    size="icon"
                    variant="outline"
                    className="size-8 shrink-0"
                    disabled={processing || !data.title}
                    aria-label={placeholder}
                >
                    <Plus />
                </Button>
            </div>
            {kinds && (
                <NativeSelect value={data.kind} onChange={(e) => setData('kind', e.target.value)} className="h-8 text-xs" aria-label="Card layer">
                    {kinds.map((kind) => (
                        <option key={kind.value} value={kind.value}>
                            {kind.label}
                        </option>
                    ))}
                </NativeSelect>
            )}
        </form>
    );
}
