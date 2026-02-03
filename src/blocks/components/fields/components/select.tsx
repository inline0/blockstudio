import apiFetch from '@wordpress/api-fetch';
import {
  Button,
  ComboboxControl,
  SelectControl,
  Spinner,
} from '@wordpress/components';
import { useRef, useEffect, useState, useMemo } from '@wordpress/element';
import { debounce, get, isArray } from 'lodash-es';
import { Cards } from '@/blocks/components/list/cards';
import { BlockstudioAttribute } from '@/types/block';
import {
  Any,
  BlockstudioBlockAttributes,
  BlockstudioFieldsOptions,
} from '@/types/types';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

const addSingleFetched = (
  options: BlockstudioFieldsOptions[],
  newItem: BlockstudioFieldsOptions,
) => {
  return options
    .concat(newItem)
    .filter(
      (
        item: BlockstudioFieldsOptions,
        index: number,
        self: BlockstudioFieldsOptions[],
      ) =>
        item?.value &&
        item?.label &&
        index ===
          self.findIndex(
            (t) => t.label === item.label && t.value === item.value,
          ),
    );
};

const AdvancedSelect = ({
  attributes,
  change,
  disable,
  inRepeater,
  item,
  multiple,
  options = [],
  value,
  ...rest
}: {
  attributes: BlockstudioBlockAttributes;
  change: (val: Any, direct?: boolean, size?: string) => void;
  disable: (id: string) => void;
  inRepeater: boolean;
  item: BlockstudioAttribute;
  multiple: boolean;
  options: BlockstudioFieldsOptions[];
  value: Any;
}) => {
  value = multiple && value?.value ? [value] : value;

  const isFetch =
    (item?.populate?.fetch && item?.populate?.type === 'query') ||
    item?.populate?.type === 'fetch';
  const ref = useRef(null);
  const [allOptions, setAllOptions] = useState(
    isFetch
      ? multiple
        ? [...options, value].filter((e) => e?.value && e?.label)
        : addSingleFetched(options, value)
      : options.filter((_, i) =>
          item?.allowNull && item?.allowNull !== '' ? i !== 0 : true,
        ),
  );
  const [isLoading, setIsLoading] = useState(false);
  const [searchValue, setSearchValue] = useState('');

  const filteredValue = multiple
    ? (value || []).filter((e: Any) => e?.value)
    : value;
  const filteredOptions = multiple
    ? allOptions.filter(
        (option) => !filteredValue.map((e: Any) => e.value).includes(option.value),
      )
    : allOptions;

  const allowReset = !!(!multiple && value?.value);

  const fetcher = async (searchValue: string) => {
    setIsLoading(true);
    try {
      let res: { label: string; value: string }[] | Response;
      if (item?.populate?.type === 'fetch') {
        res = await fetch(
          `${
            (
              item?.populate?.arguments as {
                urlSearch: string;
              }
            )?.urlSearch
          }${searchValue}`,
        );

        if (res.ok) {
          res = await res.json();
          res = (res as unknown as object[]).map((e) => ({
            label:
              get(e, item?.populate?.returnFormat?.label || '') ||
              `${Object.values(e)[0]}`,
            value:
              get(e, item?.populate?.returnFormat?.value || '') ||
              `${Object.values(e)[0]}`,
          }));
        }
      } else {
        res = await apiFetch({
          path: '/blockstudio/v1/attributes/populate',
          method: 'POST',
          data: {
            ...item,
            fromEditor: true,
            populate: {
              ...item?.populate,
              arguments: {
                ...(item?.populate?.arguments || {}),
                search:
                  item?.populate?.query === 'users' && searchValue !== ''
                    ? `*${searchValue}*`
                    : searchValue,
                s: searchValue,
              },
            },
          },
        });
      }
      setAllOptions(
        addSingleFetched(
          res as {
            label: string;
            value: string;
          }[],
          value,
        ),
      );
      return res;
    } finally {
      setIsLoading(false);
    }
  };

  const debouncedApiFetch = useMemo(() => {
    return debounce((searchValue: string) => fetcher(searchValue), 250);
  }, []);

  const onChange = (val: string | null | undefined) => {
    if (multiple) {
      change(
        isFetch
          ? [
              ...(value || []).filter((e: Any) => e.value && e.label),
              allOptions.find((e) => e.value === val),
            ]
          : val,
        isFetch,
      );

      const input = (ref.current as HTMLElement | null)?.querySelector('input');
      if (input) {
        input.blur();
        input.focus();
      }
    } else {
      change(
        !val ? false : isFetch ? allOptions.find((e) => e.value === val) : val,
        isFetch,
      );
    }
  };

  const onFilterChange = (val: string) => {
    setSearchValue(val);

    options.filter((option) =>
      option.label.toLowerCase().startsWith(val.toLowerCase()),
    );
  };

  useEffect(() => {
    const element = ref?.current as HTMLElement | null;
    const input = element?.querySelector('input');
    if (input && item?.allowNull) {
      input.placeholder = String(item.allowNull);
    }
  }, [allOptions]);

  useEffect(() => {
    if (!isFetch) return;

    if (searchValue === '') {
      setAllOptions(multiple ? options : addSingleFetched(allOptions, value));
      setIsLoading(false);
    } else {
      debouncedApiFetch(searchValue);
    }
  }, [searchValue]);

  return (
    <div
      ref={ref}
      css={css({
        position: 'relative',

        '.components-form-token-field__suggestions-list': {
          '&:empty': {
            display: 'none',
          },
        },
        '.components-form-token-field__input': {
          paddingRight: isFetch ? '24px' : undefined,
        },
      })}
    >
      {isLoading && (
        <div
          css={css({
            position: 'absolute',
            right: multiple || !allowReset ? '-4px' : '20px',
            top: '2px',
          })}
        >
          <Spinner />
        </div>
      )}
      {(filteredOptions.length >= 1 || isFetch || !multiple) && (
        <ComboboxControl
          {...{ ...rest, allowReset }}
          options={filteredOptions}
          onFilterValueChange={onFilterChange}
          onChange={onChange}
          value={multiple ? null : value?.value || value}
          label={false}
          help={false}
        />
      )}
      {multiple && filteredValue.length >= 1 && (
        <Cards
          {...{ attributes, change, disable, inRepeater, item }}
          style={{ marginTop: '8px' }}
          v={filteredValue}
          ids={filteredValue}
        />
      )}
    </div>
  );
};

export const Select = ({
  attributes,
  change,
  disable,
  inRepeater,
  item,
  options = [],
  v,
  value,
  ...rest
}: {
  attributes: BlockstudioBlockAttributes;
  change: (val: Any, media?: boolean, size?: string) => void;
  disable: (id: string) => void;
  inRepeater: boolean;
  item: BlockstudioAttribute;
  options: BlockstudioFieldsOptions[];
  v: string;
  value: string;
}) => {
  const hasChanged = () => {
    const val = item.multiple ? (isArray(v) ? v : [v]) : [value];
    const values = val.map((e) => e?.value || e).flat();
    const defaultValues = item.default
      ? (!isArray(item.default) ? [item.default] : item.default)
          .map((e) => e?.value || e)
          .flat()
      : false;
    return JSON.stringify(values) !== JSON.stringify(defaultValues);
  };

  const resetDefault = () => {
    const findLabel = (val: string) =>
      options.find((e) => e.value === val) || { value: val, label: val };

    return item.default
      ? item.multiple
        ? (isArray(item.default) ? item.default : [item.default]).map((e) => ({
            value: e?.value || e,
            label: findLabel(e?.value || e).label,
          }))
        : {
            value:
              (
                item.default as {
                  value: string;
                }
              )?.value || item.default,
            label: findLabel(
              ((
                item.default as {
                  value: string;
                }
              )?.value || item.default) as string,
            ),
          }
      : item.multiple
        ? []
        : '';
  };

  return (
    <div
      css={css({
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'flex-end',
      })}
    >
      {item.stylisedUi ||
      item.multiple ||
      item?.populate?.fetch ||
      item?.populate?.type === 'fetch' ? (
        <AdvancedSelect
          {...{
            ...rest,
            attributes,
            change,
            disable,
            inRepeater,
            item,
            options,
          }}
          value={v}
          multiple={item.multiple ?? false}
        />
      ) : (
        <SelectControl
          {...{ ...rest, value, options }}
          className={`components-base-control`}
          help={false}
          label={false}
        />
      )}
      {item?.allowReset && hasChanged() && (
        <Button
          variant="link"
          css={css({
            marginLeft: 'auto',
            marginTop: '4px',
          })}
          onClick={() => {
            change(resetDefault(), true);
          }}
        >
          {__((item?.allowReset as unknown as string) || 'Reset to default')}
        </Button>
      )}
    </div>
  );
};
