import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { Theme, PanelLayout, Toast } from '@/types';

interface UIState {
  // Theme
  theme: Theme;
  resolvedTheme: 'light' | 'dark';

  // Panel layout
  layout: PanelLayout;

  // Command palette
  commandPaletteOpen: boolean;

  // Toasts
  toasts: Toast[];

  // Loading states
  isLoading: boolean;
  loadingMessage: string | null;

  // Actions
  setTheme: (theme: Theme) => void;
  toggleLeftSidebar: () => void;
  toggleRightSidebar: () => void;
  toggleBottomPanel: () => void;
  setActiveLeftPanel: (panel: PanelLayout['activeLeftPanel']) => void;
  setLeftSidebarWidth: (width: number) => void;
  setRightSidebarWidth: (width: number) => void;
  setBottomPanelHeight: (height: number) => void;
  openCommandPalette: () => void;
  closeCommandPalette: () => void;
  addToast: (toast: Omit<Toast, 'id'>) => void;
  removeToast: (id: string) => void;
  setLoading: (loading: boolean, message?: string) => void;
}

const getSystemTheme = (): 'light' | 'dark' => {
  if (typeof window === 'undefined') return 'dark';
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

export const useUIStore = create<UIState>()(
  persist(
    (set, get) => ({
      // Initial state
      theme: 'dark',
      resolvedTheme: 'dark',

      layout: {
        leftSidebarOpen: true,
        rightSidebarOpen: true,
        bottomPanelOpen: false,
        leftSidebarWidth: 260,
        rightSidebarWidth: 350,
        bottomPanelHeight: 200,
        activeLeftPanel: 'files',
      },

      commandPaletteOpen: false,
      toasts: [],
      isLoading: false,
      loadingMessage: null,

      // Actions
      setTheme: (theme) => {
        const resolved = theme === 'system' ? getSystemTheme() : theme;
        set({ theme, resolvedTheme: resolved });

        // Update document class
        if (typeof document !== 'undefined') {
          document.documentElement.classList.remove('light', 'dark');
          document.documentElement.classList.add(resolved);
        }
      },

      toggleLeftSidebar: () => {
        set((state) => ({
          layout: { ...state.layout, leftSidebarOpen: !state.layout.leftSidebarOpen },
        }));
      },

      toggleRightSidebar: () => {
        set((state) => ({
          layout: { ...state.layout, rightSidebarOpen: !state.layout.rightSidebarOpen },
        }));
      },

      toggleBottomPanel: () => {
        set((state) => ({
          layout: { ...state.layout, bottomPanelOpen: !state.layout.bottomPanelOpen },
        }));
      },

      setActiveLeftPanel: (panel) => {
        set((state) => ({
          layout: { ...state.layout, activeLeftPanel: panel },
        }));
      },

      setLeftSidebarWidth: (width) => {
        set((state) => ({
          layout: { ...state.layout, leftSidebarWidth: Math.max(200, Math.min(400, width)) },
        }));
      },

      setRightSidebarWidth: (width) => {
        set((state) => ({
          layout: { ...state.layout, rightSidebarWidth: Math.max(280, Math.min(500, width)) },
        }));
      },

      setBottomPanelHeight: (height) => {
        set((state) => ({
          layout: { ...state.layout, bottomPanelHeight: Math.max(100, Math.min(400, height)) },
        }));
      },

      openCommandPalette: () => {
        set({ commandPaletteOpen: true });
      },

      closeCommandPalette: () => {
        set({ commandPaletteOpen: false });
      },

      addToast: (toast) => {
        const id = crypto.randomUUID();
        const newToast = { ...toast, id };

        set((state) => ({
          toasts: [...state.toasts, newToast],
        }));

        // Auto-remove after duration
        const duration = toast.duration ?? 5000;
        if (duration > 0) {
          setTimeout(() => {
            get().removeToast(id);
          }, duration);
        }
      },

      removeToast: (id) => {
        set((state) => ({
          toasts: state.toasts.filter((t) => t.id !== id),
        }));
      },

      setLoading: (loading, message) => {
        set({ isLoading: loading, loadingMessage: message ?? null });
      },
    }),
    {
      name: 'flowcode-ui',
      partialize: (state) => ({
        theme: state.theme,
        layout: state.layout,
      }),
    }
  )
);

// Initialize theme on load
if (typeof window !== 'undefined') {
  const store = useUIStore.getState();
  store.setTheme(store.theme);

  // Listen for system theme changes
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    const currentTheme = useUIStore.getState().theme;
    if (currentTheme === 'system') {
      useUIStore.getState().setTheme('system');
    }
  });
}
