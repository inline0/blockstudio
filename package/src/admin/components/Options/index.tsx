import { SetStateAction } from 'react';
import { json } from '@codemirror/lang-json';
import { githubLight } from '@uiw/codemirror-theme-github';
import CodeMirror from '@uiw/react-codemirror';
import apiFetch from '@wordpress/api-fetch';
import {
  BaseControl,
  Button,
  CardBody,
  CardFooter,
  ExternalLink,
  Flex,
  FormTokenField,
  Panel,
  PanelBody,
  TextControl,
  TextareaControl,
  ToggleControl,
  __experimentalInputControl as InputControl,
  __experimentalNumberControl as NumberControl,
  __experimentalText as Text,
} from '@wordpress/components';
import { TokenItem } from '@wordpress/components/build-types/form-token-field/types';
import { useDispatch, useSelect } from '@wordpress/data';
import { useCallback, useState } from '@wordpress/element';
import { cloneDeep, debounce, get, mergeWith, set } from 'lodash-es';
import parserBabel from 'prettier/esm/parser-babel.mjs';
import prettier from 'prettier/esm/standalone.mjs';
import { Card } from '@/admin/components/Card';
import { colors } from '@/admin/const/colors';
import { ConditionalWrapper } from '@/components/ConditionalWrapper';
import { style } from '@/const/index';
import { selectors } from '@/editor/store/selectors';
import { CustomClasses } from '@/tailwind/components/CustomClasses';
import { useTailwindSaveEditor } from '@/tailwind/useTailwindSaveEditor';
import { Any } from '@/type/types';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';
import { downloadFile } from '@/utils/downloadFile';
import { uniqueObjectArrayBy } from '@/utils/uniqueObjectArrayBy';

const users = window.blockstudioAdmin.optionsUsers;
const roles = window.blockstudioAdmin.optionsRoles;

const scripts = window.blockstudioAdmin.data.scripts;
const styles = window.blockstudioAdmin.data.styles;
const assets = [...new Set([...Object.keys(styles), ...Object.keys(scripts)])];
const assetsStyles = [...new Set(Object.keys(styles))];

const Component = ({
  filterSettings,
  isRootLevel = false,
  options,
  path = '',
  schema,
  setState,
  state,
}: {
  filterSettings: object;
  isRootLevel?: boolean;
  options: Any;
  path?: string;
  schema: {
    description?: string;
    element?: string;
    help?: string;
    id?: string;
    json?: boolean;
    properties?: object;
    type?: string | string[];
  };
  setState: (value: SetStateAction<object>) => void;
  state: object;
}) => {
  const blockstudio = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors)?.getBlockstudio(),
    []
  );
  const [data, setData] = useState([]);
  const description = schema.description?.slice(0, -1);
  const disabled = () => {
    const data = get(filterSettings, path);

    if (schema?.type === 'array') {
      return data?.length !== 0;
    }

    return (
      `blockstudio.settings.${path}`.replaceAll('.', '/') in
      blockstudio.optionsFiltersValues
    );
  };

  const getState = (text = false) => {
    const getter = get(state, path);
    const getterFilter = get(filterSettings, path);

    let value =
      getterFilter && getterFilter !== '' && getterFilter?.length !== 0
        ? getterFilter
        : getter;

    if (schema.id === 'userRoles') {
      value = value.filter((e: string) => roles.includes(e));
    }

    if (schema.id === 'editorAssets') {
      value = value.filter((e: string) => assets.includes(e));
    }

    if (
      ['blockEditorCssClasses', 'blockEditorCssVariables'].includes(schema.id)
    ) {
      value = value?.filter((e: string) => assetsStyles.includes(e));
    }

    return text && !value ? '' : value;
  };

  const updateState = (
    e: string | number[] | string[] | boolean | TokenItem[]
  ) => {
    let value = schema?.type === 'string' && e === '' ? false : e;

    if (schema?.id === 'userIds') {
      value = (value as string[]).map((e: string) => Number(e));
    }

    const newState = cloneDeep(state);

    set(newState, path, value);
    setState(newState);
  };

  const userQuery = async (value: string) => {
    let data = (await apiFetch({
      path: `/wp/v2/users?search=${value}`,
    })) as {
      id: number;
    }[];
    data = data.map((e) => {
      return {
        ...e,
        ID: `${e.id}`,
      };
    });

    const allUsers = [...users, ...data] as [];
    window.blockstudioAdmin.optionsUsers = uniqueObjectArrayBy(allUsers, 'ID');
    setData(data);
  };

  const debouncedUserQuery = useCallback(
    debounce((value: string) => userQuery(value), 500),
    []
  );

  const type = schema?.type;

  if (type === 'boolean') {
    const isAiMd = schema.description.includes('context');
    return (
      <Flex gap={4} direction="column">
        <ToggleControl
          css={css({
            marginBottom: '0',
            '& > div > div': {
              'align-items': 'flex-start',
            },
          })}
          label={__(description)}
          checked={getState()}
          onChange={(e) => updateState(e)}
          disabled={disabled()}
        />
        {isAiMd && options?.ai?.enableContextGeneration && (
          <>
            <Flex>
              <Button
                variant="secondary"
                onClick={() =>
                  downloadFile(
                    window.blockstudioAdmin.llmTxtUrl,
                    'blockstudio-llm.txt'
                  )
                }
              >
                {__('Download the compiled .txt file')}
              </Button>
              <ExternalLink href={window.blockstudioAdmin.llmTxtUrl}>
                {__('View the compiled .txt file')}
              </ExternalLink>
            </Flex>
            <div>
              {getState() && (
                <div
                  css={css({
                    display: 'flex',
                    flexDirection: 'column',
                    gap: '4px',
                  })}
                >
                  <InputControl
                    css={css({
                      width: '100%',
                    })}
                    value={window.blockstudioAdmin.llmTxtUrl}
                    disabled
                  />
                  <Text>
                    {__(
                      'The above link will contain an always up-to-date context file.'
                    )}
                  </Text>
                </div>
              )}
            </div>
          </>
        )}
      </Flex>
    );
  } else if (
    type === 'string' ||
    (Array.isArray(type) && type.includes('string'))
  ) {
    if (schema.element === 'textarea') {
      return (
        <TextareaControl
          css={css({
            textarea: {
              border: '1px solid #949494',
              boxShadow: 'none',
            },

            '&, .components-base-control__help, .components-base-control__field':
              {
                marginBottom: '0',
              },
          })}
          label={__(description)}
          value={getState(true)}
          onChange={(e) => updateState(e)}
          disabled={disabled()}
        />
      );
    } else {
      return (
        <TextControl
          css={css({
            '&, .components-base-control__help': {
              marginBottom: '0',
            },
          })}
          label={__(description)}
          value={getState(true)}
          onChange={(e) => updateState(e)}
          help={schema.help}
          disabled={disabled()}
        />
      );
    }
  } else if (
    type === 'number' ||
    (Array.isArray(type) && type.includes('number'))
  ) {
    return (
      <NumberControl
        css={css({
          '&, .components-base-control__help': {
            marginBottom: '0',
          },
        })}
        label={__(description)}
        value={getState(true)}
        onChange={(e) => updateState(e)}
        help={schema.help}
        disabled={disabled()}
        min={0}
      />
    );
  } else if (
    type === 'array' ||
    (Array.isArray(type) && type.includes('array'))
  ) {
    return (
      <div
        css={css({
          '.components-spacer': {
            display: 'none',
          },
          '.components-form-token-field__suggestion': {
            minHeight: 0,
          },
        })}
      >
        <FormTokenField
          css={css({
            pointerEvents: disabled() ? 'none' : 'auto',

            '.components-form-token-field__token': {
              opacity: disabled() && '0.5',
            },

            '.components-form-token-field__label': {
              textWrap: 'balance',
            },
          })}
          displayTransform={(value) => {
            if (schema.id === 'userIds') {
              const users = [...window.blockstudioAdmin.optionsUsers, ...data];

              const user = users.find(
                (e: { ID: string }) => e.ID === `${value}`
              );

              if (user) {
                return user?.user_nicename || user?.name;
              }
            }

            if (schema.id === 'editorAssets') {
              if (scripts?.[value]) {
                return `script: ${value}`;
              }

              if (styles?.[value]) {
                return `style: ${value}`;
              }
            }

            if (typeof value === 'string') {
              return value.trim();
            }

            return value;
          }}
          onInputChange={(value) => {
            if (schema.id === 'userIds') {
              debouncedUserQuery(value);
            }
          }}
          saveTransform={(value) => {
            if (schema.id === 'userIds') {
              const id = data.find(
                (e: { name: string }) => e.name === value
              )?.id;

              if (id) {
                return `${id}`;
              }
            }

            return value;
          }}
          onChange={(values) => updateState(values as TokenItem[])}
          label={__(description)}
          value={getState()}
          suggestions={
            schema.id === 'userIds'
              ? data
                  .filter((e: { id: string }) => !getState().includes(e.id))
                  .map((e: { name: string }) => e.name)
              : schema.id === 'userRoles'
              ? roles
              : schema.id === 'editorAssets'
              ? assets
              : ['blockEditorCssClasses', 'blockEditorCssVariables'].includes(
                  schema.id
                )
              ? assetsStyles
              : []
          }
          __experimentalShowHowTo={false}
          __nextHasNoMarginBottom={false}
          __experimentalExpandOnFocus={schema.id !== 'userIds'}
          __experimentalValidateInput={(value) => {
            if (schema.id === 'userIds') {
              return data.find((e: { name: string }) => e.name === value);
            }

            if (schema.id === 'userRoles') {
              return roles.includes(value);
            }

            if (schema.id === 'editorAssets') {
              return scripts?.[value] || styles?.[value];
            }

            if (
              ['blockEditorCssClasses', 'blockEditorCssVariables'].includes(
                schema.id
              )
            ) {
              return styles?.[value];
            }

            return false;
          }}
        />
      </div>
    );
  } else if (type === 'object' && !schema.json) {
    return Object.keys(schema?.properties || {}).map((key, i) => {
      const newPath = path ? `${path}.${key}` : key;
      return (
        <ConditionalWrapper
          key={key}
          condition={isRootLevel}
          wrapper={(children) => (
            <div>
              <Panel
                css={css({
                  border: 'none',
                  padding: 0,
                  '.components-panel__body-title': {
                    margin: 0,
                    '&:hover': {
                      backgroundColor: colors.gray['50'],
                    },
                  },
                  '.components-panel__body-toggle': {
                    padding: '16px 32px',
                    borderTopLeftRadius: i === 0 && '8px',
                    borderTopRightRadius: i === 0 && '8px',
                  },
                })}
              >
                <PanelBody
                  title={key
                    .replace(/([A-Z])/g, ' $1')
                    .trim()
                    .toLowerCase()
                    .replace(/^\w|\s\w/g, (letter) => letter.toUpperCase())
                    .replace('Ai', 'AI')}
                  initialOpen={false}
                  css={css({ padding: 0 })}
                >
                  {children}
                </PanelBody>
              </Panel>
            </div>
          )}
        >
          <div
            css={css({
              display: 'grid',
              gap: '16px',
              padding: isRootLevel && '16px 32px 32px 32px',
            })}
          >
            <Component
              {...{
                filterSettings,
                options,
                setState,
                state,
              }}
              schema={schema.properties[key]}
              path={newPath}
            />
          </div>
        </ConditionalWrapper>
      );
    });
  } else if (type === 'object' && schema.json) {
    return (
      <>
        <BaseControl label={__(description)} help={__(schema.help)}>
          <div
            css={css({
              border: style.border,
              borderRadius: style.borderRadius,
              overflow: 'hidden',
            })}
          >
            <CodeMirror
              value={JSON.stringify(getState(true) || {}, null, 2)}
              onChange={(e) => updateState(JSON.parse(e))}
              basicSetup={{
                autocompletion: true,
                lineNumbers: false,
                foldGutter: false,
              }}
              theme={githubLight}
              extensions={[json()]}
            />
          </div>
        </BaseControl>
        {schema.description.includes('Tailwind') && (
          <CustomClasses
            buttonProps={{
              css: css({
                width: 'max-content',
              }),
              variant: 'secondary',
              children: 'Edit Global Classes',
            }}
          />
        )}
      </>
    );
  } else {
    return null;
  }
};

export const Options = () => {
  const { setOptions } = useDispatch('blockstudio/editor');
  const tailwindSave = useTailwindSaveEditor();
  const blockstudio = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors)?.getBlockstudio(),
    []
  );
  const options = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors)?.getOptions(),
    []
  );
  const filterSettings = blockstudio?.optionsFilters;
  const [isLoading, setIsLoading] = useState(false);
  const [isJson, setIsJson] = useState(blockstudio?.optionsJson !== null);
  const [state, setState] = useState(
    mergeWith(
      {},
      options,
      filterSettings,
      (objValue: boolean, srcValue: boolean) => {
        if (srcValue === false) {
          return objValue;
        }
      }
    )
  );

  if (blockstudio?.allowEditor === 'false') return null;

  const onSave = async () => {
    let newOptions = cloneDeep(state);
    if (newOptions?.$schema) {
      const schema = newOptions?.$schema;
      delete newOptions?.$schema;
      newOptions = {
        $schema: schema,
        ...newOptions,
      };
    }
    setIsLoading(true);
    await apiFetch({
      path: '/blockstudio/v1/editor/options/save',
      method: 'POST',
      data: {
        options: encodeURIComponent(
          prettier.format(JSON.stringify(newOptions), {
            parser: 'json',
            plugins: [parserBabel],
          })
        ),
        json: isJson,
      },
    }).finally(() => {
      setIsLoading(false);
      setOptions(newOptions);
    });
    await tailwindSave();
  };

  return (
    <>
      <Card>
        <CardBody
          css={css({
            padding: '0',
            overflow: 'hidden',
          })}
        >
          <Flex direction="column">
            <div
              css={css({
                display: 'grid',
              })}
            >
              <div
                css={css({
                  display: 'grid',
                })}
              >
                <Component
                  {...{
                    state,
                    setState,
                    options,
                    filterSettings,
                  }}
                  schema={blockstudio.optionsSchema as unknown}
                  isRootLevel={true}
                />
              </div>
            </div>
          </Flex>
        </CardBody>
        <CardFooter
          css={css({
            borderTop: `1px solid ${colors.gray[100]}`,
          })}
        >
          <Flex justify="space-between" align="flex-start">
            <ToggleControl
              label={__('Save as JSON')}
              checked={isJson}
              onChange={(e) => setIsJson(e)}
              help={__(
                'Save options to blockstudio.json in current theme directory'
              )}
            />
            <Button
              variant="primary"
              disabled={isLoading}
              onClick={onSave}
              isBusy={isLoading}
            >
              {__('Save changes')}
            </Button>
          </Flex>
        </CardFooter>
      </Card>
    </>
  );
};
