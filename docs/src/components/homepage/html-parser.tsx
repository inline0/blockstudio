import { Code } from "lucide-react";
import { Button } from "onedocs";
import { Section, SectionIcon } from "./section";
import { Feature } from "./feature";
import { CodeCard } from "./code-card";

const templateCode = `<h1>About Us</h1>
<p>We build tools for developers.</p>

<block name="core/columns">
  <block name="core/column">
    <h2>Our Mission</h2>
    <p>Making block development simple.</p>
  </block>
  <block name="core/column">
    <img src="/team.jpg" alt="Team" />
  </block>
</block>`;

const rendererCode = `add_filter(
  'blockstudio/parser/renderers',
  function ( $renderers, $parser ) {
    $renderers['acme/hero'] = function (
      $element, $attrs, $parser
    ) {
      $content = $parser->get_inner_html(
        $element
      );
      $html = '<div class="acme-hero">'
        . $content . '</div>';

      return array(
        'blockName'    => 'acme/hero',
        'attrs'        => $attrs,
        'innerBlocks'  => array(),
        'innerHTML'    => $html,
        'innerContent' => array( $html ),
      );
    };

    return $renderers;
  },
  10,
  2
);`;

export async function HtmlParser() {
  return (
    <Section
      icon={<SectionIcon><Code /></SectionIcon>}
      title="HTML-to-block parser"
      description="Write standard HTML, get native WordPress blocks. Extend the parser with custom renderers for any block type."
    >
      <div className="flex flex-col gap-10 px-6 lg:px-10">
        <Feature
          headline="Standard HTML maps to core blocks"
          description={
            <p>
              Elements like{" "}
              <code className="text-fd-foreground font-mono text-sm">
                {"<h1>"}
              </code>
              ,{" "}
              <code className="text-fd-foreground font-mono text-sm">
                {"<p>"}
              </code>
              ,{" "}
              <code className="text-fd-foreground font-mono text-sm">
                {"<img>"}
              </code>
              , and{" "}
              <code className="text-fd-foreground font-mono text-sm">
                {"<ul>"}
              </code>
              {" "}convert to their corresponding core blocks automatically.
              Use the{" "}
              <code className="text-fd-foreground font-mono text-sm">
                {"<block>"}
              </code>
              {" "}tag for any registered block type with full attribute support.
            </p>
          }
          demo={<CodeCard code={templateCode} lang="html" />}
        />
        <Feature
          headline="Extensible with custom renderers"
          description={
            <p>
              Register your own block renderers with a single filter. Each
              renderer receives the DOM element, parsed attributes, and the
              parser instance for helper methods. Works with any block type,
              including third-party blocks.
            </p>
          }
          cta={
            <Button href="/docs/pages" className="w-max">
              Learn more &rarr;
            </Button>
          }
          demo={<CodeCard code={rendererCode} lang="php" />}
        />
      </div>
    </Section>
  );
}
