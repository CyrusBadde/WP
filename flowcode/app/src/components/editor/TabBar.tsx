import React, { useCallback } from 'react';
import { X, FileCode } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useProjectStore } from '@/stores';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';

export function TabBar() {
  const { tabs, activeTabId, setActiveTab, closeTab } = useProjectStore();

  const handleTabClick = useCallback(
    (tabId: string) => {
      setActiveTab(tabId);
    },
    [setActiveTab]
  );

  const handleCloseTab = useCallback(
    (e: React.MouseEvent, tabId: string) => {
      e.stopPropagation();
      closeTab(tabId);
    },
    [closeTab]
  );

  if (tabs.length === 0) {
    return null;
  }

  return (
    <div className="border-b border-border bg-background">
      <ScrollArea className="w-full">
        <div className="flex">
          {tabs.map((tab) => (
            <div
              key={tab.id}
              onClick={() => handleTabClick(tab.id)}
              className={cn(
                'group flex items-center gap-2 border-r border-border px-3 py-2 cursor-pointer min-w-[120px] max-w-[200px]',
                activeTabId === tab.id
                  ? 'bg-background border-b-2 border-b-primary'
                  : 'bg-muted/50 hover:bg-muted'
              )}
            >
              <FileCode className="h-4 w-4 shrink-0 text-muted-foreground" />
              <span className="truncate text-sm">
                {tab.isModified && <span className="text-primary mr-1">*</span>}
                {tab.name}
              </span>
              <button
                onClick={(e) => handleCloseTab(e, tab.id)}
                className="ml-auto p-0.5 rounded opacity-0 group-hover:opacity-100 hover:bg-accent"
                title="Close"
              >
                <X className="h-3 w-3" />
              </button>
            </div>
          ))}
        </div>
        <ScrollBar orientation="horizontal" />
      </ScrollArea>
    </div>
  );
}
