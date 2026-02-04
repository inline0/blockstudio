import { useState, useEffect } from 'react';
import { getCssClasses } from '@/blocks/utils/get-css-classes';

type StyleEntry = {
  type?: string;
  src?: string;
  inline?: string;
};

const cache: {
  [key: string]: string[];
} = {};

export const useGetCssClasses = (
  styles: Record<string, StyleEntry> = {},
  allAssets: string[] = [],
  reload: unknown[] = [],
) => {
  const [classes, setClasses] = useState(new Set<string>());

  useEffect(() => {
    const allClasses = new Set() as Set<string>;

    const fetchClasses = async () => {
      for (const [k, v] of Object.entries(styles)) {
        const assets = new Set<string>(allAssets);

        if (cache[k] && [...assets].includes(k)) {
          cache[k].forEach((cls: string) => allClasses.add(cls));
          continue;
        }

        if (!assets.has(k) || v.type === 'script') continue;

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

        getCssClasses(allClasses, cssContent);

        cache[k] = [...allClasses];
      }

      setClasses(allClasses);
    };

    fetchClasses();
  }, reload);

  return classes;
};
