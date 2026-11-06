import { AssistantChat } from '@/components/assistant-chat';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { activityHref } from '@/lib/walkthrough';
import { type GuidePayload, type SharedData, type WalkthroughActivity } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { BookOpen, ChevronLeft, ChevronRight, Pencil, X } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

const STORAGE_KEY = 'guide-open';

function readOpen(): boolean {
    try {
        return localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

function writeOpen(open: boolean) {
    try {
        localStorage.setItem(STORAGE_KEY, open ? '1' : '0');
    } catch {
        // Storage can be unavailable (private mode); the panel just won't remember.
    }
}

function stepHref(activity: WalkthroughActivity, initiativeId: number | null): string | null {
    return initiativeId === null ? null : activityHref(activity, initiativeId);
}

/**
 * Right-side guide panel. It docks beside the page instead of blocking it, so you can
 * read and work at the same time. Its open state is remembered across pages.
 */
export function GuideButton() {
    const { guide } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);

    useEffect(() => setOpen(readOpen()), []);

    useEffect(() => {
        document.body.classList.toggle('guide-open', open && guide !== null);
        return () => document.body.classList.remove('guide-open');
    }, [open, guide]);

    if (!guide) return null;

    const change = (next: boolean) => {
        setOpen(next);
        writeOpen(next);
    };

    return (
        <>
            <Button
                variant={open ? 'secondary' : 'outline'}
                size="sm"
                onClick={() => change(!open)}
                className="gap-1.5"
                aria-expanded={open}
                data-test="guide-toggle"
            >
                <BookOpen className="size-4" /> Guide
            </Button>
            <aside
                aria-label="Guide"
                data-test="guide-panel"
                className={cn(
                    'bg-background fixed inset-y-0 right-0 z-40 flex w-full flex-col overflow-y-auto border-l shadow-xl transition-transform duration-300 sm:w-[26rem]',
                    open ? 'translate-x-0' : 'pointer-events-none translate-x-full',
                )}
                inert={!open}
            >
                <Button variant="ghost" size="icon" className="absolute top-3 right-3 size-8" onClick={() => change(false)} aria-label="Close guide">
                    <X />
                </Button>
                <GuidePanel key={guide.key} guide={guide} />
            </aside>
        </>
    );
}

function GuidePanel({ guide }: { guide: GuidePayload }) {
    const [editing, setEditing] = useState(false);
    const index = guide.activities.findIndex((a) => a.key === guide.key);
    const current = index >= 0 ? guide.activities[index] : null;
    const prev = index > 0 ? guide.activities[index - 1] : null;
    const next = index >= 0 && index < guide.activities.length - 1 ? guide.activities[index + 1] : null;

    return (
        <>
            <div className="space-y-1 border-b p-6 pr-14 pb-4">
                <div className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                    {current ? `Step ${current.step} of ${guide.activities.length} · ${current.phase_label}` : 'Guide'}
                </div>
                <h2 className="text-lg font-semibold">{guide.title}</h2>
            </div>

            <div className="flex-1 space-y-6 p-6">
                {editing ? (
                    <GuideEditor guide={guide} onDone={() => setEditing(false)} />
                ) : (
                    <div>
                        <div className="guide-prose text-muted-foreground" dangerouslySetInnerHTML={{ __html: guide.html }} />
                        {guide.canEdit && (
                            <div className="flex items-center gap-2">
                                <Button variant="ghost" size="sm" onClick={() => setEditing(true)} className="gap-1.5">
                                    <Pencil className="size-3.5" /> Edit guide
                                </Button>
                                {guide.customized && <Badge variant="outline">Edited by an admin</Badge>}
                            </div>
                        )}
                    </div>
                )}

                {guide.initiativeId !== null && <ActivityNotes guide={guide} />}

                {current && (
                    <div className="flex justify-between gap-2 border-t pt-4">
                        <StepLink activity={prev} initiativeId={guide.initiativeId} direction="prev" />
                        <StepLink activity={next} initiativeId={guide.initiativeId} direction="next" />
                    </div>
                )}

                {guide.initiativeId !== null && <AllSteps guide={guide} />}

                <AssistantChat guide={guide} />
            </div>
        </>
    );
}

function StepLink({
    activity,
    initiativeId,
    direction,
}: {
    activity: WalkthroughActivity | null;
    initiativeId: number | null;
    direction: 'prev' | 'next';
}) {
    if (!activity) return <span />;
    const href = stepHref(activity, initiativeId);
    const label = (
        <>
            {direction === 'prev' && <ChevronLeft className="size-4" />}
            <span className="truncate">{activity.title}</span>
            {direction === 'next' && <ChevronRight className="size-4" />}
        </>
    );

    return href ? (
        <Button variant="outline" size="sm" asChild className="max-w-[48%]">
            <Link href={href} prefetch data-test={`guide-${direction}`}>
                {label}
            </Link>
        </Button>
    ) : (
        <Button variant="outline" size="sm" disabled className="max-w-[48%]" title="Not built yet">
            {label}
        </Button>
    );
}

function AllSteps({ guide }: { guide: GuidePayload }) {
    const phases = [...new Set(guide.activities.map((a) => a.phase_label))];

    return (
        <details className="border-t pt-4">
            <summary className="cursor-pointer text-sm font-medium">All steps</summary>
            <div className="mt-3 space-y-3">
                {phases.map((phase) => (
                    <div key={phase}>
                        <div className="text-muted-foreground mb-1 text-xs font-medium tracking-wide uppercase">{phase}</div>
                        <ol className="space-y-0.5">
                            {guide.activities
                                .filter((a) => a.phase_label === phase)
                                .map((a) => {
                                    const href = stepHref(a, guide.initiativeId);
                                    const text = `${a.step}. ${a.title}`;
                                    return (
                                        <li key={a.key} className={cn('text-sm', a.key === guide.key && 'font-semibold')}>
                                            {href ? (
                                                <Link href={href} className={cn('hover:underline', !a.route && 'text-muted-foreground/70')}>
                                                    {text}
                                                    {!a.route && ' (planned)'}
                                                </Link>
                                            ) : (
                                                <span className="text-muted-foreground/60">{text}</span>
                                            )}
                                        </li>
                                    );
                                })}
                        </ol>
                    </div>
                ))}
            </div>
        </details>
    );
}

function GuideEditor({ guide, onDone }: { guide: GuidePayload; onDone: () => void }) {
    const { data, setData, put, processing, errors } = useForm({ body: guide.markdown ?? '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.guide.update', guide.key), { preserveScroll: true, onSuccess: onDone });
    };

    const reset = () => {
        if (window.confirm('Discard the edited version and go back to the default guide text?')) {
            router.delete(route('admin.guide.destroy', guide.key), { preserveScroll: true, onSuccess: onDone });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-2">
            <p className="text-muted-foreground text-xs">Markdown. Changes apply to every initiative.</p>
            <Textarea
                value={data.body}
                onChange={(e) => setData('body', e.target.value)}
                className="min-h-72 font-mono text-xs"
                data-test="guide-editor"
            />
            <InputError message={errors.body} />
            <div className="flex items-center justify-end gap-2">
                {guide.customized && (
                    <Button type="button" variant="ghost" size="sm" className="text-destructive mr-auto" onClick={reset}>
                        Reset to default
                    </Button>
                )}
                <Button type="button" variant="ghost" size="sm" onClick={onDone}>
                    Cancel
                </Button>
                <Button type="submit" size="sm" disabled={processing}>
                    Save guide
                </Button>
            </div>
        </form>
    );
}

function ActivityNotes({ guide }: { guide: GuidePayload }) {
    const { data, setData, put, processing, isDirty, recentlySuccessful } = useForm({ body: guide.note ?? '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('initiatives.notes.update', { initiative: guide.initiativeId!, key: guide.key }), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-2 border-t pt-4">
            <div className="text-sm font-medium">Notes for this initiative</div>
            {guide.canEditNote ? (
                <>
                    <Textarea
                        value={data.body}
                        onChange={(e) => setData('body', e.target.value)}
                        placeholder="Decisions, open questions, reflections…"
                        data-test="guide-notes"
                        className="min-h-28"
                    />
                    <div className="flex items-center justify-end gap-2">
                        {recentlySuccessful && <span className="text-muted-foreground text-xs">Saved</span>}
                        <Button type="submit" size="sm" variant="outline" disabled={processing || !isDirty}>
                            Save notes
                        </Button>
                    </div>
                </>
            ) : (
                <p className="text-muted-foreground text-sm whitespace-pre-line">{guide.note || 'No notes yet.'}</p>
            )}
        </form>
    );
}
