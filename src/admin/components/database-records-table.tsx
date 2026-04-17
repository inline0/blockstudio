import type { ReactElement } from 'react';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { Notice, Spinner } from '@wordpress/components';
import { DataViews } from '@wordpress/dataviews/wp';
import type { Field, View } from '@wordpress/dataviews';
import apiFetch from '@wordpress/api-fetch';
import type {
  AdminDatabaseDefinition,
  AdminDatabaseRecordsResponse,
  AdminDatabaseRow,
} from '../types';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

const PAGE_SIZE = 20;

const formatLabel = (key: string): string => {
  return key
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());
};

const formatValue = (value: unknown): string => {
  if (null === value || undefined === value) {
    return '';
  }

  if (Array.isArray(value)) {
    if (value.every((item) => ['string', 'number', 'boolean'].includes(typeof item))) {
      return value.join(', ');
    }

    return JSON.stringify(value);
  }

  if ('object' === typeof value) {
    return JSON.stringify(value);
  }

  if ('boolean' === typeof value) {
    return value ? __('True') : __('False');
  }

  return String(value);
};

const inferFieldType = (rows: AdminDatabaseRow[], key: string): Field<AdminDatabaseRow>['type'] => {
  const sample = rows.find((row) => undefined !== row[key])?.[key];

  if ('number' === typeof sample) {
    return Number.isInteger(sample) ? 'integer' : 'number';
  }

  return undefined;
};

const getFieldKeys = (
  database: AdminDatabaseDefinition,
  rows: AdminDatabaseRow[],
): string[] => {
  const baseKeys = ['id', ...database.fields];

  if (database.userScoped && !baseKeys.includes('user_id')) {
    baseKeys.push('user_id');
  }

  rows.forEach((row) => {
    Object.keys(row).forEach((key) => {
      if (!baseKeys.includes(key)) {
        baseKeys.push(key);
      }
    });
  });

  return baseKeys;
};

type DatabaseRecordsTableProps = {
  database: AdminDatabaseDefinition;
};

export const DatabaseRecordsTable = ({
  database,
}: DatabaseRecordsTableProps): ReactElement => {
  const [items, setItems] = useState<AdminDatabaseRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [totalItems, setTotalItems] = useState(0);
  const [view, setView] = useState<View>({
    fields: [],
    page: 1,
    perPage: PAGE_SIZE,
    type: 'table',
  });

  useEffect(() => {
    setView((currentView) => ({
      ...currentView,
      page: 1,
    }));
  }, [database.id]);

  useEffect(() => {
    let mounted = true;
    const page = Math.max(view.page ?? 1, 1);
    const perPage = Math.max(view.perPage ?? PAGE_SIZE, 1);
    const offset = (page - 1) * perPage;

    setLoading(true);
    setError(null);

    apiFetch<AdminDatabaseRecordsResponse>({
      path: `/blockstudio/v1/admin/databases?key=${encodeURIComponent(database.id)}&limit=${perPage}&offset=${offset}`,
    })
      .then((response) => {
        if (!mounted) {
          return;
        }

        setItems(response.items);
        setTotalItems(response.total);
      })
      .catch(() => {
        if (!mounted) {
          return;
        }

        setError(__('Could not load database records.'));
        setItems([]);
        setTotalItems(0);
      })
      .finally(() => {
        if (mounted) {
          setLoading(false);
        }
      });

    return () => {
      mounted = false;
    };
  }, [database.id, view.page, view.perPage]);

  const fields = useMemo(() => {
    return getFieldKeys(database, items).map((key) => {
      const field: Field<AdminDatabaseRow> = {
        enableGlobalSearch: false,
        enableSorting: false,
        id: key,
        label: formatLabel(key),
        render: ({ item }) => formatValue(item[key]),
        type: inferFieldType(items, key),
      };

      return field;
    });
  }, [database, items]);

  const resolvedView = useMemo<View>(() => {
    return {
      fields: fields.map((field) => field.id),
      page: Math.max(view.page ?? 1, 1),
      perPage: Math.max(view.perPage ?? PAGE_SIZE, 1),
      search: view.search,
      sort: view.sort,
      type: 'table',
    };
  }, [fields, view.page, view.perPage, view.search, view.sort]);

  const totalPages = Math.max(
    1,
    Math.ceil(totalItems / Math.max(resolvedView.perPage ?? PAGE_SIZE, 1)),
  );

  if (loading) {
    return (
      <div
        css={css({
          padding: '20px',
          display: 'flex',
          justifyContent: 'center',
        })}
      >
        <Spinner />
      </div>
    );
  }

  if (error) {
    return (
      <div css={css({ padding: '20px' })}>
        <Notice isDismissible={false} status="error">
          {error}
        </Notice>
      </div>
    );
  }

  return (
    <div>
      {items.length === 0 ? (
        <div css={css({ padding: '20px' })}>
          <Notice isDismissible={false} status="info">
            {__('This database has no records yet.')}
          </Notice>
        </div>
      ) : (
        <DataViews<AdminDatabaseRow>
          data={items}
          defaultLayouts={{ table: {} }}
          fields={fields}
          getItemId={(item) => String(item.id ?? JSON.stringify(item))}
          onChangeView={setView}
          paginationInfo={{ totalItems, totalPages }}
          view={resolvedView}
        >
          <>
            <DataViews.Footer />
          </>
        </DataViews>
      )}
    </div>
  );
};
