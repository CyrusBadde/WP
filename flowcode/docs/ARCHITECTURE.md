# FlowCode Architecture Document

## Executive Summary

FlowCode is a browser-first "vibe coding" platform that combines AI-assisted development, visual workflow automation, and seamless integrations. This document defines the architecture for an MVP that is secure, testable, and shippable.

---

## 1. System Overview

### 1.1 Design Principles

1. **Browser-First**: Phase 1 runs entirely in the browser with no backend dependencies
2. **Security by Default**: No sensitive data in browser storage; clear separation of demo vs secure modes
3. **Progressive Enhancement**: Features gracefully degrade; mocked services are clearly labeled
4. **Composable Architecture**: Modular components that can be independently tested and replaced
5. **AI as a Tool**: AI assists but never executes without user confirmation

### 1.2 High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              BROWSER (Client)                                │
├─────────────────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐ │
│  │   React     │  │   Monaco    │  │ React Flow  │  │   AI Chat Panel     │ │
│  │   Shell     │  │   Editor    │  │   Canvas    │  │   (Streaming)       │ │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘  └──────────┬──────────┘ │
│         │                │                │                     │           │
│  ┌──────┴────────────────┴────────────────┴─────────────────────┴─────────┐ │
│  │                         State Management Layer                          │ │
│  │   (Zustand stores: project, editor, ai, workflow, integrations)        │ │
│  └────────────────────────────────┬───────────────────────────────────────┘ │
│                                   │                                         │
│  ┌────────────────────────────────┴───────────────────────────────────────┐ │
│  │                          Service Layer                                  │ │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌─────────────┐ │ │
│  │  │ FileSystem   │  │ AI Adapter   │  │ Integration  │  │ Audit Log   │ │ │
│  │  │ (Simulated)  │  │ (Anthropic)  │  │ Adapters     │  │ Service     │ │ │
│  │  └──────────────┘  └──────────────┘  └──────────────┘  └─────────────┘ │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                   │                                         │
│  ┌────────────────────────────────┴───────────────────────────────────────┐ │
│  │                       Persistence Layer                                 │ │
│  │   IndexedDB: projects, workflows, chat history, audit logs             │ │
│  │   LocalStorage: settings, theme preferences (non-sensitive only)       │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ HTTPS (API calls only)
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           EXTERNAL SERVICES                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│  ┌──────────────┐                                                           │
│  │ Anthropic    │  AI completions (claude-sonnet-4-20250514)                    │
│  │ API          │  Configurable via adapter                                 │
│  └──────────────┘                                                           │
│                                                                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  (Phase 3)           │
│  │ GitHub       │  │ Vercel       │  │ Supabase     │  Requires OAuth +    │
│  │ (mocked P1)  │  │ (mocked P1)  │  │ (mocked P1)  │  backend token store │
│  └──────────────┘  └──────────────┘  └──────────────┘                       │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ (Phase 3 only)
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           BACKEND (Phase 3)                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│  - OAuth flow handlers                                                      │
│  - Secure token storage (encrypted at rest)                                 │
│  - Deployment orchestration                                                 │
│  - API proxy for integrations                                               │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Component Diagram

### 2.1 React Component Hierarchy

```
<App>
├── <ThemeProvider>
│   ├── <ErrorBoundary>
│   │   ├── <Router>
│   │   │   ├── <WelcomeScreen>                    # Landing / project selection
│   │   │   │   ├── <TemplateGallery>
│   │   │   │   ├── <RecentProjects>
│   │   │   │   └── <QuickActions>
│   │   │   │
│   │   │   └── <WorkspaceLayout>                  # Main editor workspace
│   │   │       ├── <Sidebar>
│   │   │       │   ├── <FileTree>
│   │   │       │   ├── <ComponentPalette>
│   │   │       │   ├── <IntegrationHub>
│   │   │       │   └── <DatabasePanel>
│   │   │       │
│   │   │       ├── <MainArea>
│   │   │       │   ├── <TabBar>
│   │   │       │   ├── <EditorPane>
│   │   │       │   │   ├── <MonacoEditor>
│   │   │       │   │   └── <InlineSuggestions>
│   │   │       │   ├── <PreviewPane>
│   │   │       │   │   └── <IframeSandbox>
│   │   │       │   ├── <TerminalPane>             # Simulated terminal
│   │   │       │   └── <WorkflowCanvas>           # React Flow
│   │   │       │       ├── <TriggerNodes>
│   │   │       │       ├── <ActionNodes>
│   │   │       │       └── <TemplateGallery>
│   │   │       │
│   │   │       ├── <AIChatPanel>
│   │   │       │   ├── <ChatHistory>
│   │   │       │   ├── <StreamingResponse>
│   │   │       │   ├── <ProgressSteps>
│   │   │       │   └── <QuickActions>
│   │   │       │
│   │   │       ├── <CommandPalette>               # Cmd+K modal
│   │   │       ├── <GitPanel>
│   │   │       │   ├── <BranchGraph>
│   │   │       │   └── <CommitHistory>
│   │   │       │
│   │   │       └── <StatusBar>
│   │   │
│   │   └── <ToastNotifications>
│   │
│   └── <AuditLogViewer>                           # Debug/admin panel
```

### 2.2 State Stores (Zustand)

```
stores/
├── projectStore.ts          # Current project, files, active file
│   ├── projects: Project[]
│   ├── currentProject: Project | null
│   ├── files: FileNode[]
│   ├── activeFileId: string
│   └── actions: createProject, openFile, saveFile, etc.
│
├── editorStore.ts           # Editor state, tabs, settings
│   ├── tabs: Tab[]
│   ├── activeTabId: string
│   ├── editorSettings: { theme, keybindings, fontSize }
│   └── actions: openTab, closeTab, updateSettings
│
├── aiStore.ts               # AI chat, agents, streaming
│   ├── messages: ChatMessage[]
│   ├── isStreaming: boolean
│   ├── currentAgent: AgentType
│   ├── pendingActions: AIAction[]
│   └── actions: sendMessage, applyAction, cancelStream
│
├── workflowStore.ts         # React Flow state
│   ├── nodes: Node[]
│   ├── edges: Edge[]
│   ├── templates: WorkflowTemplate[]
│   └── actions: addNode, connect, saveWorkflow
│
├── integrationStore.ts      # External service connections
│   ├── integrations: Integration[]
│   ├── permissions: PermissionGrant[]
│   └── actions: connect, disconnect, checkStatus
│
├── auditStore.ts            # Audit log
│   ├── entries: AuditEntry[]
│   └── actions: log, query, export
│
└── uiStore.ts               # UI state (panels, theme)
    ├── theme: 'light' | 'dark'
    ├── accentColor: string
    ├── panelLayout: LayoutConfig
    ├── commandPaletteOpen: boolean
    └── actions: setTheme, togglePanel, openCommandPalette
```

---

## 3. Data Flow

### 3.1 User Creates a New Project

```
┌────────┐    ┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  User  │───▶│ TemplateGal  │───▶│ projectStore │───▶│  IndexedDB   │
│ clicks │    │ lery.select  │    │ .createProj  │    │  .projects   │
└────────┘    └──────────────┘    └──────────────┘    └──────────────┘
                                         │
                                         ▼
                                  ┌──────────────┐
                                  │ FileSystem   │  Creates initial
                                  │ Service      │  file structure
                                  └──────────────┘
```

### 3.2 User Asks AI to Generate Code

```
┌────────┐    ┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  User  │───▶│ AIChatPanel  │───▶│   aiStore    │───▶│  AI Adapter  │
│ prompt │    │ .sendMessage │    │ .sendMessage │    │ .complete()  │
└────────┘    └──────────────┘    └──────────────┘    └──────────────┘
                                                             │
                              ┌───────────────────────────────┘
                              │ Streaming chunks
                              ▼
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│ StreamingRes │◀───│   aiStore    │───▶│  auditStore  │
│ ponse UI     │    │ .appendChunk │    │ .log(action) │
└──────────────┘    └──────────────┘    └──────────────┘
                              │
                              │ User confirms "Apply"
                              ▼
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│ MonacoEditor │◀───│ projectStore │───▶│  IndexedDB   │
│ updates      │    │ .applyDiff   │    │ auto-save    │
└──────────────┘    └──────────────┘    └──────────────┘
```

### 3.3 Workflow Automation Trigger

```
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│ WorkflowCanv │───▶│ workflowStor │───▶│  Execution   │
│ as.execute() │    │ e.run()      │    │  Engine      │
└──────────────┘    └──────────────┘    └──────────────┘
                                               │
                    ┌──────────────────────────┤
                    │                          │
                    ▼                          ▼
           ┌──────────────┐           ┌──────────────┐
           │ Mock Adapter │           │ Real Adapter │ (Phase 3)
           │ (simulated)  │           │ (API calls)  │
           └──────────────┘           └──────────────┘
                    │                          │
                    └──────────┬───────────────┘
                               ▼
                    ┌──────────────┐
                    │  auditStore  │  Log all actions
                    │  .log()      │
                    └──────────────┘
```

---

## 4. File System Simulation

### 4.1 Data Structure

```typescript
interface FileNode {
  id: string;                    // UUID
  name: string;                  // "Button.tsx"
  path: string;                  // "/src/components/Button.tsx"
  type: 'file' | 'directory';
  content?: string;              // File content (files only)
  language?: string;             // "typescript", "css", etc.
  children?: string[];           // Child IDs (directories only)
  parentId: string | null;
  metadata: {
    createdAt: number;
    modifiedAt: number;
    size: number;
  };
}

interface Project {
  id: string;
  name: string;
  description: string;
  template: string;
  rootId: string;                // Root directory FileNode ID
  settings: ProjectSettings;
  createdAt: number;
  modifiedAt: number;
}
```

### 4.2 Operations

```typescript
interface FileSystemService {
  // CRUD
  createFile(parentPath: string, name: string, content?: string): FileNode;
  createDirectory(parentPath: string, name: string): FileNode;
  readFile(path: string): string;
  updateFile(path: string, content: string): void;
  deleteNode(path: string): void;
  moveNode(fromPath: string, toPath: string): void;

  // Queries
  getNode(path: string): FileNode | null;
  listDirectory(path: string): FileNode[];
  searchFiles(query: string): FileNode[];

  // Persistence
  exportProject(): Blob;         // ZIP file
  importProject(file: File): Project;
}
```

---

## 5. AI Agent System

### 5.1 Agent Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        AI Orchestrator                          │
├─────────────────────────────────────────────────────────────────┤
│  Responsibilities:                                              │
│  - Parse user intent                                            │
│  - Delegate to specialized agents                               │
│  - Merge results from multiple agents                           │
│  - Manage context window (files + recent diffs)                 │
│  - Enforce rate limiting and debouncing                         │
└─────────────────────────────┬───────────────────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
        ▼                     ▼                     ▼
┌───────────────┐    ┌───────────────┐    ┌───────────────┐
│  Coding Agent │    │  Debug Agent  │    │  Docs Agent   │
├───────────────┤    ├───────────────┤    ├───────────────┤
│ - Generate    │    │ - Analyze err │    │ - Generate    │
│   components  │    │ - Suggest fix │    │   JSDoc       │
│ - Refactor    │    │ - Add logging │    │ - README      │
│ - Implement   │    │ - Trace flow  │    │ - Component   │
│   features    │    │               │    │   docs        │
└───────────────┘    └───────────────┘    └───────────────┘
        │                     │                     │
        ▼                     ▼                     ▼
┌───────────────┐    ┌───────────────┐    ┌───────────────┐
│ Optimize Agent│    │  Test Agent   │    │  Review Agent │
├───────────────┤    ├───────────────┤    ├───────────────┤
│ - Performance │    │ - Generate    │    │ - Code review │
│ - Bundle size │    │   unit tests  │    │ - Best practs │
│ - Memoization │    │ - Test cases  │    │ - Security    │
│ - Lazy load   │    │ - Coverage    │    │   audit       │
└───────────────┘    └───────────────┘    └───────────────┘
```

### 5.2 AI Adapter Interface

```typescript
interface AIAdapter {
  // Core completion
  complete(request: CompletionRequest): AsyncGenerator<StreamChunk>;

  // Specialized methods
  generateCode(context: CodeContext): AsyncGenerator<CodeDiff>;
  explainCode(code: string): Promise<string>;
  generateTests(code: string): Promise<TestSuite>;
  suggestFix(error: ErrorContext): Promise<FixSuggestion>;

  // Configuration
  setModel(model: string): void;
  getModel(): string;
}

interface CompletionRequest {
  messages: Message[];
  context: {
    files: FileContext[];        // Relevant files
    recentDiffs: Diff[];         // Recent changes
    projectInfo: ProjectMeta;    // Project metadata
  };
  options: {
    maxTokens?: number;
    temperature?: number;
    stream: boolean;
  };
}

// Default implementation uses Anthropic API
// Model is configurable: claude-sonnet-4-20250514 by default
const DEFAULT_MODEL = 'claude-sonnet-4-20250514';
```

### 5.3 Prompt Injection Defenses

1. **System Prompt Separation**: User content is clearly demarcated
2. **Output Validation**: AI outputs are validated before execution
3. **Action Confirmation**: Destructive actions require user confirmation
4. **Content Sandboxing**: Generated code runs in sandboxed iframe

---

## 6. Execution Model

### 6.1 Where Code Runs

| Component | Runs In | Rationale |
|-----------|---------|-----------|
| React UI | Browser | User interaction, immediate feedback |
| Monaco Editor | Browser | Standard browser-based editor |
| File System | Browser (IndexedDB) | No backend in Phase 1 |
| AI API Calls | Browser → Anthropic | Direct API calls with user's key |
| Preview | Browser (sandboxed iframe) | Security isolation |
| Workflow Engine | Browser | Local execution with mock adapters |
| Integrations | Browser (mocked) | Real calls require backend (Phase 3) |

### 6.2 Preview Sandbox

```typescript
// Preview runs in a sandboxed iframe
<iframe
  sandbox="allow-scripts allow-same-origin"
  src="about:blank"
  // No allow-top-navigation
  // No allow-forms (unless explicitly needed)
  // No allow-popups
/>
```

The preview compiles user code using an in-browser bundler (esbuild-wasm) and renders it in isolation.

---

## 7. Persistence Strategy

### 7.1 Storage Allocation

| Data Type | Storage | Rationale |
|-----------|---------|-----------|
| Projects | IndexedDB | Large, structured data |
| File contents | IndexedDB | Can be large, needs indexing |
| Workflows | IndexedDB | Complex nested structures |
| Chat history | IndexedDB | Can grow large |
| Audit logs | IndexedDB | Append-only, queryable |
| Settings | LocalStorage | Small, frequently accessed |
| Theme prefs | LocalStorage | Small, frequently accessed |
| API keys | **NOT STORED** | Security - prompt each session or Phase 3 backend |

### 7.2 Demo Mode vs Secure Mode

```typescript
type AppMode = 'demo' | 'secure';

// Demo Mode (Phase 1)
// - No real API keys stored
// - Mock integrations only
// - Clear warning banners
// - Suitable for learning/exploration

// Secure Mode (Phase 3)
// - Backend handles OAuth flows
// - Tokens stored server-side (encrypted)
// - Real integrations enabled
// - Audit trail for all actions
```

---

## 8. Decision Rationale

### 8.1 Why Zustand over Redux/Context?

- **Bundle size**: ~1KB vs Redux's ~7KB
- **Simplicity**: No boilerplate, no providers required
- **DevEx**: TypeScript inference works naturally
- **Persistence**: Built-in middleware for IndexedDB integration
- **Performance**: Automatic selective re-renders

### 8.2 Why In-Browser File System?

- **Zero backend**: Ship Phase 1 without infrastructure
- **Offline-capable**: Works without network
- **Privacy**: User data never leaves browser
- **Speed**: No network latency for file operations

### 8.3 Why Mock Integrations First?

- **Security**: OAuth tokens require secure server-side storage
- **Velocity**: Ship faster without OAuth complexity
- **UX**: Full UI is testable with mocks
- **Clear upgrade path**: Swap mock adapters for real ones

---

## 9. Next Phase Dependencies

### Phase 2 Requirements (Workflow + Integrations UI)
- Phase 1 complete and tested
- React Flow canvas integrated
- Mock adapters defined

### Phase 3 Requirements (Backend + Secure Mode)
- Phase 2 complete
- Backend infrastructure (Node.js recommended)
- OAuth app registrations (GitHub, GitLab, etc.)
- Secrets management (HashiCorp Vault or AWS Secrets Manager)
- Database for user data (PostgreSQL recommended)

---

## Appendix A: Technology Versions

| Technology | Version | Purpose |
|------------|---------|---------|
| React | 18.x | UI framework |
| TypeScript | 5.x | Type safety |
| Tailwind CSS | 3.x | Styling |
| shadcn/ui | latest | Component library |
| Monaco Editor | 0.45.x | Code editor |
| React Flow | 11.x | Workflow canvas |
| Zustand | 4.x | State management |
| esbuild-wasm | 0.19.x | In-browser bundling |
| idb | 7.x | IndexedDB wrapper |
| Vitest | 1.x | Testing |

---

*Document Version: 1.0*
*Last Updated: Phase 0*
*Status: Approved for Phase 1 Implementation*
