import { ReactNode } from 'react';
import { __experimentalText as Text } from '@wordpress/components';

export const Label = ({ children }: { children: ReactNode }) => {
  return (
    <Text size="10px" weight={600} lineHeight={0} truncate numberOfLines={1}>
      {children}
    </Text>
  );
};
