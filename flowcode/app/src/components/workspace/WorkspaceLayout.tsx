import { useCallback } from 'react';
import {
  PanelRightClose,
  PanelRightOpen,
  Files,
  Blocks,
  GitBranch,
  Database,
  Plug,
  Moon,
  Sun,
  Command,
  ChevronLeft,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger, TooltipProvider } from '@/components/ui/tooltip';
import { useUIStore, useProjectStore } from '@/stores';
import { FileTree } from '@/components/sidebar/FileTree';
import { EditorPane } from '@/components/editor/EditorPane';
import { ChatPanel } from '@/components/ai/ChatPanel';
import { CommandPalette } from './CommandPalette';
import type { PanelId } from '@/types';

const sidebarPanels: Array<{ id: PanelId; icon: typeof Files; label: string }> = [
  { id: 'files', icon: Files, label: 'Files' },
  { id: 'components', icon: Blocks, label: 'Components' },
  { id: 'git', icon: GitBranch, label: 'Git' },
  { id: 'database', icon: Database, label: 'Database' },
  { id: 'integrations', icon: Plug, label: 'Integrations' },
];

function LeftPanelContent({ panelId }: { panelId: PanelId }) {
  switch (panelId) {
    case 'files':
      return <FileTree />;
    case 'components':
      return <ComponentPalette />;
    case 'git':
      return <GitPanel />;
    case 'database':
      return <DatabasePanel />;
    case 'integrations':
      return <IntegrationsPanel />;
    default:
      return <FileTree />;
  }
}

// Placeholder components for other panels
function ComponentPalette() {
  return (
    <div className="p-4 text-sm text-muted-foreground">
      <p className="font-medium mb-2">Component Library</p>
      <p>Drag and drop components to insert into your code.</p>
      <div className="mt-4 space-y-2">
        {['Button', 'Card', 'Input', 'Form', 'Modal', 'Table'].map((name) => (
          <div
            key={name}
            className="p-2 rounded border border-border hover:border-primary cursor-grab"
            draggable
          >
            {name}
          </div>
        ))}
      </div>
    </div>
  );
}

function GitPanel() {
  return (
    <div className="p-4 text-sm text-muted-foreground">
      <p className="font-medium mb-2">Git (Simulated)</p>
      <p>Git integration is simulated in demo mode.</p>
      <div className="mt-4 space-y-2">
        <div className="p-2 rounded bg-muted">
          <span className="text-green-500">●</span> main
        </div>
      </div>
    </div>
  );
}

function DatabasePanel() {
  return (
    <div className="p-4 text-sm text-muted-foreground">
      <p className="font-medium mb-2">Database Schema</p>
      <p>Design your database schema visually.</p>
      <p className="mt-2 text-xs">Coming in Phase 2</p>
    </div>
  );
}

function IntegrationsPanel() {
  return (
    <div className="p-4 text-sm text-muted-foreground">
      <p className="font-medium mb-2">Integrations</p>
      <p>Connect to external services.</p>
      <div className="mt-4 space-y-2">
        {['GitHub', 'GitLab', 'Vercel', 'Netlify', 'Supabase'].map((name) => (
          <div key={name} className="flex items-center gap-2 p-2 rounded border border-border">
            <Plug className="h-4 w-4" />
            <span>{name}</span>
            <span className="ml-auto text-xs text-yellow-500">Demo</span>
          </div>
        ))}
      </div>
    </div>
  );
}

export function WorkspaceLayout() {
  const {
    layout,
    toggleLeftSidebar,
    toggleRightSidebar,
    setActiveLeftPanel,
    resolvedTheme,
    setTheme,
    openCommandPalette,
    commandPaletteOpen,
    closeCommandPalette,
  } = useUIStore();

  const { currentProject, closeProject } = useProjectStore();

  const handleThemeToggle = useCallback(() => {
    setTheme(resolvedTheme === 'dark' ? 'light' : 'dark');
  }, [resolvedTheme, setTheme]);

  const handlePanelClick = useCallback(
    (panelId: PanelId) => {
      if (layout.activeLeftPanel === panelId && layout.leftSidebarOpen) {
        toggleLeftSidebar();
      } else {
        setActiveLeftPanel(panelId);
        if (!layout.leftSidebarOpen) {
          toggleLeftSidebar();
        }
      }
    },
    [layout.activeLeftPanel, layout.leftSidebarOpen, setActiveLeftPanel, toggleLeftSidebar]
  );

  return (
    <TooltipProvider delayDuration={300}>
      <div className="flex h-screen w-screen flex-col overflow-hidden bg-background">
        {/* Top Bar */}
        <header className="flex h-12 items-center justify-between border-b border-border px-3">
          <div className="flex items-center gap-2">
            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={closeProject}>
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <span className="font-medium">{currentProject?.name || 'FlowCode'}</span>
          </div>

          <div className="flex items-center gap-1">
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="ghost"
                  size="sm"
                  className="gap-2"
                  onClick={openCommandPalette}
                >
                  <Command className="h-4 w-4" />
                  <span className="text-xs text-muted-foreground">⌘K</span>
                </Button>
              </TooltipTrigger>
              <TooltipContent>Command Palette</TooltipContent>
            </Tooltip>

            <Tooltip>
              <TooltipTrigger asChild>
                <Button variant="ghost" size="icon" className="h-8 w-8" onClick={handleThemeToggle}>
                  {resolvedTheme === 'dark' ? (
                    <Sun className="h-4 w-4" />
                  ) : (
                    <Moon className="h-4 w-4" />
                  )}
                </Button>
              </TooltipTrigger>
              <TooltipContent>Toggle theme</TooltipContent>
            </Tooltip>
          </div>
        </header>

        {/* Main Content */}
        <div className="flex flex-1 overflow-hidden">
          {/* Activity Bar */}
          <aside className="flex w-12 flex-col items-center gap-1 border-r border-border bg-muted/30 py-2">
            {sidebarPanels.map((panel) => {
              const Icon = panel.icon;
              const isActive = layout.activeLeftPanel === panel.id && layout.leftSidebarOpen;

              return (
                <Tooltip key={panel.id}>
                  <TooltipTrigger asChild>
                    <Button
                      variant="ghost"
                      size="icon"
                      className={cn('h-10 w-10', isActive && 'bg-accent')}
                      onClick={() => handlePanelClick(panel.id)}
                    >
                      <Icon className="h-5 w-5" />
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent side="right">{panel.label}</TooltipContent>
                </Tooltip>
              );
            })}
          </aside>

          {/* Left Sidebar */}
          {layout.leftSidebarOpen && (
            <aside
              className="flex flex-col border-r border-border bg-background"
              style={{ width: layout.leftSidebarWidth }}
            >
              <LeftPanelContent panelId={layout.activeLeftPanel} />
            </aside>
          )}

          {/* Editor Area */}
          <main className="flex flex-1 flex-col overflow-hidden">
            <EditorPane />
          </main>

          {/* Right Sidebar (AI Chat) */}
          {layout.rightSidebarOpen && (
            <aside
              className="flex flex-col border-l border-border bg-background"
              style={{ width: layout.rightSidebarWidth }}
            >
              <ChatPanel />
            </aside>
          )}

          {/* Right Sidebar Toggle */}
          <div className="flex flex-col items-center justify-center border-l border-border bg-muted/30 px-1">
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8"
                  onClick={toggleRightSidebar}
                >
                  {layout.rightSidebarOpen ? (
                    <PanelRightClose className="h-4 w-4" />
                  ) : (
                    <PanelRightOpen className="h-4 w-4" />
                  )}
                </Button>
              </TooltipTrigger>
              <TooltipContent side="left">Toggle AI Panel</TooltipContent>
            </Tooltip>
          </div>
        </div>

        {/* Command Palette */}
        <CommandPalette open={commandPaletteOpen} onClose={closeCommandPalette} />
      </div>
    </TooltipProvider>
  );
}
