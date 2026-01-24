import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { ChatMessage, AIAction, AgentType, StreamChunk } from '@/types';
import { generateId } from '@/lib/utils';

interface AIState {
  // Chat
  messages: ChatMessage[];
  isStreaming: boolean;
  streamingMessageId: string | null;
  currentAgent: AgentType;

  // Pending actions
  pendingActions: AIAction[];

  // API Key (NOT persisted - memory only)
  apiKey: string | null;
  hasApiKey: boolean;

  // Rate limiting
  requestCount: number;
  lastRequestTime: number;

  // Actions
  setApiKey: (key: string | null) => void;
  clearApiKey: () => void;
  sendMessage: (content: string) => void;
  appendStreamChunk: (chunk: StreamChunk) => void;
  finishStreaming: () => void;
  cancelStreaming: () => void;
  setCurrentAgent: (agent: AgentType) => void;
  addAction: (action: Omit<AIAction, 'id' | 'timestamp'>) => void;
  applyAction: (actionId: string) => void;
  rejectAction: (actionId: string) => void;
  clearActions: () => void;
  clearChat: () => void;
  canMakeRequest: () => boolean;
  recordRequest: () => void;
}

const MAX_REQUESTS_PER_MINUTE = 20;
const REQUEST_WINDOW_MS = 60000;

export const useAIStore = create<AIState>()(
  persist(
    (set, get) => ({
      messages: [],
      isStreaming: false,
      streamingMessageId: null,
      currentAgent: 'orchestrator',
      pendingActions: [],
      apiKey: null,
      hasApiKey: false,
      requestCount: 0,
      lastRequestTime: 0,

      setApiKey: (key) => {
        set({ apiKey: key, hasApiKey: !!key });
      },

      clearApiKey: () => {
        set({ apiKey: null, hasApiKey: false });
      },

      sendMessage: (content) => {
        const userMessage: ChatMessage = {
          id: generateId(),
          role: 'user',
          content,
          timestamp: Date.now(),
        };

        const assistantMessageId = generateId();
        const assistantMessage: ChatMessage = {
          id: assistantMessageId,
          role: 'assistant',
          content: '',
          timestamp: Date.now(),
          agent: get().currentAgent,
          metadata: {
            actions: [],
            filesReferenced: [],
          },
        };

        set((state) => ({
          messages: [...state.messages, userMessage, assistantMessage],
          isStreaming: true,
          streamingMessageId: assistantMessageId,
        }));
      },

      appendStreamChunk: (chunk) => {
        set((state) => {
          if (!state.streamingMessageId) return state;

          const messages = state.messages.map((msg) => {
            if (msg.id !== state.streamingMessageId) return msg;

            let content = msg.content;
            const metadata = { ...msg.metadata };

            switch (chunk.type) {
              case 'text':
                content += chunk.content || '';
                break;
              case 'action':
                if (chunk.action) {
                  const action: AIAction = {
                    id: generateId(),
                    type: chunk.action.type || 'suggest',
                    status: 'pending',
                    payload: chunk.action.payload || {},
                    timestamp: Date.now(),
                  };
                  metadata.actions = [...(metadata.actions || []), action];
                }
                break;
              case 'progress':
                // Progress updates don't modify content
                break;
              case 'error':
                content += `\n\nError: ${chunk.error}`;
                break;
            }

            return { ...msg, content, metadata };
          });

          return { messages };
        });
      },

      finishStreaming: () => {
        set((state) => {
          // Extract pending actions from the completed message
          const streamingMessage = state.messages.find(
            (m) => m.id === state.streamingMessageId
          );
          const newActions = streamingMessage?.metadata?.actions || [];

          return {
            isStreaming: false,
            streamingMessageId: null,
            pendingActions: [...state.pendingActions, ...newActions],
          };
        });
      },

      cancelStreaming: () => {
        set((state) => {
          // Mark the streaming message as cancelled
          const messages = state.messages.map((msg) => {
            if (msg.id === state.streamingMessageId) {
              return {
                ...msg,
                content: msg.content + '\n\n*[Cancelled]*',
              };
            }
            return msg;
          });

          return {
            messages,
            isStreaming: false,
            streamingMessageId: null,
          };
        });
      },

      setCurrentAgent: (agent) => {
        set({ currentAgent: agent });
      },

      addAction: (action) => {
        const fullAction: AIAction = {
          ...action,
          id: generateId(),
          timestamp: Date.now(),
        };
        set((state) => ({
          pendingActions: [...state.pendingActions, fullAction],
        }));
      },

      applyAction: (actionId) => {
        set((state) => ({
          pendingActions: state.pendingActions.map((a) =>
            a.id === actionId ? { ...a, status: 'applied' } : a
          ),
        }));
      },

      rejectAction: (actionId) => {
        set((state) => ({
          pendingActions: state.pendingActions.map((a) =>
            a.id === actionId ? { ...a, status: 'rejected' } : a
          ),
        }));
      },

      clearActions: () => {
        set({ pendingActions: [] });
      },

      clearChat: () => {
        set({ messages: [], pendingActions: [] });
      },

      canMakeRequest: () => {
        const state = get();
        const now = Date.now();

        // Reset count if window has passed
        if (now - state.lastRequestTime > REQUEST_WINDOW_MS) {
          return true;
        }

        return state.requestCount < MAX_REQUESTS_PER_MINUTE;
      },

      recordRequest: () => {
        const now = Date.now();
        set((state) => {
          // Reset count if window has passed
          if (now - state.lastRequestTime > REQUEST_WINDOW_MS) {
            return { requestCount: 1, lastRequestTime: now };
          }
          return { requestCount: state.requestCount + 1, lastRequestTime: now };
        });
      },
    }),
    {
      name: 'flowcode-ai',
      partialize: (state) => ({
        // Only persist chat history, NOT the API key
        messages: state.messages.slice(-100), // Keep last 100 messages
        currentAgent: state.currentAgent,
      }),
    }
  )
);

// Clear API key on page unload (security measure)
if (typeof window !== 'undefined') {
  window.addEventListener('beforeunload', () => {
    useAIStore.getState().clearApiKey();
  });
}
