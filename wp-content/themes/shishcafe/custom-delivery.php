    <?php


    function custom_delivery_scripts()
    {
        if (is_checkout()) {
            wp_enqueue_script('custom-delivery-script', get_stylesheet_directory_uri() . '/js/custom-delivery.js', array('jquery'), null, true);
            wp_localize_script('custom-delivery-script', 'customDelivery', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('custom_delivery_nonce'),
                'store_latitude' => STORE_LATITUDE,
                'store_longitude' => STORE_LONGITUDE,
                'store_address' => STORE_ADDRESS,
                'initial_stored_location' => isset($_COOKIE['selectedLocation']) ? sanitize_text_field($_COOKIE['selectedLocation']) : '',
            ));
        }
    }
    add_action('wp_enqueue_scripts', 'custom_delivery_scripts');

    function get_wc_session_value($key, $default = '')
    {
        if (WC()->session && WC()->session->has_session()) {
            return WC()->session->get($key, $default);
        }
        return $default;
    }
    // ✅ Step 1: Add delivery/pickup options via action
    // Add delivery method as a custom field between email and address
    add_filter('woocommerce_checkout_fields', 'add_delivery_option_field_between_email_and_address');
    function add_delivery_option_field_between_email_and_address($fields)
    {
        $stored_location = isset($_COOKIE['selectedLocation']) ? sanitize_text_field($_COOKIE['selectedLocation']) : '';
        $is_delivery_allowed = (strtolower($stored_location) === 'rochdale');
        // $selected = WC()->session->get('delivery_pickup_option', $is_delivery_allowed ? 'delivery' : 'pickup');
        $selected = get_wc_session_value(
            'delivery_pickup_option',
            $is_delivery_allowed ? 'delivery' : 'pickup'
        );
        $options = $is_delivery_allowed
            ? array(
                'delivery' => 'Delivery',
                'pickup'   => 'Pickup',
            )
            : array(
                'pickup'   => 'Pickup',
            );
        $fields['billing']['delivery_pickup_option'] = array(
            'type'     => 'radio',
            'label'    => __('Choose your Order Type'),
            'class'    => array('form-row-wide'),
            'required' => true,
            //             'options'  => array(
            //                 'delivery' => 'Delivery',
            //                 'pickup'   => 'Pickup',
            //             ),
            'options'  => $options,
            'priority' => 45,
            'default'  => $selected,
        );

        return $fields;
    }

    // ✅ Step 2: Add autocomplete input to billing fields
    add_filter('woocommerce_checkout_fields', 'add_custom_autocomplete_field');
    function add_custom_autocomplete_field($fields)
    {
        $fields['billing']['billing_autocomplete_address'] = array(
            'label'       => __('Delivery Address (Autocomplete)', 'your-text-domain'),
            'placeholder' => __('Start typing your address...', 'your-text-domain'),
            'required'    => true,
            'class'       => array('form-row-wide'),
            'priority'    => 45,
        );
        return $fields;
    }

    add_action('woocommerce_checkout_update_order_review', function () {
        if (!empty($_POST['delivery_pickup_option'])) {
            WC()->session->set('delivery_pickup_option', sanitize_text_field($_POST['delivery_pickup_option']));
        }
    }, 10);

    function custom_calculate_delivery_distance_callback()
    {
        check_ajax_referer('custom_delivery_nonce', 'nonce');

        $address = sanitize_text_field($_POST['customer_address'] ?? '');
        $stored_location = sanitize_text_field($_POST['stored_location'] ?? '');
        $option = sanitize_text_field($_POST['delivery_pickup_option'] ?? '');

        // 🚫 If delivery is not selected, skip distance logic
        if ($option !== 'delivery') {
            WC()->session->set('calculated_delivery_distance_miles', 0);
            WC()->session->set('delivery_location_status', 'not_required');

            wp_send_json_success([
                'distance_miles' => 0,
                'delivery_status' => 'not_required',
                'customer_latitude'   => null,
                'customer_longitude'  => null,
                'customer_address'    => ''
            ]);
        }

        // 🚫 If location is not Rochdale, delivery not available
        if (strtolower($stored_location) !== 'rochdale') {
            WC()->session->set('calculated_delivery_distance_miles', 0);
            WC()->session->set('delivery_location_status', 'not_available');
            WC()->cart->calculate_totals();
            wp_send_json_success([
                'distance_miles' => 0,
                'delivery_status' => 'not_available',
                'customer_latitude'   => null,
                'customer_longitude'  => null,
                'customer_address'    => ''
            ]);
        }

        // ✅ Proceed with geocode & distance matrix
        if (empty($address) || !defined('GOOGLE_API_KEY')) {
            wp_send_json_error('Address missing or API key not set.');
        }

        $geo_url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($address) . "&key=" . GOOGLE_API_KEY;
        $geo_response = wp_remote_get($geo_url);
        $geo_data = json_decode(wp_remote_retrieve_body($geo_response), true);

        if (empty($geo_data['results'][0]['geometry']['location'])) {
            wp_send_json_error('Failed to geocode address.');
        }

        $lat = floatval($geo_data['results'][0]['geometry']['location']['lat']);
        $lng = floatval($geo_data['results'][0]['geometry']['location']['lng']);
        $formatted_address = $geo_data['results'][0]['formatted_address'] ?? $address;

        // Get distance
        $dist_url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=" . STORE_LATITUDE . "," . STORE_LONGITUDE . "&destinations={$lat},{$lng}&mode=driving&key=" . GOOGLE_API_KEY;
        $dist_response = wp_remote_get($dist_url);
        $dist_data = json_decode(wp_remote_retrieve_body($dist_response), true);

        if (empty($dist_data['rows'][0]['elements'][0]['distance']['value'])) {
            wp_send_json_error('Failed to calculate distance.');
        }

        $distance_km_raw = $dist_data['rows'][0]['elements'][0]['distance']['value'] / 1000;
        $distance_miles = round($distance_km_raw * 0.621371, 2);
        // $status = ($distance_miles > 0) ? 'available' : 'unavailable';
        $status = ($distance_miles > 0 && $distance_miles  <= 5) ? 'available' : 'unavailable';


        WC()->session->set('calculated_delivery_distance_miles', $distance_miles);
        WC()->session->set('delivery_location_status', $status);
        WC()->cart->calculate_totals();
        wp_send_json_success([
            'distance_miles' => $distance_miles,
            'delivery_status' => $status,
            'customer_latitude'   => $lat,
            'customer_longitude'  => $lng,
            'customer_address'    => $formatted_address
        ]);
    }

    add_action('wp_ajax_custom_calculate_delivery_distance', 'custom_calculate_delivery_distance_callback');

    add_action('wp_ajax_nopriv_custom_calculate_delivery_distance', 'custom_calculate_delivery_distance_callback');

    function custom_add_delivery_fee($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) return;
        error_log('[custom_add_delivery_fee] Triggered!');


        // Remove previously added delivery fee by name (no direct WooCommerce method, so this resets everything each time)
        foreach ($cart->get_fees() as $fee_key => $fee) {
            if ($fee->name === 'Delivery Charges') {
                unset($cart->fees_api()->fees[$fee_key]);
            }
        }

        $option = WC()->session->get('delivery_pickup_option', 'pickup');
        $distance = WC()->session->get('calculated_delivery_distance_miles', 0);
        $status = WC()->session->get('delivery_location_status', 'unavailable');
        $subtotal = $cart->get_subtotal();
        error_log("[Delivery Fee] Option: $option, Distance: $distance, Status: $status, Subtotal: $subtotal");
        error_log("[SESSION] delivery_pickup_option: " . WC()->session->get('delivery_pickup_option'));


        // Only add fee if delivery is selected and conditions met
        if ($option === 'delivery' && $status === 'available' && $distance > 0) {
            $fee = 0;
            $per_mile = 1.5;

            if ($subtotal >= 20) {
                $fee = ($distance <= 3) ? 0 : ($distance - 3) * $per_mile;
            } elseif ($subtotal >= 10) {
                $fee = ($distance <= 3) ? $per_mile : $per_mile + ($distance - 3) * $per_mile;
            }

            if ($fee > 0) {
                $cart->add_fee('Delivery Charges', $fee);
            }
        }
    }
    add_action('woocommerce_cart_calculate_fees', 'custom_add_delivery_fee', 20, 1);

    add_action('woocommerce_thankyou', function () {
        WC()->session->set('calculated_delivery_distance_miles', 0);
        WC()->session->set('delivery_location_status', '');
        WC()->session->set('delivery_pickup_option', '');
    });
    // CLear Session data 
    add_action('wp_ajax_custom_clear_delivery_session', 'custom_clear_delivery_session_callback');
    add_action('wp_ajax_nopriv_custom_clear_delivery_session', 'custom_clear_delivery_session_callback');

    function custom_clear_delivery_session_callback()
    {
        check_ajax_referer('custom_delivery_nonce', 'nonce');

        WC()->session->set('delivery_pickup_option', 'pickup');
        WC()->session->set('calculated_delivery_distance_miles', 0);
        WC()->session->set('delivery_location_status', 'not_required');

        wp_send_json_success('Session cleared for pickup.');
    }

    // update cart with radio 
    add_action('woocommerce_checkout_update_order_review', function () {
        if (isset($_POST['post_data'])) {
            parse_str($_POST['post_data'], $form_data);

            if (isset($form_data['delivery_pickup_option'])) {
                $option = sanitize_text_field($form_data['delivery_pickup_option']);
                WC()->session->set('delivery_pickup_option', $option);
                error_log("[Session Updated] delivery_pickup_option set to: $option");
            } else {
                error_log("[Session NOT SET] delivery_pickup_option missing in form_data");
            }
        }
    });
    // testing 
    add_action('woocommerce_cart_calculate_fees', function () {
        $option = WC()->session->get('delivery_pickup_option');
        error_log("[Delivery Fee Hook] delivery_pickup_option: " . $option);
    });

    // add data to checkout 
    // 1. Update order meta only if delivery is selected
    add_action('woocommerce_checkout_update_order_meta', function ($order_id) {
        if ($_POST['delivery_pickup_option'] == 'delivery') {
            update_post_meta($order_id, '_custom_delivery_option', 'delivery');
        } else if ($_POST['delivery_pickup_option'] == 'pickup') {
            update_post_meta($order_id, '_custom_delivery_option', 'pickup');
        }
    });
    // 2. Show value in admin only if it's set (Delivery only)
    add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
        $val = get_post_meta($order->get_id(), '_custom_delivery_option', true);
        if ($val == 'delivery') {
            echo '<p><strong>Order Type:</strong> Delivery</p>';
        } else if ($val == 'pickup') {
            echo '<p><strong>Order Type:</strong> Pickup</p>';
        }
    }, 10, 1);
    // 3. Add to email meta 
    add_filter('woocommerce_email_order_meta_fields', function ($fields, $sent_to_admin, $order) {
        $val = get_post_meta($order->get_id(), '_custom_delivery_option', true);
        if ($val == 'delivery') {
            $fields['custom_delivery_option'] = [
                'label' => 'Order Type',
                'value' => 'Delivery',
            ];
        } else if ($val == 'pickup') {
            $fields['custom_delivery_option'] = [
                'label' => 'Order Type',
                'value' => 'Pickup',
            ];
        }

        return $fields;
    }, 10, 3);


    // Auto Complete Address  to form 
    add_action('woocommerce_checkout_update_order_meta', 'save_autocomplete_address_meta');
    function save_autocomplete_address_meta($order_id)
    {
        if (!empty($_POST['billing_autocomplete_address'])) {
            update_post_meta($order_id, '_billing_autocomplete_address', sanitize_text_field($_POST['billing_autocomplete_address']));
        }
    }

    add_action('woocommerce_admin_order_data_after_billing_address', 'display_autocomplete_address_admin', 10, 1);
    function display_autocomplete_address_admin($order)
    {
        $value = get_post_meta($order->get_id(), '_billing_autocomplete_address', true);
        if ($value) {
            echo '<p><strong>' . __('Delivery Address (Autocomplete)', 'your-text-domain') . ':</strong> ' . esc_html($value) . '</p>';
        }
    }

    add_filter('woocommerce_email_order_meta_fields', 'add_autocomplete_address_to_email', 10, 3);
    function add_autocomplete_address_to_email($fields, $sent_to_admin, $order)
    {
        $fields['billing_autocomplete_address'] = array(
            'label' => __('Delivery Address (Autocomplete)', 'your-text-domain'),
            'value' => get_post_meta($order->get_id(), '_billing_autocomplete_address', true),
        );
        return $fields;
    }

    add_action('wp_enqueue_scripts', 'enqueue_google_autocomplete_script');
    function enqueue_google_autocomplete_script()
    {
        if (is_checkout()) {
            wp_enqueue_script('google-places-autocomplete', 'https://maps.googleapis.com/maps/api/js?key=' . GOOGLE_API_KEY . '&libraries=places', [], null, true);
            //             wp_enqueue_script('custom-autocomplete-checkout', get_stylesheet_directory_uri() . '/js/autocomplete.js', ['jquery'], null, true);
        }
    }



    // make address fields readonly 
    // add_filter('woocommerce_checkout_fields', 'customize_billing_fields_for_autocomplete');
    // function customize_billing_fields_for_autocomplete($fields)
    // {
    //     // Set initial values empty and readonly
    //     foreach (['billing_address_1', 'billing_city', 'billing_postcode', 'billing_state', 'billing_country'] as $key) {
    //         $fields['billing'][$key]['default'] = '';
    //         $fields['billing'][$key]['custom_attributes'] = ['readonly' => 'readonly'];
    //     }

    //     return $fields;
    // }
