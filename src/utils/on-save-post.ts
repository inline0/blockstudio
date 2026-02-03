import { select, subscribe } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';

const saving = () => {
  const isSaving =
    select('core/editor').isSavingPost() ||
    select('core/editor').isAutosavingPost();
  const isSaveable = select('core/editor').isEditedPostSaveable();
  const isPostSavingLocked = select('core/editor').isPostSavingLocked();
  const hasNonPostEntityChanges =
    select('core/editor').hasNonPostEntityChanges();
  const isAutoSaving = select('core/editor').isAutosavingPost();
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
        const postType = select('core/editor')?.getCurrentPostType();
        if (!postType) return;
        cb();
      }
    });
  });
};
