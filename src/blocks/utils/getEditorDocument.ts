export const getEditorDocument = (): Document => {
  let doc: Document = document;

  const hasIframe = (document.querySelector('.editor-canvas__iframe') ||
    document.querySelector(
      '.edit-site-visual-editor__editor-canvas'
    )) as HTMLIFrameElement | null;
  if (hasIframe?.contentDocument) doc = hasIframe.contentDocument;

  return doc;
};
