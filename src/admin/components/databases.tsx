import type { ReactElement } from 'react';
import { Card, CardBody, Notice, TabPanel } from '@wordpress/components';
import type { AdminDatabaseDefinition } from '../types';
import { DatabaseRecordsTable } from './database-records-table';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const Databases = (): ReactElement => {
  const databases = window.blockstudioAdminPage?.databases ?? [];

  if (0 === databases.length) {
    return (
      <div
        css={css({
          padding: '20px',
        })}
      >
        <Card>
          <CardBody>
            <Notice isDismissible={false} status="info">
              {__('No Blockstudio databases are registered.')}
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
      <TabPanel
        tabs={databases.map((database: AdminDatabaseDefinition) => {
          return {
            name: database.id,
            title: database.label,
          };
        })}
      >
        {(tab) => {
          const database = databases.find((entry) => entry.id === tab.name);

          if (!database) {
            return (
              <Card>
                <CardBody>
                  <Notice isDismissible={false} status="warning">
                    {__('The selected database could not be found.')}
                  </Notice>
                </CardBody>
              </Card>
            );
          }

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
                <DatabaseRecordsTable database={database} />
              </CardBody>
            </Card>
          );
        }}
      </TabPanel>
    </div>
  );
};
