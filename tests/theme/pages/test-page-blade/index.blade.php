<div>
  <h1>Blade Template Test Page</h1>
  <p>This page uses a Blade template with {{ strtoupper("Blade") }} support.</p>

  <h2>Text Blocks</h2>
  <p>Paragraph with <strong>bold</strong> and <em>italic</em> text.</p>

  <h3>Dynamic Content</h3>
  <p>Current year: {{ date("Y") }}</p>

  <h3>Headings</h3>
  <h1>Blade Heading 1</h1>
  <h2>Blade Heading 2</h2>
  <h3>Blade Heading 3</h3>
  <h4>Blade Heading 4</h4>
  <h5>Blade Heading 5</h5>
  <h6>Blade Heading 6</h6>

  <h3>Lists</h3>
  <ul>
    @for ($i = 1; $i <= 3; $i++)
    <li>Blade item {{ $i }}</li>
    @endfor
  </ul>
  <ol>
    @for ($i = 1; $i <= 3; $i++)
    <li>Blade ordered {{ $i }}</li>
    @endfor
  </ol>

  <h3>Quote</h3>
  <blockquote>
    <p>This is a Blade blockquote.</p>
  </blockquote>

  <h3>Pullquote</h3>
  <block name="core/pullquote">
    <p>Blade pullquote for emphasis.</p>
  </block>

  <h3>Code</h3>
  <code>const blade = "template";</code>

  <h3>Preformatted</h3>
  <pre>Blade preformatted text
    with preserved whitespace.</pre>

  <h3>Verse</h3>
  <block name="core/verse">Blade roses are red,
Blade violets are blue.</block>

  <h2>Media Blocks</h2>

  <h3>Image</h3>
  <img src="https://picsum.photos/800/400" alt="Blade sample image" />

  <h3>Gallery</h3>
  <block name="core/gallery">
    @for ($i = 1; $i <= 3; $i++)
    <img src="https://picsum.photos/400/300?blade{{ $i }}" alt="Blade gallery {{ $i }}" />
    @endfor
  </block>

  <h3>Audio</h3>
  <audio src="https://example.com/blade-sample.mp3"></audio>

  <h3>Video</h3>
  <video src="https://example.com/blade-sample.mp4"></video>

  <h3>Cover</h3>
  <block name="core/cover" url="https://picsum.photos/1200/600">
    <h2>Blade Cover Title</h2>
    <p>Content inside a Blade cover block.</p>
  </block>

  <h3>Embed</h3>
  <block name="core/embed" url="https://www.youtube.com/watch?v=dQw4w9WgXcQ" providerNameSlug="youtube" />

  <h2>Design Blocks</h2>

  <h3>Group</h3>
  <div>
    <p>Blade paragraph inside a group.</p>
    <p>Another Blade paragraph in the same group.</p>
  </div>

  <h3>Row</h3>
  <block name="core/group" layout='{"type":"flex","flexWrap":"nowrap"}'>
    @for ($i = 1; $i <= 3; $i++)
    <p>Blade row {{ $i }}</p>
    @endfor
  </block>

  <h3>Stack</h3>
  <block name="core/group" layout='{"type":"flex","orientation":"vertical"}'>
    @for ($i = 1; $i <= 3; $i++)
    <p>Blade stack {{ $i }}</p>
    @endfor
  </block>

  <h3>Columns</h3>
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

  <h3>Separator</h3>
  <hr />

  <h3>Spacer</h3>
  <block name="core/spacer" height="50px" />

  <h3>Buttons</h3>
  <block name="core/buttons">
    <block name="core/button" url="#">Blade Button</block>
  </block>

  <h2>Interactive Blocks</h2>

  <h3>Details</h3>
  <details>
    <summary>Blade expand details</summary>
    <p>Hidden Blade content.</p>
  </details>

  <h3>Table</h3>
  <table>
    <thead>
      <tr>
        <th>Blade Header 1</th>
        <th>Blade Header 2</th>
      </tr>
    </thead>
    <tbody>
      @for ($i = 1; $i <= 2; $i++)
      <tr>
        <td>Blade Row {{ $i }}, Cell 1</td>
        <td>Blade Row {{ $i }}, Cell 2</td>
      </tr>
      @endfor
    </tbody>
  </table>

  <h2>Social Blocks</h2>
  <block name="core/social-links">
    <block name="core/social-link" service="wordpress" url="https://wordpress.org" />
    <block name="core/social-link" service="github" url="https://github.com" />
  </block>

  <h2>Media & Text</h2>
  <block name="core/media-text" mediaurl="https://picsum.photos/600/400" mediatype="image">
    <h3>Blade Media Content</h3>
    <p>Text next to Blade media.</p>
  </block>

  <h2>Accordion Blocks</h2>
  <block name="core/accordion">
    <block name="core/accordion-item">
      <block name="core/accordion-heading">Blade Accordion Item</block>
      <block name="core/accordion-panel">
        <p>Blade accordion panel content.</p>
      </block>
    </block>
  </block>

  <h2>Query Blocks</h2>
  <block name="core/query">
    <p>Blade query placeholder.</p>
  </block>
  <block name="core/comments">
    <p>Blade comments placeholder.</p>
  </block>

  <h2>Additional Parser Paths</h2>

  <h3>Section as Group</h3>
  <section>
    <p>Blade section group paragraph.</p>
  </section>

  <h3>Figure with Image</h3>
  <figure>
    <img src="https://picsum.photos/600/300" alt="Blade figure image" />
  </figure>

  <h3>Unknown HTML Element</h3>
  <marquee>Blade unknown element becomes HTML block.</marquee>

  <h3>Figure without Image</h3>
  <figure>
    <figcaption>Blade figure without img.</figcaption>
  </figure>

  Blade bare text auto-wrapped paragraph.

  <h2>Pagination Blocks</h2>
  <block name="core/more" />
  <block name="core/nextpage" />
</div>
