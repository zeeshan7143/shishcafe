// jQuery(function ($) {

//     console.log('PB JS loaded');

//     /* ===========================
//      * DEBUG: CHECK TABS & CONTENT
//      * =========================== */

//     console.log('Tabs:', $('.pb-tabs li').length);
//     console.log('Contents:', $('.pb-content').length);

//     $('.pb-content').hide();

//     $('.pb-tabs li').on('click', function () {

//         const tab = $(this).data('tab');
//         console.log('Tab clicked:', tab);

//         $('.pb-tabs li').removeClass('active');
//         $(this).addClass('active');

//         $('.pb-content').hide();
//         $('#pb-' + tab).show();
//     });

//     /* SHOW FIRST TAB */
//     $('.pb-tabs li:first').trigger('click');

// });

// jQuery(function ($) {

//     console.log('PB Phase 2 loaded');
//     console.log('PB DATA:', PB_DATA);
//     console.log('PB CHILD PRICES:', PB_DATA.child_prices);
//     /* ===========================
//      * LOCATION
//      * =========================== */
//     const location = localStorage.getItem('selectedLocation');
//     console.log('Selected location:', location);

//     if (!location) {
//         console.warn('PB: No location selected');
//         return;
//     }

//     console.log('PB child prices:', PB_DATA.child_prices);

//     /* ===========================
//      * SHOW PRICES PER PRODUCT
//      * =========================== */

//     $('.pb-content').each(function () {

//         const category = $(this).attr('id').replace('pb-', '');

//         $(this).find('.pb-item').each(function () {

//             const $item = $(this);
//             const pid = $item.data('id');

//             const price =
//                 PB_DATA.child_prices?.[category]?.[pid]?.[location]
//                 ?? PB_DATA.child_prices?.[category]?.[pid]?.['all']
//                 ?? null;

//             if (price === null) {
//                 $item.find('.pb-price')
//                     .text('Not available for this location')
//                     .css('color', 'red');
//                 console.warn(`No price for product ${pid} @ ${location}`);
//             } else {
//                 $item.find('.pb-price')
//                     .text('£' + parseFloat(price).toFixed(2));
//                 console.log(`Price OK: ${pid} = £${price}`);
//             }
//         });
//     });

//     /* ===========================
//      * TABS (SAFE)
//      * =========================== */

//     $('.pb-content').hide();

//     $('.pb-tabs li').on('click', function () {

//         const tab = $(this).data('tab');

//         $('.pb-tabs li').removeClass('active');
//         $(this).addClass('active');

//         $('.pb-content').hide();
//         $('#pb-' + tab).show();

//         console.log('Tab opened:', tab);
//     });

//     $('.pb-tabs li:first').trigger('click');
// });




// jQuery(function ($) {

//     console.log('PB Phase 3 updated loaded');
//     console.log('PB DATA:', PB_DATA);
//     console.log('PB CHILD PRICES:', PB_DATA.child_prices);
//     const location = localStorage.getItem('selectedLocation');
//     if (!location) {
//         alert('Location not selected');
//         return;
//     }
//     window.PB_MAIN_VARIATION_ID = PB.main_variations?.[location] || null;

//     if (!window.PB_MAIN_VARIATION_ID) {
//         console.warn('No main variation found for location:', location);
//     }
//     let globalExtraTotal = 0;

//     /* ===========================
//      * CATEGORY LOOP
//      * =========================== */
//     $('.pb-content').each(function () {

//         const $category = $(this);
//         const categoryKey = $category.data('category');
//         const freeLimit = parseInt($category.data('free'), 10);

//         let selectedCount = 0;
//         let paidCount = 0;
//         let categoryExtra = 0;

//         $category.find('.pb-item').each(function () {

//             const $item = $(this);
//             const pid = $item.data('id');

//             const price =
//                 PB_DATA.child_prices?.[categoryKey]?.[pid]?.[location]
//                 ?? PB_DATA.child_prices?.[categoryKey]?.[pid]?.['all']
//                 ?? null;

//             if (price === null) {
//                 $item.hide();
//                 return;
//             }

//             const numericPrice = parseFloat(price);
//             $item.data('price', numericPrice);

//             $item.find('input[type="checkbox"]').on('change', function () {

//                 if (this.checked) {
//                     selectedCount++;
//                     if (selectedCount <= freeLimit) {
//                         $item.removeClass('pb-paid').addClass('pb-free')
//                             .find('.pb-price').text('Included').show();
//                     } else {
//                         $item.removeClass('pb-free').addClass('pb-paid')
//                             .find('.pb-price').text('+£' + numericPrice.toFixed(2)).show();
//                         paidCount++;
//                         categoryExtra += numericPrice;
//                         globalExtraTotal += numericPrice;
//                     }
//                 } else {
//                     selectedCount--;
//                     if ($item.hasClass('pb-paid')) {
//                         paidCount--;
//                         categoryExtra -= numericPrice;
//                         globalExtraTotal -= numericPrice;
//                     }
//                     $item.removeClass('pb-free pb-paid')
//                         .find('.pb-price').hide().text('');
//                 }

//                 // Update per-tab extra
//                 $category.find('.pb-extra-tab').text('£' + categoryExtra.toFixed(2));

//                 // Update per-tab counter
//                 $category.find('.pb-counter')
//                     .text(`${selectedCount} selected (${paidCount} paid) + £${categoryExtra.toFixed(2)}`);

//                 // Update global total
//                 $('.pb-extra-total').text(globalExtraTotal.toFixed(2));

//                 console.log(`Category ${categoryKey}: selected=${selectedCount}, paid=${paidCount}, categoryExtra=${categoryExtra}, globalExtra=${globalExtraTotal}`);
//                 // ============================
//                 // COUNTER & MESSAGE UPDATE
//                 // ============================

//                 let counterText = '';
//                 let counterClass = '';
//                 let extraText = '';
//                 const $extraTab = $category.find('.pb-extra-tab');

//                 if (selectedCount === 0) {
//                     counterText = 'No selections required';
//                     counterClass = 'pb-neutral';
//                     $extraTab.hide();
//                 }
//                 else if (selectedCount < freeLimit) {
//                     const remaining = freeLimit - selectedCount;
//                     counterText = `${remaining} more required`;
//                     counterClass = 'pb-required';
//                     $extraTab.hide();
//                 }
//                 else if (selectedCount === freeLimit) {
//                     counterText = `${selectedCount} items selected`;
//                     counterClass = 'pb-complete';
//                     $extraTab.hide();
//                 }
//                 else {
//                     categoryExtra = parseFloat(categoryExtra) || 0;

//                     if (paidCount === 1) {
//                         counterText = `${selectedCount} items selected (with ${paidCount} extra)`;
//                     }
//                     else {
//                         counterText = `${selectedCount} items selected (with ${paidCount} extras)`;
//                     }
//                     counterClass = 'pb-extra';
//                     // Show extra price ONLY when extra exists
//                     $category.find('.pb-counter-text').text(counterText);
//                     let extraText = `(for ${paidCount} extra items)`;
//                     $extraTab
//                         .text('+£' + categoryExtra.toFixed(2) + ' ' + extraText)
//                         .show();
//                 }
//                 // Apply counter text + color
//                 $category.find('.pb-counter')
//                     .removeClass('pb-neutral pb-required pb-complete')
//                     .addClass(counterClass)
//                     .text(counterText);
//                 // end counter 

//             });

//         });
//     });

//     /* ===========================
//      * TABS
//      * =========================== */
//     $('.pb-content').hide();
//     $('.pb-tabs li').on('click', function () {
//         const tab = $(this).data('tab');
//         $('.pb-tabs li').removeClass('active');
//         $(this).addClass('active');
//         $('.pb-content').hide();
//         $('#pb-' + tab).show();
//     });
//     $('.pb-tabs li:first').trigger('click');
//     // Add to Cart 
//     $('#pb-add-cart').on('click', function () {
//         console.log('Add to cart clicked', window.PB_MAIN_VARIATION_ID);
//         if (!window.PB_MAIN_VARIATION_ID) {
//             alert('Please select location first');
//             return;
//         }

//         if (window.PB_BLOCK_ADD_TO_CART === true) {
//             alert('Please complete required selections');
//             return;
//         }

//         $.post(PB_DATA.ajax_url, {
//             action: 'pb_add_to_cart',
//             variation_id: PB_MAIN_VARIATION_ID,
//             extra_price: window.PB_GLOBAL_EXTRA || 0,
//             items: window.PB_SELECTED_ITEMS || []
//         }, function (res) {

//             if (res.success) {
//                 window.location.href = PB_DATA.cart_url;
//             } else {
//                 alert(res.data || 'Add to cart failed');
//             }

//         });

//     });


// });


jQuery(function ($) {

    console.log('==============================');
    console.log('PB JS Loaded');
    // console.log('PB_DATA:', PB_DATA);
    console.log('==============================');

    const location = localStorage.getItem('selectedLocation');
    console.log('Selected location from localStorage:', location);

    if (!location) {
        console.warn('❌ No location found in localStorage');
        return;
    }
    window.PB_MAIN_VARIATION_ID = PB_DATA.main_variations?.[location] || null;

    if (!window.PB_MAIN_VARIATION_ID) {
        console.warn('No main variation found for location:', location);
    }
    /* ===========================
     * GLOBAL STATE
     * =========================== */
    window.PB_GLOBAL_EXTRA = 0;
    window.PB_SELECTED_ITEMS = [];
    window.PB_BLOCK_ADD_TO_CART = false;

    /* ===========================
     * CATEGORY LOOP
     * =========================== */
    $('.pb-content').each(function () {

        const $category = $(this);
        const categoryKey = $category.data('category');
        const freeLimit = parseInt($category.data('free'), 10);

        console.group(`Category: ${categoryKey}`);
        console.log('Free limit:', freeLimit);

        let selectedCount = 0;
        let paidCount = 0;
        let categoryExtra = 0;

        const $counter = $category.find('.pb-counter');
        const $counterText = $category.find('.pb-counter-text');
        const $extraTab = $category.find('.pb-extra-tab');

        $category.find('.pb-item').each(function () {

            const $item = $(this);
            const pid = $item.data('id');

            const price =
                PB_DATA.child_prices?.[categoryKey]?.[pid]?.[location]
                ?? PB_DATA.child_prices?.[categoryKey]?.[pid]?.['all']
                ?? null;

            console.log(`Product ${pid} price for ${location}:`, price);

            if (price === null) {
                console.warn(`❌ Product ${pid} hidden (no price for location)`);
                $item.hide();
                return;
            }

            const numericPrice = parseFloat(price);
            $item.data('price', numericPrice);

            $item.find('input[type="checkbox"]').on('change', function () {

                console.group(`Toggle product ${pid}`);

                if (this.checked) {

                    selectedCount++;
                    window.PB_SELECTED_ITEMS.push(pid);

                    if (selectedCount <= freeLimit) {
                        $item
                            .removeClass('pb-paid')
                            .addClass('pb-free')
                            .find('.pb-price')
                            .text('Included')
                            .show();
                    } else {
                        paidCount++;
                        categoryExtra += numericPrice;
                        window.PB_GLOBAL_EXTRA += numericPrice;

                        $item
                            .removeClass('pb-free')
                            .addClass('pb-paid')
                            .find('.pb-price')
                            .text('+£' + numericPrice.toFixed(2))
                            .show();
                    }

                } else {

                    selectedCount--;
                    window.PB_SELECTED_ITEMS = window.PB_SELECTED_ITEMS.filter(id => id !== pid);

                    if ($item.hasClass('pb-paid')) {
                        paidCount--;
                        categoryExtra -= numericPrice;
                        window.PB_GLOBAL_EXTRA -= numericPrice;
                    }

                    $item
                        .removeClass('pb-free pb-paid')
                        .find('.pb-price')
                        .hide()
                        .text('');
                }

                /* ===========================
                 * COUNTER LOGIC
                 * =========================== */
                let counterText = '';
                let counterClass = '';

                if (selectedCount === 0) {
                    counterText = 'No selections required';
                    counterClass = 'pb-neutral';
                    $extraTab.hide();
                }
                else if (selectedCount < freeLimit) {
                    counterText = `${freeLimit - selectedCount} more required`;
                    counterClass = 'pb-required';
                    $extraTab.hide();
                }
                else if (selectedCount === freeLimit) {
                    counterText = `${selectedCount} items selected`;
                    counterClass = 'pb-complete';
                    $extraTab.hide();
                }
                else {
                    counterText =
                        paidCount === 1
                            ? `${selectedCount} items selected (with 1 extra)`
                            : `${selectedCount} items selected (with ${paidCount} extras)`;

                    counterClass = 'pb-extra';
                    $extraTab
                        .text(`+£${categoryExtra.toFixed(2)} (for ${paidCount} extra items)`)
                        .show();
                }

                $counter
                    .removeClass('pb-neutral pb-required pb-complete pb-extra')
                    .addClass(counterClass);

                $counterText.text(counterText);

                /* ===========================
                 * GLOBAL SUMMARY
                 * =========================== */
                $('.pb-extra-total').text(window.PB_GLOBAL_EXTRA.toFixed(2));

                window.PB_BLOCK_ADD_TO_CART = selectedCount < freeLimit;

                console.log({
                    selectedCount,
                    paidCount,
                    categoryExtra,
                    GLOBAL_EXTRA: window.PB_GLOBAL_EXTRA,
                    SELECTED_ITEMS: window.PB_SELECTED_ITEMS
                });

                console.groupEnd();
            });

        });

        console.groupEnd();
    });

    /* ===========================
     * TABS
     * =========================== */
    $('.pb-content').hide();
    $('.pb-tabs li').on('click', function () {
        const tab = $(this).data('tab');
        $('.pb-tabs li').removeClass('active');
        $(this).addClass('active');
        $('.pb-content').hide();
        $('#pb-' + tab).show();
    });
    $('.pb-tabs li:first').trigger('click');

    /* ===========================
     * ADD TO CART
     * =========================== */
    $('#pb-add-cart').on('click', function () {

        console.log('==============================');
        console.log('ADD TO CART CLICKED');
        console.log('PB_MAIN_VARIATION_ID:', window.PB_MAIN_VARIATION_ID);
        console.log('GLOBAL EXTRA:', window.PB_GLOBAL_EXTRA);
        console.log('SELECTED ITEMS:', window.PB_SELECTED_ITEMS);
        console.log('BLOCK ADD TO CART:', window.PB_BLOCK_ADD_TO_CART);
        console.log('==============================');

        if (!window.PB_MAIN_VARIATION_ID) {
            alert('❌ Variation ID not set. Location → variation mapping missing.');
            return;
        }

        if (window.PB_BLOCK_ADD_TO_CART) {
            alert('❌ Please complete required selections');
            return;
        }

        $.post(PB_DATA.ajax_url, {
            action: 'pb_add_to_cart',
            variation_id: window.PB_MAIN_VARIATION_ID,
            extra_price: window.PB_GLOBAL_EXTRA,
            items: window.PB_SELECTED_ITEMS
        }, function (res) {

            console.log('AJAX response:', res);

            if (res.success) {
                window.location.href = PB_DATA.cart_url;
            } else {
                alert(res.data || 'Add to cart failed');
            }
        });
    });

});

