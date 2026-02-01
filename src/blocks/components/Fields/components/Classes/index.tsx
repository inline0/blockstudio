import {
  DropdownMenu,
  FormTokenField,
  MenuGroup,
  MenuItem,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import { tag, moreHorizontal } from '@wordpress/icons';
import { cloneDeep, set } from 'lodash-es';
import { Class } from '@/blocks/components/Fields/components/Classes/components/Class';
import { useGetCssClasses } from '@/blocks/hooks/useGetCssClasses';
import { CustomClasses } from '@/tailwind/components/CustomClasses';
import { classes } from '@/tailwind/data/classes';
import { screens } from '@/tailwind/data/screens';
import { selectors as selectorsTailwind } from '@/tailwind/store/selectors';
import { mergeClassNames } from '@/tailwind/utils/mergeClassNames';
import { sortClasses } from '@/tailwind/utils/sortClasses';
import { BlockstudioBlockAttributes } from '@/types/types';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const Classes = ({
  attributeId,
  attributes,
  clientId,
  fieldOnly = false,
  keyName,
  label,
  only,
  setAttributes,
  setValue,
  tailwind,
  value,
}: {
  attributeId?: string;
  attributes?: BlockstudioBlockAttributes;
  clientId?: string;
  fieldOnly?: boolean;
  keyName: string;
  label?: string;
  onResetTemporaryValue?: () => void;
  only?: boolean;
  setAttributes?: (props: BlockstudioBlockAttributes) => void;
  setValue?: (value: string, temporaryValue?: string) => void;
  tailwind?: boolean;
  value: string;
}) => {
  const { setTemporaryClasses } = useDispatch('blockstudio/tailwind');
  const settingsCssClasses = useGetCssClasses(
    window.blockstudioAdmin?.styles,
    window.blockstudioAdmin?.cssClasses ?? [],
  );
  const customClasses = useSelect(
    (select) =>
      (
        select('blockstudio/tailwind') as typeof selectorsTailwind
      ).getCustomClasses(),
    [],
  ) || [];
  const allClasses = [
    ...(tailwind
      ? [...classes, ...(customClasses || []).map((item: { className: string }) => item.className)]
      : []),
    ...settingsCssClasses,
  ] as string[];
  const [showCustomClasses, setShowCustomClasses] = useState(false);
  const [suggestions, setSuggestions] = useState<string[]>([...classes] as string[]);
  const ref = useRef<HTMLDivElement | null>(null);
  const valueRef = useRef(value);
  valueRef.current = value;
  const attributesRef = useRef(attributes);
  attributesRef.current = attributes;

  const sorted = sortClasses(value.trim());
  const groupedClasses = sorted.reduce((acc: Record<string, string[]>, className: string) => {
    const parts = className.split(':');
    const prefix =
      parts.length === 2 ? `${parts[0]} (${(screens as Record<string, string>)[parts[0]]})` : __('Base');
    if (!className) return acc;
    if (!acc[prefix]) {
      acc[prefix] = [];
    }
    acc[prefix].push(className);
    return acc;
  }, {} as Record<string, string[]>);

  const handleChange = (val: string, temporaryVal: string) => {
    if (setValue) {
      setValue(val, temporaryVal);
      return;
    }

    const clone = cloneDeep(attributesRef.current) as BlockstudioBlockAttributes | undefined;
    if (clone) {
      set(clone as object, keyName, val.trim());
    }
    setTemporaryClasses({
      [`${clientId}-${attributeId}`]: temporaryVal,
    });
    setAttributes?.({
      ...attributes,
      blockstudio: clone?.blockstudio,
    } as BlockstudioBlockAttributes);
  };

  const resetTemporaryValue = () => {
    setTemporaryClasses({
      [`${clientId}-${attributeId}`]: '',
    });
  };

  useEffect(() => setSuggestions(allClasses), [customClasses]);

  useEffect(() => {
    if (setValue) return;

    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (
          mutation.type === 'attributes' &&
          mutation.attributeName === 'aria-selected'
        ) {
          const selected = ref.current?.querySelector(
            '.components-form-token-field__suggestion[aria-selected="true"]',
          );
          if (selected) handleChange(valueRef.current, selected.textContent || '');
        }
        if (
          !ref.current?.querySelector('.components-form-token-field__suggestion')
        ) {
          resetTemporaryValue();
        }
      });
    });

    const container = ref.current;
    if (container) {
      observer.observe(container, {
        attributes: true,
        subtree: true,
        attributeFilter: ['aria-selected'],
      });
    }

    return () => {
      resetTemporaryValue();
      observer.disconnect();
    };
  }, []);

  useEffect(() => {
    const escapeHandler = (e: KeyboardEvent) => {
      if (!ref.current) return;
      if (e.key === 'Escape') {
        const input = ref.current?.querySelector('input');
        if (input) input.blur();
        resetTemporaryValue();
      }
    };

    document.addEventListener('keydown', escapeHandler);

    return () => {
      document.removeEventListener('keydown', escapeHandler);
    };
  }, []);

  return (
    <>
      <div {...{ ref }}>
        <div
          css={css({
            display: 'grid',
            gridTemplateColumns: '1fr auto',

            '.components-spacer': {
              display: 'none',
            },
          })}
        >
          <FormTokenField
            {...{ label }}
            suggestions={suggestions.filter((c) => !value.includes(c))}
            onChange={(val) => {
              handleChange(mergeClassNames(value, (val as string[]).join(' ')), '');
            }}
            onInputChange={(val) => {
              if (val.includes(':')) {
                const prefix = val.substring(0, val.lastIndexOf(':'));
                setSuggestions(allClasses.map((c) => `${prefix}:${c}`));
              } else {
                setSuggestions(allClasses);
              }
            }}
            tokenizeOnBlur
            __experimentalValidateInput={(token) => {
              if (token.includes('[') || token.includes(':')) return true;
              return allClasses.includes(token);
            }}
            __experimentalShowHowTo={false}
          />
          {!fieldOnly && tailwind && (
            <DropdownMenu
              css={css({
                '.components-button': {
                  marginTop: only ? '23px' : undefined,
                  width: '32px',
                  height: '32px',
                },
              })}
              icon={moreHorizontal}
              label="More"
            >
              {({ onClose }) => (
                <>
                  <MenuGroup>
                    <MenuItem
                      icon={tag}
                      onClick={() => {
                        setShowCustomClasses(true);
                        onClose();
                      }}
                    >
                      {__('Custom Tailwind Classes')}
                    </MenuItem>
                  </MenuGroup>
                </>
              )}
            </DropdownMenu>
          )}
        </div>
        <div
          css={css({
            display: 'grid',
            gap: '12px',
            marginTop: '12px',

            '&:empty': {
              display: 'none',
            },
          })}
        >
          {tailwind ? (
            Object.entries(groupedClasses).map(([prefix, classes]) => (
              <div key={prefix}>
                <h3
                  css={css({
                    margin: '0',
                    marginBottom: '4px',
                    color: '#1e1e1e',
                    fontSize: '11px',
                    fontWeight: '500',
                    textTransform: 'uppercase',
                  })}
                >
                  {prefix.toUpperCase()}
                </h3>
                <div
                  css={css({
                    display: 'flex',
                    flexWrap: 'wrap',
                    gap: '6px',
                  })}
                >
                  {(classes as string[])
                    .filter((e) => e)
                    .map((c) => (
                      <Class
                        {...{
                          attributes,
                          keyName,
                          setAttributes,
                          setValue,
                          value,
                        }}
                        key={c}
                        text={c}
                      />
                    ))}
                </div>
              </div>
            ))
          ) : (value.split(' ') as string[]).filter((e) => e).length ? (
            <div
              css={css({
                display: 'flex',
                flexWrap: 'wrap',
                gap: '6px',
              })}
            >
              {(value.split(' ') as string[])
                .filter((e) => e)
                .map((c) => (
                  <Class
                    {...{
                      attributes,
                      keyName,
                      setAttributes,
                      setValue,
                      value,
                    }}
                    key={c}
                    text={c}
                  />
                ))}
            </div>
          ) : null}
        </div>
      </div>
      {!fieldOnly && (
        <CustomClasses
          show={showCustomClasses}
          setShow={setShowCustomClasses}
        />
      )}
    </>
  );
};
