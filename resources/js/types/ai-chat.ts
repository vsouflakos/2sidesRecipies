/** State of a proposal within a conversation message. */
export interface ProposalState {
    kind: 'edit' | 'variant';
    action?: string;
    data?: unknown;
    changes?: unknown;
    status: 'pending' | 'applied' | 'dismissed' | 'failed';
    failure_reason?: string;
    variant_recipe_id?: number;
    variant_url?: string;
}

/** A single message in the AI conversation. */
export interface ConversationMessage {
    id: number;
    role: 'user' | 'assistant' | 'tool_proposal';
    content: string;
    proposal_state: ProposalState | null;
}

/** Server-side lifecycle of a queued agent turn. */
export type AgentStatus = 'idle' | 'generating' | 'failed';

/**
 * Current status of the AI chat.
 * 'generating' means a turn is in progress (queued job running) — the client
 * polls until the server reports the turn idle or failed.
 */
export type ChatStatus = 'idle' | 'loading-history' | 'generating' | 'error';

/** Shape of the GET conversation endpoint response. */
export interface ConversationResponse {
    messages: ConversationMessage[];
    agent_status: AgentStatus;
    agent_error: string | null;
}
