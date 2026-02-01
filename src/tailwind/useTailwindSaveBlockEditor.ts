import apiFetch from '@wordpress/api-fetch';
import { useTailwindCompile } from '@/tailwind/useTailwindCompile';

export const useTailwindSaveBlockEditor = () => {
  const compile = useTailwindCompile({ enabled: true });

  return async () => {
    const tailwind = await compile(document.body.innerHTML);
    await apiFetch({
      path: '/blockstudio/v1/editor/tailwind/save',
      method: 'POST',
      data: {
        content: tailwind,
        id: window.blockstudioAdmin.postId,
      },
    });
  };
};
