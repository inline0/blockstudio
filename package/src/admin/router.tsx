import { createHashRouter } from 'react-router-dom';
import { Settings } from '@/admin/components/Settings';
import Admin from '@/admin/index';
import { Editor } from '@/editor/index';

export const router = createHashRouter([
  {
    path: '/',
    element: <Admin />,
    children: [
      {
        path: 'editor',
        element: <Editor />,
        children: [
          {
            path: ':id',
            element: <Editor />,
          },
        ],
      },
      {
        path: 'settings',
        element: <Settings />,
      },
    ],
  },
  {
    path: '*',
    element: <Admin />,
    children: [
      {
        path: '*',
        element: <Settings />,
      },
    ],
  },
]);
