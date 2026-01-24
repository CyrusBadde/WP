import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { AuditEntry } from '@/types';
import { generateId } from '@/lib/utils';

interface AuditState {
  entries: AuditEntry[];
  maxEntries: number;

  // Actions
  log: (entry: Omit<AuditEntry, 'id' | 'timestamp'>) => void;
  query: (filter: {
    type?: AuditEntry['type'];
    startTime?: number;
    endTime?: number;
    limit?: number;
  }) => AuditEntry[];
  clear: () => void;
  exportLogs: () => string;
}

const MAX_AUDIT_ENTRIES = 1000;

export const useAuditStore = create<AuditState>()(
  persist(
    (set, get) => ({
      entries: [],
      maxEntries: MAX_AUDIT_ENTRIES,

      log: (entry) => {
        const fullEntry: AuditEntry = {
          ...entry,
          id: generateId(),
          timestamp: Date.now(),
        };

        set((state) => {
          const entries = [...state.entries, fullEntry];
          // Trim if exceeds max
          if (entries.length > state.maxEntries) {
            return { entries: entries.slice(-state.maxEntries) };
          }
          return { entries };
        });

        // Also log to console in development
        if (import.meta.env.DEV) {
          console.log('[Audit]', fullEntry.type, fullEntry.action, fullEntry.details);
        }
      },

      query: ({ type, startTime, endTime, limit = 100 }) => {
        let results = get().entries;

        if (type) {
          results = results.filter((e) => e.type === type);
        }

        if (startTime) {
          results = results.filter((e) => e.timestamp >= startTime);
        }

        if (endTime) {
          results = results.filter((e) => e.timestamp <= endTime);
        }

        // Sort by timestamp descending and limit
        return results
          .sort((a, b) => b.timestamp - a.timestamp)
          .slice(0, limit);
      },

      clear: () => {
        // Log the clear action before clearing
        const clearEntry: AuditEntry = {
          id: generateId(),
          timestamp: Date.now(),
          type: 'security',
          action: 'audit_log_cleared',
          details: { previousCount: get().entries.length },
        };

        set({ entries: [clearEntry] });
      },

      exportLogs: () => {
        const entries = get().entries;
        return JSON.stringify(entries, null, 2);
      },
    }),
    {
      name: 'flowcode-audit',
      partialize: (state) => ({
        entries: state.entries.slice(-MAX_AUDIT_ENTRIES),
      }),
    }
  )
);

// Utility function to log common actions
export const auditLog = {
  aiAction: (action: string, details: Record<string, unknown>) => {
    useAuditStore.getState().log({
      type: 'ai_action',
      action,
      details,
    });
  },

  fileChange: (action: string, filePath: string, details?: Record<string, unknown>) => {
    useAuditStore.getState().log({
      type: 'file_change',
      action,
      details: { filePath, ...details },
    });
  },

  integration: (action: string, integration: string, details?: Record<string, unknown>) => {
    useAuditStore.getState().log({
      type: 'integration',
      action,
      details: { integration, ...details },
    });
  },

  workflow: (action: string, workflowId: string, details?: Record<string, unknown>) => {
    useAuditStore.getState().log({
      type: 'workflow',
      action,
      details: { workflowId, ...details },
    });
  },

  error: (action: string, error: Error | string, details?: Record<string, unknown>) => {
    useAuditStore.getState().log({
      type: 'error',
      action,
      details: {
        error: error instanceof Error ? error.message : error,
        stack: error instanceof Error ? error.stack : undefined,
        ...details,
      },
    });
  },

  security: (action: string, details: Record<string, unknown>) => {
    useAuditStore.getState().log({
      type: 'security',
      action,
      details,
    });
  },
};
