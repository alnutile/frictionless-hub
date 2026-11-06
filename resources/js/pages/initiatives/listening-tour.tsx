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
import { fillTemplate } from '@/lib/merge-fields';
import { cn } from '@/lib/utils';
import { type InitiativeSummary, type Option } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Copy, Mail, MessageSquare, Pencil, Plus, Trash2, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Value {
    id: number;
    name: string;
}

interface Group {
    id: number;
    name: string;
    values: Value[];
}

interface Coverage {
    rows: { area_id: number | null; area: string; total: number; counts: Record<string, number> }[];
    column_totals: Record<string, number>;
    total: number;
    issues: { area: string; category: string; missing: number }[];
    empty_areas: string[];
    empty_values: { category: string; value: string }[];
    target: number | null;
    remaining: number | null;
}

interface Suggestions {
    has_data: boolean;
    hot_stages: { stage: string; points: number; pain_total: number; top: string }[];
    affected_groups: string[];
    worst_rated: { label: string; average: number; count: number }[];
    disagreements: { label: string; spread: number }[];
}

interface Participant {
    id: number;
    name: string;
    role: string | null;
    status: string;
    scheduled_at: string | null;
    invite_reason: string | null;
    notes: string | null;
    area_value_id: number | null;
    value_ids: number[];
    checked_item_ids: number[];
}

interface ChecklistItem {
    id: number;
    label: string;
}

interface Template {
    id: number;
    name: string;
    channel: 'email' | 'chat';
    subject: string | null;
    body: string;
}

interface Props {
    initiative: InitiativeSummary;
    canManage: boolean;
    target: number | null;
    areaCategoryId: number;
    areas: Value[];
    groups: Group[];
    coverage: Coverage;
    statusCounts: Record<string, number>;
    suggestions: Suggestions;
    participants: Participant[] | null;
    checklist: ChecklistItem[];
    templates: Template[];
    statuses: Option[];
    mergeFields: string[];
    facilitator: string;
}

type Tab = 'plan' | 'participants' | 'setup';

export default function ListeningTour(props: Props) {
    const { initiative, canManage } = props;
    const [tab, setTab] = useState<Tab>('plan');
    const tabs: { key: Tab; label: string }[] = [
        { key: 'plan', label: 'Plan & coverage' },
        ...(canManage ? [{ key: 'participants' as Tab, label: `Participants (${props.participants?.length ?? 0})` }] : []),
        { key: 'setup', label: 'Groups, checklist & invites' },
    ];

    return (
        <InitiativeLayout initiative={initiative} title="Plan the listening tour">
            <div className="flex flex-wrap gap-1 rounded-lg border p-1" role="tablist">
                {tabs.map((t) => (
                    <button
                        key={t.key}
                        type="button"
                        role="tab"
                        aria-selected={tab === t.key}
                        onClick={() => setTab(t.key)}
                        data-test={`tour-tab-${t.key}`}
                        className={cn(
                            'rounded-md px-3 py-1.5 text-sm',
                            tab === t.key ? 'bg-secondary font-medium' : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {tab === 'plan' && <PlanTab {...props} onGoToSetup={() => setTab('setup')} onGoToParticipants={() => setTab('participants')} />}
            {tab === 'participants' && canManage && <ParticipantsTab {...props} />}
            {tab === 'setup' && <SetupTab {...props} />}
        </InitiativeLayout>
    );
}

/* ----------------------------------------------------------------------------------------------
 * Plan & coverage
 * --------------------------------------------------------------------------------------------*/

function PlanTab(props: Props & { onGoToSetup: () => void; onGoToParticipants: () => void }) {
    const { coverage, canManage } = props;

    return (
        <div className="space-y-6">
            <WhereToLook {...props} />
            <Card>
                <CardHeader>
                    <CardTitle>Coverage</CardTitle>
                    <CardDescription>
                        How many people you plan to talk to (everyone except those who declined), by work area and group. The Total column should
                        match each group's columns added up, because everyone has one value per group.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <TargetBar {...props} />
                    {props.areas.length === 0 && coverage.total === 0 ? (
                        <div className="text-muted-foreground rounded-lg border border-dashed p-6 text-sm">
                            No work areas yet. {canManage ? 'Add the teams or areas you want covered in ' : 'A facilitator will add them in '}
                            <button type="button" className="text-teal-600 underline dark:text-teal-400" onClick={props.onGoToSetup}>
                                Groups, checklist &amp; invites
                            </button>
                            , then add people to the tour.
                        </div>
                    ) : (
                        <CoverageMatrix {...props} />
                    )}
                    <CoverageWarnings coverage={coverage} />
                    {!canManage && (
                        <p className="text-muted-foreground text-xs">
                            Participant names and notes are only visible to the people running this initiative.
                        </p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

function WhereToLook({ suggestions, initiative }: Props) {
    if (!suggestions.has_data) {
        return (
            <Card className="border-dashed" data-test="tour-suggestions">
                <CardHeader>
                    <CardTitle>Where to point the tour</CardTitle>
                    <CardDescription>
                        The tour works best when it tests what you already suspect. Nothing has been captured in the earlier steps yet, so start
                        there. This panel will then suggest where to look.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('initiatives.friction', initiative.id)}>2. Log friction</Link>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('initiatives.ratings', initiative.id)}>4. Rate the workflow</Link>
                    </Button>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card data-test="tour-suggestions">
            <CardHeader>
                <CardTitle>Where to point the tour</CardTitle>
                <CardDescription>From steps 1–4: what the data so far suggests. Plan the tour to confirm or overturn it.</CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4 md:grid-cols-2">
                {suggestions.hot_stages.length > 0 && (
                    <div>
                        <div className="mb-1 text-sm font-medium">Stages that hurt most</div>
                        <ul className="text-muted-foreground space-y-1 text-sm">
                            {suggestions.hot_stages.map((s) => (
                                <li key={s.stage}>
                                    <span className="text-foreground font-medium">{s.stage}</span>: {s.points} friction point
                                    {s.points === 1 ? '' : 's'}, worst: “{s.top}”. Talk to people who work here.
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
                {suggestions.worst_rated.length > 0 && (
                    <div>
                        <div className="mb-1 text-sm font-medium">Lowest-rated parts of the workflow</div>
                        <ul className="text-muted-foreground space-y-1 text-sm">
                            {suggestions.worst_rated.map((r) => (
                                <li key={r.label}>
                                    <span className="text-foreground font-medium">{r.label}</span>: averages {r.average} from {r.count}. Ask what
                                    makes it hard.
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
                {suggestions.affected_groups.length > 0 && (
                    <div>
                        <div className="mb-1 text-sm font-medium">Make sure the tour includes</div>
                        <div className="flex flex-wrap gap-1.5">
                            {suggestions.affected_groups.map((g) => (
                                <Badge key={g} variant="secondary">
                                    {g}
                                </Badge>
                            ))}
                        </div>
                        <p className="text-muted-foreground mt-1 text-xs">The groups named on high-pain (4–5) friction points.</p>
                    </div>
                )}
                {suggestions.disagreements.length > 0 && (
                    <div>
                        <div className="mb-1 text-sm font-medium">Where people disagree</div>
                        <ul className="text-muted-foreground space-y-1 text-sm">
                            {suggestions.disagreements.map((d) => (
                                <li key={d.label}>
                                    <span className="text-foreground font-medium">{d.label}</span>: ratings span {d.spread} points. Interview people
                                    from both ends.
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function TargetBar({ coverage, statusCounts, statuses, canManage, initiative, target }: Props) {
    const { data, setData, put, processing } = useForm({ interview_target: target ? String(target) : '' });

    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            {canManage ? (
                <form
                    className="flex items-center gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        put(route('initiatives.listening-tour.target', initiative.id), { preserveScroll: true });
                    }}
                >
                    <Label htmlFor="interview_target">Target</Label>
                    <Input
                        id="interview_target"
                        type="number"
                        min={1}
                        className="h-8 w-20"
                        value={data.interview_target}
                        onChange={(e) => setData('interview_target', e.target.value)}
                    />
                    <Button type="submit" size="sm" variant="outline" disabled={processing}>
                        Set
                    </Button>
                </form>
            ) : (
                coverage.target !== null && <span>Target: {coverage.target}</span>
            )}
            <span data-test="tour-progress">
                <span className="font-semibold tabular-nums">{coverage.total}</span> planned
                {coverage.remaining !== null && (coverage.remaining > 0 ? ` · ${coverage.remaining} to go` : ' · target reached')}
            </span>
            <div className="flex flex-wrap gap-1.5">
                {statuses.map((s) => (
                    <Badge key={s.value} variant="outline" className="font-normal">
                        {s.label}: {statusCounts[s.value] ?? 0}
                    </Badge>
                ))}
            </div>
        </div>
    );
}

function CoverageMatrix({ areas, groups, coverage }: Props) {
    const issueAreas = new Set(coverage.issues.map((i) => i.area));
    const cellClass = (count: number) =>
        count === 0 ? 'text-muted-foreground/40' : 'bg-teal-100 font-semibold text-teal-900 dark:bg-teal-950 dark:text-teal-100';

    return (
        <div className="overflow-x-auto">
            <table className="w-full border-separate border-spacing-1 text-sm" data-test="coverage-matrix">
                <thead>
                    <tr>
                        <th />
                        <th />
                        {groups.map((g) => (
                            <th
                                key={g.id}
                                colSpan={Math.max(g.values.length, 1)}
                                className="border-b px-2 pb-1 text-center text-xs font-medium tracking-wide uppercase"
                            >
                                {g.name}
                            </th>
                        ))}
                    </tr>
                    <tr className="text-muted-foreground">
                        <th className="px-2 text-left font-medium">Work area</th>
                        <th className="px-2 text-center font-medium">Total</th>
                        {groups.map((g) =>
                            g.values.length ? (
                                g.values.map((v) => (
                                    <th key={v.id} className="px-2 text-center font-medium whitespace-nowrap">
                                        {v.name}
                                    </th>
                                ))
                            ) : (
                                <th key={g.id} className="px-2 text-center text-xs font-normal">
                                    no values
                                </th>
                            ),
                        )}
                    </tr>
                </thead>
                <tbody>
                    {coverage.rows.map((row) => (
                        <tr key={row.area_id ?? 'none'} data-test={row.area_id === null ? 'coverage-row-none' : undefined}>
                            <td
                                className={cn(
                                    'px-2 py-1 font-medium whitespace-nowrap',
                                    row.area_id === null && 'text-amber-700 dark:text-amber-300',
                                )}
                            >
                                {issueAreas.has(row.area) && <AlertTriangle className="mr-1 inline size-3.5 text-amber-500" />}
                                {row.area}
                            </td>
                            <td className="text-center font-semibold tabular-nums">{row.total}</td>
                            {groups.map((g) =>
                                g.values.length ? (
                                    g.values.map((v) => (
                                        <td
                                            key={v.id}
                                            className={cn('h-9 min-w-14 rounded-md text-center tabular-nums', cellClass(row.counts[v.id] ?? 0))}
                                        >
                                            {row.counts[v.id] ?? 0}
                                        </td>
                                    ))
                                ) : (
                                    <td key={g.id} />
                                ),
                            )}
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr>
                        <td className="text-muted-foreground px-2 pt-2 font-medium">Total</td>
                        <td className="pt-2 text-center font-semibold tabular-nums" data-test="coverage-total">
                            {coverage.total}
                        </td>
                        {groups.map((g) =>
                            g.values.length ? (
                                g.values.map((v) => (
                                    <td key={v.id} className="pt-2 text-center font-semibold tabular-nums">
                                        {coverage.column_totals[v.id] ?? 0}
                                    </td>
                                ))
                            ) : (
                                <td key={g.id} />
                            ),
                        )}
                    </tr>
                </tfoot>
            </table>
            {areas.length === 0 && <p className="text-muted-foreground mt-2 text-xs">No work areas defined yet.</p>}
        </div>
    );
}

function CoverageWarnings({ coverage }: { coverage: Coverage }) {
    const items: string[] = [
        ...coverage.issues.map(
            (i) =>
                `Totals check: ${i.missing} ${i.missing === 1 ? 'person' : 'people'} in ${i.area} ${i.missing === 1 ? 'has' : 'have'} no ${i.category} value.`,
        ),
        ...coverage.empty_areas.map((a) => `Nobody from ${a} is on the tour yet.`),
        ...coverage.empty_values.map((v) => `No one in the ${v.category} group “${v.value}” yet.`),
    ];

    if (items.length === 0) return null;

    return (
        <ul
            className="space-y-1 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100"
            data-test="tour-gaps"
        >
            {items.map((text) => (
                <li key={text} className="flex gap-2">
                    <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                    {text}
                </li>
            ))}
        </ul>
    );
}

/* ----------------------------------------------------------------------------------------------
 * Participants
 * --------------------------------------------------------------------------------------------*/

function ParticipantsTab(props: Props) {
    const { participants, areas, groups, statuses, checklist, templates, initiative } = props;
    const [editing, setEditing] = useState<Participant | 'new' | null>(null);
    const [inviting, setInviting] = useState<Participant | null>(null);
    const areaName = (id: number | null) => areas.find((a) => a.id === id)?.name;
    const valueName = (id: number) => groups.flatMap((g) => g.values).find((v) => v.id === id)?.name;
    const statusLabel = (s: string) => statuses.find((o) => o.value === s)?.label ?? s;

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between gap-2">
                <p className="text-muted-foreground text-sm">
                    Everyone you plan to interview. Only facilitators of this initiative can see this list.
                </p>
                <Button onClick={() => setEditing('new')} data-test="participant-open">
                    <Plus /> Add person
                </Button>
            </div>

            {participants && participants.length > 0 ? (
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Person</th>
                                <th className="px-3 py-2 font-medium">Work area</th>
                                <th className="px-3 py-2 font-medium">Groups</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                                <th className="px-3 py-2 font-medium">Prep</th>
                                <th className="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {participants.map((p) => (
                                <tr key={p.id} className="border-t align-top">
                                    <td className="px-3 py-2">
                                        <div className="font-medium">{p.name}</div>
                                        {p.role && <div className="text-muted-foreground">{p.role}</div>}
                                    </td>
                                    <td className="px-3 py-2">
                                        {areaName(p.area_value_id) ?? (
                                            <Badge variant="outline" className="border-amber-400 text-amber-700 dark:text-amber-300">
                                                No area
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        <div className="flex flex-wrap gap-1">
                                            {p.value_ids.map((id) => (
                                                <Badge key={id} variant="secondary" className="font-normal">
                                                    {valueName(id)}
                                                </Badge>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2 whitespace-nowrap">
                                        <Badge variant={p.status === 'interviewed' ? 'default' : 'outline'}>{statusLabel(p.status)}</Badge>
                                        {p.scheduled_at && (
                                            <div className="text-muted-foreground mt-1 text-xs">{p.scheduled_at.replace('T', ' ')}</div>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 tabular-nums">
                                        {checklist.length ? `${p.checked_item_ids.length}/${checklist.length}` : '—'}
                                    </td>
                                    <td className="px-3 py-2 whitespace-nowrap">
                                        {templates.length > 0 && (
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                className="size-7"
                                                onClick={() => setInviting(p)}
                                                aria-label="Write invite"
                                                data-test={`invite-${p.id}`}
                                            >
                                                <Mail />
                                            </Button>
                                        )}
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            className="size-7"
                                            onClick={() => setEditing(p)}
                                            aria-label="Edit"
                                            data-test={`participant-edit-${p.id}`}
                                        >
                                            <Pencil />
                                        </Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            className="size-7"
                                            aria-label="Remove"
                                            onClick={() => {
                                                if (window.confirm(`Remove ${p.name} from the tour?`)) {
                                                    router.delete(
                                                        route('initiatives.participants.destroy', { initiative: initiative.id, participant: p.id }),
                                                        { preserveScroll: true },
                                                    );
                                                }
                                            }}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div className="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm">
                    No one on the tour yet. Add people from the areas and groups the Plan tab says to cover.
                </div>
            )}

            {editing !== null && <ParticipantDialog {...props} participant={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
            {inviting && <InviteDialog {...props} participant={inviting} onClose={() => setInviting(null)} />}
        </div>
    );
}

function ParticipantDialog({
    initiative,
    areas,
    groups,
    statuses,
    checklist,
    participant,
    onClose,
}: Props & { participant: Participant | null; onClose: () => void }) {
    const valueFor = (group: Group) => String(participant?.value_ids.find((id) => group.values.some((v) => v.id === id)) ?? '');
    const { data, setData, post, patch, transform, processing, errors } = useForm({
        name: participant?.name ?? '',
        role: participant?.role ?? '',
        status: participant?.status ?? 'candidate',
        scheduled_at: participant?.scheduled_at ?? '',
        invite_reason: participant?.invite_reason ?? '',
        notes: participant?.notes ?? '',
        area_value_id: participant?.area_value_id ? String(participant.area_value_id) : '',
        groupValues: Object.fromEntries(groups.map((g) => [g.id, valueFor(g)])) as Record<string, string>,
    });

    // The form keeps one select per group; the server wants a flat list of value ids.
    transform(({ groupValues, ...rest }) => ({
        ...rest,
        area_value_id: rest.area_value_id || null,
        scheduled_at: rest.scheduled_at || null,
        value_ids: Object.values(groupValues).filter(Boolean).map(Number),
    }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (participant) {
            patch(route('initiatives.participants.update', { initiative: initiative.id, participant: participant.id }), options);
        } else {
            post(route('initiatives.participants.store', initiative.id), options);
        }
    };

    const toggleCheck = (itemId: number) =>
        participant &&
        router.post(
            route('initiatives.participants.checklist', { initiative: initiative.id, participant: participant.id, checklistItem: itemId }),
            {},
            { preserveScroll: true, preserveState: true },
        );

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogTitle>{participant ? `Edit ${participant.name}` : 'Add someone to the tour'}</DialogTitle>
                <DialogDescription>Pick one value per group so the coverage counts add up.</DialogDescription>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="role">Role</Label>
                        <Input
                            id="role"
                            value={data.role}
                            onChange={(e) => setData('role', e.target.value)}
                            placeholder="e.g. Senior iOS developer"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="area_value_id">Work area</Label>
                        <NativeSelect id="area_value_id" value={data.area_value_id} onChange={(e) => setData('area_value_id', e.target.value)}>
                            <option value="">No area yet</option>
                            {areas.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name}
                                </option>
                            ))}
                        </NativeSelect>
                        <InputError message={(errors as Record<string, string>).area_value_id} />
                    </div>
                    {groups.map((g) => (
                        <div key={g.id} className="grid gap-2">
                            <Label htmlFor={`group-${g.id}`}>{g.name}</Label>
                            <NativeSelect
                                id={`group-${g.id}`}
                                value={data.groupValues[g.id] ?? ''}
                                onChange={(e) => setData('groupValues', { ...data.groupValues, [g.id]: e.target.value })}
                            >
                                <option value="">Not set</option>
                                {g.values.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </div>
                    ))}
                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <NativeSelect id="status" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                            {statuses.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="scheduled_at">Scheduled for</Label>
                        <Input
                            id="scheduled_at"
                            type="datetime-local"
                            value={data.scheduled_at}
                            onChange={(e) => setData('scheduled_at', e.target.value)}
                        />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="invite_reason">Why them? (used in invites as {'{{reason}}'})</Label>
                        <Input
                            id="invite_reason"
                            value={data.invite_reason}
                            onChange={(e) => setData('invite_reason', e.target.value)}
                            placeholder="e.g. you've been closest to the mobile release process"
                        />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="notes">Notes</Label>
                        <Textarea id="notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </div>
                    {Object.entries(errors)
                        .filter(([k]) => k.startsWith('value_ids'))
                        .map(([k, m]) => (
                            <InputError key={k} className="sm:col-span-2" message={m} />
                        ))}

                    {participant && checklist.length > 0 && (
                        <div className="space-y-2 sm:col-span-2">
                            <div className="text-sm font-medium">Pre-interview checklist</div>
                            {checklist.map((item) => (
                                <label key={item.id} className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={participant.checked_item_ids.includes(item.id)}
                                        onCheckedChange={() => toggleCheck(item.id)}
                                        data-test={`check-${participant.id}-${item.id}`}
                                    />
                                    {item.label}
                                </label>
                            ))}
                        </div>
                    )}

                    <DialogFooter className="sm:col-span-2">
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing} data-test="participant-submit">
                            {participant ? 'Save' : 'Add to tour'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function InviteDialog({ templates, facilitator, initiative, participant, onClose }: Props & { participant: Participant; onClose: () => void }) {
    const [templateId, setTemplateId] = useState(templates[0]?.id);
    const [copied, setCopied] = useState(false);
    const template = templates.find((t) => t.id === templateId) ?? templates[0];
    const values = {
        name: participant.name,
        first_name: participant.name.split(' ')[0],
        role: participant.role,
        reason: participant.invite_reason,
        facilitator,
        initiative: initiative.name,
    };
    const subject = template.subject ? fillTemplate(template.subject, values) : null;
    const body = fillTemplate(template.body, values);

    const copy = async () => {
        await navigator.clipboard.writeText(subject ? `Subject: ${subject}\n\n${body}` : body);
        setCopied(true);
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-xl">
                <DialogTitle>Invite {participant.name}</DialogTitle>
                <DialogDescription>Filled in from the template. Copy it into your email or chat tool.</DialogDescription>
                <NativeSelect value={template.id} onChange={(e) => setTemplateId(Number(e.target.value))} aria-label="Template">
                    {templates.map((t) => (
                        <option key={t.id} value={t.id}>
                            {t.name}
                        </option>
                    ))}
                </NativeSelect>
                <div className="bg-muted space-y-2 rounded-md p-3 text-sm" data-test="invite-preview">
                    {subject && <div className="font-medium">Subject: {subject}</div>}
                    <div className="whitespace-pre-wrap">{body}</div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={copy}>
                        <Copy /> {copied ? 'Copied' : 'Copy'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/* ----------------------------------------------------------------------------------------------
 * Setup: groups, checklist, templates
 * --------------------------------------------------------------------------------------------*/

function SetupTab(props: Props) {
    const { initiative, canManage, areas, areaCategoryId, groups, checklist } = props;

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Groups to cover</CardTitle>
                    <CardDescription>
                        Work areas are the rows of the coverage matrix. Add other groups that matter (for example experience level or time at the
                        company). Each person gets at most one value per group.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    <ValueChips
                        title="Work areas"
                        values={areas}
                        canManage={canManage}
                        addRoute={route('initiatives.segment-values.store', { initiative: initiative.id, segmentCategory: areaCategoryId })}
                        removeRoute={(id) => route('initiatives.segment-values.destroy', { initiative: initiative.id, segmentValue: id })}
                        testId="area"
                    />
                    {groups.map((g) => (
                        <ValueChips
                            key={g.id}
                            title={g.name}
                            values={g.values}
                            canManage={canManage}
                            addRoute={route('initiatives.segment-values.store', { initiative: initiative.id, segmentCategory: g.id })}
                            removeRoute={(id) => route('initiatives.segment-values.destroy', { initiative: initiative.id, segmentValue: id })}
                            onRemoveGroup={() => {
                                if (window.confirm(`Remove the "${g.name}" group and its values from everyone?`)) {
                                    router.delete(
                                        route('initiatives.segment-categories.destroy', { initiative: initiative.id, segmentCategory: g.id }),
                                        { preserveScroll: true },
                                    );
                                }
                            }}
                            testId={`group-${g.id}`}
                        />
                    ))}
                    {canManage && (
                        <QuickAdd
                            placeholder="New group, e.g. Experience level"
                            routeName={route('initiatives.segment-categories.store', initiative.id)}
                            field="name"
                            testId="group-new"
                        />
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Pre-interview checklist</CardTitle>
                    <CardDescription>
                        What to have ready before each conversation. You check these off per person in the Participants tab.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-2">
                    {checklist.length === 0 && <p className="text-muted-foreground text-sm">No checklist items yet.</p>}
                    <ul className="space-y-1">
                        {checklist.map((item) => (
                            <li key={item.id} className="flex items-center justify-between gap-2 text-sm">
                                <span>{item.label}</span>
                                {canManage && (
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        className="size-7"
                                        aria-label="Remove item"
                                        onClick={() =>
                                            router.delete(
                                                route('initiatives.checklist-items.destroy', { initiative: initiative.id, checklistItem: item.id }),
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <X />
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                    {canManage && (
                        <QuickAdd
                            placeholder="Add a checklist item"
                            routeName={route('initiatives.checklist-items.store', initiative.id)}
                            field="label"
                            testId="checklist-new"
                        />
                    )}
                </CardContent>
            </Card>

            <div className="lg:col-span-2">
                <Templates {...props} />
            </div>
        </div>
    );
}

function ValueChips({
    title,
    values,
    canManage,
    addRoute,
    removeRoute,
    onRemoveGroup,
    testId,
}: {
    title: string;
    values: Value[];
    canManage: boolean;
    addRoute: string;
    removeRoute: (id: number) => string;
    onRemoveGroup?: () => void;
    testId: string;
}) {
    return (
        <div className="space-y-2">
            <div className="flex items-center gap-2 text-sm font-medium">
                {title}
                {canManage && onRemoveGroup && (
                    <button
                        type="button"
                        onClick={onRemoveGroup}
                        className="text-muted-foreground hover:text-destructive"
                        aria-label={`Remove ${title}`}
                    >
                        <Trash2 className="size-3.5" />
                    </button>
                )}
            </div>
            <div className="flex flex-wrap items-center gap-1.5">
                {values.length === 0 && <span className="text-muted-foreground text-sm">None yet</span>}
                {values.map((v) => (
                    <Badge key={v.id} variant="secondary" className="gap-1">
                        {v.name}
                        {canManage && (
                            <button
                                type="button"
                                aria-label={`Remove ${v.name}`}
                                className="hover:text-destructive"
                                onClick={() => {
                                    if (window.confirm(`Remove "${v.name}"? People in it will lose this value.`)) {
                                        router.delete(removeRoute(v.id), { preserveScroll: true });
                                    }
                                }}
                            >
                                <X className="size-3" />
                            </button>
                        )}
                    </Badge>
                ))}
            </div>
            {canManage && <QuickAdd placeholder={`Add to ${title}`} routeName={addRoute} field="name" testId={`${testId}-new-value`} />}
        </div>
    );
}

function QuickAdd({ placeholder, routeName, field, testId }: { placeholder: string; routeName: string; field: string; testId: string }) {
    const { data, setData, post, processing, errors, reset } = useForm<Record<string, string>>({ [field]: '' });

    return (
        <form
            className="space-y-1"
            onSubmit={(e) => {
                e.preventDefault();
                post(routeName, { preserveScroll: true, onSuccess: () => reset() });
            }}
        >
            <div className="flex max-w-sm gap-1.5">
                <Input
                    value={data[field]}
                    onChange={(e) => setData(field, e.target.value)}
                    placeholder={placeholder}
                    className="h-8"
                    data-test={testId}
                />
                <Button
                    type="submit"
                    size="icon"
                    variant="outline"
                    className="size-8 shrink-0"
                    disabled={processing || !data[field]}
                    aria-label={placeholder}
                >
                    <Plus />
                </Button>
            </div>
            <InputError message={errors[field]} />
        </form>
    );
}

function Templates({ initiative, canManage, templates, mergeFields }: Props) {
    const [editing, setEditing] = useState<Template | 'new' | null>(null);

    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-2">
                <div className="space-y-1.5">
                    <CardTitle>Invite templates</CardTitle>
                    <CardDescription>
                        Fill-in fields: {mergeFields.map((f) => `{{${f}}}`).join(', ')}. Use the envelope button in Participants to see an invite
                        filled in for a person.
                    </CardDescription>
                </div>
                {canManage && (
                    <Button variant="outline" size="sm" onClick={() => setEditing('new')}>
                        <Plus /> Template
                    </Button>
                )}
            </CardHeader>
            <CardContent className="grid gap-3 md:grid-cols-2">
                {templates.length === 0 && <p className="text-muted-foreground text-sm">No templates yet.</p>}
                {templates.map((t) => (
                    <div key={t.id} className="space-y-2 rounded-lg border p-3">
                        <div className="flex items-center gap-2">
                            {t.channel === 'email' ? <Mail className="size-4" /> : <MessageSquare className="size-4" />}
                            <span className="font-medium">{t.name}</span>
                            {canManage && (
                                <span className="ml-auto flex">
                                    <Button size="icon" variant="ghost" className="size-7" onClick={() => setEditing(t)} aria-label="Edit template">
                                        <Pencil />
                                    </Button>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        className="size-7"
                                        aria-label="Remove template"
                                        onClick={() => {
                                            if (window.confirm(`Remove the "${t.name}" template?`)) {
                                                router.delete(
                                                    route('initiatives.message-templates.destroy', {
                                                        initiative: initiative.id,
                                                        messageTemplate: t.id,
                                                    }),
                                                    {
                                                        preserveScroll: true,
                                                    },
                                                );
                                            }
                                        }}
                                    >
                                        <Trash2 />
                                    </Button>
                                </span>
                            )}
                        </div>
                        {t.subject && <div className="text-sm">Subject: {t.subject}</div>}
                        <p className="text-muted-foreground line-clamp-4 text-sm whitespace-pre-wrap">{t.body}</p>
                    </div>
                ))}
            </CardContent>
            {editing !== null && (
                <TemplateDialog initiativeId={initiative.id} template={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
            )}
        </Card>
    );
}

function TemplateDialog({ initiativeId, template, onClose }: { initiativeId: number; template: Template | null; onClose: () => void }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        name: template?.name ?? '',
        channel: template?.channel ?? 'email',
        subject: template?.subject ?? '',
        body: template?.body ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (template) {
            patch(route('initiatives.message-templates.update', { initiative: initiativeId, messageTemplate: template.id }), options);
        } else {
            post(route('initiatives.message-templates.store', initiativeId), options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-xl">
                <DialogTitle>{template ? 'Edit template' : 'New invite template'}</DialogTitle>
                <DialogDescription>Write it in your own voice. Fill-in fields are replaced per person.</DialogDescription>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="tpl-name">Name</Label>
                            <Input id="tpl-name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="tpl-channel">Channel</Label>
                            <NativeSelect
                                id="tpl-channel"
                                value={data.channel}
                                onChange={(e) => setData('channel', e.target.value as 'email' | 'chat')}
                            >
                                <option value="email">Email</option>
                                <option value="chat">Chat</option>
                            </NativeSelect>
                        </div>
                    </div>
                    {data.channel === 'email' && (
                        <div className="grid gap-2">
                            <Label htmlFor="tpl-subject">Subject</Label>
                            <Input id="tpl-subject" value={data.subject} onChange={(e) => setData('subject', e.target.value)} />
                        </div>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="tpl-body">Message</Label>
                        <Textarea id="tpl-body" value={data.body} onChange={(e) => setData('body', e.target.value)} className="min-h-48" required />
                        <InputError message={errors.body} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Save template
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
