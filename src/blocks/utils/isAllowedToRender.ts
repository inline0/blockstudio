import { isObject } from 'lodash-es';
import { BlockstudioAttribute, BlockstudioCondition } from '@/types/block';
import { BlockstudioBlock, BlockstudioBlockAttributes } from '@/types/types';

const blockstudioData = window.blockstudioAdmin;

export const isAllowedToRender = (
  item: BlockstudioAttribute,
  attributes: BlockstudioBlockAttributes | boolean = false,
  outerAttributes = false
) => {
  if (item?.hidden) {
    return false;
  }

  if (item?.type === 'richtext') {
    return false;
  }

  if (!item?.conditions?.length) {
    return true;
  }

  try {
    const results = [];
    item.conditions.forEach((condition: BlockstudioCondition[]) => {
      const resultsInner = [];

      condition.forEach((innerCondition: BlockstudioCondition) => {
        if (!innerCondition?.operator) {
          return;
        }

        const valueOrVal = (val: any) => {
          if (isObject(val) && 'value' in val) {
            return val.value;
          }

          return val;
        };

        const operators: {
          [key: string]: (checkValue: any, value: any) => boolean;
        } = {
          '==': (checkValue: boolean, value: boolean) =>
            valueOrVal(checkValue) === value,
          '!=': (checkValue: boolean, value: boolean) =>
            valueOrVal(checkValue) !== value,
          includes: (checkValue: string[], value: string) =>
            valueOrVal(checkValue) && valueOrVal(checkValue).includes(value),
          '!includes': (checkValue: string[], value: string) =>
            valueOrVal(checkValue) && !valueOrVal(checkValue).includes(value),
          empty: (checkValue: string) =>
            valueOrVal(checkValue) && valueOrVal(checkValue) === '',
          '!empty': (checkValue: string) =>
            valueOrVal(checkValue) && valueOrVal(checkValue) !== '',
          '<': (checkValue: string, value: string) =>
            parseInt(valueOrVal(checkValue)) < parseInt(value),
          '>': (checkValue: string, value: string) =>
            parseInt(valueOrVal(checkValue)) > parseInt(value),
          '<=': (checkValue: string, value: string) =>
            parseInt(valueOrVal(checkValue)) <= parseInt(value),
          '>=': (checkValue: string, value: string) =>
            parseInt(valueOrVal(checkValue)) >= parseInt(value),
        };

        const attr =
          innerCondition?.context === 'main'
            ? outerAttributes
            : attributes || false;

        if (
          (innerCondition?.type && blockstudioData?.[innerCondition.type]) ||
          innerCondition?.id
        ) {
          resultsInner.push(
            operators[innerCondition?.operator](
              blockstudioData?.[innerCondition.type] ||
                (attr &&
                  ((attr as unknown as BlockstudioBlock).blockstudio
                    .attributes?.[innerCondition?.id]?.value ||
                    (attr as unknown as BlockstudioBlock).blockstudio
                      .attributes?.[innerCondition?.id])),
              innerCondition?.value
            )
          );
        }
      });

      results.push(resultsInner.filter((e) => !e).length === 0);
    });

    // Don't simplify.
    if (!results.includes(true)) {
      return false;
    }

    return true;
  } catch {
    return true;
  }
};
