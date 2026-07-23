document.addEventListener('blockstudio:island:hydrate', (event) => {
  if (event.target?.dataset?.bsIsland === 'blockstudio/island-hydrated') {
    event.target.dataset.hydratedFixture = '1';
  }
});
