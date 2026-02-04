<div>
  <h1>Blade Template Test Page</h1>
  <p>This page uses a Blade template with {{ strtoupper("Blade") }} support.</p>

  <h2>Text Blocks</h2>
  <p>Paragraph with <strong>bold</strong> and <em>italic</em> text.</p>

  <h3>Dynamic Content</h3>
  <p>Current year: {{ date("Y") }}</p>

  <h3>Lists</h3>
  <ul>
    @for ($i = 1; $i <= 3; $i++)
    <li>Blade item {{ $i }}</li>
    @endfor
  </ul>

  <h2>Block Syntax</h2>
  <block name="core/buttons">
    <block name="core/button" url="#">Blade Button</block>
  </block>

  <block name="core/columns">
    <block name="core/column">
      <h4>Column A</h4>
      <p>Blade column content.</p>
    </block>
    <block name="core/column">
      <h4>Column B</h4>
      <p>Second column.</p>
    </block>
  </block>
</div>
