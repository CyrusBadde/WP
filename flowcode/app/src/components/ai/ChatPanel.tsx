import { useState, useCallback, useRef, useEffect } from 'react';
import {
  Send,
  Loader2,
  Bot,
  User,
  Settings,
  Sparkles,
  Bug,
  FileCode,
  BookOpen,
  Zap,
  Shield,
  X,
  Check,
  Copy,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useAIStore, useProjectStore } from '@/stores';
import { aiAdapter, mockAIAdapter } from '@/services/aiAdapter';
import type { AgentType, ChatMessage } from '@/types';

const agentIcons: Record<AgentType, typeof Bot> = {
  orchestrator: Sparkles,
  coding: FileCode,
  debugging: Bug,
  testing: Shield,
  documentation: BookOpen,
  optimization: Zap,
  review: Shield,
};

const agentLabels: Record<AgentType, string> = {
  orchestrator: 'Assistant',
  coding: 'Coding Agent',
  debugging: 'Debug Agent',
  testing: 'Test Agent',
  documentation: 'Docs Agent',
  optimization: 'Optimize Agent',
  review: 'Review Agent',
};

interface MessageBubbleProps {
  message: ChatMessage;
}

function MessageBubble({ message }: MessageBubbleProps) {
  const [copied, setCopied] = useState(false);
  const isUser = message.role === 'user';
  const AgentIcon = message.agent ? agentIcons[message.agent] : Bot;

  const handleCopy = useCallback(() => {
    navigator.clipboard.writeText(message.content);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }, [message.content]);

  return (
    <div className={cn('flex gap-3 py-4', isUser ? 'flex-row-reverse' : 'flex-row')}>
      <div
        className={cn(
          'flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
          isUser ? 'bg-primary text-primary-foreground' : 'bg-muted'
        )}
      >
        {isUser ? <User className="h-4 w-4" /> : <AgentIcon className="h-4 w-4" />}
      </div>

      <div className={cn('flex flex-col gap-1 max-w-[80%]', isUser && 'items-end')}>
        <div className="flex items-center gap-2">
          <span className="text-xs text-muted-foreground">
            {isUser ? 'You' : message.agent ? agentLabels[message.agent] : 'Assistant'}
          </span>
          <span className="text-xs text-muted-foreground">
            {new Date(message.timestamp).toLocaleTimeString()}
          </span>
        </div>

        <div
          className={cn(
            'rounded-lg px-4 py-2 text-sm',
            isUser ? 'bg-primary text-primary-foreground' : 'bg-muted'
          )}
        >
          <div className="whitespace-pre-wrap break-words">{message.content}</div>
        </div>

        {!isUser && message.content && (
          <button
            onClick={handleCopy}
            className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
          >
            {copied ? <Check className="h-3 w-3" /> : <Copy className="h-3 w-3" />}
            {copied ? 'Copied' : 'Copy'}
          </button>
        )}
      </div>
    </div>
  );
}

interface QuickActionProps {
  icon: typeof Bot;
  label: string;
  onClick: () => void;
}

function QuickAction({ icon: Icon, label, onClick }: QuickActionProps) {
  return (
    <button
      onClick={onClick}
      className="flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm hover:bg-accent transition-colors"
    >
      <Icon className="h-4 w-4" />
      {label}
    </button>
  );
}

export function ChatPanel() {
  const [input, setInput] = useState('');
  const [showApiKeyInput, setShowApiKeyInput] = useState(false);
  const [apiKeyInput, setApiKeyInput] = useState('');
  const scrollRef = useRef<HTMLDivElement>(null);

  const {
    messages,
    isStreaming,
    hasApiKey,
    currentAgent,
    sendMessage,
    appendStreamChunk,
    finishStreaming,
    cancelStreaming,
    setApiKey,
  } = useAIStore();

  const { currentProject, getActiveFile, files } = useProjectStore();

  // Auto-scroll to bottom when messages change
  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [messages]);

  const handleSend = useCallback(async () => {
    if (!input.trim() || isStreaming) return;

    const userInput = input;
    setInput('');

    // Build context from current project
    const activeFile = getActiveFile();
    const contextFiles = activeFile
      ? [
          {
            path: activeFile.path,
            content: activeFile.content || '',
            language: activeFile.language || 'plaintext',
          },
        ]
      : [];

    sendMessage(userInput);

    // Use mock adapter if no API key
    const adapter = hasApiKey ? aiAdapter : mockAIAdapter;

    try {
      for await (const chunk of adapter.complete({
        messages: [
          ...messages.map((m) => ({ role: m.role as 'user' | 'assistant', content: m.content })),
          { role: 'user', content: userInput },
        ],
        context: {
          files: contextFiles,
          recentDiffs: [],
          projectInfo: {
            name: currentProject?.name || 'Unknown',
            template: currentProject?.template || 'blank',
            fileCount: files.size,
          },
        },
        agent: currentAgent,
      })) {
        appendStreamChunk(chunk);
      }
    } catch (error) {
      appendStreamChunk({
        type: 'error',
        error: error instanceof Error ? error.message : 'Unknown error',
      });
    }

    finishStreaming();
  }, [
    input,
    isStreaming,
    hasApiKey,
    messages,
    currentAgent,
    currentProject,
    files.size,
    getActiveFile,
    sendMessage,
    appendStreamChunk,
    finishStreaming,
  ]);

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSend();
      }
    },
    [handleSend]
  );

  const handleSetApiKey = useCallback(() => {
    if (apiKeyInput.trim()) {
      aiAdapter.setApiKey(apiKeyInput.trim());
      setApiKey(apiKeyInput.trim());
      setShowApiKeyInput(false);
      setApiKeyInput('');
    }
  }, [apiKeyInput, setApiKey]);

  const quickActions = [
    { icon: Bug, label: 'Fix bug', prompt: 'Help me fix this bug: ' },
    { icon: Sparkles, label: 'Add feature', prompt: 'Add a feature that ' },
    { icon: Zap, label: 'Optimize', prompt: 'Optimize this code for better performance' },
    { icon: BookOpen, label: 'Document', prompt: 'Add documentation to this code' },
  ];

  return (
    <div className="flex h-full flex-col">
      {/* Header */}
      <div className="flex items-center justify-between border-b border-border px-4 py-3">
        <div className="flex items-center gap-2">
          <Bot className="h-5 w-5 text-primary" />
          <span className="font-medium">AI Assistant</span>
        </div>
        <div className="flex items-center gap-2">
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8"
            onClick={() => setShowApiKeyInput(!showApiKeyInput)}
            title="API Settings"
          >
            <Settings className="h-4 w-4" />
          </Button>
        </div>
      </div>

      {/* API Key Input */}
      {showApiKeyInput && (
        <div className="border-b border-border bg-muted/50 p-3">
          <div className="flex items-center gap-2">
            <Input
              type="password"
              value={apiKeyInput}
              onChange={(e) => setApiKeyInput(e.target.value)}
              placeholder="Enter Anthropic API key (sk-ant-...)"
              className="flex-1"
            />
            <Button onClick={handleSetApiKey} size="sm">
              Save
            </Button>
            <Button
              variant="ghost"
              size="icon"
              className="h-8 w-8"
              onClick={() => setShowApiKeyInput(false)}
            >
              <X className="h-4 w-4" />
            </Button>
          </div>
          <p className="mt-2 text-xs text-muted-foreground">
            {hasApiKey
              ? 'API key is set. Key is stored in memory only and cleared on page close.'
              : 'No API key set. Using demo mode with simulated responses.'}
          </p>
        </div>
      )}

      {/* Messages */}
      <ScrollArea className="flex-1 px-4" ref={scrollRef}>
        {messages.length === 0 ? (
          <div className="flex h-full flex-col items-center justify-center py-8">
            <Bot className="h-12 w-12 text-muted-foreground mb-4" />
            <h3 className="text-lg font-medium mb-2">How can I help you today?</h3>
            <p className="text-sm text-muted-foreground text-center mb-6 max-w-sm">
              I can help you write code, debug issues, optimize performance, and more.
            </p>

            {/* Quick Actions */}
            <div className="flex flex-wrap justify-center gap-2">
              {quickActions.map((action) => (
                <QuickAction
                  key={action.label}
                  icon={action.icon}
                  label={action.label}
                  onClick={() => setInput(action.prompt)}
                />
              ))}
            </div>
          </div>
        ) : (
          <div className="py-4">
            {messages.map((message) => (
              <MessageBubble key={message.id} message={message} />
            ))}

            {isStreaming && (
              <div className="flex items-center gap-2 py-4 text-muted-foreground">
                <Loader2 className="h-4 w-4 animate-spin" />
                <span className="text-sm">Thinking...</span>
                <button
                  onClick={cancelStreaming}
                  className="ml-auto text-xs hover:text-foreground"
                >
                  Cancel
                </button>
              </div>
            )}
          </div>
        )}
      </ScrollArea>

      {/* Input */}
      <div className="border-t border-border p-4">
        <div className="flex items-center gap-2">
          <Input
            value={input}
            onChange={(e) => setInput(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="Ask me anything..."
            disabled={isStreaming}
            className="flex-1"
          />
          <Button
            onClick={handleSend}
            disabled={!input.trim() || isStreaming}
            size="icon"
            className="h-9 w-9"
          >
            {isStreaming ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <Send className="h-4 w-4" />
            )}
          </Button>
        </div>

        {!hasApiKey && (
          <p className="mt-2 text-xs text-yellow-600 dark:text-yellow-500">
            Demo mode: Responses are simulated. Add API key for real AI assistance.
          </p>
        )}
      </div>
    </div>
  );
}
