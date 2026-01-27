import { MouseEvent } from 'react';
import { Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { moreHorizontalMobile } from '@wordpress/icons';
import { selectors } from '@/editor/store/selectors';
import { css } from '@/utils/css';

export const Add = ({ onClick }: { onClick: (_: MouseEvent) => void }) => {
  const isEditor = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).isEditor(),
    [],
  );

  return (
    !isEditor && (
      <div
        className="blockstudio-add"
        css={css({
          display: 'none',
          position: 'absolute',
          right: 0,
          top: '50%',
          transform: 'translateY(-50%)',
          zIndex: 50,
          height: '20px',
        })}
      >
        <Button
          icon={moreHorizontalMobile}
          isSmall
          onClick={(e: MouseEvent<HTMLButtonElement>) => {
            e.stopPropagation();
            onClick(e);
          }}
          css={css({
            height: '20px',
          })}
        />
      </div>
    )
  );
};
