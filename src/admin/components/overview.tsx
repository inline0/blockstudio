import type { ReactElement } from 'react';
import { Card, CardBody, Notice, TabPanel } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { RegistryTable } from './registry-table';
import {
  createDefaultViews,
  getRegistryTabs,
  REGISTRY_CONFIG,
} from '../data/registry-config';
import type { BlockstudioAdminOverview, RegistryTabName } from '../types';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

const renderRegistryTable = (
  name: RegistryTabName,
  overview: BlockstudioAdminOverview,
  views: ReturnType<typeof createDefaultViews>,
  onChangeView: (name: RegistryTabName, view: (typeof views)[RegistryTabName]) => void,
): ReactElement => {
  switch (name) {
    case 'blocks':
      return (
        <RegistryTable
          config={REGISTRY_CONFIG.blocks}
          items={overview.blocks}
          onChangeView={(view) => onChangeView(name, view)}
          view={views.blocks}
        />
      );
    case 'extensions':
      return (
        <RegistryTable
          config={REGISTRY_CONFIG.extensions}
          items={overview.extensions}
          onChangeView={(view) => onChangeView(name, view)}
          view={views.extensions}
        />
      );
    case 'fields':
      return (
        <RegistryTable
          config={REGISTRY_CONFIG.fields}
          items={overview.fields}
          onChangeView={(view) => onChangeView(name, view)}
          view={views.fields}
        />
      );
    case 'pages':
      return (
        <RegistryTable
          config={REGISTRY_CONFIG.pages}
          items={overview.pages}
          onChangeView={(view) => onChangeView(name, view)}
          view={views.pages}
        />
      );
    case 'schemas':
      return (
        <RegistryTable
          config={REGISTRY_CONFIG.schemas}
          items={overview.schemas}
          onChangeView={(view) => onChangeView(name, view)}
          view={views.schemas}
        />
      );
  }
};

export const Overview = (): ReactElement => {
  const overview = window.blockstudioAdminPage?.overview;
  const [views, setViews] = useState(createDefaultViews);

  if (!overview) {
    return (
      <div
        css={css({
          padding: '20px',
        })}
      >
        <Card>
          <CardBody>
            <Notice isDismissible={false} status="warning">
              {__('No Blockstudio overview data is available.')}
            </Notice>
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div
      css={css({
        padding: '20px',
        '.components-tab-panel__tabs': {
          margin: '0 0 0 8px',
          overflowX: 'auto',
          overflowY: 'hidden',
          flexWrap: 'nowrap',
          scrollbarWidth: 'thin',
        },
        '.components-tab-panel__tabs .components-button': {
          flex: '0 0 auto',
          whiteSpace: 'nowrap',
        },
        '.components-tab-panel__tab-content': {
          outline: 'none',
        },
      })}
    >
      <TabPanel tabs={getRegistryTabs(overview)}>
        {(tab) => {
          const name = tab.name as RegistryTabName;

          return (
            <Card
              css={css({
                overflow: 'hidden',
              })}
            >
              <CardBody
                css={css({
                  padding: '0',
                })}
              >
                {renderRegistryTable(
                  name,
                  overview,
                  views,
                  (viewName, view) => {
                    setViews((currentViews) => {
                      return {
                        ...currentViews,
                        [viewName]: view,
                      };
                    });
                  },
                )}
              </CardBody>
            </Card>
          );
        }}
      </TabPanel>
    </div>
  );
};
