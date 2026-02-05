<div>
  <h2>Blade Pattern Heading</h2>
  <p>This pattern uses {{ strtoupper("Blade") }} template.</p>
  <block name="core/buttons">
    <block name="core/button" url="#">Blade Pattern Button</block>
  </block>
  <hr />
  <ul>
    @for ($i = 1; $i <= 2; $i++)
    <li>Blade pattern item {{ $i }}</li>
    @endfor
  </ul>
  <blockquote>
    <p>Blade pattern quote text.</p>
  </blockquote>
  <img src="https://picsum.photos/400/200" alt="Blade pattern image" />
  <block name="core/columns">
    <block name="core/column">
      <p>Blade pattern column A</p>
    </block>
    <block name="core/column">
      <p>Blade pattern column B</p>
    </block>
  </block>
</div>
