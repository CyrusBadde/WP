import { useState, useCallback, useEffect, useMemo } from 'react';
import {
  Search,
  FileCode,
  Moon,
  Sun,
  FolderPlus,
  FilePlus,
  Sparkles,
  X,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useUIStore, useProjectStore } from '@/stores';

interface CommandItem {
  id: string;
  label: string;
  description?: string;
  icon: typeof FileCode;
  category: string;
  action: () => void;
  keywords?: string[];
}

interface CommandPaletteProps {
  open: boolean;
  onClose: () => void;
}

export function CommandPalette({ open, onClose }: CommandPaletteProps) {
  const [query, setQuery] = useState('');
  const [selectedIndex, setSelectedIndex] = useState(0);

  const { setTheme, resolvedTheme, toggleLeftSidebar, toggleRightSidebar } = useUIStore();
  const { currentProject, createFile, createDirectory } = useProjectStore();

  const commands: CommandItem[] = useMemo(() => {
    const baseCommands: CommandItem[] = [
      {
        id: 'theme-toggle',
        label: resolvedTheme === 'dark' ? 'Switch to Light Theme' : 'Switch to Dark Theme',
        description: 'Toggle between light and dark mode',
        icon: resolvedTheme === 'dark' ? Sun : Moon,
        category: 'Settings',
        action: () => {
          setTheme(resolvedTheme === 'dark' ? 'light' : 'dark');
          onClose();
        },
        keywords: ['theme', 'dark', 'light', 'mode', 'color'],
      },
      {
        id: 'toggle-sidebar',
        label: 'Toggle Sidebar',
        description: 'Show or hide the file sidebar',
        icon: FolderPlus,
        category: 'View',
        action: () => {
          toggleLeftSidebar();
          onClose();
        },
        keywords: ['sidebar', 'panel', 'files', 'explorer'],
      },
      {
        id: 'toggle-ai',
        label: 'Toggle AI Panel',
        description: 'Show or hide the AI assistant',
        icon: Sparkles,
        category: 'View',
        action: () => {
          toggleRightSidebar();
          onClose();
        },
        keywords: ['ai', 'assistant', 'chat', 'panel'],
      },
    ];

    // Add project-specific commands
    if (currentProject) {
      baseCommands.push(
        {
          id: 'new-file',
          label: 'New File',
          description: 'Create a new file in the project',
          icon: FilePlus,
          category: 'File',
          action: () => {
            const name = prompt('Enter file name:');
            if (name) {
              createFile('/', name);
            }
            onClose();
          },
          keywords: ['new', 'file', 'create'],
        },
        {
          id: 'new-folder',
          label: 'New Folder',
          description: 'Create a new folder in the project',
          icon: FolderPlus,
          category: 'File',
          action: () => {
            const name = prompt('Enter folder name:');
            if (name) {
              createDirectory('/', name);
            }
            onClose();
          },
          keywords: ['new', 'folder', 'directory', 'create'],
        }
      );
    }

    return baseCommands;
  }, [
    resolvedTheme,
    currentProject,
    setTheme,
    toggleLeftSidebar,
    toggleRightSidebar,
    createFile,
    createDirectory,
    onClose,
  ]);

  const filteredCommands = useMemo(() => {
    if (!query.trim()) return commands;

    const lowerQuery = query.toLowerCase();
    return commands.filter((cmd) => {
      const matchesLabel = cmd.label.toLowerCase().includes(lowerQuery);
      const matchesDescription = cmd.description?.toLowerCase().includes(lowerQuery);
      const matchesKeywords = cmd.keywords?.some((k) => k.includes(lowerQuery));
      return matchesLabel || matchesDescription || matchesKeywords;
    });
  }, [commands, query]);

  // Group by category
  const groupedCommands = useMemo(() => {
    const groups: Record<string, CommandItem[]> = {};
    for (const cmd of filteredCommands) {
      if (!groups[cmd.category]) {
        groups[cmd.category] = [];
      }
      groups[cmd.category].push(cmd);
    }
    return groups;
  }, [filteredCommands]);

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      switch (e.key) {
        case 'ArrowDown':
          e.preventDefault();
          setSelectedIndex((i) => Math.min(i + 1, filteredCommands.length - 1));
          break;
        case 'ArrowUp':
          e.preventDefault();
          setSelectedIndex((i) => Math.max(i - 1, 0));
          break;
        case 'Enter':
          e.preventDefault();
          if (filteredCommands[selectedIndex]) {
            filteredCommands[selectedIndex].action();
          }
          break;
        case 'Escape':
          e.preventDefault();
          onClose();
          break;
      }
    },
    [filteredCommands, selectedIndex, onClose]
  );

  // Reset selected index when query changes
  useEffect(() => {
    setSelectedIndex(0);
  }, [query]);

  // Reset on open
  useEffect(() => {
    if (open) {
      setQuery('');
      setSelectedIndex(0);
    }
  }, [open]);

  // Keyboard shortcut to open
  useEffect(() => {
    const handleGlobalKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        if (!open) {
          // This would open via useUIStore.openCommandPalette()
        } else {
          onClose();
        }
      }
    };

    window.addEventListener('keydown', handleGlobalKeyDown);
    return () => window.removeEventListener('keydown', handleGlobalKeyDown);
  }, [open, onClose]);

  let flatIndex = 0;

  return (
    <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
      <DialogContent className="p-0 gap-0 max-w-lg overflow-hidden">
        <div className="flex items-center border-b border-border px-3">
          <Search className="h-4 w-4 text-muted-foreground" />
          <Input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="Search commands..."
            className="border-0 focus-visible:ring-0 px-3"
            autoFocus
          />
          <button onClick={onClose} className="p-1 rounded hover:bg-accent">
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="max-h-[300px] overflow-y-auto p-2">
          {Object.keys(groupedCommands).length === 0 ? (
            <div className="py-6 text-center text-sm text-muted-foreground">
              No commands found
            </div>
          ) : (
            Object.entries(groupedCommands).map(([category, items]) => (
              <div key={category} className="mb-2">
                <div className="px-2 py-1 text-xs font-medium text-muted-foreground">
                  {category}
                </div>
                {items.map((cmd) => {
                  const Icon = cmd.icon;
                  const currentIndex = flatIndex++;
                  const isSelected = currentIndex === selectedIndex;

                  return (
                    <button
                      key={cmd.id}
                      onClick={cmd.action}
                      className={cn(
                        'flex w-full items-center gap-3 rounded-md px-2 py-2 text-left',
                        isSelected ? 'bg-accent' : 'hover:bg-accent/50'
                      )}
                    >
                      <Icon className="h-4 w-4 text-muted-foreground" />
                      <div className="flex-1 min-w-0">
                        <div className="text-sm">{cmd.label}</div>
                        {cmd.description && (
                          <div className="text-xs text-muted-foreground truncate">
                            {cmd.description}
                          </div>
                        )}
                      </div>
                    </button>
                  );
                })}
              </div>
            ))
          )}
        </div>

        <div className="border-t border-border px-3 py-2 text-xs text-muted-foreground">
          <span className="mr-4">↑↓ Navigate</span>
          <span className="mr-4">↵ Select</span>
          <span>Esc Close</span>
        </div>
      </DialogContent>
    </Dialog>
  );
}
