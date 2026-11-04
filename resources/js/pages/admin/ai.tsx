import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Props {
    maskedKey: string | null;
    model: string;
    defaultModel: string;
    systemPrompt: string;
    defaultPrompt: string;
    promptCustomized: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'AI assistant', href: '/admin/ai' }];

export default function AiSettings({ maskedKey, model, defaultModel, systemPrompt, defaultPrompt, promptCustomized }: Props) {
    const { flash, errors: pageErrors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const { data, setData, put, processing, errors } = useForm({
        api_key: '',
        remove_key: false as boolean,
        model,
        system_prompt: systemPrompt,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.ai.update'), { preserveScroll: true, onSuccess: () => setData('api_key', '') });
    };

    const test = () => router.post(route('admin.ai.test'), {}, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI assistant" />
            <div className="flex max-w-3xl flex-col gap-6 p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">AI assistant</h1>
                    <p className="text-muted-foreground text-sm">
                        The assistant appears in the Guide panel on every screen. It's given the screen's guide text, the initiative's data and the
                        person's notes as context. Requests go through OpenRouter, and the key stays on the server.
                    </p>
                </div>

                {flash?.success && (
                    <div className="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                        {flash.success}
                    </div>
                )}
                <InputError message={pageErrors.test} />

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Provider</CardTitle>
                            <CardDescription>
                                Create a key at openrouter.ai. Any model on OpenRouter works; <code>{defaultModel}</code> lets OpenRouter pick one.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="api_key">OpenRouter API key</Label>
                                <Input
                                    id="api_key"
                                    type="password"
                                    autoComplete="off"
                                    value={data.api_key}
                                    onChange={(e) => setData('api_key', e.target.value)}
                                    placeholder={maskedKey ? `Saved (${maskedKey}). Leave blank to keep it` : 'sk-or-…'}
                                    data-test="ai-key"
                                />
                                <InputError message={errors.api_key ?? pageErrors.api_key} />
                                {maskedKey && (
                                    <label className="text-muted-foreground flex items-center gap-2 text-sm">
                                        <Checkbox checked={data.remove_key} onCheckedChange={(c) => setData('remove_key', c === true)} /> Remove the
                                        saved key (turns the assistant off)
                                    </label>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="model">Model</Label>
                                <Input
                                    id="model"
                                    value={data.model}
                                    onChange={(e) => setData('model', e.target.value)}
                                    placeholder={defaultModel}
                                    data-test="ai-model"
                                />
                                <p className="text-muted-foreground text-xs">
                                    An OpenRouter model id, e.g. <code>anthropic/…</code>, <code>openai/…</code> or <code>google/…</code>.
                                </p>
                                <InputError message={errors.model} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>System prompt</CardTitle>
                            <CardDescription>
                                How the assistant behaves. The context for the current screen is added after this prompt on every message.
                                {promptCustomized ? ' This prompt has been edited.' : ' This is the default prompt.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <Textarea
                                value={data.system_prompt}
                                onChange={(e) => setData('system_prompt', e.target.value)}
                                className="min-h-96 font-mono text-xs"
                                data-test="ai-prompt"
                            />
                            <InputError message={errors.system_prompt} />
                            <div className="flex justify-end">
                                <Button type="button" variant="ghost" size="sm" onClick={() => setData('system_prompt', defaultPrompt)}>
                                    Restore default prompt
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex items-center justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={test}
                            disabled={!maskedKey}
                            title={maskedKey ? undefined : 'Save a key first'}
                        >
                            Test connection
                        </Button>
                        <Button type="submit" disabled={processing} data-test="ai-save">
                            Save settings
                        </Button>
                    </div>
                </form>

                <HeadingSmall title="Coming next" description="Letting the assistant add data and write summaries, with your confirmation." />
            </div>
        </AppLayout>
    );
}
