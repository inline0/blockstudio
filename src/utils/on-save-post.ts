import { select, subscribe } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';

interface EditorStore {
  isSavingPost: () => boolean;
  isAutosavingPost: () => boolean;
  isEditedPostSaveable: () => boolean;
  isPostSavingLocked: () => boolean;
  hasNonPostEntityChanges: () => boolean;
  getCurrentPostType: () => string | null;
}

const getEditorStore = () => select('core/editor') as EditorStore | undefined;

const saving = () => {
  const editor = getEditorStore();

  if (!editor) {
    return false;
  }

  const isSaving = editor.isSavingPost() || editor.isAutosavingPost();
  const isSaveable = editor.isEditedPostSaveable();
  const isPostSavingLocked = editor.isPostSavingLocked();
  const hasNonPostEntityChanges = editor.hasNonPostEntityChanges();
  const isAutoSaving = editor.isAutosavingPost();
  const isButtonDisabled = isSaving || !isSaveable || isPostSavingLocked;
  const isBusy = !isAutoSaving && isSaving;
  const isNotInteractable = isButtonDisabled && !hasNonPostEntityChanges;

  return isBusy && isNotInteractable;
};

export const onSavePost = (cb = () => {}) => {
  domReady(() => {
    let wasSaving = saving();

    subscribe(() => {
      const isSaving = saving();
      const isDoneSaving = wasSaving && !isSaving;
      wasSaving = isSaving;

      if (isDoneSaving) {
        const postType = getEditorStore()?.getCurrentPostType();
        if (!postType) return;
        cb();
      }
    });
  });
};
