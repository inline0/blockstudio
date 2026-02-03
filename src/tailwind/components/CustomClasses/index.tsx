import apiFetch from '@wordpress/api-fetch';
import {
  __experimentalVStack as VStack,
  Button,
  Modal,
  TextControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { trash } from '@wordpress/icons';
import { cloneDeep } from 'lodash-es';
import parserBabel from 'prettier/plugins/babel';
import prettier from 'prettier/standalone';
import { Classes as Tailwind } from '@/blocks/components/Fields/components/Classes';
import { style } from '@/const/index';
import { SPACING } from '@/const/spacings';
import { selectors } from '@/tailwind/store/selectors';
import { BlockstudioBlockAttributes } from '@/types/types';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

type ButtonProps = {
  variant?: 'link' | 'secondary' | 'primary' | 'tertiary';
  [key: string]: unknown;
};

const blockstudio = window.blockstudioAdmin;

export const CustomClasses = ({
  buttonProps,
  index,
  show: externalShow,
  setShow: setExternalShow,
}: {
  buttonProps?: ButtonProps;
  index?: number;
  show?: boolean;
  setShow?: (show: boolean) => void;
}) => {
  const { setCustomClasses } = useDispatch('blockstudio/tailwind');
  const customClasses = useSelect(
    (select) =>
      (select('blockstudio/tailwind') as typeof selectors).getCustomClasses(),
    [],
  );
  const [internalClasses, setInternalClasses] = useState(customClasses);
  const [isLoading, setIsLoading] = useState(false);
  const [show, setShow] = useState(false);

  const onPrimary = async () => {
    setIsLoading(true);
    const optionsClone = cloneDeep(blockstudio.options);
    optionsClone.tailwind.customClasses = internalClasses;
    const formatted = await prettier.format(JSON.stringify(optionsClone), {
      parser: 'json',
      plugins: [parserBabel],
    });
    await apiFetch({
      path: '/blockstudio/v1/editor/options/save',
      method: 'POST',
      data: {
        options: encodeURIComponent(formatted),
        json: blockstudio.optionsJson !== null,
      },
    }).finally(() => {
      setIsLoading(false);
      setCustomClasses(internalClasses);
    });
  };

  const handleChange = (index: number, type: string, value: string) => {
    const clone = cloneDeep(internalClasses) || [];
    clone[index] = {
      ...(clone[index] || {}),
      [type]: value,
    };
    setInternalClasses(clone);
  };

  const addClass = () => {
    const clone = cloneDeep(internalClasses) || [];
    clone.push({ className: '', value: '' });
    setInternalClasses(clone);
  };

  const removeClass = (indexToRemove: number) => {
    const clone = cloneDeep(internalClasses) || [];
    clone.splice(indexToRemove, 1);
    setInternalClasses(clone);
  };

  useEffect(() => {
    if (show) setInternalClasses(customClasses);
  }, [show]);

  useEffect(() => {
    if (externalShow !== undefined) setShow(externalShow);
  }, [externalShow]);

  return (
    <>
      {buttonProps && <Button onClick={() => setShow(true)} {...buttonProps} />}
      {show && (
        <Modal
          title="Custom Tailwind Classes"
          onRequestClose={() => {
            setShow(false);
            setExternalShow && setExternalShow(false);
          }}
        >
          <VStack spacing={SPACING.SECTION}>
            <VStack>
              <div
                css={css({
                  display: 'grid',
                })}
              >
                {internalClasses?.length
                  ? internalClasses?.map((item, i) => {
                      const showIndex = (index ?? -1) + 1 && (index ?? -1) + 1 === i + 1;
                      const showItem = !((index ?? -1) + 1) || showIndex;

                      return (
                        <div
                          key={i}
                          css={css({
                            display: showItem ? 'block' : 'none',
                            paddingTop: i !== 0 ? '24px' : undefined,
                            marginTop: i !== 0 ? '24px' : undefined,
                            borderTop: i !== 0 ? style.border : undefined,
                          })}
                        >
                          <div
                            css={css({
                              display: 'grid',
                              gridTemplateColumns: '1fr auto',
                              gap: '12px',
                            })}
                          >
                            <div
                              css={css({
                                display: 'grid',
                                gap: '12px',
                              })}
                            >
                              <TextControl
                                placeholder={__('Classname')}
                                value={item.className || ''}
                                onChange={(val) =>
                                  handleChange(i, 'className', val)
                                }
                                __nextHasNoMarginBottom
                              />
                              <Tailwind
                                attributes={{} as BlockstudioBlockAttributes}
                                setAttributes={() => {}}
                                label="Classes"
                                value={item.value || ''}
                                setValue={(val) =>
                                  handleChange(i, 'value', val)
                                }
                                keyName="value"
                                fieldOnly
                                tailwind
                              />
                            </div>
                            {!showIndex && (
                              <Button
                                css={css({
                                  height: '32px',
                                  width: '32px',
                                })}
                                icon={trash}
                                variant="secondary"
                                onClick={() => removeClass(i)}
                              />
                            )}
                          </div>
                        </div>
                      );
                    })
                  : null}
              </div>
            </VStack>
            <div
              css={css({
                display: 'flex',
                marginTop: '2px',
                justifyContent: 'space-between',
                flexWrap: 'wrap',
                gap: '12px',
              })}
            >
              <Button variant="secondary" onClick={addClass}>
                {__('Add class')}
              </Button>
              <Button
                css={css({
                  width: 'max-content',
                })}
                variant="primary"
                onClick={onPrimary}
                disabled={isLoading}
                isBusy={isLoading}
              >
                {__('Save classes')}
              </Button>
            </div>
          </VStack>
        </Modal>
      )}
    </>
  );
};
