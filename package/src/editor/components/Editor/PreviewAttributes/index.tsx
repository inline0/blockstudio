import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { debounce } from 'lodash-es';
import { ErrorBoundary } from 'react-error-boundary';
import { colors } from '@/admin/const/colors';
import { Error } from '@/components/Error';
import { Configurator } from '@/configurator/index';
import { selectors } from '@/editor/store/selectors';
import { BlockstudioBlock } from '@/type/types';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const PreviewAttributes = () => {
  const [isLoading, setIsLoading] = useState(true);
  const [value, setValue] = useState<BlockstudioBlock>(null);
  const [innerPath, setInnerPath] = useState('');
  const files = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getFiles(),
    [],
  );
  const filesChanged = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getFilesChanged(),
    [],
  );
  const path = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getPath(),
    [],
  );

  useEffect(() => {
    const fetchAttributes = debounce(() => {
      if (innerPath !== path) {
        setIsLoading(true);
      }

      try {
        const data = filesChanged?.[path]
          ? JSON.parse(filesChanged[path])
          : JSON.parse(files[path].value);

        apiFetch({
          path: '/blockstudio/v1/attributes/build',
          method: 'POST',
          data: data?.blockstudio?.attributes || [],
        })
          .then((attributes) => {
            setValue(null);
            const val = {
              ...data,
              attributes,
            };
            setValue({ ...val });
          })
          .finally(() => {
            setIsLoading(false);
          });
      } catch (e) {
        setIsLoading(false);
      }
    }, 500);

    fetchAttributes();

    return () => fetchAttributes.cancel();
  }, [path, files, filesChanged]);

  useEffect(() => {
    setValue(null);
    setIsLoading(true);
    setInnerPath(path);
  }, [path]);

  return (
    <div
      id="blockstudio-editor-preview"
      css={css({
        height:
          'calc(100vh - var(--blockstudio-editor-admin-bar) - var(--blockstudio-editor-toolbar))',
        width: '100%',
        background: isLoading ? colors.gray[50] : colors.gray[100],
        padding: '16px',
        display: 'flex',
        flexDirection: 'column',
        boxSizing: 'border-box',
        overflow: 'auto',
        position: 'relative',
      })}
    >
      <div
        css={css({
          borderRadius: '4px',
          width: '100%',
          maxWidth: '280px',
          margin: 'auto',
        })}
      >
        <ErrorBoundary FallbackComponent={() => <Error />}>
          {value?.blockstudio?.attributes?.filter((e) => e.type !== 'richtext')
            ?.length ? (
            <Configurator site={false} outerBlock={value} />
          ) : isLoading ? (
            <Spinner
              css={css({
                margin: '0',
                position: 'absolute',
                top: '50%',
                left: '50%',
                transform: 'translate(-50%, -50%)',
              })}
            />
          ) : (
            <div
              css={css({
                display: 'flex',
                justifyContent: 'center',
                alignItems: 'center',
                textAlign: 'center',
              })}
            >
              {__('No attributes found')}
            </div>
          )}
        </ErrorBoundary>
      </div>
    </div>
  );
};
