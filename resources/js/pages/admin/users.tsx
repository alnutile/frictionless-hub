import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Option, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Copy, RefreshCw, Trash2, UserPlus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string;
    created_at: string;
}

interface InvitationRow {
    id: number;
    email: string;
    role: string;
    invited_by: string | null;
    expires_at: string;
    status: 'pending' | 'expired' | 'accepted';
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Users & roles', href: '/admin/users' }];

export default function AdminUsers({ users, roles, invitations }: { users: UserRow[]; roles: Option[]; invitations: InvitationRow[] }) {
    const { auth, flash, errors } = usePage<SharedData & { errors: Record<string, string> }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users & roles" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">Users & roles</h1>
                    <p className="text-muted-foreground text-sm">
                        Admins manage everything. Facilitators create and run their own initiatives. Viewers have read-only access. People join by
                        invitation only.
                    </p>
                </div>

                {flash?.success && (
                    <div className="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                        {flash.success}
                    </div>
                )}
                <InputError message={errors.role} />

                <InvitePanel roles={roles} invitations={invitations} link={flash?.invite_link ?? null} />

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">Email</th>
                                <th className="px-3 py-2 font-medium">Joined</th>
                                <th className="px-3 py-2 font-medium">Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr key={user.id} className="border-t">
                                    <td className="px-3 py-2 font-medium">{user.name}</td>
                                    <td className="px-3 py-2">{user.email}</td>
                                    <td className="px-3 py-2">{user.created_at}</td>
                                    <td className="px-3 py-2">
                                        <NativeSelect
                                            className="w-40"
                                            value={user.role}
                                            disabled={user.id === auth.user.id}
                                            onChange={(e) =>
                                                router.patch(route('admin.users.update', user.id), { role: e.target.value }, { preserveScroll: true })
                                            }
                                            aria-label={`Role for ${user.name}`}
                                        >
                                            {roles.map((role) => (
                                                <option key={role.value} value={role.value}>
                                                    {role.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

function InvitePanel({ roles, invitations, link }: { roles: Option[]; invitations: InvitationRow[]; link: { email: string; url: string } | null }) {
    const [copied, setCopied] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ email: '', role: 'viewer' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        setCopied(false);
        post(route('admin.invitations.store'), { preserveScroll: true, onSuccess: () => reset('email') });
    };

    const copy = async (url: string) => {
        await navigator.clipboard.writeText(url);
        setCopied(true);
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <UserPlus className="size-5" /> Invite someone
                </CardTitle>
                <CardDescription>
                    Creates a single-use sign-up link that's valid for 7 days. Send it to them yourself. For security, the link is shown only once;
                    you can make a new one at any time.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
                    <div className="grid gap-1.5">
                        <Label htmlFor="invite-email">Email</Label>
                        <Input
                            id="invite-email"
                            type="email"
                            className="w-72"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            data-test="invite-email"
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="invite-role">Role</Label>
                        <NativeSelect
                            id="invite-role"
                            className="w-40"
                            value={data.role}
                            onChange={(e) => setData('role', e.target.value)}
                            data-test="invite-role"
                        >
                            {roles.map((r) => (
                                <option key={r.value} value={r.value}>
                                    {r.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <Button type="submit" disabled={processing} data-test="invite-create">
                        Create invite link
                    </Button>
                </form>
                <InputError message={errors.email} />

                {link && (
                    <div
                        className="space-y-2 rounded-md border border-teal-300 bg-teal-50 p-3 text-sm dark:border-teal-900 dark:bg-teal-950"
                        data-test="invite-link"
                    >
                        <div>
                            Invite link for <span className="font-medium">{link.email}</span>. Copy it now; it won't be shown again.
                        </div>
                        <div className="flex gap-2">
                            <Input
                                readOnly
                                value={link.url}
                                className="font-mono text-xs"
                                onFocus={(e) => e.currentTarget.select()}
                                data-test="invite-url"
                            />
                            <Button type="button" variant="outline" onClick={() => copy(link.url)}>
                                <Copy /> {copied ? 'Copied' : 'Copy'}
                            </Button>
                        </div>
                    </div>
                )}

                {invitations.length > 0 && (
                    <div className="overflow-x-auto">
                        <div className="mb-1 text-sm font-medium">Open invitations</div>
                        <table className="w-full text-sm">
                            <tbody>
                                {invitations.map((i) => (
                                    <tr key={i.id} className="border-t">
                                        <td className="py-2 pr-3">{i.email}</td>
                                        <td className="px-3 py-2">{i.role}</td>
                                        <td className="text-muted-foreground px-3 py-2">{i.invited_by && `by ${i.invited_by}`}</td>
                                        <td className="px-3 py-2">
                                            {i.status === 'expired' ? (
                                                <Badge variant="outline" className="border-amber-400 text-amber-700 dark:text-amber-300">
                                                    Expired {i.expires_at}
                                                </Badge>
                                            ) : (
                                                <span className="text-muted-foreground">Expires {i.expires_at}</span>
                                            )}
                                        </td>
                                        <td className="py-2 text-right whitespace-nowrap">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => {
                                                    setCopied(false);
                                                    router.post(route('admin.invitations.regenerate', i.id), {}, { preserveScroll: true });
                                                }}
                                            >
                                                <RefreshCw /> New link
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="text-destructive"
                                                onClick={() =>
                                                    window.confirm(`Revoke the invitation for ${i.email}?`) &&
                                                    router.delete(route('admin.invitations.destroy', i.id), { preserveScroll: true })
                                                }
                                            >
                                                <Trash2 /> Revoke
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
