# FlowCode: Phase 2 & Phase 3 Implementation Guide

## Phase 1 Completion Summary

Phase 1 MVP is now complete with the following deliverables:

### Delivered Components
- **Project Scaffolding**: React 19 + TypeScript + Vite + Tailwind CSS v4
- **State Management**: Zustand stores for UI, Project, AI, and Audit
- **File System**: Simulated in-memory filesystem with IndexedDB persistence
- **Editor**: Monaco Editor with multi-file support, tabs, and keyboard shortcuts
- **AI Agent System**: Adapter pattern with streaming, rate limiting, and mock fallback
- **Command Palette**: Cmd+K palette with fuzzy search
- **Welcome Screen**: Template gallery (React, Next.js, API, Blank)
- **Security**: Prompt injection sanitization, memory-only API keys, audit logging
- **Tests**: 46 passing tests covering utilities, stores, and security

### Documentation
- `docs/ARCHITECTURE.md` - System design and component hierarchy
- `docs/INVARIANTS.md` - System constraints and invariants
- `docs/THREAT_MODEL.md` - Security analysis using STRIDE methodology

---

## Phase 2: Workflow Automation & Integrations UI

### Priority 1: Visual Workflow Canvas

**Goal**: Drag-and-drop workflow builder using React Flow

#### Implementation Steps

1. **Create Workflow Store** (`src/stores/workflowStore.ts`)
   ```typescript
   interface WorkflowState {
     workflows: Map<string, Workflow>;
     activeWorkflowId: string | null;
     nodes: WorkflowNode[];
     edges: WorkflowEdge[];
     // Actions
     addNode: (node: WorkflowNode) => void;
     removeNode: (nodeId: string) => void;
     connectNodes: (source: string, target: string) => void;
     executeWorkflow: (workflowId: string) => Promise<void>;
   }
   ```

2. **Define Node Types**
   - **Trigger Nodes**: Manual, Schedule, Webhook, File Change
   - **Action Nodes**: AI Agent, Code Transform, API Call, File Operation
   - **Control Nodes**: Conditional, Loop, Parallel, Wait
   - **Output Nodes**: Deploy, Notification, Export

3. **Build React Flow Components**
   - `src/components/workflow/WorkflowCanvas.tsx` - Main canvas
   - `src/components/workflow/NodePalette.tsx` - Draggable node library
   - `src/components/workflow/NodeEditor.tsx` - Node configuration panel
   - `src/components/workflow/nodes/*.tsx` - Custom node renderers

4. **Workflow Execution Engine**
   - Topological sort for execution order
   - Async node execution with error handling
   - State passing between nodes
   - Execution history and replay

### Priority 2: Integration Hub UI

**Goal**: Mock UI for external service connections

#### Implementation Steps

1. **Create Integrations Store** (`src/stores/integrationsStore.ts`)
   ```typescript
   interface Integration {
     id: string;
     type: 'github' | 'gitlab' | 'vercel' | 'netlify' | 'supabase';
     name: string;
     connected: boolean;
     config: Record<string, unknown>;
   }
   ```

2. **Build Integration Components**
   - `src/components/integrations/IntegrationHub.tsx` - Main hub view
   - `src/components/integrations/IntegrationCard.tsx` - Service card
   - `src/components/integrations/ConfigModal.tsx` - Configuration dialog

3. **Mock Integration Behaviors**
   - GitHub: Repository list, branches, commits, PRs
   - Vercel/Netlify: Deployment list, deploy trigger
   - Supabase: Schema browser, query interface

### Priority 3: Database Schema Builder

**Goal**: Visual database schema designer

#### Implementation Steps

1. **Create Schema Store** (`src/stores/schemaStore.ts`)
   ```typescript
   interface SchemaState {
     tables: Table[];
     relationships: Relationship[];
     // Actions
     addTable: (table: Table) => void;
     addColumn: (tableId: string, column: Column) => void;
     addRelationship: (rel: Relationship) => void;
     generateMigration: () => string;
   }
   ```

2. **Build Schema Components**
   - `src/components/schema/SchemaCanvas.tsx` - Visual schema view
   - `src/components/schema/TableNode.tsx` - Table representation
   - `src/components/schema/RelationshipLine.tsx` - FK visualization
   - `src/components/schema/ColumnEditor.tsx` - Column configuration

3. **SQL Generation**
   - PostgreSQL CREATE TABLE statements
   - Foreign key constraints
   - Index definitions
   - Migration diff generation

### Priority 4: Enhanced AI Agents

**Goal**: Specialized agent capabilities

#### Implementation Steps

1. **Extend Agent System**
   ```typescript
   interface AgentCapability {
     id: string;
     name: string;
     description: string;
     systemPrompt: string;
     tools: AgentTool[];
   }

   const AGENTS: Record<AgentType, AgentCapability> = {
     coding: { /* ... */ },
     debugging: { /* ... */ },
     testing: { /* ... */ },
     documentation: { /* ... */ },
     optimization: { /* ... */ },
     review: { /* ... */ },
   };
   ```

2. **Add Agent Tools**
   - File read/write operations
   - Code execution (sandboxed)
   - Test execution
   - Linting and formatting

3. **Build Agent UI**
   - Agent selector in chat panel
   - Tool execution visualization
   - Action history and undo

---

## Phase 3: Backend & Production Infrastructure

### Priority 1: Secure Backend Service

**Goal**: Handle secrets and OAuth securely

#### Technology Stack
- **Runtime**: Node.js + Express or Hono
- **Database**: PostgreSQL with Prisma ORM
- **Auth**: OAuth 2.0 with JWT sessions
- **Secrets**: HashiCorp Vault or AWS Secrets Manager

#### Implementation Steps

1. **API Design**
   ```
   POST /api/auth/login          - OAuth initiation
   GET  /api/auth/callback/:provider - OAuth callback
   POST /api/auth/logout         - Session termination

   GET  /api/projects            - List user projects
   POST /api/projects            - Create project
   GET  /api/projects/:id        - Get project
   PUT  /api/projects/:id        - Update project
   DELETE /api/projects/:id      - Delete project

   POST /api/integrations/:type/connect    - Connect integration
   DELETE /api/integrations/:type/disconnect - Disconnect
   GET  /api/integrations/:type/status     - Connection status

   POST /api/secrets             - Store encrypted secret
   DELETE /api/secrets/:id       - Delete secret

   POST /api/deploy              - Trigger deployment
   GET  /api/deploy/:id/status   - Deployment status
   ```

2. **Database Schema**
   ```sql
   CREATE TABLE users (
     id UUID PRIMARY KEY,
     email VARCHAR(255) UNIQUE NOT NULL,
     created_at TIMESTAMP DEFAULT NOW()
   );

   CREATE TABLE projects (
     id UUID PRIMARY KEY,
     user_id UUID REFERENCES users(id),
     name VARCHAR(255) NOT NULL,
     data JSONB NOT NULL,
     created_at TIMESTAMP DEFAULT NOW(),
     updated_at TIMESTAMP DEFAULT NOW()
   );

   CREATE TABLE integrations (
     id UUID PRIMARY KEY,
     user_id UUID REFERENCES users(id),
     type VARCHAR(50) NOT NULL,
     encrypted_tokens BYTEA,
     config JSONB,
     created_at TIMESTAMP DEFAULT NOW()
   );

   CREATE TABLE secrets (
     id UUID PRIMARY KEY,
     user_id UUID REFERENCES users(id),
     name VARCHAR(255) NOT NULL,
     encrypted_value BYTEA NOT NULL,
     created_at TIMESTAMP DEFAULT NOW()
   );
   ```

3. **Security Implementation**
   - Token encryption at rest using AES-256-GCM
   - HTTPS only with HSTS
   - CSRF protection
   - Rate limiting per user
   - Audit logging

### Priority 2: OAuth Integration

**Goal**: Real authentication with external services

#### Implementation Steps

1. **OAuth Providers**
   - GitHub OAuth App
   - GitLab OAuth Application
   - Vercel Integration
   - Netlify OAuth

2. **Token Management**
   - Secure token storage (encrypted)
   - Token refresh handling
   - Scope validation
   - Revocation support

3. **User Session**
   - JWT with short expiry
   - Refresh token rotation
   - Session invalidation

### Priority 3: Real Deployments

**Goal**: Actual deployment to hosting platforms

#### Implementation Steps

1. **Vercel Integration**
   - Project creation via API
   - File upload and deployment
   - Environment variable management
   - Domain configuration

2. **Netlify Integration**
   - Site creation
   - Deploy via API
   - Build settings
   - Deploy previews

3. **Deployment Pipeline**
   - Build in sandboxed container
   - Asset optimization
   - Deployment queue
   - Rollback support

### Priority 4: Multi-User & Collaboration

**Goal**: Team features (stretch goal)

#### Implementation Steps

1. **User Management**
   - User registration and profiles
   - Team/organization structure
   - Role-based access control

2. **Real-Time Collaboration**
   - WebSocket connections
   - Operational Transform or CRDT
   - Cursor presence
   - Change notifications

---

## Technical Debt to Address

### Before Phase 2
- [ ] Add E2E tests with Playwright
- [ ] Implement proper error boundaries
- [ ] Add loading states for all async operations
- [ ] Improve accessibility (ARIA labels, keyboard navigation)
- [ ] Add comprehensive keyboard shortcuts

### Before Phase 3
- [ ] Performance audit and optimization
- [ ] Bundle size analysis and code splitting
- [ ] SEO meta tags (if needed for public pages)
- [ ] Analytics integration
- [ ] Error monitoring (Sentry or similar)

---

## Estimated Effort

| Phase | Component | Complexity | Notes |
|-------|-----------|------------|-------|
| 2 | Workflow Canvas | High | React Flow integration, execution engine |
| 2 | Integration Hub UI | Medium | Mock implementations only |
| 2 | Schema Builder | Medium | Visual editor with SQL generation |
| 2 | Enhanced AI Agents | Medium | Extend existing adapter |
| 3 | Backend Service | High | Full auth, encryption, API |
| 3 | OAuth Integration | Medium | Multiple providers |
| 3 | Real Deployments | High | Platform-specific APIs |
| 3 | Collaboration | Very High | Real-time sync complexity |

---

## Security Considerations for Phase 3

### Critical Requirements

1. **Secret Storage**
   - Never log secrets
   - Encrypt at rest
   - Short-lived tokens
   - Principle of least privilege

2. **OAuth Tokens**
   - Secure storage
   - Regular rotation
   - Scope minimization
   - Revocation handling

3. **API Security**
   - Authentication required
   - Input validation
   - Rate limiting
   - CORS configuration

4. **Deployment Security**
   - Sandboxed builds
   - No code execution on server
   - Asset scanning
   - Dependency auditing

---

## Getting Started with Phase 2

1. **Create feature branch**
   ```bash
   git checkout -b feature/phase-2-workflow
   ```

2. **Install React Flow**
   ```bash
   npm install @xyflow/react
   ```

3. **Start with workflow store**
   - Define types in `src/types/workflow.ts`
   - Create store in `src/stores/workflowStore.ts`
   - Add tests in `src/test/workflowStore.test.ts`

4. **Build canvas component**
   - Start with basic React Flow setup
   - Add custom node types
   - Implement drag-and-drop from palette

5. **Integrate with existing editor**
   - Add workflow panel to workspace
   - Connect to AI agent for workflow generation
