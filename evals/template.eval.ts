import type { EvalSuite } from "./types.ts";
import { phpSyntax, templateVars } from "./scorers/php-syntax.ts";
import { requiredFiles } from "./scorers/structure.ts";

const suite: EvalSuite = {
  name: "Template Evals",
  cases: [
    {
      name: "Basic $a attribute access",
      prompt:
        'Write a Blockstudio PHP template (index.php) for a block that has "title" (text) and "content" (textarea) fields. Access them via the $a variable. Wrap everything in a div.',
      scorers: [
        phpSyntax(),
        templateVars("$a['title']", "$a['content']"),
        requiredFiles("index.php"),
      ],
    },
    {
      name: "useBlockProps",
      prompt:
        'Write a Blockstudio PHP template for a block with a "heading" text field. The root div element must use the useBlockProps attribute for proper WordPress block integration. Show only the index.php file.',
      scorers: [
        phpSyntax(),
        templateVars("useBlockProps", "$a['heading']"),
      ],
    },
    {
      name: "RichText component",
      prompt:
        'Write a Blockstudio PHP template that uses a RichText component for the "bio" field. The RichText tag should include the attribute prop pointing to the bio field. Show the index.php.',
      scorers: [
        phpSyntax(),
        templateVars("<RichText", "bio"),
      ],
    },
    {
      name: "InnerBlocks component",
      prompt:
        'Write a Blockstudio PHP template that uses InnerBlocks to allow nested blocks. The InnerBlocks component should specify allowedBlocks to only permit core/paragraph and core/heading. Show the index.php.',
      scorers: [
        phpSyntax(),
        templateVars("<InnerBlocks", "allowedBlocks"),
      ],
    },
    {
      name: "Repeater foreach loop",
      prompt:
        'Write a Blockstudio PHP template for a block with a repeater field (id: "items"). Each item has "title" (text) and "url" (url) fields. Loop through the items using foreach and render each as a link inside a list. Show the index.php.',
      scorers: [
        phpSyntax(),
        templateVars("foreach", "$a['items']"),
      ],
    },
    {
      name: "Conditional rendering",
      prompt:
        'Write a Blockstudio PHP template for a block with a "title" text field and a "showSubtitle" toggle. Conditionally render a subtitle section only when the toggle is on. Also show different content in the editor vs frontend using $isEditor. Show the index.php.',
      scorers: [
        phpSyntax(),
        templateVars("$a['title']", "$a['showSubtitle']", "$isEditor"),
      ],
    },
    {
      name: "Group field access (id prefix)",
      prompt:
        'Write a Blockstudio PHP template for a block with a group (id: "settings") containing "color" (color) and "size" (select) fields. Access them using the group id prefix pattern (e.g. $a[\'settings_color\']). Show the index.php.',
      scorers: [
        phpSyntax(),
        templateVars("$a['settings_color']", "$a['settings_size']"),
      ],
    },
    {
      name: "Tailwind classes field",
      prompt:
        'Write a Blockstudio PHP template for a block with a classes field (id: "wrapperClasses"). Apply the classes to the root div element. Make sure to escape the output properly. Show the index.php.',
      scorers: [
        phpSyntax(),
        templateVars("$a['wrapperClasses']"),
      ],
    },
    {
      name: "$b block metadata",
      prompt:
        'Write a Blockstudio PHP template that uses the $b variable to display the block\'s name ($b[\'name\']), directory path ($b[\'dir\']), and current post ID ($b[\'postId\']). Render each value in a paragraph tag. Show the index.php.',
      scorers: [
        phpSyntax(),
        templateVars("$b['name']", "$b['dir']", "$b['postId']"),
      ],
    },
    {
      name: "$c parent context",
      prompt:
        'Write a Blockstudio PHP template for a child block that accesses parent block data via the $c variable. The parent block is "acme/container" and the child should read $c[\'acme/container\'][\'color\'] and $c[\'acme/container\'][\'layout\']. Show the index.php.',
      scorers: [
        phpSyntax(),
        templateVars("$c['acme/container']"),
      ],
    },
    {
      name: "$innerBlocks variable",
      prompt:
        'Write a Blockstudio PHP template that checks if $innerBlocks has content and renders it inside a wrapper div. Use an if statement to conditionally render the inner blocks only when they exist. Show the index.php.',
      scorers: [
        phpSyntax(),
        templateVars("$innerBlocks"),
      ],
    },
    {
      name: "MediaPlaceholder component",
      prompt:
        'Write a Blockstudio PHP template that uses a <MediaPlaceholder> component for the "heroImage" field. The MediaPlaceholder should include the attribute prop pointing to the heroImage field. Show the index.php.',
      scorers: [
        phpSyntax(),
        templateVars("<MediaPlaceholder", "heroImage"),
      ],
    },
    {
      name: "Escape functions",
      prompt:
        'Write a Blockstudio PHP template for a block with "title" (text), "cssClass" (text), and "content" (wysiwyg) fields. Use esc_html() for the title, esc_attr() for the CSS class, and wp_kses_post() for the content. Show the index.php.',
      scorers: [
        phpSyntax(),
        templateVars("esc_html", "esc_attr", "wp_kses_post"),
      ],
    },
    {
      name: "Link field rendering",
      prompt:
        'Write a Blockstudio PHP template for a block with a link field (id: "cta"). Render it as an anchor tag accessing the url, title, and target properties from the link field value ($a[\'cta\'][\'url\'], $a[\'cta\'][\'title\'], $a[\'cta\'][\'target\']). Show the index.php.',
      scorers: [
        phpSyntax(),
        templateVars("$a['cta']", "url", "title", "target"),
      ],
    },
    {
      name: "Files field rendering",
      prompt:
        'Write a Blockstudio PHP template for a block with a files field (id: "gallery"). Loop through the gallery items and render each as an img tag, accessing the url, alt, width, and height properties from each file array. Show the index.php.',
      scorers: [
        phpSyntax(),
        templateVars("$a['gallery']", "foreach", "url", "alt"),
      ],
    },
  ],
};

export default suite;
