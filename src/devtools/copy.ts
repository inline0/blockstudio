const PREVIEW_ATTR_VALUE_MAX_LENGTH = 15;
const PREVIEW_TEXT_MAX_LENGTH = 100;

const shortenPath = (fullPath: string): string => {
  const parts = fullPath.replace(/\\/g, '/').split('/');
  const themeOrPluginIdx = parts.findIndex(
    (p) => p === 'themes' || p === 'plugins',
  );
  if (themeOrPluginIdx >= 0 && themeOrPluginIdx + 1 < parts.length) {
    return parts.slice(themeOrPluginIdx + 1).join('/');
  }
  return parts.slice(-3).join('/');
};

const truncateValue = (value: string, maxLength: number): string => {
  return value.length > maxLength ? `${value.slice(0, maxLength)}...` : value;
};

const getTagName = (element: Element): string => {
  return (element.tagName || '').toLowerCase();
};

const formatChildElements = (elements: Element[]): string => {
  if (elements.length === 0) return '';
  if (elements.length <= 2) {
    return elements.map((el) => `<${getTagName(el)} ...>`).join('\n  ');
  }
  return `(${elements.length} elements)`;
};

const getHTMLPreview = (element: Element, tagName: string): string => {
  if (!(element instanceof HTMLElement)) {
    return `<${tagName} />`;
  }

  let attrsText = '';
  for (const { name, value } of element.attributes) {
    if (name.startsWith('data-blockstudio')) continue;
    attrsText += ` ${name}="${truncateValue(value, PREVIEW_ATTR_VALUE_MAX_LENGTH)}"`;
  }

  const topElements: Element[] = [];
  const bottomElements: Element[] = [];
  let foundFirstText = false;

  for (const node of element.childNodes) {
    if (node.nodeType === Node.COMMENT_NODE) continue;
    if (node.nodeType === Node.TEXT_NODE) {
      if (node.textContent && node.textContent.trim().length > 0) {
        foundFirstText = true;
      }
    } else if (node instanceof Element) {
      if (!foundFirstText) {
        topElements.push(node);
      } else {
        bottomElements.push(node);
      }
    }
  }

  const text =
    element.innerText?.trim() ?? element.textContent?.trim() ?? '';
  const truncatedText = truncateValue(text, PREVIEW_TEXT_MAX_LENGTH);

  let content = '';
  const topStr = formatChildElements(topElements);
  if (topStr) content += `\n  ${topStr}`;
  if (truncatedText.length > 0) content += `\n  ${truncatedText}`;
  const bottomStr = formatChildElements(bottomElements);
  if (bottomStr) content += `\n  ${bottomStr}`;

  if (content.length > 0) {
    return `<${tagName}${attrsText}>${content}\n</${tagName}>`;
  }
  return `<${tagName}${attrsText} />`;
};

const buildCopyContent = (
  targetElement: Element,
  tagName: string,
  path: string,
): string => {
  const htmlPreview = getHTMLPreview(targetElement, tagName);
  const shortPath = shortenPath(path);
  return `@<${tagName}>\n\n${htmlPreview}\n  in ${shortPath}`;
};

const copyToClipboard = async (text: string): Promise<boolean> => {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch {
    return false;
  }
};

export { copyToClipboard, buildCopyContent, shortenPath };
