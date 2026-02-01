export const getEditorDocument = () => {
  let doc = document;

  const hasIframe = (document.querySelector('.editor-canvas__iframe') ||
    document.querySelector(
      '.edit-site-visual-editor__editor-canvas'
    )) as HTMLIFrameElement;
  if (hasIframe) doc = hasIframe.contentDocument;

  return doc;
};
