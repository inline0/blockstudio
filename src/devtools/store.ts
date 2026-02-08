type Phase = 'idle' | 'holding' | 'active' | 'copying' | 'justCopied';

interface Bounds {
  x: number;
  y: number;
  width: number;
  height: number;
  borderRadius: number;
}

interface GrabbedBox {
  id: string;
  bounds: Bounds;
  createdAt: number;
}

interface State {
  phase: Phase;
  holdStartedAt: number;
  pointer: { x: number; y: number };
  detectedElement: Element | null;
  detectedPath: string | null;
  detectedTargetElement: Element | null;
  detectedTagName: string | null;
  elementBounds: Bounds | null;
  animatedBounds: Bounds | null;
  grabbedBoxes: GrabbedBox[];
}

const OFFSCREEN = -10000;

const state: State = {
  phase: 'idle',
  holdStartedAt: 0,
  pointer: { x: OFFSCREEN, y: OFFSCREEN },
  detectedElement: null,
  detectedPath: null,
  detectedTargetElement: null,
  detectedTagName: null,
  elementBounds: null,
  animatedBounds: null,
  grabbedBoxes: [],
};

export { state, OFFSCREEN };
export type { State, Phase, Bounds, GrabbedBox };
