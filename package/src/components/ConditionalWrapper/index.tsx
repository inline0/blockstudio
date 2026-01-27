import { ReactNode } from 'react';

export const ConditionalWrapper = ({
  condition,
  wrapper,
  children,
}: {
  condition: boolean;
  wrapper: (children: ReactNode) => ReactNode;
  children: ReactNode;
}) => {
  return condition ? wrapper(children) : children;
};
