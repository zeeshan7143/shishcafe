<?php

/**
 * Plugin Name: Woo Package Builder (ACF Free)
 * Description: Dynamic package builder using WooCommerce + ACF Free
 * Author: Enigmatix Global
 * Author URI: https://enigmatixglobal.com/
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

        add_action('wp_ajax_pb_get_builder', [$this, 'get_builder']);
        add_action('wp_ajax_nopriv_pb_get_builder', [$this, 'get_builder']);

        add_action('woocommerce_before_calculate_totals', [$this, 'update_cart_price']);
        add_filter('woocommerce_get_item_data', [$this, 'display_selected_items'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_selected_items'], 10, 4);
    }

    public function enqueue_assets()
    {
        wp_enqueue_style('pb-css', plugin_dir_url(__FILE__) . 'assets/css/package-builder.css', [], '1.1');
        wp_enqueue_script('pb-js', plugin_dir_url(__FILE__) . 'assets/js/package-builder.js', ['jquery'], '1.1', true);

        $package_tree = [];
        $package_term = get_term_by('slug', 'package-deals', 'product_cat');
        if ($package_term && !is_wp_error($package_term)) {
            $build_tree = function ($term_id) use (&$build_tree) {
                $children = get_terms([
                    'taxonomy' => 'product_cat',
                    'hide_empty' => false,
                    'parent' => (int) $term_id,
                ]);

                $nodes = [];
                if (!is_wp_error($children)) {
                    foreach ($children as $child) {
                        $nodes[] = [
                            'name' => $child->name,
                            'slug' => $child->slug,
                            'children' => $build_tree($child->term_id),
                        ];
                    }
                }
                return $nodes;
            };

            $package_tree[] = [
                'name' => $package_term->name,
                'slug' => $package_term->slug,
                'children' => $build_tree($package_term->term_id),
            ];
        }

        wp_localize_script('pb-js', 'PB_CONFIG', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'cart_url' => wc_get_cart_url(),
            'category_tree' => ['categories' => $package_tree],
        ]);
    }

    public function render_shortcode($atts)
    {
        // DEBUG: Query and log ramzan-deals products
        error_log('=== CHECKING RAMZAN-DEALS PRODUCTS ===');
        $ramzan_args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'tax_query' => [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => 'ramzan-deals',
                ],
            ],
        ];
        $ramzan_query = new WP_Query($ramzan_args);
        error_log('Found ' . $ramzan_query->post_count . ' products with ramzan-deals category');

        $ramzan_products_data = [];
        if ($ramzan_query->have_posts()) {
            while ($ramzan_query->have_posts()) {
                $ramzan_query->the_post();
                $pid = get_the_ID();
                $title = get_the_title();
                $cats = wp_get_post_terms($pid, 'product_cat', ['fields' => 'names']);
                $tags = wp_get_post_terms($pid, 'product_tag', ['fields' => 'names']);
                error_log('  - Product: ' . $title . ' (ID: ' . $pid . ')');
                error_log('    Categories: ' . implode(', ', $cats));
                error_log('    Tags: ' . implode(', ', $tags));

                $acf_subset = null;
                if (function_exists('get_fields')) {
                    $acf_all = get_fields($pid);
                    error_log('    ACF Fields: ' . print_r($acf_all, true));
                    $acf_subset = [
                        'detail_for_2' => $acf_all['detail_for_2'] ?? '',
                        'detail_for_8' => $acf_all['detail_for_8'] ?? '',
                        'qty_persons'  => $acf_all['qty_persons'] ?? [],
                    ];
                    error_log('    ACF Subset: ' . print_r($acf_subset, true));
                } else {
                    error_log('    ACF Fields: ACF not available');
                }

                $ramzan_products_data[] = [
                    'id' => $pid,
                    'title' => $title,
                    'categories' => $cats,
                    'tags' => $tags,
                    'acf' => $acf_subset,
                ];
            }
            wp_reset_postdata();
        } else {
            error_log('NO PRODUCTS FOUND with ramzan-deals category!');
        }
        error_log('=== END RAMZAN CHECK ===');

        $packages = wc_get_products([
            'limit' => -1,
            'status' => 'publish',
            'orderby' => 'ID',
            'order' => 'ASC',
            'tax_query' => [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => ['package-deals'],
                ],
            ],
        ]);

        if ($packages) {
            $packages = array_values(array_filter($packages, function ($p) {
                $term_ids = wp_get_post_terms($p->get_id(), 'product_cat', ['fields' => 'ids']);
                if (empty($term_ids)) {
                    return false;
                }
                if (count($term_ids) !== 1) {
                    return false;
                }
                $only_term = get_term($term_ids[0], 'product_cat');
                return $only_term && $only_term->slug === 'package-deals';
            }));
        }

        if (!$packages) {
            return '<p>No packages found</p>';
        }

        ob_start();
?>
        <div id="pb-package-selector">
            <div class="pb-package-list">
                <?php foreach ($packages as $p): ?>
                    <?php
                    $package_tag_slugs = wp_get_post_terms($p->get_id(), 'product_tag', ['fields' => 'slugs']);
                    $is_ramzan_package = (!is_wp_error($package_tag_slugs) && in_array('ramzan-deals', $package_tag_slugs, true));
                    $package_price = $p->get_price();
                    $price_map = [];
                    if ($p->is_type('variable')) {
                        foreach ($p->get_available_variations() as $variation) {
                            foreach ($variation['attributes'] as $attr_key => $attr_value) {
                                if (strpos($attr_key, 'location') !== false && $attr_value) {
                                    $price_map[sanitize_title($attr_value)] = (float) $variation['display_price'];
                                    break;
                                }
                            }
                        }
                    }
                    ?>
                    <?php $requires_persons = !$is_ramzan_package; ?>
                    <label class="pb-package-option" data-requires-persons="<?= $requires_persons ? '1' : '0'; ?>"
                        <?php if (!$is_ramzan_package): ?>
                            <?php
                            // Get chicken price from ACF for this product
                            $acf_fields = function_exists('get_fields') ? get_fields($p->get_id()) : [];
                            $chicken_price = isset($acf_fields['chicken_pricing']) ? (float)$acf_fields['chicken_pricing'] : 0;
                            ?>
                            data-chicken-price="<?= esc_attr($chicken_price); ?>"
                        <?php endif; ?>
                    >
                        <input type="radio" name="pb_package" class="pb-p-select" value="<?= esc_attr($p->get_id()); ?>" data-requires-persons="<?= $requires_persons ? '1' : '0'; ?>">
                        <h2 class="pb-package-title"><?= esc_html($p->get_name()); ?></h2>
                        <?php if ($p->get_short_description()): ?>
                            <p class="pb-package-desc"><?= wp_kses_post($p->get_short_description()); ?></p>
                        <?php endif; ?>
                        <?php if (!$is_ramzan_package): ?>
                            <div class="pb-persons-slect-box">
                                <label class="pb-persons-label">Select No. of Persons to Serve</label>
                                <select class="pb-persons-select"
                                    data-package-id="<?= esc_attr($p->get_id()); ?>"
                                    data-package-price="<?= esc_attr($package_price); ?>"
                                    data-package-price-map="<?= esc_attr(wp_json_encode($price_map)); ?>"
                                    required>
                                    <option value="" disabled>Select Persons</option>
                                    <?php for ($i = 1; $i <= 25; $i++): ?>
                                        <option value="<?= esc_attr($i); ?>" <?= $i === 1 ? 'selected' : ''; ?>><?= esc_html($i); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <h4 class="pb-package-price" style="display: none;"></h4>
                        <?php else: ?>
                            <h4 class="pb-package-price">Package Price £<?= esc_html(number_format($package_price, 2)); ?></h4>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="pb-package-actions">
                <button type="button" id="pb-clear-package" class="pb-clear-btn" style="display: none;" disabled>
                    Clear Selection
                </button>
                <button type="button" id="pb-next-package" class="pb-next-btn" disabled>
                    Next
                </button>
            </div>
        </div>

        <div id="pb-builder-container"></div>
        <?php
        // Print ramzan products data to browser console (if any)
        if (!empty($ramzan_products_data)) {
            echo "<script>console.log('Ramzan products (browser): ', ";
            echo wp_json_encode($ramzan_products_data);
            echo ");</script>";
        }

        return ob_get_clean();
    }

    private function build_package_builder_payload($product_id)
    {
        $product_id = (int) $product_id;
        if (!$product_id) {
            return ['error' => 'Invalid product ID'];
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
            return ['error' => 'No ACF fields found'];
        }

        $is_ramzan = false;
        $product_tags = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'slugs']);
        error_log('Product tags: ' . print_r($product_tags, true));
        if (!is_wp_error($product_tags) && in_array('ramzan-deals', $product_tags, true)) {
            $is_ramzan = true;
            error_log('Ramzan package detected for product ID: ' . $product_id);
        }

        if ($is_ramzan) {
            return $this->build_ramzan_package($product_id, $acf_fields, $main_variations);
        }

        $categories = [];

        $get_term = function ($slug, $name) {
            $term = $slug ? get_term_by('slug', $slug, 'product_cat') : null;
            if (!$term && $name) {
                $term = get_term_by('name', $name, 'product_cat');
            }
            if (!$term && $name === 'Starters') {
                $term = get_term_by('name', 'Starter', 'product_cat');
            }
            if (!$term && $slug === 'starters') {
                $term = get_term_by('slug', 'starter', 'product_cat');
            }
            return $term;
        };

        // Desserts
        $dessert_qty = $acf_fields['dessert_qty'] ?? 0;
        $dessert_desc = $acf_fields['text_description_dessert'] ?? '';
        if (is_numeric($dessert_qty) && (int) $dessert_qty > 0) {
            $term = $get_term('desserts', 'Desserts');
            if ($term) {
                $categories[$term->slug] = [
                    'slug' => $term->slug,
                    'label' => $term->name,
                    'qty' => (int) $dessert_qty,
                    'desc' => $dessert_desc,
                ];
            }
        }

        // Starters
        $starters_qty = $acf_fields['all_qty_starters'] ?? 0;
        $starters_desc = $acf_fields['text_description_starters'] ?? '';
        if (is_numeric($starters_qty) && (int) $starters_qty > 0) {
            $term = $get_term('starters', 'Starters');
            if ($term) {
                $categories[$term->slug] = [
                    'slug' => $term->slug,
                    'label' => $term->name,
                    'qty' => (int) $starters_qty,
                    'desc' => $starters_desc,
                ];
            }
        }

        // Mains (sum any filled mains qty fields)
        $mains_fields = [
            ['qty' => 'lamb_chicken_qty_mains_mix', 'desc' => 'text_description_mains_mix'],
            ['qty' => 'chicken_qty_mains', 'desc' => 'text_description_mains_chicken'],
            ['qty' => 'lamb_qty_mains', 'desc' => 'text_description_mains_lamb'],
            ['qty' => 'vegetarian_qty_mains', 'desc' => 'text_description_mains_vegetarian'],
        ];

        $mains_qty = 0;
        $mains_descs = [];

        foreach ($mains_fields as $field) {
            $qty = $acf_fields[$field['qty']] ?? 0;
            if (is_numeric($qty) && (int) $qty > 0) {
                $mains_qty += (int) $qty;
                $desc = $acf_fields[$field['desc']] ?? '';
                if (!empty($desc)) {
                    $mains_descs[] = $desc;
                }
            }
        }

        if ($mains_qty > 0) {
            $term = $get_term('mains', 'Mains');
            if ($term) {
                $categories[$term->slug] = [
                    'slug' => $term->slug,
                    'label' => $term->name,
                    'qty' => (int) $mains_qty,
                    'desc' => implode('<br>', array_unique($mains_descs)),
                ];
            }
        }

        // Order tabs: Starters, Mains, Desserts
        $ordered = [];
        foreach (['Starters', 'Mains', 'Desserts'] as $label) {
            foreach ($categories as $k => $cat) {
                if ($cat['label'] === $label) {
                    $ordered[$k] = $cat;
                }
            }
        }
        foreach ($categories as $k => $cat) {
            if (!isset($ordered[$k])) {
                $ordered[$k] = $cat;
            }
        }
        $categories = $ordered;

        if (empty($categories)) {
            return ['error' => 'No valid categories'];
        }

        /* ===========================
         * PHASE 2: CHILD PRODUCT VARIATION PRICES
         * =========================== */

        $child_prices = [];

        foreach ($categories as $key => $cat) {

            $products = wc_get_products([
                'limit' => -1,
                'status' => 'publish',
                'tax_query' => [
                    [
                        'taxonomy' => 'product_cat',
                        'field'    => 'slug',
                        'terms'    => ['package-deals'],
                        'include_children' => true,
                    ],
                ],
            ]);

            foreach ($products as $p) {

                $terms = get_the_terms($p->get_id(), 'product_cat');
                $slugs = [];
                if ($terms && !is_wp_error($terms)) {
                    foreach ($terms as $t) {
                        $slugs[] = $t->slug;
                    }
                }

                $main_slug = $cat['slug'];
                if ($cat['label'] === 'Starters') {
                    $main_slug = 'starter';
                }

                if (!in_array('package-deals', $slugs, true)) {
                    continue;
                }
                if (!in_array($main_slug, $slugs, true)) {
                    continue;
                }

                if ($p->is_type('variable')) {
                    foreach ($p->get_available_variations() as $v) {

                        $location = null;

                        foreach ($v['attributes'] as $attr_key => $attr_value) {
                            if (strpos($attr_key, 'location') !== false && $attr_value) {
                                $location = sanitize_title($attr_value);
                                break;
                            }
                        }
                        if (!$location) continue;

                        $child_prices[$key][$p->get_id()][$location] =
                            (float) $v['display_price'];
                    }
                } else {
                    $child_prices[$key][$p->get_id()]['all'] =
                        (float) $p->get_price();
                }
            }
        }

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
                        [
                            'taxonomy' => 'product_cat',
                            'field'    => 'slug',
                            'terms'    => ['package-deals'],
                            'include_children' => true,
                        ],
                    ],
                ]);

                $group_definitions = [];
                $group_products = [];
                $products_by_slug = [];

                $main_slug = $cat['slug'];
                if ($cat['label'] === 'Starters') {
                    $main_slug = 'starter';
                }

                if ($products) {
                    foreach ($products as $p) {
                        $terms = get_the_terms($p->get_id(), 'product_cat');
                        if ($terms && !is_wp_error($terms)) {
                            $slugs = [];
                            foreach ($terms as $term) {
                                $slugs[] = $term->slug;
                            }

                            if (!in_array($main_slug, $slugs, true)) {
                                continue;
                            }

                            if (in_array('traditional', $slugs, true)) {
                                $products_by_slug['traditional'][] = $p;
                            }
                            if (in_array('ice-cream', $slugs, true)) {
                                $products_by_slug['ice-cream'][] = $p;
                            }
                            if (in_array('chicken', $slugs, true)) {
                                $products_by_slug['chicken'][] = $p;
                            }
                            if (in_array('lamb', $slugs, true)) {
                                $products_by_slug['lamb'][] = $p;
                            }
                            if (in_array('vegetarian', $slugs, true)) {
                                $products_by_slug['vegetarian'][] = $p;
                            }
                        }
                    }
                }

                $category_mode = 'subgroups';
                $combined_qty = 0;
                $category_mix = false;
                $mix_qty = 0;
                $mix_groups = [];
                $mix_desc = '';

                if ($cat['label'] === 'Desserts') {
                    $category_mode = 'combined';
                    $combined_qty = (int) ($acf_fields['dessert_qty'] ?? 0);
                    $group_definitions = [
                        'ice-cream' => ['label' => 'Ice Cream'],
                        'traditional' => ['label' => 'Traditional'],
                        'tradiotional' => ['label' => 'Tradiotional'],
                    ];
                    foreach ($group_definitions as $slug => $def) {
                        $group_products[$slug] = $products_by_slug[$slug] ?? [];
                    }
                } elseif ($cat['label'] === 'Starters') {
                    $category_mode = 'combined';
                    $combined_qty = (int) ($acf_fields['all_qty_starters'] ?? 0);
                    $group_definitions = [
                        'chicken' => ['label' => 'Chicken'],
                        'lamb' => ['label' => 'Lamb'],
                        'vegetarian' => ['label' => 'Vegetarian'],
                    ];
                    foreach ($group_definitions as $slug => $def) {
                        $group_products[$slug] = $products_by_slug[$slug] ?? [];
                    }
                } elseif ($cat['label'] === 'Mains') {
                    $mix_qty = (int) ($acf_fields['lamb_chicken_qty_mains_mix'] ?? 0);
                    $mix_desc = $acf_fields['text_description_mains_mix'] ?? '';
                    $chicken_qty = (int) ($acf_fields['chicken_qty_mains'] ?? 0);
                    $lamb_qty = (int) ($acf_fields['lamb_qty_mains'] ?? 0);
                    $veg_qty = (int) ($acf_fields['vegetarian_qty_mains'] ?? 0);

                    if ($mix_qty > 0) {
                        $category_mix = true;
                        $mix_groups = ['chicken', 'lamb'];

                        $group_definitions['chicken'] = [
                            'label' => 'Chicken',
                            'qty' => 0,
                            'desc' => '',
                            'render' => 'subsection',
                        ];
                        $group_products['chicken'] = $products_by_slug['chicken'] ?? [];

                        $group_definitions['lamb'] = [
                            'label' => 'Lamb',
                            'qty' => 0,
                            'desc' => '',
                            'render' => 'subsection',
                        ];
                        $group_products['lamb'] = $products_by_slug['lamb'] ?? [];
                    } else {
                        if ($chicken_qty > 0) {
                            $group_definitions['chicken'] = [
                                'label' => 'Chicken',
                                'qty' => $chicken_qty,
                                'desc' => $acf_fields['text_description_mains_chicken'] ?? '',
                            ];
                            $group_products['chicken'] = $products_by_slug['chicken'] ?? [];
                        }
                        if ($lamb_qty > 0) {
                            $group_definitions['lamb'] = [
                                'label' => 'Lamb',
                                'qty' => $lamb_qty,
                                'desc' => $acf_fields['text_description_mains_lamb'] ?? '',
                            ];
                            $group_products['lamb'] = $products_by_slug['lamb'] ?? [];
                        }
                    }

                    if ($veg_qty > 0) {
                        $group_definitions['vegetarian'] = [
                            'label' => 'Vegetarian',
                            'qty' => $veg_qty,
                            'desc' => $acf_fields['text_description_mains_vegetarian'] ?? '',
                        ];
                        $group_products['vegetarian'] = $products_by_slug['vegetarian'] ?? [];
                    }
                }
                ?>

                <div class="pb-content<?= $category_mode === 'combined' ? ' pb-combined' : ''; ?>"
                    id="pb-<?= esc_attr($key); ?>"
                    data-category="<?= esc_attr($key); ?>"
                    data-free="<?= esc_attr($category_mode === 'combined' ? $combined_qty : 0); ?>"
                    data-mix="<?= $category_mix ? '1' : '0'; ?>"
                    data-mix-free="<?= esc_attr($mix_qty); ?>"
                    data-mix-groups="<?= esc_attr(implode(',', $mix_groups)); ?>">
                    <p class="pb-info" style="display: none;">
                        Qty: <?= esc_html($cat['qty']); ?>
                    </p>
                    <?php if (!empty($cat['desc']) && empty($category_mix) && !($cat['slug'] === 'mains' && empty($mix_groups))): ?>
                        <div class="pb-info-desc">
                            <?= wp_kses_post($cat['desc']); ?>
                        </div>
                    <?php endif; ?>
                    <!-- <p class="pb-info">
                        Additional items can be added with extra payment.
                    </p> -->

                    <?php if (!empty($group_products)): ?>

                        <?php if ($category_mode === 'combined'): ?>
                            <div class="extra-pricing-line pb-category-counter">
                                <div class="pb-counter">
                                    <span class="pb-counter-text"><?= esc_html($combined_qty); ?> selections required</span>
                                </div>
                                <span class="pb-extra-tab"></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($category_mix): ?>
                            <div class="pb-mix-block">
                                <!-- <h4 class="pb-subgroup-title">Chicken &amp; Lamb Mix</h4> -->
                                <?php if (!empty($mix_desc)): ?>
                                    <div class="pb-info-desc">
                                        <?= wp_kses_post($mix_desc); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="extra-pricing-line pb-mix-counter">
                                    <div class="pb-counter">
                                        <span class="pb-counter-text"><?= esc_html($mix_qty); ?> selections required</span>
                                    </div>
                                    <span class="pb-extra-tab"></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($group_definitions as $group_key => $group): ?>
                            <?php if (empty($group_products[$group_key])) continue; ?>

                            <?php if (!empty($group['render']) && $group['render'] === 'subsection'): ?>
                                <div class="pb-subsection" data-group="<?= esc_attr($group_key); ?>">
                                    <h4 class="pb-subgroup-title"><?= esc_html($group['label']); ?></h4>
                                    <div class="pb-subsection-items-group">
                                        <?php foreach ($group_products[$group_key] as $p): ?>
                                            <?php
                                            $item_type = '';
                                            $item_terms = get_the_terms($p->get_id(), 'product_cat');
                                            if ($item_terms && !is_wp_error($item_terms)) {
                                                foreach ($item_terms as $t) {
                                                    if (in_array($t->slug, ['chicken', 'lamb', 'vegetarian'], true)) {
                                                        $item_type = $t->slug;
                                                        break;
                                                    }
                                                }
                                            }
                                            ?>
                                            <label class="pb-item" data-id="<?= $p->get_id(); ?>" data-type="<?= esc_attr($item_type); ?>">
                                                <div class="selcedbox">
                                                    <input type="checkbox">
                                                    <h5 class="pb-price"></h5>
                                                </div>
                                                <h3><?= esc_html($p->get_name()); ?></h3>

                                                <h4 class="pb-desc">
                                                    <?= wp_kses_post($p->get_short_description()); ?>
                                                </h4>


                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php elseif ($category_mode === 'combined'): ?>
                                <div class="pb-subsection" data-group="<?= esc_attr($group_key); ?>">
                                    <h4 class="pb-subgroup-title"><?= esc_html($group['label']); ?></h4>
                                    <div class="pb-subsection-items-group">
                                        <?php foreach ($group_products[$group_key] as $p): ?>
                                            <?php
                                            $item_type = '';
                                            $item_terms = get_the_terms($p->get_id(), 'product_cat');
                                            if ($item_terms && !is_wp_error($item_terms)) {
                                                foreach ($item_terms as $t) {
                                                    if (in_array($t->slug, ['chicken', 'lamb', 'vegetarian'], true)) {
                                                        $item_type = $t->slug;
                                                        break;
                                                    }
                                                }
                                            }
                                            ?>
                                            <label class="pb-item" data-id="<?= $p->get_id(); ?>" data-type="<?= esc_attr($item_type); ?>">
                                                <div class="selcedbox">
                                                    <input type="checkbox">
                                                    <h5 class="pb-price"></h5>
                                                </div>
                                                <h3><?= esc_html($p->get_name()); ?></h3>

                                                <h4 class="pb-desc">
                                                    <?= wp_kses_post($p->get_short_description()); ?>
                                                </h4>


                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="pb-subgroup"
                                    data-group="<?= esc_attr($group_key); ?>"
                                    data-free="<?= esc_attr($group['qty']); ?>">
                                    <?php if (!empty($group['desc'])): ?>
                                        <div class="pb-info-desc">
                                            <?= wp_kses_post($group['desc']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="pb-info" style="display: none;">
                                        Qty: <?= esc_html($group['qty']); ?>
                                    </div>

                                    <div class="extra-pricing-line">
                                        <div class="pb-counter">
                                            <span class="pb-counter-text"><?= esc_html($group['qty']); ?> selections required</span>
                                        </div>
                                        <span class="pb-extra-tab"></span>
                                    </div>
                                    <h4 class="pb-subgroup-title">
                                        <?= esc_html($group['label']); ?>
                                    </h4>
                                    <div class="pb-subsection-items-group">
                                        <?php foreach ($group_products[$group_key] as $p): ?>
                                            <?php
                                            $item_type = '';
                                            $item_terms = get_the_terms($p->get_id(), 'product_cat');
                                            if ($item_terms && !is_wp_error($item_terms)) {
                                                foreach ($item_terms as $t) {
                                                    if (in_array($t->slug, ['chicken', 'lamb', 'vegetarian'], true)) {
                                                        $item_type = $t->slug;
                                                        break;
                                                    }
                                                }
                                            }
                                            ?>
                                            <label class="pb-item" data-id="<?= $p->get_id(); ?>" data-type="<?= esc_attr($item_type); ?>">
                                                <div class="selcedbox">
                                                    <input type="checkbox">
                                                    <h5 class="pb-price"></h5>
                                                </div>
                                                <h3><?= esc_html($p->get_name()); ?></h3>

                                                <h4 class="pb-desc">
                                                    <?= wp_kses_post($p->get_short_description()); ?>
                                                </h4>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No products found</p>
                    <?php endif; ?>

                </div>


            <?php endforeach; ?>
            <div class="pb-summary">
                Package total: £<span class="pb-package-total">0.00</span>
            </div>
            <div class="pb-footer">

                <!-- <div class="pb-summary d-p-none">
                    Extra total: £<span class="pb-extra-total">0.00</span>
                </div>
                <div class="pb-summary">
                    Package total: £<span class="pb-package-total">0.00</span>
                </div> -->

                <button type="button" id="pb-add-cart" class="pb-add-cart-btn">
                    Add Package to Cart
                </button>

            </div>
        </div>

        <?php
        $html = ob_get_clean();

        $package_deals_term = get_term_by('slug', 'package-deals', 'product_cat');
        $package_deals_children = [];
        if ($package_deals_term) {
            $children = get_terms([
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
                'parent' => (int) $package_deals_term->term_id,
            ]);
            if (!is_wp_error($children)) {
                foreach ($children as $child) {
                    $package_deals_children[] = [
                        'id' => (int) $child->term_id,
                        'slug' => $child->slug,
                        'name' => $child->name,
                        'parent' => (int) $child->parent,
                    ];
                }
            }
        }

        $package_pricing = [
            'chicken' => (float) ($acf_fields['chicken_pricing'] ?? 0),
            'lamb' => (float) ($acf_fields['lamb_pricing'] ?? 0),
            'vegetarian' => (float) ($acf_fields['vegetarian_pricing'] ?? 0),
        ];

        return [
            'html' => $html,
            'data' => [
                'acf_fields' => $acf_fields,
                'package_deals_children' => $package_deals_children,
                'child_prices' => $child_prices,
                'main_variations' => $main_variations,
                'package_pricing' => $package_pricing,
            ],
        ];
    }

    private function build_ramzan_package($product_id, $acf_fields, $main_variations)
    {
        error_log('Ramzan ACF fields: ' . print_r($acf_fields, true));

        // Get the main product for pricing
        $main_product = wc_get_product($product_id);
        $package_price = $main_product ? $main_product->get_price() : 0;

        $qty_persons_raw = $acf_fields['qty_persons'] ?? [];
        error_log('qty_persons raw: ' . print_r($qty_persons_raw, true));

        // Parse nested array structure - ACF checkbox returns array of arrays with 'value' key
        $qty_persons = [];
        if (is_array($qty_persons_raw)) {
            foreach ($qty_persons_raw as $person_option) {
                if (is_array($person_option) && isset($person_option['value'])) {
                    // Extract number from "for 2" or "for 8" format
                    $value = $person_option['value'];
                    if (preg_match('/\d+/', $value, $matches)) {
                        $qty_persons[] = $matches[0]; // Store as string "2" or "8"
                    }
                }
            }
        }
        error_log('Parsed qty_persons: ' . print_r($qty_persons, true));

        $detail_for_2 = $acf_fields['detail_for_2'] ?? '';
        $detail_for_8 = $acf_fields['detail_for_8'] ?? '';

        error_log('detail_for_2: ' . $detail_for_2);
        error_log('detail_for_8: ' . $detail_for_8);

        $persons_data = [];
        if (in_array('2', $qty_persons, true)) {
            $persons_data['2'] = [
                'label' => 'For 2 Persons',
                'detail' => $detail_for_2,
            ];
        }
        if (in_array('8', $qty_persons, true)) {
            $persons_data['8'] = [
                'label' => 'For 8 Persons',
                'detail' => $detail_for_8,
            ];
        }

        error_log('persons_data: ' . print_r($persons_data, true));

        if (empty($persons_data)) {
            return ['error' => 'No person options found'];
        }

        // Get all products with ramzan-deals category (child of package-deals)
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'tax_query' => [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => 'ramzan-deals',
                ],
            ],
        ];

        error_log('=== RAMZAN PRODUCTS QUERY ===');
        error_log('Query args: ' . print_r($args, true));

        $query = new WP_Query($args);
        $products = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);
                $products[] = $product;

                // Log categories for each product
                $product_cats = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);
                error_log('Product: ' . get_the_title() . ' - Categories: ' . print_r($product_cats, true));
            }
            wp_reset_postdata();
        }

        error_log('Total found: ' . count($products) . ' products with ramzan-deals category');
        error_log('=== END RAMZAN QUERY ===');

        $products_by_persons = [];
        $child_prices = [];

        foreach ($products as $p) {
            // Read child's ACF to determine which person options it belongs to
            $child_acf = function_exists('get_fields') ? get_fields($p->get_id()) : [];
            error_log('Checking product: ' . $p->get_name() . ' (ID: ' . $p->get_id() . ') - ACF: ' . print_r($child_acf, true));

            // Normalize child's qty_persons
            $child_qty_raw = $child_acf['qty_persons'] ?? [];
            $child_qty = [];
            if (is_array($child_qty_raw)) {
                foreach ($child_qty_raw as $opt) {
                    if (is_array($opt) && isset($opt['value'])) {
                        if (preg_match('/\\d+/', $opt['value'], $m)) {
                            $child_qty[] = $m[0];
                        }
                    }
                }
            }

            $is_for_2 = in_array('2', $child_qty, true);
            $is_for_8 = in_array('8', $child_qty, true);

            // Helper to push product entry with acf subset and price mapping
            $push_product = function ($person_key) use (&$products_by_persons, $p, $child_acf, &$child_prices) {
                if (!isset($products_by_persons[$person_key])) {
                    $products_by_persons[$person_key] = [];
                }
                $acf_subset = [
                    'detail_for_2' => $child_acf['detail_for_2'] ?? '',
                    'detail_for_8' => $child_acf['detail_for_8'] ?? '',
                    'qty_persons'  => $child_acf['qty_persons'] ?? [],
                ];
                $products_by_persons[$person_key][] = [
                    'product' => $p,
                    'acf' => $acf_subset,
                ];

                if ($p->is_type('variable')) {
                    foreach ($p->get_available_variations() as $v) {
                        $location = null;
                        foreach ($v['attributes'] as $attr_key => $attr_value) {
                            if (strpos($attr_key, 'location') !== false && $attr_value) {
                                $location = sanitize_title($attr_value);
                                break;
                            }
                        }
                        if (!$location) continue;
                        $child_prices[$person_key][$p->get_id()][$location] = (float) $v['display_price'];
                    }
                } else {
                    $child_prices[$person_key][$p->get_id()]['all'] = (float) $p->get_price();
                }
            };

            if ($is_for_2 && isset($persons_data['2'])) {
                error_log('Adding to for-2 (by ACF): ' . $p->get_name());
                $push_product('2');
            }

            if ($is_for_8 && isset($persons_data['8'])) {
                error_log('Adding to for-8 (by ACF): ' . $p->get_name());
                $push_product('8');
            }
        }

        ob_start();
        ?>

        <div id="package-builder" class="pb-ramzan">

            <ul class="pb-tabs">
                <?php foreach ($persons_data as $key => $pdata): ?>
                    <li data-tab="ramzan-<?= esc_attr($key); ?>">
                        <?= esc_html($pdata['label']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php foreach ($persons_data as $key => $pdata): ?>
                <div class="pb-content pb-ramzan-person"
                    id="pb-ramzan-<?= esc_attr($key); ?>"
                    data-category="<?= esc_attr($key); ?>">

                    <?php if (!empty($pdata['detail'])): ?>
                        <div class="pb-info-desc">
                            <?= wp_kses_post($pdata['detail']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($products_by_persons[$key])): ?>
                        <h4 class="pb-ramzan-items-header">Included Items</h4>
                        <div class="pb-subsection-items-group">
                            <?php foreach ($products_by_persons[$key] as $entry):
                                $prod = $entry['product'];
                                $prod_acf = $entry['acf'];
                                $detail_key = ($key == '2') ? 'detail_for_2' : 'detail_for_8';
                                $detail_text = $prod_acf[$detail_key] ?? '';
                            ?>
                                <div class="pb-item pb-ramzan-item selected" data-id="<?= $prod->get_id(); ?>" data-selected="1">
                                    <div class="selcedbox">
                                        <label class="pb-item-select">
                                            <input type="checkbox" class="pb-ramzan-checkbox" value="<?= $prod->get_id(); ?>" checked disabled>
                                        </label>
                                        <span class="pb-included-badge">Included</span>
                                    </div>
                                    <h3><?= esc_html($prod->get_name()); ?></h3>

                                    <?php if ($detail_text): ?>
                                        <h4 class="pb-desc">
                                            <?= wp_kses_post($detail_text); ?>
                                        </h4>
                                    <?php elseif ($prod->get_short_description()): ?>
                                        <h4 class="pb-desc">
                                            <?= wp_kses_post($prod->get_short_description()); ?>
                                        </h4>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No products found for this option.</p>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
            <div class="pb-summary">
                Package total: £<span class="pb-package-total"><?= esc_html(number_format($package_price, 2)); ?></span>
            </div>
            <div class="pb-footer">
                <button type="button" id="pb-add-cart" class="pb-add-cart-btn">
                    Add Package to Cart
                </button>
            </div>
        </div>

<?php
        $html = ob_get_clean();

        // Add console logging data
        $console_data = [
            'type' => 'ramzan',
            'qty_persons_raw' => $qty_persons_raw,
            'qty_persons_parsed' => $qty_persons,
            'persons_structure' => $persons_data,
            'products_by_persons' => (function ($products_by_persons) {
                $out = [];
                foreach ($products_by_persons as $person_key => $entries) {
                    $out[$person_key] = array_map(function ($entry) use ($person_key) {
                        $p = $entry['product'];
                        $acf = $entry['acf'] ?? [];
                        $detail_key = $person_key === '2' ? 'detail_for_2' : 'detail_for_8';
                        return [
                            'id' => $p->get_id(),
                            'name' => $p->get_name(),
                            'detail' => $acf[$detail_key] ?? '',
                            'qty_persons' => $acf['qty_persons'] ?? [],
                        ];
                    }, $entries);
                }
                return $out;
            })($products_by_persons),
        ];

        return [
            'html' => $html,
            'data' => [
                'acf_fields' => $acf_fields,
                'child_prices' => $child_prices,
                'main_variations' => $main_variations,
                'is_ramzan' => true,
                'package_pricing' => [],
                'console_log' => $console_data,
            ],
        ];
    }

    public function get_builder()
    {
        $product_id = absint($_POST['product_id'] ?? 0);

        if (!$product_id) {
            wp_send_json_error('Invalid product ID');
        }

        $payload = $this->build_package_builder_payload($product_id);

        if (!empty($payload['error'])) {
            wp_send_json_error($payload['error']);
        }

        wp_send_json_success($payload);
    }

    public function add_to_cart()
    {
        $variation_id = absint($_POST['variation_id'] ?? 0);
        $extra_price  = floatval($_POST['extra_price'] ?? 0);
        $package_price = floatval($_POST['package_price'] ?? 0);
        $persons = absint($_POST['persons'] ?? 0);
        $items        = $_POST['items'] ?? [];

        error_log('=== ADD TO CART RECEIVED ===');
        error_log('Variation ID: ' . $variation_id);
        error_log('Package Price: ' . $package_price);
        error_log('Extra Price: ' . $extra_price);
        error_log('Persons: ' . $persons);
        error_log('Items count: ' . count($items));
        error_log('============================');

        if (!$variation_id) {
            wp_send_json_error('Invalid variation');
        }

        $entries = [];
        if (is_array($items)) {
            foreach ($items as $item) {
                $pid = 0;
                $tab = '';
                $subgroup = '';

                if (is_array($item)) {
                    $pid = absint($item['id'] ?? 0);
                    $tab = sanitize_text_field($item['tab'] ?? '');
                    $subgroup = sanitize_text_field($item['subgroup'] ?? '');
                } else {
                    $pid = absint($item);
                }

                if (!$pid) {
                    continue;
                }

                $prod = wc_get_product($pid);
                if (!$prod) {
                    continue;
                }

                $entries[] = [
                    'id' => $pid,
                    'tab' => $tab,
                    'subgroup' => $subgroup,
                    'name' => $prod->get_name(),
                ];
            }
        }

        $item_ids = array_map(function ($entry) {
            return $entry['id'];
        }, $entries);

        WC()->cart->add_to_cart(
            wp_get_post_parent_id($variation_id),
            1,
            $variation_id,
            [],
            [
                'pb_package_price' => $package_price,
                'pb_persons' => $persons,
                'pb_extra_price' => $extra_price,
                'pb_items'       => $item_ids,
                'pb_item_entries' => $entries,
            ]
        );

        error_log('Cart meta set: pb_package_price=' . $package_price . ', pb_persons=' . $persons);

        wp_send_json_success();
    }
    public function update_cart_price($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) return;

        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['pb_package_price'])) {
                $package_price = (float) $cart_item['pb_package_price'];
                $extra = isset($cart_item['pb_extra_price']) ? (float) $cart_item['pb_extra_price'] : 0;
                $total_price = $package_price + $extra;
                
                error_log('=== UPDATE CART PRICE ===');
                error_log('Package Price from meta: ' . $package_price);
                error_log('Extra Price from meta: ' . $extra);
                error_log('Total Price to set: ' . $total_price);
                error_log('Product ID: ' . $cart_item['data']->get_id());
                error_log('=========================');
                
                $cart_item['data']->set_price($total_price);
                continue;
            }

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
        if (!empty($values['pb_persons'])) {
            $item->add_meta_data('Persons to Serve (per head)', absint($values['pb_persons']), true);
        }

        if (!empty($values['pb_item_entries']) && is_array($values['pb_item_entries'])) {
            // Group hierarchically by tab and subgroup
            $grouped = [];
            foreach ($values['pb_item_entries'] as $entry) {
                $tab = sanitize_text_field($entry['tab'] ?? '');
                $subgroup = sanitize_text_field($entry['subgroup'] ?? '');
                $name = sanitize_text_field($entry['name'] ?? '');
                if ($name === '') continue;

                if (!isset($grouped[$tab])) {
                    $grouped[$tab] = [];
                }
                if (!isset($grouped[$tab][$subgroup])) {
                    $grouped[$tab][$subgroup] = [];
                }
                $grouped[$tab][$subgroup][] = $name;
            }

            // Format hierarchical HTML in a box container - WITHOUT required text
            $display_html = '<div style="background-color: #f5f5f5; padding: 12px; border-radius: 4px; margin-top: 6px; line-height: 1.8;">';
            foreach ($grouped as $tab => $subgroups) {
                if ($tab) {
                    $display_html .= '<div style="font-weight: 600; margin-top: 8px; margin-bottom: 6px;">' . esc_html($tab) . '</div>';
                    foreach ($subgroups as $subgroup => $names) {
                        if ($subgroup) {
                            $display_html .= '<div style="margin-left: 16px; font-weight: 500;">' . esc_html($subgroup) . ':</div>';
                            foreach ($names as $name) {
                                $display_html .= '<div style="margin-left: 32px;">• ' . esc_html($name) . '</div>';
                            }
                        } else {
                            foreach ($names as $name) {
                                $display_html .= '<div style="margin-left: 16px;">• ' . esc_html($name) . '</div>';
                            }
                        }
                    }
                }
            }
            $display_html .= '</div>';

            if (strlen($display_html) > 50) {
                $item->add_meta_data('Selected Items', wp_kses_post($display_html), true);
            }
        } elseif (!empty($values['pb_items'])) {
            $names = [];
            foreach ((array) $values['pb_items'] as $pid) {
                $prod = wc_get_product($pid);
                if ($prod) {
                    $names[] = $prod->get_name();
                }
            }
            if (!empty($names)) {
                $item->add_meta_data('Selected Items', implode(', ', $names));
            }
        }

        // Add main package short description to order (not shown in cart/checkout)
        $product_id = $values['product_id'] ?? $item->get_product_id();
        $product = $product_id ? wc_get_product($product_id) : null;
        if ($product) {
            $short_desc = $product->get_short_description();
            if ($short_desc) {
                $item->add_meta_data('Package Description', wp_kses_post($short_desc));
            }
        }
    }

    public function display_selected_items($item_data, $cart_item)
    {
        if (!empty($cart_item['pb_persons'])) {
            $item_data[] = [
                'name'  => 'Persons to Serve (per head)',
                'value' => absint($cart_item['pb_persons'])
            ];
        }

        if (!empty($cart_item['pb_item_entries']) && is_array($cart_item['pb_item_entries'])) {
            // Group hierarchically by tab and subgroup
            $grouped = [];
            foreach ($cart_item['pb_item_entries'] as $entry) {
                $tab = sanitize_text_field($entry['tab'] ?? '');
                $subgroup = sanitize_text_field($entry['subgroup'] ?? '');
                $name = sanitize_text_field($entry['name'] ?? '');
                if ($name === '') continue;

                if (!isset($grouped[$tab])) {
                    $grouped[$tab] = [];
                }
                if (!isset($grouped[$tab][$subgroup])) {
                    $grouped[$tab][$subgroup] = [];
                }
                $grouped[$tab][$subgroup][] = $name;
            }

            // Format hierarchical HTML in a box container - WITHOUT required text
            $display_html = '<div style="background-color: #f5f5f5; padding: 12px; border-radius: 4px; margin-top: 6px; line-height: 1.8;">';
            foreach ($grouped as $tab => $subgroups) {
                if ($tab) {
                    $display_html .= '<div style="font-weight: 600; margin-top: 8px; margin-bottom: 6px;">' . esc_html($tab) . '</div>';
                    foreach ($subgroups as $subgroup => $names) {
                        if ($subgroup) {
                            $display_html .= '<div style="margin-left: 16px; font-weight: 500;">' . esc_html($subgroup) . ':</div>';
                            foreach ($names as $name) {
                                $display_html .= '<div style="margin-left: 32px;">• ' . esc_html($name) . '</div>';
                            }
                        } else {
                            foreach ($names as $name) {
                                $display_html .= '<div style="margin-left: 16px;">• ' . esc_html($name) . '</div>';
                            }
                        }
                    }
                }
            }
            $display_html .= '</div>';

            $item_data[] = [
                'name'  => 'Selected Items',
                'value' => wp_kses_post($display_html)
            ];
        } elseif (!empty($cart_item['pb_item_names'])) {
            $item_data[] = [
                'name'  => 'Selected Items',
                'value' => implode(', ', $cart_item['pb_item_names'])
            ];
        }

        return $item_data;
    }
}
add_action('woocommerce_before_calculate_totals', function ($cart) {

    if (is_admin() && !defined('DOING_AJAX')) return;

    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['pb_package_price'])) {
            $package_price = (float) $cart_item['pb_package_price'];
            $extra = isset($cart_item['pb_extra_price']) ? (float) $cart_item['pb_extra_price'] : 0;
            $cart_item['data']->set_price($package_price + $extra);
            continue;
        }
        if (isset($cart_item['pb_extra_price'])) {
            $cart_item['data']->set_price(
                $cart_item['data']->get_price() + $cart_item['pb_extra_price']
            );
        }
    }
});

new Woo_Package_Builder();
