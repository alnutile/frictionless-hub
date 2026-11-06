import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import InitiativeLayout from '@/layouts/initiative-layout';
import { cn } from '@/lib/utils';
import { type InitiativeSummary, type Option } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { NotebookPen, Plus, X } from 'lucide-react';

interface CodeCount {
    id: number;
    name: string;
    notes: number;
    interviews: number;
}

interface InterviewRow {
    id: number;
    participant: string;
    role: string | null;
    area: string | null;
    conducted_at: string | null;
    status: 'draft' | 'complete';
    counts: Record<string, number>;
    codes: string[];
    note_count: number;
}

interface Props {
    initiative: InitiativeSummary;
    canManage: boolean;
    codeFrequency: CodeCount[];
    stats: { complete: number; draft: number; by_kind: Record<string, number>; open_follow_ups: number; uncoded: number };
    kinds: Option[];
    interviews: InterviewRow[] | null;
    toInterview: { id: number; name: string; status: string; scheduled_at: string | null }[] | null;
    openFollowUps: { id: number; body: string; owner: string | null; interview_id: number; participant: string | null }[] | null;
    tourSize: number;
}

export default function Interviews({ initiative, canManage, codeFrequency, stats, kinds, interviews, toInterview, openFollowUps, tourSize }: Props) {
    const tiles: { label: string; value: number; href?: string }[] = [
        { label: 'Interviews complete', value: stats.complete },
        { label: 'In progress', value: stats.draft },
        { label: 'Pain points noted', value: stats.by_kind.pain_point ?? 0 },
        { label: 'Open follow-ups', value: stats.open_follow_ups, href: '#open-follow-ups' },
    ];

    return (
        <InitiativeLayout initiative={initiative} title="Interviews">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-test="interview-stats">
                {tiles.map((t) => {
                    const card = (
                        <Card className={cn('gap-1 py-4', t.href && canManage && 'hover:border-primary/50')}>
                            <CardHeader className="px-4">
                                <CardDescription>{t.label}</CardDescription>
                                <CardTitle className="text-3xl tabular-nums">{t.value}</CardTitle>
                            </CardHeader>
                        </Card>
                    );
                    return t.href && canManage && t.value > 0 ? (
                        <a key={t.label} href={t.href} data-test="follow-ups-tile">
                            {card}
                        </a>
                    ) : (
                        <div key={t.label}>{card}</div>
                    );
                })}
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    {canManage && toInterview && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Ready to interview</CardTitle>
                                <CardDescription>
                                    People on the listening tour who don't have notes yet. Start notes during or right after the conversation.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {toInterview.length === 0 ? (
                                    <p className="text-muted-foreground text-sm" data-test="ready-empty">
                                        {tourSize === 0 ? 'No one is on the tour yet. ' : 'Everyone on the tour has notes. '}
                                        <Link
                                            href={route('initiatives.listening-tour', initiative.id)}
                                            className="text-teal-600 underline dark:text-teal-400"
                                        >
                                            {tourSize === 0 ? 'Plan the tour in step 5' : 'Add more people in step 5'}
                                        </Link>
                                        .
                                    </p>
                                ) : (
                                    <ul className="divide-y">
                                        {toInterview.map((p) => (
                                            <li key={p.id} className="flex items-center justify-between gap-2 py-2 text-sm">
                                                <span>
                                                    <span className="font-medium">{p.name}</span>{' '}
                                                    <span className="text-muted-foreground">
                                                        · {p.status}
                                                        {p.scheduled_at && ` · ${p.scheduled_at}`}
                                                    </span>
                                                </span>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(route('initiatives.interviews.store', initiative.id), { participant_id: p.id })
                                                    }
                                                    data-test={`start-interview-${p.id}`}
                                                >
                                                    <NotebookPen /> Start notes
                                                </Button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {canManage && openFollowUps && openFollowUps.length > 0 && (
                        <Card id="open-follow-ups" className="scroll-mt-4" data-test="open-follow-ups">
                            <CardHeader>
                                <CardTitle>Open follow-ups</CardTitle>
                                <CardDescription>Added in the Follow-ups section of each interview. Tick them off there.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-1.5 text-sm">
                                    {openFollowUps.map((f) => (
                                        <li key={f.id}>
                                            <Link
                                                href={route('initiatives.interviews.show', [initiative.id, f.interview_id])}
                                                className="hover:underline"
                                            >
                                                {f.body}
                                            </Link>
                                            <span className="text-muted-foreground">
                                                {f.owner && ` · ${f.owner}`} · from {f.participant}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    )}
                    {canManage && interviews ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>Interview notes</CardTitle>
                            </CardHeader>
                            <CardContent className="overflow-x-auto">
                                {interviews.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">No interviews yet.</p>
                                ) : (
                                    <table className="w-full text-sm" data-test="interview-list">
                                        <thead className="text-muted-foreground text-left">
                                            <tr>
                                                <th className="py-2 pr-3 font-medium">Person</th>
                                                <th className="px-3 py-2 font-medium">Date</th>
                                                <th className="px-3 py-2 font-medium">Notes</th>
                                                <th className="px-3 py-2 font-medium">Codes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {interviews.map((i) => (
                                                <tr key={i.id} className="border-t align-top">
                                                    <td className="py-2 pr-3">
                                                        <Link
                                                            href={route('initiatives.interviews.show', [initiative.id, i.id])}
                                                            className="font-medium hover:underline"
                                                        >
                                                            {i.participant}
                                                        </Link>
                                                        <div className="text-muted-foreground">{[i.role, i.area].filter(Boolean).join(' · ')}</div>
                                                    </td>
                                                    <td className="px-3 py-2 whitespace-nowrap">
                                                        {i.conducted_at ?? '—'}
                                                        <div className="flex flex-wrap gap-1">
                                                            <Badge variant={i.status === 'complete' ? 'default' : 'outline'}>
                                                                {i.status === 'complete' ? 'Complete' : 'Draft'}
                                                            </Badge>
                                                            {i.status === 'complete' && i.note_count === 0 && (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="border-amber-400 text-amber-700 dark:text-amber-300"
                                                                >
                                                                    No notes
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="text-muted-foreground px-3 py-2 text-xs whitespace-nowrap">
                                                        {kinds
                                                            .filter((k) => (i.counts[k.value] ?? 0) > 0)
                                                            .map((k) => `${i.counts[k.value]} ${k.label.toLowerCase()}`)
                                                            .join(' · ') || 'none yet'}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <div className="flex flex-wrap gap-1">
                                                            {i.codes.map((c) => (
                                                                <Badge key={c} variant="secondary" className="font-normal">
                                                                    {c}
                                                                </Badge>
                                                            ))}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </CardContent>
                        </Card>
                    ) : (
                        <p className="text-muted-foreground text-sm">
                            Interview notes identify people, so only those running this initiative can read them. The counts and codes here are
                            shared.
                        </p>
                    )}
                </div>

                <Card className="h-fit" data-test="code-frequency">
                    <CardHeader>
                        <CardTitle>Codes so far</CardTitle>
                        <CardDescription>
                            Short tags applied to notes. Codes mentioned across many interviews become themes in step 7.
                            {stats.uncoded > 0 && ` ${stats.uncoded} note${stats.uncoded === 1 ? ' is' : 's are'} not coded yet.`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {codeFrequency.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No codes yet. Add them here or while taking notes.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="text-muted-foreground text-left text-xs">
                                    <tr>
                                        <th className="pb-1 font-medium">Code</th>
                                        <th className="pb-1 text-right font-medium">Interviews</th>
                                        <th className="pb-1 text-right font-medium">Notes</th>
                                        {canManage && <th />}
                                    </tr>
                                </thead>
                                <tbody>
                                    {codeFrequency.map((c) => (
                                        <tr key={c.id} className="border-t">
                                            <td className="py-1.5">{c.name}</td>
                                            <td className="py-1.5 text-right font-semibold tabular-nums">{c.interviews}</td>
                                            <td className="text-muted-foreground py-1.5 text-right tabular-nums">{c.notes}</td>
                                            {canManage && (
                                                <td className="py-1.5 text-right">
                                                    <button
                                                        type="button"
                                                        aria-label={`Delete code ${c.name}`}
                                                        className="text-muted-foreground hover:text-destructive"
                                                        onClick={() => {
                                                            if (
                                                                window.confirm(
                                                                    `Delete the "${c.name}" code? It will be removed from ${c.notes} note(s).`,
                                                                )
                                                            ) {
                                                                router.delete(route('initiatives.codes.destroy', [initiative.id, c.id]), {
                                                                    preserveScroll: true,
                                                                });
                                                            }
                                                        }}
                                                    >
                                                        <X className="size-3.5" />
                                                    </button>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                        {canManage && <NewCode initiativeId={initiative.id} />}
                    </CardContent>
                </Card>
            </div>
        </InitiativeLayout>
    );
}

function NewCode({ initiativeId }: { initiativeId: number }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '' });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(route('initiatives.codes.store', initiativeId), { preserveScroll: true, onSuccess: () => reset() });
            }}
        >
            <div className="flex gap-1.5">
                <Input
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    placeholder="New code, e.g. slow-ci"
                    className="h-8"
                    data-test="code-new"
                />
                <Button
                    type="submit"
                    size="icon"
                    variant="outline"
                    className="size-8 shrink-0"
                    disabled={processing || !data.name}
                    aria-label="Add code"
                >
                    <Plus />
                </Button>
            </div>
            <InputError message={errors.name} />
        </form>
    );
}
