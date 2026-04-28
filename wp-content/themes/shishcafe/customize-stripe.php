<?php 
/**
 * Get the order location for Stripe metadata.
 */
function shishcafe_get_order_location_for_stripe($order)
{
    if (! $order instanceof WC_Order) {
        return '';
    }

    $locations = [];
    foreach ($order->get_items() as $item) {
        $location = $item->get_meta('location', true);
        if (! empty($location)) {
            $locations[] = wc_clean($location);
        }
    }

    $locations = array_values(array_unique(array_filter($locations)));

    if (empty($locations)) {
        return '';
    }

    return count($locations) === 1 ? $locations[0] : implode(', ', $locations);
}

/**
 * Add location metadata to Stripe intent metadata payload.
 */
function shishcafe_add_location_to_stripe_intent_metadata($metadata, $order)
{
	if (! is_array($metadata)) {
		$metadata = [];
	}

	$location = shishcafe_get_order_location_for_stripe($order);
	if (! empty($location)) {
		$metadata['branch_location'] = $location;
	}

	return $metadata;
}

// Current hook in WooCommerce Stripe Gateway (UPE flow).
add_filter('wc_stripe_intent_metadata', 'shishcafe_add_location_to_stripe_intent_metadata', 20, 2);

// Compatibility fallback for older/legacy flows.
add_filter('wc_stripe_payment_metadata', 'shishcafe_add_location_to_stripe_intent_metadata', 20, 2);
