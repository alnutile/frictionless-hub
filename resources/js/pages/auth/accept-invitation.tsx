import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface Props {
    token: string;
    invitation: { email: string; role: string } | null;
}

export default function AcceptInvitation({ token, invitation }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        password: '',
        password_confirmation: '',
    });

    if (!invitation) {
        return (
            <AuthLayout title="This invitation can't be used" description="The link is invalid, has expired, or was already used.">
                <Head title="Invitation" />
                <div className="space-y-4 text-center text-sm" data-test="invitation-invalid">
                    <p className="text-muted-foreground">Ask the admin who invited you for a new link.</p>
                    <Link href={route('login')} className="underline">
                        Go to log in
                    </Link>
                </div>
            </AuthLayout>
        );
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('invitations.accept', token), { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <AuthLayout
            title="Join Frictionless Hub"
            description={`You've been invited as a ${invitation.role.toLowerCase()}. Set up your account to continue.`}
        >
            <Head title="Accept invitation" />
            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input id="email" type="email" value={invitation.email} disabled />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Your name</Label>
                        <Input id="name" required autoFocus autoComplete="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <Input
                            id="password"
                            type="password"
                            required
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <InputError message={errors.password} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">Confirm password</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                        />
                    </div>
                    <InputError message={(errors as Record<string, string>).token} />
                    <Button type="submit" className="w-full" disabled={processing} data-test="accept-invitation">
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Create account
                    </Button>
                </div>
            </form>
        </AuthLayout>
    );
}
