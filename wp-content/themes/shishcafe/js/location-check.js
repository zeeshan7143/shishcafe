
jQuery(document).ready(function ($) {

    // console.log('wc_loc_guard object:', wc_loc_guard);

    const locationKey = 'selectedLocation';

    /**
     * Simple popup HTML
     */
    function showConflictPopup(currentLocation, newLocation, redirectUrl) {

        if (jQuery('#location-popup-overlay').length) return;

        const html = `
            <div id="location-popup-overlay">
                <div id="location-popup">
                    <span id="close-popup">✕</span>
                    <p> 
                        You’re switching from your previous location <strong style="text-transform: capitalize">${currentLocation}</strong> to a new location <strong style="text-transform: capitalize">${newLocation}</strong>.<br>
                        Your cart currently contains items from the old location.
                        To continue, please choose one of the options below:
                    </p>
                    <div class="buttons">
                        <button id="location-keep-cart">Proceed to Checkout with Current Items</button>
                        <button id="location-clear-cart">Clear Cart & Change Location</button>
                    </div>
                </div>
            </div>
        `;

        // Append popup
        jQuery('body').append(html);

        //Stop body scrolling
        jQuery('body').css('overflow', 'hidden');

        // ➜ Close popup
        jQuery('#close-popup').on('click', function () {
            jQuery('#location-popup-overlay').remove();
            jQuery('body').css('overflow', ''); // restore scroll
        });

        // ➜ Continue checkout (do NOT change location)
        jQuery('#location-keep-cart').on('click', function () {
            window.location.href = wc_loc_guard.checkout_url;
        });

        // ➜ Clear cart & allow location change
        // jQuery('#location-clear-cart').on('click', function () {
        //     jQuery.post(wc_loc_guard.ajax_url, {
        //         action: 'wc_clear_cart_only',
        //         nonce: wc_loc_guard.nonce
        //     }, function () {

        //         // Update localStorage only AFTER cart is cleared
        //         localStorage.setItem(locationKey, newLocation);

        //         window.location.href = redirectUrl;
        //     });
        // });
        jQuery('#location-clear-cart').on('click', function () {

            jQuery.post(wc_loc_guard.ajax_url, {
                action: 'wc_clear_cart_only',   
                nonce: wc_loc_guard.nonce
            }, function () {

                // ✅ Update localStorage AFTER cart clear
                localStorage.setItem(locationKey, newLocation);

                // 🔥 WooCommerce standard refresh
                jQuery(document.body).trigger('wc_fragment_refresh');
                jQuery(document.body).trigger('updated_cart_totals');

                // 🔥 Royal Addons mini cart refresh (CRITICAL)
                jQuery(document.body).trigger('added_to_cart');
                jQuery(document.body).trigger('removed_from_cart');

                // ⏳ Small delay ensures all plugins update
                setTimeout(function () {
                    window.location.href = redirectUrl;
                }, 400);
            });
        });

    }

    /**
     * INTERCEPT CLICK BEFORE YOUR EXISTING JS
     */
    document.addEventListener('click', function (e) {

        const target = e.target.closest('a#btns-locations');
        if (!target) return;

        const newLocation = target.getAttribute('title')?.trim()?.toLowerCase();
        const redirectUrl = target.getAttribute('href');
        const currentLocation = localStorage.getItem(locationKey);

        if (
            wc_loc_guard.cart_count > 0 &&
            currentLocation &&
            newLocation &&
            currentLocation !== newLocation
        ) {
            e.preventDefault();
            e.stopImmediatePropagation(); // 🔑 BLOCK OTHER HANDLERS
            showConflictPopup(currentLocation, newLocation, redirectUrl);
        }

    }, true); // 👈 CAPTURE MODE (MOST IMPORTANT)

});
