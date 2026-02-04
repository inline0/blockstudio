import apiFetch from '@wordpress/api-fetch';
import { SelectControl, ComboboxControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { isArray } from 'lodash-es';
import { Base } from '@/blocks/components/base';
import { selectors } from '@/blocks/store/selectors';
import { css } from '@/utils/css';

const iconData: Record<string, string[]> = {
  octicons: [],
  'vscode-icons': [],
  'remix-icons': [
    'arrows',
    'buildings',
    'business',
    'communication',
    'design',
    'development',
    'device',
    'document',
    'editor',
    'finance',
    'food',
    'health',
    'logos',
    'map',
    'media',
    'others',
    'system',
    'user',
    'weather',
  ],
  'tabler-icons': [],
  'simple-icons': [],
  'flat-color-icons': [],
  'bootstrap-icons': [],
  'material-design-icons': ['two-tone', 'outlined', 'filled', 'round', 'sharp'],
  'box-icons': ['logos', 'solid', 'regular'],
  'ion-icons': [],
  heroicons: ['solid', 'outline'],
  'feather-icons': [],
  'fontawesome-free': ['brands', 'solid', 'regular'],
  'grommet-icons': [],
  'css-gg': [],
};

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
    value?.subSet ||
      (isArray(item?.subSets) ? item?.subSets[0] : item?.subSets) ||
      (Object.keys(iconData).includes('fontawesome-free')
        ? 'solid'
        : iconData[set][0]),
  );

  const string = iconData?.[set]?.includes(subSet) ? `${set}-${subSet}` : set;

  useEffect(() => {
    if (!icons?.[string]) {
      apiFetch({
        path: iconData?.[set].includes(subSet)
          ? `/blockstudio/v1/icons?set=${set}&subSet=${subSet}`
          : `/blockstudio/v1/icons?set=${set}`,
        method: 'GET',
      }).then((data) => {
        setIcons({ [string]: data });
      });
    }
  }, [set, subSet]);

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
            setSet(value);
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
          onChange={(value: string) => setSubSet(value)}
        />
        {icons?.[string] && (
          <ComboboxControl
            options={Object.entries(icons[string]).map(([k]) => {
              return {
                value: k.replace('.svg', ''),
                label: k.replace('.svg', ''),
              };
            })}
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
                  __html: `${(icons?.[string] as unknown as Record<string, string>)?.[e.item.value + '.svg']} ${
                    e.item.label
                  }`,
                }}
              />
            )}
          />
        )}
      </div>
    </Base>
  );
};
