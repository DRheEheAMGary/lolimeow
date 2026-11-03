<?php
/**
 * Gutenberg 编辑器数学公式增强
 *
 * - 独占一段的 $$...$$ 自动转 core/math 区块（非空内容）
 * - 段落中的 $...$ 自动转行内 core/math 格式（非空内容）
 * - 段落中的 $$...$$ 不渲染，保持普通文本
 * - 空内容不闭合、不转换
 * - 代码块、行内代码不处理
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('enqueue_block_editor_assets', function () {
    $rel = '/assets/js/editor-math.js';
    $abs = get_stylesheet_directory() . $rel;
    if (!is_file($abs)) {
        return;
    }
    wp_enqueue_script(
        'lolimeow-editor-math',
        get_stylesheet_directory_uri() . $rel,
        ['wp-blocks', 'wp-data', 'wp-block-editor', 'wp-dom-ready', 'wp-rich-text'],
        (string) filemtime($abs),
        true
    );
});
