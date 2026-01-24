import { TabBar } from './TabBar';
import { MonacoEditor } from './MonacoEditor';

export function EditorPane() {
  return (
    <div className="flex h-full flex-col">
      <TabBar />
      <div className="flex-1 min-h-0">
        <MonacoEditor className="h-full" />
      </div>
    </div>
  );
}
