import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Merge Tailwind CSS classes with proper precedence
 */
export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

/**
 * Generate a unique ID
 */
export function generateId(): string {
  return crypto.randomUUID();
}

/**
 * Debounce a function
 */
export function debounce<T extends (...args: Parameters<T>) => void>(
  fn: T,
  delay: number
): (...args: Parameters<T>) => void {
  let timeoutId: ReturnType<typeof setTimeout>;
  return (...args: Parameters<T>) => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => fn(...args), delay);
  };
}

/**
 * Throttle a function
 */
export function throttle<T extends (...args: Parameters<T>) => void>(
  fn: T,
  limit: number
): (...args: Parameters<T>) => void {
  let inThrottle = false;
  return (...args: Parameters<T>) => {
    if (!inThrottle) {
      fn(...args);
      inThrottle = true;
      setTimeout(() => (inThrottle = false), limit);
    }
  };
}

/**
 * Get file extension from path
 */
export function getFileExtension(path: string): string {
  const parts = path.split('.');
  return parts.length > 1 ? parts[parts.length - 1] : '';
}

/**
 * Get language from file extension
 */
export function getLanguageFromExtension(ext: string): string {
  const languageMap: Record<string, string> = {
    ts: 'typescript',
    tsx: 'typescript',
    js: 'javascript',
    jsx: 'javascript',
    json: 'json',
    md: 'markdown',
    css: 'css',
    scss: 'scss',
    html: 'html',
    py: 'python',
    rs: 'rust',
    go: 'go',
    java: 'java',
    yaml: 'yaml',
    yml: 'yaml',
    toml: 'toml',
    sql: 'sql',
    sh: 'shell',
    bash: 'shell',
    zsh: 'shell',
  };
  return languageMap[ext.toLowerCase()] || 'plaintext';
}

/**
 * Get Monaco language ID from file path
 */
export function getMonacoLanguage(path: string): string {
  const ext = getFileExtension(path);
  return getLanguageFromExtension(ext);
}

/**
 * Get file icon name based on file type
 */
export function getFileIcon(name: string, isDirectory: boolean): string {
  if (isDirectory) {
    return 'folder';
  }

  const ext = getFileExtension(name).toLowerCase();
  const iconMap: Record<string, string> = {
    ts: 'file-type-typescript',
    tsx: 'file-type-typescript',
    js: 'file-type-javascript',
    jsx: 'file-type-javascript',
    json: 'file-type-json',
    md: 'file-type-markdown',
    css: 'file-type-css',
    html: 'file-type-html',
    py: 'file-type-python',
    rs: 'file-type-rust',
    go: 'file-type-go',
  };

  return iconMap[ext] || 'file';
}

/**
 * Format bytes to human readable string
 */
export function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

/**
 * Format timestamp to relative time
 */
export function formatRelativeTime(timestamp: number): string {
  const now = Date.now();
  const diff = now - timestamp;

  const seconds = Math.floor(diff / 1000);
  const minutes = Math.floor(seconds / 60);
  const hours = Math.floor(minutes / 60);
  const days = Math.floor(hours / 24);

  if (days > 0) return `${days}d ago`;
  if (hours > 0) return `${hours}h ago`;
  if (minutes > 0) return `${minutes}m ago`;
  return 'just now';
}

/**
 * Format timestamp to date string
 */
export function formatDate(timestamp: number): string {
  return new Date(timestamp).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

/**
 * Sanitize user input for prompt injection protection
 */
export function sanitizeForPrompt(input: string): string {
  return input
    .replace(/<\/?system>/gi, '[filtered]')
    .replace(/ignore previous instructions/gi, '[filtered]')
    .replace(/disregard.*rules/gi, '[filtered]')
    .replace(/pretend you are/gi, '[filtered]')
    .replace(/you are now/gi, '[filtered]');
}

/**
 * Validate Anthropic API key format
 */
export function isValidApiKeyFormat(key: string): boolean {
  return /^sk-ant-[a-zA-Z0-9-]+$/.test(key);
}

/**
 * Assert condition is true (for invariant checking)
 */
export function assert(condition: boolean, message: string): asserts condition {
  if (!condition) {
    throw new Error(`Invariant violation: ${message}`);
  }
}

/**
 * Sleep for a given number of milliseconds
 */
export function sleep(ms: number): Promise<void> {
  return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Get the parent path from a file path
 */
export function getParentPath(path: string): string {
  const parts = path.split('/').filter(Boolean);
  parts.pop();
  return '/' + parts.join('/');
}

/**
 * Get the file name from a path
 */
export function getFileName(path: string): string {
  const parts = path.split('/');
  return parts[parts.length - 1] || '';
}

/**
 * Join path segments
 */
export function joinPath(...segments: string[]): string {
  return '/' + segments
    .map(s => s.replace(/^\/+|\/+$/g, ''))
    .filter(Boolean)
    .join('/');
}
