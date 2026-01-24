import { useCallback, useRef, useEffect } from 'react';
import Editor, { type OnMount, type OnChange, useMonaco } from '@monaco-editor/react';
import type { editor } from 'monaco-editor';
import { useProjectStore, useUIStore } from '@/stores';
import { debounce } from '@/lib/utils';
import { auditLog } from '@/stores/auditStore';

interface MonacoEditorProps {
  className?: string;
}

export function MonacoEditor({ className }: MonacoEditorProps) {
  const editorRef = useRef<editor.IStandaloneCodeEditor | null>(null);
  const monaco = useMonaco();
  const { getActiveFile, updateFile, markTabModified, activeTabId } = useProjectStore();
  const { resolvedTheme } = useUIStore();

  const activeFile = getActiveFile();

  // Debounced save function
  const debouncedSave = useCallback(
    debounce((path: string, content: string) => {
      updateFile(path, content);
      if (activeTabId) {
        markTabModified(activeTabId, false);
      }
      auditLog.fileChange('auto_save', path, { size: content.length });
    }, 1000),
    [updateFile, markTabModified, activeTabId]
  );

  const handleEditorMount: OnMount = useCallback((editor) => {
    editorRef.current = editor;

    // Add keyboard shortcuts using Monaco's KeyMod and KeyCode
    if (monaco) {
      editor.addCommand(
        monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS,
        () => {
          const model = editor.getModel();
          const file = getActiveFile();
          if (model && file) {
            updateFile(file.path, model.getValue());
            if (activeTabId) {
              markTabModified(activeTabId, false);
            }
            auditLog.fileChange('manual_save', file.path);
          }
        }
      );
    }
  }, [monaco, activeTabId, updateFile, markTabModified, getActiveFile]);

  const handleChange: OnChange = useCallback(
    (value) => {
      if (value !== undefined && activeFile) {
        // Mark as modified
        if (activeTabId) {
          markTabModified(activeTabId, true);
        }
        // Debounced auto-save
        debouncedSave(activeFile.path, value);
      }
    },
    [activeFile, activeTabId, markTabModified, debouncedSave]
  );

  // Update editor value when switching files
  useEffect(() => {
    if (editorRef.current && activeFile) {
      const model = editorRef.current.getModel();
      if (model && model.getValue() !== activeFile.content) {
        model.setValue(activeFile.content || '');
      }
    }
  }, [activeFile?.id, activeFile?.content]);

  if (!activeFile) {
    return (
      <div className="flex h-full items-center justify-center text-muted-foreground">
        <div className="text-center">
          <p className="text-lg">No file open</p>
          <p className="text-sm">Select a file from the sidebar to edit</p>
        </div>
      </div>
    );
  }

  return (
    <div className={className}>
      <Editor
        height="100%"
        language={activeFile.language || 'plaintext'}
        value={activeFile.content || ''}
        theme={resolvedTheme === 'dark' ? 'vs-dark' : 'light'}
        onMount={handleEditorMount}
        onChange={handleChange}
        options={{
          fontSize: 14,
          fontFamily: "'JetBrains Mono', 'Fira Code', monospace",
          minimap: { enabled: true },
          wordWrap: 'on',
          lineNumbers: 'on',
          tabSize: 2,
          insertSpaces: true,
          automaticLayout: true,
          scrollBeyondLastLine: false,
          renderWhitespace: 'selection',
          bracketPairColorization: { enabled: true },
          padding: { top: 8, bottom: 8 },
          smoothScrolling: true,
          cursorBlinking: 'smooth',
          cursorSmoothCaretAnimation: 'on',
        }}
        loading={
          <div className="flex h-full items-center justify-center">
            <div className="animate-spin h-8 w-8 border-2 border-primary border-t-transparent rounded-full" />
          </div>
        }
      />
    </div>
  );
}
