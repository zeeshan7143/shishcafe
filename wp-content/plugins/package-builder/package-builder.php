<?php

/**
 * Plugin Name: Woo Package Builder (ACF Free)
 * Description: Dynamic package builder using WooCommerce + ACF Free
 * Author: Enigmatix Global
 * Version: 1.1
 */

if (!defined('ABSPATH')) exit;

class Woo_Package_Builder
{

    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('package_builder', [$this, 'render_shortcode']);

        add_action('wp_ajax_pb_add_to_cart', [$this, 'add_to_cart']);
        add_action('wp_ajax_nopriv_pb_add_to_cart', [$this, 'add_to_cart']);

        add_action('woocommerce_before_calculate_totals', [$this, 'update_cart_price']);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_selected_items'], 10, 4);
    }

    public function enqueue_assets()
    {
        wp_enqueue_style('pb-css', plugin_dir_url(__FILE__) . 'assets/css/package-builder.css', [], '1.1');
        wp_enqueue_script('pb-js', plugin_dir_url(__FILE__) . 'assets/js/package-builder.js', ['jquery'], '1.1', true);
    }

    public function render_shortcode($atts)
    {
        $atts = shortcode_atts(['id' => 0], $atts);
        $product_id = (int) $atts['id'];

        if (!$product_id) {
            return '<p>Invalid product ID</p>';
        }
        /* ===========================
            * MAIN PRODUCT VARIATIONS
        * =========================== */

        $main_product = wc_get_product($product_id);
        $main_variations = [];

        if ($main_product && $main_product->is_type('variable')) {
            foreach ($main_product->get_available_variations() as $v) {
                foreach ($v['attributes'] as $attr_key => $attr_val) {
                    if (strpos($attr_key, 'location') !== false && $attr_val) {
                        $main_variations[sanitize_title($attr_val)] = (int) $v['variation_id'];
                    }
                }
            }
        }

        /* ===========================
     * STEP 1: READ ACF FIELDS
     * =========================== */

        $acf_fields = get_fields($product_id);
        if (!$acf_fields) {
            return '<p>No ACF fields found</p>';
        }

        $categories = [];

        foreach ($acf_fields as $slug => $qty) {
            if (!is_numeric($qty) || $qty <= 0) continue;

            $term = get_term_by('slug', $slug, 'product_cat');
            if (!$term) continue;

            $categories[$slug] = [
                'slug' => $term->slug,
                'label' => $term->name,
                'qty' => (int) $qty,
            ];
        }

        if (empty($categories)) {
            return '<p>No valid categories</p>';
        }

        /* ===========================
     * DEBUG PHP (VIEW SOURCE)
     * =========================== */
        echo '<!-- PB CATEGORIES: ' . json_encode($categories) . ' -->';
        /* ===========================
 * PHASE 2: CHILD PRODUCT VARIATION PRICES
 * =========================== */

        $child_prices = [];

        foreach ($categories as $key => $cat) {

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

            foreach ($products as $p) {

                if ($p->is_type('variable')) {
                    foreach ($p->get_available_variations() as $v) {

                        // $location = $v['attributes']['attribute_location'] ?? null;
                        $location = null;

                        foreach ($v['attributes'] as $attr_key => $attr_value) {
                            if (strpos($attr_key, 'location') !== false && $attr_value) {
                                $location = sanitize_title($attr_value); // 🔥 KEY FIX
                                break;
                            }
                        }
                        if (!$location) continue;

                        $child_prices[$key][$p->get_id()][$location] =
                            (float) $v['display_price'];
                        error_log('PB VAR ATTRS: ' . print_r($v['attributes'], true));
                    }
                } else {
                    $child_prices[$key][$p->get_id()]['all'] =
                        (float) $p->get_price();
                }
            }
        }

        /* SEND TO JS */
        wp_localize_script('pb-js', 'PB_DATA', [
            'child_prices' => $child_prices,
            'main_variations' => $main_variations,
            'ajax_url' => admin_url('admin-ajax.php'),
            'cart_url' => wc_get_cart_url(),
        ]);
        /* DEBUG PHP */
        echo '<!-- PB CHILD PRICES: ' . json_encode($child_prices) . ' -->';

        ob_start();
?>

        <div id="package-builder">

            <!-- TABS -->
            <ul class="pb-tabs">
                <?php foreach ($categories as $key => $cat): ?>
                    <li data-tab="<?= esc_attr($key); ?>">
                        <?= esc_html($cat['label']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- TAB CONTENT -->
            <?php foreach ($categories as $key => $cat): ?>

                <?php
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

                <!-- DEBUG -->
                <!-- PRODUCTS <?= esc_html($key); ?>: <?= count($products); ?> -->

                <div class="pb-content"
                    id="pb-<?= esc_attr($key); ?>"
                    data-category="<?= esc_attr($key); ?>"
                    data-free="<?= esc_attr($cat['qty']); ?>">
                    <p class="pb-info">
                        <?= esc_html($cat['qty']); ?> items included.
                        Additional items can be added with extra payment.
                    </p>

                    <!-- <div class="pb-counter">0 selected (0 paid)</div> -->
                    <div class="extra-pricing-line">
                        <div class="pb-counter">
                            <span class="pb-counter-text"><?= esc_html($cat['qty']); ?> selections required</span>
                            <!-- <span class="pb-extra-tab"></span> -->

                        </div>
                        <span class="pb-extra-tab"></span>
                    </div>
                    <?php if ($products): ?>
                        <?php foreach ($products as $p): ?>
                            <div class="pb-item" data-id="<?= $p->get_id(); ?>">
                                <input type="checkbox">
                                <strong><?= esc_html($p->get_name()); ?></strong>

                                <div class="pb-desc">
                                    <?= wp_kses_post($p->get_short_description()); ?>
                                </div>

                                <div class="pb-price"></div>
                            </div>

                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No products found</p>
                    <?php endif; ?>
                    <!-- <div class="pb-summary">
                        Extra total: £<span class="pb-extra-total">0.00</span>
                    </div> -->



                </div>


            <?php endforeach; ?>
            <div class="pb-footer">

                <div class="pb-summary">
                    Extra total: £<span class="pb-extra-total">0.00</span>
                </div>

                <button type="button" id="pb-add-cart" class="pb-add-cart-btn">
                    Add Package to Cart
                </button>

            </div>
        </div>

<?php
        return ob_get_clean();
    }


    // public function add_to_cart()
    // {
    //     if (!isset($_POST['variation_id'])) {
    //         wp_send_json_error('Missing variation');
    //     }

    //     $variation_id  = (int) $_POST['variation_id'];
    //     $extra_price   = (float) $_POST['extra_price'];
    //     $selected_items = $_POST['items'] ?? [];

    //     WC()->cart->add_to_cart(
    //         wp_get_post_parent_id($variation_id), // parent product
    //         1,
    //         $variation_id,
    //         [],
    //         [
    //             'pb_extra_price' => $extra_price,
    //             'pb_items'       => $selected_items
    //         ]
    //     );

    //     wp_send_json_success();
    // }
    public function add_to_cart()
    {
        $variation_id = absint($_POST['variation_id'] ?? 0);
        $extra_price  = floatval($_POST['extra_price'] ?? 0);
        $items        = $_POST['items'] ?? [];

        if (!$variation_id) {
            wp_send_json_error('Invalid variation');
        }

        WC()->cart->add_to_cart(
            wp_get_post_parent_id($variation_id),
            1,
            $variation_id,
            [],
            [
                'pb_extra_price' => $extra_price,
                'pb_items'       => array_map('sanitize_text_field', $items)
            ]
        );

        wp_send_json_success();
    }



    public function update_cart_price($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) return;

        foreach ($cart->get_cart() as $cart_item) {

            if (isset($cart_item['pb_extra_price'])) {

                $base_price = $cart_item['data']->get_price();
                $cart_item['data']->set_price(
                    $base_price + (float) $cart_item['pb_extra_price']
                );
            }
        }
    }


    public function save_selected_items($item, $cart_key, $values, $order)
    {
        // if (!empty($values['pb_selected_items'])) {
        //     $item->add_meta_data('Selected Items', implode(', ', $values['pb_selected_items']));
        // }
        if (!empty($values['pb_items'])) {
            $item->add_meta_data('Selected Items', implode(', ', $values['pb_items']));
        }
    }
}
add_action('woocommerce_before_calculate_totals', function ($cart) {

    if (is_admin() && !defined('DOING_AJAX')) return;

    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['pb_extra_price'])) {
            $cart_item['data']->set_price(
                $cart_item['data']->get_price() + $cart_item['pb_extra_price']
            );
        }
    }
});

new Woo_Package_Builder();
