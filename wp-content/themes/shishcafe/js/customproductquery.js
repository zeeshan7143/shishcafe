document.addEventListener('DOMContentLoaded', () => {
    const locationKey = 'selectedLocation';
    const selectedLocation = localStorage.getItem(locationKey);

    if (!selectedLocation) {
        console.warn('No selected location found');
        return;
    }

    const formattedLocation = selectedLocation.charAt(0).toUpperCase() + selectedLocation.slice(1).toLowerCase();

    // Find all shortcode instances
    document.querySelectorAll('.custom-product-woocommerce').forEach((shortcodeContainer) => {
        const shortcodeId = shortcodeContainer.id;
        const locationPriceData = window[shortcodeId + '_data'];

        if (!locationPriceData || !locationPriceData.prices) {
            console.warn(`No price data found for shortcode ${shortcodeId}`);
            return;
        }

        const { prices, currency } = locationPriceData;

        shortcodeContainer.querySelectorAll('.custom-product').forEach((product) => {
            const productId = product.getAttribute('data-product-id');
//             const priceWrapper = product.querySelector('.custom-product-price');
            const priceWrapper = product.querySelector('.custom-product-prices');

            if (!productId || !priceWrapper || !prices[productId]) {
                priceWrapper.innerHTML = '<span>No price available</span>';
                return;
            }

            if (!prices[productId][formattedLocation]) {
                priceWrapper.innerHTML = '<span>No price available</span>'; 
                return;
            }

            const priceRange = prices[productId][formattedLocation];

            if (!Array.isArray(priceRange) || priceRange.length === 0) {
                priceWrapper.innerHTML = '<span>No price available</span>';
                return;
            }

            const minPrice = Math.min(...priceRange).toFixed(2);
            const maxPrice = Math.max(...priceRange).toFixed(2);

            let updatedPriceHtml = "";

            if (minPrice && maxPrice && minPrice !== maxPrice) {
                updatedPriceHtml = `<span class="woocommerce-Price-amount amount">
                                        <bdi><span class="woocommerce-Price-currencySymbol">${currency}</span>${minPrice}</bdi>
                                    </span> – 
                                    <span class="woocommerce-Price-amount amount">
                                        <bdi><span class="woocommerce-Price-currencySymbol">${currency}</span>${maxPrice}</bdi>
                                    </span>`;
            } else if (minPrice) {
                updatedPriceHtml = `<span class="woocommerce-Price-amount amount">
                                        <bdi><span class="woocommerce-Price-currencySymbol">${currency}</span>${minPrice}</bdi>
                                    </span>`;
            } else {
                updatedPriceHtml = '<span>No price available</span>';
            }

            priceWrapper.innerHTML = updatedPriceHtml;
        });
    });
});

// === Sync localStorage and Cookie for Location ===
document.addEventListener('DOMContentLoaded', () => {
    const locationKey = 'selectedLocation';
    const cookieName = 'selectedLocationCookie';

    // Helper: set cookie
    function setCookie(name, value, days = 7) {
        const d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = `${name}=${value}; expires=${d.toUTCString()}; path=/`;
    }

    // Helper: get cookie
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
    }

    // Sync localStorage → cookie
    function syncCookieWithLocalStorage() {
        const stored = localStorage.getItem(locationKey);
        if (stored && stored !== getCookie(cookieName)) {
            setCookie(cookieName, stored);
        }
    }

    // Initial sync
    syncCookieWithLocalStorage();

    // When location changes in localStorage, update cookie and reload
    window.addEventListener('storage', (event) => {
        if (event.key === locationKey) {
            syncCookieWithLocalStorage();
            location.reload(); // reload page so PHP shortcode picks new tag
        }
    });
});
