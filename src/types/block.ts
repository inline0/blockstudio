// To parse this data:
//
//   import { Convert, Block } from "./file";
//
//   const block = Convert.toBlock(json);
//
// These functions will throw an error if the JSON doesn't
// match the expected interface, even if the JSON is valid.

export interface Block {
    $schema?: string;
    /**
     * The name of the experiment this block is a part of, or boolean true if there is no
     * specific experiment name.
     */
    __experimental?: boolean | string;
    /**
     * The `allowedBlocks` property specifies that only the listed block types can be the
     * children of this block. For example, a ‘List’ block allows only ‘List Item’ blocks as
     * direct children.
     */
    allowedBlocks?: string[];
    /**
     * The `ancestor` property makes a block available inside the specified block types at any
     * position of the ancestor block subtree. That allows, for example, to place a ‘Comment
     * Content’ block inside a ‘Column’ block, as long as ‘Column’ is somewhere within a
     * ‘Comment Template’ block.
     */
    ancestor?: string[];
    /**
     * The version of the Block API used by the block. The most recent version is 3 and it was
     * introduced in WordPress 6.3.
     *
     * See the API versions documentation at
     * https://developer.wordpress.org/block-editor/reference-guides/block-api/block-api-versions/
     * for more details.
     */
    apiVersion?: number;
    /**
     * Attributes provide the structured data needs of a block. They can exist in different
     * forms when they are serialized, but they are declared together under a common interface.
     *
     * See the attributes documentation at
     * https://developer.wordpress.org/block-editor/reference-guides/block-api/block-attributes/
     * for more details.
     */
    attributes?: Attributes;
    /**
     * Block Hooks allow a block to automatically insert itself next to all instances of a given
     * block type.
     *
     * See the Block Hooks documentation at
     * https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/#block-hooks-optional
     * for more details.
     */
    blockHooks?: BlockHooks;
    /**
     * Blockstudio specific settings.
     */
    blockstudio?: boolean | BlockstudioClass;
    /**
     * Blocks are grouped into categories to help users browse and discover them.
     * Core provided categories are: text, media, design, widgets, theme, embed
     *
     * Plugins and Themes can also register custom block categories.
     *
     *
     * https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/#managing-block-categories
     */
    category?: string;
    /**
     * This is a short description for your block, which can be translated with our translation
     * functions. This will be shown in the block inspector.
     */
    description?: string;
    /**
     * Block type editor script definition. It will only be enqueued in the context of the
     * editor.
     */
    editorScript?: string[] | string;
    /**
     * Block type editor style definition. It will only be enqueued in the context of the editor.
     */
    editorStyle?: string[] | string;
    /**
     * It provides structured example data for the block. This data is used to construct a
     * preview for the block to be shown in the Inspector Help Panel when the user mouses over
     * the block.
     *
     * See the example documentation at
     * https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/#example-optional
     * for more details.
     */
    example?: Example;
    /**
     * An icon property should be specified to make it easier to identify a block. These can be
     * any of WordPress’ Dashicons (slug serving also as a fallback in non-js contexts).
     */
    icon?: string;
    /**
     * Sometimes a block could have aliases that help users discover it while searching. For
     * example, an image block could also want to be discovered by photo. You can do so by
     * providing an array of unlimited terms (which are translated).
     */
    keywords?: string[];
    /**
     * The name for a block is a unique string that identifies a block. Names have to be
     * structured as `namespace/block-name`, where namespace is the name of your plugin or theme.
     */
    name: string;
    /**
     * Setting parent lets a block require that it is only available when nested within the
     * specified blocks. For example, you might want to allow an ‘Add to Cart’ block to only be
     * available within a ‘Product’ block.
     */
    parent?: string[];
    /**
     * Context provided for available access by descendants of blocks of this type, in the form
     * of an object which maps a context name to one of the block’s own attribute.
     *
     * See the block context documentation at
     * https://developer.wordpress.org/block-editor/reference-guides/block-api/block-context/
     * for more details.
     */
    providesContext?: { [key: string]: any };
    /**
     * Template file loaded on the server when rendering a block.
     */
    render?: string;
    /**
     * Block type frontend and editor script definition. It will be enqueued both in the editor
     * and when viewing the content on the front of the site.
     */
    script?: string[] | string;
    /**
     * Provides custom CSS selectors and mappings for the block. Selectors may be set for the
     * block itself or per-feature e.g. typography. Custom selectors per feature or sub-feature,
     * allow different block styles to be applied to different elements within the block.
     */
    selectors?: Selectors;
    /**
     * Block type frontend style definition. It will be enqueued both in the editor and when
     * viewing the content on the front of the site.
     */
    style?: string[] | string;
    /**
     * Block styles can be used to provide alternative styles to block. It works by adding a
     * class name to the block’s wrapper. Using CSS, a theme developer can target the class name
     * for the block style if it is selected.
     *
     * Plugins and Themes can also register custom block style for existing blocks.
     *
     * https://developer.wordpress.org/block-editor/reference-guides/block-api/block-styles/
     */
    styles?: Style[];
    /**
     * It contains as set of options to control features used in the editor. See the supports
     * documentation at
     * https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/
     * for more details.
     */
    supports?: Supports;
    /**
     * The gettext text domain of the plugin/block. More information can be found in the Text
     * Domain section of the How to Internationalize your Plugin page.
     *
     *
     * https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/
     */
    textdomain?: string;
    /**
     * This is the display title for your block, which can be translated with our translation
     * functions. The block inserter will show this name.
     */
    title: string;
    /**
     * Array of the names of context values to inherit from an ancestor provider.
     *
     * See the block context documentation at
     * https://developer.wordpress.org/block-editor/reference-guides/block-api/block-context/
     * for more details.
     */
    usesContext?: string[];
    /**
     * Block Variations is the API that allows a block to have similar versions of it, but all
     * these versions share some common functionality.
     */
    variations?: Variation[] | string;
    /**
     * The current version number of the block, such as 1.0 or 1.0.3. It’s similar to how
     * plugins are versioned. This field might be used with block assets to control cache
     * invalidation, and when the block author omits it, then the installed version of WordPress
     * is used instead.
     */
    version?: string;
    /**
     * Block type frontend script definition. It will be enqueued only when viewing the content
     * on the front of the site.
     */
    viewScript?: string[] | string;
    /**
     * Block type frontend script module definition. It will be enqueued only when viewing the
     * content on the front of the site.
     */
    viewScriptModule?: string[] | string;
    /**
     * Block type frontend style definition. It will be enqueued only when viewing the content
     * on the front of the site.
     */
    viewStyle?: string[] | string;
}

/**
 * Attributes provide the structured data needs of a block. They can exist in different
 * forms when they are serialized, but they are declared together under a common interface.
 *
 * See the attributes documentation at
 * https://developer.wordpress.org/block-editor/reference-guides/block-api/block-attributes/
 * for more details.
 */
export interface Attributes {
}

/**
 * Block Hooks allow a block to automatically insert itself next to all instances of a given
 * block type.
 *
 * See the Block Hooks documentation at
 * https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/#block-hooks-optional
 * for more details.
 */
export interface BlockHooks {
}

export interface BlockstudioClass {
    /**
     * Custom attributes that will be applied to the block.
     */
    attributes?: BlockstudioAttribute[];
    /**
     * Block specific options for rendering inside the Block Editor.
     */
    blockEditor?: BlockstudioBlockEditor;
    /**
     * Conditional logic detailing when the field should be displayed in the editor.
     */
    conditions?: Array<BlockstudioCondition[]>;
    /**
     * Block specific options for the editor.
     */
    editor?: BlockstudioEditor;
    /**
     * Custom SVG icon to be displayed inside the editor.
     */
    icon?: string;
    /**
     * HTML content that will be rendered as the inner blocks.
     */
    innerBlocks?: string;
    /**
     * Whether this block should overwrite another block.
     */
    override?: boolean;
    /**
     * When the block should refresh. This is useful when the block relies on external data like
     * custom fields.
     */
    refreshOn?: string[];
    /**
     * Custom block transforms.
     */
    transforms?: BlockstudioTransforms;
}

export interface BlockstudioAttribute {
    /**
     * Conditional logic detailing when the field should be displayed in the editor.
     */
    conditions?: Array<StickyCondition[]>;
    /**
     * The description for the field. Will be displayed underneath the field.
     */
    description?: string;
    /**
     * The help text for the field. Will be displayed in a tooltip next to the label.
     */
    help?: string;
    /**
     * Whether to hide the field UI in the editor. This is handy when using the variations API.
     */
    hidden?: boolean;
    /**
     * A unique identifier for the field, which will be used get the value inside block
     * templates. Must be unique within the current context.
     */
    id?: string;
    /**
     * Another identifier that can be used to uniquely identify fields across different
     * contexts. (inside repeaters etc.)
     */
    key?: string;
    /**
     * The label for the field.
     */
    label?: string;
    /**
     * Whether to display a switch that disables the field.
     */
    switch?: boolean;
    type:    FluffyType;
    /**
     * Default value that should be applied when first adding the block.
     */
    default?: any[] | boolean | number | { [key: string]: any } | string;
    /**
     * Fallback value that that will display when field value is empty.
     */
    fallback?: any[] | boolean | number | { [key: string]: any } | string;
    /**
     * Enables link selection from dropdown.
     */
    link?: boolean;
    /**
     * Enables media selection from dropdown.
     */
    media?: boolean;
    /**
     * Options to choose from.
     */
    options?:  FluffyOption[];
    populate?: FluffyPopulate;
    /**
     * Specifies the return format value.
     *
     * The format to return the icon in.
     */
    returnFormat?: ReturnFormatEnum;
    /**
     * Whether to enable Tailwind classes for this input field.
     */
    tailwind?: boolean;
    /**
     * Whether to enable autocompletion or not.
     */
    autoCompletion?: boolean;
    /**
     * Whether to show the fold gutter or not.
     */
    foldGutter?: boolean;
    /**
     * The height of the editor.
     */
    height?: string;
    /**
     * The language to use for syntax highlighting.
     */
    language?: Language;
    /**
     * Whether to display line numbers or not.
     */
    lineNumbers?: boolean;
    /**
     * The maximum height of the editor.
     */
    maxHeight?: string;
    /**
     * The minimum height of the editor.
     */
    minHeight?: string;
    /**
     * Whether to show a button that opens the editor in a popup window.
     */
    popout?: boolean;
    /**
     * Whether the palette should have a clearing button or not.
     */
    clearable?: boolean;
    /**
     * Whether to allow custom color or not.
     */
    disableCustomColors?: boolean;
    /**
     * The day that the week should start on. 0 for Sunday, 1 for Monday, etc.
     */
    startOfWeek?: number;
    /**
     * Whether we use a 12-hour clock. With a 12-hour clock, an AM/PM widget is displayed and
     * the time format is assumed to be MM-DD-YYYY (as opposed to the default format DD-MM-YYYY).
     */
    is12Hour?: boolean;
    /**
     * If true, the gallery media modal opens directly in the media library where the user can
     * add additional images. If false the gallery media modal opens in the edit mode where the
     * user can edit existing images, by reordering them, remove them, or change their
     * attributes. Only applies if gallery === true.
     */
    addToGallery?: boolean;
    /**
     * Array with the types of the media to upload/select from the media library. Each type is a
     * string that can contain the general mime type e.g: 'image', 'audio', 'text', or the
     * complete mime type e.g: 'audio/mpeg', 'image/gif'. If allowedTypes is unset all mime
     * types should be allowed.
     */
    allowedTypes?: any[] | string;
    /**
     * If true, the component will initiate all the states required to represent a gallery. By
     * default, the media modal opens in the gallery edit frame, but that can be changed using
     * the addToGalleryflag.
     */
    gallery?: boolean;
    /**
     * Maximum amount of files that can be added.
     *
     * The maximum value length.
     *
     * Maximum amount of rows.
     */
    max?: number;
    /**
     * Minimum amount of files that can be added.
     *
     * Minimum value length.
     *
     * Minimum amount of rows.
     */
    min?: number;
    /**
     * Whether to allow multiple selections or not.
     *
     * If true, multiple options can be selected. "stylisedUi" will be automatically enabled.
     */
    multiple?: boolean;
    /**
     * The media size to return when using the URL return format.
     */
    returnSize?: string;
    /**
     * Adds a media size dropdown to the field.
     */
    size?: boolean;
    /**
     * Media button text.
     */
    textMediaButton?: string;
    /**
     * Title displayed in the media modal.
     *
     * Title text. It shows even when the component is closed.
     */
    title?: string;
    /**
     * Whether to allow custom color or not.
     */
    disableCustomGradients?: boolean;
    /**
     * Accepts all other field types besides another tabs.
     */
    attributes?: AttributeAttribute[];
    /**
     * Custom CSS class that will be applied to the group.
     */
    class?: string;
    /**
     * Whether or not the panel will start open.
     */
    initialOpen?: boolean;
    /**
     * When set to true, the component will remain open regardless of the initialOpen prop and
     * the
     */
    opened?: boolean;
    /**
     * Scrolls the content into view when visible.
     */
    scrollAfterOpen?: boolean;
    /**
     * Custom CSS styles that will be applied to the group.
     */
    style?: { [key: string]: any };
    /**
     * Which icon set to include. Leave empty to include all.
     */
    sets?: any[] | string;
    /**
     * Which sub icon set to include. Leave empty to include all.
     */
    subSets?: any[] | string;
    /**
     * Whether rich previews should be shown when adding an URL.
     */
    hasRichPreviews?: boolean;
    /**
     * Whether to allow turning a URL-like search query directly into a link.
     */
    noDirectEntry?: boolean;
    /**
     * Whether to add a fallback suggestion which treats the search query as a URL.
     */
    noURLSuggestion?: boolean;
    /**
     * Adds a toggle control to the link modal.
     */
    opensInNewTab?: boolean;
    /**
     * Whether to present suggestions when typing the URL.
     */
    showSuggestions?: boolean;
    /**
     * Custom text that should be displayed inside the link button.
     *
     * Text for the add button.
     */
    textButton?: string;
    /**
     * Whether to allow creation of link value from suggestion.
     */
    withCreateSuggestion?: boolean;
    /**
     * The message to display. Block and attribute data is available in bracket syntax, e.g.:
     * The block title is <strong>{block.title}</strong> and the value of text is
     * <strong>{attributes.text}</strong>
     */
    value?: string;
    /**
     * Determines the drag axis to increment/decrement the value.
     */
    dragDirection?: DragDirection;
    /**
     * If isDragEnabled is true, this controls the amount of px to have been dragged before the
     * value changes.
     */
    dragThreshold?: number;
    /**
     * If true, the default input HTML arrows will be hidden.
     */
    hideHTMLArrows?: boolean;
    /**
     * If true, enables mouse drag gesture to increment/decrement the number value. Holding
     * SHIFT while dragging will increase the value by the shiftStep.
     */
    isShiftStepEnabled?: boolean;
    /**
     * If true enforces a valid number within the control’s min/max range. If false allows an
     * empty string as a valid value.
     */
    required?: boolean;
    /**
     * Amount to increment by when the SHIFT key is held down. This shift value is a multiplier
     * to the step value. For example, if the step value is 5, and shiftStep is 10, each jump
     * would increment/decrement by 50.
     */
    shiftStep?: number;
    /**
     * Amount by which the value is changed when incrementing/decrementing. It is also a factor
     * in validation as value must be a multiple of step (offset by min, if specified) to be
     * valid. Accepts the special string value any that voids the validation constraint and
     * causes stepping actions to increment/decrement by 1.
     */
    step?: number;
    /**
     * If this property is true, a button to reset the slider is rendered.
     *
     * If this property is true, a button to reset the select is rendered.
     */
    allowReset?: boolean;
    /**
     * The slider starting position, used when no value is passed. The initialPosition will be
     * clamped between the provided min and max prop values.
     */
    initialPosition?: number;
    /**
     * Renders a visual representation of step ticks. Custom mark indicators can be provided by
     * an Array.
     */
    marks?: FluffyMark[];
    /**
     * CSS color string to customize the rail element’s background.
     */
    railColor?: string;
    /**
     * The value to revert to if the Reset button is clicked (enabled by allowReset)
     */
    resetFallbackValue?: number;
    /**
     * Define if separator line under/above control row should be disabled or full width. By
     * default it is placed below excluding underline the control icon.
     */
    separatorType?: SeparatorType;
    /**
     * Forcing the Tooltip UI to show or hide. This is overridden to false when step is set to
     * the special string value any.
     */
    showTooltip?: boolean;
    /**
     * CSS color string to customize the track element’s background.
     */
    trackColor?: string;
    /**
     * Determines if the input number field will render next to the RangeControl. This is
     * overridden to false when step is set to the special string value any.
     */
    withInputField?: boolean;
    /**
     * Allows the user to select an empty choice. If true, the label will be empty, otherwise
     * the option will render the specified string.
     */
    allowNull?: boolean | string;
    /**
     * Renders a stylised version of a select with the ability to search through items.
     */
    stylisedUi?: boolean;
    /**
     * The tabs to display.
     */
    tabs?: FluffyTab[];
    /**
     * The number of rows the textarea should contain.
     */
    rows?: number;
    /**
     * Text that will be displayed when rows are minimized.
     */
    textMinimized?: FluffyTextMinimized | string;
    /**
     * Text to display in alert when removing repeater row.
     */
    textRemove?: boolean | string;
    /**
     * If true, the unit select field is hidden.
     */
    disableUnits?: boolean;
    /**
     * If true, the ENTER key press is required in order to trigger an onChange. If enabled, a
     * change is also triggered when tabbing away.
     */
    isPressEnterToChange?: boolean;
    /**
     * If true, and the selected unit provides a default value, this value is set when changing
     * units.
     */
    isResetValueOnUnitChange?: boolean;
    /**
     * Determines if the unit select field is tabbable.
     */
    isUnitSelectTabbable?: boolean;
    units?:                FluffyUnit[];
    /**
     * The toolbar configuration for the editor.
     */
    toolbar?: FluffyToolbar;
    [property: string]: any;
}

export interface AttributeAttribute {
    /**
     * Conditional logic detailing when the field should be displayed in the editor.
     */
    conditions?: Array<FluffyCondition[]>;
    /**
     * The description for the field. Will be displayed underneath the field.
     */
    description?: string;
    /**
     * The help text for the field. Will be displayed in a tooltip next to the label.
     */
    help?: string;
    /**
     * Whether to hide the field UI in the editor. This is handy when using the variations API.
     */
    hidden?: boolean;
    /**
     * A unique identifier for the field, which will be used get the value inside block
     * templates. Must be unique within the current context.
     */
    id?: string;
    /**
     * Another identifier that can be used to uniquely identify fields across different
     * contexts. (inside repeaters etc.)
     */
    key?: string;
    /**
     * The label for the field.
     */
    label?: string;
    /**
     * Whether to display a switch that disables the field.
     */
    switch?: boolean;
    type:    PurpleType;
    /**
     * Default value that should be applied when first adding the block.
     */
    default?: any[] | boolean | number | { [key: string]: any } | string;
    /**
     * Fallback value that that will display when field value is empty.
     */
    fallback?: any[] | boolean | number | { [key: string]: any } | string;
    /**
     * Enables link selection from dropdown.
     */
    link?: boolean;
    /**
     * Enables media selection from dropdown.
     */
    media?: boolean;
    /**
     * Options to choose from.
     */
    options?:  PurpleOption[];
    populate?: PurplePopulate;
    /**
     * Specifies the return format value.
     *
     * The format to return the icon in.
     */
    returnFormat?: ReturnFormatEnum;
    /**
     * Whether to enable Tailwind classes for this input field.
     */
    tailwind?: boolean;
    /**
     * Whether to enable autocompletion or not.
     */
    autoCompletion?: boolean;
    /**
     * Whether to show the fold gutter or not.
     */
    foldGutter?: boolean;
    /**
     * The height of the editor.
     */
    height?: string;
    /**
     * The language to use for syntax highlighting.
     */
    language?: Language;
    /**
     * Whether to display line numbers or not.
     */
    lineNumbers?: boolean;
    /**
     * The maximum height of the editor.
     */
    maxHeight?: string;
    /**
     * The minimum height of the editor.
     */
    minHeight?: string;
    /**
     * Whether to show a button that opens the editor in a popup window.
     */
    popout?: boolean;
    /**
     * Whether the palette should have a clearing button or not.
     */
    clearable?: boolean;
    /**
     * Whether to allow custom color or not.
     */
    disableCustomColors?: boolean;
    /**
     * The day that the week should start on. 0 for Sunday, 1 for Monday, etc.
     */
    startOfWeek?: number;
    /**
     * Whether we use a 12-hour clock. With a 12-hour clock, an AM/PM widget is displayed and
     * the time format is assumed to be MM-DD-YYYY (as opposed to the default format DD-MM-YYYY).
     */
    is12Hour?: boolean;
    /**
     * If true, the gallery media modal opens directly in the media library where the user can
     * add additional images. If false the gallery media modal opens in the edit mode where the
     * user can edit existing images, by reordering them, remove them, or change their
     * attributes. Only applies if gallery === true.
     */
    addToGallery?: boolean;
    /**
     * Array with the types of the media to upload/select from the media library. Each type is a
     * string that can contain the general mime type e.g: 'image', 'audio', 'text', or the
     * complete mime type e.g: 'audio/mpeg', 'image/gif'. If allowedTypes is unset all mime
     * types should be allowed.
     */
    allowedTypes?: any[] | string;
    /**
     * If true, the component will initiate all the states required to represent a gallery. By
     * default, the media modal opens in the gallery edit frame, but that can be changed using
     * the addToGalleryflag.
     */
    gallery?: boolean;
    /**
     * Maximum amount of files that can be added.
     *
     * The maximum value length.
     *
     * Maximum amount of rows.
     */
    max?: number;
    /**
     * Minimum amount of files that can be added.
     *
     * Minimum value length.
     *
     * Minimum amount of rows.
     */
    min?: number;
    /**
     * Whether to allow multiple selections or not.
     *
     * If true, multiple options can be selected. "stylisedUi" will be automatically enabled.
     */
    multiple?: boolean;
    /**
     * The media size to return when using the URL return format.
     */
    returnSize?: string;
    /**
     * Adds a media size dropdown to the field.
     */
    size?: boolean;
    /**
     * Media button text.
     */
    textMediaButton?: string;
    /**
     * Title displayed in the media modal.
     */
    title?: string;
    /**
     * Whether to allow custom color or not.
     */
    disableCustomGradients?: boolean;
    /**
     * Which icon set to include. Leave empty to include all.
     */
    sets?: any[] | string;
    /**
     * Which sub icon set to include. Leave empty to include all.
     */
    subSets?: any[] | string;
    /**
     * Whether rich previews should be shown when adding an URL.
     */
    hasRichPreviews?: boolean;
    /**
     * Whether to allow turning a URL-like search query directly into a link.
     */
    noDirectEntry?: boolean;
    /**
     * Whether to add a fallback suggestion which treats the search query as a URL.
     */
    noURLSuggestion?: boolean;
    /**
     * Adds a toggle control to the link modal.
     */
    opensInNewTab?: boolean;
    /**
     * Whether to present suggestions when typing the URL.
     */
    showSuggestions?: boolean;
    /**
     * Custom text that should be displayed inside the link button.
     *
     * Text for the add button.
     */
    textButton?: string;
    /**
     * Whether to allow creation of link value from suggestion.
     */
    withCreateSuggestion?: boolean;
    /**
     * The message to display. Block and attribute data is available in bracket syntax, e.g.:
     * The block title is <strong>{block.title}</strong> and the value of text is
     * <strong>{attributes.text}</strong>
     */
    value?: string;
    /**
     * Determines the drag axis to increment/decrement the value.
     */
    dragDirection?: DragDirection;
    /**
     * If isDragEnabled is true, this controls the amount of px to have been dragged before the
     * value changes.
     */
    dragThreshold?: number;
    /**
     * If true, the default input HTML arrows will be hidden.
     */
    hideHTMLArrows?: boolean;
    /**
     * If true, enables mouse drag gesture to increment/decrement the number value. Holding
     * SHIFT while dragging will increase the value by the shiftStep.
     */
    isShiftStepEnabled?: boolean;
    /**
     * If true enforces a valid number within the control’s min/max range. If false allows an
     * empty string as a valid value.
     */
    required?: boolean;
    /**
     * Amount to increment by when the SHIFT key is held down. This shift value is a multiplier
     * to the step value. For example, if the step value is 5, and shiftStep is 10, each jump
     * would increment/decrement by 50.
     */
    shiftStep?: number;
    /**
     * Amount by which the value is changed when incrementing/decrementing. It is also a factor
     * in validation as value must be a multiple of step (offset by min, if specified) to be
     * valid. Accepts the special string value any that voids the validation constraint and
     * causes stepping actions to increment/decrement by 1.
     */
    step?: number;
    /**
     * If this property is true, a button to reset the slider is rendered.
     *
     * If this property is true, a button to reset the select is rendered.
     */
    allowReset?: boolean;
    /**
     * The slider starting position, used when no value is passed. The initialPosition will be
     * clamped between the provided min and max prop values.
     */
    initialPosition?: number;
    /**
     * Renders a visual representation of step ticks. Custom mark indicators can be provided by
     * an Array.
     */
    marks?: PurpleMark[];
    /**
     * CSS color string to customize the rail element’s background.
     */
    railColor?: string;
    /**
     * The value to revert to if the Reset button is clicked (enabled by allowReset)
     */
    resetFallbackValue?: number;
    /**
     * Define if separator line under/above control row should be disabled or full width. By
     * default it is placed below excluding underline the control icon.
     */
    separatorType?: SeparatorType;
    /**
     * Forcing the Tooltip UI to show or hide. This is overridden to false when step is set to
     * the special string value any.
     */
    showTooltip?: boolean;
    /**
     * CSS color string to customize the track element’s background.
     */
    trackColor?: string;
    /**
     * Determines if the input number field will render next to the RangeControl. This is
     * overridden to false when step is set to the special string value any.
     */
    withInputField?: boolean;
    /**
     * Allows the user to select an empty choice. If true, the label will be empty, otherwise
     * the option will render the specified string.
     */
    allowNull?: boolean | string;
    /**
     * Renders a stylised version of a select with the ability to search through items.
     */
    stylisedUi?: boolean;
    /**
     * The tabs to display.
     */
    tabs?: PurpleTab[];
    /**
     * The number of rows the textarea should contain.
     */
    rows?: number;
    /**
     * Accepts all other field types besides another tabs.
     */
    attributes?: Attribute[];
    /**
     * Text that will be displayed when rows are minimized.
     */
    textMinimized?: PurpleTextMinimized | string;
    /**
     * Text to display in alert when removing repeater row.
     */
    textRemove?: boolean | string;
    /**
     * If true, the unit select field is hidden.
     */
    disableUnits?: boolean;
    /**
     * If true, the ENTER key press is required in order to trigger an onChange. If enabled, a
     * change is also triggered when tabbing away.
     */
    isPressEnterToChange?: boolean;
    /**
     * If true, and the selected unit provides a default value, this value is set when changing
     * units.
     */
    isResetValueOnUnitChange?: boolean;
    /**
     * Determines if the unit select field is tabbable.
     */
    isUnitSelectTabbable?: boolean;
    units?:                PurpleUnit[];
    /**
     * The toolbar configuration for the editor.
     */
    toolbar?: PurpleToolbar;
    [property: string]: any;
}

export interface AttributeTab {
    /**
     * Custom attributes that will be applied to the block.
     */
    attributes?: Attribute[];
    /**
     * Block specific options for rendering inside the Block Editor.
     */
    blockEditor?: PurpleBlockEditor;
    /**
     * Conditional logic detailing when the field should be displayed in the editor.
     */
    conditions?: Array<PurpleCondition[]>;
    /**
     * Block specific options for the editor.
     */
    editor?: PurpleEditor;
    /**
     * Custom SVG icon to be displayed inside the editor.
     */
    icon?: string;
    /**
     * HTML content that will be rendered as the inner blocks.
     */
    innerBlocks?: string;
    /**
     * Whether this block should overwrite another block.
     */
    override?: boolean;
    /**
     * When the block should refresh. This is useful when the block relies on external data like
     * custom fields.
     */
    refreshOn?: string[];
    /**
     * The title of the tab.
     */
    title: string;
    /**
     * Custom block transforms.
     */
    transforms?: PurpleTransforms;
    [property: string]: any;
}

export interface Attribute {
    /**
     * Conditional logic detailing when the field should be displayed in the editor.
     */
    conditions?: Array<AttributeCondition[]>;
    /**
     * The description for the field. Will be displayed underneath the field.
     */
    description?: string;
    /**
     * The help text for the field. Will be displayed in a tooltip next to the label.
     */
    help?: string;
    /**
     * Whether to hide the field UI in the editor. This is handy when using the variations API.
     */
    hidden?: boolean;
    /**
     * A unique identifier for the field, which will be used get the value inside block
     * templates. Must be unique within the current context.
     */
    id?: string;
    /**
     * Another identifier that can be used to uniquely identify fields across different
     * contexts. (inside repeaters etc.)
     */
    key?: string;
    /**
     * The label for the field.
     */
    label?: string;
    /**
     * Whether to display a switch that disables the field.
     */
    switch?: boolean;
    type:    AttributeType;
    /**
     * Default value that should be applied when first adding the block.
     */
    default?: any[] | boolean | number | { [key: string]: any } | string;
    /**
     * Fallback value that that will display when field value is empty.
     */
    fallback?: any[] | boolean | number | { [key: string]: any } | string;
    /**
     * Enables link selection from dropdown.
     */
    link?: boolean;
    /**
     * Enables media selection from dropdown.
     */
    media?: boolean;
    /**
     * Options to choose from.
     */
    options?:  AttributeOption[];
    populate?: AttributePopulate;
    /**
     * Specifies the return format value.
     *
     * The format to return the icon in.
     */
    returnFormat?: ReturnFormatEnum;
    /**
     * Whether to enable Tailwind classes for this input field.
     */
    tailwind?: boolean;
    /**
     * Whether to enable autocompletion or not.
     */
    autoCompletion?: boolean;
    /**
     * Whether to show the fold gutter or not.
     */
    foldGutter?: boolean;
    /**
     * The height of the editor.
     */
    height?: string;
    /**
     * The language to use for syntax highlighting.
     */
    language?: Language;
    /**
     * Whether to display line numbers or not.
     */
    lineNumbers?: boolean;
    /**
     * The maximum height of the editor.
     */
    maxHeight?: string;
    /**
     * The minimum height of the editor.
     */
    minHeight?: string;
    /**
     * Whether to show a button that opens the editor in a popup window.
     */
    popout?: boolean;
    /**
     * Whether the palette should have a clearing button or not.
     */
    clearable?: boolean;
    /**
     * Whether to allow custom color or not.
     */
    disableCustomColors?: boolean;
    /**
     * The day that the week should start on. 0 for Sunday, 1 for Monday, etc.
     */
    startOfWeek?: number;
    /**
     * Whether we use a 12-hour clock. With a 12-hour clock, an AM/PM widget is displayed and
     * the time format is assumed to be MM-DD-YYYY (as opposed to the default format DD-MM-YYYY).
     */
    is12Hour?: boolean;
    /**
     * If true, the gallery media modal opens directly in the media library where the user can
     * add additional images. If false the gallery media modal opens in the edit mode where the
     * user can edit existing images, by reordering them, remove them, or change their
     * attributes. Only applies if gallery === true.
     */
    addToGallery?: boolean;
    /**
     * Array with the types of the media to upload/select from the media library. Each type is a
     * string that can contain the general mime type e.g: 'image', 'audio', 'text', or the
     * complete mime type e.g: 'audio/mpeg', 'image/gif'. If allowedTypes is unset all mime
     * types should be allowed.
     */
    allowedTypes?: any[] | string;
    /**
     * If true, the component will initiate all the states required to represent a gallery. By
     * default, the media modal opens in the gallery edit frame, but that can be changed using
     * the addToGalleryflag.
     */
    gallery?: boolean;
    /**
     * Maximum amount of files that can be added.
     *
     * The maximum value length.
     */
    max?: number;
    /**
     * Minimum amount of files that can be added.
     *
     * Minimum value length.
     */
    min?: number;
    /**
     * Whether to allow multiple selections or not.
     *
     * If true, multiple options can be selected. "stylisedUi" will be automatically enabled.
     */
    multiple?: boolean;
    /**
     * The media size to return when using the URL return format.
     */
    returnSize?: string;
    /**
     * Adds a media size dropdown to the field.
     */
    size?: boolean;
    /**
     * Media button text.
     */
    textMediaButton?: string;
    /**
     * Title displayed in the media modal.
     */
    title?: string;
    /**
     * Whether to allow custom color or not.
     */
    disableCustomGradients?: boolean;
    /**
     * Which icon set to include. Leave empty to include all.
     */
    sets?: any[] | string;
    /**
     * Which sub icon set to include. Leave empty to include all.
     */
    subSets?: any[] | string;
    /**
     * Whether rich previews should be shown when adding an URL.
     */
    hasRichPreviews?: boolean;
    /**
     * Whether to allow turning a URL-like search query directly into a link.
     */
    noDirectEntry?: boolean;
    /**
     * Whether to add a fallback suggestion which treats the search query as a URL.
     */
    noURLSuggestion?: boolean;
    /**
     * Adds a toggle control to the link modal.
     */
    opensInNewTab?: boolean;
    /**
     * Whether to present suggestions when typing the URL.
     */
    showSuggestions?: boolean;
    /**
     * Custom text that should be displayed inside the link button.
     */
    textButton?: string;
    /**
     * Whether to allow creation of link value from suggestion.
     */
    withCreateSuggestion?: boolean;
    /**
     * The message to display. Block and attribute data is available in bracket syntax, e.g.:
     * The block title is <strong>{block.title}</strong> and the value of text is
     * <strong>{attributes.text}</strong>
     */
    value?: string;
    /**
     * Determines the drag axis to increment/decrement the value.
     */
    dragDirection?: DragDirection;
    /**
     * If isDragEnabled is true, this controls the amount of px to have been dragged before the
     * value changes.
     */
    dragThreshold?: number;
    /**
     * If true, the default input HTML arrows will be hidden.
     */
    hideHTMLArrows?: boolean;
    /**
     * If true, enables mouse drag gesture to increment/decrement the number value. Holding
     * SHIFT while dragging will increase the value by the shiftStep.
     */
    isShiftStepEnabled?: boolean;
    /**
     * If true enforces a valid number within the control’s min/max range. If false allows an
     * empty string as a valid value.
     */
    required?: boolean;
    /**
     * Amount to increment by when the SHIFT key is held down. This shift value is a multiplier
     * to the step value. For example, if the step value is 5, and shiftStep is 10, each jump
     * would increment/decrement by 50.
     */
    shiftStep?: number;
    /**
     * Amount by which the value is changed when incrementing/decrementing. It is also a factor
     * in validation as value must be a multiple of step (offset by min, if specified) to be
     * valid. Accepts the special string value any that voids the validation constraint and
     * causes stepping actions to increment/decrement by 1.
     */
    step?: number;
    /**
     * If this property is true, a button to reset the slider is rendered.
     *
     * If this property is true, a button to reset the select is rendered.
     */
    allowReset?: boolean;
    /**
     * The slider starting position, used when no value is passed. The initialPosition will be
     * clamped between the provided min and max prop values.
     */
    initialPosition?: number;
    /**
     * Renders a visual representation of step ticks. Custom mark indicators can be provided by
     * an Array.
     */
    marks?: AttributeMark[];
    /**
     * CSS color string to customize the rail element’s background.
     */
    railColor?: string;
    /**
     * The value to revert to if the Reset button is clicked (enabled by allowReset)
     */
    resetFallbackValue?: number;
    /**
     * Define if separator line under/above control row should be disabled or full width. By
     * default it is placed below excluding underline the control icon.
     */
    separatorType?: SeparatorType;
    /**
     * Forcing the Tooltip UI to show or hide. This is overridden to false when step is set to
     * the special string value any.
     */
    showTooltip?: boolean;
    /**
     * CSS color string to customize the track element’s background.
     */
    trackColor?: string;
    /**
     * Determines if the input number field will render next to the RangeControl. This is
     * overridden to false when step is set to the special string value any.
     */
    withInputField?: boolean;
    /**
     * Allows the user to select an empty choice. If true, the label will be empty, otherwise
     * the option will render the specified string.
     */
    allowNull?: boolean | string;
    /**
     * Renders a stylised version of a select with the ability to search through items.
     */
    stylisedUi?: boolean;
    /**
     * The tabs to display.
     */
    tabs?: AttributeTab[];
    /**
     * The number of rows the textarea should contain.
     */
    rows?: number;
    /**
     * If true, the unit select field is hidden.
     */
    disableUnits?: boolean;
    /**
     * If true, the ENTER key press is required in order to trigger an onChange. If enabled, a
     * change is also triggered when tabbing away.
     */
    isPressEnterToChange?: boolean;
    /**
     * If true, and the selected unit provides a default value, this value is set when changing
     * units.
     */
    isResetValueOnUnitChange?: boolean;
    /**
     * Determines if the unit select field is tabbable.
     */
    isUnitSelectTabbable?: boolean;
    units?:                AttributeUnit[];
    /**
     * The toolbar configuration for the editor.
     */
    toolbar?: AttributeToolbar;
    [property: string]: any;
}

/**
 * Block specific options for rendering inside the Block Editor.
 */
export interface PurpleBlockEditor {
    /**
     * Whether the block should should load inside the Block Editor or show a placeholder.
     */
    disableLoading?: boolean;
    [property: string]: any;
}

export interface PurpleCondition {
    /**
     * ID of the field whose value will be used for the conditionally render it.
     */
    id?: string;
    /**
     * How the values should be compared.
     */
    operator: Operator;
    /**
     * Condition type.
     */
    type?: string;
    /**
     * Value that will be compared.
     */
    value?: boolean | number | string;
    [property: string]: any;
}

/**
 * How the values should be compared.
 */
export enum Operator {
    Empty = "==",
    Fluffy = "<",
    Includes = "includes",
    Indigo = ">=",
    Operator = "!=",
    OperatorEmpty = "empty",
    OperatorIncludes = "!includes",
    PurpleEmpty = "!empty",
    Sticky = "<=",
    Tentacled = ">",
}

/**
 * Block specific options for the editor.
 */
export interface PurpleEditor {
    /**
     * List of WordPress script or style handles that should be added to the preview.
     */
    assets?: string[];
    [property: string]: any;
}

/**
 * Custom block transforms.
 */
export interface PurpleTransforms {
    from: PurpleFrom[];
}

export interface PurpleFrom {
    blocks?: string[];
    prefix?: string;
    regExp?: string;
    type:    FromType;
}

export enum FromType {
    Block = "block",
    Enter = "enter",
    Prefix = "prefix",
}

export interface AttributeCondition {
    /**
     * ID of the field whose value will be used for the conditionally render it.
     */
    id?: string;
    /**
     * How the values should be compared.
     */
    operator: Operator;
    /**
     * Condition type.
     */
    type?: string;
    /**
     * Value that will be compared.
     */
    value?: boolean | number | string;
    [property: string]: any;
}

/**
 * Determines the drag axis to increment/decrement the value.
 */
export enum DragDirection {
    E = "e",
    N = "n",
    S = "s",
    W = "w",
}

/**
 * The language to use for syntax highlighting.
 */
export enum Language {
    CSS = "css",
    HTML = "html",
    JSON = "json",
    Javascript = "javascript",
    Twig = "twig",
}

export interface AttributeMark {
    label?: string;
    value?: number;
    [property: string]: any;
}

/**
 * Options to choose from.
 */
export interface AttributeOption {
    label?:       string;
    value?:       number | string;
    name?:        string;
    slug?:        string;
    innerBlocks?: InnerBlockElement[];
    [property: string]: any;
}

export interface InnerBlockElement {
    attributes?:  { [key: string]: any };
    innerBlocks?: InnerBlockElement[];
    name:         string;
    [property: string]: any;
}

export interface AttributePopulate {
    /**
     * Query or fetch arguments.
     */
    arguments?: any[] | PurpleArguments;
    /**
     * Custom data ID.
     */
    custom?: string;
    /**
     * The function that should be executed.
     */
    function?: string;
    /**
     * How the data should be positioned in regards to the default options.
     */
    position?: PositionEnum;
    /**
     * Type of query that should be used to fetch data.
     */
    query?: Query;
    /**
     * Format of the returning data when using objects.
     */
    returnFormat?: PurpleReturnFormat;
    type?:         PopulateType;
    /**
     * If true, search value will be used to search through data. Only works with "query" type.
     */
    fetch?: boolean;
    [property: string]: any;
}

export interface PurpleArguments {
    /**
     * Search URL when using the "fetch" type.
     */
    urlSearch?: string;
    [property: string]: any;
}

/**
 * How the data should be positioned in regards to the default options.
 */
export enum PositionEnum {
    After = "after",
    Before = "before",
}

/**
 * Type of query that should be used to fetch data.
 */
export enum Query {
    Posts = "posts",
    Terms = "terms",
    Users = "users",
}

/**
 * Format of the returning data when using objects.
 */
export interface PurpleReturnFormat {
    label?: string;
    value?: string;
    [property: string]: any;
}

export enum PopulateType {
    Custom = "custom",
    Fetch = "fetch",
    Function = "function",
    Query = "query",
}

/**
 * Specifies the return format value.
 *
 * The format to return the icon in.
 */
export enum ReturnFormatEnum {
    Both = "both",
    Element = "element",
    ID = "id",
    Label = "label",
    Object = "object",
    URL = "url",
    Value = "value",
}

/**
 * Define if separator line under/above control row should be disabled or full width. By
 * default it is placed below excluding underline the control icon.
 */
export enum SeparatorType {
    FullWidth = "fullWidth",
    None = "none",
    TopFullWidth = "topFullWidth",
}

/**
 * The toolbar configuration for the editor.
 */
export interface AttributeToolbar {
    /**
     * Which text formats are allowed.
     */
    formats?: PurpleFormats;
    /**
     * Which HTML tags are allowed.
     */
    tags?: PurpleTags;
    [property: string]: any;
}

/**
 * Which text formats are allowed.
 */
export interface PurpleFormats {
    /**
     * Whether bold text format is allowed.
     */
    bold?: boolean;
    /**
     * Whether italic text format is allowed.
     */
    italic?: boolean;
    /**
     * Whether ordered list text format is allowed.
     */
    orderedList?: boolean;
    /**
     * Whether strikethrough text format is allowed.
     */
    strikethrough?: boolean;
    /**
     * Which text alignment formats are allowed.
     */
    textAlign?: PurpleTextAlign;
    /**
     * Whether underline text format is allowed.
     */
    underline?: boolean;
    /**
     * Whether unordered list text format is allowed.
     */
    unorderedList?: boolean;
    [property: string]: any;
}

/**
 * Which text alignment formats are allowed.
 */
export interface PurpleTextAlign {
    /**
     * Which text alignments are allowed.
     */
    alignments?: Alignment[];
    [property: string]: any;
}

export enum Alignment {
    Center = "center",
    Justify = "justify",
    Left = "left",
    Right = "right",
}

/**
 * Which HTML tags are allowed.
 */
export interface PurpleTags {
    /**
     * Which HTML headings levels are allowed.
     */
    headings?: any[];
    [property: string]: any;
}

export enum AttributeType {
    Attributes = "attributes",
    Checkbox = "checkbox",
    Classes = "classes",
    Code = "code",
    Color = "color",
    Date = "date",
    Datetime = "datetime",
    Files = "files",
    Gradient = "gradient",
    Icon = "icon",
    Link = "link",
    Message = "message",
    Number = "number",
    Radio = "radio",
    Range = "range",
    Richtext = "richtext",
    Select = "select",
    Tabs = "tabs",
    Text = "text",
    Textarea = "textarea",
    Toggle = "toggle",
    Unit = "unit",
    WYSIWYG = "wysiwyg",
}

export interface AttributeUnit {
    default?: number;
    label?:   string;
    value?:   string;
    [property: string]: any;
}

export interface FluffyCondition {
    /**
     * ID of the field whose value will be used for the conditionally render it.
     */
    id?: string;
    /**
     * How the values should be compared.
     */
    operator: Operator;
    /**
     * Condition type.
     */
    type?: string;
    /**
     * Value that will be compared.
     */
    value?: boolean | number | string;
    [property: string]: any;
}

export interface PurpleMark {
    label?: string;
    value?: number;
    [property: string]: any;
}

/**
 * Options to choose from.
 */
export interface PurpleOption {
    label?:       string;
    value?:       number | string;
    name?:        string;
    slug?:        string;
    innerBlocks?: InnerBlockElement[];
    [property: string]: any;
}

export interface PurplePopulate {
    /**
     * Query or fetch arguments.
     */
    arguments?: any[] | FluffyArguments;
    /**
     * Custom data ID.
     */
    custom?: string;
    /**
     * The function that should be executed.
     */
    function?: string;
    /**
     * How the data should be positioned in regards to the default options.
     */
    position?: PositionEnum;
    /**
     * Type of query that should be used to fetch data.
     */
    query?: Query;
    /**
     * Format of the returning data when using objects.
     */
    returnFormat?: FluffyReturnFormat;
    type?:         PopulateType;
    /**
     * If true, search value will be used to search through data. Only works with "query" type.
     */
    fetch?: boolean;
    [property: string]: any;
}

export interface FluffyArguments {
    /**
     * Search URL when using the "fetch" type.
     */
    urlSearch?: string;
    [property: string]: any;
}

/**
 * Format of the returning data when using objects.
 */
export interface FluffyReturnFormat {
    label?: string;
    value?: string;
    [property: string]: any;
}

export interface PurpleTab {
    /**
     * Custom attributes that will be applied to the block.
     */
    attributes?: Attribute[];
    /**
     * Block specific options for rendering inside the Block Editor.
     */
    blockEditor?: FluffyBlockEditor;
    /**
     * Conditional logic detailing when the field should be displayed in the editor.
     */
    conditions?: Array<TentacledCondition[]>;
    /**
     * Block specific options for the editor.
     */
    editor?: FluffyEditor;
    /**
     * Custom SVG icon to be displayed inside the editor.
     */
    icon?: string;
    /**
     * HTML content that will be rendered as the inner blocks.
     */
    innerBlocks?: string;
    /**
     * Whether this block should overwrite another block.
     */
    override?: boolean;
    /**
     * When the block should refresh. This is useful when the block relies on external data like
     * custom fields.
     */
    refreshOn?: string[];
    /**
     * The title of the tab.
     */
    title: string;
    /**
     * Custom block transforms.
     */
    transforms?: FluffyTransforms;
    [property: string]: any;
}

/**
 * Block specific options for rendering inside the Block Editor.
 */
export interface FluffyBlockEditor {
    /**
     * Whether the block should should load inside the Block Editor or show a placeholder.
     */
    disableLoading?: boolean;
    [property: string]: any;
}

export interface TentacledCondition {
    /**
     * ID of the field whose value will be used for the conditionally render it.
     */
    id?: string;
    /**
     * How the values should be compared.
     */
    operator: Operator;
    /**
     * Condition type.
     */
    type?: string;
    /**
     * Value that will be compared.
     */
    value?: boolean | number | string;
    [property: string]: any;
}

/**
 * Block specific options for the editor.
 */
export interface FluffyEditor {
    /**
     * List of WordPress script or style handles that should be added to the preview.
     */
    assets?: string[];
    [property: string]: any;
}

/**
 * Custom block transforms.
 */
export interface FluffyTransforms {
    from: FluffyFrom[];
}

export interface FluffyFrom {
    blocks?: string[];
    prefix?: string;
    regExp?: string;
    type:    FromType;
}

export interface PurpleTextMinimized {
    /**
     * Fallback text if the attribute is not set.
     */
    fallback?: string;
    /**
     * ID of the attribute which should be used as the text.
     */
    id?: string;
    /**
     * Prefix for the text.
     */
    prefix?: string;
    /**
     * Suffix for the text.
     */
    suffix?: string;
    [property: string]: any;
}

/**
 * The toolbar configuration for the editor.
 */
export interface PurpleToolbar {
    /**
     * Which text formats are allowed.
     */
    formats?: FluffyFormats;
    /**
     * Which HTML tags are allowed.
     */
    tags?: FluffyTags;
    [property: string]: any;
}

/**
 * Which text formats are allowed.
 */
export interface FluffyFormats {
    /**
     * Whether bold text format is allowed.
     */
    bold?: boolean;
    /**
     * Whether italic text format is allowed.
     */
    italic?: boolean;
    /**
     * Whether ordered list text format is allowed.
     */
    orderedList?: boolean;
    /**
     * Whether strikethrough text format is allowed.
     */
    strikethrough?: boolean;
    /**
     * Which text alignment formats are allowed.
     */
    textAlign?: FluffyTextAlign;
    /**
     * Whether underline text format is allowed.
     */
    underline?: boolean;
    /**
     * Whether unordered list text format is allowed.
     */
    unorderedList?: boolean;
    [property: string]: any;
}

/**
 * Which text alignment formats are allowed.
 */
export interface FluffyTextAlign {
    /**
     * Which text alignments are allowed.
     */
    alignments?: Alignment[];
    [property: string]: any;
}

/**
 * Which HTML tags are allowed.
 */
export interface FluffyTags {
    /**
     * Which HTML headings levels are allowed.
     */
    headings?: any[];
    [property: string]: any;
}

export enum PurpleType {
    Attributes = "attributes",
    Checkbox = "checkbox",
    Classes = "classes",
    Code = "code",
    Color = "color",
    Date = "date",
    Datetime = "datetime",
    Files = "files",
    Gradient = "gradient",
    Icon = "icon",
    Link = "link",
    Message = "message",
    Number = "number",
    Radio = "radio",
    Range = "range",
    Repeater = "repeater",
    Richtext = "richtext",
    Select = "select",
    Tabs = "tabs",
    Text = "text",
    Textarea = "textarea",
    Toggle = "toggle",
    Unit = "unit",
    WYSIWYG = "wysiwyg",
}

export interface PurpleUnit {
    default?: number;
    label?:   string;
    value?:   string;
    [property: string]: any;
}

export interface StickyCondition {
    /**
     * ID of the field whose value will be used for the conditionally render it.
     */
    id?: string;
    /**
     * How the values should be compared.
     */
    operator: Operator;
    /**
     * Condition type.
     */
    type?: string;
    /**
     * Value that will be compared.
     */
    value?: boolean | number | string;
    [property: string]: any;
}

export interface FluffyMark {
    label?: string;
    value?: number;
    [property: string]: any;
}

/**
 * Options to choose from.
 */
export interface FluffyOption {
    label?:       string;
    value?:       number | string;
    name?:        string;
    slug?:        string;
    innerBlocks?: InnerBlockElement[];
    [property: string]: any;
}

export interface FluffyPopulate {
    /**
     * Query or fetch arguments.
     */
    arguments?: any[] | TentacledArguments;
    /**
     * Custom data ID.
     */
    custom?: string;
    /**
     * The function that should be executed.
     */
    function?: string;
    /**
     * How the data should be positioned in regards to the default options.
     */
    position?: PositionEnum;
    /**
     * Type of query that should be used to fetch data.
     */
    query?: Query;
    /**
     * Format of the returning data when using objects.
     */
    returnFormat?: TentacledReturnFormat;
    type?:         PopulateType;
    /**
     * If true, search value will be used to search through data. Only works with "query" type.
     */
    fetch?: boolean;
    [property: string]: any;
}

export interface TentacledArguments {
    /**
     * Search URL when using the "fetch" type.
     */
    urlSearch?: string;
    [property: string]: any;
}

/**
 * Format of the returning data when using objects.
 */
export interface TentacledReturnFormat {
    label?: string;
    value?: string;
    [property: string]: any;
}

export interface FluffyTab {
    /**
     * Custom attributes that will be applied to the block.
     */
    attributes?: Attribute[];
    /**
     * Block specific options for rendering inside the Block Editor.
     */
    blockEditor?: TentacledBlockEditor;
    /**
     * Conditional logic detailing when the field should be displayed in the editor.
     */
    conditions?: Array<IndigoCondition[]>;
    /**
     * Block specific options for the editor.
     */
    editor?: TentacledEditor;
    /**
     * Custom SVG icon to be displayed inside the editor.
     */
    icon?: string;
    /**
     * HTML content that will be rendered as the inner blocks.
     */
    innerBlocks?: string;
    /**
     * Whether this block should overwrite another block.
     */
    override?: boolean;
    /**
     * When the block should refresh. This is useful when the block relies on external data like
     * custom fields.
     */
    refreshOn?: string[];
    /**
     * The title of the tab.
     */
    title: string;
    /**
     * Custom block transforms.
     */
    transforms?: TentacledTransforms;
    [property: string]: any;
}

/**
 * Block specific options for rendering inside the Block Editor.
 */
export interface TentacledBlockEditor {
    /**
     * Whether the block should should load inside the Block Editor or show a placeholder.
     */
    disableLoading?: boolean;
    [property: string]: any;
}

export interface IndigoCondition {
    /**
     * ID of the field whose value will be used for the conditionally render it.
     */
    id?: string;
    /**
     * How the values should be compared.
     */
    operator: Operator;
    /**
     * Condition type.
     */
    type?: string;
    /**
     * Value that will be compared.
     */
    value?: boolean | number | string;
    [property: string]: any;
}

/**
 * Block specific options for the editor.
 */
export interface TentacledEditor {
    /**
     * List of WordPress script or style handles that should be added to the preview.
     */
    assets?: string[];
    [property: string]: any;
}

/**
 * Custom block transforms.
 */
export interface TentacledTransforms {
    from: TentacledFrom[];
}

export interface TentacledFrom {
    blocks?: string[];
    prefix?: string;
    regExp?: string;
    type:    FromType;
}

export interface FluffyTextMinimized {
    /**
     * Fallback text if the attribute is not set.
     */
    fallback?: string;
    /**
     * ID of the attribute which should be used as the text.
     */
    id?: string;
    /**
     * Prefix for the text.
     */
    prefix?: string;
    /**
     * Suffix for the text.
     */
    suffix?: string;
    [property: string]: any;
}

/**
 * The toolbar configuration for the editor.
 */
export interface FluffyToolbar {
    /**
     * Which text formats are allowed.
     */
    formats?: TentacledFormats;
    /**
     * Which HTML tags are allowed.
     */
    tags?: TentacledTags;
    [property: string]: any;
}

/**
 * Which text formats are allowed.
 */
export interface TentacledFormats {
    /**
     * Whether bold text format is allowed.
     */
    bold?: boolean;
    /**
     * Whether italic text format is allowed.
     */
    italic?: boolean;
    /**
     * Whether ordered list text format is allowed.
     */
    orderedList?: boolean;
    /**
     * Whether strikethrough text format is allowed.
     */
    strikethrough?: boolean;
    /**
     * Which text alignment formats are allowed.
     */
    textAlign?: TentacledTextAlign;
    /**
     * Whether underline text format is allowed.
     */
    underline?: boolean;
    /**
     * Whether unordered list text format is allowed.
     */
    unorderedList?: boolean;
    [property: string]: any;
}

/**
 * Which text alignment formats are allowed.
 */
export interface TentacledTextAlign {
    /**
     * Which text alignments are allowed.
     */
    alignments?: Alignment[];
    [property: string]: any;
}

/**
 * Which HTML tags are allowed.
 */
export interface TentacledTags {
    /**
     * Which HTML headings levels are allowed.
     */
    headings?: any[];
    [property: string]: any;
}

export enum FluffyType {
    Attributes = "attributes",
    Checkbox = "checkbox",
    Classes = "classes",
    Code = "code",
    Color = "color",
    Date = "date",
    Datetime = "datetime",
    Files = "files",
    Gradient = "gradient",
    Group = "group",
    Icon = "icon",
    Link = "link",
    Message = "message",
    Number = "number",
    Radio = "radio",
    Range = "range",
    Repeater = "repeater",
    Richtext = "richtext",
    Select = "select",
    Tabs = "tabs",
    Text = "text",
    Textarea = "textarea",
    Toggle = "toggle",
    Unit = "unit",
    WYSIWYG = "wysiwyg",
}

export interface FluffyUnit {
    default?: number;
    label?:   string;
    value?:   string;
    [property: string]: any;
}

/**
 * Block specific options for rendering inside the Block Editor.
 */
export interface BlockstudioBlockEditor {
    /**
     * Whether the block should should load inside the Block Editor or show a placeholder.
     */
    disableLoading?: boolean;
    [property: string]: any;
}

export interface BlockstudioCondition {
    /**
     * ID of the field whose value will be used for the conditionally render it.
     */
    id?: string;
    /**
     * How the values should be compared.
     */
    operator: Operator;
    /**
     * Condition type.
     */
    type?: string;
    /**
     * Value that will be compared.
     */
    value?: boolean | number | string;
    [property: string]: any;
}

/**
 * Block specific options for the editor.
 */
export interface BlockstudioEditor {
    /**
     * List of WordPress script or style handles that should be added to the preview.
     */
    assets?: string[];
    [property: string]: any;
}

/**
 * Custom block transforms.
 */
export interface BlockstudioTransforms {
    from: StickyFrom[];
}

export interface StickyFrom {
    blocks?: string[];
    prefix?: string;
    regExp?: string;
    type:    FromType;
}

/**
 * It provides structured example data for the block. This data is used to construct a
 * preview for the block to be shown in the Inspector Help Panel when the user mouses over
 * the block.
 *
 * See the example documentation at
 * https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/#example-optional
 * for more details.
 */
export interface Example {
    /**
     * Set the attributes for the block example
     */
    attributes?: { [key: string]: any };
    /**
     * Set the inner blocks that should be used within the block example. The blocks should be
     * defined as a nested array like this:
     *
     * [ { "name": "core/heading", "attributes": { "content": "This is an Example" } } ]
     *
     * Where each block itself is an object that contains the block name, the block attributes,
     * and the blocks inner blocks.
     */
    innerBlocks?: any[];
    /**
     * The viewportWidth controls the width of the iFrame container in which the block preview
     * will get rendered
     */
    viewportWidth?: number;
    [property: string]: any;
}

/**
 * Provides custom CSS selectors and mappings for the block. Selectors may be set for the
 * block itself or per-feature e.g. typography. Custom selectors per feature or sub-feature,
 * allow different block styles to be applied to different elements within the block.
 */
export interface Selectors {
    /**
     * Custom CSS selector used to generate rules for the block's theme.json border styles.
     */
    border?: BorderObject | string;
    /**
     * Custom CSS selector used to generate rules for the block's theme.json color styles.
     */
    color?: ColorColor | string;
    /**
     * Custom CSS selector used to generate rules for the block's theme.json dimensions styles.
     */
    dimensions?: DimensionsDimensions | string;
    /**
     * The primary CSS class to apply to the block. This replaces the `.wp-block-name` class if
     * set.
     */
    root?: string;
    /**
     * Custom CSS selector used to generate rules for the block's theme.json spacing styles.
     */
    spacing?: SpacingSpacing | string;
    /**
     * Custom CSS selector used to generate rules for the block's theme.json typography styles.
     */
    typography?: TypographyTypography | string;
    [property: string]: any;
}

export interface BorderObject {
    color?:  string;
    radius?: string;
    root?:   string;
    style?:  string;
    width?:  string;
    [property: string]: any;
}

export interface ColorColor {
    background?: string;
    root?:       string;
    text?:       string;
    [property: string]: any;
}

export interface DimensionsDimensions {
    aspectRatio?: string;
    minHeight?:   string;
    root?:        string;
    [property: string]: any;
}

export interface SpacingSpacing {
    blockGap?: string;
    margin?:   string;
    padding?:  string;
    root?:     string;
    [property: string]: any;
}

export interface TypographyTypography {
    fontFamily?:     string;
    fontSize?:       string;
    fontStyle?:      string;
    fontWeight?:     string;
    letterSpacing?:  string;
    lineHeight?:     string;
    root?:           string;
    textDecoration?: string;
    textTransform?:  string;
    [property: string]: any;
}

export interface Style {
    isDefault?: boolean;
    label:      string;
    name:       string;
}

/**
 * It contains as set of options to control features used in the editor. See the supports
 * documentation at
 * https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/
 * for more details.
 */
export interface Supports {
    /**
     * This property adds block controls which allow to change block’s alignment.
     */
    align?: AlignElement[] | boolean;
    /**
     * This property allows to enable wide alignment for your theme. To disable this behavior
     * for a single block, set this flag to false.
     */
    alignWide?: boolean;
    /**
     * Anchors let you link directly to a specific block on a page. This property adds a field
     * to define an id for the block and a button to copy the direct link.
     */
    anchor?: boolean;
    /**
     * ARIA-labels let you define an accessible label for elements. This property allows
     * enabling the definition of an aria-label for the block, without exposing a UI field.
     */
    ariaLabel?: boolean;
    /**
     * This value signals that a block supports some of the CSS style properties related to
     * background. When it does, the block editor will show UI controls for the user to set
     * their values if the theme declares support.
     *
     * When the block declares support for a specific background property, its attributes
     * definition is extended to include the style attribute.
     */
    background?: Background;
    /**
     * By default, the class .wp-block-your-block-name is added to the root element of your
     * saved markup. This helps having a consistent mechanism for styling blocks that themes and
     * plugins can rely on. If, for whatever reason, a class is not desired on the markup, this
     * functionality can be disabled.
     */
    className?: boolean;
    /**
     * This value signals that a block supports some of the properties related to color. When it
     * does, the block editor will show UI controls for the user to set their values.
     *
     * Note that the background and text keys have a default value of true, so if the color
     * property is present they’ll also be considered enabled
     */
    color?: SupportsColor;
    /**
     * This property adds a field to define a custom className for the block’s wrapper.
     */
    customClassName?: boolean;
    /**
     * This value signals that a block supports some of the CSS style properties related to
     * dimensions. When it does, the block editor will show UI controls for the user to set
     * their values if the theme declares support.
     *
     * When the block declares support for a specific dimensions property, its attributes
     * definition is extended to include the style attribute.
     */
    dimensions?: SupportsDimensions;
    /**
     * This value signals that a block supports some of the properties related to filters. When
     * it does, the block editor will show UI controls for the user to set their values if the
     * theme declares support.
     *
     * When the block declares support for a specific filter property, its attributes definition
     * is extended to include the style attribute.
     */
    filter?: Filter;
    /**
     * By default, a block’s markup can be edited individually. To disable this behavior, set
     * html to false.
     */
    html?: boolean;
    /**
     * By default, all blocks will appear in the inserter, block transforms menu, Style Book,
     * etc. To hide a block from all parts of the user interface so that it can only be inserted
     * programmatically, set inserter to false.
     */
    inserter?: boolean;
    /**
     * Indicates if the block is using Interactivity API features.
     */
    interactivity?: boolean | InteractivityObject;
    /**
     * This value only applies to blocks that are containers for inner blocks. If set to `true`
     * the layout type will be `flow`. For other layout types it's necessary to set the `type`
     * explicitly inside the `default` object.
     */
    layout?: boolean | LayoutObject;
    /**
     * A block may want to disable the ability to toggle the lock state. It can be
     * locked/unlocked by a user from the block 'Options' dropdown by default. To disable this
     * behavior, set lock to false.
     */
    lock?: boolean;
    /**
     * A non-multiple block can be inserted into each post, one time only. For example, the
     * built-in ‘More’ block cannot be inserted again if it already exists in the post being
     * edited. A non-multiple block’s icon is automatically dimmed (unclickable) to prevent
     * multiple instances.
     */
    multiple?: boolean;
    /**
     * This value signals that a block supports some of the CSS style properties related to
     * position. When it does, the block editor will show UI controls for the user to set their
     * values if the theme declares support.
     *
     * When the block declares support for a specific position property, its attributes
     * definition is extended to include the style attribute.
     */
    position?: PositionObject;
    /**
     * By default, a block can be renamed by a user from the block 'Options' dropdown or the
     * 'Advanced' panel. To disable this behavior, set renaming to false.
     */
    renaming?: boolean;
    /**
     * A block may want to disable the ability of being converted into a reusable block. By
     * default all blocks can be converted to a reusable block. If supports reusable is set to
     * false, the option to convert the block into a reusable block will not appear.
     */
    reusable?: boolean;
    /**
     * Allow blocks to define a box shadow.
     */
    shadow?: boolean | { [key: string]: any };
    /**
     * This value signals that a block supports some of the CSS style properties related to
     * spacing. When it does, the block editor will show UI controls for the user to set their
     * values if the theme declares support.
     *
     * When the block declares support for a specific spacing property, its attributes
     * definition is extended to include the style attribute.
     */
    spacing?: SupportsSpacing;
    /**
     * This property indicates whether the block can split when the Enter key is pressed or when
     * blocks are pasted.
     */
    splitting?: boolean;
    /**
     * This value signals that a block supports some of the CSS style properties related to
     * typography. When it does, the block editor will show UI controls for the user to set
     * their values if the theme declares support.
     *
     * When the block declares support for a specific typography property, its attributes
     * definition is extended to include the style attribute.
     */
    typography?: SupportsTypography;
    [property: string]: any;
}

export enum AlignElement {
    Center = "center",
    Full = "full",
    Left = "left",
    Right = "right",
    Wide = "wide",
}

/**
 * This value signals that a block supports some of the CSS style properties related to
 * background. When it does, the block editor will show UI controls for the user to set
 * their values if the theme declares support.
 *
 * When the block declares support for a specific background property, its attributes
 * definition is extended to include the style attribute.
 */
export interface Background {
    /**
     * Allow blocks to define a background image.
     */
    backgroundImage?: boolean;
    /**
     * Allow blocks to define values related to the size of a background image, including size,
     * position, and repeat controls
     */
    backgroundSize?: boolean;
    [property: string]: any;
}

/**
 * This value signals that a block supports some of the properties related to color. When it
 * does, the block editor will show UI controls for the user to set their values.
 *
 * Note that the background and text keys have a default value of true, so if the color
 * property is present they’ll also be considered enabled
 */
export interface SupportsColor {
    /**
     * This property adds UI controls which allow the user to apply a solid background color to
     * a block.
     *
     * When color support is declared, this property is enabled by default (along with text), so
     * simply setting color will enable background color.
     *
     * To disable background support while keeping other color supports enabled, set to false.
     *
     * When the block declares support for color.background, its attributes definition is
     * extended to include two new attributes: backgroundColor and style
     */
    background?: boolean;
    /**
     * This property adds block controls which allow the user to set button colors in a block.
     * Button color is disabled by default.
     *
     * Button color presets are sourced from the editor-color-palette theme support.
     *
     * When the block declares support for color.button, its attributes definition is extended
     * to include the style attribute
     */
    button?: boolean;
    /**
     * Determines whether the contrast checker widget displays in the block editor UI.
     *
     * The contrast checker appears only if the block declares support for color. It tests the
     * readability of color combinations and warns if there is a potential issue. The property
     * is enabled by default.
     *
     * Set to `false` to explicitly disable.
     */
    enableContrastChecker?: boolean;
    /**
     * This property adds UI controls which allow the user to apply a gradient background to a
     * block.
     *
     * Gradient presets are sourced from editor-gradient-presets theme support.
     *
     * When the block declares support for color.gradient, its attributes definition is extended
     * to include two new attributes: gradient and style
     */
    gradients?: boolean;
    /**
     * This property adds block controls which allow the user to set heading colors in a block.
     * Heading color is disabled by default.
     *
     * Heading color presets are sourced from the editor-color-palette theme support.
     *
     * When the block declares support for color.heading, its attributes definition is extended
     * to include the style attribute
     */
    heading?: boolean;
    /**
     * This property adds block controls which allow the user to set link color in a block, link
     * color is disabled by default.
     *
     * Link color presets are sourced from the editor-color-palette theme support.
     *
     * When the block declares support for color.link, its attributes definition is extended to
     * include the style attribute
     */
    link?: boolean;
    /**
     * This property adds block controls which allow the user to set text color in a block.
     *
     * When color support is declared, this property is enabled by default (along with
     * background), so simply setting color will enable text color.
     *
     * Text color presets are sourced from the editor-color-palette theme support.
     *
     * When the block declares support for color.text, its attributes definition is extended to
     * include two new attributes: textColor and style
     */
    text?: boolean;
    [property: string]: any;
}

/**
 * This value signals that a block supports some of the CSS style properties related to
 * dimensions. When it does, the block editor will show UI controls for the user to set
 * their values if the theme declares support.
 *
 * When the block declares support for a specific dimensions property, its attributes
 * definition is extended to include the style attribute.
 */
export interface SupportsDimensions {
    /**
     * Allow blocks to define an aspect ratio value.
     */
    aspectRatio?: boolean;
    /**
     * Allow blocks to define a minimum height value.
     */
    minHeight?: boolean;
    [property: string]: any;
}

/**
 * This value signals that a block supports some of the properties related to filters. When
 * it does, the block editor will show UI controls for the user to set their values if the
 * theme declares support.
 *
 * When the block declares support for a specific filter property, its attributes definition
 * is extended to include the style attribute.
 */
export interface Filter {
    /**
     * Allow blocks to define a duotone filter.
     */
    duotone?: boolean;
    [property: string]: any;
}

export interface InteractivityObject {
    /**
     * Indicates whether a block is compatible with the Interactivity API client-side
     * navigation.
     *
     * Set it to true only if the block is not interactive or if it is interactive using the
     * Interactivity API. Set it to false if the block is interactive but uses vanilla JS,
     * jQuery or another JS framework/library other than the Interactivity API.
     */
    clientNavigation?: boolean;
    /**
     * Indicates whether the block is using the Interactivity API directives.
     */
    interactive?: boolean;
    [property: string]: any;
}

export interface LayoutObject {
    /**
     * For the `constrained` layout type only, determines display of the custom content and wide
     * size controls in the block sidebar.
     */
    allowCustomContentAndWideSize?: boolean;
    /**
     * Determines display of layout controls in the block sidebar. If set to false, layout
     * controls will be hidden.
     */
    allowEditing?: boolean;
    /**
     * For the `flow` layout type only, determines display of the `Inner blocks use content
     * width` toggle.
     */
    allowInheriting?: boolean;
    /**
     * For the `flex` layout type, determines display of justification controls in the block
     * toolbar and block sidebar. For the `constrained` layout type, determines display of
     * justification control in the block sidebar.
     */
    allowJustification?: boolean;
    /**
     * For the `flex` layout type only, determines display of the orientation control in the
     * block toolbar.
     */
    allowOrientation?: boolean;
    /**
     * For the `flex` layout type only, determines display of sizing controls (Fit/Fill/Fixed)
     * on all child blocks of the flex block.
     */
    allowSizingOnChildren?: boolean;
    /**
     * Exposes a switcher control that allows toggling between all existing layout types.
     */
    allowSwitching?: boolean;
    /**
     * For the `flex` layout type only, determines display of vertical alignment controls in the
     * block toolbar.
     */
    allowVerticalAlignment?: boolean;
    /**
     * Allows setting the `type` property to define what layout type is default for the block,
     * and also default values for any properties inherent to that layout type, e.g., for a
     * `flex` layout, a default value can be set for `flexWrap`.
     */
    default?: DefaultObject;
    [property: string]: any;
}

/**
 * Allows setting the `type` property to define what layout type is default for the block,
 * and also default values for any properties inherent to that layout type, e.g., for a
 * `flex` layout, a default value can be set for `flexWrap`.
 */
export interface DefaultObject {
    /**
     * The column count value.
     */
    columnCount?: number;
    /**
     * The content size used on all children.
     */
    contentSize?: string;
    /**
     * The flex wrap value.
     */
    flexWrap?: FlexWrap;
    /**
     * Content justification value.
     */
    justifyContent?: JustifyContent;
    /**
     * The minimum column width value.
     */
    minimumColumnWidth?: string;
    /**
     * The orientation of the layout.
     */
    orientation?: Orientation;
    /**
     * The layout type.
     */
    type?: DefaultType;
    /**
     * The vertical alignment value.
     */
    verticalAlignment?: VerticalAlignment;
    /**
     * The wide size used on alignwide children.
     */
    wideSize?: string;
    [property: string]: any;
}

/**
 * The flex wrap value.
 */
export enum FlexWrap {
    Nowrap = "nowrap",
    Wrap = "wrap",
}

/**
 * Content justification value.
 */
export enum JustifyContent {
    Center = "center",
    Left = "left",
    Right = "right",
    SpaceBetween = "space-between",
    Stretch = "stretch",
}

/**
 * The orientation of the layout.
 */
export enum Orientation {
    Horizontal = "horizontal",
    Vertical = "vertical",
}

/**
 * The layout type.
 */
export enum DefaultType {
    Constrained = "constrained",
    Flex = "flex",
    Grid = "grid",
}

/**
 * The vertical alignment value.
 */
export enum VerticalAlignment {
    Bottom = "bottom",
    Center = "center",
    SpaceBetween = "space-between",
    Stretch = "stretch",
    Top = "top",
}

/**
 * This value signals that a block supports some of the CSS style properties related to
 * position. When it does, the block editor will show UI controls for the user to set their
 * values if the theme declares support.
 *
 * When the block declares support for a specific position property, its attributes
 * definition is extended to include the style attribute.
 */
export interface PositionObject {
    /**
     * Allow blocks to stick to their immediate parent when scrolling the page.
     */
    sticky?: boolean;
    [property: string]: any;
}

/**
 * This value signals that a block supports some of the CSS style properties related to
 * spacing. When it does, the block editor will show UI controls for the user to set their
 * values if the theme declares support.
 *
 * When the block declares support for a specific spacing property, its attributes
 * definition is extended to include the style attribute.
 */
export interface SupportsSpacing {
    margin?:  MarginElement[] | boolean;
    padding?: MarginElement[] | boolean;
    [property: string]: any;
}

export enum MarginElement {
    Bottom = "bottom",
    Horizontal = "horizontal",
    Left = "left",
    Right = "right",
    Top = "top",
    Vertical = "vertical",
}

/**
 * This value signals that a block supports some of the CSS style properties related to
 * typography. When it does, the block editor will show UI controls for the user to set
 * their values if the theme declares support.
 *
 * When the block declares support for a specific typography property, its attributes
 * definition is extended to include the style attribute.
 */
export interface SupportsTypography {
    /**
     * This value signals that a block supports the font-size CSS style property. When it does,
     * the block editor will show an UI control for the user to set its value.
     *
     * The values shown in this control are the ones declared by the theme via the
     * editor-font-sizes theme support, or the default ones if none is provided.
     *
     * When the block declares support for fontSize, its attributes definition is extended to
     * include two new attributes: fontSize and style
     */
    fontSize?: boolean;
    /**
     * This value signals that a block supports the line-height CSS style property. When it
     * does, the block editor will show an UI control for the user to set its value if the theme
     * declares support.
     *
     * When the block declares support for lineHeight, its attributes definition is extended to
     * include a new attribute style of object type with no default assigned. It stores the
     * custom value set by the user. The block can apply a default style by specifying its own
     * style attribute with a default
     */
    lineHeight?: boolean;
    /**
     * This property adds block toolbar controls which allow to change block's text alignment.
     */
    textAlign?: TextAlignElement[] | boolean;
    [property: string]: any;
}

export enum TextAlignElement {
    Center = "center",
    Left = "left",
    Right = "right",
}

/**
 * An array of block variations.
 */
export interface Variation {
    /**
     * Values that override block attributes.
     */
    attributes?: { [key: string]: any };
    /**
     * A category classification, used in search interfaces to arrange block types by category.
     */
    category?: string;
    /**
     * A detailed variation description.
     */
    description?: string;
    /**
     * Example provides structured data for the block preview. You can set to undefined to
     * disable the preview shown for the block type.
     */
    example?: { [key: string]: any };
    /**
     * An icon helping to visualize the variation. It can have the same shape as the block type.
     */
    icon?: string;
    /**
     * Initial configuration of nested blocks.
     */
    innerBlocks?: Array<any[]>;
    /**
     * The list of attributes that should be compared. Each attributes will be matched and the
     * variation will be active if all of them are matching.
     */
    isActive?: string[];
    /**
     * Indicates whether the current variation is the default one.
     */
    isDefault?: boolean;
    /**
     * An array of terms (which can be translated) that help users discover the variation while
     * searching.
     */
    keywords?: string[];
    /**
     * The unique and machine-readable name.
     */
    name: string;
    /**
     * The list of scopes where the variation is applicable.
     */
    scope?: Scope[];
    /**
     * A human-readable variation title.
     */
    title: string;
}

export enum Scope {
    Block = "block",
    Inserter = "inserter",
    Transform = "transform",
}

// Converts JSON strings to/from your types
// and asserts the results of JSON.parse at runtime
export class Convert {
    public static toBlock(json: string): Block {
        return cast(JSON.parse(json), r("Block"));
    }

    public static blockToJson(value: Block): string {
        return JSON.stringify(uncast(value, r("Block")), null, 2);
    }
}

function invalidValue(typ: any, val: any, key: any, parent: any = ''): never {
    const prettyTyp = prettyTypeName(typ);
    const parentText = parent ? ` on ${parent}` : '';
    const keyText = key ? ` for key "${key}"` : '';
    throw Error(`Invalid value${keyText}${parentText}. Expected ${prettyTyp} but got ${JSON.stringify(val)}`);
}

function prettyTypeName(typ: any): string {
    if (Array.isArray(typ)) {
        if (typ.length === 2 && typ[0] === undefined) {
            return `an optional ${prettyTypeName(typ[1])}`;
        } else {
            return `one of [${typ.map(a => { return prettyTypeName(a); }).join(", ")}]`;
        }
    } else if (typeof typ === "object" && typ.literal !== undefined) {
        return typ.literal;
    } else {
        return typeof typ;
    }
}

function jsonToJSProps(typ: any): any {
    if (typ.jsonToJS === undefined) {
        const map: any = {};
        typ.props.forEach((p: any) => map[p.json] = { key: p.js, typ: p.typ });
        typ.jsonToJS = map;
    }
    return typ.jsonToJS;
}

function jsToJSONProps(typ: any): any {
    if (typ.jsToJSON === undefined) {
        const map: any = {};
        typ.props.forEach((p: any) => map[p.js] = { key: p.json, typ: p.typ });
        typ.jsToJSON = map;
    }
    return typ.jsToJSON;
}

function transform(val: any, typ: any, getProps: any, key: any = '', parent: any = ''): any {
    function transformPrimitive(typ: string, val: any): any {
        if (typeof typ === typeof val) return val;
        return invalidValue(typ, val, key, parent);
    }

    function transformUnion(typs: any[], val: any): any {
        // val must validate against one typ in typs
        const l = typs.length;
        for (let i = 0; i < l; i++) {
            const typ = typs[i];
            try {
                return transform(val, typ, getProps);
            } catch {}
        }
        return invalidValue(typs, val, key, parent);
    }

    function transformEnum(cases: string[], val: any): any {
        if (cases.indexOf(val) !== -1) return val;
        return invalidValue(cases.map(a => { return l(a); }), val, key, parent);
    }

    function transformArray(typ: any, val: any): any {
        // val must be an array with no invalid elements
        if (!Array.isArray(val)) return invalidValue(l("array"), val, key, parent);
        return val.map(el => transform(el, typ, getProps));
    }

    function transformDate(val: any): any {
        if (val === null) {
            return null;
        }
        const d = new Date(val);
        if (isNaN(d.valueOf())) {
            return invalidValue(l("Date"), val, key, parent);
        }
        return d;
    }

    function transformObject(props: { [k: string]: any }, additional: any, val: any): any {
        if (val === null || typeof val !== "object" || Array.isArray(val)) {
            return invalidValue(l(ref || "object"), val, key, parent);
        }
        const result: any = {};
        Object.getOwnPropertyNames(props).forEach(key => {
            const prop = props[key];
            const v = Object.prototype.hasOwnProperty.call(val, key) ? val[key] : undefined;
            result[prop.key] = transform(v, prop.typ, getProps, key, ref);
        });
        Object.getOwnPropertyNames(val).forEach(key => {
            if (!Object.prototype.hasOwnProperty.call(props, key)) {
                result[key] = transform(val[key], additional, getProps, key, ref);
            }
        });
        return result;
    }

    if (typ === "any") return val;
    if (typ === null) {
        if (val === null) return val;
        return invalidValue(typ, val, key, parent);
    }
    if (typ === false) return invalidValue(typ, val, key, parent);
    let ref: any = undefined;
    while (typeof typ === "object" && typ.ref !== undefined) {
        ref = typ.ref;
        typ = typeMap[typ.ref];
    }
    if (Array.isArray(typ)) return transformEnum(typ, val);
    if (typeof typ === "object") {
        return typ.hasOwnProperty("unionMembers") ? transformUnion(typ.unionMembers, val)
            : typ.hasOwnProperty("arrayItems")    ? transformArray(typ.arrayItems, val)
            : typ.hasOwnProperty("props")         ? transformObject(getProps(typ), typ.additional, val)
            : invalidValue(typ, val, key, parent);
    }
    // Numbers can be parsed by Date but shouldn't be.
    if (typ === Date && typeof val !== "number") return transformDate(val);
    return transformPrimitive(typ, val);
}

function cast<T>(val: any, typ: any): T {
    return transform(val, typ, jsonToJSProps);
}

function uncast<T>(val: T, typ: any): any {
    return transform(val, typ, jsToJSONProps);
}

function l(typ: any) {
    return { literal: typ };
}

function a(typ: any) {
    return { arrayItems: typ };
}

function u(...typs: any[]) {
    return { unionMembers: typs };
}

function o(props: any[], additional: any) {
    return { props, additional };
}

function m(additional: any) {
    return { props: [], additional };
}

function r(name: string) {
    return { ref: name };
}

const typeMap: any = {
    "Block": o([
        { json: "$schema", js: "$schema", typ: u(undefined, "") },
        { json: "__experimental", js: "__experimental", typ: u(undefined, u(true, "")) },
        { json: "allowedBlocks", js: "allowedBlocks", typ: u(undefined, a("")) },
        { json: "ancestor", js: "ancestor", typ: u(undefined, a("")) },
        { json: "apiVersion", js: "apiVersion", typ: u(undefined, 0) },
        { json: "attributes", js: "attributes", typ: u(undefined, r("Attributes")) },
        { json: "blockHooks", js: "blockHooks", typ: u(undefined, r("BlockHooks")) },
        { json: "blockstudio", js: "blockstudio", typ: u(undefined, u(true, r("BlockstudioClass"))) },
        { json: "category", js: "category", typ: u(undefined, "") },
        { json: "description", js: "description", typ: u(undefined, "") },
        { json: "editorScript", js: "editorScript", typ: u(undefined, u(a(""), "")) },
        { json: "editorStyle", js: "editorStyle", typ: u(undefined, u(a(""), "")) },
        { json: "example", js: "example", typ: u(undefined, r("Example")) },
        { json: "icon", js: "icon", typ: u(undefined, "") },
        { json: "keywords", js: "keywords", typ: u(undefined, a("")) },
        { json: "name", js: "name", typ: "" },
        { json: "parent", js: "parent", typ: u(undefined, a("")) },
        { json: "providesContext", js: "providesContext", typ: u(undefined, m("any")) },
        { json: "render", js: "render", typ: u(undefined, "") },
        { json: "script", js: "script", typ: u(undefined, u(a(""), "")) },
        { json: "selectors", js: "selectors", typ: u(undefined, r("Selectors")) },
        { json: "style", js: "style", typ: u(undefined, u(a(""), "")) },
        { json: "styles", js: "styles", typ: u(undefined, a(r("Style"))) },
        { json: "supports", js: "supports", typ: u(undefined, r("Supports")) },
        { json: "textdomain", js: "textdomain", typ: u(undefined, "") },
        { json: "title", js: "title", typ: "" },
        { json: "usesContext", js: "usesContext", typ: u(undefined, a("")) },
        { json: "variations", js: "variations", typ: u(undefined, u(a(r("Variation")), "")) },
        { json: "version", js: "version", typ: u(undefined, "") },
        { json: "viewScript", js: "viewScript", typ: u(undefined, u(a(""), "")) },
        { json: "viewScriptModule", js: "viewScriptModule", typ: u(undefined, u(a(""), "")) },
        { json: "viewStyle", js: "viewStyle", typ: u(undefined, u(a(""), "")) },
    ], false),
    "Attributes": o([
    ], false),
    "BlockHooks": o([
    ], false),
    "BlockstudioClass": o([
        { json: "attributes", js: "attributes", typ: u(undefined, a(r("BlockstudioAttribute"))) },
        { json: "blockEditor", js: "blockEditor", typ: u(undefined, r("BlockstudioBlockEditor")) },
        { json: "conditions", js: "conditions", typ: u(undefined, a(a(r("BlockstudioCondition")))) },
        { json: "editor", js: "editor", typ: u(undefined, r("BlockstudioEditor")) },
        { json: "icon", js: "icon", typ: u(undefined, "") },
        { json: "innerBlocks", js: "innerBlocks", typ: u(undefined, "") },
        { json: "override", js: "override", typ: u(undefined, true) },
        { json: "refreshOn", js: "refreshOn", typ: u(undefined, a("")) },
        { json: "transforms", js: "transforms", typ: u(undefined, r("BlockstudioTransforms")) },
    ], false),
    "BlockstudioAttribute": o([
        { json: "conditions", js: "conditions", typ: u(undefined, a(a(r("StickyCondition")))) },
        { json: "description", js: "description", typ: u(undefined, "") },
        { json: "help", js: "help", typ: u(undefined, "") },
        { json: "hidden", js: "hidden", typ: u(undefined, true) },
        { json: "id", js: "id", typ: u(undefined, "") },
        { json: "key", js: "key", typ: u(undefined, "") },
        { json: "label", js: "label", typ: u(undefined, "") },
        { json: "switch", js: "switch", typ: u(undefined, true) },
        { json: "type", js: "type", typ: r("FluffyType") },
        { json: "default", js: "default", typ: u(undefined, u(a("any"), true, 3.14, m("any"), "")) },
        { json: "fallback", js: "fallback", typ: u(undefined, u(a("any"), true, 3.14, m("any"), "")) },
        { json: "link", js: "link", typ: u(undefined, true) },
        { json: "media", js: "media", typ: u(undefined, true) },
        { json: "options", js: "options", typ: u(undefined, a(r("FluffyOption"))) },
        { json: "populate", js: "populate", typ: u(undefined, r("FluffyPopulate")) },
        { json: "returnFormat", js: "returnFormat", typ: u(undefined, r("ReturnFormatEnum")) },
        { json: "tailwind", js: "tailwind", typ: u(undefined, true) },
        { json: "autoCompletion", js: "autoCompletion", typ: u(undefined, true) },
        { json: "foldGutter", js: "foldGutter", typ: u(undefined, true) },
        { json: "height", js: "height", typ: u(undefined, "") },
        { json: "language", js: "language", typ: u(undefined, r("Language")) },
        { json: "lineNumbers", js: "lineNumbers", typ: u(undefined, true) },
        { json: "maxHeight", js: "maxHeight", typ: u(undefined, "") },
        { json: "minHeight", js: "minHeight", typ: u(undefined, "") },
        { json: "clearable", js: "clearable", typ: u(undefined, true) },
        { json: "disableCustomColors", js: "disableCustomColors", typ: u(undefined, true) },
        { json: "startOfWeek", js: "startOfWeek", typ: u(undefined, 3.14) },
        { json: "is12Hour", js: "is12Hour", typ: u(undefined, true) },
        { json: "addToGallery", js: "addToGallery", typ: u(undefined, true) },
        { json: "allowedTypes", js: "allowedTypes", typ: u(undefined, u(a("any"), "")) },
        { json: "gallery", js: "gallery", typ: u(undefined, true) },
        { json: "max", js: "max", typ: u(undefined, 3.14) },
        { json: "min", js: "min", typ: u(undefined, 3.14) },
        { json: "multiple", js: "multiple", typ: u(undefined, true) },
        { json: "returnSize", js: "returnSize", typ: u(undefined, "") },
        { json: "size", js: "size", typ: u(undefined, true) },
        { json: "textMediaButton", js: "textMediaButton", typ: u(undefined, "") },
        { json: "title", js: "title", typ: u(undefined, "") },
        { json: "disableCustomGradients", js: "disableCustomGradients", typ: u(undefined, true) },
        { json: "attributes", js: "attributes", typ: u(undefined, a(r("AttributeAttribute"))) },
        { json: "class", js: "class", typ: u(undefined, "") },
        { json: "initialOpen", js: "initialOpen", typ: u(undefined, true) },
        { json: "opened", js: "opened", typ: u(undefined, true) },
        { json: "scrollAfterOpen", js: "scrollAfterOpen", typ: u(undefined, true) },
        { json: "style", js: "style", typ: u(undefined, m("any")) },
        { json: "sets", js: "sets", typ: u(undefined, u(a("any"), "")) },
        { json: "subSets", js: "subSets", typ: u(undefined, u(a("any"), "")) },
        { json: "hasRichPreviews", js: "hasRichPreviews", typ: u(undefined, true) },
        { json: "noDirectEntry", js: "noDirectEntry", typ: u(undefined, true) },
        { json: "noURLSuggestion", js: "noURLSuggestion", typ: u(undefined, true) },
        { json: "opensInNewTab", js: "opensInNewTab", typ: u(undefined, true) },
        { json: "showSuggestions", js: "showSuggestions", typ: u(undefined, true) },
        { json: "textButton", js: "textButton", typ: u(undefined, "") },
        { json: "withCreateSuggestion", js: "withCreateSuggestion", typ: u(undefined, true) },
        { json: "value", js: "value", typ: u(undefined, "") },
        { json: "dragDirection", js: "dragDirection", typ: u(undefined, r("DragDirection")) },
        { json: "dragThreshold", js: "dragThreshold", typ: u(undefined, 3.14) },
        { json: "hideHTMLArrows", js: "hideHTMLArrows", typ: u(undefined, true) },
        { json: "isShiftStepEnabled", js: "isShiftStepEnabled", typ: u(undefined, true) },
        { json: "required", js: "required", typ: u(undefined, true) },
        { json: "shiftStep", js: "shiftStep", typ: u(undefined, 3.14) },
        { json: "step", js: "step", typ: u(undefined, 3.14) },
        { json: "allowReset", js: "allowReset", typ: u(undefined, true) },
        { json: "initialPosition", js: "initialPosition", typ: u(undefined, 3.14) },
        { json: "marks", js: "marks", typ: u(undefined, a(r("FluffyMark"))) },
        { json: "railColor", js: "railColor", typ: u(undefined, "") },
        { json: "resetFallbackValue", js: "resetFallbackValue", typ: u(undefined, 3.14) },
        { json: "separatorType", js: "separatorType", typ: u(undefined, r("SeparatorType")) },
        { json: "showTooltip", js: "showTooltip", typ: u(undefined, true) },
        { json: "trackColor", js: "trackColor", typ: u(undefined, "") },
        { json: "withInputField", js: "withInputField", typ: u(undefined, true) },
        { json: "allowNull", js: "allowNull", typ: u(undefined, u(true, "")) },
        { json: "stylisedUi", js: "stylisedUi", typ: u(undefined, true) },
        { json: "tabs", js: "tabs", typ: u(undefined, a(r("FluffyTab"))) },
        { json: "rows", js: "rows", typ: u(undefined, 3.14) },
        { json: "textMinimized", js: "textMinimized", typ: u(undefined, u(r("FluffyTextMinimized"), "")) },
        { json: "textRemove", js: "textRemove", typ: u(undefined, u(true, "")) },
        { json: "disableUnits", js: "disableUnits", typ: u(undefined, true) },
        { json: "isPressEnterToChange", js: "isPressEnterToChange", typ: u(undefined, true) },
        { json: "isResetValueOnUnitChange", js: "isResetValueOnUnitChange", typ: u(undefined, true) },
        { json: "isUnitSelectTabbable", js: "isUnitSelectTabbable", typ: u(undefined, true) },
        { json: "units", js: "units", typ: u(undefined, a(r("FluffyUnit"))) },
        { json: "toolbar", js: "toolbar", typ: u(undefined, r("FluffyToolbar")) },
    ], "any"),
    "AttributeAttribute": o([
        { json: "conditions", js: "conditions", typ: u(undefined, a(a(r("FluffyCondition")))) },
        { json: "description", js: "description", typ: u(undefined, "") },
        { json: "help", js: "help", typ: u(undefined, "") },
        { json: "hidden", js: "hidden", typ: u(undefined, true) },
        { json: "id", js: "id", typ: u(undefined, "") },
        { json: "key", js: "key", typ: u(undefined, "") },
        { json: "label", js: "label", typ: u(undefined, "") },
        { json: "switch", js: "switch", typ: u(undefined, true) },
        { json: "type", js: "type", typ: r("PurpleType") },
        { json: "default", js: "default", typ: u(undefined, u(a("any"), true, 3.14, m("any"), "")) },
        { json: "fallback", js: "fallback", typ: u(undefined, u(a("any"), true, 3.14, m("any"), "")) },
        { json: "link", js: "link", typ: u(undefined, true) },
        { json: "media", js: "media", typ: u(undefined, true) },
        { json: "options", js: "options", typ: u(undefined, a(r("PurpleOption"))) },
        { json: "populate", js: "populate", typ: u(undefined, r("PurplePopulate")) },
        { json: "returnFormat", js: "returnFormat", typ: u(undefined, r("ReturnFormatEnum")) },
        { json: "tailwind", js: "tailwind", typ: u(undefined, true) },
        { json: "autoCompletion", js: "autoCompletion", typ: u(undefined, true) },
        { json: "foldGutter", js: "foldGutter", typ: u(undefined, true) },
        { json: "height", js: "height", typ: u(undefined, "") },
        { json: "language", js: "language", typ: u(undefined, r("Language")) },
        { json: "lineNumbers", js: "lineNumbers", typ: u(undefined, true) },
        { json: "maxHeight", js: "maxHeight", typ: u(undefined, "") },
        { json: "minHeight", js: "minHeight", typ: u(undefined, "") },
        { json: "clearable", js: "clearable", typ: u(undefined, true) },
        { json: "disableCustomColors", js: "disableCustomColors", typ: u(undefined, true) },
        { json: "startOfWeek", js: "startOfWeek", typ: u(undefined, 3.14) },
        { json: "is12Hour", js: "is12Hour", typ: u(undefined, true) },
        { json: "addToGallery", js: "addToGallery", typ: u(undefined, true) },
        { json: "allowedTypes", js: "allowedTypes", typ: u(undefined, u(a("any"), "")) },
        { json: "gallery", js: "gallery", typ: u(undefined, true) },
        { json: "max", js: "max", typ: u(undefined, 3.14) },
        { json: "min", js: "min", typ: u(undefined, 3.14) },
        { json: "multiple", js: "multiple", typ: u(undefined, true) },
        { json: "returnSize", js: "returnSize", typ: u(undefined, "") },
        { json: "size", js: "size", typ: u(undefined, true) },
        { json: "textMediaButton", js: "textMediaButton", typ: u(undefined, "") },
        { json: "title", js: "title", typ: u(undefined, "") },
        { json: "disableCustomGradients", js: "disableCustomGradients", typ: u(undefined, true) },
        { json: "sets", js: "sets", typ: u(undefined, u(a("any"), "")) },
        { json: "subSets", js: "subSets", typ: u(undefined, u(a("any"), "")) },
        { json: "hasRichPreviews", js: "hasRichPreviews", typ: u(undefined, true) },
        { json: "noDirectEntry", js: "noDirectEntry", typ: u(undefined, true) },
        { json: "noURLSuggestion", js: "noURLSuggestion", typ: u(undefined, true) },
        { json: "opensInNewTab", js: "opensInNewTab", typ: u(undefined, true) },
        { json: "showSuggestions", js: "showSuggestions", typ: u(undefined, true) },
        { json: "textButton", js: "textButton", typ: u(undefined, "") },
        { json: "withCreateSuggestion", js: "withCreateSuggestion", typ: u(undefined, true) },
        { json: "value", js: "value", typ: u(undefined, "") },
        { json: "dragDirection", js: "dragDirection", typ: u(undefined, r("DragDirection")) },
        { json: "dragThreshold", js: "dragThreshold", typ: u(undefined, 3.14) },
        { json: "hideHTMLArrows", js: "hideHTMLArrows", typ: u(undefined, true) },
        { json: "isShiftStepEnabled", js: "isShiftStepEnabled", typ: u(undefined, true) },
        { json: "required", js: "required", typ: u(undefined, true) },
        { json: "shiftStep", js: "shiftStep", typ: u(undefined, 3.14) },
        { json: "step", js: "step", typ: u(undefined, 3.14) },
        { json: "allowReset", js: "allowReset", typ: u(undefined, true) },
        { json: "initialPosition", js: "initialPosition", typ: u(undefined, 3.14) },
        { json: "marks", js: "marks", typ: u(undefined, a(r("PurpleMark"))) },
        { json: "railColor", js: "railColor", typ: u(undefined, "") },
        { json: "resetFallbackValue", js: "resetFallbackValue", typ: u(undefined, 3.14) },
        { json: "separatorType", js: "separatorType", typ: u(undefined, r("SeparatorType")) },
        { json: "showTooltip", js: "showTooltip", typ: u(undefined, true) },
        { json: "trackColor", js: "trackColor", typ: u(undefined, "") },
        { json: "withInputField", js: "withInputField", typ: u(undefined, true) },
        { json: "allowNull", js: "allowNull", typ: u(undefined, u(true, "")) },
        { json: "stylisedUi", js: "stylisedUi", typ: u(undefined, true) },
        { json: "tabs", js: "tabs", typ: u(undefined, a(r("PurpleTab"))) },
        { json: "rows", js: "rows", typ: u(undefined, 3.14) },
        { json: "attributes", js: "attributes", typ: u(undefined, a(r("Attribute"))) },
        { json: "textMinimized", js: "textMinimized", typ: u(undefined, u(r("PurpleTextMinimized"), "")) },
        { json: "textRemove", js: "textRemove", typ: u(undefined, u(true, "")) },
        { json: "disableUnits", js: "disableUnits", typ: u(undefined, true) },
        { json: "isPressEnterToChange", js: "isPressEnterToChange", typ: u(undefined, true) },
        { json: "isResetValueOnUnitChange", js: "isResetValueOnUnitChange", typ: u(undefined, true) },
        { json: "isUnitSelectTabbable", js: "isUnitSelectTabbable", typ: u(undefined, true) },
        { json: "units", js: "units", typ: u(undefined, a(r("PurpleUnit"))) },
        { json: "toolbar", js: "toolbar", typ: u(undefined, r("PurpleToolbar")) },
    ], "any"),
    "AttributeTab": o([
        { json: "attributes", js: "attributes", typ: u(undefined, a(r("Attribute"))) },
        { json: "blockEditor", js: "blockEditor", typ: u(undefined, r("PurpleBlockEditor")) },
        { json: "conditions", js: "conditions", typ: u(undefined, a(a(r("PurpleCondition")))) },
        { json: "editor", js: "editor", typ: u(undefined, r("PurpleEditor")) },
        { json: "icon", js: "icon", typ: u(undefined, "") },
        { json: "innerBlocks", js: "innerBlocks", typ: u(undefined, "") },
        { json: "override", js: "override", typ: u(undefined, true) },
        { json: "refreshOn", js: "refreshOn", typ: u(undefined, a("")) },
        { json: "title", js: "title", typ: "" },
        { json: "transforms", js: "transforms", typ: u(undefined, r("PurpleTransforms")) },
    ], "any"),
    "Attribute": o([
        { json: "conditions", js: "conditions", typ: u(undefined, a(a(r("AttributeCondition")))) },
        { json: "description", js: "description", typ: u(undefined, "") },
        { json: "help", js: "help", typ: u(undefined, "") },
        { json: "hidden", js: "hidden", typ: u(undefined, true) },
        { json: "id", js: "id", typ: u(undefined, "") },
        { json: "key", js: "key", typ: u(undefined, "") },
        { json: "label", js: "label", typ: u(undefined, "") },
        { json: "switch", js: "switch", typ: u(undefined, true) },
        { json: "type", js: "type", typ: r("AttributeType") },
        { json: "default", js: "default", typ: u(undefined, u(a("any"), true, 3.14, m("any"), "")) },
        { json: "fallback", js: "fallback", typ: u(undefined, u(a("any"), true, 3.14, m("any"), "")) },
        { json: "link", js: "link", typ: u(undefined, true) },
        { json: "media", js: "media", typ: u(undefined, true) },
        { json: "options", js: "options", typ: u(undefined, a(r("AttributeOption"))) },
        { json: "populate", js: "populate", typ: u(undefined, r("AttributePopulate")) },
        { json: "returnFormat", js: "returnFormat", typ: u(undefined, r("ReturnFormatEnum")) },
        { json: "tailwind", js: "tailwind", typ: u(undefined, true) },
        { json: "autoCompletion", js: "autoCompletion", typ: u(undefined, true) },
        { json: "foldGutter", js: "foldGutter", typ: u(undefined, true) },
        { json: "height", js: "height", typ: u(undefined, "") },
        { json: "language", js: "language", typ: u(undefined, r("Language")) },
        { json: "lineNumbers", js: "lineNumbers", typ: u(undefined, true) },
        { json: "maxHeight", js: "maxHeight", typ: u(undefined, "") },
        { json: "minHeight", js: "minHeight", typ: u(undefined, "") },
        { json: "clearable", js: "clearable", typ: u(undefined, true) },
        { json: "disableCustomColors", js: "disableCustomColors", typ: u(undefined, true) },
        { json: "startOfWeek", js: "startOfWeek", typ: u(undefined, 3.14) },
        { json: "is12Hour", js: "is12Hour", typ: u(undefined, true) },
        { json: "addToGallery", js: "addToGallery", typ: u(undefined, true) },
        { json: "allowedTypes", js: "allowedTypes", typ: u(undefined, u(a("any"), "")) },
        { json: "gallery", js: "gallery", typ: u(undefined, true) },
        { json: "max", js: "max", typ: u(undefined, 3.14) },
        { json: "min", js: "min", typ: u(undefined, 3.14) },
        { json: "multiple", js: "multiple", typ: u(undefined, true) },
        { json: "returnSize", js: "returnSize", typ: u(undefined, "") },
        { json: "size", js: "size", typ: u(undefined, true) },
        { json: "textMediaButton", js: "textMediaButton", typ: u(undefined, "") },
        { json: "title", js: "title", typ: u(undefined, "") },
        { json: "disableCustomGradients", js: "disableCustomGradients", typ: u(undefined, true) },
        { json: "sets", js: "sets", typ: u(undefined, u(a("any"), "")) },
        { json: "subSets", js: "subSets", typ: u(undefined, u(a("any"), "")) },
        { json: "hasRichPreviews", js: "hasRichPreviews", typ: u(undefined, true) },
        { json: "noDirectEntry", js: "noDirectEntry", typ: u(undefined, true) },
        { json: "noURLSuggestion", js: "noURLSuggestion", typ: u(undefined, true) },
        { json: "opensInNewTab", js: "opensInNewTab", typ: u(undefined, true) },
        { json: "showSuggestions", js: "showSuggestions", typ: u(undefined, true) },
        { json: "textButton", js: "textButton", typ: u(undefined, "") },
        { json: "withCreateSuggestion", js: "withCreateSuggestion", typ: u(undefined, true) },
        { json: "value", js: "value", typ: u(undefined, "") },
        { json: "dragDirection", js: "dragDirection", typ: u(undefined, r("DragDirection")) },
        { json: "dragThreshold", js: "dragThreshold", typ: u(undefined, 3.14) },
        { json: "hideHTMLArrows", js: "hideHTMLArrows", typ: u(undefined, true) },
        { json: "isShiftStepEnabled", js: "isShiftStepEnabled", typ: u(undefined, true) },
        { json: "required", js: "required", typ: u(undefined, true) },
        { json: "shiftStep", js: "shiftStep", typ: u(undefined, 3.14) },
        { json: "step", js: "step", typ: u(undefined, 3.14) },
        { json: "allowReset", js: "allowReset", typ: u(undefined, true) },
        { json: "initialPosition", js: "initialPosition", typ: u(undefined, 3.14) },
        { json: "marks", js: "marks", typ: u(undefined, a(r("AttributeMark"))) },
        { json: "railColor", js: "railColor", typ: u(undefined, "") },
        { json: "resetFallbackValue", js: "resetFallbackValue", typ: u(undefined, 3.14) },
        { json: "separatorType", js: "separatorType", typ: u(undefined, r("SeparatorType")) },
        { json: "showTooltip", js: "showTooltip", typ: u(undefined, true) },
        { json: "trackColor", js: "trackColor", typ: u(undefined, "") },
        { json: "withInputField", js: "withInputField", typ: u(undefined, true) },
        { json: "allowNull", js: "allowNull", typ: u(undefined, u(true, "")) },
        { json: "stylisedUi", js: "stylisedUi", typ: u(undefined, true) },
        { json: "tabs", js: "tabs", typ: u(undefined, a(r("AttributeTab"))) },
        { json: "rows", js: "rows", typ: u(undefined, 3.14) },
        { json: "disableUnits", js: "disableUnits", typ: u(undefined, true) },
        { json: "isPressEnterToChange", js: "isPressEnterToChange", typ: u(undefined, true) },
        { json: "isResetValueOnUnitChange", js: "isResetValueOnUnitChange", typ: u(undefined, true) },
        { json: "isUnitSelectTabbable", js: "isUnitSelectTabbable", typ: u(undefined, true) },
        { json: "units", js: "units", typ: u(undefined, a(r("AttributeUnit"))) },
        { json: "toolbar", js: "toolbar", typ: u(undefined, r("AttributeToolbar")) },
    ], "any"),
    "PurpleBlockEditor": o([
        { json: "disableLoading", js: "disableLoading", typ: u(undefined, true) },
    ], "any"),
    "PurpleCondition": o([
        { json: "id", js: "id", typ: u(undefined, "") },
        { json: "operator", js: "operator", typ: r("Operator") },
        { json: "type", js: "type", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, u(true, 3.14, "")) },
    ], "any"),
    "PurpleEditor": o([
        { json: "assets", js: "assets", typ: u(undefined, a("")) },
    ], "any"),
    "PurpleTransforms": o([
        { json: "from", js: "from", typ: a(r("PurpleFrom")) },
    ], false),
    "PurpleFrom": o([
        { json: "blocks", js: "blocks", typ: u(undefined, a("")) },
        { json: "prefix", js: "prefix", typ: u(undefined, "") },
        { json: "regExp", js: "regExp", typ: u(undefined, "") },
        { json: "type", js: "type", typ: r("FromType") },
    ], false),
    "AttributeCondition": o([
        { json: "id", js: "id", typ: u(undefined, "") },
        { json: "operator", js: "operator", typ: r("Operator") },
        { json: "type", js: "type", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, u(true, 3.14, "")) },
    ], "any"),
    "AttributeMark": o([
        { json: "label", js: "label", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, 3.14) },
    ], "any"),
    "AttributeOption": o([
        { json: "label", js: "label", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, u(3.14, "")) },
        { json: "name", js: "name", typ: u(undefined, "") },
        { json: "slug", js: "slug", typ: u(undefined, "") },
        { json: "innerBlocks", js: "innerBlocks", typ: u(undefined, a(r("InnerBlockElement"))) },
    ], "any"),
    "InnerBlockElement": o([
        { json: "attributes", js: "attributes", typ: u(undefined, m("any")) },
        { json: "innerBlocks", js: "innerBlocks", typ: u(undefined, a(r("InnerBlockElement"))) },
        { json: "name", js: "name", typ: "" },
    ], "any"),
    "AttributePopulate": o([
        { json: "arguments", js: "arguments", typ: u(undefined, u(a("any"), r("PurpleArguments"))) },
        { json: "custom", js: "custom", typ: u(undefined, "") },
        { json: "function", js: "function", typ: u(undefined, "") },
        { json: "position", js: "position", typ: u(undefined, r("PositionEnum")) },
        { json: "query", js: "query", typ: u(undefined, r("Query")) },
        { json: "returnFormat", js: "returnFormat", typ: u(undefined, r("PurpleReturnFormat")) },
        { json: "type", js: "type", typ: u(undefined, r("PopulateType")) },
        { json: "fetch", js: "fetch", typ: u(undefined, true) },
    ], "any"),
    "PurpleArguments": o([
        { json: "urlSearch", js: "urlSearch", typ: u(undefined, "") },
    ], "any"),
    "PurpleReturnFormat": o([
        { json: "label", js: "label", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, "") },
    ], "any"),
    "AttributeToolbar": o([
        { json: "formats", js: "formats", typ: u(undefined, r("PurpleFormats")) },
        { json: "tags", js: "tags", typ: u(undefined, r("PurpleTags")) },
    ], "any"),
    "PurpleFormats": o([
        { json: "bold", js: "bold", typ: u(undefined, true) },
        { json: "italic", js: "italic", typ: u(undefined, true) },
        { json: "orderedList", js: "orderedList", typ: u(undefined, true) },
        { json: "strikethrough", js: "strikethrough", typ: u(undefined, true) },
        { json: "textAlign", js: "textAlign", typ: u(undefined, r("PurpleTextAlign")) },
        { json: "underline", js: "underline", typ: u(undefined, true) },
        { json: "unorderedList", js: "unorderedList", typ: u(undefined, true) },
    ], "any"),
    "PurpleTextAlign": o([
        { json: "alignments", js: "alignments", typ: u(undefined, a(r("Alignment"))) },
    ], "any"),
    "PurpleTags": o([
        { json: "headings", js: "headings", typ: u(undefined, a("any")) },
    ], "any"),
    "AttributeUnit": o([
        { json: "default", js: "default", typ: u(undefined, 3.14) },
        { json: "label", js: "label", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, "") },
    ], "any"),
    "FluffyCondition": o([
        { json: "id", js: "id", typ: u(undefined, "") },
        { json: "operator", js: "operator", typ: r("Operator") },
        { json: "type", js: "type", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, u(true, 3.14, "")) },
    ], "any"),
    "PurpleMark": o([
        { json: "label", js: "label", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, 3.14) },
    ], "any"),
    "PurpleOption": o([
        { json: "label", js: "label", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, u(3.14, "")) },
        { json: "name", js: "name", typ: u(undefined, "") },
        { json: "slug", js: "slug", typ: u(undefined, "") },
        { json: "innerBlocks", js: "innerBlocks", typ: u(undefined, a(r("InnerBlockElement"))) },
    ], "any"),
    "PurplePopulate": o([
        { json: "arguments", js: "arguments", typ: u(undefined, u(a("any"), r("FluffyArguments"))) },
        { json: "custom", js: "custom", typ: u(undefined, "") },
        { json: "function", js: "function", typ: u(undefined, "") },
        { json: "position", js: "position", typ: u(undefined, r("PositionEnum")) },
        { json: "query", js: "query", typ: u(undefined, r("Query")) },
        { json: "returnFormat", js: "returnFormat", typ: u(undefined, r("FluffyReturnFormat")) },
        { json: "type", js: "type", typ: u(undefined, r("PopulateType")) },
        { json: "fetch", js: "fetch", typ: u(undefined, true) },
    ], "any"),
    "FluffyArguments": o([
        { json: "urlSearch", js: "urlSearch", typ: u(undefined, "") },
    ], "any"),
    "FluffyReturnFormat": o([
        { json: "label", js: "label", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, "") },
    ], "any"),
    "PurpleTab": o([
        { json: "attributes", js: "attributes", typ: u(undefined, a(r("Attribute"))) },
        { json: "blockEditor", js: "blockEditor", typ: u(undefined, r("FluffyBlockEditor")) },
        { json: "conditions", js: "conditions", typ: u(undefined, a(a(r("TentacledCondition")))) },
        { json: "editor", js: "editor", typ: u(undefined, r("FluffyEditor")) },
        { json: "icon", js: "icon", typ: u(undefined, "") },
        { json: "innerBlocks", js: "innerBlocks", typ: u(undefined, "") },
        { json: "override", js: "override", typ: u(undefined, true) },
        { json: "refreshOn", js: "refreshOn", typ: u(undefined, a("")) },
        { json: "title", js: "title", typ: "" },
        { json: "transforms", js: "transforms", typ: u(undefined, r("FluffyTransforms")) },
    ], "any"),
    "FluffyBlockEditor": o([
        { json: "disableLoading", js: "disableLoading", typ: u(undefined, true) },
    ], "any"),
    "TentacledCondition": o([
        { json: "id", js: "id", typ: u(undefined, "") },
        { json: "operator", js: "operator", typ: r("Operator") },
        { json: "type", js: "type", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, u(true, 3.14, "")) },
    ], "any"),
    "FluffyEditor": o([
        { json: "assets", js: "assets", typ: u(undefined, a("")) },
    ], "any"),
    "FluffyTransforms": o([
        { json: "from", js: "from", typ: a(r("FluffyFrom")) },
    ], false),
    "FluffyFrom": o([
        { json: "blocks", js: "blocks", typ: u(undefined, a("")) },
        { json: "prefix", js: "prefix", typ: u(undefined, "") },
        { json: "regExp", js: "regExp", typ: u(undefined, "") },
        { json: "type", js: "type", typ: r("FromType") },
    ], false),
    "PurpleTextMinimized": o([
        { json: "fallback", js: "fallback", typ: u(undefined, "") },
        { json: "id", js: "id", typ: u(undefined, "") },
        { json: "prefix", js: "prefix", typ: u(undefined, "") },
        { json: "suffix", js: "suffix", typ: u(undefined, "") },
    ], "any"),
    "PurpleToolbar": o([
        { json: "formats", js: "formats", typ: u(undefined, r("FluffyFormats")) },
        { json: "tags", js: "tags", typ: u(undefined, r("FluffyTags")) },
    ], "any"),
    "FluffyFormats": o([
        { json: "bold", js: "bold", typ: u(undefined, true) },
        { json: "italic", js: "italic", typ: u(undefined, true) },
        { json: "orderedList", js: "orderedList", typ: u(undefined, true) },
        { json: "strikethrough", js: "strikethrough", typ: u(undefined, true) },
        { json: "textAlign", js: "textAlign", typ: u(undefined, r("FluffyTextAlign")) },
        { json: "underline", js: "underline", typ: u(undefined, true) },
        { json: "unorderedList", js: "unorderedList", typ: u(undefined, true) },
    ], "any"),
    "FluffyTextAlign": o([
        { json: "alignments", js: "alignments", typ: u(undefined, a(r("Alignment"))) },
    ], "any"),
    "FluffyTags": o([
        { json: "headings", js: "headings", typ: u(undefined, a("any")) },
    ], "any"),
    "PurpleUnit": o([
        { json: "default", js: "default", typ: u(undefined, 3.14) },
        { json: "label", js: "label", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, "") },
    ], "any"),
    "StickyCondition": o([
        { json: "id", js: "id", typ: u(undefined, "") },
        { json: "operator", js: "operator", typ: r("Operator") },
        { json: "type", js: "type", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, u(true, 3.14, "")) },
    ], "any"),
    "FluffyMark": o([
        { json: "label", js: "label", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, 3.14) },
    ], "any"),
    "FluffyOption": o([
        { json: "label", js: "label", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, u(3.14, "")) },
        { json: "name", js: "name", typ: u(undefined, "") },
        { json: "slug", js: "slug", typ: u(undefined, "") },
        { json: "innerBlocks", js: "innerBlocks", typ: u(undefined, a(r("InnerBlockElement"))) },
    ], "any"),
    "FluffyPopulate": o([
        { json: "arguments", js: "arguments", typ: u(undefined, u(a("any"), r("TentacledArguments"))) },
        { json: "custom", js: "custom", typ: u(undefined, "") },
        { json: "function", js: "function", typ: u(undefined, "") },
        { json: "position", js: "position", typ: u(undefined, r("PositionEnum")) },
        { json: "query", js: "query", typ: u(undefined, r("Query")) },
        { json: "returnFormat", js: "returnFormat", typ: u(undefined, r("TentacledReturnFormat")) },
        { json: "type", js: "type", typ: u(undefined, r("PopulateType")) },
        { json: "fetch", js: "fetch", typ: u(undefined, true) },
    ], "any"),
    "TentacledArguments": o([
        { json: "urlSearch", js: "urlSearch", typ: u(undefined, "") },
    ], "any"),
    "TentacledReturnFormat": o([
        { json: "label", js: "label", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, "") },
    ], "any"),
    "FluffyTab": o([
        { json: "attributes", js: "attributes", typ: u(undefined, a(r("Attribute"))) },
        { json: "blockEditor", js: "blockEditor", typ: u(undefined, r("TentacledBlockEditor")) },
        { json: "conditions", js: "conditions", typ: u(undefined, a(a(r("IndigoCondition")))) },
        { json: "editor", js: "editor", typ: u(undefined, r("TentacledEditor")) },
        { json: "icon", js: "icon", typ: u(undefined, "") },
        { json: "innerBlocks", js: "innerBlocks", typ: u(undefined, "") },
        { json: "override", js: "override", typ: u(undefined, true) },
        { json: "refreshOn", js: "refreshOn", typ: u(undefined, a("")) },
        { json: "title", js: "title", typ: "" },
        { json: "transforms", js: "transforms", typ: u(undefined, r("TentacledTransforms")) },
    ], "any"),
    "TentacledBlockEditor": o([
        { json: "disableLoading", js: "disableLoading", typ: u(undefined, true) },
    ], "any"),
    "IndigoCondition": o([
        { json: "id", js: "id", typ: u(undefined, "") },
        { json: "operator", js: "operator", typ: r("Operator") },
        { json: "type", js: "type", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, u(true, 3.14, "")) },
    ], "any"),
    "TentacledEditor": o([
        { json: "assets", js: "assets", typ: u(undefined, a("")) },
    ], "any"),
    "TentacledTransforms": o([
        { json: "from", js: "from", typ: a(r("TentacledFrom")) },
    ], false),
    "TentacledFrom": o([
        { json: "blocks", js: "blocks", typ: u(undefined, a("")) },
        { json: "prefix", js: "prefix", typ: u(undefined, "") },
        { json: "regExp", js: "regExp", typ: u(undefined, "") },
        { json: "type", js: "type", typ: r("FromType") },
    ], false),
    "FluffyTextMinimized": o([
        { json: "fallback", js: "fallback", typ: u(undefined, "") },
        { json: "id", js: "id", typ: u(undefined, "") },
        { json: "prefix", js: "prefix", typ: u(undefined, "") },
        { json: "suffix", js: "suffix", typ: u(undefined, "") },
    ], "any"),
    "FluffyToolbar": o([
        { json: "formats", js: "formats", typ: u(undefined, r("TentacledFormats")) },
        { json: "tags", js: "tags", typ: u(undefined, r("TentacledTags")) },
    ], "any"),
    "TentacledFormats": o([
        { json: "bold", js: "bold", typ: u(undefined, true) },
        { json: "italic", js: "italic", typ: u(undefined, true) },
        { json: "orderedList", js: "orderedList", typ: u(undefined, true) },
        { json: "strikethrough", js: "strikethrough", typ: u(undefined, true) },
        { json: "textAlign", js: "textAlign", typ: u(undefined, r("TentacledTextAlign")) },
        { json: "underline", js: "underline", typ: u(undefined, true) },
        { json: "unorderedList", js: "unorderedList", typ: u(undefined, true) },
    ], "any"),
    "TentacledTextAlign": o([
        { json: "alignments", js: "alignments", typ: u(undefined, a(r("Alignment"))) },
    ], "any"),
    "TentacledTags": o([
        { json: "headings", js: "headings", typ: u(undefined, a("any")) },
    ], "any"),
    "FluffyUnit": o([
        { json: "default", js: "default", typ: u(undefined, 3.14) },
        { json: "label", js: "label", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, "") },
    ], "any"),
    "BlockstudioBlockEditor": o([
        { json: "disableLoading", js: "disableLoading", typ: u(undefined, true) },
    ], "any"),
    "BlockstudioCondition": o([
        { json: "id", js: "id", typ: u(undefined, "") },
        { json: "operator", js: "operator", typ: r("Operator") },
        { json: "type", js: "type", typ: u(undefined, "") },
        { json: "value", js: "value", typ: u(undefined, u(true, 3.14, "")) },
    ], "any"),
    "BlockstudioEditor": o([
        { json: "assets", js: "assets", typ: u(undefined, a("")) },
    ], "any"),
    "BlockstudioTransforms": o([
        { json: "from", js: "from", typ: a(r("StickyFrom")) },
    ], false),
    "StickyFrom": o([
        { json: "blocks", js: "blocks", typ: u(undefined, a("")) },
        { json: "prefix", js: "prefix", typ: u(undefined, "") },
        { json: "regExp", js: "regExp", typ: u(undefined, "") },
        { json: "type", js: "type", typ: r("FromType") },
    ], false),
    "Example": o([
        { json: "attributes", js: "attributes", typ: u(undefined, m("any")) },
        { json: "innerBlocks", js: "innerBlocks", typ: u(undefined, a("any")) },
        { json: "viewportWidth", js: "viewportWidth", typ: u(undefined, 3.14) },
    ], "any"),
    "Selectors": o([
        { json: "border", js: "border", typ: u(undefined, u(r("BorderObject"), "")) },
        { json: "color", js: "color", typ: u(undefined, u(r("ColorColor"), "")) },
        { json: "dimensions", js: "dimensions", typ: u(undefined, u(r("DimensionsDimensions"), "")) },
        { json: "root", js: "root", typ: u(undefined, "") },
        { json: "spacing", js: "spacing", typ: u(undefined, u(r("SpacingSpacing"), "")) },
        { json: "typography", js: "typography", typ: u(undefined, u(r("TypographyTypography"), "")) },
    ], "any"),
    "BorderObject": o([
        { json: "color", js: "color", typ: u(undefined, "") },
        { json: "radius", js: "radius", typ: u(undefined, "") },
        { json: "root", js: "root", typ: u(undefined, "") },
        { json: "style", js: "style", typ: u(undefined, "") },
        { json: "width", js: "width", typ: u(undefined, "") },
    ], "any"),
    "ColorColor": o([
        { json: "background", js: "background", typ: u(undefined, "") },
        { json: "root", js: "root", typ: u(undefined, "") },
        { json: "text", js: "text", typ: u(undefined, "") },
    ], "any"),
    "DimensionsDimensions": o([
        { json: "aspectRatio", js: "aspectRatio", typ: u(undefined, "") },
        { json: "minHeight", js: "minHeight", typ: u(undefined, "") },
        { json: "root", js: "root", typ: u(undefined, "") },
    ], "any"),
    "SpacingSpacing": o([
        { json: "blockGap", js: "blockGap", typ: u(undefined, "") },
        { json: "margin", js: "margin", typ: u(undefined, "") },
        { json: "padding", js: "padding", typ: u(undefined, "") },
        { json: "root", js: "root", typ: u(undefined, "") },
    ], "any"),
    "TypographyTypography": o([
        { json: "fontFamily", js: "fontFamily", typ: u(undefined, "") },
        { json: "fontSize", js: "fontSize", typ: u(undefined, "") },
        { json: "fontStyle", js: "fontStyle", typ: u(undefined, "") },
        { json: "fontWeight", js: "fontWeight", typ: u(undefined, "") },
        { json: "letterSpacing", js: "letterSpacing", typ: u(undefined, "") },
        { json: "lineHeight", js: "lineHeight", typ: u(undefined, "") },
        { json: "root", js: "root", typ: u(undefined, "") },
        { json: "textDecoration", js: "textDecoration", typ: u(undefined, "") },
        { json: "textTransform", js: "textTransform", typ: u(undefined, "") },
    ], "any"),
    "Style": o([
        { json: "isDefault", js: "isDefault", typ: u(undefined, true) },
        { json: "label", js: "label", typ: "" },
        { json: "name", js: "name", typ: "" },
    ], false),
    "Supports": o([
        { json: "align", js: "align", typ: u(undefined, u(a(r("AlignElement")), true)) },
        { json: "alignWide", js: "alignWide", typ: u(undefined, true) },
        { json: "anchor", js: "anchor", typ: u(undefined, true) },
        { json: "ariaLabel", js: "ariaLabel", typ: u(undefined, true) },
        { json: "background", js: "background", typ: u(undefined, r("Background")) },
        { json: "className", js: "className", typ: u(undefined, true) },
        { json: "color", js: "color", typ: u(undefined, r("SupportsColor")) },
        { json: "customClassName", js: "customClassName", typ: u(undefined, true) },
        { json: "dimensions", js: "dimensions", typ: u(undefined, r("SupportsDimensions")) },
        { json: "filter", js: "filter", typ: u(undefined, r("Filter")) },
        { json: "html", js: "html", typ: u(undefined, true) },
        { json: "inserter", js: "inserter", typ: u(undefined, true) },
        { json: "interactivity", js: "interactivity", typ: u(undefined, u(true, r("InteractivityObject"))) },
        { json: "layout", js: "layout", typ: u(undefined, u(true, r("LayoutObject"))) },
        { json: "lock", js: "lock", typ: u(undefined, true) },
        { json: "multiple", js: "multiple", typ: u(undefined, true) },
        { json: "position", js: "position", typ: u(undefined, r("PositionObject")) },
        { json: "renaming", js: "renaming", typ: u(undefined, true) },
        { json: "reusable", js: "reusable", typ: u(undefined, true) },
        { json: "shadow", js: "shadow", typ: u(undefined, u(true, m("any"))) },
        { json: "spacing", js: "spacing", typ: u(undefined, r("SupportsSpacing")) },
        { json: "splitting", js: "splitting", typ: u(undefined, true) },
        { json: "typography", js: "typography", typ: u(undefined, r("SupportsTypography")) },
    ], "any"),
    "Background": o([
        { json: "backgroundImage", js: "backgroundImage", typ: u(undefined, true) },
        { json: "backgroundSize", js: "backgroundSize", typ: u(undefined, true) },
    ], "any"),
    "SupportsColor": o([
        { json: "background", js: "background", typ: u(undefined, true) },
        { json: "button", js: "button", typ: u(undefined, true) },
        { json: "enableContrastChecker", js: "enableContrastChecker", typ: u(undefined, true) },
        { json: "gradients", js: "gradients", typ: u(undefined, true) },
        { json: "heading", js: "heading", typ: u(undefined, true) },
        { json: "link", js: "link", typ: u(undefined, true) },
        { json: "text", js: "text", typ: u(undefined, true) },
    ], "any"),
    "SupportsDimensions": o([
        { json: "aspectRatio", js: "aspectRatio", typ: u(undefined, true) },
        { json: "minHeight", js: "minHeight", typ: u(undefined, true) },
    ], "any"),
    "Filter": o([
        { json: "duotone", js: "duotone", typ: u(undefined, true) },
    ], "any"),
    "InteractivityObject": o([
        { json: "clientNavigation", js: "clientNavigation", typ: u(undefined, true) },
        { json: "interactive", js: "interactive", typ: u(undefined, true) },
    ], "any"),
    "LayoutObject": o([
        { json: "allowCustomContentAndWideSize", js: "allowCustomContentAndWideSize", typ: u(undefined, true) },
        { json: "allowEditing", js: "allowEditing", typ: u(undefined, true) },
        { json: "allowInheriting", js: "allowInheriting", typ: u(undefined, true) },
        { json: "allowJustification", js: "allowJustification", typ: u(undefined, true) },
        { json: "allowOrientation", js: "allowOrientation", typ: u(undefined, true) },
        { json: "allowSizingOnChildren", js: "allowSizingOnChildren", typ: u(undefined, true) },
        { json: "allowSwitching", js: "allowSwitching", typ: u(undefined, true) },
        { json: "allowVerticalAlignment", js: "allowVerticalAlignment", typ: u(undefined, true) },
        { json: "default", js: "default", typ: u(undefined, r("DefaultObject")) },
    ], "any"),
    "DefaultObject": o([
        { json: "columnCount", js: "columnCount", typ: u(undefined, 3.14) },
        { json: "contentSize", js: "contentSize", typ: u(undefined, "") },
        { json: "flexWrap", js: "flexWrap", typ: u(undefined, r("FlexWrap")) },
        { json: "justifyContent", js: "justifyContent", typ: u(undefined, r("JustifyContent")) },
        { json: "minimumColumnWidth", js: "minimumColumnWidth", typ: u(undefined, "") },
        { json: "orientation", js: "orientation", typ: u(undefined, r("Orientation")) },
        { json: "type", js: "type", typ: u(undefined, r("DefaultType")) },
        { json: "verticalAlignment", js: "verticalAlignment", typ: u(undefined, r("VerticalAlignment")) },
        { json: "wideSize", js: "wideSize", typ: u(undefined, "") },
    ], "any"),
    "PositionObject": o([
        { json: "sticky", js: "sticky", typ: u(undefined, true) },
    ], "any"),
    "SupportsSpacing": o([
        { json: "margin", js: "margin", typ: u(undefined, u(a(r("MarginElement")), true)) },
        { json: "padding", js: "padding", typ: u(undefined, u(a(r("MarginElement")), true)) },
    ], "any"),
    "SupportsTypography": o([
        { json: "fontSize", js: "fontSize", typ: u(undefined, true) },
        { json: "lineHeight", js: "lineHeight", typ: u(undefined, true) },
        { json: "textAlign", js: "textAlign", typ: u(undefined, u(a(r("TextAlignElement")), true)) },
    ], "any"),
    "Variation": o([
        { json: "attributes", js: "attributes", typ: u(undefined, m("any")) },
        { json: "category", js: "category", typ: u(undefined, "") },
        { json: "description", js: "description", typ: u(undefined, "") },
        { json: "example", js: "example", typ: u(undefined, m("any")) },
        { json: "icon", js: "icon", typ: u(undefined, "") },
        { json: "innerBlocks", js: "innerBlocks", typ: u(undefined, a(a("any"))) },
        { json: "isActive", js: "isActive", typ: u(undefined, a("")) },
        { json: "isDefault", js: "isDefault", typ: u(undefined, true) },
        { json: "keywords", js: "keywords", typ: u(undefined, a("")) },
        { json: "name", js: "name", typ: "" },
        { json: "scope", js: "scope", typ: u(undefined, a(r("Scope"))) },
        { json: "title", js: "title", typ: "" },
    ], false),
    "Operator": [
        "==",
        "<",
        "includes",
        ">=",
        "!=",
        "empty",
        "!includes",
        "!empty",
        "<=",
        ">",
    ],
    "FromType": [
        "block",
        "enter",
        "prefix",
    ],
    "DragDirection": [
        "e",
        "n",
        "s",
        "w",
    ],
    "Language": [
        "css",
        "html",
        "json",
        "javascript",
        "twig",
    ],
    "PositionEnum": [
        "after",
        "before",
    ],
    "Query": [
        "posts",
        "terms",
        "users",
    ],
    "PopulateType": [
        "custom",
        "fetch",
        "function",
        "query",
    ],
    "ReturnFormatEnum": [
        "both",
        "element",
        "id",
        "label",
        "object",
        "url",
        "value",
    ],
    "SeparatorType": [
        "fullWidth",
        "none",
        "topFullWidth",
    ],
    "Alignment": [
        "center",
        "justify",
        "left",
        "right",
    ],
    "AttributeType": [
        "attributes",
        "checkbox",
        "classes",
        "code",
        "color",
        "date",
        "datetime",
        "files",
        "gradient",
        "icon",
        "link",
        "message",
        "number",
        "radio",
        "range",
        "richtext",
        "select",
        "tabs",
        "text",
        "textarea",
        "toggle",
        "unit",
        "wysiwyg",
    ],
    "PurpleType": [
        "attributes",
        "checkbox",
        "classes",
        "code",
        "color",
        "date",
        "datetime",
        "files",
        "gradient",
        "icon",
        "link",
        "message",
        "number",
        "radio",
        "range",
        "repeater",
        "richtext",
        "select",
        "tabs",
        "text",
        "textarea",
        "toggle",
        "unit",
        "wysiwyg",
    ],
    "FluffyType": [
        "attributes",
        "checkbox",
        "classes",
        "code",
        "color",
        "date",
        "datetime",
        "files",
        "gradient",
        "group",
        "icon",
        "link",
        "message",
        "number",
        "radio",
        "range",
        "repeater",
        "richtext",
        "select",
        "tabs",
        "text",
        "textarea",
        "toggle",
        "unit",
        "wysiwyg",
    ],
    "AlignElement": [
        "center",
        "full",
        "left",
        "right",
        "wide",
    ],
    "FlexWrap": [
        "nowrap",
        "wrap",
    ],
    "JustifyContent": [
        "center",
        "left",
        "right",
        "space-between",
        "stretch",
    ],
    "Orientation": [
        "horizontal",
        "vertical",
    ],
    "DefaultType": [
        "constrained",
        "flex",
        "grid",
    ],
    "VerticalAlignment": [
        "bottom",
        "center",
        "space-between",
        "stretch",
        "top",
    ],
    "MarginElement": [
        "bottom",
        "horizontal",
        "left",
        "right",
        "top",
        "vertical",
    ],
    "TextAlignElement": [
        "center",
        "left",
        "right",
    ],
    "Scope": [
        "block",
        "inserter",
        "transform",
    ],
};
