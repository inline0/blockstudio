import { Hero } from './hero';
import { FileStructure } from './file-structure';
import { Features } from './features';
import { PlusSection } from '../plus-section';

export function FullStackPage() {
  return (
    <div className="flex flex-col">
      <Hero />
      <FileStructure />
      <Features />
      <div className="border-t">
        <PlusSection />
      </div>
    </div>
  );
}
