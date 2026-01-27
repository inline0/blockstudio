import { ReactNode } from 'react';
import {
  Button,
  Modal as M,
  __experimentalVStack as VStack,
  __experimentalText as Text,
} from '@wordpress/components';
import { useFocusOnMount } from '@wordpress/compose';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';
import { SPACING } from '@/editor/const/spacings';
import { selectors } from '@/editor/store/selectors';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const Modal = ({
  disabled = false,
  onRequestClose,
  onSecondary = () => {},
  onPrimary,
  isDestructive = false,
  isLoading = false,
  textPrimary = __('Save'),
  textSecondary = __("Don't save"),
  title = __('Save changes on current block?'),
  text = '',
  isValid = true,
  children = null,
  show = false,
  ...rest
}: {
  disabled?: boolean;
  onRequestClose: () => void;
  onSecondary?: () => void;
  onPrimary: () => void;
  isDestructive?: boolean;
  isLoading?: boolean;
  textPrimary?: string;
  textSecondary?: string;
  title?: string;
  text?: string;
  isValid?: boolean;
  children?: ReactNode;
  show?: boolean | string;
  [key: string]: unknown;
}) => {
  const { setContextMenu } = useDispatch('blockstudio/editor');
  const contextMenu = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getContextMenu(),
    [],
  );
  const button = useRef(null);
  const ref = useFocusOnMount();

  useEffect(() => {
    show && setContextMenu({ ...contextMenu, x: 0, y: 0 });

    const onEnter = (e: KeyboardEvent) => {
      if (e.key === 'Enter' && show) {
        e.preventDefault();
        button.current.click();
      }
    };

    window.addEventListener('keydown', onEnter);

    return () => window.removeEventListener('keydown', onEnter);
  }, [show]);

  return show ? (
    <M
      {...rest}
      title={title}
      onRequestClose={onRequestClose}
      css={css({
        maxWidth: '600px',
        width: '100%',
        '.components-button.is-destructive:focus:not(:disabled)': {
          color: '#fff',
        },
      })}
    >
      <VStack ref={ref} spacing={SPACING.SECTION}>
        {children && children}
        {text && <Text>{text}</Text>}
        <div
          css={css({
            display: 'flex',
            marginTop: '2px',
          })}
        >
          <Button
            ref={button}
            variant="primary"
            onClick={onPrimary}
            isDestructive={isDestructive}
            disabled={disabled || !isValid || isLoading}
            isBusy={isLoading}
            css={css({
              marginRight: '12px',
            })}
          >
            {textPrimary}
          </Button>
          {textSecondary && (
            <Button variant="secondary" onClick={onSecondary}>
              {textSecondary}
            </Button>
          )}
        </div>
      </VStack>
    </M>
  ) : null;
};
