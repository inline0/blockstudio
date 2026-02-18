<div>
  <h1>Core Blocks Test Page</h1>
  <p>This page tests all supported HTML to block conversions.</p>

  <h2>Text Blocks</h2>

  <h3>Paragraph</h3>
  <p>This is a paragraph with <strong>bold</strong> and <em>italic</em> text.</p>

  <h3>Headings</h3>
  <h1>Heading Level 1</h1>
  <h2>Heading Level 2</h2>
  <h3>Heading Level 3</h3>
  <h4>Heading Level 4</h4>
  <h5>Heading Level 5</h5>
  <h6>Heading Level 6</h6>

  <h3>Lists</h3>
  <ul>
    <li>Unordered item one</li>
    <li>Unordered item two</li>
    <li>Unordered item three</li>
  </ul>
  <ol>
    <li>Ordered item one</li>
    <li>Ordered item two</li>
    <li>Ordered item three</li>
  </ol>

  <h3>Quote</h3>
  <blockquote>
    <p>This is a blockquote with some quoted text.</p>
  </blockquote>

  <h3>Pullquote</h3>
  <block name="core/pullquote">
    <p>This is a pullquote for emphasis.</p>
  </block>

  <h3>Code</h3>
  <code>const hello = "world";</code>

  <h3>Preformatted</h3>
  <pre>This is preformatted text
    with preserved whitespace
      and indentation.</pre>

  <h3>Verse</h3>
  <block name="core/verse">Roses are red,
Violets are blue,
This is a verse block,
Just for you.</block>

  <h2>Media Blocks</h2>

  <h3>Image</h3>
  <img src="https://picsum.photos/800/400" alt="Sample image" />

  <h3>Gallery</h3>
  <block name="core/gallery">
    <img src="https://picsum.photos/400/300?1" alt="Gallery image 1" />
    <img src="https://picsum.photos/400/300?2" alt="Gallery image 2" />
    <img src="https://picsum.photos/400/300?3" alt="Gallery image 3" />
  </block>

  <h3>Audio</h3>
  <audio src="https://example.com/sample.mp3"></audio>

  <h3>Video</h3>
  <video src="https://example.com/sample.mp4"></video>

  <h3>Cover</h3>
  <block name="core/cover" url="https://picsum.photos/1200/600">
    <h2>Cover Block Title</h2>
    <p>Content inside a cover block with background image.</p>
  </block>

  <h3>Embed</h3>
  <block name="core/embed" url="https://www.youtube.com/watch?v=dQw4w9WgXcQ" providerNameSlug="youtube" />

  <h2>Design Blocks</h2>

  <h3>Group</h3>
  <div>
    <p>This paragraph is inside a group.</p>
    <p>Another paragraph in the same group.</p>
  </div>

  <h3>Row (Flex Layout)</h3>
  <block name="core/group" layout='{"type":"flex","flexWrap":"nowrap"}'>
    <p>Item 1</p>
    <p>Item 2</p>
    <p>Item 3</p>
  </block>

  <h3>Stack (Vertical Flex)</h3>
  <block name="core/group" layout='{"type":"flex","orientation":"vertical"}'>
    <p>Stacked item 1</p>
    <p>Stacked item 2</p>
    <p>Stacked item 3</p>
  </block>

  <h3>Columns</h3>
  <block name="core/columns">
    <block name="core/column">
      <h4>Column 1</h4>
      <p>Content in the first column.</p>
    </block>
    <block name="core/column">
      <h4>Column 2</h4>
      <p>Content in the second column.</p>
    </block>
    <block name="core/column">
      <h4>Column 3</h4>
      <p>Content in the third column.</p>
    </block>
  </block>

  <h3>Separator</h3>
  <hr />

  <h3>Spacer</h3>
  <block name="core/spacer" height="50px" />

  <h3>Buttons</h3>
  <block name="core/buttons">
    <block name="core/button" url="https://example.com">Primary Button</block>
    <block name="core/button" url="https://example.com/secondary">Secondary Button</block>
  </block>

  <h2>Interactive Blocks</h2>

  <h3>Details (Accordion)</h3>
  <details>
    <summary>Click to expand</summary>
    <p>This is the hidden content that appears when you click the summary.</p>
  </details>

  <h3>Table</h3>
  <table>
    <thead>
      <tr>
        <th>Header 1</th>
        <th>Header 2</th>
        <th>Header 3</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Row 1, Cell 1</td>
        <td>Row 1, Cell 2</td>
        <td>Row 1, Cell 3</td>
      </tr>
      <tr>
        <td>Row 2, Cell 1</td>
        <td>Row 2, Cell 2</td>
        <td>Row 2, Cell 3</td>
      </tr>
    </tbody>
  </table>

  <h2>Social Blocks</h2>

  <h3>Social Links</h3>
  <block name="core/social-links">
    <block name="core/social-link" service="wordpress" url="https://wordpress.org" />
    <block name="core/social-link" service="github" url="https://github.com" />
    <block name="core/social-link" service="twitter" url="https://twitter.com" />
  </block>

  <h2>Media & Text</h2>

  <h3>Media Text</h3>
  <block name="core/media-text" mediaurl="https://picsum.photos/600/400" mediatype="image">
    <h3>Media & Text Content</h3>
    <p>This is the text content next to the media.</p>
  </block>

  <h2>Accordion Blocks</h2>

  <h3>Accordion</h3>
  <block name="core/accordion">
    <block name="core/accordion-item">
      <block name="core/accordion-heading">First Accordion Item</block>
      <block name="core/accordion-panel">
        <p>Content inside the first accordion panel.</p>
      </block>
    </block>
    <block name="core/accordion-item">
      <block name="core/accordion-heading">Second Accordion Item</block>
      <block name="core/accordion-panel">
        <p>Content inside the second accordion panel.</p>
      </block>
    </block>
  </block>

  <h2>Query Blocks</h2>

  <h3>Query</h3>
  <block name="core/query">
    <p>Query loop placeholder content.</p>
  </block>

  <h3>Comments</h3>
  <block name="core/comments">
    <p>Comments placeholder content.</p>
  </block>

  <h2>Generic Block Syntax</h2>

  <h3>Using block element</h3>
  <block name="core/paragraph">
    This paragraph was created using the generic block syntax.
  </block>

  <block name="core/heading" level="3">
    Generic Heading Block
  </block>

  <block name="core/group">
    <p>Nested content inside a generic group block.</p>
    <p>Multiple paragraphs work too.</p>
  </block>

  <h2>Additional Parser Paths</h2>

  <h3>Section as Group</h3>
  <section>
    <p>This paragraph is inside a section group.</p>
    <p>Sections map to the same group block as divs.</p>
  </section>

  <h3>Figure with Image</h3>
  <figure>
    <img src="https://picsum.photos/600/300" alt="Figure image" />
  </figure>

  <h3>Unknown HTML Element</h3>
  <marquee>This unknown element becomes a raw HTML block.</marquee>

  <h3>Figure without Image</h3>
  <figure>
    <figcaption>A figure without an img falls back to raw HTML.</figcaption>
  </figure>

  <h3>Blockstudio Block with Defaults</h3>
  <block name="blockstudio/type-preload-simple" />

  <h3>Blockstudio Block with Values</h3>
  <block name="blockstudio/type-preload-simple" title="Page Value" count="99" active="true" />

  <h3>Blockstudio Block No Defaults</h3>
  <block name="blockstudio/type-preload-no-defaults" title="Explicit" count="42" active="false" />

  Bare text becomes an auto-wrapped paragraph.

  <h2>Pagination Blocks</h2>

  <h3>More</h3>
  <block name="core/more" />

  <h3>Page Break</h3>
  <block name="core/nextpage" />
</div>