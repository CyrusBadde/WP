// Core type definitions for FlowCode

// ============================================================================
// File System Types
// ============================================================================

export interface FileNode {
  id: string;
  name: string;
  path: string;
  type: 'file' | 'directory';
  content?: string;
  language?: string;
  children?: string[];
  parentId: string | null;
  metadata: {
    createdAt: number;
    modifiedAt: number;
    size: number;
  };
}

export interface Project {
  id: string;
  name: string;
  description: string;
  template: ProjectTemplate;
  rootId: string;
  settings: ProjectSettings;
  createdAt: number;
  modifiedAt: number;
}

export interface ProjectSettings {
  theme: 'light' | 'dark' | 'system';
  fontSize: number;
  tabSize: number;
  wordWrap: boolean;
  minimap: boolean;
  keybindings: 'default' | 'vim' | 'emacs';
}

export type ProjectTemplate = 'react' | 'nextjs' | 'api' | 'blank';

// ============================================================================
// Editor Types
// ============================================================================

export interface EditorTab {
  id: string;
  fileId: string;
  name: string;
  path: string;
  language: string;
  isModified: boolean;
  scrollPosition?: { line: number; column: number };
}

export interface EditorSettings {
  theme: 'vs-dark' | 'vs-light' | 'hc-black';
  fontSize: number;
  tabSize: number;
  wordWrap: 'on' | 'off' | 'wordWrapColumn';
  minimap: boolean;
  lineNumbers: 'on' | 'off' | 'relative';
  keybindings: 'default' | 'vim' | 'emacs';
}

// ============================================================================
// AI Types
// ============================================================================

export type AgentType = 'orchestrator' | 'coding' | 'debugging' | 'testing' | 'documentation' | 'optimization' | 'review';

export interface ChatMessage {
  id: string;
  role: 'user' | 'assistant' | 'system';
  content: string;
  timestamp: number;
  agent?: AgentType;
  metadata?: {
    actions?: AIAction[];
    filesReferenced?: string[];
    tokensUsed?: number;
  };
}

export interface AIAction {
  id: string;
  type: 'create_file' | 'edit_file' | 'delete_file' | 'explain' | 'suggest';
  status: 'pending' | 'applied' | 'rejected';
  payload: {
    filePath?: string;
    content?: string;
    diff?: CodeDiff;
    explanation?: string;
  };
  timestamp: number;
}

export interface CodeDiff {
  filePath: string;
  hunks: DiffHunk[];
}

export interface DiffHunk {
  oldStart: number;
  oldLines: number;
  newStart: number;
  newLines: number;
  content: string;
}

export interface StreamChunk {
  type: 'text' | 'action' | 'progress' | 'error' | 'done';
  content?: string;
  action?: Partial<AIAction>;
  progress?: { step: string; percent: number };
  error?: string;
}

export interface AIContext {
  files: FileContext[];
  recentDiffs: CodeDiff[];
  projectInfo: {
    name: string;
    template: ProjectTemplate;
    fileCount: number;
  };
}

export interface FileContext {
  path: string;
  content: string;
  language: string;
}

// ============================================================================
// Workflow Types (React Flow)
// ============================================================================

export interface WorkflowNode {
  id: string;
  type: 'trigger' | 'action' | 'condition' | 'transform';
  position: { x: number; y: number };
  data: {
    label: string;
    config: Record<string, unknown>;
    status?: 'idle' | 'running' | 'success' | 'error';
  };
}

export interface WorkflowEdge {
  id: string;
  source: string;
  target: string;
  sourceHandle?: string;
  targetHandle?: string;
  label?: string;
}

export interface Workflow {
  id: string;
  name: string;
  description: string;
  nodes: WorkflowNode[];
  edges: WorkflowEdge[];
  createdAt: number;
  modifiedAt: number;
}

export interface WorkflowTemplate {
  id: string;
  name: string;
  description: string;
  category: string;
  nodes: Omit<WorkflowNode, 'id'>[];
  edges: Omit<WorkflowEdge, 'id'>[];
}

// ============================================================================
// Integration Types
// ============================================================================

export type IntegrationType = 'github' | 'gitlab' | 'vercel' | 'netlify' | 'supabase';

export interface Integration {
  id: string;
  type: IntegrationType;
  name: string;
  status: 'connected' | 'disconnected' | 'error';
  permissions: IntegrationPermission[];
  connectedAt?: number;
  isMocked: boolean;
}

export interface IntegrationPermission {
  scope: 'read' | 'write' | 'delete' | 'admin';
  resources: string[];
  grantedAt: number;
  expiresAt?: number;
}

// ============================================================================
// Database Types
// ============================================================================

export interface DatabaseSchema {
  id: string;
  name: string;
  tables: DatabaseTable[];
  createdAt: number;
  modifiedAt: number;
}

export interface DatabaseTable {
  id: string;
  name: string;
  columns: DatabaseColumn[];
  relations: DatabaseRelation[];
}

export interface DatabaseColumn {
  id: string;
  name: string;
  type: 'text' | 'integer' | 'boolean' | 'timestamp' | 'uuid' | 'json';
  nullable: boolean;
  defaultValue?: string;
  isPrimaryKey: boolean;
  isUnique: boolean;
}

export interface DatabaseRelation {
  id: string;
  type: 'one-to-one' | 'one-to-many' | 'many-to-many';
  targetTable: string;
  sourceColumn: string;
  targetColumn: string;
}

// ============================================================================
// Git Types
// ============================================================================

export interface GitCommit {
  id: string;
  hash: string;
  message: string;
  author: string;
  timestamp: number;
  parentIds: string[];
  branch: string;
}

export interface GitBranch {
  name: string;
  commitId: string;
  isActive: boolean;
  upstream?: string;
}

export interface GitStatus {
  staged: string[];
  modified: string[];
  untracked: string[];
}

// ============================================================================
// Audit Types
// ============================================================================

export interface AuditEntry {
  id: string;
  timestamp: number;
  type: 'ai_action' | 'file_change' | 'integration' | 'workflow' | 'error' | 'security';
  action: string;
  details: Record<string, unknown>;
  userId?: string;
  metadata?: {
    before?: string;
    after?: string;
    reason?: string;
  };
}

// ============================================================================
// UI Types
// ============================================================================

export type Theme = 'light' | 'dark' | 'system';
export type PanelId = 'files' | 'components' | 'integrations' | 'database' | 'git' | 'ai';

export interface PanelLayout {
  leftSidebarOpen: boolean;
  rightSidebarOpen: boolean;
  bottomPanelOpen: boolean;
  leftSidebarWidth: number;
  rightSidebarWidth: number;
  bottomPanelHeight: number;
  activeLeftPanel: PanelId;
}

export interface Toast {
  id: string;
  type: 'info' | 'success' | 'warning' | 'error';
  title: string;
  description?: string;
  duration?: number;
}

// ============================================================================
// Component Library Types
// ============================================================================

export interface ComponentDefinition {
  id: string;
  name: string;
  category: string;
  description: string;
  preview: string;
  code: string;
  imports: string[];
  props: ComponentProp[];
}

export interface ComponentProp {
  name: string;
  type: string;
  required: boolean;
  defaultValue?: string;
  description: string;
}
