type BuilderAttributes = {
  blockstudio: {
    data: {
      attributes: {
        attribute: string;
        value: string;
        data?: {
          media?: number;
        };
      }[];
      className: string;
      className__temporary: string;
      tag: string;
      tagCustom: boolean;
      content: string;
    };
  };
};

type BuilderContainerTags =
  | 'div'
  | 'section'
  | 'article'
  | 'aside'
  | 'header'
  | 'footer'
  | 'nav'
  | 'main'
  | 'a';
type BuilderElementTags = 'img' | 'input' | 'textarea' | 'select' | 'hr';
type BuilderTextTags = 'p' | 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6' | 'span';

export type {
  BuilderAttributes,
  BuilderContainerTags,
  BuilderElementTags,
  BuilderTextTags,
};
