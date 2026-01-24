import React, { useState, useCallback } from 'react';
import {
  ChevronRight,
  ChevronDown,
  File,
  Folder,
  FolderOpen,
  Plus,
  Trash2,
  Edit2,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { useProjectStore } from '@/stores';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { FileNode } from '@/types';

interface FileTreeItemProps {
  node: FileNode;
  level: number;
  onSelect: (node: FileNode) => void;
  selectedPath: string | null;
}

function FileTreeItem({ node, level, onSelect, selectedPath }: FileTreeItemProps) {
  const [isExpanded, setIsExpanded] = useState(level === 0);
  const [isRenaming, setIsRenaming] = useState(false);
  const [newName, setNewName] = useState(node.name);

  const { getChildren, renameNode, deleteNode, openTab } = useProjectStore();
  const children = node.type === 'directory' ? getChildren(node.path) : [];
  const isSelected = selectedPath === node.path;
  const isRoot = level === 0;

  const handleClick = useCallback(() => {
    if (node.type === 'directory') {
      setIsExpanded(!isExpanded);
    } else {
      openTab(node.id);
    }
    onSelect(node);
  }, [node, isExpanded, openTab, onSelect]);

  const handleRename = useCallback(() => {
    if (newName && newName !== node.name) {
      renameNode(node.path, newName);
    }
    setIsRenaming(false);
  }, [newName, node.name, node.path, renameNode]);

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key === 'Enter') {
        handleRename();
      } else if (e.key === 'Escape') {
        setNewName(node.name);
        setIsRenaming(false);
      }
    },
    [handleRename, node.name]
  );

  const handleDelete = useCallback(
    (e: React.MouseEvent) => {
      e.stopPropagation();
      if (confirm(`Delete ${node.name}?`)) {
        deleteNode(node.path);
      }
    },
    [node.name, node.path, deleteNode]
  );

  const handleStartRename = useCallback((e: React.MouseEvent) => {
    e.stopPropagation();
    setIsRenaming(true);
  }, []);

  return (
    <div>
      <div
        className={cn(
          'group flex items-center gap-1 px-2 py-1 cursor-pointer rounded-sm hover:bg-accent',
          isSelected && 'bg-accent text-accent-foreground'
        )}
        style={{ paddingLeft: `${level * 12 + 4}px` }}
        onClick={handleClick}
      >
        {node.type === 'directory' ? (
          <>
            {isExpanded ? (
              <ChevronDown className="h-4 w-4 shrink-0 text-muted-foreground" />
            ) : (
              <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground" />
            )}
            {isExpanded ? (
              <FolderOpen className="h-4 w-4 shrink-0 text-yellow-500" />
            ) : (
              <Folder className="h-4 w-4 shrink-0 text-yellow-500" />
            )}
          </>
        ) : (
          <>
            <span className="w-4" />
            <File className="h-4 w-4 shrink-0 text-blue-400" />
          </>
        )}

        {isRenaming ? (
          <Input
            value={newName}
            onChange={(e) => setNewName(e.target.value)}
            onBlur={handleRename}
            onKeyDown={handleKeyDown}
            className="h-5 px-1 text-sm"
            autoFocus
            onClick={(e) => e.stopPropagation()}
          />
        ) : (
          <span className="truncate text-sm">{isRoot ? 'Project Root' : node.name}</span>
        )}

        {!isRoot && !isRenaming && (
          <div className="ml-auto hidden gap-1 group-hover:flex">
            <button
              onClick={handleStartRename}
              className="p-0.5 rounded hover:bg-muted"
              title="Rename"
            >
              <Edit2 className="h-3 w-3" />
            </button>
            <button
              onClick={handleDelete}
              className="p-0.5 rounded hover:bg-destructive hover:text-destructive-foreground"
              title="Delete"
            >
              <Trash2 className="h-3 w-3" />
            </button>
          </div>
        )}
      </div>

      {node.type === 'directory' && isExpanded && (
        <div>
          {children.map((child) => (
            <FileTreeItem
              key={child.id}
              node={child}
              level={level + 1}
              onSelect={onSelect}
              selectedPath={selectedPath}
            />
          ))}
        </div>
      )}
    </div>
  );
}

interface NewItemInputProps {
  parentPath: string;
  type: 'file' | 'directory';
  onComplete: () => void;
}

function NewItemInput({ parentPath, type, onComplete }: NewItemInputProps) {
  const [name, setName] = useState('');
  const { createFile, createDirectory } = useProjectStore();

  const handleSubmit = useCallback(() => {
    if (name.trim()) {
      if (type === 'file') {
        createFile(parentPath, name.trim());
      } else {
        createDirectory(parentPath, name.trim());
      }
    }
    onComplete();
  }, [name, type, parentPath, createFile, createDirectory, onComplete]);

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key === 'Enter') {
        handleSubmit();
      } else if (e.key === 'Escape') {
        onComplete();
      }
    },
    [handleSubmit, onComplete]
  );

  return (
    <div className="flex items-center gap-1 px-2 py-1">
      {type === 'directory' ? (
        <Folder className="h-4 w-4 text-yellow-500" />
      ) : (
        <File className="h-4 w-4 text-blue-400" />
      )}
      <Input
        value={name}
        onChange={(e) => setName(e.target.value)}
        onBlur={handleSubmit}
        onKeyDown={handleKeyDown}
        placeholder={type === 'file' ? 'filename.tsx' : 'folder name'}
        className="h-6 px-1 text-sm"
        autoFocus
      />
    </div>
  );
}

export function FileTree() {
  const { currentProject, files } = useProjectStore();
  const [selectedPath, setSelectedPath] = useState<string | null>(null);
  const [newItemType, setNewItemType] = useState<'file' | 'directory' | null>(null);

  if (!currentProject) {
    return (
      <div className="p-4 text-sm text-muted-foreground">
        No project open. Create or open a project to see files.
      </div>
    );
  }

  const rootNode = files.get(currentProject.rootId);
  if (!rootNode) {
    return (
      <div className="p-4 text-sm text-muted-foreground">
        Error loading project files.
      </div>
    );
  }

  const handleSelect = useCallback((node: FileNode) => {
    setSelectedPath(node.path);
  }, []);

  const parentPath = selectedPath || '/';

  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center justify-between border-b border-border px-3 py-2">
        <span className="text-xs font-medium uppercase text-muted-foreground">
          Files
        </span>
        <div className="flex gap-1">
          <Button
            variant="ghost"
            size="icon"
            className="h-6 w-6"
            onClick={() => setNewItemType('file')}
            title="New File"
          >
            <Plus className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            className="h-6 w-6"
            onClick={() => setNewItemType('directory')}
            title="New Folder"
          >
            <Folder className="h-4 w-4" />
          </Button>
        </div>
      </div>

      <ScrollArea className="flex-1">
        <div className="py-2">
          {newItemType && (
            <NewItemInput
              parentPath={parentPath}
              type={newItemType}
              onComplete={() => setNewItemType(null)}
            />
          )}
          <FileTreeItem
            node={rootNode}
            level={0}
            onSelect={handleSelect}
            selectedPath={selectedPath}
          />
        </div>
      </ScrollArea>
    </div>
  );
}
