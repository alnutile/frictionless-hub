import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InitiativeLayout from '@/layouts/initiative-layout';
import { type InitiativeSummary, type Option } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface Props {
    initiative: InitiativeSummary;
    theme: {
        id: number;
        name: string;
        summary: string | null;
        codes: string[];
        weight: { notes: number; interviews: number; areas: { area: string; interviews: number }[] };
    };
    interviews: {
        interview_id: number;
        participant: string;
        area: string | null;
        notes: { id: number; kind: string; body: string; codes: string[] }[];
    }[];
    kinds: Option[];
}

export default function ThemeEvidence({ initiative, theme, interviews, kinds }: Props) {
    const kindLabel = (k: string) => kinds.find((o) => o.value === k)?.label.replace(/s$/, '') ?? k;

    return (
        <InitiativeLayout initiative={initiative} title={`Theme: ${theme.name}`}>
            <Button variant="ghost" size="sm" className="w-fit" asChild>
                <Link href={route('initiatives.themes', initiative.id)}>
                    <ArrowLeft /> All themes
                </Link>
            </Button>

            <Card data-test="theme-evidence">
                <CardHeader>
                    <CardTitle>{theme.name}</CardTitle>
                    <CardDescription>
                        Evidence: {theme.weight.interviews} interview{theme.weight.interviews === 1 ? '' : 's'}, {theme.weight.notes} note
                        {theme.weight.notes === 1 ? '' : 's'}, from codes {theme.codes.join(', ') || '(none)'}.
                    </CardDescription>
                </CardHeader>
                {theme.summary && <CardContent className="text-sm whitespace-pre-line">{theme.summary}</CardContent>}
            </Card>

            {interviews.length === 0 && <p className="text-muted-foreground text-sm">No notes carry this theme's codes yet.</p>}

            <div className="grid gap-4 md:grid-cols-2">
                {interviews.map((i) => (
                    <Card key={i.interview_id}>
                        <CardHeader>
                            <CardTitle className="text-base">
                                <Link href={route('initiatives.interviews.show', [initiative.id, i.interview_id])} className="hover:underline">
                                    {i.participant}
                                </Link>
                            </CardTitle>
                            <CardDescription>{i.area ?? 'No area'}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {i.notes.map((n) => (
                                <div key={n.id} className="space-y-1 text-sm">
                                    <Badge variant="outline" className="font-normal">
                                        {kindLabel(n.kind)}
                                    </Badge>
                                    <p className={n.kind === 'quote' ? 'italic' : undefined}>{n.body}</p>
                                    <div className="text-muted-foreground text-xs">{n.codes.join(', ')}</div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </InitiativeLayout>
    );
}
