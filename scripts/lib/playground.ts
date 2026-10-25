import * as PlaygroundCli from '@wp-playground/cli';

export interface RunCLIServer {
  serverUrl: string;
  playground: {
    cli(
      argv: string[],
      options?: { env?: Record<string, string> },
    ): Promise<{
      stdout: ReadableStream<Uint8Array>;
      stderr: ReadableStream<Uint8Array>;
      exitCode: Promise<number>;
    }>;
  };
  [Symbol.asyncDispose](): Promise<void>;
}

// 3.1.60's declarations use extensionless ESM exports, which NodeNext cannot resolve.
export const { runCLI } = PlaygroundCli as unknown as {
  runCLI(args: Record<string, unknown>): Promise<RunCLIServer>;
};
