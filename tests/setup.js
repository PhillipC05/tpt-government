/**
 * Jest setup file for frontend testing
 *
 * This file is loaded before running Jest tests to set up the testing environment.
 */

// Import jest-dom for additional matchers
import '@testing-library/jest-dom';

// Mock localStorage
const localStorageMock = {
  getItem: jest.fn(),
  setItem: jest.fn(),
  removeItem: jest.fn(),
  clear: jest.fn(),
};

global.localStorage = localStorageMock;

// Mock sessionStorage
const sessionStorageMock = {
  getItem: jest.fn(),
  setItem: jest.fn(),
  removeItem: jest.fn(),
  clear: jest.fn(),
};

global.sessionStorage = sessionStorageMock;

// Mock navigator.serviceWorker
Object.defineProperty(navigator, 'serviceWorker', {
  value: {
    register: jest.fn().mockResolvedValue({
      scope: '/',
      update: jest.fn(),
      unregister: jest.fn(),
    }),
    ready: Promise.resolve({
      active: { state: 'activated' },
      waiting: null,
      installing: null,
    }),
    getRegistrations: jest.fn().mockResolvedValue([]),
    getRegistration: jest.fn().mockResolvedValue(null),
  },
  writable: true,
});

// Mock Notification API
global.Notification = {
  requestPermission: jest.fn().mockResolvedValue('granted'),
  permission: 'granted',
};

// Mock fetch API
global.fetch = jest.fn();

// Mock IntersectionObserver
global.IntersectionObserver = class IntersectionObserver {
  constructor() {}
  observe() {}
  disconnect() {}
  unobserve() {}
};

// Mock ResizeObserver
global.ResizeObserver = class ResizeObserver {
  constructor() {}
  observe() {}
  disconnect() {}
  unobserve() {}
};

// Mock matchMedia
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: jest.fn().mockImplementation(query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: jest.fn(), // deprecated
    removeListener: jest.fn(), // deprecated
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
    dispatchEvent: jest.fn(),
  })),
});

// Mock crypto.getRandomValues
Object.defineProperty(window, 'crypto', {
  value: {
    getRandomValues: jest.fn().mockImplementation((array) => {
      for (let i = 0; i < array.length; i++) {
        array[i] = Math.floor(Math.random() * 256);
      }
      return array;
    }),
  },
});

// Mock URL.createObjectURL
global.URL.createObjectURL = jest.fn(() => 'mock-object-url');
global.URL.revokeObjectURL = jest.fn();

// Mock Blob
global.Blob = class Blob {
  constructor(content, options) {
    this.content = content;
    this.options = options;
  }
};

// Mock File
global.File = class File extends Blob {
  constructor(bits, filename, options = {}) {
    super(bits, options);
    this.name = filename;
    this.lastModified = options.lastModified || Date.now();
    this.lastModifiedDate = new Date(this.lastModified);
  }
};

// Mock FileReader
global.FileReader = class FileReader {
  constructor() {
    this.onload = null;
    this.onerror = null;
    this.result = null;
  }

  readAsDataURL(blob) {
    // Simulate async behavior
    setTimeout(() => {
      this.result = 'data:image/png;base64,mockData';
      if (this.onload) {
        this.onload({ target: { result: this.result } });
      }
    }, 0);
  }

  readAsText(blob) {
    setTimeout(() => {
      this.result = 'mock text content';
      if (this.onload) {
        this.onload({ target: { result: this.result } });
      }
    }, 0);
  }
};

// Mock Image
global.Image = class Image {
  constructor() {
    this.onload = null;
    this.onerror = null;
    this.src = '';
    setTimeout(() => {
      if (this.onload) {
        this.onload();
      }
    }, 0);
  }
};

// Mock console methods to avoid noise in tests
const originalConsoleError = console.error;
const originalConsoleWarn = console.warn;

beforeAll(() => {
  console.error = jest.fn();
  console.warn = jest.fn();
});

afterAll(() => {
  console.error = originalConsoleError;
  console.warn = originalConsoleWarn;
});

// Mock axios
jest.mock('axios', () => ({
  get: jest.fn(),
  post: jest.fn(),
  put: jest.fn(),
  delete: jest.fn(),
  patch: jest.fn(),
  create: jest.fn(() => ({
    get: jest.fn(),
    post: jest.fn(),
    put: jest.fn(),
    delete: jest.fn(),
    patch: jest.fn(),
    interceptors: {
      request: { use: jest.fn() },
      response: { use: jest.fn() },
    },
  })),
  defaults: {
    baseURL: '',
    headers: {},
  },
}));

// Mock lodash
jest.mock('lodash', () => ({
  debounce: jest.fn((fn) => fn),
  throttle: jest.fn((fn) => fn),
  cloneDeep: jest.fn((obj) => JSON.parse(JSON.stringify(obj))),
  isEqual: jest.fn((a, b) => JSON.stringify(a) === JSON.stringify(b)),
  uniq: jest.fn((arr) => [...new Set(arr)]),
  uniqBy: jest.fn((arr, fn) => {
    const seen = new Set();
    return arr.filter(item => {
      const key = typeof fn === 'function' ? fn(item) : item[fn];
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    });
  }),
}));

// Mock uuid
jest.mock('uuid', () => ({
  v4: jest.fn(() => 'mock-uuid-1234'),
  v1: jest.fn(() => 'mock-uuid-v1'),
}));

// Mock date-fns
jest.mock('date-fns', () => ({
  format: jest.fn((date, format) => `formatted-${date}-${format}`),
  parseISO: jest.fn((dateString) => new Date(dateString)),
  isValid: jest.fn(() => true),
  addDays: jest.fn((date, days) => new Date(date.getTime() + days * 24 * 60 * 60 * 1000)),
  subDays: jest.fn((date, days) => new Date(date.getTime() - days * 24 * 60 * 60 * 1000)),
  differenceInDays: jest.fn((date1, date2) => 5),
  startOfDay: jest.fn((date) => new Date(date.getFullYear(), date.getMonth(), date.getDate())),
  endOfDay: jest.fn((date) => new Date(date.getFullYear(), date.getMonth(), date.getDate(), 23, 59, 59, 999)),
}));

// Clean up after each test
afterEach(() => {
  jest.clearAllMocks();
  localStorage.clear();
  sessionStorage.clear();
});

// Helper function to create mock DOM elements
global.createMockElement = (tagName = 'div') => {
  const element = document.createElement(tagName);
  element.getBoundingClientRect = jest.fn(() => ({
    width: 100,
    height: 100,
    top: 0,
    left: 0,
    bottom: 100,
    right: 100,
  }));
  return element;
};

// Helper function to wait for async operations
global.waitForAsync = () => new Promise(resolve => setImmediate(resolve));

// Helper function to flush promises
global.flushPromises = () => new Promise(resolve => setImmediate(resolve));
