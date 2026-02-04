document.addEventListener(
  'blockstudio/blockstudio/events/attributes/select/update',
  ({ detail }) => {
    // Get data from event.
    const { clientId, value } = detail;

    // Get current block and InnerBlocks.
    const currentBlock = wp.data
      .select('core/block-editor')
      .getBlocksByClientId(clientId)[0];
    const childBlocks = currentBlock.innerBlocks;

    // Remove current InnerBlocks.
    const clientIds = childBlocks.map((block) => block.clientId);
    clientIds.forEach((item) => {
      wp.data.dispatch('core/block-editor').removeBlock(item);
    });

    // Add new layout depending on current value.
    if (value.value === 'layout-1') {
      const heading = wp.blocks.createBlock('core/heading', {
        content: 'This is the first layout',
      });
      const paragraph = wp.blocks.createBlock('core/paragraph', {
        content: 'This is a paragraph from the first layout',
      });
      wp.data.dispatch('core/editor').insertBlock(heading, 0, clientId);
      wp.data.dispatch('core/editor').insertBlock(paragraph, 1, clientId);
    } else if (value.value === 'layout-2') {
      const heading = wp.blocks.createBlock('core/heading', {
        content: 'This is the second layout',
      });
      const heading2 = wp.blocks.createBlock('core/heading', {
        content: 'With another heading',
        level: 3,
      });
      const paragraph = wp.blocks.createBlock('core/paragraph', {
        content: 'This is a paragraph from the second layout',
      });
      wp.data.dispatch('core/editor').insertBlock(heading, 0, clientId);
      wp.data.dispatch('core/editor').insertBlock(heading2, 1, clientId);
      wp.data.dispatch('core/editor').insertBlock(paragraph, 2, clientId);
    }
  }
);
