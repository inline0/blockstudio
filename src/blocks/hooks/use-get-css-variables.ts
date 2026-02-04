import { useState, useEffect } from 'react';
import { getCssVariables } from '@/blocks/utils/get-css-variables';

type StyleEntry = {
  type?: string;
  src?: string;
  inline?: string;
};

const cache: {
  [key: string]: string[];
} = {};

export const useGetCssVariables = (
  styles: Record<string, StyleEntry> = {},
  allAssets: string[] = [],
  reload: unknown[] = [],
) => {
  const [variables, setVariables] = useState(new Set<string>());

  useEffect(() => {
    const allVariables = new Set() as Set<string>;

    const fetchVariables = async () => {
      for (const [k, v] of Object.entries(styles)) {
        const variables = new Set<string>(allAssets);

        if (cache[k] && [...variables].includes(k)) {
          cache[k].forEach((cls: string) => allVariables.add(cls));
          continue;
        }

        if (!variables.has(k) || v.type === 'script') continue;

        let cssContent = '';
        if (v?.src) {
          try {
            const response = await fetch(v.src);
            cssContent = await response.text();
          } catch (error) {
            console.error('Error fetching CSS content:', error);
          }
        } else if (v?.inline) {
          cssContent = v.inline;
        }

        getCssVariables(allVariables, cssContent);

        cache[k] = [...allVariables];
      }

      setVariables(allVariables);
    };

    fetchVariables();
  }, reload);

  return [...variables] as string[];
};
