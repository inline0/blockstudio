import { throttle } from 'lodash-es';

const disableSelect = (button: HTMLElement) => {
  button.style.pointerEvents = 'none';
  button.style.opacity = '0.5';
};

const enableSelect = (button: HTMLElement) => {
  button.style.pointerEvents = 'auto';
  button.style.opacity = '1';
};

export const mediaModal = () => {
  new MutationObserver(
    throttle(() => {
      const id = 'blockstudio-function__media';
      const modal = document.querySelector(`[class*=${id}]`);

      if (modal) {
        const classes = modal.classList.value.split(' ');
        const min = Number(
          classes.find((e) => e.startsWith(`${id}-min`)).split(`${id}-min-`)[1]
        );
        const max = Number(
          classes.find((e) => e.startsWith(`${id}-max`)).split(`${id}-max-`)[1]
        );

        const all = document.querySelectorAll('.attachment');
        const selected = document.querySelectorAll('.attachment.selected');
        const notSelected = document.querySelectorAll(
          '.attachment:not(.selected)'
        );
        const selectButton = document.querySelector(
          '.media-button-select'
        ) as HTMLElement;

        if (typeof min === 'number') {
          if (selected.length < min) {
            disableSelect(selectButton);
          } else {
            enableSelect(selectButton);
          }
        }

        if (typeof max === 'number') {
          if (selected.length >= max) {
            notSelected.forEach((item) => {
              (item as HTMLElement).style.pointerEvents = 'none';
              (item as HTMLElement).style.opacity = '0.5';
            });

            if (selected.length > max) {
              disableSelect(selectButton);
            } else {
              enableSelect(selectButton);
            }
          } else {
            all.forEach((item) => {
              (item as HTMLElement).style.pointerEvents = 'auto';
              (item as HTMLElement).style.opacity = '1';
            });
          }
        }
      }
    }, 500)
  ).observe(document.documentElement, {
    childList: true,
    subtree: true,
    attributes: true,
  });
};
