export const checkForBlockProps = (htmlString: string, component = false) => {
  htmlString = htmlString.replace(/<!--.*?-->/gs, '');

  const parser = new DOMParser();
  const doc = parser.parseFromString(htmlString, 'text/html');
  const innerblocks = doc.body.querySelector(':scope > innerblocks');
  const richtext = doc.body.querySelector(':scope > richtext');
  const firstElement = doc.body.firstElementChild;
  const elementsScope = doc.body.querySelectorAll(':scope > *');
  const useblockprops = doc.body.querySelectorAll('[useblockprops="true"]');

  if (elementsScope.length !== 1 || useblockprops.length !== 1) {
    return false;
  }

  if (component) {
    return (
      (innerblocks || richtext) && firstElement.hasAttribute('useblockprops')
    );
  }

  return (
    !innerblocks && !richtext && firstElement.hasAttribute('useblockprops')
  );
};
