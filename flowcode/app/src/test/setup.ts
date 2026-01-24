import '@testing-library/jest-dom';

// Mock IndexedDB
const indexedDB = {
  open: vi.fn(),
};

Object.defineProperty(window, 'indexedDB', {
  value: indexedDB,
  writable: true,
});

// Mock crypto.randomUUID
Object.defineProperty(crypto, 'randomUUID', {
  value: () => `test-${Math.random().toString(36).substr(2, 9)}`,
});

// Mock matchMedia
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: vi.fn().mockImplementation(query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  })),
});

// Mock ResizeObserver
window.ResizeObserver = vi.fn().mockImplementation(() => ({
  observe: vi.fn(),
  unobserve: vi.fn(),
  disconnect: vi.fn(),
}));

// Mock IntersectionObserver
window.IntersectionObserver = vi.fn().mockImplementation(() => ({
  observe: vi.fn(),
  unobserve: vi.fn(),
  disconnect: vi.fn(),
}));
