export const findCssRules = (selector: string): string[] => {
  const stylesheets = document.styleSheets;

  selector = selector.replace(/([^\w-])/g, '\\$1');

  const rulesList: string[] = [];

  for (let i = 0; i < stylesheets.length; i++) {
    const rules =
      (stylesheets[i] as CSSStyleSheet).cssRules ||
      (stylesheets[i] as any).rules;

    if (rules) {
      for (let j = 0; j < rules.length; j++) {
        const rule = rules[j];

        if (rule.type === CSSRule.STYLE_RULE) {
          const styleRule = rule as CSSStyleRule;
          if (styleRule.selectorText) {
            const matchedSelectors = styleRule.selectorText
              .split(',')
              .map((s) => s.trim());
            if (matchedSelectors.includes(selector)) {
              rulesList.push(styleRule.cssText);
            }
          }
        }
      }
    }
  }

  return rulesList;
};
