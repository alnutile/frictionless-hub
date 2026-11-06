import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import InitiativeLayout from '@/layouts/initiative-layout';
import { cn } from '@/lib/utils';
import { type InitiativeSummary, type Option } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, ChevronLeft, ChevronRight, Flame, Pencil, Plus, Tags, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Note {
    id: number;
    kind: string;
    body: string;
    workflow_stage_id: number | null;
    stage: string | null;
    friction_point_id: number | null;
    owner: string | null;
    done: boolean;
    code_ids: number[];
}

interface Ref {
    id: number;
    name: string;
}

interface Props {
    initiative: InitiativeSummary;
    interview: {
        id: number;
        status: 'draft' | 'complete';
        conducted_at: string | null;
        interviewer: string | null;
        responsibilities: string | null;
        tools: string | null;
        transcript: string | null;
    };
    participant: { name: string; role: string | null; area: string | null; groups: string[] };
    notes: Note[];
    kinds: Option[];
    stages: Ref[];
    codes: Ref[];
    previousId: number | null;
    nextId: number | null;
}

const KIND_HINTS: Record<string, string> = {
    pain_point: 'Where they lose time or energy. Link a stage so it can go to the friction log.',
    bright_spot: 'What works well and should be protected.',
    quote: 'Their words, worth repeating (without their name).',
    observation: 'Your own notes on patterns or context.',
    follow_up: 'Things to do or check afterwards, with an owner.',
};

export default function InterviewPage(props: Props) {
    const { initiative, interview, participant, notes, kinds, previousId, nextId } = props;

    return (
        <InitiativeLayout initiative={initiative} title={`Interview: ${participant.name}`}>
            <div className="flex flex-wrap items-center gap-2">
                <Button variant="ghost" size="sm" asChild>
                    <Link href={route('initiatives.interviews', initiative.id)}>
                        <ArrowLeft /> All interviews
                    </Link>
                </Button>
                <div className="ml-auto flex gap-1">
                    {previousId && (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route('initiatives.interviews.show', [initiative.id, previousId])}>
                                <ChevronLeft /> Previous
                            </Link>
                        </Button>
                    )}
                    {nextId && (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route('initiatives.interviews.show', [initiative.id, nextId])}>
                                Next <ChevronRight />
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            <nav className="flex flex-wrap gap-1.5" aria-label="Jump to section" data-test="section-jump">
                {kinds.map((kind) => {
                    const items = notes.filter((n) => n.kind === kind.value);
                    const open = kind.value === 'follow_up' ? items.filter((n) => !n.done).length : 0;
                    return (
                        <a
                            key={kind.value}
                            href={`#section-${kind.value}`}
                            className="hover:bg-accent rounded-full border px-3 py-1 text-xs"
                            data-test={`jump-${kind.value}`}
                        >
                            {kind.label} <span className="text-muted-foreground tabular-nums">({items.length})</span>
                            {open > 0 && <span className="ml-1 text-amber-600 dark:text-amber-400">· {open} open</span>}
                        </a>
                    );
                })}
            </nav>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    {kinds.map((kind) => (
                        <NoteSection key={kind.value} {...props} kind={kind} notes={notes.filter((n) => n.kind === kind.value)} />
                    ))}
                </div>
                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">{participant.name}</CardTitle>
                            <CardDescription>
                                {[participant.role, participant.area].filter(Boolean).join(' · ') || 'No role or area recorded'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-1">
                            {participant.groups.map((g) => (
                                <Badge key={g} variant="secondary" className="font-normal">
                                    {g}
                                </Badge>
                            ))}
                        </CardContent>
                    </Card>
                    <InterviewDetails initiativeId={initiative.id} interview={interview} noteCount={notes.length} />
                </div>
            </div>
        </InitiativeLayout>
    );
}

function InterviewDetails({ initiativeId, interview, noteCount }: { initiativeId: number; interview: Props['interview']; noteCount: number }) {
    const { data, setData, patch, transform, processing, errors, isDirty } = useForm({
        conducted_at: interview.conducted_at ?? '',
        status: interview.status,
        responsibilities: interview.responsibilities ?? '',
        tools: interview.tools ?? '',
        transcript: interview.transcript ?? '',
    });

    const save = (status?: 'draft' | 'complete') => {
        // Themes are built from notes, so an empty "complete" interview quietly skews the data.
        if (status === 'complete' && noteCount === 0 && !window.confirm('No notes yet. Mark this interview complete anyway?')) {
            return;
        }
        transform((current) => ({ ...current, status: status ?? current.status }));
        patch(route('initiatives.interviews.update', [initiativeId, interview.id]), { preserveScroll: true });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        save();
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>About the conversation</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-2">
                        <Label htmlFor="conducted_at">When</Label>
                        <Input
                            id="conducted_at"
                            type="datetime-local"
                            value={data.conducted_at}
                            onChange={(e) => setData('conducted_at', e.target.value)}
                        />
                        {interview.interviewer && <p className="text-muted-foreground text-xs">Notes started by {interview.interviewer}</p>}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="responsibilities">Their main responsibilities</Label>
                        <Textarea id="responsibilities" value={data.responsibilities} onChange={(e) => setData('responsibilities', e.target.value)} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="tools">Tools and systems they mentioned</Label>
                        <Textarea id="tools" value={data.tools} onChange={(e) => setData('tools', e.target.value)} />
                    </div>
                    <details>
                        <summary className="cursor-pointer text-sm font-medium">Transcript (optional)</summary>
                        <Textarea
                            className="mt-2 min-h-48 text-xs"
                            value={data.transcript}
                            onChange={(e) => setData('transcript', e.target.value)}
                            placeholder="Paste a transcript or raw notes here."
                        />
                        <InputError message={errors.transcript} />
                    </details>
                    <div className="flex flex-wrap items-center justify-end gap-2 pt-2">
                        <Badge variant={interview.status === 'complete' ? 'default' : 'outline'} className="mr-auto" data-test="interview-status">
                            {interview.status === 'complete' ? 'Complete' : 'Draft'}
                        </Badge>
                        <Button type="submit" variant="outline" disabled={processing || !isDirty}>
                            Save
                        </Button>
                        {interview.status === 'draft' && (
                            <Button type="button" disabled={processing} onClick={() => save('complete')} data-test="interview-complete">
                                <Check /> Mark complete
                            </Button>
                        )}
                    </div>
                    <div className="border-t pt-3 text-right">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="text-destructive"
                            onClick={() => {
                                if (window.confirm('Delete these interview notes? This cannot be undone.')) {
                                    router.delete(route('initiatives.interviews.destroy', [initiativeId, interview.id]));
                                }
                            }}
                        >
                            Delete interview notes
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function NoteSection({ initiative, interview, stages, codes, kind, notes }: Props & { kind: Option; notes: Note[] }) {
    return (
        <Card id={`section-${kind.value}`} className="scroll-mt-4" data-test={`section-${kind.value}`}>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    {kind.label} <span className="text-muted-foreground text-sm font-normal">({notes.length})</span>
                </CardTitle>
                <CardDescription>{KIND_HINTS[kind.value]}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {notes.map((note) => (
                    <NoteItem key={note.id} initiativeId={initiative.id} interviewId={interview.id} note={note} stages={stages} codes={codes} />
                ))}
                <AddNote initiativeId={initiative.id} interviewId={interview.id} kind={kind.value} stages={stages} />
            </CardContent>
        </Card>
    );
}

function NoteItem({
    initiativeId,
    interviewId,
    note,
    stages,
    codes,
}: {
    initiativeId: number;
    interviewId: number;
    note: Note;
    stages: Ref[];
    codes: Ref[];
}) {
    const [editing, setEditing] = useState(false);
    const [coding, setCoding] = useState(false);
    const params = { initiative: initiativeId, interview: interviewId, interviewNote: note.id };
    const applied = codes.filter((c) => note.code_ids.includes(c.id));

    const setCodes = (codeIds: number[]) =>
        router.patch(route('initiatives.interview-notes.update', params), { body: note.body, code_ids: codeIds }, { preserveScroll: true });

    return (
        <div className={cn('group rounded-md border p-3', note.kind === 'follow_up' && note.done && 'opacity-60')} data-test={`note-${note.id}`}>
            {editing ? (
                <EditNote initiativeId={initiativeId} interviewId={interviewId} note={note} stages={stages} onDone={() => setEditing(false)} />
            ) : (
                <div className="flex items-start gap-2">
                    {note.kind === 'follow_up' && (
                        <Checkbox
                            className="mt-0.5"
                            checked={note.done}
                            onCheckedChange={() => router.post(route('initiatives.interview-notes.toggle', params), {}, { preserveScroll: true })}
                            aria-label="Done"
                            data-test={`followup-done-${note.id}`}
                        />
                    )}
                    <div className="flex-1 space-y-1">
                        <p className={cn('text-sm whitespace-pre-line', note.kind === 'quote' && 'italic', note.done && 'line-through')}>
                            {note.body}
                        </p>
                        <div className="text-muted-foreground flex flex-wrap items-center gap-1.5 text-xs">
                            {note.stage && <Badge variant="outline">{note.stage}</Badge>}
                            {note.owner && <span>Owner: {note.owner}</span>}
                            {applied.map((c) => (
                                <Badge key={c.id} variant="secondary" className="font-normal">
                                    {c.name}
                                </Badge>
                            ))}
                            {note.kind === 'pain_point' &&
                                (note.friction_point_id ? (
                                    <Link
                                        href={route('initiatives.friction', initiativeId)}
                                        className="text-teal-600 hover:underline dark:text-teal-400"
                                    >
                                        ✓ In friction log
                                    </Link>
                                ) : (
                                    <button
                                        type="button"
                                        className="inline-flex items-center gap-1 text-amber-700 hover:underline dark:text-amber-300"
                                        onClick={() =>
                                            router.post(route('initiatives.interview-notes.promote', params), {}, { preserveScroll: true })
                                        }
                                        data-test={`promote-${note.id}`}
                                    >
                                        <Flame className="size-3" /> Add to friction log
                                    </button>
                                ))}
                        </div>
                    </div>
                    <div className="flex">
                        <Button
                            size="icon"
                            variant="ghost"
                            className="size-7"
                            onClick={() => setCoding(!coding)}
                            aria-label="Codes"
                            data-test={`note-codes-${note.id}`}
                        >
                            <Tags />
                        </Button>
                        <Button size="icon" variant="ghost" className="size-7" onClick={() => setEditing(true)} aria-label="Edit note">
                            <Pencil />
                        </Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            className="size-7"
                            aria-label="Delete note"
                            onClick={() =>
                                window.confirm('Delete this note?') &&
                                router.delete(route('initiatives.interview-notes.destroy', params), { preserveScroll: true })
                            }
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </div>
            )}

            {coding && !editing && (
                <div className="mt-3 space-y-2 border-t pt-3">
                    <div className="flex flex-wrap gap-1.5">
                        {codes.length === 0 && <span className="text-muted-foreground text-xs">No codes yet. Create one below.</span>}
                        {codes.map((c) => {
                            const on = note.code_ids.includes(c.id);
                            return (
                                <button
                                    key={c.id}
                                    type="button"
                                    aria-pressed={on}
                                    onClick={() => setCodes(on ? note.code_ids.filter((id) => id !== c.id) : [...note.code_ids, c.id])}
                                    className={cn(
                                        'rounded-full border px-2.5 py-0.5 text-xs',
                                        on ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-accent',
                                    )}
                                    data-test={`code-chip-${note.id}-${c.id}`}
                                >
                                    {c.name}
                                </button>
                            );
                        })}
                    </div>
                    <NewCodeForNote initiativeId={initiativeId} noteId={note.id} />
                </div>
            )}
        </div>
    );
}

function NewCodeForNote({ initiativeId, noteId }: { initiativeId: number; noteId: number }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', note_id: noteId });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(route('initiatives.codes.store', initiativeId), { preserveScroll: true, onSuccess: () => reset('name') });
            }}
        >
            <div className="flex max-w-xs gap-1.5">
                <Input
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    placeholder="New code"
                    className="h-7 text-xs"
                    data-test={`new-code-${noteId}`}
                />
                <Button
                    type="submit"
                    size="icon"
                    variant="outline"
                    className="size-7 shrink-0"
                    disabled={processing || !data.name}
                    aria-label="Create and apply code"
                >
                    <Plus />
                </Button>
            </div>
            <InputError message={errors.name} />
        </form>
    );
}

function EditNote({
    initiativeId,
    interviewId,
    note,
    stages,
    onDone,
}: {
    initiativeId: number;
    interviewId: number;
    note: Note;
    stages: Ref[];
    onDone: () => void;
}) {
    const { data, setData, patch, processing, errors } = useForm({
        body: note.body,
        workflow_stage_id: note.workflow_stage_id ? String(note.workflow_stage_id) : '',
        owner: note.owner ?? '',
    });

    return (
        <form
            className="space-y-2"
            onSubmit={(e) => {
                e.preventDefault();
                patch(route('initiatives.interview-notes.update', { initiative: initiativeId, interview: interviewId, interviewNote: note.id }), {
                    preserveScroll: true,
                    onSuccess: onDone,
                });
            }}
        >
            <Textarea value={data.body} onChange={(e) => setData('body', e.target.value)} required />
            <InputError message={errors.body} />
            <NoteExtras
                kind={note.kind}
                stages={stages}
                stageId={data.workflow_stage_id}
                owner={data.owner}
                onStage={(v) => setData('workflow_stage_id', v)}
                onOwner={(v) => setData('owner', v)}
            />
            <div className="flex justify-end gap-2">
                <Button type="button" size="sm" variant="ghost" onClick={onDone}>
                    Cancel
                </Button>
                <Button type="submit" size="sm" disabled={processing}>
                    Save
                </Button>
            </div>
        </form>
    );
}

function AddNote({ initiativeId, interviewId, kind, stages }: { initiativeId: number; interviewId: number; kind: string; stages: Ref[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ kind, body: '', workflow_stage_id: '', owner: '' });

    return (
        <form
            className="space-y-2"
            onSubmit={(e) => {
                e.preventDefault();
                post(route('initiatives.interview-notes.store', { initiative: initiativeId, interview: interviewId }), {
                    preserveScroll: true,
                    onSuccess: () => reset('body', 'owner'),
                });
            }}
        >
            <Textarea
                value={data.body}
                onChange={(e) => setData('body', e.target.value)}
                placeholder="Add a note…"
                className="min-h-16"
                data-test={`note-input-${kind}`}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) e.currentTarget.form?.requestSubmit();
                }}
            />
            <InputError message={errors.body} />
            <div className="flex flex-wrap items-end gap-2">
                <NoteExtras
                    kind={kind}
                    stages={stages}
                    stageId={data.workflow_stage_id}
                    owner={data.owner}
                    onStage={(v) => setData('workflow_stage_id', v)}
                    onOwner={(v) => setData('owner', v)}
                />
                <Button
                    type="submit"
                    size="sm"
                    variant="outline"
                    className="ml-auto"
                    disabled={processing || !data.body.trim()}
                    data-test={`note-add-${kind}`}
                >
                    <Plus /> Add
                </Button>
            </div>
        </form>
    );
}

function NoteExtras({
    kind,
    stages,
    stageId,
    owner,
    onStage,
    onOwner,
}: {
    kind: string;
    stages: Ref[];
    stageId: string;
    owner: string;
    onStage: (v: string) => void;
    onOwner: (v: string) => void;
}) {
    if (kind === 'pain_point') {
        return (
            <NativeSelect
                value={stageId}
                onChange={(e) => onStage(e.target.value)}
                className="h-8 w-48 text-xs"
                aria-label="Stage"
                data-test={`note-stage-${kind}`}
            >
                <option value="">No stage</option>
                {stages.map((s) => (
                    <option key={s.id} value={s.id}>
                        {s.name}
                    </option>
                ))}
            </NativeSelect>
        );
    }

    if (kind === 'follow_up') {
        return (
            <Input
                value={owner}
                onChange={(e) => onOwner(e.target.value)}
                placeholder="Owner"
                className="h-8 w-48 text-xs"
                aria-label="Owner"
                data-test={`note-owner-${kind}`}
            />
        );
    }

    return null;
}
