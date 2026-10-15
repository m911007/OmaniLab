import '@testing-library/jest-dom/vitest';

// jsdom لا يوفر ResizeObserver الذي تحتاجه ResponsiveContainer في recharts.
class ResizeObserverStub {
  observe(): void {}
  unobserve(): void {}
  disconnect(): void {}
}
globalThis.ResizeObserver ??= ResizeObserverStub;
