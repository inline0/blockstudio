(function (wp) {
  const el = wp.element.createElement;
  const TextControl = wp.components.TextControl;

  window.blockstudio.registerFieldType('test/dimensions', {
    component(props) {
      const value =
        props.value &&
        typeof props.value === 'object' &&
        !Array.isArray(props.value)
          ? props.value
          : {};
      const sides = props.sides || ['top', 'right', 'bottom', 'left'];

      return el(
        'div',
        {
          'data-testid': 'custom-dimensions-field',
        },
        sides.map((side) =>
          el(TextControl, {
            key: side,
            label: side.charAt(0).toUpperCase() + side.slice(1),
            value: value[side] || '',
            onChange(nextValue) {
              props.onChange({
                ...value,
                [side]: nextValue,
              });
            },
          }),
        ),
      );
    },
  });
})(window.wp);
