import {
  Button,
  SearchControl,
  CardHeader,
  CardBody,
  Spinner,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { Card } from '@/admin/components/Card';
import { selectors } from '@/editor/store/selectors';
import { BlockstudioEditorFileStructure } from '@/type/types';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';
import { Table } from './Table';

export const List = () => {
  const [mounted, setMounted] = useState(false);
  const [searchInput, setSearchInput] = useState('');
  const { setNewInstance } = useDispatch('blockstudio/editor');
  const blocks = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getBlocks(),
    [],
  );
  const paths = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getPaths(),
    [],
  );

  useEffect(() => {
    setMounted(true);
  }, []);

  return mounted ? (
    <>
      <Card>
        <CardHeader>
          <div
            css={css({
              display: 'grid',
              gridTemplateColumns: '1fr auto',
              gap: '16px',
              width: '100%',
            })}
          >
            <SearchControl
              value={searchInput}
              onChange={setSearchInput}
              css={css({
                width: '100%',
                div: {
                  margin: '0',
                },
              })}
            />
            <Button
              css={css({ height: '100%' })}
              variant="secondary"
              onClick={() => setNewInstance(true)}
            >
              {__('Add instance')}
            </Button>
          </div>
        </CardHeader>
        <CardBody
          css={css({
            padding: '0',
          })}
        >
          <Table
            blocks={{
              ...blocks,
              ...paths.filter(
                (e) =>
                  !Object.values(blocks)
                    .map(
                      (e) =>
                        (e as unknown as BlockstudioEditorFileStructure)
                          .instance,
                    )
                    .includes(
                      (e as unknown as BlockstudioEditorFileStructure).instance,
                    ),
              ),
            }}
            searchInput={searchInput}
          />
        </CardBody>
      </Card>
    </>
  ) : (
    <div
      css={css({
        height: '200px',
        width: '100%',
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center',
      })}
    >
      <Spinner />
    </div>
  );
};
