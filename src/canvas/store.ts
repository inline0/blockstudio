import { createReduxStore, register } from '@wordpress/data';

const STORAGE_KEY = 'blockstudio-canvas-settings';

export type CanvasView = 'pages' | 'blocks';

interface CanvasState {
  liveMode: boolean;
  pollInterval: number;
  fingerprint: string;
  view: CanvasView;
}

interface CanvasAction {
  type: string;
  value: unknown;
}

function loadSettings(): Partial<CanvasState> {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored) return JSON.parse(stored);
  } catch {
    // Ignore parse errors.
  }
  return {};
}

function saveSettings(state: CanvasState): void {
  try {
    localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({
        liveMode: state.liveMode,
        pollInterval: state.pollInterval,
        view: state.view,
      }),
    );
  } catch {
    // Ignore storage errors.
  }
}

const persisted = loadSettings();

const DEFAULT_STATE: CanvasState = {
  liveMode: persisted.liveMode ?? false,
  pollInterval: persisted.pollInterval ?? 2,
  fingerprint: '',
  view: (persisted as any).view === 'blocks' ? 'blocks' : 'pages',
};

export const STORE_NAME = 'blockstudio/canvas';

export const store = createReduxStore(STORE_NAME, {
  reducer(state: CanvasState = DEFAULT_STATE, action: CanvasAction) {
    let next = state;

    switch (action.type) {
      case 'SET_LIVE_MODE':
        next = { ...state, liveMode: action.value as boolean };
        break;
      case 'SET_POLL_INTERVAL':
        next = { ...state, pollInterval: action.value as number };
        break;
      case 'SET_VIEW':
        next = { ...state, view: action.value as CanvasView };
        break;
      case 'SET_FINGERPRINT':
        return { ...state, fingerprint: action.value as string };
      default:
        return state;
    }

    saveSettings(next);
    return next;
  },

  actions: {
    setLiveMode(value: boolean) {
      return { type: 'SET_LIVE_MODE' as const, value };
    },
    setPollInterval(value: number) {
      return { type: 'SET_POLL_INTERVAL' as const, value };
    },
    setView(value: CanvasView) {
      return { type: 'SET_VIEW' as const, value };
    },
    setFingerprint(value: string) {
      return { type: 'SET_FINGERPRINT' as const, value };
    },
  },

  selectors: {
    isLiveMode(state: CanvasState) {
      return state.liveMode;
    },
    getPollInterval(state: CanvasState) {
      return state.pollInterval;
    },
    getView(state: CanvasState) {
      return state.view;
    },
    getFingerprint(state: CanvasState) {
      return state.fingerprint;
    },
  },
});

register(store);
