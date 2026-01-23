document.addEventListener('DOMContentLoaded', () => {
    const locationKey = 'selectedLocation'; // Key for localStorage
    const selectedLocation = localStorage.getItem(locationKey);

    // Ensure first letter is uppercase (for matching locations)
    const formattedLocation = selectedLocation
        ? selectedLocation.charAt(0).toUpperCase() + selectedLocation.slice(1).toLowerCase()
        : '';

    const priceElement = document.getElementById('custom-price-range'); // Target price wrapper
    const locationDropdown = document.querySelector('select[name="attribute_location"]');

    if (!locationDropdown || !priceElement || typeof locationPriceData === 'undefined' || !locationPriceData.prices) {
        console.warn('Required elements or data missing');
        return; // Exit if elements or data are missing
    }

    const { prices, currency } = locationPriceData; // Get price data and currency from PHP
    console.log('Location Data:', locationPriceData);

    // Function to update price based on selected location
    const updatePriceRange = (selectedLocation) => {
        if (!selectedLocation || !prices[selectedLocation]) {
            priceElement.innerHTML = '<span>No price available</span>';
            return;
        }

        const priceRange = prices[selectedLocation];
        console.log('Updating price for:', selectedLocation, priceRange);

        // Ensure min/max prices are valid numbers
        const minPrice = priceRange.min ? parseFloat(priceRange.min).toFixed(2) : '0.00';
        const maxPrice = priceRange.max ? parseFloat(priceRange.max).toFixed(2) : '0.00';

        // Update WooCommerce price format
//         priceElement.innerHTML = `<span class="woocommerce-Price-amount amount"><bdi>${currency}${minPrice}</bdi></span> – 
//                                   <span class="woocommerce-Price-amount amount"><bdi>${currency}${maxPrice}</bdi></span>`;
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
      updatedPriceHtml = "<span>No price available</span>";
    }

    priceElement.innerHTML = updatedPriceHtml;
    };

    // Initial load (if a location is preselected)
    updatePriceRange(formattedLocation || locationDropdown.value);

    // Update price on dropdown change
    locationDropdown.addEventListener('change', (event) => {
        updatePriceRange(event.target.value);
    });
});


// Entire Website 

document.addEventListener("DOMContentLoaded", function () {
    // Ensure productPriceRanges is defined
    if (typeof productPriceRanges === 'undefined') {
        console.error('productPriceRanges is not defined. Check if it is passed from PHP.');
        return;
    }

    const locationKey = 'selectedLocation'; // Key for localStorage
    const selectedLocation = localStorage.getItem(locationKey);

    // Ensure the location value is capitalized for matching
    const formattedLocation = selectedLocation
        ? selectedLocation.charAt(0).toUpperCase() + selectedLocation.slice(1).toLowerCase()
        : '';

    // Function to update the price ranges dynamically
    const updatePriceRanges = (location) => {
        if (!productPriceRanges || !location) {
            console.warn('Price ranges or location data is missing.');
            return;
        }

        Object.keys(productPriceRanges).forEach((productId) => {
            const priceRange = productPriceRanges[productId]?.[location];
            if (priceRange) {
                const priceElement = document.querySelector(`#custom-price-range-${productId}`);
                if (priceElement) {
                    priceElement.innerHTML = `${priceRange.currency}${priceRange.min.toFixed(2)} – ${priceRange.currency}${priceRange.max.toFixed(2)}`;
                }
            }
        });
    };

    // If a location is already selected, update the price ranges
    if (formattedLocation) {
        // console.log('Selected Location:', formattedLocation);
        updatePriceRanges(formattedLocation);
    } else {
        console.warn('No location selected. Prices will not update.');
    }

    // Listen for changes in the location dropdown and update prices
    const locationDropdown = document.querySelector('select[name="attribute_location"]');
    if (locationDropdown) {
        locationDropdown.addEventListener('change', (event) => {
            const newLocation = event.target.value;
            localStorage.setItem(locationKey, newLocation);

            // Ensure the new location is capitalized
            const formattedNewLocation = newLocation
                ? newLocation.charAt(0).toUpperCase() + newLocation.slice(1).toLowerCase()
                : '';

            updatePriceRanges(formattedNewLocation);
        });
    }
});