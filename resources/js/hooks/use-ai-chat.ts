import { useCallback, useEffect, useRef, useState } from 'react';
import type {
    ChatStatus,
    ConversationMessage,
    ConversationResponse,
} from '@/types/ai-chat';

/** Interval between conversation polls while a turn is generating. */
const POLL_INTERVAL_MS = 1800;

/**
 * Manages AI chat state for a recipe.
 *
 * The agentic turn runs in a background job; this hook posts the user message
 * then polls the conversation endpoint until the turn finishes. Each poll
 * replaces the whole message list, so `tool_proposal` cards and the final
 * assistant reply appear as the job persists them. A turn already in progress
 * on mount (e.g. after a page refresh) is resumed automatically.
 */
export function useAiChat(recipeId: number): {
    messages: ConversationMessage[];
    status: ChatStatus;
    error: string | null;
    sendMessage: (text: string) => Promise<void>;
    applyProposal: (messageId: number) => Promise<boolean>;
    applyVariant: (messageId: number) => Promise<boolean>;
    dismissProposal: (messageId: number) => void;
    retry: () => void;
    loadHistory: () => Promise<void>;
} {
    const [messages, setMessages] = useState<ConversationMessage[]>([]);
    const [status, setStatus] = useState<ChatStatus>('idle');
    const [error, setError] = useState<string | null>(null);
    const lastUserMessageRef = useRef<string>('');
    const pollTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const isMountedRef = useRef(true);

    /** Stop polling and flag unmount so in-flight polls bail out. */
    useEffect(() => {
        isMountedRef.current = true;

        return () => {
            isMountedRef.current = false;

            if (pollTimerRef.current !== null) {
                clearTimeout(pollTimerRef.current);
                pollTimerRef.current = null;
            }
        };
    }, []);

    /** Read the XSRF token from the cookie Laravel sets on every response. */
    function getXsrfToken(): string {
        const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

        return match ? decodeURIComponent(match[1]) : '';
    }

    /** Fetch the conversation state, or null on a transient network/HTTP failure. */
    const fetchConversation =
        useCallback(async (): Promise<ConversationResponse | null> => {
            try {
                const response = await fetch(
                    `/recipes/${recipeId}/conversation`,
                    { headers: { 'X-XSRF-TOKEN': getXsrfToken() } },
                );

                if (!response.ok) {
                    return null;
                }

                return (await response.json()) as ConversationResponse;
            } catch {
                return null;
            }
        }, [recipeId]);

    const stopPolling = useCallback((): void => {
        if (pollTimerRef.current !== null) {
            clearTimeout(pollTimerRef.current);
            pollTimerRef.current = null;
        }
    }, []);

    /**
     * Poll the conversation once and reconcile UI state. While the turn is
     * still generating it reschedules itself; it stops on idle (done) or
     * failed (chat error). Declared as a named function expression so it can
     * reschedule itself without a circular hook dependency.
     */
    const poll = useCallback(
        async function poll(): Promise<void> {
            const data = await fetchConversation();

            if (!isMountedRef.current) {
                return;
            }

            if (data === null) {
                // Transient failure — keep trying; the job is still running server-side.
                pollTimerRef.current = setTimeout(
                    () => void poll(),
                    POLL_INTERVAL_MS,
                );

                return;
            }

            setMessages(data.messages ?? []);

            if (data.agent_status === 'generating') {
                setStatus('generating');
                pollTimerRef.current = setTimeout(
                    () => void poll(),
                    POLL_INTERVAL_MS,
                );

                return;
            }

            if (data.agent_status === 'failed') {
                setError(data.agent_error ?? null);
                setStatus('error');

                return;
            }

            setStatus('idle');
        },
        [fetchConversation],
    );

    /** Stop any current polling and start a fresh poll cycle. */
    const beginPolling = useCallback((): void => {
        stopPolling();
        pollTimerRef.current = setTimeout(() => void poll(), POLL_INTERVAL_MS);
    }, [poll, stopPolling]);

    const loadHistory = useCallback(async (): Promise<void> => {
        setStatus('loading-history');

        const data = await fetchConversation();

        if (!isMountedRef.current) {
            return;
        }

        if (data !== null) {
            setMessages(data.messages ?? []);

            if (data.agent_status === 'generating') {
                // A turn is already running (e.g. the page was refreshed
                // mid-turn) — resume polling so it completes in this session.
                setError(null);
                setStatus('generating');
                beginPolling();

                return;
            }

            if (data.agent_status === 'failed') {
                setError(data.agent_error ?? null);
                setStatus('error');

                return;
            }
        }

        setStatus('idle');
    }, [fetchConversation, beginPolling]);

    const sendMessage = useCallback(
        async (text: string): Promise<void> => {
            if (!text.trim()) {
                return;
            }

            lastUserMessageRef.current = text;
            setError(null);

            /** Optimistically append the user message (negative id, replaced on first poll). */
            const optimisticUserMessage: ConversationMessage = {
                id: Date.now() * -1,
                role: 'user',
                content: text,
                proposal_state: null,
            };

            setMessages((prev) => [...prev, optimisticUserMessage]);
            setStatus('generating');

            try {
                const response = await fetch(
                    `/recipes/${recipeId}/conversation`,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-XSRF-TOKEN': getXsrfToken(),
                        },
                        body: JSON.stringify({ message: text }),
                    },
                );

                if (response.status === 409) {
                    // A turn is already in progress — follow it instead of erroring.
                    beginPolling();

                    return;
                }

                if (!response.ok) {
                    setStatus('error');

                    return;
                }

                beginPolling();
            } catch {
                setStatus('error');
            }
        },
        [recipeId, beginPolling],
    );

    const applyProposal = useCallback(
        async (messageId: number): Promise<boolean> => {
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

                const data = (await response.json()) as {
                    status: string;
                    message?: string;
                };

                if (data.status === 'applied') {
                    setMessages((prev) =>
                        prev.map((m) =>
                            m.id === messageId && m.proposal_state
                                ? {
                                      ...m,
                                      proposal_state: {
                                          ...m.proposal_state,
                                          status: 'applied',
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
                                          failure_reason:
                                              data.message ?? 'Unknown error',
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
        },
        [recipeId],
    );

    const applyVariant = useCallback(
        async (messageId: number): Promise<boolean> => {
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

                const data = (await response.json()) as {
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
                                          variant_recipe_id:
                                              data.variant_recipe_id,
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
                                          failure_reason:
                                              'Variant creation failed',
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
        },
        [recipeId],
    );

    const dismissProposal = useCallback((messageId: number): void => {
        /** Local-only — no server call; card stays visible as a permanent record. */
        setMessages((prev) =>
            prev.map((m) =>
                m.id === messageId && m.proposal_state
                    ? {
                          ...m,
                          proposal_state: {
                              ...m.proposal_state,
                              status: 'dismissed',
                          },
                      }
                    : m,
            ),
        );
    }, []);

    const retry = useCallback((): void => {
        if (lastUserMessageRef.current) {
            void sendMessage(lastUserMessageRef.current);
        }
    }, [sendMessage]);

    return {
        messages,
        status,
        error,
        sendMessage,
        applyProposal,
        applyVariant,
        dismissProposal,
        retry,
        loadHistory,
    };
}
