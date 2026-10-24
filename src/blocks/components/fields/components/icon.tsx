import apiFetch from '@wordpress/api-fetch';
import { SelectControl, ComboboxControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { isArray } from 'lodash-es';
import { Base } from '@/blocks/components/base';
import { selectors } from '@/blocks/store/selectors';
import { getIconOptions } from '@/blocks/utils/icon-options';
import { getDefaultSubSet, iconData } from '@/blocks/utils/icon-sets';
import { css } from '@/utils/css';

export const Icon = ({
  item,
  change,
  value,
}: {
  item: {
    sets: string[] | string;
    subSets: string[];
    label: string;
  };
  change: (item: { set: string; subSet?: string; icon: string }) => void;
  value: {
    set: string;
    subSet?: string;
    icon: string;
  };
}) => {
  const { setIcons } = useDispatch('blockstudio/blocks');
  const icons = useSelect(
    (select) => (select('blockstudio/blocks') as typeof selectors).getIcons(),
    [],
  );

  const [set, setSet] = useState(
    value?.set ||
      (isArray(item?.sets) ? item?.sets[0] : item?.sets) ||
      (Object.keys(iconData).includes('fontawesome-free')
        ? 'fontawesome-free'
        : Object.keys(iconData)[0]),
  );

  const [subSet, setSubSet] = useState(
    value?.subSet || getDefaultSubSet(set, item?.subSets),
  );

  const [active, setActive] = useState(false);

  const string = iconData?.[set]?.includes(subSet) ? `${set}-${subSet}` : set;
  const iconSet = icons?.[string] as unknown as
    | Record<string, string>
    | undefined;

  useEffect(() => {
    // The set carries one SVG per icon (up to 5 MB) and is only shown in the
    // dropdown, so it is fetched on first interaction instead of on mount.
    if (active && !iconSet) {
      apiFetch({
        path: iconData?.[set].includes(subSet)
          ? `/blockstudio/v1/icons?set=${set}&subSet=${subSet}`
          : `/blockstudio/v1/icons?set=${set}`,
        method: 'GET',
      })
        .then((data) => {
          setIcons({ [string]: data });
        })
        .catch(() => {
          setIcons({ [string]: {} });
        });
    }
  }, [active, set, subSet]);

  useEffect(() => {
    if (value?.set) {
      setSet(value.set);
    }
    if (value?.subSet) {
      setSubSet(value.subSet);
    }
  }, [value]);

  return (
    <Base>
      <div
        css={css({
          '& > * + *': {
            marginTop: 'calc(var(--blockstudio-fields-space) / 2)',
          },
        })}
      >
        <SelectControl
          options={Object.keys(iconData)
            .filter((e) =>
              item?.sets?.length
                ? item.sets.includes(e)
                : item?.sets
                  ? e === item.sets
                  : e,
            )
            .map((e) => {
              return {
                value: e,
                label: e,
              };
            })}
          value={set}
          onChange={(value: string) => {
            setActive(true);
            setSet(value);
            setSubSet(getDefaultSubSet(value, item?.subSets));
          }}
        />
        <SelectControl
          options={iconData[set]
            .filter((e: string | string[]) =>
              item?.subSets?.length
                ? item.subSets.includes(e as string)
                : item?.subSets
                  ? e === item.subSets
                  : e,
            )
            .map((e: string) => {
              return {
                value: e,
                label: e,
              };
            })}
          value={subSet}
          onChange={(value: string) => {
            setActive(true);
            setSubSet(value);
          }}
        />
        <div onFocus={() => setActive(true)}>
          <ComboboxControl
            isLoading={active && !iconSet}
            options={getIconOptions(iconSet, value?.icon)}
            onChange={(icon) =>
              icon &&
              change(
                iconData?.[set]?.includes(subSet)
                  ? { set, subSet, icon }
                  : {
                      set,
                      icon,
                    },
              )
            }
            value={value?.icon || null}
            __experimentalRenderItem={(e) => (
              <span
                css={css({
                  display: 'flex',
                  alignItems: 'center',
                  svg: {
                    height: '16px',
                    width: '16px',
                    marginRight: '12px',
                    fill: 'currentColor',
                  },
                })}
                dangerouslySetInnerHTML={{
                  __html: `${iconSet?.[e.item.value + '.svg']} ${e.item.label}`,
                }}
              />
            )}
          />
        </div>
      </div>
    </Base>
  );
};
