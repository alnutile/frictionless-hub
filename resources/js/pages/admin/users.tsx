import InputError from '@/components/input-error';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Option, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Users & roles', href: '/admin/users' }];

export default function AdminUsers({ users, roles }: { users: UserRow[]; roles: Option[] }) {
    const { auth, flash, errors } = usePage<SharedData & { errors: Record<string, string> }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users & roles" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">Users & roles</h1>
                    <p className="text-muted-foreground text-sm">
                        Admins manage everything. Facilitators create and run their own initiatives. Viewers have read-only access. New sign-ups start
                        as viewers.
                    </p>
                </div>

                {flash?.success && (
                    <div className="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{flash.success}</div>
                )}
                <InputError message={errors.role} />

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
