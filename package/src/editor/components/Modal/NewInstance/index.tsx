import apiFetch from '@wordpress/api-fetch';
import {
  TextControl,
  TextareaControl,
  Button,
  __experimentalText as Text,
  __experimentalVStack as VStack,
  __experimentalHStack as HStack,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { Modal } from '@/editor/components/Modal';
import { SPACING } from '@/editor/const/spacings';
import { selectors } from '@/editor/store/selectors';
import { limitName } from '@/editor/utils/limitName';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

const PLUGIN_DETAILS = `/**
* Plugin Name: Blockstudio Blocks
* Description: Custom Blockstudio Blocks.
* Version: 1.0
* Author: Blockstudio
**/
`;

const PLUGIN_IMPLEMENTATION = () => `
add_action( 'init', function () {
  if ( defined( "BLOCKSTUDIO" ) ) {
    Blockstudio\\Build::init( [
      'dir'    => plugin_dir_path( __FILE__ ) . 'blocks',
    ] );
  };
} );
`;

export const ModalNewInstance = () => {
  const [cantContinue, setCantContinue] = useState(false);
  const [details, setDetails] = useState(PLUGIN_DETAILS);
  const [detailsOpen, setDetailsOpen] = useState(false);
  const [folderName, setFolderName] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const { setNewInstance } = useDispatch('blockstudio/editor');
  const newInstance = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).newInstance(),
    [],
  );
  const pluginsPath = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getPluginsPath(),
    [],
  );
  const plugins = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getPlugins(),
    [],
  );

  const getPluginName = () => {
    const names = Object.keys(plugins)
      .filter((k) => k.includes('blockstudio-blocks'))
      .map((k) => k.split('/')[0]);

    if (!names.includes('blockstudio-blocks')) {
      return 'blockstudio-blocks';
    }

    const check = (index: number) => {
      if (names.includes(`blockstudio-blocks-${index}`)) {
        return check(index + 1);
      } else {
        return `blockstudio-blocks-${index}`;
      }
    };

    return check(2);
  };

  const onType = (value: string) => {
    const val = limitName(value);
    setFolderName(val);

    if (
      Object.keys(plugins)
        .map((k) => k.split('/')[0])
        .includes(val)
    ) {
      setCantContinue(true);
    } else {
      setCantContinue(false);
    }
  };

  const onCreate = async () => {
    const path = `${pluginsPath}/${folderName}/blocks.php`;

    setIsLoading(true);
    await apiFetch({
      path: '/blockstudio/v1/editor/file/create',
      method: 'POST',
      data: {
        files: [
          {
            instance: true,
            path,
            content: `<?php\n${details}${PLUGIN_IMPLEMENTATION()}`,
          },
        ],
      },
    })
      .catch((err) => {
        throw err;
      })
      .finally(() => setIsLoading(false));
    await apiFetch({
      path: '/blockstudio/v1/editor/plugin/activate',
      method: 'POST',
      data: {
        path,
      },
    })
      .then(() => location.reload())
      .catch((err) => {
        throw err;
      })
      .finally(() => setIsLoading(false));
  };

  useEffect(() => {
    setFolderName(getPluginName());
  }, []);

  return (
    <Modal
      {...{ isLoading }}
      id="blockstudio-new-instance"
      disabled={cantContinue}
      onPrimary={onCreate}
      onSecondary={() => setNewInstance(false)}
      onRequestClose={() => setNewInstance(false)}
      show={newInstance}
      textPrimary={__('Create plugin')}
      title={__('Create a new Blockstudio instance')}
    >
      <VStack spacing={SPACING.SECTION}>
        <VStack spacing={SPACING.INPUT}>
          <Text>
            {__(
              "Blockstudio will automatically create a custom plugin that'll house your blocks.",
            )}
          </Text>
          <HStack>
            <Button variant="link" onClick={() => setDetailsOpen(!detailsOpen)}>
              {detailsOpen
                ? __('Close plugin details')
                : __('Edit plugin details')}{' '}
            </Button>
          </HStack>
        </VStack>
        {detailsOpen && (
          <VStack spacing={SPACING.INPUT}>
            <TextControl
              css={css({
                '.components-base-control__help': {
                  color: 'red',
                },
              })}
              label={__('Folder name')}
              value={folderName}
              onChange={onType}
              help={cantContinue && __('Plugin folder already exists')}
            />
            <TextareaControl
              label={__('Plugin details')}
              onChange={(value) => setDetails(value)}
              value={details}
              rows={10}
            />
          </VStack>
        )}
      </VStack>
    </Modal>
  );
};
