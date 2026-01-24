import { useEffect } from 'react';
import { useProjectStore, useUIStore } from '@/stores';
import { WelcomeScreen } from '@/components/welcome/WelcomeScreen';
import { WorkspaceLayout } from '@/components/workspace/WorkspaceLayout';

function App() {
  const { currentProject } = useProjectStore();
  const { openCommandPalette, resolvedTheme } = useUIStore();

  // Global keyboard shortcuts
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Cmd/Ctrl + K for command palette
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        openCommandPalette();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [openCommandPalette]);

  // Apply theme class to document
  useEffect(() => {
    document.documentElement.classList.remove('light', 'dark');
    document.documentElement.classList.add(resolvedTheme);
  }, [resolvedTheme]);

  // Show workspace if project is open, otherwise show welcome screen
  if (currentProject) {
    return <WorkspaceLayout />;
  }

  return <WelcomeScreen />;
}

export default App;
