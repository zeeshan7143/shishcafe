// Add tags and id in li 
// document.addEventListener("DOMContentLoaded", function () {
//     function updateProductAttributes() {
//         let products = document.querySelectorAll("li.product");

//         products.forEach(function (product) {
//             let classList = product.classList;
//             // Extract product tag classes
//             let tagClasses = Array.from(classList)
//                 .filter(cls => cls.startsWith("product_tag-"))
//                 .map(cls => cls.replace("product_tag-", "").replace(/-/g, " "));

//             if (tagClasses.length > 0) {
//                 product.setAttribute("data-category", tagClasses.join(", "));
//             }

//             // Extract product ID
//             let productIdClass = Array.from(classList).find(cls => cls.startsWith("post-"));
//             if (productIdClass) {
//                 let productId = productIdClass.replace("post-", "");
//                 product.setAttribute("data-product-id", productId);
//             }
//         });
//     }
//     // Run once on initial load
//     updateProductAttributes();
//     // **Watch for product list updates (AJAX Loaded Products)**
//     const observer = new MutationObserver(updateProductAttributes);
//     observer.observe(document.body, { childList: true, subtree: true });
// });


// store location in local storage 
// jQuery(document).ready(function ($) {
//     const locationKey = 'selectedLocation'; // Key for localStorage
//     const defaultLocation = 'Rochdale'; // Default location value

//     // Function to set the location dropdown value
//     function updateLocation(value) {
//         const locationSelect = jQuery('select[name="attribute_location"]'); // Replace with your dropdown name
//         if (locationSelect.length) {
//             locationSelect.val(value).change(); // Set value and trigger change event
//         }
//     }

//     // On page load, check if a location is already set in localStorage
//     if (!localStorage.getItem(locationKey)) {
//         // If no location is stored, set the default location
//         localStorage.setItem(locationKey, defaultLocation.toLocaleLowerCase());
//         updateLocation(defaultLocation.toLocaleLowerCase());
//     } else {
//         // If a location is already stored, update the dropdown with the saved value
//         const savedLocation = localStorage.getItem(locationKey);
//         updateLocation(savedLocation.toLocaleLowerCase());
//     }

//     jQuery('a#btns-locations').on('click', function (e) {
//         e.preventDefault(); // Prevent default link behavior

//         const selectedLocation = jQuery(this).attr('title').trim(); // Get location from the title attribute
//         const redirectUrl = jQuery(this).attr('href'); // Get the URL

//         if (selectedLocation) {
//             console.log('Selected for Location:', selectedLocation);

//             // Save the selected location in localStorage
//             localStorage.setItem(locationKey, selectedLocation.toLocaleLowerCase());

//             // Redirect to the new page
//             window.location.href = redirectUrl;
//         } else {
//             console.log('No location found in title attribute.');
//         }
//     });

// });

jQuery(document).ready(function ($) {
    const locationKey = 'selectedLocation'; // Key for localStorage
    const defaultLocation = 'Rochdale'; // Default location value

    // 🔑 Function to sync localStorage → cookie
    function syncLocationToCookie(value) {
        if (value) {
            document.cookie = `${locationKey}=${value}; path=/; max-age=86400`; // Cookie valid 1 day
        }
    }

    // Function to set the location dropdown value
    function updateLocation(value) {
        const locationSelect = jQuery('select[name="attribute_location"]'); // Replace with your dropdown name
        if (locationSelect.length) {
            locationSelect.val(value).change(); // Set value and trigger change event
        }
    }

    // On page load, check if a location is already set in localStorage
    if (!localStorage.getItem(locationKey)) {
        // If no location is stored, set the default location
        localStorage.setItem(locationKey, defaultLocation.toLocaleLowerCase());
        updateLocation(defaultLocation.toLocaleLowerCase());
        syncLocationToCookie(defaultLocation.toLocaleLowerCase()); // 🍪 sync on first load
    } else {
        // If a location is already stored, update the dropdown with the saved value
        const savedLocation = localStorage.getItem(locationKey);
        updateLocation(savedLocation.toLocaleLowerCase());
        syncLocationToCookie(savedLocation.toLocaleLowerCase()); // 🍪 sync on load
    }

    jQuery('a#btns-locations').on('click', function (e) {
        e.preventDefault(); // Prevent default link behavior

        const selectedLocation = jQuery(this).attr('title').trim(); // Get location from the title attribute
        const redirectUrl = jQuery(this).attr('href'); // Get the URL

        if (selectedLocation) {
            console.log('Selected for Location:', selectedLocation);

            // Save the selected location in localStorage
            localStorage.setItem(locationKey, selectedLocation.toLocaleLowerCase());

            // 🍪 Also set a cookie for PHP to read on the server
            syncLocationToCookie(selectedLocation.toLocaleLowerCase());

            // Redirect to the new page
            window.location.href = redirectUrl;
        } else {
            console.log('No location found in title attribute.');
        }
    });
});


// Update DropDown Location Value 
// jQuery(document).ready(function ($) {
//     const locationKey = 'selectedLocation'; // Key used to store the value in localStorage
//     var savedLocation = localStorage.getItem(locationKey); // Get the saved location value

//     if (savedLocation) {
//         var savedLocationLower = savedLocation.trim().toLowerCase();

//         // Target both select dropdowns
//         var locationDropdowns = jQuery('select[name="attribute_location"], select[name="ppom[fields][location]"], select[name="ppom[fields][location_ppom]"]');
//         //         var locationDropdowns = jQuery('select[name="attribute_location"]');

//         locationDropdowns.each(function () {
//             var dropdown = $(this);

//             dropdown.find('option').each(function () {
//                 var optionText = $(this).text().trim().toLowerCase();

//                 if (optionText === savedLocationLower) {
//                     dropdown.val($(this).val()).trigger('change'); // Set & trigger change event
//                     dropdown.prop('disabled', true); // Disable selection
//                 }
//             });
//         });
//     }
// });
jQuery(document).ready(function ($) {
    const locationKey = 'selectedLocation';
    const savedLocation = (localStorage.getItem(locationKey) || '').trim().toLowerCase();

    if (!savedLocation) return;

    $('select[name="attribute_location"], select[name="ppom[fields][location]"], select[name="ppom[fields][location_ppom]"]').each(function () {
        const $dropdown = $(this);
        let matched = false;

        $dropdown.find('option').each(function () {
            if ($(this).text().trim().toLowerCase() === savedLocation) {
                $dropdown.val($(this).val()).trigger('change');
                matched = true;
                return false; // stop once matched
            }
        });

        // disable only if match found
        if (matched) {
            $dropdown.prop('disabled', true);
        }
    });
});


// Show product base on Location 
// jQuery(document).ready(function ($) {
//     const locationKey = 'selectedLocation'; // Key used in localStorage
//     const selectedCategory = localStorage.getItem(locationKey); // Get stored tag/category

//     if (selectedCategory) {
//         jQuery('.products .product').each(function () {
//             // Get the data-category attribute from the <li> element
//             var productTags = jQuery(this).attr('data-category').toLowerCase();

//             // Debugging: Check the tags in the console
//             // console.log('Product Tags:', productTags);

//             // Hide products if the selected tag doesn't match
//             if (!productTags || !productTags.includes(selectedCategory)) {
//                 jQuery(this).hide(); // Hide products that don't match
//             }
//         });
//     }
// });
// 
// 
//  Variations for PPom Value According to Variations from products  
jQuery(document).ready(function ($) {
    if (!jQuery('body').hasClass('single-product')) {
        return; // Stop script if not on product page
    }

    // Function to handle dropdown value sync
    function syncDropdown(sourceId, targetId) {
        jQuery('#' + sourceId).on('change', function () {
            var selectedValue = jQuery(this).val(); // Get selected value
            jQuery('#' + targetId).val(selectedValue).trigger('change'); // Set value in target dropdown
        });
        jQuery('#' + targetId).prop('disabled', true);
        // 🔹 Hide the source dropdown (Optional: Uncomment the next line to hide) and label
        jQuery('#' + targetId).prev('label').hide();
        jQuery('#' + targetId).hide();
    }

    // Define the dropdown mappings
    var dropdownMappings = {
        "type": "type_of_wraps",
        "type": "serving_with",
        "choice-of-serving": "serving_with_raps",
		"quantity":"choose_donut_quantity",
		
    };

    // Apply sync function to each mapping
    $.each(dropdownMappings, function (source, target) {
        syncDropdown(source, target);
    });
});

//  Variations for PPom Value According to Variations from products   for Pizza
jQuery(document).ready(function ($) {
    if (!jQuery('body').hasClass('single-product')) {
        return; // Stop script if not on product page
    }

    // Function to handle dropdown value sync with transformation
    function syncDropdown(sourceId, targetId) {
        $('#' + sourceId).on('change', function () {
            var selectedText = $(this).find("option:selected").text().trim(); // Get selected text

            // Transform the text (e.g., replace " with inch)
            var transformedValue = selectedText.replace('"', ' inch');

            // Find the matching option in target dropdown and set it
            $('#' + targetId + ' option').each(function () {
                if ($(this).text().trim() === transformedValue) {
                    $(this).prop('selected', true).trigger('change');
                }
            });
        });

        $('#' + targetId).prop('disabled', true);
		$('#' + targetId).prev('label').hide();
        $('#' + targetId).hide();
    }

    // Define the dropdown mappings
    var dropdownMappings = {
        "size": "get_size",
		"size":"get_size_3",
    };

    // Apply sync function to each mapping
    $.each(dropdownMappings, function (source, target) {
        syncDropdown(source, target);
    });
});

// SHow phone number according to location in header
jQuery(document).ready(function ($) {
    const locationKey = 'selectedLocation';
    const selectedLocation = localStorage.getItem(locationKey);

    const phoneTextField = $('#phone-number-field .elementor-icon-list-text');
    const addressTextField = $('#address-field .elementor-icon-list-text');
    const timingTextField = $('#timing-field .elementor-icon-list-text');
    // Default Values Rochdale 
    let phoneText = '01706-666601';
    let addressText = '31-33 tweedale street Rochdale </br> Ol11 1hh';
    let timingText = 'Monday – Friday: 10am – 10pm<br> Sunday: 11am – 9pm';

    if (selectedLocation === 'oldham') {
        phoneText = '0161 222 7860';
        addressText = '173-175 Lees Road, Oldham </br> OL4 1JP';
        timingText = 'Sunday – Thursday: 12pm – 11pm<br> Friday & Saturday: 12pm – 12am';
    } else if (selectedLocation === 'manchester') {
        phoneText = '0161 394 0006';
        addressText = '70 Bury Old Road Cheetham Hill, Manchester </br> M8 5B8';
        timingText = 'Sunday – Thursday: 12pm – 1am<br> Friday & Saturday: 12pm – 2am';
    }
	    else if (selectedLocation === 'stockport') {
        phoneText = '0161 394 0777';
        addressText = '587B Stockport Road, Longsight </br> Manchester, M13 ORX';
        timingText = 'Sunday – Thursday: 12pm – 1am<br> Friday & Saturday: 12pm – 2am';
    }

//     phoneTextField.html(phoneText);
//     addressTextField.html(addressText);
//     timingTextField.html(timingText);
});
