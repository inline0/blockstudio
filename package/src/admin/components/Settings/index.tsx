import {
  CardBody,
  Flex,
  __experimentalText as Text,
} from '@wordpress/components';
import { Card } from '@/admin/components/Card';
import { License } from '@/admin/components/License';
import { Options } from '@/admin/components/Options';
import { css } from '@/utils/css';

export const Settings = () => {
  return (
    <Flex direction="column" gap={6}>
      {[
        {
          component: Options,
        },
        {
          name: 'License',
          component: License,
        },
      ].map(({ name, component: Component }) =>
        name === 'License' ? (
          <Card key={name}>
            <CardBody>
              <Flex direction="column">
                {name && (
                  <Text
                    size="medium"
                    weight={600}
                    css={css({
                      marginBottom: '4px',
                      display: 'block',
                    })}
                  >
                    {name}
                  </Text>
                )}
                <Component />
              </Flex>
            </CardBody>
          </Card>
        ) : (
          <Component key={name} />
        ),
      )}
    </Flex>
  );
};
