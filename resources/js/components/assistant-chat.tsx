import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { streamAssistantReply } from '@/lib/assistant-stream';
import { cn } from '@/lib/utils';
import { type GuidePayload } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Send, Sparkles, Trash2 } from 'lucide-react';
import { FormEvent, KeyboardEvent, useEffect, useRef, useState } from 'react';

const SUGGESTIONS = ['What should I do on this screen?', "Summarize what we've found so far", 'What stands out as most important?'];

/**
 * Chat with the assistant about the current screen. The server adds the guide text and the
 * initiative's data as context; replies stream in, then are saved and re-rendered as markdown.
 */
export function AssistantChat({ guide }: { guide: GuidePayload }) {
    const { assistant } = guide;
    const [input, setInput] = useState('');
    const [pending, setPending] = useState<{ question: string; reply: string } | null>(null);
    const [error, setError] = useState<string | null>(null);
    const endRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        endRef.current?.scrollIntoView({ block: 'end' });
    }, [assistant.messages.length, pending?.reply]);

    if (!assistant.configured) {
        return (
            <section className="space-y-2 border-t pt-4" data-test="assistant">
                <h3 className="flex items-center gap-1.5 text-sm font-medium">
                    <Sparkles className="size-4 text-teal-500" /> Assistant
                </h3>
                <p className="text-muted-foreground text-sm">
                    {assistant.canConfigure ? (
                        <>
                            Not set up yet.{' '}
                            <Link href={route('admin.ai.edit')} className="text-teal-600 underline dark:text-teal-400">
                                Add an OpenRouter key
                            </Link>{' '}
                            to turn it on.
                        </>
                    ) : (
                        'An admin has not set up the assistant yet.'
                    )}
                </p>
            </section>
        );
    }

    const send = async (text: string) => {
        const message = text.trim();
        if (!message || pending) return;

        setInput('');
        setError(null);
        setPending({ question: message, reply: '' });

        await streamAssistantReply(
            assistant.endpoint,
            { message, activity_key: guide.key },
            {
                onDelta: (delta) => setPending((p) => (p ? { ...p, reply: p.reply + delta } : p)),
                onError: (msg) => setError(msg),
            },
        ).catch(() => setError('The assistant could not reply just now.'));

        router.reload({ only: ['guide'], onFinish: () => setPending(null) });
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        send(input);
    };

    const onKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            send(input);
        }
    };

    const clear = () => {
        if (window.confirm('Clear your conversation for this initiative?')) {
            router.delete(assistant.clearEndpoint, { preserveScroll: true, only: ['guide'] });
        }
    };

    const isEmpty = assistant.messages.length === 0 && !pending;

    return (
        <section className="space-y-3 border-t pt-4" data-test="assistant">
            <div className="flex items-center justify-between">
                <h3 className="flex items-center gap-1.5 text-sm font-medium">
                    <Sparkles className="size-4 text-teal-500" /> Ask the assistant
                </h3>
                {assistant.messages.length > 0 && !pending && (
                    <Button variant="ghost" size="icon" className="size-7" onClick={clear} aria-label="Clear conversation">
                        <Trash2 className="size-3.5" />
                    </Button>
                )}
            </div>

            {isEmpty && (
                <div className="space-y-2">
                    <p className="text-muted-foreground text-xs">
                        It knows this screen, its guide, and this initiative's data. Your conversation is private to you.
                    </p>
                    <div className="flex flex-wrap gap-1.5">
                        {SUGGESTIONS.map((s) => (
                            <button key={s} type="button" onClick={() => send(s)} className="hover:bg-accent rounded-full border px-2.5 py-1 text-xs">
                                {s}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            <div className="space-y-3" data-test="assistant-messages">
                {assistant.messages.map((m) => (
                    <Bubble key={m.id} role={m.role}>
                        {m.html ? <div className="guide-prose" dangerouslySetInnerHTML={{ __html: m.html }} /> : m.content}
                    </Bubble>
                ))}
                {pending && (
                    <>
                        <Bubble role="user">{pending.question}</Bubble>
                        <Bubble role="assistant">
                            <span className="whitespace-pre-wrap" data-test="assistant-reply">
                                {pending.reply || <span className="text-muted-foreground animate-pulse">Thinking…</span>}
                            </span>
                        </Bubble>
                    </>
                )}
                {error && (
                    <p className="text-destructive text-sm" role="alert">
                        {error}
                    </p>
                )}
                <div ref={endRef} />
            </div>

            <form onSubmit={submit} className="bg-background sticky bottom-0 flex items-end gap-1.5 pt-1 pb-2">
                <Textarea
                    value={input}
                    onChange={(e) => setInput(e.target.value)}
                    onKeyDown={onKeyDown}
                    placeholder="Ask about this screen… (Enter to send)"
                    className="min-h-10 resize-none text-sm"
                    rows={2}
                    disabled={!!pending}
                    data-test="assistant-input"
                />
                <Button type="submit" size="icon" disabled={!!pending || !input.trim()} aria-label="Send" data-test="assistant-send">
                    <Send />
                </Button>
            </form>
        </section>
    );
}

function Bubble({ role, children }: { role: 'user' | 'assistant'; children: React.ReactNode }) {
    return (
        <div className={cn('rounded-lg px-3 py-2 text-sm', role === 'user' ? 'bg-primary text-primary-foreground ml-8' : 'bg-muted mr-4')}>
            {children}
        </div>
    );
}
