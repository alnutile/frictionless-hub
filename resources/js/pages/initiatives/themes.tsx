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
import { Link, router, useForm } from '@inertiajs/react';
import { Flame, Layers, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface CodeRef {
    id: number;
    name: string;
}

interface Weight {
    notes: number;
    interviews: number;
    areas: { area: string; interviews: number }[];
}

interface Theme {
    id: number;
    name: string;
    summary: string | null;
    impact: string | null;
    effort: string | null;
    solutions: string | null;
    codes: CodeRef[];
    weight: Weight;
    friction_point: { id: number; title: string } | null;
}

interface Props {
    initiative: InitiativeSummary;
    canManage: boolean;
    themes: Theme[];
    unthemedCodes: { id: number; name: string; notes: number; interviews: number }[];
    codeCount: number;
    uncodedCount: number;
    uncodedNotes: { id: number; kind: string; body: string; interview_id: number; participant: string }[] | null;
    allCodes: (CodeRef & { theme_id: number | null })[];
    frictionPoints: { id: number; title: string }[];
    levels: Option[];
}

export default function Themes(props: Props) {
    const { initiative, canManage, themes, unthemedCodes, codeCount, uncodedCount, uncodedNotes } = props;
    const [editing, setEditing] = useState<Theme | 'new' | null>(null);
    const grouped = codeCount - unthemedCodes.length;

    return (
        <InitiativeLayout initiative={initiative} title="Synthesize themes">
            {codeCount === 0 ? (
                <Card className="border-dashed" data-test="themes-empty">
                    <CardHeader>
                        <CardTitle>Themes are built from interview codes</CardTitle>
                        <CardDescription>
                            There are no codes yet. In step 6, tag each interview note with short codes such as <code>slow-ci</code> or{' '}
                            <code>docs-gaps</code>. Then come back and group the codes that point at the same underlying problem into a theme.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button variant="outline" asChild>
                            <Link href={route('initiatives.interviews', initiative.id)}>6. Interviews</Link>
                        </Button>
                    </CardContent>
                </Card>
            ) : (
                <>
                    <div className="flex flex-wrap items-center gap-3" data-test="coverage-summary">
                        <p className="text-sm">
                            <span className="font-semibold tabular-nums">{grouped}</span> of <span className="tabular-nums">{codeCount}</span> codes
                            are in a theme
                            {uncodedCount > 0 && (
                                <span className="text-amber-700 dark:text-amber-300">
                                    {' '}
                                    · {uncodedCount} note{uncodedCount === 1 ? '' : 's'} not coded yet
                                </span>
                            )}
                        </p>
                        {canManage && (
                            <Button className="ml-auto" onClick={() => setEditing('new')} data-test="theme-new">
                                <Plus /> New theme
                            </Button>
                        )}
                    </div>

                    <div className="grid gap-6 lg:grid-cols-3">
                        <div className="space-y-4 lg:col-span-2">
                            {themes.length === 0 && (
                                <div className="text-muted-foreground rounded-xl border border-dashed p-8 text-sm">
                                    No themes yet. Look at the codes on the right that come up in the most interviews, and group the ones that
                                    describe the same underlying problem.
                                </div>
                            )}
                            {themes.map((theme) => (
                                <ThemeCard key={theme.id} {...props} theme={theme} onEdit={() => setEditing(theme)} />
                            ))}
                        </div>

                        <div className="space-y-6">
                            <Card data-test="unthemed-codes">
                                <CardHeader>
                                    <CardTitle>Codes not in a theme</CardTitle>
                                    <CardDescription>
                                        Each code can belong to one theme. Leftovers may be noise, or a theme you haven't named yet.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {unthemedCodes.length === 0 ? (
                                        <p className="text-muted-foreground text-sm">Every code is in a theme.</p>
                                    ) : (
                                        <ul className="space-y-2">
                                            {unthemedCodes.map((c) => (
                                                <li key={c.id} className="flex items-center gap-2 text-sm">
                                                    <span className="flex-1">
                                                        {c.name}{' '}
                                                        <span className="text-muted-foreground text-xs">
                                                            {c.interviews} interview{c.interviews === 1 ? '' : 's'}
                                                        </span>
                                                    </span>
                                                    {canManage && themes.length > 0 && (
                                                        <NativeSelect
                                                            className="h-8 w-36 text-xs"
                                                            value=""
                                                            aria-label={`Add ${c.name} to a theme`}
                                                            data-test={`assign-code-${c.id}`}
                                                            onChange={(e) =>
                                                                e.target.value &&
                                                                router.put(
                                                                    route('initiatives.codes.theme', [initiative.id, c.id]),
                                                                    { theme_id: Number(e.target.value) },
                                                                    { preserveScroll: true },
                                                                )
                                                            }
                                                        >
                                                            <option value="">Add to theme…</option>
                                                            {themes.map((t) => (
                                                                <option key={t.id} value={t.id}>
                                                                    {t.name}
                                                                </option>
                                                            ))}
                                                        </NativeSelect>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>

                            {uncodedCount > 0 && (
                                <Card data-test="uncoded-notes">
                                    <CardHeader>
                                        <CardTitle>Notes without codes</CardTitle>
                                        <CardDescription>These aren't counted in any theme yet. Code them in step 6.</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        {uncodedNotes ? (
                                            <ul className="space-y-2 text-sm">
                                                {uncodedNotes.map((n) => (
                                                    <li key={n.id}>
                                                        <Link
                                                            href={route('initiatives.interviews.show', [initiative.id, n.interview_id])}
                                                            className="hover:underline"
                                                        >
                                                            {n.body}
                                                        </Link>
                                                        <span className="text-muted-foreground text-xs"> · {n.participant}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : (
                                            <p className="text-muted-foreground text-sm">
                                                {uncodedCount} note(s). Only the people running this initiative can read them.
                                            </p>
                                        )}
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    </div>
                </>
            )}

            {editing !== null && <ThemeDialog {...props} theme={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
        </InitiativeLayout>
    );
}

function LevelBadge({ label, value }: { label: string; value: string | null }) {
    if (!value) return null;
    const tone =
        value === 'high'
            ? 'border-red-300 text-red-700 dark:text-red-300'
            : value === 'medium'
              ? 'border-amber-300 text-amber-700 dark:text-amber-300'
              : 'border-green-300 text-green-700 dark:text-green-300';
    return (
        <Badge variant="outline" className={cn('font-normal capitalize', tone)}>
            {label}: {value}
        </Badge>
    );
}

function ThemeCard({ initiative, canManage, theme, frictionPoints, onEdit }: Props & { theme: Theme; onEdit: () => void }) {
    return (
        <Card data-test={`theme-${theme.id}`}>
            <CardHeader className="flex-row items-start justify-between gap-3">
                <div className="space-y-1.5">
                    <CardTitle className="flex items-center gap-2">
                        <Layers className="size-4 text-teal-500" /> {theme.name}
                    </CardTitle>
                    <div className="flex flex-wrap gap-1.5 text-sm" data-test={`theme-weight-${theme.id}`}>
                        <Badge>
                            {theme.weight.interviews} interview{theme.weight.interviews === 1 ? '' : 's'}
                        </Badge>
                        <Badge variant="secondary">
                            {theme.weight.notes} note{theme.weight.notes === 1 ? '' : 's'}
                        </Badge>
                        <LevelBadge label="Impact" value={theme.impact} />
                        <LevelBadge label="Effort" value={theme.effort} />
                    </div>
                </div>
                {canManage && (
                    <div className="flex shrink-0">
                        <Button size="sm" variant="ghost" asChild>
                            <Link href={route('initiatives.themes.show', [initiative.id, theme.id])} data-test={`theme-evidence-${theme.id}`}>
                                <Search /> Evidence
                            </Link>
                        </Button>
                        <Button size="icon" variant="ghost" className="size-8" onClick={onEdit} aria-label="Edit theme">
                            <Pencil />
                        </Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            className="size-8"
                            aria-label="Delete theme"
                            onClick={() =>
                                window.confirm(`Delete "${theme.name}"? Its codes go back to the unthemed list.`) &&
                                router.delete(route('initiatives.themes.destroy', [initiative.id, theme.id]), { preserveScroll: true })
                            }
                        >
                            <Trash2 />
                        </Button>
                    </div>
                )}
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
                {theme.summary && <p className="whitespace-pre-line">{theme.summary}</p>}
                <div className="flex flex-wrap items-center gap-1.5">
                    <span className="text-muted-foreground text-xs">Codes:</span>
                    {theme.codes.map((c) => (
                        <Badge key={c.id} variant="outline" className="font-normal">
                            {c.name}
                        </Badge>
                    ))}
                    {theme.codes.length === 0 && <span className="text-muted-foreground text-xs">none yet</span>}
                </div>
                {theme.weight.areas.length > 0 && (
                    <div className="text-muted-foreground text-xs">
                        Heard from: {theme.weight.areas.map((a) => `${a.area} (${a.interviews})`).join(', ')}
                    </div>
                )}
                {theme.solutions && (
                    <div>
                        <div className="text-muted-foreground text-xs font-medium uppercase">Possible solutions</div>
                        <p className="whitespace-pre-line">{theme.solutions}</p>
                    </div>
                )}
                <div className="flex flex-wrap items-center gap-2 border-t pt-3" data-test={`theme-friction-${theme.id}`}>
                    {theme.friction_point ? (
                        <>
                            <Link href={route('initiatives.friction', initiative.id)} className="text-teal-600 hover:underline dark:text-teal-400">
                                ✓ In friction log: {theme.friction_point.title}
                            </Link>
                            {canManage && (
                                <button
                                    type="button"
                                    className="text-muted-foreground text-xs underline"
                                    onClick={() =>
                                        router.put(
                                            route('initiatives.themes.link', [initiative.id, theme.id]),
                                            { friction_point_id: null },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Unlink
                                </button>
                            )}
                        </>
                    ) : canManage ? (
                        <>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    router.post(route('initiatives.themes.promote', [initiative.id, theme.id]), {}, { preserveScroll: true })
                                }
                                data-test={`theme-promote-${theme.id}`}
                            >
                                <Flame /> Add to friction log
                            </Button>
                            <NativeSelect
                                className="h-8 w-64 text-xs"
                                value=""
                                aria-label="Link to an existing friction point"
                                data-test={`theme-link-${theme.id}`}
                                onChange={(e) =>
                                    e.target.value &&
                                    router.put(
                                        route('initiatives.themes.link', [initiative.id, theme.id]),
                                        { friction_point_id: Number(e.target.value) },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <option value="">…or link an existing friction point</option>
                                {frictionPoints.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.title}
                                    </option>
                                ))}
                            </NativeSelect>
                        </>
                    ) : (
                        <span className="text-muted-foreground text-xs">Not linked to the friction log yet.</span>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function ThemeDialog({ initiative, allCodes, levels, theme, onClose }: Props & { theme: Theme | null; onClose: () => void }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        name: theme?.name ?? '',
        summary: theme?.summary ?? '',
        impact: theme?.impact ?? '',
        effort: theme?.effort ?? '',
        solutions: theme?.solutions ?? '',
        code_ids: theme?.codes.map((c) => c.id) ?? ([] as number[]),
    });
    // Offer codes that are free or already in this theme; codes in other themes can be moved but are listed separately.
    const free = allCodes.filter((c) => c.theme_id === null || c.theme_id === theme?.id);
    const elsewhere = allCodes.filter((c) => c.theme_id !== null && c.theme_id !== theme?.id);

    const toggle = (id: number, on: boolean) => setData('code_ids', on ? [...data.code_ids, id] : data.code_ids.filter((c) => c !== id));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (theme) {
            patch(route('initiatives.themes.update', [initiative.id, theme.id]), options);
        } else {
            post(route('initiatives.themes.store', initiative.id), options);
        }
    };

    const codeList = (codes: typeof allCodes) =>
        codes.map((c) => (
            <label key={c.id} className="flex items-center gap-2 text-sm">
                <Checkbox
                    checked={data.code_ids.includes(c.id)}
                    onCheckedChange={(on) => toggle(c.id, on === true)}
                    data-test={`theme-code-${c.id}`}
                />
                {c.name}
            </label>
        ));

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogTitle>{theme ? 'Edit theme' : 'New theme'}</DialogTitle>
                <DialogDescription>Name the underlying problem in plain words, then pick the codes that are evidence for it.</DialogDescription>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="theme-name">Name</Label>
                        <Input
                            id="theme-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            autoFocus
                            placeholder="e.g. Waiting on builds and environments"
                            data-test="theme-name"
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="theme-summary">Summary</Label>
                        <Textarea id="theme-summary" value={data.summary} onChange={(e) => setData('summary', e.target.value)} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Codes</Label>
                        <div className="grid grid-cols-2 gap-1.5">{codeList(free)}</div>
                        {elsewhere.length > 0 && (
                            <details>
                                <summary className="text-muted-foreground cursor-pointer text-xs">Move a code from another theme</summary>
                                <div className="mt-2 grid grid-cols-2 gap-1.5">{codeList(elsewhere)}</div>
                            </details>
                        )}
                        <InputError message={errors.code_ids} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="theme-impact">Impact</Label>
                            <NativeSelect id="theme-impact" value={data.impact} onChange={(e) => setData('impact', e.target.value)}>
                                <option value="">Not set</option>
                                {levels.map((l) => (
                                    <option key={l.value} value={l.value}>
                                        {l.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="theme-effort">Effort to address</Label>
                            <NativeSelect id="theme-effort" value={data.effort} onChange={(e) => setData('effort', e.target.value)}>
                                <option value="">Not set</option>
                                {levels.map((l) => (
                                    <option key={l.value} value={l.value}>
                                        {l.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="theme-solutions">Possible solutions</Label>
                        <Textarea id="theme-solutions" value={data.solutions} onChange={(e) => setData('solutions', e.target.value)} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing} data-test="theme-save">
                            {theme ? 'Save theme' : 'Create theme'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
