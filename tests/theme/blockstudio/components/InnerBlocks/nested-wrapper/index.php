<section useBlockProps class="<?php echo esc_attr($a['classes'] ?? ''); ?>">
    <div class="content-section__inner">
        <InnerBlocks
            tag="div"
            class="content-section__content"
            template="<?php echo esc_attr(wp_json_encode([
                ['core/heading', ['level' => 2, 'placeholder' => 'Section Heading']],
                ['core/paragraph', ['placeholder' => 'Add content…']],
            ])); ?>"
        />
    </div>
</section>
