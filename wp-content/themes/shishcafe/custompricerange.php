<?php
add_action('wp_enqueue_scripts', 'enqueue_location_price_script');

function enqueue_location_price_script()
{
	if (is_product()) {
		$product_id = get_queried_object_id();
		$product = wc_get_product($product_id); // Ensure product is retrieved

		if ($product && $product->is_type('variable')) {
			$location_prices = [];

			foreach ($product->get_available_variations() as $variation) {
				$location = isset($variation['attributes']['attribute_location']) ? $variation['attributes']['attribute_location'] : null;
				$price = isset($variation['display_price']) ? $variation['display_price'] : null;

				if ($location && $price !== null) {
					if (!isset($location_prices[$location])) {
						$location_prices[$location] = [];
					}
					$location_prices[$location][] = $price;
				}
			}
			// Prepare price range data
			$price_ranges = [];
			foreach ($location_prices as $location => $prices) {
				$price_ranges[$location] = [
					'min' => min($prices),
					'max' => max($prices),
				];
			}

			$currency_symbol = get_woocommerce_currency_symbol(); // Get currency dynamically

			// Enqueue script and pass price data
			wp_enqueue_script('location-price-range', get_template_directory_uri() . '/js/custompricerange.js', array('jquery'), null, true);
			
			// Pass price data to JavaScript
			wp_localize_script('location-price-range', 'locationPriceData', [
				'prices'   => $price_ranges,
				'currency' => $currency_symbol
			]);
		}
	}
}
add_filter('woocommerce_get_price_html', 'custom_price_range_display', 10, 2);

function custom_price_range_display($price, $product)
{
	if ($product->is_type('variable')) {
		// Placeholder for JavaScript to update the price
		$price = '<span id="custom-price-range">' . $price . '</span>';
	}
	return $price;
}
?>