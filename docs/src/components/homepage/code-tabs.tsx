import { CodeBlock } from 'onedocs';
import { Tabs, Tab } from 'onedocs/components';

interface CodeTabsItem {
  label: string;
  code: string;
  lang: string;
}

interface CodeTabsProps {
  items: CodeTabsItem[];
}

export async function CodeTabs({ items }: CodeTabsProps) {
  return (
    <Tabs
      items={items.map((i) => i.label)}
      className="my-0 h-full [&>div[role=tablist]]:h-14 [&>div[role=tablist]]:lg:px-6 [&>div[role=tabpanel][data-state=active]]:grid [&>div[role=tabpanel][data-state=active]]:grid-cols-1 [&>div[role=tabpanel][data-state=active]]:h-full [&_.shiki]:p-1"
    >
      {items.map((item) => (
        <Tab key={item.label}>
          <CodeBlock code={item.code} lang={item.lang} />
        </Tab>
      ))}
    </Tabs>
  );
}
