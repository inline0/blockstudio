import { store } from '@wordpress/interactivity';

store('example/card', {
  actions: {
    open() {
      return true;
    },
  },
});
