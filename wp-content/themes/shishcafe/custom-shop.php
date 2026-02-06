<?php
// Short Code for Custom Shop Page
function custom_shop_shortcode()
{
    // Enqueue script and pass AJAX URL only when shortcode is used
    wp_enqueue_script('custom-shop', get_template_directory_uri() . '/js/custom-shop.js', array('jquery'), _S_VERSION, true);
    wp_localize_script('custom-shop', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));

    ob_start(); ?>

    <div class="custom-shop-container">
        <aside class="custom-shop-sidebar">
            <h3>Filter by Category</h3>

            <select id="category-select">
                <option value="">Select a category</option>
                <?php
                $categories = get_terms('product_cat', array(
                    'hide_empty' => true,
                    'exclude'    => get_option('default_product_cat') // Exclude "Uncategorized"
                ));

                foreach ($categories as $category) {
                    $count = $category->count; // Get product count
                    echo '<option value="' . esc_attr($category->slug) . '">'
                        . esc_html($category->name) . ' <span class="product-count">(' . esc_html($count) . ')</span>'
                        . '</option>';
                }
                ?>
            </select>

            <div id="category-checkboxes">
                <?php foreach ($categories as $category) : ?>
                    <label>
                        <input type="checkbox" value="<?php echo esc_attr($category->slug); ?>" class="category-filter">
                        <?php echo esc_html($category->name); ?>
                        <span class="product-count">(<?php echo esc_html($category->count); ?>)</span>
                    </label><br>
                <?php endforeach; ?>
            </div>
            <button id="reset-filters" style="display: none;">Reset Filters</button>
        </aside>

        <main class="custom-shop-products">
            <div id="product-list" class="products"></div>
            <button id="load-more" data-page="1">Load More</button>
            <!-- <p id="not-found" style="text-align: center;">No products found.</p> -->
        </main>
    </div>
<?php
    return ob_get_clean();
}
add_shortcode('custom_shop', 'custom_shop_shortcode');


// Category Filter and Location Filter
function load_more_products()
{
    // Get the page number
    $paged = isset($_POST['page']) ? intval($_POST['page']) : 1;

    // Get selected categories from AJAX request
    $categories = isset($_POST['categories']) ? array_filter(explode(',', sanitize_text_field($_POST['categories']))) : [];
    // Get selected location from AJAX request
    $selected_location = isset($_POST['selected_location']) ? sanitize_text_field($_POST['selected_location']) : '';

    // Base query args
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => 9, // Change this number as needed
        'paged'          => $paged,
        'post_status'    => 'publish',
        'orderby'        => 'ID', // Order by post ID
        'order'          => 'ASC',
        'meta_query'     => array('relation' => 'AND'), // Start with AND relation
    );

    // Filter by selected categories
    if (!empty($categories)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $categories,
            ),
        );
    }

    // Filter by location (checking product tags)
    if (!empty($selected_location)) {
        $args['tax_query'][] = array(
            'taxonomy' => 'product_tag',
            'field'    => 'slug',
            'terms'    => sanitize_title_with_dashes($selected_location), // Match tag slugs
        );
    }

    // Query the products
    $query = new WP_Query($args);
    $output = '';
    $product_tags_data = array();
    $location_prices = [];

    if ($query->have_posts()) {
        ob_start();
        while ($query->have_posts()) {
            $query->the_post();
            global $product;
            $product_id = get_the_ID();
            if ($product->is_type('variable')) {
                $variations = $product->get_available_variations();

                foreach ($variations as $variation) {
                    $location = $variation['attributes']['attribute_location'] ?? null;
                    $price = $variation['display_price'];

                    if ($location) {
                        if (!isset($location_prices[$product_id])) {
                            $location_prices[$product_id] = [];
                        }
                        if (!isset($location_prices[$product_id][$location])) {
                            $location_prices[$product_id][$location] = [];
                        }
                        $location_prices[$product_id][$location][] = $price;
                    }
                }
            }
            wc_get_template_part('content', 'product'); // Load the WooCommerce product template
            $tags = wp_get_post_terms(get_the_ID(), 'product_tag', array('fields' => 'slugs'));
            $product_tags_data[get_the_ID()] = $tags;
            $product_location_prices[$product_id] = $location_prices; // Add location-based prices
        }
        $output = ob_get_clean();
    } else {
        $output = '';
    }
    // For Prouct category count Start 
    $category_counts = [];
    $categories = get_terms('product_cat', ['hide_empty' => true]);
    foreach ($categories as $cat) {
        $count_args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => [
                'relation' => 'AND',
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => $cat->slug,
                ],
            ],
        ];
        if (!empty($selected_location)) {
            $count_args['tax_query'][] = [
                'taxonomy' => 'product_tag',
                'field'    => 'slug',
                'terms'    => sanitize_title_with_dashes($selected_location),
            ];
        }
        $count_query = new WP_Query($count_args);
        $category_counts[$cat->slug] = $count_query->found_posts;
    }
    // wp_reset_postdata();
    echo json_encode([
        'html'            => $output,
        'has_more'        => $query->max_num_pages > $paged,
        'product_tags'    => $product_tags_data,
        'prices'          => $location_prices,
        'category_counts' => $category_counts, // 🔥 new
    ]);
    // For Prouct category count End 
    // wp_reset_postdata();

    // // Return response as JSON
    // echo json_encode([
    //     'html' => $output,
    //     'has_more' => $query->max_num_pages > $paged,
    //     'product_tags' => $product_tags_data,
    //     'prices'   => $location_prices,
    // ]);

    wp_die();
}

add_action('wp_ajax_load_more_products', 'load_more_products');
add_action('wp_ajax_nopriv_load_more_products', 'load_more_products');
?>