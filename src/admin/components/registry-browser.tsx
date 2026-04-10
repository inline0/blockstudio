import type { ReactElement } from 'react';
import { useState, useCallback, useEffect } from '@wordpress/element';
import { Button, Card, CardBody, Notice, Spinner } from '@wordpress/components';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import type { View, Field } from '@wordpress/dataviews';
import apiFetch from '@wordpress/api-fetch';
import type { AdminRegistryBlockRow } from '../types';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

const DEFAULT_LAYOUTS = {
  table: {},
};

const defaultView: View = {
  fields: [
    'registry',
    'name',
    'title',
    'description',
    'category',
    'type',
    'status',
    'actions',
  ],
  page: 1,
  perPage: 20,
  sort: { direction: 'asc' as const, field: 'name' },
  type: 'table',
};

export const RegistryBrowser = (): ReactElement => {
  const [items, setItems] = useState<AdminRegistryBlockRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [view, setView] = useState<View>(defaultView);
  const [importing, setImporting] = useState<Set<string>>(new Set());

  useEffect(() => {
    apiFetch<AdminRegistryBlockRow[]>({ path: '/blockstudio/v1/registry/blocks' })
      .then((data) => setItems(data))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const handleImport = useCallback(async (item: AdminRegistryBlockRow) => {
    setImporting((prev) => new Set(prev).add(item.id));

    try {
      await apiFetch({
        path: '/blockstudio/v1/registry/import',
        method: 'POST',
        data: {
          registry: item.registry,
          block: item.name,
        },
      });

      setItems((prev) =>
        prev.map((row) =>
          row.id === item.id ? { ...row, status: 'installed' as const } : row,
        ),
      );
    } catch {
      // Silently handle for now
    } finally {
      setImporting((prev) => {
        const next = new Set(prev);
        next.delete(item.id);
        return next;
      });
    }
  }, []);

  const fields: Field<AdminRegistryBlockRow>[] = [
    {
      enableGlobalSearch: true,
      id: 'registry',
      label: __('Registry'),
    },
    {
      enableGlobalSearch: true,
      id: 'name',
      label: __('Name'),
    },
    {
      enableGlobalSearch: true,
      id: 'title',
      label: __('Title'),
    },
    {
      enableGlobalSearch: true,
      id: 'description',
      label: __('Description'),
    },
    {
      enableGlobalSearch: true,
      id: 'category',
      label: __('Category'),
    },
    {
      enableGlobalSearch: true,
      id: 'type',
      label: __('Type'),
    },
    {
      enableGlobalSearch: true,
      id: 'status',
      label: __('Status'),
      render: ({ item }) => (
        <span
          css={css({
            color: item.status === 'installed' ? '#00a32a' : '#757575',
            fontWeight: item.status === 'installed' ? '600' : '400',
          })}
        >
          {item.status === 'installed' ? __('Installed') : __('Available')}
        </span>
      ),
    },
    {
      id: 'actions',
      label: __('Actions'),
      enableSorting: false,
      enableGlobalSearch: false,
      render: ({ item }) => {
        const isImporting = importing.has(item.id);
        const label =
          item.status === 'installed' ? __('Reimport') : __('Import');

        return (
          <Button
            disabled={isImporting}
            isBusy={isImporting}
            onClick={() => handleImport(item)}
            variant="secondary"
            size="compact"
          >
            {label}
          </Button>
        );
      },
    },
  ];

  const { data, paginationInfo } = filterSortAndPaginate(
    items,
    view,
    fields,
  );

  if (loading) {
    return (
      <div css={css({ padding: '20px', display: 'flex', justifyContent: 'center' })}>
        <Spinner />
      </div>
    );
  }

  if (items.length === 0) {
    return (
      <div css={css({ padding: '20px' })}>
        <Card>
          <CardBody>
            <Notice isDismissible={false} status="info">
              {__(
                'No registries are configured. Add registries to your theme\'s blocks.json file.',
              )}
            </Notice>
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div css={css({ padding: '20px' })}>
      <Card css={css({ overflow: 'hidden' })}>
        <CardBody css={css({ padding: '0' })}>
          <DataViews<any>
            data={data}
            defaultLayouts={DEFAULT_LAYOUTS}
            fields={fields}
            getItemId={(item) => item.id}
            onChangeView={setView}
            paginationInfo={paginationInfo}
            view={view}
          >
            <>
              <div className="dataviews__view-actions">
                <DataViews.Search label={__('Search blocks')} />
              </div>
              <DataViews.FiltersToggled className="dataviews-filters__container" />
              <DataViews.Layout />
              <DataViews.Footer />
            </>
          </DataViews>
        </CardBody>
      </Card>
    </div>
  );
};
