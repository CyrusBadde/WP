# FlowCode System Invariants

This document defines the invariants and constraints that must hold true throughout the system's operation. Violations of these invariants indicate bugs or security issues.

---

## 1. Security Invariants

### S1: No Secrets in Browser Storage
```
INVARIANT: LocalStorage ∪ IndexedDB ∩ {API keys, OAuth tokens, passwords} = ∅
```
- API keys are NEVER persisted in browser storage
- Users must provide API keys per-session OR use Phase 3 backend
- Demo keys for testing are clearly marked as non-sensitive

### S2: User Confirmation for Destructive Actions
```
INVARIANT: ∀ action ∈ DestructiveActions: requiresUserConfirmation(action) = true
```
Destructive actions include:
- Deleting files/projects
- Overwriting existing code
- Executing AI-generated code
- Connecting to external services

### S3: Sandboxed Code Execution
```
INVARIANT: ∀ userCode: execute(userCode) ⊂ SandboxedIframe
```
- User/AI-generated code runs only in sandboxed iframes
- No access to parent window globals
- No access to browser storage
- Limited network access (same-origin only)

### S4: Audit Trail Completeness
```
INVARIANT: ∀ action ∈ AIActions: ∃ entry ∈ AuditLog: records(entry, action)
```
- Every AI action (suggestion, code change, file operation) is logged
- Logs include: timestamp, action type, before/after state, user confirmation status

### S5: Input Sanitization
```
INVARIANT: ∀ input: displayed(input) = sanitize(input)
```
- All user input is sanitized before DOM insertion
- React's built-in XSS protection is used
- No `dangerouslySetInnerHTML` without explicit sanitization

---

## 2. Data Integrity Invariants

### D1: File System Consistency
```
INVARIANT: ∀ file ∈ FileSystem:
  file.parentId ≠ null ⟹ ∃ parent ∈ FileSystem:
    parent.id = file.parentId ∧ file.id ∈ parent.children
```
- Every file (except root) has a valid parent
- Parent's children array always includes the file
- No orphan files exist

### D2: Path Uniqueness
```
INVARIANT: ∀ f1, f2 ∈ FileSystem: f1.path = f2.path ⟹ f1.id = f2.id
```
- No two files can have the same path
- Paths are case-sensitive

### D3: Project State Consistency
```
INVARIANT: ∀ project:
  project.rootId ∈ FileSystem ∧
  getNode(project.rootId).type = 'directory'
```
- Every project has a valid root directory
- Root directory always exists while project exists

### D4: Undo Stack Validity
```
INVARIANT: ∀ action ∈ UndoStack:
  canApply(action.undo) ∧ canApply(action.redo)
```
- Every action in the undo stack can be reversed
- Undo/redo operations are always valid

---

## 3. UI State Invariants

### U1: Single Active File
```
INVARIANT: |{tab ∈ Tabs: tab.isActive}| ≤ 1
```
- At most one tab is active at any time
- Zero active tabs is valid (no file open)

### U2: Tab-File Correspondence
```
INVARIANT: ∀ tab ∈ Tabs: ∃ file ∈ FileSystem: tab.fileId = file.id
```
- Every open tab corresponds to an existing file
- Closing a file removes its tab

### U3: Command Palette Mutual Exclusivity
```
INVARIANT: commandPaletteOpen ⟹ ¬modalOpen ∧ ¬contextMenuOpen
```
- Command palette takes focus exclusively
- Other overlays are dismissed when command palette opens

### U4: Theme Consistency
```
INVARIANT: ∀ component: component.theme = globalTheme
```
- All components reflect the current theme
- Theme changes propagate synchronously

---

## 4. AI Agent Invariants

### A1: Agent Response Boundaries
```
INVARIANT: ∀ response ∈ AIResponses:
  response.actions ⊂ AllowedActions ∧
  response.scope ⊂ currentProject.files
```
- AI can only suggest actions on files in the current project
- AI cannot suggest system-level operations

### A2: Streaming State Consistency
```
INVARIANT: isStreaming ⟹ ∃ request ∈ PendingRequests
```
- Streaming indicator is shown only when a request is pending
- Cancellation clears streaming state

### A3: Context Window Limits
```
INVARIANT: contextSize(request) ≤ MAX_CONTEXT_TOKENS
```
- AI requests never exceed model context limits
- Automatic truncation with summarization if needed

### A4: Rate Limiting
```
INVARIANT: requestCount(window: 1min) ≤ MAX_REQUESTS_PER_MINUTE
```
- Enforced client-side rate limiting
- Debouncing prevents rapid-fire requests

---

## 5. Workflow Invariants

### W1: Node Connectivity
```
INVARIANT: ∀ edge ∈ Edges:
  edge.source ∈ Nodes ∧ edge.target ∈ Nodes
```
- All edges connect existing nodes
- Deleting a node removes its edges

### W2: Trigger Uniqueness
```
INVARIANT: ∀ workflow: |{node ∈ workflow.nodes: node.type = 'trigger'}| ≥ 1
```
- Every workflow has at least one trigger
- Workflow cannot execute without a trigger

### W3: Execution Idempotency
```
INVARIANT: ∀ workflow, input:
  execute(workflow, input) = execute(workflow, input)
```
- Same input produces same output (for deterministic nodes)
- Side effects are logged and reproducible

---

## 6. Integration Invariants

### I1: Permission Scope
```
INVARIANT: ∀ integration, action:
  execute(integration, action) ⟹ hasPermission(integration, action.scope)
```
- Integrations can only perform actions within granted permissions
- Permission checks happen before every action

### I2: Mock vs Real Separation
```
INVARIANT: mode = 'demo' ⟹ ∀ integration: integration.adapter = MockAdapter
```
- Demo mode uses only mock adapters
- Real adapters require secure mode (Phase 3)

---

## 7. Error Handling Invariants

### E1: Error Boundary Coverage
```
INVARIANT: ∀ component ∈ tree(App):
  ∃ errorBoundary ∈ ancestors(component)
```
- Every component is wrapped by an error boundary
- Errors don't crash the entire application

### E2: Graceful Degradation
```
INVARIANT: ∀ failure ∈ NonCriticalFailures:
  appState.after(failure) ∈ ValidStates
```
- Non-critical failures don't corrupt state
- User can continue working after errors

### E3: Error Logging
```
INVARIANT: ∀ error: ∃ log ∈ ErrorLogs: contains(log, error)
```
- All errors are logged for debugging
- Logs include stack traces and context

---

## 8. Performance Invariants

### P1: Debounced Persistence
```
INVARIANT: saveInterval ≥ MIN_SAVE_INTERVAL (500ms)
```
- Auto-save is debounced to prevent excessive writes
- User-initiated saves bypass debouncing

### P2: Lazy Loading
```
INVARIANT: ∀ file: file.content loaded ⟹ file.isOpen ∨ file.isReferenced
```
- File contents are loaded on-demand
- Closed files can be unloaded from memory

### P3: Virtual Scrolling
```
INVARIANT: renderedItems ≤ MAX_VISIBLE_ITEMS + BUFFER
```
- Long lists use virtualization
- DOM nodes are recycled

---

## Invariant Verification

### Compile-Time Checks
- TypeScript strict mode enabled
- ESLint rules enforce patterns
- Type guards for runtime checks

### Runtime Assertions
```typescript
function assert(condition: boolean, message: string): asserts condition {
  if (!condition) {
    const error = new InvariantViolationError(message);
    auditLog.log({ type: 'invariant_violation', error });
    throw error;
  }
}
```

### Testing Requirements
- Unit tests verify individual invariants
- Integration tests verify cross-system invariants
- Property-based tests for file system operations

---

*Document Version: 1.0*
*Last Updated: Phase 0*
