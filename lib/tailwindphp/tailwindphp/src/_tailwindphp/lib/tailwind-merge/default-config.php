<?php

declare (strict_types=1);
/**
 * Port of: https://github.com/dcastil/tailwind-merge/blob/main/src/lib/default-config.ts
 *
 * Default configuration for Tailwind CSS v4.x class merging.
 *
 * @port-deviation:types Uses PHP arrays instead of TypeScript types
 * @port-deviation:callables Uses Closure/callable for validators
 */
namespace BlockstudioVendor\TailwindPHP\Lib\TailwindMerge;

require_once __DIR__ . '/validators.php';
class DefaultConfig
{
    /**
     * Get the default configuration for Tailwind Merge.
     *
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        // Theme getters
        $fromTheme = fn(string $key) => self::createThemeGetter($key);
        $themeColor = $fromTheme('color');
        $themeFont = $fromTheme('font');
        $themeText = $fromTheme('text');
        $themeFontWeight = $fromTheme('font-weight');
        $themeTracking = $fromTheme('tracking');
        $themeLeading = $fromTheme('leading');
        $themeBreakpoint = $fromTheme('breakpoint');
        $themeContainer = $fromTheme('container');
        $themeSpacing = $fromTheme('spacing');
        $themeRadius = $fromTheme('radius');
        $themeShadow = $fromTheme('shadow');
        $themeInsetShadow = $fromTheme('inset-shadow');
        $themeTextShadow = $fromTheme('text-shadow');
        $themeDropShadow = $fromTheme('drop-shadow');
        $themeBlur = $fromTheme('blur');
        $themePerspective = $fromTheme('perspective');
        $themeAspect = $fromTheme('aspect');
        $themeEase = $fromTheme('ease');
        $themeAnimate = $fromTheme('animate');
        // Validators
        $isAny = [Validators::class, 'isAny'];
        $isAnyNonArbitrary = [Validators::class, 'isAnyNonArbitrary'];
        $isFraction = [Validators::class, 'isFraction'];
        $isNumber = [Validators::class, 'isNumber'];
        $isInteger = [Validators::class, 'isInteger'];
        $isPercent = [Validators::class, 'isPercent'];
        $isTshirtSize = [Validators::class, 'isTshirtSize'];
        $isArbitraryValue = [Validators::class, 'isArbitraryValue'];
        $isArbitraryVariable = [Validators::class, 'isArbitraryVariable'];
        $isArbitraryLength = [Validators::class, 'isArbitraryLength'];
        $isArbitraryNumber = [Validators::class, 'isArbitraryNumber'];
        $isArbitraryPosition = [Validators::class, 'isArbitraryPosition'];
        $isArbitrarySize = [Validators::class, 'isArbitrarySize'];
        $isArbitraryImage = [Validators::class, 'isArbitraryImage'];
        $isArbitraryShadow = [Validators::class, 'isArbitraryShadow'];
        $isArbitraryVariableLength = [Validators::class, 'isArbitraryVariableLength'];
        $isArbitraryVariableFamilyName = [Validators::class, 'isArbitraryVariableFamilyName'];
        $isArbitraryVariablePosition = [Validators::class, 'isArbitraryVariablePosition'];
        $isArbitraryVariableSize = [Validators::class, 'isArbitraryVariableSize'];
        $isArbitraryVariableImage = [Validators::class, 'isArbitraryVariableImage'];
        $isArbitraryVariableShadow = [Validators::class, 'isArbitraryVariableShadow'];
        // Scale helpers
        $scaleBreak = fn() => ['auto', 'avoid', 'all', 'avoid-page', 'page', 'left', 'right', 'column'];
        $scalePosition = fn() => ['center', 'top', 'bottom', 'left', 'right', 'top-left', 'left-top', 'top-right', 'right-top', 'bottom-right', 'right-bottom', 'bottom-left', 'left-bottom'];
        $scalePositionWithArbitrary = fn() => [...$scalePosition(), $isArbitraryVariable, $isArbitraryValue];
        $scaleOverflow = fn() => ['auto', 'hidden', 'clip', 'visible', 'scroll'];
        $scaleOverscroll = fn() => ['auto', 'contain', 'none'];
        $scaleUnambiguousSpacing = fn() => [$isArbitraryVariable, $isArbitraryValue, $themeSpacing];
        $scaleInset = fn() => [$isFraction, 'full', 'auto', ...$scaleUnambiguousSpacing()];
        $scaleGridTemplateColsRows = fn() => [$isInteger, 'none', 'subgrid', $isArbitraryVariable, $isArbitraryValue];
        $scaleGridColRowStartAndEnd = fn() => ['auto', ['span' => ['full', $isInteger, $isArbitraryVariable, $isArbitraryValue]], $isInteger, $isArbitraryVariable, $isArbitraryValue];
        $scaleGridColRowStartOrEnd = fn() => [$isInteger, 'auto', $isArbitraryVariable, $isArbitraryValue];
        $scaleGridAutoColsRows = fn() => ['auto', 'min', 'max', 'fr', $isArbitraryVariable, $isArbitraryValue];
        $scaleAlignPrimaryAxis = fn() => ['start', 'end', 'center', 'between', 'around', 'evenly', 'stretch', 'baseline', 'center-safe', 'end-safe'];
        $scaleAlignSecondaryAxis = fn() => ['start', 'end', 'center', 'stretch', 'center-safe', 'end-safe'];
        $scaleMargin = fn() => ['auto', ...$scaleUnambiguousSpacing()];
        $scaleSizing = fn() => [$isFraction, 'auto', 'full', 'dvw', 'dvh', 'lvw', 'lvh', 'svw', 'svh', 'min', 'max', 'fit', ...$scaleUnambiguousSpacing()];
        $scaleColor = fn() => [$themeColor, $isArbitraryVariable, $isArbitraryValue];
        $scaleBgPosition = fn() => [...$scalePosition(), $isArbitraryVariablePosition, $isArbitraryPosition, ['position' => [$isArbitraryVariable, $isArbitraryValue]]];
        $scaleBgRepeat = fn() => ['no-repeat', ['repeat' => ['', 'x', 'y', 'space', 'round']]];
        $scaleBgSize = fn() => ['auto', 'cover', 'contain', $isArbitraryVariableSize, $isArbitrarySize, ['size' => [$isArbitraryVariable, $isArbitraryValue]]];
        $scaleGradientStopPosition = fn() => [$isPercent, $isArbitraryVariableLength, $isArbitraryLength];
        $scaleRadius = fn() => ['', 'none', 'full', $themeRadius, $isArbitraryVariable, $isArbitraryValue];
        $scaleBorderWidth = fn() => ['', $isNumber, $isArbitraryVariableLength, $isArbitraryLength];
        $scaleLineStyle = fn() => ['solid', 'dashed', 'dotted', 'double'];
        $scaleBlendMode = fn() => ['normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'color-burn', 'hard-light', 'soft-light', 'difference', 'exclusion', 'hue', 'saturation', 'color', 'luminosity'];
        $scaleMaskImagePosition = fn() => [$isNumber, $isPercent, $isArbitraryVariablePosition, $isArbitraryPosition];
        $scaleBlur = fn() => ['', 'none', $themeBlur, $isArbitraryVariable, $isArbitraryValue];
        $scaleRotate = fn() => ['none', $isNumber, $isArbitraryVariable, $isArbitraryValue];
        $scaleScale = fn() => ['none', $isNumber, $isArbitraryVariable, $isArbitraryValue];
        $scaleSkew = fn() => [$isNumber, $isArbitraryVariable, $isArbitraryValue];
        $scaleTranslate = fn() => [$isFraction, 'full', ...$scaleUnambiguousSpacing()];
        return ['cacheSize' => 500, 'prefix' => null, 'experimentalParseClassName' => null, 'theme' => ['animate' => ['spin', 'ping', 'pulse', 'bounce'], 'aspect' => ['video'], 'blur' => [$isTshirtSize], 'breakpoint' => [$isTshirtSize], 'color' => [$isAny], 'container' => [$isTshirtSize], 'drop-shadow' => [$isTshirtSize], 'ease' => ['in', 'out', 'in-out'], 'font' => [$isAnyNonArbitrary], 'font-weight' => ['thin', 'extralight', 'light', 'normal', 'medium', 'semibold', 'bold', 'extrabold', 'black'], 'inset-shadow' => [$isTshirtSize], 'leading' => ['none', 'tight', 'snug', 'normal', 'relaxed', 'loose'], 'perspective' => ['dramatic', 'near', 'normal', 'midrange', 'distant', 'none'], 'radius' => [$isTshirtSize], 'shadow' => [$isTshirtSize], 'spacing' => ['px', $isNumber], 'text' => [$isTshirtSize], 'text-shadow' => [$isTshirtSize], 'tracking' => ['tighter', 'tight', 'normal', 'wide', 'wider', 'widest']], 'classGroups' => [
            // Layout
            'aspect' => [['aspect' => ['auto', 'square', $isFraction, $isArbitraryValue, $isArbitraryVariable, $themeAspect]]],
            'container' => ['container'],
            'columns' => [['columns' => [$isNumber, $isArbitraryValue, $isArbitraryVariable, $themeContainer]]],
            'break-after' => [['break-after' => $scaleBreak()]],
            'break-before' => [['break-before' => $scaleBreak()]],
            'break-inside' => [['break-inside' => ['auto', 'avoid', 'avoid-page', 'avoid-column']]],
            'box-decoration' => [['box-decoration' => ['slice', 'clone']]],
            'box' => [['box' => ['border', 'content']]],
            'display' => ['block', 'inline-block', 'inline', 'flex', 'inline-flex', 'table', 'inline-table', 'table-caption', 'table-cell', 'table-column', 'table-column-group', 'table-footer-group', 'table-header-group', 'table-row-group', 'table-row', 'flow-root', 'grid', 'inline-grid', 'contents', 'list-item', 'hidden'],
            'sr' => ['sr-only', 'not-sr-only'],
            'float' => [['float' => ['right', 'left', 'none', 'start', 'end']]],
            'clear' => [['clear' => ['left', 'right', 'both', 'none', 'start', 'end']]],
            'isolation' => ['isolate', 'isolation-auto'],
            'object-fit' => [['object' => ['contain', 'cover', 'fill', 'none', 'scale-down']]],
            'object-position' => [['object' => $scalePositionWithArbitrary()]],
            'overflow' => [['overflow' => $scaleOverflow()]],
            'overflow-x' => [['overflow-x' => $scaleOverflow()]],
            'overflow-y' => [['overflow-y' => $scaleOverflow()]],
            'overscroll' => [['overscroll' => $scaleOverscroll()]],
            'overscroll-x' => [['overscroll-x' => $scaleOverscroll()]],
            'overscroll-y' => [['overscroll-y' => $scaleOverscroll()]],
            'position' => ['static', 'fixed', 'absolute', 'relative', 'sticky'],
            'inset' => [['inset' => $scaleInset()]],
            'inset-x' => [['inset-x' => $scaleInset()]],
            'inset-y' => [['inset-y' => $scaleInset()]],
            'start' => [['start' => $scaleInset()]],
            'end' => [['end' => $scaleInset()]],
            'top' => [['top' => $scaleInset()]],
            'right' => [['right' => $scaleInset()]],
            'bottom' => [['bottom' => $scaleInset()]],
            'left' => [['left' => $scaleInset()]],
            'visibility' => ['visible', 'invisible', 'collapse'],
            'z' => [['z' => [$isInteger, 'auto', $isArbitraryVariable, $isArbitraryValue]]],
            // Flexbox and Grid
            'basis' => [['basis' => [$isFraction, 'full', 'auto', $themeContainer, ...$scaleUnambiguousSpacing()]]],
            'flex-direction' => [['flex' => ['row', 'row-reverse', 'col', 'col-reverse']]],
            'flex-wrap' => [['flex' => ['nowrap', 'wrap', 'wrap-reverse']]],
            'flex' => [['flex' => [$isNumber, $isFraction, 'auto', 'initial', 'none', $isArbitraryValue]]],
            'grow' => [['grow' => ['', $isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'shrink' => [['shrink' => ['', $isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'order' => [['order' => [$isInteger, 'first', 'last', 'none', $isArbitraryVariable, $isArbitraryValue]]],
            'grid-cols' => [['grid-cols' => $scaleGridTemplateColsRows()]],
            'col-start-end' => [['col' => $scaleGridColRowStartAndEnd()]],
            'col-start' => [['col-start' => $scaleGridColRowStartOrEnd()]],
            'col-end' => [['col-end' => $scaleGridColRowStartOrEnd()]],
            'grid-rows' => [['grid-rows' => $scaleGridTemplateColsRows()]],
            'row-start-end' => [['row' => $scaleGridColRowStartAndEnd()]],
            'row-start' => [['row-start' => $scaleGridColRowStartOrEnd()]],
            'row-end' => [['row-end' => $scaleGridColRowStartOrEnd()]],
            'grid-flow' => [['grid-flow' => ['row', 'col', 'dense', 'row-dense', 'col-dense']]],
            'auto-cols' => [['auto-cols' => $scaleGridAutoColsRows()]],
            'auto-rows' => [['auto-rows' => $scaleGridAutoColsRows()]],
            'gap' => [['gap' => $scaleUnambiguousSpacing()]],
            'gap-x' => [['gap-x' => $scaleUnambiguousSpacing()]],
            'gap-y' => [['gap-y' => $scaleUnambiguousSpacing()]],
            'justify-content' => [['justify' => [...$scaleAlignPrimaryAxis(), 'normal']]],
            'justify-items' => [['justify-items' => [...$scaleAlignSecondaryAxis(), 'normal']]],
            'justify-self' => [['justify-self' => ['auto', ...$scaleAlignSecondaryAxis()]]],
            'align-content' => [['content' => ['normal', ...$scaleAlignPrimaryAxis()]]],
            'align-items' => [['items' => [...$scaleAlignSecondaryAxis(), ['baseline' => ['', 'last']]]]],
            'align-self' => [['self' => ['auto', ...$scaleAlignSecondaryAxis(), ['baseline' => ['', 'last']]]]],
            'place-content' => [['place-content' => $scaleAlignPrimaryAxis()]],
            'place-items' => [['place-items' => [...$scaleAlignSecondaryAxis(), 'baseline']]],
            'place-self' => [['place-self' => ['auto', ...$scaleAlignSecondaryAxis()]]],
            // Spacing
            'p' => [['p' => $scaleUnambiguousSpacing()]],
            'px' => [['px' => $scaleUnambiguousSpacing()]],
            'py' => [['py' => $scaleUnambiguousSpacing()]],
            'ps' => [['ps' => $scaleUnambiguousSpacing()]],
            'pe' => [['pe' => $scaleUnambiguousSpacing()]],
            'pt' => [['pt' => $scaleUnambiguousSpacing()]],
            'pr' => [['pr' => $scaleUnambiguousSpacing()]],
            'pb' => [['pb' => $scaleUnambiguousSpacing()]],
            'pl' => [['pl' => $scaleUnambiguousSpacing()]],
            'm' => [['m' => $scaleMargin()]],
            'mx' => [['mx' => $scaleMargin()]],
            'my' => [['my' => $scaleMargin()]],
            'ms' => [['ms' => $scaleMargin()]],
            'me' => [['me' => $scaleMargin()]],
            'mt' => [['mt' => $scaleMargin()]],
            'mr' => [['mr' => $scaleMargin()]],
            'mb' => [['mb' => $scaleMargin()]],
            'ml' => [['ml' => $scaleMargin()]],
            'space-x' => [['space-x' => $scaleUnambiguousSpacing()]],
            'space-x-reverse' => ['space-x-reverse'],
            'space-y' => [['space-y' => $scaleUnambiguousSpacing()]],
            'space-y-reverse' => ['space-y-reverse'],
            // Sizing
            'size' => [['size' => $scaleSizing()]],
            'w' => [['w' => [$themeContainer, 'screen', ...$scaleSizing()]]],
            'min-w' => [['min-w' => [$themeContainer, 'screen', 'none', ...$scaleSizing()]]],
            'max-w' => [['max-w' => [$themeContainer, 'screen', 'none', 'prose', ['screen' => [$themeBreakpoint]], ...$scaleSizing()]]],
            'h' => [['h' => ['screen', 'lh', ...$scaleSizing()]]],
            'min-h' => [['min-h' => ['screen', 'lh', 'none', ...$scaleSizing()]]],
            'max-h' => [['max-h' => ['screen', 'lh', ...$scaleSizing()]]],
            // Typography
            'font-size' => [['text' => ['base', $themeText, $isArbitraryVariableLength, $isArbitraryLength]]],
            'font-smoothing' => ['antialiased', 'subpixel-antialiased'],
            'font-style' => ['italic', 'not-italic'],
            'font-weight' => [['font' => [$themeFontWeight, $isArbitraryVariable, $isArbitraryNumber]]],
            'font-stretch' => [['font-stretch' => ['ultra-condensed', 'extra-condensed', 'condensed', 'semi-condensed', 'normal', 'semi-expanded', 'expanded', 'extra-expanded', 'ultra-expanded', $isPercent, $isArbitraryValue]]],
            'font-family' => [['font' => [$isArbitraryVariableFamilyName, $isArbitraryValue, $themeFont]]],
            'fvn-normal' => ['normal-nums'],
            'fvn-ordinal' => ['ordinal'],
            'fvn-slashed-zero' => ['slashed-zero'],
            'fvn-figure' => ['lining-nums', 'oldstyle-nums'],
            'fvn-spacing' => ['proportional-nums', 'tabular-nums'],
            'fvn-fraction' => ['diagonal-fractions', 'stacked-fractions'],
            'tracking' => [['tracking' => [$themeTracking, $isArbitraryVariable, $isArbitraryValue]]],
            'line-clamp' => [['line-clamp' => [$isNumber, 'none', $isArbitraryVariable, $isArbitraryNumber]]],
            'leading' => [['leading' => [$themeLeading, ...$scaleUnambiguousSpacing()]]],
            'list-image' => [['list-image' => ['none', $isArbitraryVariable, $isArbitraryValue]]],
            'list-style-position' => [['list' => ['inside', 'outside']]],
            'list-style-type' => [['list' => ['disc', 'decimal', 'none', $isArbitraryVariable, $isArbitraryValue]]],
            'text-alignment' => [['text' => ['left', 'center', 'right', 'justify', 'start', 'end']]],
            'placeholder-color' => [['placeholder' => $scaleColor()]],
            'text-color' => [['text' => $scaleColor()]],
            'text-decoration' => ['underline', 'overline', 'line-through', 'no-underline'],
            'text-decoration-style' => [['decoration' => [...$scaleLineStyle(), 'wavy']]],
            'text-decoration-thickness' => [['decoration' => [$isNumber, 'from-font', 'auto', $isArbitraryVariable, $isArbitraryLength]]],
            'text-decoration-color' => [['decoration' => $scaleColor()]],
            'underline-offset' => [['underline-offset' => [$isNumber, 'auto', $isArbitraryVariable, $isArbitraryValue]]],
            'text-transform' => ['uppercase', 'lowercase', 'capitalize', 'normal-case'],
            'text-overflow' => ['truncate', 'text-ellipsis', 'text-clip'],
            'text-wrap' => [['text' => ['wrap', 'nowrap', 'balance', 'pretty']]],
            'indent' => [['indent' => $scaleUnambiguousSpacing()]],
            'vertical-align' => [['align' => ['baseline', 'top', 'middle', 'bottom', 'text-top', 'text-bottom', 'sub', 'super', $isArbitraryVariable, $isArbitraryValue]]],
            'whitespace' => [['whitespace' => ['normal', 'nowrap', 'pre', 'pre-line', 'pre-wrap', 'break-spaces']]],
            'break' => [['break' => ['normal', 'words', 'all', 'keep']]],
            'wrap' => [['wrap' => ['break-word', 'anywhere', 'normal']]],
            'hyphens' => [['hyphens' => ['none', 'manual', 'auto']]],
            'content' => [['content' => ['none', $isArbitraryVariable, $isArbitraryValue]]],
            // Backgrounds
            'bg-attachment' => [['bg' => ['fixed', 'local', 'scroll']]],
            'bg-clip' => [['bg-clip' => ['border', 'padding', 'content', 'text']]],
            'bg-origin' => [['bg-origin' => ['border', 'padding', 'content']]],
            'bg-position' => [['bg' => $scaleBgPosition()]],
            'bg-repeat' => [['bg' => $scaleBgRepeat()]],
            'bg-size' => [['bg' => $scaleBgSize()]],
            'bg-image' => [['bg' => ['none', ['linear' => [['to' => ['t', 'tr', 'r', 'br', 'b', 'bl', 'l', 'tl']], $isInteger, $isArbitraryVariable, $isArbitraryValue], 'radial' => ['', $isArbitraryVariable, $isArbitraryValue], 'conic' => [$isInteger, $isArbitraryVariable, $isArbitraryValue]], $isArbitraryVariableImage, $isArbitraryImage]]],
            'bg-color' => [['bg' => $scaleColor()]],
            'gradient-from-pos' => [['from' => $scaleGradientStopPosition()]],
            'gradient-via-pos' => [['via' => $scaleGradientStopPosition()]],
            'gradient-to-pos' => [['to' => $scaleGradientStopPosition()]],
            'gradient-from' => [['from' => $scaleColor()]],
            'gradient-via' => [['via' => $scaleColor()]],
            'gradient-to' => [['to' => $scaleColor()]],
            // Borders
            'rounded' => [['rounded' => $scaleRadius()]],
            'rounded-s' => [['rounded-s' => $scaleRadius()]],
            'rounded-e' => [['rounded-e' => $scaleRadius()]],
            'rounded-t' => [['rounded-t' => $scaleRadius()]],
            'rounded-r' => [['rounded-r' => $scaleRadius()]],
            'rounded-b' => [['rounded-b' => $scaleRadius()]],
            'rounded-l' => [['rounded-l' => $scaleRadius()]],
            'rounded-ss' => [['rounded-ss' => $scaleRadius()]],
            'rounded-se' => [['rounded-se' => $scaleRadius()]],
            'rounded-ee' => [['rounded-ee' => $scaleRadius()]],
            'rounded-es' => [['rounded-es' => $scaleRadius()]],
            'rounded-tl' => [['rounded-tl' => $scaleRadius()]],
            'rounded-tr' => [['rounded-tr' => $scaleRadius()]],
            'rounded-br' => [['rounded-br' => $scaleRadius()]],
            'rounded-bl' => [['rounded-bl' => $scaleRadius()]],
            'border-w' => [['border' => $scaleBorderWidth()]],
            'border-w-x' => [['border-x' => $scaleBorderWidth()]],
            'border-w-y' => [['border-y' => $scaleBorderWidth()]],
            'border-w-s' => [['border-s' => $scaleBorderWidth()]],
            'border-w-e' => [['border-e' => $scaleBorderWidth()]],
            'border-w-t' => [['border-t' => $scaleBorderWidth()]],
            'border-w-r' => [['border-r' => $scaleBorderWidth()]],
            'border-w-b' => [['border-b' => $scaleBorderWidth()]],
            'border-w-l' => [['border-l' => $scaleBorderWidth()]],
            'divide-x' => [['divide-x' => $scaleBorderWidth()]],
            'divide-x-reverse' => ['divide-x-reverse'],
            'divide-y' => [['divide-y' => $scaleBorderWidth()]],
            'divide-y-reverse' => ['divide-y-reverse'],
            'border-style' => [['border' => [...$scaleLineStyle(), 'hidden', 'none']]],
            'divide-style' => [['divide' => [...$scaleLineStyle(), 'hidden', 'none']]],
            'border-color' => [['border' => $scaleColor()]],
            'border-color-x' => [['border-x' => $scaleColor()]],
            'border-color-y' => [['border-y' => $scaleColor()]],
            'border-color-s' => [['border-s' => $scaleColor()]],
            'border-color-e' => [['border-e' => $scaleColor()]],
            'border-color-t' => [['border-t' => $scaleColor()]],
            'border-color-r' => [['border-r' => $scaleColor()]],
            'border-color-b' => [['border-b' => $scaleColor()]],
            'border-color-l' => [['border-l' => $scaleColor()]],
            'divide-color' => [['divide' => $scaleColor()]],
            'outline-style' => [['outline' => [...$scaleLineStyle(), 'none', 'hidden']]],
            'outline-offset' => [['outline-offset' => [$isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'outline-w' => [['outline' => ['', $isNumber, $isArbitraryVariableLength, $isArbitraryLength]]],
            'outline-color' => [['outline' => $scaleColor()]],
            // Effects
            'shadow' => [['shadow' => ['', 'none', $themeShadow, $isArbitraryVariableShadow, $isArbitraryShadow]]],
            'shadow-color' => [['shadow' => $scaleColor()]],
            'inset-shadow' => [['inset-shadow' => ['none', $themeInsetShadow, $isArbitraryVariableShadow, $isArbitraryShadow]]],
            'inset-shadow-color' => [['inset-shadow' => $scaleColor()]],
            'ring-w' => [['ring' => $scaleBorderWidth()]],
            'ring-w-inset' => ['ring-inset'],
            'ring-color' => [['ring' => $scaleColor()]],
            'ring-offset-w' => [['ring-offset' => [$isNumber, $isArbitraryLength]]],
            'ring-offset-color' => [['ring-offset' => $scaleColor()]],
            'inset-ring-w' => [['inset-ring' => $scaleBorderWidth()]],
            'inset-ring-color' => [['inset-ring' => $scaleColor()]],
            'text-shadow' => [['text-shadow' => ['none', $themeTextShadow, $isArbitraryVariableShadow, $isArbitraryShadow]]],
            'text-shadow-color' => [['text-shadow' => $scaleColor()]],
            'opacity' => [['opacity' => [$isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'mix-blend' => [['mix-blend' => [...$scaleBlendMode(), 'plus-darker', 'plus-lighter']]],
            'bg-blend' => [['bg-blend' => $scaleBlendMode()]],
            // Filters
            'filter' => [['filter' => ['', 'none', $isArbitraryVariable, $isArbitraryValue]]],
            'blur' => [['blur' => $scaleBlur()]],
            'brightness' => [['brightness' => [$isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'contrast' => [['contrast' => [$isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'drop-shadow' => [['drop-shadow' => ['', 'none', $themeDropShadow, $isArbitraryVariableShadow, $isArbitraryShadow]]],
            'drop-shadow-color' => [['drop-shadow' => $scaleColor()]],
            'grayscale' => [['grayscale' => ['', $isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'hue-rotate' => [['hue-rotate' => [$isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'invert' => [['invert' => ['', $isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'saturate' => [['saturate' => [$isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'sepia' => [['sepia' => ['', $isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'backdrop-filter' => [['backdrop-filter' => ['', 'none', $isArbitraryVariable, $isArbitraryValue]]],
            'backdrop-blur' => [['backdrop-blur' => $scaleBlur()]],
            'backdrop-brightness' => [['backdrop-brightness' => [$isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'backdrop-contrast' => [['backdrop-contrast' => [$isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'backdrop-grayscale' => [['backdrop-grayscale' => ['', $isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'backdrop-hue-rotate' => [['backdrop-hue-rotate' => [$isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'backdrop-invert' => [['backdrop-invert' => ['', $isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'backdrop-opacity' => [['backdrop-opacity' => [$isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'backdrop-saturate' => [['backdrop-saturate' => [$isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'backdrop-sepia' => [['backdrop-sepia' => ['', $isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            // Tables
            'border-collapse' => [['border' => ['collapse', 'separate']]],
            'border-spacing' => [['border-spacing' => $scaleUnambiguousSpacing()]],
            'border-spacing-x' => [['border-spacing-x' => $scaleUnambiguousSpacing()]],
            'border-spacing-y' => [['border-spacing-y' => $scaleUnambiguousSpacing()]],
            'table-layout' => [['table' => ['auto', 'fixed']]],
            'caption' => [['caption' => ['top', 'bottom']]],
            // Transitions and Animation
            'transition' => [['transition' => ['', 'all', 'colors', 'opacity', 'shadow', 'transform', 'none', $isArbitraryVariable, $isArbitraryValue]]],
            'transition-behavior' => [['transition' => ['normal', 'discrete']]],
            'duration' => [['duration' => [$isNumber, 'initial', $isArbitraryVariable, $isArbitraryValue]]],
            'ease' => [['ease' => ['linear', 'initial', $themeEase, $isArbitraryVariable, $isArbitraryValue]]],
            'delay' => [['delay' => [$isNumber, $isArbitraryVariable, $isArbitraryValue]]],
            'animate' => [['animate' => ['none', $themeAnimate, $isArbitraryVariable, $isArbitraryValue]]],
            // Transforms
            'backface' => [['backface' => ['hidden', 'visible']]],
            'perspective' => [['perspective' => [$themePerspective, $isArbitraryVariable, $isArbitraryValue]]],
            'perspective-origin' => [['perspective-origin' => $scalePositionWithArbitrary()]],
            'rotate' => [['rotate' => $scaleRotate()]],
            'rotate-x' => [['rotate-x' => $scaleRotate()]],
            'rotate-y' => [['rotate-y' => $scaleRotate()]],
            'rotate-z' => [['rotate-z' => $scaleRotate()]],
            'scale' => [['scale' => $scaleScale()]],
            'scale-x' => [['scale-x' => $scaleScale()]],
            'scale-y' => [['scale-y' => $scaleScale()]],
            'scale-z' => [['scale-z' => $scaleScale()]],
            'scale-3d' => ['scale-3d'],
            'skew' => [['skew' => $scaleSkew()]],
            'skew-x' => [['skew-x' => $scaleSkew()]],
            'skew-y' => [['skew-y' => $scaleSkew()]],
            'transform' => [['transform' => [$isArbitraryVariable, $isArbitraryValue, '', 'none', 'gpu', 'cpu']]],
            'transform-origin' => [['origin' => $scalePositionWithArbitrary()]],
            'transform-style' => [['transform' => ['3d', 'flat']]],
            'translate' => [['translate' => $scaleTranslate()]],
            'translate-x' => [['translate-x' => $scaleTranslate()]],
            'translate-y' => [['translate-y' => $scaleTranslate()]],
            'translate-z' => [['translate-z' => $scaleTranslate()]],
            'translate-none' => ['translate-none'],
            // Interactivity
            'accent' => [['accent' => $scaleColor()]],
            'appearance' => [['appearance' => ['none', 'auto']]],
            'caret-color' => [['caret' => $scaleColor()]],
            'color-scheme' => [['scheme' => ['normal', 'dark', 'light', 'light-dark', 'only-dark', 'only-light']]],
            'cursor' => [['cursor' => ['auto', 'default', 'pointer', 'wait', 'text', 'move', 'help', 'not-allowed', 'none', 'context-menu', 'progress', 'cell', 'crosshair', 'vertical-text', 'alias', 'copy', 'no-drop', 'grab', 'grabbing', 'all-scroll', 'col-resize', 'row-resize', 'n-resize', 'e-resize', 's-resize', 'w-resize', 'ne-resize', 'nw-resize', 'se-resize', 'sw-resize', 'ew-resize', 'ns-resize', 'nesw-resize', 'nwse-resize', 'zoom-in', 'zoom-out', $isArbitraryVariable, $isArbitraryValue]]],
            'field-sizing' => [['field-sizing' => ['fixed', 'content']]],
            'pointer-events' => [['pointer-events' => ['auto', 'none']]],
            'resize' => [['resize' => ['none', '', 'y', 'x']]],
            'scroll-behavior' => [['scroll' => ['auto', 'smooth']]],
            'scroll-m' => [['scroll-m' => $scaleUnambiguousSpacing()]],
            'scroll-mx' => [['scroll-mx' => $scaleUnambiguousSpacing()]],
            'scroll-my' => [['scroll-my' => $scaleUnambiguousSpacing()]],
            'scroll-ms' => [['scroll-ms' => $scaleUnambiguousSpacing()]],
            'scroll-me' => [['scroll-me' => $scaleUnambiguousSpacing()]],
            'scroll-mt' => [['scroll-mt' => $scaleUnambiguousSpacing()]],
            'scroll-mr' => [['scroll-mr' => $scaleUnambiguousSpacing()]],
            'scroll-mb' => [['scroll-mb' => $scaleUnambiguousSpacing()]],
            'scroll-ml' => [['scroll-ml' => $scaleUnambiguousSpacing()]],
            'scroll-p' => [['scroll-p' => $scaleUnambiguousSpacing()]],
            'scroll-px' => [['scroll-px' => $scaleUnambiguousSpacing()]],
            'scroll-py' => [['scroll-py' => $scaleUnambiguousSpacing()]],
            'scroll-ps' => [['scroll-ps' => $scaleUnambiguousSpacing()]],
            'scroll-pe' => [['scroll-pe' => $scaleUnambiguousSpacing()]],
            'scroll-pt' => [['scroll-pt' => $scaleUnambiguousSpacing()]],
            'scroll-pr' => [['scroll-pr' => $scaleUnambiguousSpacing()]],
            'scroll-pb' => [['scroll-pb' => $scaleUnambiguousSpacing()]],
            'scroll-pl' => [['scroll-pl' => $scaleUnambiguousSpacing()]],
            'snap-align' => [['snap' => ['start', 'end', 'center', 'align-none']]],
            'snap-stop' => [['snap' => ['normal', 'always']]],
            'snap-type' => [['snap' => ['none', 'x', 'y', 'both']]],
            'snap-strictness' => [['snap' => ['mandatory', 'proximity']]],
            'touch' => [['touch' => ['auto', 'none', 'manipulation']]],
            'touch-x' => [['touch-pan' => ['x', 'left', 'right']]],
            'touch-y' => [['touch-pan' => ['y', 'up', 'down']]],
            'touch-pz' => ['touch-pinch-zoom'],
            'select' => [['select' => ['none', 'text', 'all', 'auto']]],
            'will-change' => [['will-change' => ['auto', 'scroll', 'contents', 'transform', $isArbitraryVariable, $isArbitraryValue]]],
            // SVG
            'fill' => [['fill' => ['none', ...$scaleColor()]]],
            'stroke-w' => [['stroke' => [$isNumber, $isArbitraryVariableLength, $isArbitraryLength, $isArbitraryNumber]]],
            'stroke' => [['stroke' => ['none', ...$scaleColor()]]],
            // Accessibility
            'forced-color-adjust' => [['forced-color-adjust' => ['auto', 'none']]],
        ], 'conflictingClassGroups' => ['overflow' => ['overflow-x', 'overflow-y'], 'overscroll' => ['overscroll-x', 'overscroll-y'], 'inset' => ['inset-x', 'inset-y', 'start', 'end', 'top', 'right', 'bottom', 'left'], 'inset-x' => ['right', 'left'], 'inset-y' => ['top', 'bottom'], 'flex' => ['basis', 'grow', 'shrink'], 'gap' => ['gap-x', 'gap-y'], 'p' => ['px', 'py', 'ps', 'pe', 'pt', 'pr', 'pb', 'pl'], 'px' => ['pr', 'pl'], 'py' => ['pt', 'pb'], 'm' => ['mx', 'my', 'ms', 'me', 'mt', 'mr', 'mb', 'ml'], 'mx' => ['mr', 'ml'], 'my' => ['mt', 'mb'], 'size' => ['w', 'h'], 'font-size' => ['leading'], 'fvn-normal' => ['fvn-ordinal', 'fvn-slashed-zero', 'fvn-figure', 'fvn-spacing', 'fvn-fraction'], 'fvn-ordinal' => ['fvn-normal'], 'fvn-slashed-zero' => ['fvn-normal'], 'fvn-figure' => ['fvn-normal'], 'fvn-spacing' => ['fvn-normal'], 'fvn-fraction' => ['fvn-normal'], 'line-clamp' => ['display', 'overflow'], 'rounded' => ['rounded-s', 'rounded-e', 'rounded-t', 'rounded-r', 'rounded-b', 'rounded-l', 'rounded-ss', 'rounded-se', 'rounded-ee', 'rounded-es', 'rounded-tl', 'rounded-tr', 'rounded-br', 'rounded-bl'], 'rounded-s' => ['rounded-ss', 'rounded-es'], 'rounded-e' => ['rounded-se', 'rounded-ee'], 'rounded-t' => ['rounded-tl', 'rounded-tr'], 'rounded-r' => ['rounded-tr', 'rounded-br'], 'rounded-b' => ['rounded-br', 'rounded-bl'], 'rounded-l' => ['rounded-tl', 'rounded-bl'], 'border-spacing' => ['border-spacing-x', 'border-spacing-y'], 'border-w' => ['border-w-x', 'border-w-y', 'border-w-s', 'border-w-e', 'border-w-t', 'border-w-r', 'border-w-b', 'border-w-l'], 'border-w-x' => ['border-w-r', 'border-w-l'], 'border-w-y' => ['border-w-t', 'border-w-b'], 'border-color' => ['border-color-x', 'border-color-y', 'border-color-s', 'border-color-e', 'border-color-t', 'border-color-r', 'border-color-b', 'border-color-l'], 'border-color-x' => ['border-color-r', 'border-color-l'], 'border-color-y' => ['border-color-t', 'border-color-b'], 'translate' => ['translate-x', 'translate-y', 'translate-none'], 'translate-none' => ['translate', 'translate-x', 'translate-y', 'translate-z'], 'scroll-m' => ['scroll-mx', 'scroll-my', 'scroll-ms', 'scroll-me', 'scroll-mt', 'scroll-mr', 'scroll-mb', 'scroll-ml'], 'scroll-mx' => ['scroll-mr', 'scroll-ml'], 'scroll-my' => ['scroll-mt', 'scroll-mb'], 'scroll-p' => ['scroll-px', 'scroll-py', 'scroll-ps', 'scroll-pe', 'scroll-pt', 'scroll-pr', 'scroll-pb', 'scroll-pl'], 'scroll-px' => ['scroll-pr', 'scroll-pl'], 'scroll-py' => ['scroll-pt', 'scroll-pb'], 'touch' => ['touch-x', 'touch-y', 'touch-pz'], 'touch-x' => ['touch'], 'touch-y' => ['touch'], 'touch-pz' => ['touch']], 'conflictingClassGroupModifiers' => ['font-size' => ['leading']], 'orderSensitiveModifiers' => ['*', '**', 'after', 'backdrop', 'before', 'details-content', 'file', 'first-letter', 'first-line', 'marker', 'placeholder', 'selection']];
    }
    /**
     * Create a theme getter function.
     *
     * @return array{isThemeGetter: true, __invoke: callable}
     */
    private static function createThemeGetter(string $key): array
    {
        $getter = function (array $theme) use ($key): array {
            return $theme[$key] ?? [];
        };
        return ['isThemeGetter' => \true, '__invoke' => $getter];
    }
}
