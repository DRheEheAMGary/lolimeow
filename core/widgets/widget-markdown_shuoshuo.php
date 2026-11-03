<?php
/**
 * Markdown shuoshuo publisher widget.
 *
 * @package lolimeow
 */
if (!defined('ABSPATH')) { echo 'Look your sister'; exit; }

class widget_markdown_shuoshuo extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'boxmoe_widget_markdown_shuoshuo',
            'Boxmoe_Markdown说说发布',
            [
                'description' => __('在侧栏使用 Markdown 发布说说', 'boxmoe-com'),
                'classname' => 'widget-markdown-shuoshuo',
            ]
        );
    }

    public function widget($args, $instance) {
        $title = !empty($instance['title']) ? apply_filters('widget_title', $instance['title']) : '';
        echo $args['before_widget'];
        if ($title) echo $args['before_title'] . $title . $args['after_title'];
        boxmoe_render_markdown_shuoshuo_publisher(true);
        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('发布说说', 'boxmoe-com');
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">标题：</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        return ['title' => sanitize_text_field($new_instance['title'] ?? '')];
    }
}
