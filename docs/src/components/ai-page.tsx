import { AgentTools } from './ai/agent-tools';
import { DetailCards } from './ai/detail-cards';
import { AiHero } from './ai/hero';
import { LlmContext } from './ai/llm-context';
import { TokenComparison } from './ai/token-comparison';
import { WhyFilesystem } from './ai/why-filesystem';
import { PlusSection } from './plus-section';

export async function AiPage() {
  return (
    <div className="flex flex-col">
      <AiHero />
      <WhyFilesystem />
      <AgentTools />
      <TokenComparison />
      <LlmContext />
      <DetailCards />
      <div className="border-t">
        <PlusSection />
      </div>
    </div>
  );
}
