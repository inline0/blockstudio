export interface ScoreResult {
  name: string;
  score: number;
  details?: string;
}

export type Scorer = (output: string) => ScoreResult;

export interface EvalCase {
  name: string;
  prompt: string;
  scorers: Scorer[];
}

export interface EvalSuite {
  name: string;
  cases: EvalCase[];
}

export interface CaseResult {
  caseIndex: number;
  caseName: string;
  scores: ScoreResult[];
  average: number;
  output: string;
}
