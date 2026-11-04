import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface InitiativeRow {
    id: number;
    name: string;
    scope: string | null;
    phase: string;
    owner: string;
    stages_count: number;
    friction_points_count: number;
    updated_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Initiatives', href: '/initiatives' }];

export default function InitiativesIndex({ initiatives, canCreate }: { initiatives: InitiativeRow[]; canCreate: boolean }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Initiatives" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">Initiatives</h1>
                        <p className="text-muted-foreground text-sm">Each initiative is one DevEx program for an org, business unit, or team.</p>
                    </div>
                    {canCreate && <CreateInitiative />}
                </div>

                {initiatives.length === 0 ? (
                    <div className="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm">
                        No initiatives yet.{canCreate ? ' Create one to start mapping your developer workflow.' : ''}
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {initiatives.map((initiative) => (
                            <Link key={initiative.id} href={route('initiatives.show', initiative.id)} prefetch>
                                <Card className="hover:border-primary/50 h-full transition-colors">
                                    <CardHeader>
                                        <CardTitle>{initiative.name}</CardTitle>
                                        <CardDescription>
                                            {initiative.scope ?? 'No scope'} · {initiative.phase}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="text-muted-foreground flex gap-4 text-sm">
                                        <span>{initiative.stages_count} stages</span>
                                        <span>{initiative.friction_points_count} friction points</span>
                                        <span className="ml-auto">{initiative.owner}</span>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function CreateInitiative() {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', scope: '', description: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('initiatives.store'), { onSuccess: () => reset() });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus /> New initiative
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New initiative</DialogTitle>
                <DialogDescription>
                    Default workflow stages and DevEx dimensions are created for you, and you can change them later.
                </DialogDescription>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="scope">Scope</Label>
                        <Input id="scope" value={data.scope} onChange={(e) => setData('scope', e.target.value)} placeholder="e.g. Payments org" />
                        <InputError message={errors.scope} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        <InputError message={errors.description} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            Create
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
