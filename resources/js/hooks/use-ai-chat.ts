import { useCallback, useRef, useState } from 'react';
import type { ChatStatus, ConversationMessage } from '@/types/ai-chat';

/**
 * Manages AI chat state for a recipe, including SSE streaming, message history,
 * proposal apply/dismiss/variant actions, and retry.
 */
export function useAiChat(recipeId: number): {
    messages: ConversationMessage[];
    status: ChatStatus;
    sendMessage: (text: string) => Promise<void>;
    applyProposal: (messageId: number) => Promise<boolean>;
    applyVariant: (messageId: number) => Promise<boolean>;
    dismissProposal: (messageId: number) => void;
    retry: () => void;
    loadHistory: () => Promise<void>;
} {
    const [messages, setMessages] = useState<ConversationMessage[]>([]);
    const [status, setStatus] = useState<ChatStatus>('idle');
    const lastUserMessageRef = useRef<string>('');

    /** Read the XSRF token from the cookie Laravel sets on every response. */
    function getXsrfToken(): string {
        const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    }

    const loadHistory = useCallback(async (): Promise<void> => {
        setStatus('loading-history');

        try {
            const response = await fetch(`/recipes/${recipeId}/conversation`, {
                headers: { 'X-XSRF-TOKEN': getXsrfToken() },
            });

            if (response.ok) {
                const data = await response.json() as { messages: ConversationMessage[] };
                setMessages(data.messages ?? []);
            }
        } catch {
            // Non-fatal — empty message list is fine
        } finally {
            setStatus('idle');
        }
    }, [recipeId]);

    const sendMessage = useCallback(async (text: string): Promise<void> => {
        if (!text.trim()) {
            return;
        }

        lastUserMessageRef.current = text;

        /** Optimistically append the user message. */
        const optimisticUserMessage: ConversationMessage = {
            id: Date.now() * -1,
            role: 'user',
            content: text,
            proposal_state: null,
        };

        /** Placeholder assistant message that accumulates streaming tokens. */
        const streamingId = (Date.now() * -1) - 1;
        const streamingAssistantMessage: ConversationMessage = {
            id: streamingId,
            role: 'assistant',
            content: '',
            proposal_state: null,
        };

        setMessages((prev) => [...prev, optimisticUserMessage, streamingAssistantMessage]);
        setStatus('streaming');

        try {
            const response = await fetch(`/recipes/${recipeId}/conversation/stream`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify({ message: text }),
            });

            if (!response.ok || !response.body) {
                setStatus('error');
                return;
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();

            /** Buffer for partial SSE lines across chunks. */
            let buffer = '';
            let currentEvent = '';
            /** Whether the stream resolved cleanly via a done or error event. */
            let streamResolved = false;

            while (true) {
                const { done, value } = await reader.read();

                if (done) {
                    break;
                }

                const chunk = decoder.decode(value, { stream: true });
                buffer += chunk;

                // Split on CRLF-tolerant double-newline boundaries (\r\n\r\n or \n\n).
                // This is belt-and-suspenders: the server emits plain LF per the SSE
                // spec, but a defensive split prevents a stray \r from breaking parsing.
                const parts = buffer.split(/\r?\n\r?\n/);
                buffer = parts.pop() ?? '';

                for (const part of parts) {
                    // Split lines tolerating both LF and CRLF line endings.
                    const lines = part.split(/\r?\n/);

                    for (const rawLine of lines) {
                        // Strip a possible trailing \r before prefix checks.
                        const line = rawLine.replace(/\r$/, '');

                        if (line.startsWith('event: ')) {
                            currentEvent = line.slice(7).trim();
                        } else if (line.startsWith('data: ')) {
                            try {
                                const payload = JSON.parse(line.slice(6)) as Record<string, unknown>;

                                if (currentEvent === 'token' && typeof payload.text === 'string') {
                                    setMessages((prev) =>
                                        prev.map((m) =>
                                            m.id === streamingId
                                                ? { ...m, content: m.content + payload.text }
                                                : m,
                                        ),
                                    );
                                } else if (currentEvent === 'done') {
                                    streamResolved = true;
                                    setStatus('idle');
                                    /** Re-fetch so server-persisted proposal cards appear. */
                                    await loadHistory();
                                } else if (currentEvent === 'error') {
                                    streamResolved = true;
                                    const errorText =
                                        typeof payload.message === 'string' ? payload.message : '';
                                    setMessages((prev) =>
                                        prev.map((m) =>
                                            m.id === streamingId
                                                ? { ...m, content: errorText }
                                                : m,
                                        ),
                                    );
                                    setStatus('error');
                                    return;
                                }
                            } catch {
                                // Malformed JSON — skip line
                            }

                            currentEvent = '';
                        }
                    }
                }
            }

            /** Stream closed without a done or error event — resolve to error state. */
            if (!streamResolved) {
                setMessages((prev) =>
                    prev.map((m) => (m.id === streamingId ? { ...m, content: '' } : m)),
                );
                setStatus('error');
            }
        } catch {
            setStatus('error');
        }
    }, [recipeId, loadHistory]);

    const applyProposal = useCallback(async (messageId: number): Promise<boolean> => {
        try {
            const response = await fetch(
                `/recipes/${recipeId}/conversation/messages/${messageId}/apply`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                },
            );

            const data = await response.json() as { status: string; message?: string };

            if (data.status === 'applied') {
                setMessages((prev) =>
                    prev.map((m) =>
                        m.id === messageId && m.proposal_state
                            ? { ...m, proposal_state: { ...m.proposal_state, status: 'applied' } }
                            : m,
                    ),
                );
                return true;
            } else {
                setMessages((prev) =>
                    prev.map((m) =>
                        m.id === messageId && m.proposal_state
                            ? {
                                  ...m,
                                  proposal_state: {
                                      ...m.proposal_state,
                                      status: 'failed',
                                      failure_reason: data.message ?? 'Unknown error',
                                  },
                              }
                            : m,
                    ),
                );
                return false;
            }
        } catch {
            setMessages((prev) =>
                prev.map((m) =>
                    m.id === messageId && m.proposal_state
                        ? {
                              ...m,
                              proposal_state: {
                                  ...m.proposal_state,
                                  status: 'failed',
                                  failure_reason: 'Network error',
                              },
                          }
                        : m,
                ),
            );
            return false;
        }
    }, [recipeId]);

    const applyVariant = useCallback(async (messageId: number): Promise<boolean> => {
        try {
            const response = await fetch(
                `/recipes/${recipeId}/conversation/messages/${messageId}/variant`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                },
            );

            const data = await response.json() as {
                status: string;
                variant_recipe_id?: number;
                variant_url?: string;
            };

            if (data.status === 'applied' || data.status === 'created') {
                setMessages((prev) =>
                    prev.map((m) =>
                        m.id === messageId && m.proposal_state
                            ? {
                                  ...m,
                                  proposal_state: {
                                      ...m.proposal_state,
                                      status: 'applied',
                                      variant_recipe_id: data.variant_recipe_id,
                                      variant_url: data.variant_url,
                                  },
                              }
                            : m,
                    ),
                );
                return true;
            } else {
                setMessages((prev) =>
                    prev.map((m) =>
                        m.id === messageId && m.proposal_state
                            ? {
                                  ...m,
                                  proposal_state: {
                                      ...m.proposal_state,
                                      status: 'failed',
                                      failure_reason: 'Variant creation failed',
                                  },
                              }
                            : m,
                    ),
                );
                return false;
            }
        } catch {
            return false;
        }
    }, [recipeId]);

    const dismissProposal = useCallback((messageId: number): void => {
        /** Local-only — no server call; card stays visible as a permanent record. */
        setMessages((prev) =>
            prev.map((m) =>
                m.id === messageId && m.proposal_state
                    ? { ...m, proposal_state: { ...m.proposal_state, status: 'dismissed' } }
                    : m,
            ),
        );
    }, []);

    const retry = useCallback((): void => {
        if (lastUserMessageRef.current) {
            void sendMessage(lastUserMessageRef.current);
        }
    }, [sendMessage]);

    return { messages, status, sendMessage, applyProposal, applyVariant, dismissProposal, retry, loadHistory };
}
