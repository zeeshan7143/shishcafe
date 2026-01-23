jQuery(document).ready(function ($) {

    const selectedOption = document.querySelector('input[name="delivery_pickup_option"]:checked');
    if (selectedOption) {
        console.log("Selected value:", selectedOption.value);
    }

    let customerLat = '';
    let customerLng = '';
    const locationKey = 'selectedLocation';
    const $deliveryMessageContainer = $('<div id="delivery-message" class="woocommerce-message" style="display:none;margin-top:10px;"></div>').insertAfter('#delivery_pickup_option_field');

    function triggerCheckoutUpdate() {
        setTimeout(function () {
            $('body').trigger('update_checkout');
        }, 2000);
    }

    // ========================
    // Autocomplete Address
    // ========================
    if (typeof google !== 'undefined') {
        const input = document.getElementById('billing_autocomplete_address');
        if (input) {
            const autocomplete = new google.maps.places.Autocomplete(input, {
                // types: ['geocode'],
                // componentRestrictions: { country: 'pk' }
                // componentRestrictions: { country: 'gb' }
            });

            autocomplete.addListener('place_changed', function () {
                const place = autocomplete.getPlace();
                console.log('[Google Autocomplete] Selected place:', place);
                if (!place.address_components) return;

                customerLat = place.geometry.location.lat();
                customerLng = place.geometry.location.lng();

                const getComponent = (type) => {
                    const match = place.address_components.find(comp => comp.types.includes(type));
                    return match ? match.long_name : '';
                };

                const street_number = getComponent('street_number');
                const route = getComponent('route');
                const premise = getComponent('premise');
                const neighborhood = getComponent('neighborhood');
                const city = getComponent('locality');
                const state = getComponent('administrative_area_level_1');
                const country = getComponent('country');
                const postcode = getComponent('postal_code');

                // Create address line 1 by priority: street + route → premise → neighborhood
                const addressLine1 = [street_number, route].filter(Boolean).join(' ') || premise || neighborhood;

                // Set the fields
                // $('#billing_address_1').val(addressLine1).prop('readonly', true);
                // $('#billing_city').val(city).prop('readonly', true);
                // $('#billing_state').val(state).prop('readonly', true);
                // $('#billing_postcode').val(postcode).prop('readonly', true);
                // $('#billing_country').val(country).prop('readonly', true);


                // console.log(`[Filled Fields]
                // Address 1: ${premise}
                // Address 1: ${neighborhood}
                // Address 1: ${route}
                // City: ${city}
                // State: ${state}
                // Country: ${country}
                // Postcode: ${postcode || '[empty]'}`);
                triggerCheckoutUpdate()
                // $('body').trigger('update_checkout');
            });
        }
    }

    // ========================
    // Delivery Fee Calculation
    // ========================

    function setPlaceOrderButtonDisabled(state) {
        $('#place_order').prop('disabled', state);
    }

    function getSubtotalValue() {
        const raw = $('.order-total .woocommerce-Price-amount bdi').first().text().replace(/[^\d.]/g, '');
        return parseFloat(raw || 0);
    }

    // function calculateAndSetDeliveryFee() {
    //     const storedLocation = localStorage.getItem(locationKey);
    //     const selectedDeliveryOption = $('input[name="delivery_pickup_option"]:checked').val() || '';

    //     if (!selectedDeliveryOption || selectedDeliveryOption === 'pickup' || !storedLocation || storedLocation.toLowerCase() !== 'rochdale') {
    //         triggerCheckoutUpdate();
    //         return;
    //     }

    //     let customerAddress = $('#billing_autocomplete_address').val();

    //     if (!customerAddress.trim()) {
    //         $deliveryMessageContainer.text('Please enter a complete delivery address using autocomplete.').show();
    //         triggerCheckoutUpdate();
    //         return;
    //     }

    //     $.ajax({
    //         type: 'POST',
    //         url: customDelivery.ajax_url,
    //         data: {
    //             action: 'custom_calculate_delivery_distance',
    //             nonce: customDelivery.nonce,
    //             customer_address: customerAddress,
    //             stored_location: storedLocation,
    //             delivery_pickup_option: selectedDeliveryOption,
    //             customer_lat: customerLat,
    //             customer_lng: customerLng,
    //             store_latitude: customDelivery.store_latitude,
    //             store_longitude: customDelivery.store_longitude,
    //             store_address: customDelivery.store_address
    //         },
    //         success: function (response) {
    //             console.log('[AJAX] Delivery Calculation Response:', response);
    //             if (response.success) {
    //                 const distance = response.data.distance_miles;
    //                 const deliveryStatus = response.data.delivery_status;
    //                 const subtotal = getSubtotalValue();

    //                 if (deliveryStatus !== 'available' || distance <= 0) {
    //                     $deliveryMessageContainer.text('Could not calculate delivery charges. Please try again.').show();
    //                     setPlaceOrderButtonDisabled(true);
    //                 } else {
    //                     if (selectedDeliveryOption === 'delivery' && subtotal < 10) {
    //                         $deliveryMessageContainer.text('Order must be minimum £10 for delivery.').show();
    //                         setPlaceOrderButtonDisabled(true);
    //                     }
    //                     else if (selectedDeliveryOption === 'pickup' && subtotal < 10) {
    //                         setPlaceOrderButtonDisabled(false);
    //                         $deliveryMessageContainer.hide();
    //                     }
    //                     else {
    //                         setPlaceOrderButtonDisabled(false);
    //                         $deliveryMessageContainer.hide();
    //                     }
    //                 }

    //                 triggerCheckoutUpdate();
    //             } else {
    //                 $deliveryMessageContainer.text('Delivery calculation failed. Please try again.').show();
    //                 setPlaceOrderButtonDisabled(true);
    //                 triggerCheckoutUpdate();
    //             }
    //         },
    //         error: function (xhr, status, error) {
    //             console.error('[AJAX ERROR]', error);
    //             $deliveryMessageContainer.text('Delivery calculation failed. Please check console.').show();
    //             triggerCheckoutUpdate();
    //         }
    //     });
    // }
    function calculateAndSetDeliveryFee() {
        const storedLocation = localStorage.getItem("selectedLocation") || '';
        const selectedDeliveryOption = $('input[name="delivery_pickup_option"]:checked').val();
        // const distanceMiles = response.distance_miles;
        // console.log('distanceMiles', distanceMiles);
        if (selectedDeliveryOption !== 'delivery') {
            console.log('[Skip] Delivery not selected. No calculation needed.');
            triggerCheckoutUpdate();
            return;
        }

        if (!storedLocation || storedLocation.toLowerCase() !== 'rochdale') {
            console.log('[Skip] Location not eligible for delivery.');
            triggerCheckoutUpdate();
            return;
        }

        const customerAddress = $('#billing_autocomplete_address').val();
        if (!customerAddress.trim()) {
            console.log('[Skip] Address empty for delivery.');
            $deliveryMessageContainer.text('Please enter a complete delivery address using autocomplete.').show();
            triggerCheckoutUpdate();
            return;
        }
        else {
            triggerCheckoutUpdate();

            $deliveryMessageContainer.text('Please enter a complete delivery address using autocomplete.').hide();
        }

        // ✅ Show loader before AJAX starts

        $.ajax({
            type: 'POST',
            url: customDelivery.ajax_url,
            data: {
                action: 'custom_calculate_delivery_distance',
                nonce: customDelivery.nonce,
                customer_address: customerAddress,
                stored_location: storedLocation,
                delivery_pickup_option: selectedDeliveryOption,
                customer_lat: customerLat,
                customer_lng: customerLng,
                store_latitude: customDelivery.store_latitude,
                store_longitude: customDelivery.store_longitude,
                store_address: customDelivery.store_address
            },
            success: function (response) {
                if (response.success) {
                    const distance = response.data.distance_miles;
                    const deliveryStatus = response.data.delivery_status;
                    const fullAddress = response.data.customer_address;

                    console.log("Distance:", distance, "Status:", deliveryStatus, "Address:", fullAddress);

                    const subtotal = getSubtotalValue();
                    if (deliveryStatus !== 'available') {
                        $deliveryMessageContainer.text('Delivery not availble, delivery is available only in 5 miles radius').show();
                    }
                    else {
                        if (selectedDeliveryOption === 'delivery' && subtotal < 10) {
                            $deliveryMessageContainer.text('Order must be minimum £10 for delivery.').show();
                            setPlaceOrderButtonDisabled(true);
                        } else {
                            setPlaceOrderButtonDisabled(false);
                            $deliveryMessageContainer.hide();
                        }
                    }

                    triggerCheckoutUpdate();
                } else {
                    $deliveryMessageContainer.text('Could not calculate delivery charges. Please try again.').show();
                    setPlaceOrderButtonDisabled(true);
                    triggerCheckoutUpdate();
                }
            },
            error: function (xhr, status, error) {
                console.error('[AJAX ERROR]', error);
                $deliveryMessageContainer.text('Delivery calculation failed. Please check console.').show();
                triggerCheckoutUpdate();
            }
        });
    }

    function removeDeliveryFeeIfPickup() {
        const selectedOption = $('input[name="delivery_pickup_option"]:checked').val();
        if (selectedOption === 'pickup') {
            $.post({
                url: customDelivery.ajax_url,
                data: {
                    action: 'custom_clear_delivery_session',
                    nonce: customDelivery.nonce
                },
                success: function () {
                    console.log('Delivery session data cleared.');
                    triggerCheckoutUpdate();
                }
            });
        }
    }

    // ========================
    // Event Listeners
    // ========================

    // When delivery option changes
    $('body').on('change', 'input[name="delivery_pickup_option"]', function () {
        const selectedOption = $(this).val();
        const storedLocation = localStorage.getItem(locationKey) || '';

        console.log('[Change] Option changed:', selectedOption);
        triggerCheckoutUpdate();
        if (selectedOption === 'pickup') {
            $deliveryMessageContainer.text('Please enter a complete delivery address using autocomplete.').hide();
        }

        calculateAndSetDeliveryFee();
        removeDeliveryFeeIfPickup(); // <- here 

        if (storedLocation.toLowerCase() === 'rochdale' && selectedOption === 'delivery') {
            $('#woocommerce_checkout_shipping').slideDown();
        } else {
            $('#woocommerce_checkout_shipping').slideUp();
        }
    });
    // When address changes
    $('body').on('change', '#billing_autocomplete_address', function () {
        if ($('input[name="delivery_pickup_option"]:checked').val() === 'delivery') {
            calculateAndSetDeliveryFee();
        }
    });

    // WooCommerce checkout updated
    $(document.body).on('updated_checkout', function () {
        const deliveryOption = $('input[name="delivery_pickup_option"]:checked').val();
        const subtotal = getSubtotalValue();

        if (deliveryOption === 'pickup') {
            setPlaceOrderButtonDisabled(false);
        } else if (deliveryOption === 'delivery' && subtotal < 10) {
            $deliveryMessageContainer.text('Order must be minimum £10 for delivery.').show();
            setPlaceOrderButtonDisabled(true);
        } else {
            setPlaceOrderButtonDisabled(false);
        }
    });

    // Initial call
    $(window).on('load', function () {
        const selectedOption = $('input[name="delivery_pickup_option"]:checked').val();
        if (selectedOption === 'delivery') {
            calculateAndSetDeliveryFee();
        }
    });
});