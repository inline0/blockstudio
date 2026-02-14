import { CodeBlock } from 'onedocs';

interface CodeCardProps {
  code: string;
  lang: string;
}

export async function CodeCard({ code, lang }: CodeCardProps) {
  return (
    <div className="h-full [&_.shiki]:p-1 [&_.shiki]:my-0 [&_.shiki]:bg-fd-secondary [&_.shiki]:border-0 [&_.shiki]:shadow-none [&_figure]:h-full [&_figure>div[role=region]]:h-full [&_figure>div[role=region]]:max-h-none [&_pre]:h-full">
      <CodeBlock code={code} lang={lang} />
    </div>
  );
}
