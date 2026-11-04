/**
 * Reads the assistant's server-sent events: one JSON object per `data:` line,
 * ending with `data: [DONE]`. Text arrives as `text_delta` events.
 */
export type AssistantStreamEvent = { type: 'text_delta'; delta: string } | { type: 'error'; message: string } | { type: string };

export function parseEventBlock(block: string): AssistantStreamEvent | 'done' | null {
    const data = block
        .split('\n')
        .filter((line) => line.startsWith('data:'))
        .map((line) => line.slice(5).trimStart())
        .join('\n');

    if (data === '') return null;
    if (data === '[DONE]') return 'done';

    try {
        return JSON.parse(data) as AssistantStreamEvent;
    } catch {
        return null;
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export async function streamAssistantReply(
    endpoint: string,
    body: { message: string; activity_key: string },
    handlers: { onDelta: (text: string) => void; onError: (message: string) => void },
    signal?: AbortSignal,
): Promise<void> {
    const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'text/event-stream',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
        credentials: 'same-origin',
        signal,
    });

    if (!response.ok || !response.body) {
        let message = 'The assistant could not reply just now.';
        try {
            const json = await response.json();
            message = json.message ?? message;
        } catch {
            // Non-JSON error body; keep the generic message.
        }
        handlers.onError(message);
        return;
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    for (;;) {
        const { value, done } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });

        let boundary = buffer.indexOf('\n\n');
        while (boundary !== -1) {
            const event = parseEventBlock(buffer.slice(0, boundary));
            buffer = buffer.slice(boundary + 2);

            if (event === 'done') return;
            if (event && event.type === 'text_delta' && 'delta' in event) handlers.onDelta(event.delta);
            if (event && event.type === 'error' && 'message' in event) handlers.onError(event.message);

            boundary = buffer.indexOf('\n\n');
        }
    }
}
