import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import InitiativeLayout from '@/layouts/initiative-layout';
import { activityHref } from '@/lib/walkthrough';
import { type InitiativeSummary, type WalkthroughActivity } from '@/types';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

interface Props {
    initiative: InitiativeSummary;
    activity: WalkthroughActivity;
    html: string;
    previous: WalkthroughActivity | null;
    next: WalkthroughActivity | null;
    builtBefore: WalkthroughActivity[];
}

export default function Planned({ initiative, activity, html, previous, next, builtBefore }: Props) {
    return (
        <InitiativeLayout initiative={initiative} title={activity.title}>
            <Card className="max-w-3xl" data-test="planned-activity">
                <CardHeader>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">Planned</Badge>
                        <span className="text-muted-foreground text-sm">
                            Step {activity.step} · {activity.phase_label}
                        </span>
                    </div>
                    <CardTitle className="text-xl">{activity.title}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="guide-prose text-muted-foreground" dangerouslySetInnerHTML={{ __html: html }} />

                    {builtBefore.length > 0 && (
                        <div>
                            <div className="mb-2 text-sm font-medium">You can work on these steps now</div>
                            <div className="flex flex-wrap gap-2">
                                {builtBefore.map((a) => (
                                    <Button key={a.key} variant="outline" size="sm" asChild>
                                        <Link href={activityHref(a, initiative.id)} prefetch>
                                            {a.step}. {a.title}
                                        </Link>
                                    </Button>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="flex justify-between gap-2 border-t pt-4">
                        {previous ? (
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={activityHref(previous, initiative.id)}>
                                    <ChevronLeft /> {previous.step}. {previous.title}
                                </Link>
                            </Button>
                        ) : (
                            <span />
                        )}
                        {next && (
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={activityHref(next, initiative.id)}>
                                    {next.step}. {next.title} <ChevronRight />
                                </Link>
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>
        </InitiativeLayout>
    );
}
