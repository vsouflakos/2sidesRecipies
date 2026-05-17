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

/** Current status of the AI chat. */
export type ChatStatus = 'idle' | 'loading-history' | 'streaming' | 'error';
