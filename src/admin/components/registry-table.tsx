import type { ReactElement } from 'react';
import { Notice } from '@wordpress/components';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import type { View } from '@wordpress/dataviews';
import type { AdminRegistryConfig } from '../data/registry-config';

const DEFAULT_LAYOUTS = {
  table: {},
};

type RegistryTableProps<Item extends { id: string }> = {
  config: AdminRegistryConfig<Item>;
  items: Item[];
  onChangeView: (view: View) => void;
  view: View;
};

export const RegistryTable = <Item extends { id: string },>({
  config,
  items,
  onChangeView,
  view,
}: RegistryTableProps<Item>): ReactElement => {
  const { data, paginationInfo } = filterSortAndPaginate(
    items,
    view,
    config.fields,
  );

  return (
    <DataViews<any>
      data={data}
      defaultLayouts={DEFAULT_LAYOUTS}
      empty={
        <Notice isDismissible={false} status="info">
          {config.emptyMessage}
        </Notice>
      }
      fields={config.fields}
      getItemId={(item) => item.id}
      onChangeView={onChangeView}
      paginationInfo={paginationInfo}
      view={view}
    >
      <>
        <div className="dataviews__view-actions">
          <DataViews.Search label={config.searchLabel} />
        </div>
        <DataViews.FiltersToggled className="dataviews-filters__container" />
        <DataViews.Layout />
        <DataViews.Footer />
      </>
    </DataViews>
  );
};
