import { CodeBlock } from "onedocs";

interface CodeCardProps {
  code: string;
  lang: string;
}

export async function CodeCard({ code, lang }: CodeCardProps) {
  return (
    <div className="h-full [&_.shiki]:p-1 [&_.shiki]:my-0 [&_.shiki]:bg-fd-secondary [&_.shiki]:border-0 [&_.shiki]:shadow-none">
      <CodeBlock code={code} lang={lang} />
    </div>
  );
}
