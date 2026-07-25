import { mystery, store } from '@wordpress/interactivity';
import helper from 'npm:example-helper@^1.0.0';

console.log('debug');
alert('blocking');
leakedValue = true;

const root = document.querySelector('.card');
root.addEventListener('click', () => {
  document.write('unsafe');
  setTimeout('tick()', 10);
  const request = new XMLHttpRequest();
  request.open('GET', '/endpoint', false);
  jQuery('.legacy');
  requestAnimationFrame(() => true);
});

const missing = document.querySelector('[data-missing-contract]');
missing.focus();

document.addEventListener('DOMContentLoaded', helper);
document.addEventListener('DOMContentLoaded', helper);

store('example/wrong', {
  state: {
    derived: () => true,
  },
  actions: {
    existing() {
      root.setAttribute('aria-expanded', 'true');
      return mystery;
    },
  },
});
