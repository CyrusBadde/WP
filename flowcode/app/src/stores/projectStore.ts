import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { Project, FileNode, ProjectTemplate, ProjectSettings, EditorTab } from '@/types';
import { generateId, getMonacoLanguage, getParentPath, joinPath } from '@/lib/utils';

interface ProjectState {
  // Projects
  projects: Project[];
  currentProject: Project | null;

  // File system
  files: Map<string, FileNode>;
  filesByPath: Map<string, string>; // path -> id

  // Editor state
  tabs: EditorTab[];
  activeTabId: string | null;

  // Actions - Projects
  createProject: (name: string, template: ProjectTemplate, description?: string) => Project;
  openProject: (projectId: string) => void;
  closeProject: () => void;
  deleteProject: (projectId: string) => void;
  updateProjectSettings: (settings: Partial<ProjectSettings>) => void;

  // Actions - Files
  createFile: (parentPath: string, name: string, content?: string) => FileNode;
  createDirectory: (parentPath: string, name: string) => FileNode;
  readFile: (path: string) => string | null;
  updateFile: (path: string, content: string) => void;
  deleteNode: (path: string) => void;
  renameNode: (path: string, newName: string) => void;
  moveNode: (fromPath: string, toPath: string) => void;

  // Actions - Tabs
  openTab: (fileId: string) => void;
  closeTab: (tabId: string) => void;
  closeAllTabs: () => void;
  setActiveTab: (tabId: string) => void;
  markTabModified: (tabId: string, modified: boolean) => void;

  // Helpers
  getNode: (path: string) => FileNode | null;
  getNodeById: (id: string) => FileNode | null;
  getChildren: (path: string) => FileNode[];
  getActiveFile: () => FileNode | null;
}

const defaultProjectSettings: ProjectSettings = {
  theme: 'dark',
  fontSize: 14,
  tabSize: 2,
  wordWrap: true,
  minimap: true,
  keybindings: 'default',
};

// Template files for each project type
const getTemplateFiles = (template: ProjectTemplate): Omit<FileNode, 'id' | 'parentId'>[] => {
  const now = Date.now();

  const baseFiles: Omit<FileNode, 'id' | 'parentId'>[] = [
    {
      name: 'src',
      path: '/src',
      type: 'directory',
      children: [],
      metadata: { createdAt: now, modifiedAt: now, size: 0 },
    },
  ];

  switch (template) {
    case 'react':
      return [
        ...baseFiles,
        {
          name: 'package.json',
          path: '/package.json',
          type: 'file',
          language: 'json',
          content: JSON.stringify({
            name: 'my-react-app',
            version: '0.1.0',
            private: true,
            dependencies: {
              react: '^18.2.0',
              'react-dom': '^18.2.0',
            },
          }, null, 2),
          metadata: { createdAt: now, modifiedAt: now, size: 0 },
        },
        {
          name: 'App.tsx',
          path: '/src/App.tsx',
          type: 'file',
          language: 'typescript',
          content: `import { useState } from 'react'

function App() {
  const [count, setCount] = useState(0)

  return (
    <div className="app">
      <h1>Welcome to FlowCode</h1>
      <div className="card">
        <button onClick={() => setCount(c => c + 1)}>
          Count is {count}
        </button>
      </div>
    </div>
  )
}

export default App
`,
          metadata: { createdAt: now, modifiedAt: now, size: 0 },
        },
        {
          name: 'main.tsx',
          path: '/src/main.tsx',
          type: 'file',
          language: 'typescript',
          content: `import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App'
import './index.css'

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
)
`,
          metadata: { createdAt: now, modifiedAt: now, size: 0 },
        },
        {
          name: 'index.css',
          path: '/src/index.css',
          type: 'file',
          language: 'css',
          content: `:root {
  font-family: system-ui, sans-serif;
  line-height: 1.5;
}

.app {
  max-width: 1280px;
  margin: 0 auto;
  padding: 2rem;
  text-align: center;
}

.card {
  padding: 2em;
}

button {
  border-radius: 8px;
  border: 1px solid transparent;
  padding: 0.6em 1.2em;
  font-size: 1em;
  font-weight: 500;
  background-color: #1a1a1a;
  color: white;
  cursor: pointer;
  transition: border-color 0.25s;
}

button:hover {
  border-color: #646cff;
}
`,
          metadata: { createdAt: now, modifiedAt: now, size: 0 },
        },
      ];

    case 'nextjs':
      return [
        ...baseFiles,
        {
          name: 'app',
          path: '/app',
          type: 'directory',
          children: [],
          metadata: { createdAt: now, modifiedAt: now, size: 0 },
        },
        {
          name: 'package.json',
          path: '/package.json',
          type: 'file',
          language: 'json',
          content: JSON.stringify({
            name: 'my-nextjs-app',
            version: '0.1.0',
            private: true,
            dependencies: {
              next: '^14.0.0',
              react: '^18.2.0',
              'react-dom': '^18.2.0',
            },
          }, null, 2),
          metadata: { createdAt: now, modifiedAt: now, size: 0 },
        },
        {
          name: 'page.tsx',
          path: '/app/page.tsx',
          type: 'file',
          language: 'typescript',
          content: `export default function Home() {
  return (
    <main className="flex min-h-screen flex-col items-center justify-center p-24">
      <h1 className="text-4xl font-bold">Welcome to Next.js</h1>
      <p className="mt-4 text-lg text-gray-600">
        Get started by editing app/page.tsx
      </p>
    </main>
  )
}
`,
          metadata: { createdAt: now, modifiedAt: now, size: 0 },
        },
        {
          name: 'layout.tsx',
          path: '/app/layout.tsx',
          type: 'file',
          language: 'typescript',
          content: `import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'My Next.js App',
  description: 'Created with FlowCode',
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  )
}
`,
          metadata: { createdAt: now, modifiedAt: now, size: 0 },
        },
      ];

    case 'api':
      return [
        ...baseFiles,
        {
          name: 'package.json',
          path: '/package.json',
          type: 'file',
          language: 'json',
          content: JSON.stringify({
            name: 'my-api',
            version: '0.1.0',
            private: true,
            type: 'module',
            dependencies: {
              express: '^4.18.0',
            },
          }, null, 2),
          metadata: { createdAt: now, modifiedAt: now, size: 0 },
        },
        {
          name: 'index.ts',
          path: '/src/index.ts',
          type: 'file',
          language: 'typescript',
          content: `import express from 'express'

const app = express()
const port = process.env.PORT || 3000

app.use(express.json())

app.get('/', (req, res) => {
  res.json({ message: 'Welcome to your API!' })
})

app.get('/health', (req, res) => {
  res.json({ status: 'ok', timestamp: new Date().toISOString() })
})

app.listen(port, () => {
  console.log(\`Server running on port \${port}\`)
})
`,
          metadata: { createdAt: now, modifiedAt: now, size: 0 },
        },
      ];

    case 'blank':
    default:
      return [
        ...baseFiles,
        {
          name: 'README.md',
          path: '/README.md',
          type: 'file',
          language: 'markdown',
          content: `# My Project

Welcome to your new FlowCode project!

## Getting Started

Start adding files to the \`src\` directory.
`,
          metadata: { createdAt: now, modifiedAt: now, size: 0 },
        },
      ];
  }
};

export const useProjectStore = create<ProjectState>()(
  persist(
    (set, get) => ({
      projects: [],
      currentProject: null,
      files: new Map(),
      filesByPath: new Map(),
      tabs: [],
      activeTabId: null,

      // Project actions
      createProject: (name, template, description = '') => {
        const now = Date.now();
        const projectId = generateId();
        const rootId = generateId();

        // Create root directory
        const rootNode: FileNode = {
          id: rootId,
          name: name,
          path: '/',
          type: 'directory',
          children: [],
          parentId: null,
          metadata: { createdAt: now, modifiedAt: now, size: 0 },
        };

        // Create project
        const project: Project = {
          id: projectId,
          name,
          description,
          template,
          rootId,
          settings: { ...defaultProjectSettings },
          createdAt: now,
          modifiedAt: now,
        };

        // Initialize file maps
        const files = new Map<string, FileNode>();
        const filesByPath = new Map<string, string>();

        files.set(rootId, rootNode);
        filesByPath.set('/', rootId);

        // Create template files
        const templateFiles = getTemplateFiles(template);
        const directoryNodes: Map<string, FileNode> = new Map();
        directoryNodes.set('/', rootNode);

        // First pass: create all directories
        for (const fileData of templateFiles) {
          if (fileData.type === 'directory') {
            const id = generateId();
            const parentPath = getParentPath(fileData.path);
            const parentId = filesByPath.get(parentPath) || rootId;

            const node: FileNode = {
              ...fileData,
              id,
              parentId,
              children: [],
            };

            files.set(id, node);
            filesByPath.set(fileData.path, id);
            directoryNodes.set(fileData.path, node);

            // Add to parent's children
            const parent = files.get(parentId);
            if (parent && parent.children) {
              parent.children.push(id);
            }
          }
        }

        // Second pass: create all files
        for (const fileData of templateFiles) {
          if (fileData.type === 'file') {
            const id = generateId();
            const parentPath = getParentPath(fileData.path);
            const parentId = filesByPath.get(parentPath) || rootId;

            const node: FileNode = {
              ...fileData,
              id,
              parentId,
              metadata: {
                ...fileData.metadata,
                size: fileData.content?.length || 0,
              },
            };

            files.set(id, node);
            filesByPath.set(fileData.path, id);

            // Add to parent's children
            const parent = files.get(parentId);
            if (parent && parent.children) {
              parent.children.push(id);
            }
          }
        }

        set((state) => ({
          projects: [...state.projects, project],
          currentProject: project,
          files,
          filesByPath,
          tabs: [],
          activeTabId: null,
        }));

        return project;
      },

      openProject: (projectId) => {
        const state = get();
        const project = state.projects.find((p) => p.id === projectId);
        if (project) {
          set({ currentProject: project, tabs: [], activeTabId: null });
        }
      },

      closeProject: () => {
        set({ currentProject: null, tabs: [], activeTabId: null });
      },

      deleteProject: (projectId) => {
        set((state) => ({
          projects: state.projects.filter((p) => p.id !== projectId),
          currentProject: state.currentProject?.id === projectId ? null : state.currentProject,
        }));
      },

      updateProjectSettings: (settings) => {
        set((state) => {
          if (!state.currentProject) return state;
          const updated = {
            ...state.currentProject,
            settings: { ...state.currentProject.settings, ...settings },
            modifiedAt: Date.now(),
          };
          return {
            currentProject: updated,
            projects: state.projects.map((p) => (p.id === updated.id ? updated : p)),
          };
        });
      },

      // File actions
      createFile: (parentPath, name, content = '') => {
        const state = get();
        const parentId = state.filesByPath.get(parentPath);
        if (!parentId) {
          throw new Error(`Parent directory not found: ${parentPath}`);
        }

        const id = generateId();
        const path = joinPath(parentPath, name);
        const now = Date.now();
        const language = getMonacoLanguage(name);

        const node: FileNode = {
          id,
          name,
          path,
          type: 'file',
          content,
          language,
          parentId,
          metadata: {
            createdAt: now,
            modifiedAt: now,
            size: content.length,
          },
        };

        const newFiles = new Map(state.files);
        const newFilesByPath = new Map(state.filesByPath);

        newFiles.set(id, node);
        newFilesByPath.set(path, id);

        // Update parent's children
        const parent = newFiles.get(parentId);
        if (parent && parent.children) {
          const updatedParent = {
            ...parent,
            children: [...parent.children, id],
          };
          newFiles.set(parentId, updatedParent);
        }

        set({ files: newFiles, filesByPath: newFilesByPath });
        return node;
      },

      createDirectory: (parentPath, name) => {
        const state = get();
        const parentId = state.filesByPath.get(parentPath);
        if (!parentId) {
          throw new Error(`Parent directory not found: ${parentPath}`);
        }

        const id = generateId();
        const path = joinPath(parentPath, name);
        const now = Date.now();

        const node: FileNode = {
          id,
          name,
          path,
          type: 'directory',
          children: [],
          parentId,
          metadata: {
            createdAt: now,
            modifiedAt: now,
            size: 0,
          },
        };

        const newFiles = new Map(state.files);
        const newFilesByPath = new Map(state.filesByPath);

        newFiles.set(id, node);
        newFilesByPath.set(path, id);

        // Update parent's children
        const parent = newFiles.get(parentId);
        if (parent && parent.children) {
          const updatedParent = {
            ...parent,
            children: [...parent.children, id],
          };
          newFiles.set(parentId, updatedParent);
        }

        set({ files: newFiles, filesByPath: newFilesByPath });
        return node;
      },

      readFile: (path) => {
        const state = get();
        const id = state.filesByPath.get(path);
        if (!id) return null;
        const node = state.files.get(id);
        return node?.content ?? null;
      },

      updateFile: (path, content) => {
        const state = get();
        const id = state.filesByPath.get(path);
        if (!id) return;

        const node = state.files.get(id);
        if (!node || node.type !== 'file') return;

        const newFiles = new Map(state.files);
        newFiles.set(id, {
          ...node,
          content,
          metadata: {
            ...node.metadata,
            modifiedAt: Date.now(),
            size: content.length,
          },
        });

        set({ files: newFiles });
      },

      deleteNode: (path) => {
        const state = get();
        const id = state.filesByPath.get(path);
        if (!id) return;

        const node = state.files.get(id);
        if (!node) return;

        const newFiles = new Map(state.files);
        const newFilesByPath = new Map(state.filesByPath);

        // Recursively delete all children
        const deleteRecursive = (nodeId: string) => {
          const n = newFiles.get(nodeId);
          if (!n) return;

          if (n.type === 'directory' && n.children) {
            for (const childId of n.children) {
              deleteRecursive(childId);
            }
          }

          newFiles.delete(nodeId);
          newFilesByPath.delete(n.path);
        };

        deleteRecursive(id);

        // Remove from parent's children
        if (node.parentId) {
          const parent = newFiles.get(node.parentId);
          if (parent && parent.children) {
            const updatedParent = {
              ...parent,
              children: parent.children.filter((cid) => cid !== id),
            };
            newFiles.set(node.parentId, updatedParent);
          }
        }

        // Close any tabs for deleted files
        const deletedPaths = new Set<string>();
        deletedPaths.add(path);
        // Add child paths
        state.files.forEach((f) => {
          if (f.path.startsWith(path + '/')) {
            deletedPaths.add(f.path);
          }
        });

        const newTabs = state.tabs.filter((t) => !deletedPaths.has(t.path));
        const newActiveTabId = deletedPaths.has(state.tabs.find((t) => t.id === state.activeTabId)?.path || '')
          ? newTabs[0]?.id || null
          : state.activeTabId;

        set({ files: newFiles, filesByPath: newFilesByPath, tabs: newTabs, activeTabId: newActiveTabId });
      },

      renameNode: (path, newName) => {
        const state = get();
        const id = state.filesByPath.get(path);
        if (!id) return;

        const node = state.files.get(id);
        if (!node) return;

        const parentPath = getParentPath(path);
        const newPath = joinPath(parentPath, newName);

        const newFiles = new Map(state.files);
        const newFilesByPath = new Map(state.filesByPath);

        // Update path for this node and all descendants
        const updatePaths = (nodeId: string, oldBase: string, newBase: string) => {
          const n = newFiles.get(nodeId);
          if (!n) return;

          const updatedPath = n.path.replace(oldBase, newBase);
          newFilesByPath.delete(n.path);
          newFilesByPath.set(updatedPath, nodeId);

          const updatedNode = {
            ...n,
            name: nodeId === id ? newName : n.name,
            path: updatedPath,
            language: n.type === 'file' ? getMonacoLanguage(updatedPath) : undefined,
          };
          newFiles.set(nodeId, updatedNode);

          if (n.type === 'directory' && n.children) {
            for (const childId of n.children) {
              updatePaths(childId, oldBase, newBase);
            }
          }
        };

        updatePaths(id, path, newPath);

        // Update tabs
        const newTabs = state.tabs.map((t) => {
          if (t.path === path || t.path.startsWith(path + '/')) {
            return {
              ...t,
              name: t.path === path ? newName : t.name,
              path: t.path.replace(path, newPath),
            };
          }
          return t;
        });

        set({ files: newFiles, filesByPath: newFilesByPath, tabs: newTabs });
      },

      moveNode: (fromPath, toPath) => {
        // Implementation similar to rename but with parent change
        // Simplified for MVP
        const state = get();
        const id = state.filesByPath.get(fromPath);
        if (!id) return;

        const node = state.files.get(id);
        if (!node) return;

        const newParentPath = getParentPath(toPath);
        const newParentId = state.filesByPath.get(newParentPath);
        if (!newParentId) return;

        // For MVP, just update the path and parent
        get().renameNode(fromPath, node.name);
      },

      // Tab actions
      openTab: (fileId) => {
        const state = get();
        const file = state.files.get(fileId);
        if (!file || file.type !== 'file') return;

        // Check if tab already exists
        const existingTab = state.tabs.find((t) => t.fileId === fileId);
        if (existingTab) {
          set({ activeTabId: existingTab.id });
          return;
        }

        const tab: EditorTab = {
          id: generateId(),
          fileId,
          name: file.name,
          path: file.path,
          language: file.language || 'plaintext',
          isModified: false,
        };

        set({
          tabs: [...state.tabs, tab],
          activeTabId: tab.id,
        });
      },

      closeTab: (tabId) => {
        const state = get();
        const tabIndex = state.tabs.findIndex((t) => t.id === tabId);
        if (tabIndex === -1) return;

        const newTabs = state.tabs.filter((t) => t.id !== tabId);
        let newActiveTabId = state.activeTabId;

        if (state.activeTabId === tabId) {
          // Activate adjacent tab
          if (newTabs.length > 0) {
            const newIndex = Math.min(tabIndex, newTabs.length - 1);
            newActiveTabId = newTabs[newIndex].id;
          } else {
            newActiveTabId = null;
          }
        }

        set({ tabs: newTabs, activeTabId: newActiveTabId });
      },

      closeAllTabs: () => {
        set({ tabs: [], activeTabId: null });
      },

      setActiveTab: (tabId) => {
        set({ activeTabId: tabId });
      },

      markTabModified: (tabId, modified) => {
        set((state) => ({
          tabs: state.tabs.map((t) => (t.id === tabId ? { ...t, isModified: modified } : t)),
        }));
      },

      // Helpers
      getNode: (path) => {
        const state = get();
        const id = state.filesByPath.get(path);
        return id ? state.files.get(id) ?? null : null;
      },

      getNodeById: (id) => {
        return get().files.get(id) ?? null;
      },

      getChildren: (path) => {
        const state = get();
        const id = state.filesByPath.get(path);
        if (!id) return [];

        const node = state.files.get(id);
        if (!node || node.type !== 'directory' || !node.children) return [];

        return node.children
          .map((cid) => state.files.get(cid))
          .filter((n): n is FileNode => n !== undefined)
          .sort((a, b) => {
            // Directories first, then alphabetically
            if (a.type !== b.type) {
              return a.type === 'directory' ? -1 : 1;
            }
            return a.name.localeCompare(b.name);
          });
      },

      getActiveFile: () => {
        const state = get();
        const activeTab = state.tabs.find((t) => t.id === state.activeTabId);
        if (!activeTab) return null;
        return state.files.get(activeTab.fileId) ?? null;
      },
    }),
    {
      name: 'flowcode-projects',
      partialize: (state) => ({
        projects: state.projects,
        files: Array.from(state.files.entries()),
        filesByPath: Array.from(state.filesByPath.entries()),
      }),
      onRehydrateStorage: () => (state) => {
        if (state) {
          // Convert arrays back to Maps
          if (Array.isArray(state.files)) {
            state.files = new Map(state.files as [string, FileNode][]);
          }
          if (Array.isArray(state.filesByPath)) {
            state.filesByPath = new Map(state.filesByPath as [string, string][]);
          }
        }
      },
    }
  )
);
