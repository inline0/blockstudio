import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import JSZip from 'jszip';

export const useZip = () => {
  const { setConsole } = useDispatch('blockstudio/editor');

  const zip = async (path: string) => {
    try {
      const response = (await apiFetch({
        path: '/blockstudio/v1/editor/zip/create',
        method: 'POST',
        data: { path },
        parse: false,
      })) as Response;
      const blob = await response.blob();

      if (/\.[^/]+$/.test(path)) {
        const fileName = path.split('/').pop() || 'file';
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        setConsole('File downloaded');
        return;
      }

      const zipContent = await JSZip.loadAsync(blob);
      const blockNames = [];

      for (const fileName in zipContent.files) {
        if (
          Object.prototype.hasOwnProperty.call(zipContent.files, fileName) &&
          fileName.endsWith('block.json')
        ) {
          const fileContent = await zipContent.files[fileName].async('string');
          const json = JSON.parse(fileContent);
          if (json.name) {
            blockNames.push(json.name);
          }
        }
      }

      let downloadFileName = 'blockstudio-export';
      if (blockNames.length > 0) {
        const namesString = blockNames.join(';');
        downloadFileName += `__${namesString}`;
      }
      downloadFileName += '.zip';

      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.style.display = 'none';
      a.href = url;
      a.download = downloadFileName;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      setConsole('Zip created with block names');
    } catch (err) {
      setConsole({ text: err.message, type: 'error' });
    }
  };

  return { zip };
};
