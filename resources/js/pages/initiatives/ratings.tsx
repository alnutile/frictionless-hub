import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import InitiativeLayout from '@/layouts/initiative-layout';
import { cn } from '@/lib/utils';
import { type InitiativeSummary } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { Plus, Trash2, X } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Item {
    id: number;
    label: string;
    mine: number | null;
    count: number;
    average: number | null;
    distribution: Record<string, number>;
    spread: number | null;
}

interface Group {
    id: number;
    name: string;
    items: Item[];
}

function heat(avg: number | null): string {
    if (avg === null) return 'text-muted-foreground';
    if (avg >= 4) return 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200';
    if (avg >= 3) return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200';
    return 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200';
}

export default function Ratings({
    initiative,
    canManage,
    groups,
    raters,
}: {
    initiative: InitiativeSummary;
    canManage: boolean;
    groups: Group[];
    raters: number;
}) {
    return (
        <InitiativeLayout initiative={initiative} title="Rate the workflow">
            <p className="text-muted-foreground max-w-3xl text-sm">
                Rate each component from <strong>1 (excellent)</strong> to <strong>5 (severe problems)</strong>. Your rating is yours alone; the
                average and spread come from everyone who has rated ({raters} {raters === 1 ? 'person' : 'people'} so far). Click your current rating
                again to clear it.
            </p>

            {groups.length === 0 && (
                <div className="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm">
                    {canManage ? 'Add a group, then the workflow components to rate inside it.' : 'Nothing to rate yet.'}
                </div>
            )}

            {groups.map((group) => (
                <GroupCard key={group.id} initiativeId={initiative.id} group={group} canManage={canManage} />
            ))}

            {canManage && <AddGroup initiativeId={initiative.id} />}
        </InitiativeLayout>
    );
}

function GroupCard({ initiativeId, group, canManage }: { initiativeId: number; group: Group; canManage: boolean }) {
    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between">
                <CardTitle>{group.name}</CardTitle>
                {canManage && (
                    <Button
                        size="icon"
                        variant="ghost"
                        className="size-7"
                        aria-label={`Remove ${group.name}`}
                        onClick={() => {
                            if (window.confirm(`Remove "${group.name}" and all of its ratings?`)) {
                                router.delete(route('initiatives.rating-groups.destroy', { initiative: initiativeId, ratingGroup: group.id }), {
                                    preserveScroll: true,
                                });
                            }
                        }}
                    >
                        <Trash2 />
                    </Button>
                )}
            </CardHeader>
            <CardContent className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="text-muted-foreground text-left">
                        <tr>
                            <th className="py-2 pr-3 font-medium">Component</th>
                            <th className="px-3 py-2 font-medium">Your rating</th>
                            <th className="px-3 py-2 text-center font-medium">Average</th>
                            <th className="px-3 py-2 font-medium">Spread of answers</th>
                            {canManage && <th />}
                        </tr>
                    </thead>
                    <tbody>
                        {group.items.map((item) => (
                            <tr key={item.id} className="border-t">
                                <td className="py-2 pr-3">{item.label}</td>
                                <td className="px-3 py-2">
                                    <div className="flex gap-1">
                                        {[1, 2, 3, 4, 5].map((n) => (
                                            <Button
                                                key={n}
                                                size="icon"
                                                variant={item.mine === n ? 'default' : 'outline'}
                                                className="size-7"
                                                aria-label={`Rate ${n}`}
                                                aria-pressed={item.mine === n}
                                                onClick={() =>
                                                    router.put(
                                                        route('initiatives.rating-items.rate', { initiative: initiativeId, ratingItem: item.id }),
                                                        { score: item.mine === n ? null : n },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                {n}
                                            </Button>
                                        ))}
                                    </div>
                                </td>
                                <td className="px-3 py-2 text-center">
                                    <span className={cn('inline-block min-w-10 rounded-md px-2 py-1 font-semibold tabular-nums', heat(item.average))}>
                                        {item.average ?? '—'}
                                    </span>
                                </td>
                                <td className="px-3 py-2">
                                    <Distribution item={item} />
                                </td>
                                {canManage && (
                                    <td className="py-2 text-right">
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            className="size-7"
                                            aria-label={`Remove ${item.label}`}
                                            onClick={() =>
                                                router.delete(
                                                    route('initiatives.rating-items.destroy', { initiative: initiativeId, ratingItem: item.id }),
                                                    {
                                                        preserveScroll: true,
                                                    },
                                                )
                                            }
                                        >
                                            <X />
                                        </Button>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
                {canManage && <AddItem initiativeId={initiativeId} groupId={group.id} />}
            </CardContent>
        </Card>
    );
}

function Distribution({ item }: { item: Item }) {
    if (item.count === 0) return <span className="text-muted-foreground">No ratings</span>;
    const max = Math.max(...Object.values(item.distribution));

    return (
        <div className="flex items-end gap-1" title={`${item.count} rating(s)`}>
            {[1, 2, 3, 4, 5].map((n) => (
                <div key={n} className="flex flex-col items-center gap-0.5">
                    <div className="bg-primary/70 w-4 rounded-sm" style={{ height: `${max ? (item.distribution[n] / max) * 24 : 0}px` }} />
                    <span className="text-muted-foreground text-[10px]">{n}</span>
                </div>
            ))}
            <span className="text-muted-foreground ml-2 text-xs">n={item.count}</span>
            {item.spread !== null && item.spread >= 3 && <span className="ml-1 text-xs text-amber-600">wide disagreement</span>}
        </div>
    );
}

function AddItem({ initiativeId, groupId }: { initiativeId: number; groupId: number }) {
    const { data, setData, post, processing, reset } = useForm({ label: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('initiatives.rating-items.store', { initiative: initiativeId, ratingGroup: groupId }), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form onSubmit={submit} className="mt-3 flex max-w-md gap-1.5">
            <Input value={data.label} onChange={(e) => setData('label', e.target.value)} placeholder="Add a component to rate" className="h-8" />
            <Button
                type="submit"
                size="icon"
                variant="outline"
                className="size-8 shrink-0"
                disabled={processing || !data.label}
                aria-label="Add component"
            >
                <Plus />
            </Button>
        </form>
    );
}

function AddGroup({ initiativeId }: { initiativeId: number }) {
    const { data, setData, post, processing, reset } = useForm({ name: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('initiatives.rating-groups.store', initiativeId), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="flex max-w-md gap-1.5">
            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="New group, e.g. Local development" />
            <Button type="submit" variant="outline" disabled={processing || !data.name}>
                <Plus /> Add group
            </Button>
        </form>
    );
}
