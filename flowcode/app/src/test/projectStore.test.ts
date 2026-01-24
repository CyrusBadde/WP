import { describe, it, expect, beforeEach } from 'vitest';
import { useProjectStore } from '@/stores/projectStore';

// Helper to get fresh state
const getState = () => useProjectStore.getState();

describe('projectStore', () => {
  beforeEach(() => {
    // Reset store state completely
    useProjectStore.setState({
      projects: [],
      currentProject: null,
      files: new Map(),
      filesByPath: new Map(),
      tabs: [],
      activeTabId: null,
    });
  });

  describe('createProject', () => {
    it('creates a new project with template files', () => {
      const project = getState().createProject('Test Project', 'react');

      expect(project.name).toBe('Test Project');
      expect(project.template).toBe('react');
      expect(getState().currentProject?.id).toBe(project.id);
      expect(getState().projects).toHaveLength(1);
    });

    it('creates appropriate files for React template', () => {
      getState().createProject('React App', 'react');

      expect(getState().getNode('/src')).toBeTruthy();
      expect(getState().getNode('/src')?.type).toBe('directory');
      expect(getState().getNode('/src/App.tsx')).toBeTruthy();
      expect(getState().getNode('/package.json')).toBeTruthy();
    });

    it('creates appropriate files for blank template', () => {
      getState().createProject('Blank', 'blank');

      expect(getState().getNode('/README.md')).toBeTruthy();
    });
  });

  describe('file operations', () => {
    beforeEach(() => {
      getState().createProject('Test', 'blank');
    });

    it('creates a new file', () => {
      const file = getState().createFile('/', 'test.ts', 'const x = 1;');

      expect(file.name).toBe('test.ts');
      expect(file.path).toBe('/test.ts');
      expect(file.content).toBe('const x = 1;');
      expect(file.language).toBe('typescript');
    });

    it('creates a new directory', () => {
      const dir = getState().createDirectory('/', 'components');

      expect(dir.name).toBe('components');
      expect(dir.path).toBe('/components');
      expect(dir.type).toBe('directory');
    });

    it('reads file content', () => {
      getState().createFile('/', 'test.ts', 'hello world');

      expect(getState().readFile('/test.ts')).toBe('hello world');
    });

    it('updates file content', () => {
      getState().createFile('/', 'test.ts', 'original');
      getState().updateFile('/test.ts', 'updated');

      expect(getState().readFile('/test.ts')).toBe('updated');
    });

    it('deletes a file', () => {
      getState().createFile('/', 'test.ts', 'content');
      getState().deleteNode('/test.ts');

      expect(getState().getNode('/test.ts')).toBeNull();
    });

    it('renames a file', () => {
      getState().createFile('/', 'old.ts', 'content');
      getState().renameNode('/old.ts', 'new.ts');

      expect(getState().getNode('/old.ts')).toBeNull();
      expect(getState().getNode('/new.ts')).toBeTruthy();
      expect(getState().readFile('/new.ts')).toBe('content');
    });
  });

  describe('tab management', () => {
    beforeEach(() => {
      getState().createProject('Test', 'react');
    });

    it('opens a tab for a file', () => {
      const file = getState().getNode('/src/App.tsx');
      expect(file).toBeTruthy();

      if (file) {
        getState().openTab(file.id);
        expect(getState().tabs).toHaveLength(1);
        expect(getState().activeTabId).toBe(getState().tabs[0].id);
      }
    });

    it('does not duplicate tabs for the same file', () => {
      const file = getState().getNode('/src/App.tsx');

      if (file) {
        getState().openTab(file.id);
        getState().openTab(file.id);
        expect(getState().tabs).toHaveLength(1);
      }
    });

    it('closes a tab', () => {
      const file = getState().getNode('/src/App.tsx');

      if (file) {
        getState().openTab(file.id);
        const tabId = getState().tabs[0].id;
        getState().closeTab(tabId);
        expect(getState().tabs).toHaveLength(0);
        expect(getState().activeTabId).toBeNull();
      }
    });

    it('activates adjacent tab when closing current', () => {
      const file1 = getState().getNode('/src/App.tsx');
      getState().createFile('/src', 'Other.tsx', 'content');
      const file2 = getState().getNode('/src/Other.tsx');

      if (file1 && file2) {
        getState().openTab(file1.id);
        getState().openTab(file2.id);

        const activeTabId = getState().activeTabId;
        getState().closeTab(activeTabId!);

        expect(getState().tabs).toHaveLength(1);
        expect(getState().activeTabId).not.toBeNull();
      }
    });
  });

  describe('getChildren', () => {
    it('returns sorted children (directories first)', () => {
      getState().createProject('Test', 'blank');

      getState().createFile('/src', 'z-file.ts', '');
      getState().createFile('/src', 'a-file.ts', '');
      getState().createDirectory('/src', 'z-dir');
      getState().createDirectory('/src', 'a-dir');

      const children = getState().getChildren('/src');
      expect(children[0].name).toBe('a-dir');
      expect(children[1].name).toBe('z-dir');
      expect(children[2].name).toBe('a-file.ts');
      expect(children[3].name).toBe('z-file.ts');
    });
  });
});
