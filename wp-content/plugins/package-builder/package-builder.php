<?php

/**
 * Plugin Name: Woo Package Builder
 * Description: Package builder using WooCommerce and ACF.
 * Author: Enigmatix Global
 */

if (!defined('ABSPATH')) exit;

class Woo_Package_Builder
{

    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_shortcode('package_builder', [$this, 'shortcode']);
        add_action('wp_ajax_pb_add_to_cart', [$this, 'add_to_cart']);
        add_action('wp_ajax_nopriv_pb_add_to_cart', [$this, 'add_to_cart']);
        add_action('woocommerce_before_calculate_totals', [$this, 'update_price']);
    }

    public function assets()
    {
        wp_enqueue_style(
            'pb-css',
            plugin_dir_url(__FILE__) . 'assets/css/package-builder.css'
        );

        wp_enqueue_script(
            'pb-js',
            plugin_dir_url(__FILE__) . 'assets/js/package-builder.js',
            ['jquery'],
            null,
            true
        );

        wp_localize_script('pb-js', 'PB', [
            'ajax' => admin_url('admin-ajax.php'),
            'cart' => wc_get_cart_url(),
        ]);
    }

    public function shortcode($atts)
    {

        $atts = shortcode_atts(['id' => 0], $atts);
        $product_id = (int) $atts['id'];
        if (!$product_id) return 'Invalid product';

        // Map ACF field => Woo category slug => Label
        $categories = [
            'starter' => ['slug' => 'starter', 'label' => 'Starter'],
            'salad'   => ['slug' => 'salad',   'label' => 'Salad'],
            'sides'   => ['slug' => 'sides',   'label' => 'Sides'],
        ];

        ob_start(); ?>
        <div id="package-builder" data-product="<?= $product_id ?>">

            <ul class="pb-tabs">
                <?php foreach ($categories as $field => $cat):
                    $qty = (int) get_field($field, $product_id);
                    if ($qty > 0): ?>
                        <li data-tab="<?= esc_attr($field) ?>">
                            <?= esc_html($cat['label']) ?>
                            <span>(<?= $qty ?> Free)</span>
                        </li>
                <?php endif;
                endforeach; ?>
            </ul>

            <?php foreach ($categories as $field => $cat):
                $qty = (int) get_field($field, $product_id);
                if ($qty <= 0) continue;

                $products = wc_get_products([
                    'limit' => -1,
                    'status' => 'publish',
                    'tax_query' => [
                        'relation' => 'AND',
                        [
                            'taxonomy' => 'product_cat',
                            'field'    => 'slug',
                            'terms'    => [$cat['slug']],
                        ],
                        [
                            'taxonomy' => 'product_cat',
                            'field'    => 'slug',
                            'terms'    => ['package-deals'],
                        ],
                    ],
                ]);
            ?>
                <div class="pb-content"
                    id="pb-<?= esc_attr($field) ?>"
                    data-free="<?= $qty ?>"
                    data-field="<?= esc_attr($field) ?>">

                    <?php foreach ($products as $p): ?>
                        <div class="pb-item"
                            data-id="<?= $p->get_id() ?>"
                            data-price="<?= $p->get_price() ?>">
                            <span><?= esc_html($p->get_name()) ?></span>
                            <button class="pb-add">Add</button>
                        </div>
                    <?php endforeach; ?>

                </div>
            <?php endforeach; ?>

            <div class="pb-summary">
                Extra: £<span id="pb-extra">0</span>
                <button id="pb-cart">Add Package</button>
            </div>

        </div>
<?php
        return ob_get_clean();
    }

    public function add_to_cart()
    {
        WC()->cart->add_to_cart(
            (int) $_POST['product'],
            1,
            0,
            [],
            ['extra' => (float) $_POST['extra']]
        );
        wp_send_json_success();
    }

    public function update_price($cart)
    {
        foreach ($cart->get_cart() as $item) {
            if (isset($item['extra'])) {
                $item['data']->set_price(
                    $item['data']->get_price() + $item['extra']
                );
            }
        }
    }
}

new Woo_Package_Builder();
