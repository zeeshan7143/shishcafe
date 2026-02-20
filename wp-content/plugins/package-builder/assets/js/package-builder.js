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

    // Debug logging control
    const PB_DEBUG = false; // Set to true for detailed debugging
    
    const log = function(message, data) {
        if (!PB_DEBUG) return;
        if (data !== undefined) {
            console.log('[PB]', message, data);
        } else {
            console.log('[PB]', message);
        }
    };

    const logWarning = function(message, data) {
        if (data !== undefined) {
            console.warn('[PB WARNING]', message, data);
        } else {
            console.warn('[PB WARNING]', message);
        }
    };

    const logError = function(message, data) {
        if (data !== undefined) {
            console.error('[PB ERROR]', message, data);
        } else {
            console.error('[PB ERROR]', message);
        }
    };

    const $packageSelector = $('#pb-package-selector');
    const $builderContainer = $('#pb-builder-container');

    function goBackToPackages() {
        if ($packageSelector.length) {
            $packageSelector.show();
        }
        $builderContainer.empty();
        if ($packageSelector.length) {
            $('html, body').animate({ scrollTop: $packageSelector.offset().top - 20 }, 300);
        }
    }

    function initPackageBuilder(PB_DATA) {

        const location = localStorage.getItem('selectedLocation');
        log('Selected location from localStorage:', location);

        if (!location) {
            logWarning('No location found in localStorage');
            return;
        }

        window.PB_MAIN_VARIATION_ID = PB_DATA?.main_variations?.[location] || null;
        log('Main variation ID set', window.PB_MAIN_VARIATION_ID);

        if (!window.PB_MAIN_VARIATION_ID) {
            logWarning('No main variation found for location', location);
        }

        /* ===========================
         * RAMZAN PACKAGES
         * =========================== */
        if (PB_DATA?.is_ramzan) {
            log('Processing Ramzan package');
            logWarning('Ramzan package console_log data:', PB_DATA.console_log, 'debug');

            const $builder = $('#package-builder.pb-ramzan');
            if (!$builder.length) return;

            $builder.find('.pb-ramzan-person').each(function () {
                const $category = $(this);
                const categoryKey = $category.data('category');
                log('Processing Ramzan person option:', categoryKey);

                $category.find('.pb-ramzan-item').each(function () {
                    const $item = $(this);
                    const pid = $item.data('id');

                    const price =
                        PB_DATA.child_prices?.[categoryKey]?.[pid]?.[location]
                        ?? PB_DATA.child_prices?.[categoryKey]?.[pid]?.['all']
                        ?? null;

                    if (price === null) {
                        // don't hide — show as N/A so user can still select
                        $item.find('.pb-price').text('N/A');
                        return;
                    }

                    $item.find('.pb-price').text('£' + parseFloat(price).toFixed(2));
                });
            });

            $builder.find('.pb-content').hide();

            function requiredForContent($content) {
                if (!$content || !$content.length) return 0;
                const free = parseInt($content.data('free'), 10);
                if (!isNaN(free) && free > 0) return free;
                const mixFree = parseInt($content.data('mix-free'), 10);
                if (!isNaN(mixFree) && mixFree > 0) return mixFree;
                let sum = 0;
                $content.find('.pb-subgroup').each(function () {
                    sum += parseInt($(this).data('free'), 10) || 0;
                });
                return sum;
            }

            function selectedInContent($content) {
                if (!$content || !$content.length) return 0;
                return $content.find('input[type="checkbox"]:checked').length;
            }

            $builder.find('.pb-tabs li').on('click', function () {
                const $all = $builder.find('.pb-tabs li');
                const $active = $all.filter('.active');
                const activeIdx = $all.index($active);
                const $target = $(this);
                const targetIdx = $all.index($target);

                // if moving forward, ensure current tab requirement met
                if (targetIdx > activeIdx) {
                    const currTab = $active.data('tab');
                    const currId = (typeof currTab === 'string' && currTab.indexOf('pb-') === 0) ? currTab : 'pb-' + currTab;
                    const $currContent = $builder.find('#' + currId);
                    const req = requiredForContent($currContent);
                    const sel = selectedInContent($currContent);
                    if (req > 0 && sel < req) {
                        const tabLabel = $active.data('base-label') || $active.text();
                        showTabError((tabLabel || 'This tab') + ' requires ' + req + ' selections.');
                        return;
                    }
                }

                const tab = $(this).data('tab');
                const tabId = (typeof tab === 'string' && tab.indexOf('pb-') === 0) ? tab : 'pb-' + tab;
                $builder.find('.pb-tabs li').removeClass('active');
                $(this).addClass('active');
                $builder.find('.pb-content').hide();
                $builder.find('#' + tabId).show();

                // For Ramzan packages, extract persons count from tab (ramzan-2 or ramzan-8)
                if (PB_DATA?.is_ramzan) {
                    const tabIdStr = String(tabId);
                    if (tabIdStr.includes('ramzan-2')) {
                        window.PB_PERSONS_COUNT = 2;
                    } else if (tabIdStr.includes('ramzan-8')) {
                        window.PB_PERSONS_COUNT = 8;
                    }
                    console.log('Ramzan persons set to:', window.PB_PERSONS_COUNT);
                }
            });
            $builder.find('.pb-tabs li:first').trigger('click');

            // Back to packages button for ramzan only (no step nav there)
            if ($(document).find('#pb-back-to-packages').length === 0) {
                const $backBtn = $('<button id="pb-back-to-packages" class="pb-btn" style="margin-right:8px;">Back to packages</button>');
                $builder.find('.pb-footer').prepend($backBtn);
                $backBtn.on('click', function () {
                    goBackToPackages();
                });
            }

            // Make ramzan items selectable
            $builder.find('.pb-ramzan-item').each(function () {
                const $item = $(this);
                const $checkbox = $item.find('input.pb-ramzan-checkbox');
                // toggle selection class on change
                $checkbox.on('change', function (e) {
                    if ($(this).is(':checked')) {
                        $item.addClass('selected');
                        $item.find('.pb-included-badge').show();
                    } else {
                        $item.removeClass('selected');
                        $item.find('.pb-included-badge').hide();
                    }
                });

                // clicking the item toggles the checkbox (except when clicking the checkbox itself)
                $item.on('click', function (e) {
                    if ($(e.target).is('input') || $(e.target).closest('label.pb-item-select').length) return;
                    $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
                });
            });

            function getActivePersonItems() {
                const activeTab = $builder.find('.pb-tabs li.active').data('tab');
                if (!activeTab) return [];
                const tabId = (typeof activeTab === 'string' && activeTab.indexOf('pb-') === 0) ? activeTab : 'pb-' + activeTab;
                const $content = $builder.find('#' + tabId);
                const items = [];
                const $li = $builder.find('.pb-tabs li.active');
                const tabLabel = $li.data('base-label') || $li.text();
                $content.find('input.pb-ramzan-checkbox:checked').each(function () {
                    const pid = parseInt($(this).val(), 10) || 0;
                    if (pid) {
                        items.push({ id: pid, tab: tabLabel || 'Selected Items', subgroup: '' });
                    }
                });
                return items;
            }

            $builder.find('#pb-add-cart').on('click', function () {
                if (!window.PB_MAIN_VARIATION_ID) {
                    logError('Variation ID not set');
                    alert('❌ Variation ID not set. Location → variation mapping missing.');
                    return;
                }

                const selected = getActivePersonItems();
                if (!selected.length) {
                    alert('Please select at least one item for this package.');
                    return;
                }

                // For Ramzan packages, use the base price (no calculation needed)
                const packagePrice = window.PB_RAMZAN_BASE_PRICE || 0;
                const personsCount = window.PB_PERSONS_COUNT || 2;

                log('Ramzan add to cart', { packagePrice, personsCount, itemsCount: selected.length });

                $.post(PB_CONFIG.ajax_url, {
                    action: 'pb_add_to_cart',
                    variation_id: window.PB_MAIN_VARIATION_ID,
                    extra_price: 0,
                    package_price: packagePrice,
                    persons: personsCount,
                    items: selected
                }, function (res) {
                    if (res.success) {
                        window.location.href = PB_CONFIG.cart_url;
                    } else {
                        logError('Add to cart failed', res.data);
                        alert(res.data || 'Add to cart failed');
                    }
                });
            });

            return;
        }

        /* ===========================
         * GLOBAL STATE
         * =========================== */
        window.PB_GLOBAL_EXTRA = 0;
        window.PB_SELECTED_ITEMS = [];
        window.PB_BLOCK_ADD_TO_CART = false;

        const $builder = $('#package-builder');
        if (!$builder.length) return;

        function getTabLabelForContent($content) {
            if (!$content || !$content.length) return '';
            const id = $content.attr('id') || '';
            const key = id.replace(/^pb-/, '');
            if (!key) return '';
            const $li = $builder.find('.pb-tabs li[data-tab="' + key + '"]');
            if (!$li.length) return '';
            return $li.data('base-label') || $li.text();
        }

        function getItemHeading($item) {
            const $content = $item.closest('.pb-content');
            const tabLabel = getTabLabelForContent($content);
            const $sub = $item.closest('.pb-subgroup, .pb-subsection');
            const subLabel = $sub.length ? ($sub.find('.pb-subgroup-title').first().text().trim()) : '';
            return { tab: tabLabel || 'Selected Items', subgroup: subLabel || '' };
        }

        function addSelectedItem(pid, heading) {
            window.PB_SELECTED_ITEMS.push({ id: pid, tab: heading.tab || '', subgroup: heading.subgroup || '' });
        }

        function removeSelectedItem(pid) {
            let removed = false;
            window.PB_SELECTED_ITEMS = window.PB_SELECTED_ITEMS.filter(function (entry) {
                if (removed) return true;
                if (entry && entry.id === pid) {
                    removed = true;
                    return false;
                }
                return true;
            });
        }

        function calculatePackageTotal() {
            // For Ramzan packages, use the base package price without calculation
            if (PB_DATA?.is_ramzan) {
                // Ramzan packages use a fixed price per person count
                const persons = parseInt(window.PB_PERSONS_COUNT, 10) || 1;
                const basePrice = window.PB_RAMZAN_BASE_PRICE || 0;
                const total = basePrice;
                window.PB_PACKAGE_TOTAL = total;
                $builder.find('.pb-package-total').text(total.toFixed(2));
                return;
            }

            // For regular packages, calculate from selected items
            const persons = parseInt(window.PB_PERSONS_COUNT, 10) || 1;
            const pricing = PB_DATA?.package_pricing || {};
            let base = 0;

            $builder.find('.pb-item input[type="checkbox"]:checked').each(function () {
                const type = $(this).closest('.pb-item').data('type');
                if (type && pricing[type] !== undefined) {
                    base += parseFloat(pricing[type]) || 0;
                }
            });

            const total = base * persons;
            window.PB_PACKAGE_TOTAL = total;
            $builder.find('.pb-package-total').text(total.toFixed(2));
        }

        // global helpers for tab error messaging (used in multiple handlers)
        window.clearTabError = function () {
            const $te = $builder.find('.pb-tab-error');
            if ($te.length) {
                $te.hide().text('');
            }
            $builder.find('.pb-tabs li').removeClass('pb-tab-error-active');
        };

        window.showTabError = function (msg) {
            let $te = $builder.find('.pb-tab-error');
            if ($te.length === 0) {
                $te = $('<div class="pb-tab-error" style="color:#d63638;margin-top:8px;display:none;"></div>');
                $builder.find('.pb-tabs').after($te);
            }
            $te.text(msg).show();
            $builder.find('.pb-tabs li.active').addClass('pb-tab-error-active');
        };

        // Step navigation: Prev / Next buttons beneath builder footer
        (function setupStepNavigation() {
            // insert nav after footer if not present
            if ($builder.find('#pb-step-nav').length === 0) {
                const $nav = $('<div id="pb-step-nav" class="pb-step-nav" style="display:flex;gap:8px;margin-top:12px;align-items:center;"></div>');
                $nav.append('<button type="button" id="pb-prev-step" class="pb-btn">Prev</button>');
                $nav.append('<button type="button" id="pb-next-step" class="pb-btn">Next</button>');
                // Move the add to cart button from footer to step nav
                const $addCartBtn = $builder.find('#pb-add-cart').detach();
                $nav.append($addCartBtn);
                $builder.find('.pb-footer').after($nav);
            }

            // inline tab error element (hidden by default)
            if ($builder.find('.pb-tab-error').length === 0) {
                const $err = $('<div class="pb-tab-error" style="color:#d63638;margin-top:8px;display:none;"></div>');
                $builder.find('.pb-tabs').after($err);
            }
            const $tabError = $builder.find('.pb-tab-error');

            // use global showTabError / clearTabError (defined above)

            const $prev = $builder.find('#pb-prev-step');
            const $next = $builder.find('#pb-next-step');

            function getContentElementForTab(tab) {
                const tabId = (typeof tab === 'string' && tab.indexOf('pb-') === 0) ? tab : 'pb-' + tab;
                return $builder.find('#' + tabId);
            }

            function computeRequired($content) {
                if (!$content || !$content.length) return 0;
                const free = parseInt($content.data('free'), 10);
                if (!isNaN(free) && free > 0) return free;
                // For mix: add mix qty + subgroup qty (not just one or the other)
                let sum = 0;
                const mixFree = parseInt($content.data('mix-free'), 10);
                if (!isNaN(mixFree) && mixFree > 0) {
                    sum += mixFree;
                }
                // sum subgroup requirements
                $content.find('.pb-subgroup').each(function () {
                    const v = parseInt($(this).data('free'), 10) || 0;
                    sum += v;
                });
                return sum;
            }

            function countSelectedInContent($content) {
                if (!$content || !$content.length) return 0;
                return $content.find('input[type="checkbox"]:checked').length;
            }

            function updateTabLabels() {
                $builder.find('.pb-tabs li').each(function () {
                    const $li = $(this);
                    const tab = $li.data('tab');
                    const $content = getContentElementForTab(tab);
                    const req = computeRequired($content) || 0;
                    const baseLabel = $li.data('base-label') || $li.text();
                    if (!$li.data('base-label')) $li.data('base-label', baseLabel);
                    $li.text(baseLabel + (req > 0 ? ' (' + req + ' Items required)' : ''));
                });
            }

            function updateNavState() {
                const $activeLi = $builder.find('.pb-tabs li.active');
                if (!$activeLi.length) return;
                const tab = $activeLi.data('tab');
                const $content = getContentElementForTab(tab);
                const req = computeRequired($content);
                const sel = countSelectedInContent($content);

                // Prev button: enable when not first tab, or when first tab but Back-to-packages exists
                const $allTabs = $builder.find('.pb-tabs li');
                const idx = $allTabs.index($activeLi);
                const canBack = $packageSelector.length > 0;
                const prevEnabled = (idx > 0) || (idx === 0 && canBack);
                $prev.prop('disabled', !prevEnabled);
                // Update Prev label when on first tab to indicate Back-to-packages
                if (idx === 0 && canBack) {
                    $prev.text('Back to packages');
                } else {
                    $prev.text('Prev');
                }

                // Next button: keep clickable so handler can show inline error when blocked
                $next.prop('disabled', false);

                // If last tab, hide Next and show Add to cart in step nav
                if (idx === $allTabs.length - 1) {
                    $next.hide();
                    $builder.find('#pb-step-nav').find('.pb-add-cart-btn').show();
                } else {
                    $next.show();
                    $builder.find('#pb-step-nav').find('.pb-add-cart-btn').hide();
                }
            }

            // tab click updates nav state
            $builder.find('.pb-tabs').on('click', 'li', function () {
                setTimeout(function () {
                    // update nav state; do not auto-clear errors here
                    updateNavState();
                }, 50);
            });

            // checkbox changes update nav state
            $builder.on('change', 'input[type="checkbox"]', function () {
                setTimeout(function () {
                    updateNavState();
                    // clear error only if current tab's requirement is satisfied
                    const $activeLi = $builder.find('.pb-tabs li.active');
                    if ($activeLi.length) {
                        const tab = $activeLi.data('tab');
                        const $content = getContentElementForTab(tab);
                        const req = computeRequired($content);
                        const sel = countSelectedInContent($content);
                        if (req > 0 && sel >= req) {
                            clearTabError();
                        }
                    }
                }, 50);
            });

            $prev.on('click', function () {
                const $tabs = $builder.find('.pb-tabs li');
                const $active = $tabs.filter('.active');
                const idx = $tabs.index($active);
                if (idx > 0) {
                    $tabs.eq(idx - 1).trigger('click');
                } else {
                    // if at first tab, treat Prev as Back to packages
                    if ($packageSelector.length) {
                        goBackToPackages();
                    }
                }
            });

            $next.on('click', function () {
                const $tabs = $builder.find('.pb-tabs li');
                const $active = $tabs.filter('.active');
                const idx = $tabs.index($active);
                if (idx < $tabs.length - 1) {
                    // validate current tab before moving forward
                    const $curr = $tabs.eq(idx);
                    const $currContent = getContentElementForTab($curr.data('tab'));
                    const req = computeRequired($currContent);
                    const sel = countSelectedInContent($currContent);
                    if (req > 0 && sel < req) {
                        const tabLabel = $curr.data('base-label') || $curr.text();
                        showTabError((tabLabel || 'This tab') + ' requires ' + req + ' selections.');
                        return;
                    }
                    $tabs.eq(idx + 1).trigger('click');
                } else {
                    // last tab — no forward action; leave Add to cart visible
                }
            });

            // initialize labels and nav state
            updateTabLabels();
            updateNavState();
        })();

        // No separate Back button for non-ramzan: Prev handles back-to-packages on first tab

        /* ===========================
         * CATEGORY LOOP
         * =========================== */
        function recalcBlockAddToCart() {
            let block = false;

            $builder.find('.pb-subgroup').each(function () {
                const required = parseInt($(this).data('free'), 10) || 0;
                const sel = $(this).data('selected') || 0;
                if (required > 0 && sel < required) {
                    block = true;
                    return false;
                }
            });

            if (!block) {
                $builder.find('.pb-content.pb-combined').each(function () {
                    const required = parseInt($(this).data('free'), 10) || 0;
                    const sel = $(this).data('selected') || 0;
                    if (required > 0 && sel < required) {
                        block = true;
                        return false;
                    }
                });
            }

            if (!block) {
                $builder.find('.pb-content[data-mix="1"]').each(function () {
                    const required = parseInt($(this).data('mix-free'), 10) || 0;
                    const sel = $(this).data('mix-selected') || 0;
                    if (required > 0 && sel < required) {
                        block = true;
                        return false;
                    }
                });
            }

            window.PB_BLOCK_ADD_TO_CART = block;
        }

        $builder.find('.pb-content.pb-combined').each(function () {

            const $category = $(this);
            const categoryKey = $category.data('category');
            const freeLimit = parseInt($category.data('free'), 10) || 0;

            log(`Combined category initialized: ${categoryKey}, required: ${freeLimit}`);

            let selectedCount = 0;
            let paidCount = 0;
            let categoryExtra = 0;

            const $counter = $category.find('.pb-category-counter .pb-counter');
            const $counterText = $category.find('.pb-category-counter .pb-counter-text');
            const $extraTab = $category.find('.pb-category-counter .pb-extra-tab');

            $category.find('.pb-item').each(function () {

                const $item = $(this);
                const pid = $item.data('id');

                const price =
                    PB_DATA.child_prices?.[categoryKey]?.[pid]?.[location]
                    ?? PB_DATA.child_prices?.[categoryKey]?.[pid]?.['all']
                    ?? null;

                if (price === null) {
                    $item.hide();
                    return;
                }

                const numericPrice = parseFloat(price);
                $item.data('price', numericPrice);

                $item.find('input[type="checkbox"]').on('change', function () {

                    if (this.checked) {

                        selectedCount++;
                        addSelectedItem(pid, getItemHeading($item));

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
                        removeSelectedItem(pid);

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

                    let counterText = '';
                    let counterClass = '';

                    if (selectedCount === 0) {
                        counterText = `${freeLimit} selections required`;
                        counterClass = 'pb-required';
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

                    // Disable unchecked items once free limit is reached
                    $category.find('.pb-item').each(function () {
                        const $currentItem = $(this);
                        const $checkbox = $currentItem.find('input[type="checkbox"]');
                        const isChecked = $checkbox.is(':checked');

                        if (selectedCount >= freeLimit && !isChecked) {
                            $checkbox.prop('disabled', true);
                            $currentItem.addClass('pb-disabled');
                        } else {
                            $checkbox.prop('disabled', false);
                            $currentItem.removeClass('pb-disabled');
                        }
                    });

                    $builder.find('.pb-extra-total').text(window.PB_GLOBAL_EXTRA.toFixed(2));

                    $category.data('selected', selectedCount);

                    calculatePackageTotal();
                    recalcBlockAddToCart();
                });
            });
        });

        $builder.find('.pb-content[data-mix="1"]').each(function () {

            const $category = $(this);
            const categoryKey = $category.data('category');
            const freeLimit = parseInt($category.data('mix-free'), 10) || 0;
            const mixGroups = String($category.data('mix-groups') || '').split(',').filter(Boolean);

            let selectedCount = 0;
            let paidCount = 0;
            let mixExtra = 0;

            const $counter = $category.find('.pb-mix-counter .pb-counter');
            const $counterText = $category.find('.pb-mix-counter .pb-counter-text');
            const $extraTab = $category.find('.pb-mix-counter .pb-extra-tab');

            mixGroups.forEach(groupKey => {
                const $group = $category.find(`.pb-subsection[data-group="${groupKey}"]`);

                $group.find('.pb-item').each(function () {

                    const $item = $(this);
                    const pid = $item.data('id');

                    const price =
                        PB_DATA.child_prices?.[categoryKey]?.[pid]?.[location]
                        ?? PB_DATA.child_prices?.[categoryKey]?.[pid]?.['all']
                        ?? null;

                    if (price === null) {
                        $item.hide();
                        return;
                    }

                    const numericPrice = parseFloat(price);
                    $item.data('price', numericPrice);

                    $item.find('input[type="checkbox"]').on('change', function () {

                        if (this.checked) {
                            selectedCount++;
                            addSelectedItem(pid, getItemHeading($item));

                            if (selectedCount <= freeLimit) {
                                $item
                                    .removeClass('pb-paid')
                                    .addClass('pb-free')
                                    .find('.pb-price')
                                    .text('Included')
                                    .show();
                            } else {
                                paidCount++;
                                mixExtra += numericPrice;
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
                            removeSelectedItem(pid);

                            if ($item.hasClass('pb-paid')) {
                                paidCount--;
                                mixExtra -= numericPrice;
                                window.PB_GLOBAL_EXTRA -= numericPrice;
                            }

                            $item
                                .removeClass('pb-free pb-paid')
                                .find('.pb-price')
                                .hide()
                                .text('');
                        }
                        let counterText = '';
                        let counterClass = '';

                        if (selectedCount === 0) {
                            counterText = `${freeLimit} selections required`;
                            counterClass = 'pb-required';
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
                                .text(`+£${mixExtra.toFixed(2)} (for ${paidCount} extra items)`)
                                .show();
                        }

                        $counter
                            .removeClass('pb-neutral pb-required pb-complete pb-extra')
                            .addClass(counterClass);

                        $counterText.text(counterText);

                        // Disable unchecked items in ALL mix groups once free limit is reached
                        mixGroups.forEach(gk => {
                            const $g = $category.find(`.pb-subsection[data-group="${gk}"]`);
                            $g.find('.pb-item').each(function () {
                                const $currentItem = $(this);
                                const $checkbox = $currentItem.find('input[type="checkbox"]');
                                const isChecked = $checkbox.is(':checked');

                                if (selectedCount >= freeLimit && !isChecked) {
                                    $checkbox.prop('disabled', true);
                                    $currentItem.addClass('pb-disabled');
                                } else {
                                    $checkbox.prop('disabled', false);
                                    $currentItem.removeClass('pb-disabled');
                                }
                            });
                        });

                        $builder.find('.pb-extra-total').text(window.PB_GLOBAL_EXTRA.toFixed(2));

                        $category.data('mix-selected', selectedCount);

                        calculatePackageTotal();
                        recalcBlockAddToCart();
                    });
                });
            });
        });

        $builder.find('.pb-subgroup').each(function () {

            const $group = $(this);
            const $category = $group.closest('.pb-content');
            const categoryKey = $category.data('category');
            const freeLimit = parseInt($group.data('free'), 10) || 0;

            if ($category.data('mix') === 1 || $category.data('mix') === '1') {
                const mixGroups = String($category.data('mix-groups') || '').split(',').filter(Boolean);
                const groupKey = String($group.data('group') || '');
                if (mixGroups.includes(groupKey)) {
                    return;
                }
            }

            log(`Subgroup initialized: ${$group.data('group')}, required: ${freeLimit}`);

            let selectedCount = 0;
            let paidCount = 0;
            let groupExtra = 0;

            const $counter = $group.find('.pb-counter');
            const $counterText = $group.find('.pb-counter-text');
            const $extraTab = $group.find('.pb-extra-tab');

            $group.find('.pb-item').each(function () {

                const $item = $(this);
                const pid = $item.data('id');

                const price =
                    PB_DATA.child_prices?.[categoryKey]?.[pid]?.[location]
                    ?? PB_DATA.child_prices?.[categoryKey]?.[pid]?.['all']
                    ?? null;

                if (price === null) {
                    $item.hide();
                    return;
                }

                const numericPrice = parseFloat(price);
                $item.data('price', numericPrice);

                $item.find('input[type="checkbox"]').on('change', function () {

                    if (this.checked) {

                        selectedCount++;
                        addSelectedItem(pid, getItemHeading($item));

                        if (selectedCount <= freeLimit) {
                            $item
                                .removeClass('pb-paid')
                                .addClass('pb-free')
                                .find('.pb-price')
                                .text('Included')
                                .show();
                        } else {
                            paidCount++;
                            groupExtra += numericPrice;
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
                        removeSelectedItem(pid);

                        if ($item.hasClass('pb-paid')) {
                            paidCount--;
                            groupExtra -= numericPrice;
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
                        counterText = `${freeLimit} selections required`;
                        counterClass = 'pb-required';
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
                            .text(`+£${groupExtra.toFixed(2)} (for ${paidCount} extra items)`)
                            .show();
                    }

                    $counter
                        .removeClass('pb-neutral pb-required pb-complete pb-extra')
                        .addClass(counterClass);

                    $counterText.text(counterText);

                    // Disable unchecked items once free limit is reached (for subgroups)
                    $group.find('.pb-item').each(function () {
                        const $currentItem = $(this);
                        const $checkbox = $currentItem.find('input[type="checkbox"]');
                        const isChecked = $checkbox.is(':checked');

                        if (selectedCount >= freeLimit && !isChecked) {
                            $checkbox.prop('disabled', true);
                            $currentItem.addClass('pb-disabled');
                        } else {
                            $checkbox.prop('disabled', false);
                            $currentItem.removeClass('pb-disabled');
                        }
                    });

                    /* ===========================
                     * GLOBAL SUMMARY
                     * =========================== */
                    $builder.find('.pb-extra-total').text(window.PB_GLOBAL_EXTRA.toFixed(2));

                    $group.data('selected', selectedCount);

                    calculatePackageTotal();
                    recalcBlockAddToCart();
                });

            });
        });

        /* ===========================
         * TABS
         * =========================== */
        $builder.find('.pb-content').hide();
        $builder.find('.pb-tabs li').on('click', function () {
            const $all = $builder.find('.pb-tabs li');
            const $active = $all.filter('.active');
            const activeIdx = $all.index($active);
            const $target = $(this);
            const targetIdx = $all.index($target);

            // if moving forward, ensure current tab requirement met
            if (targetIdx > activeIdx) {
                const currTab = $active.data('tab');
                const currId = (typeof currTab === 'string' && currTab.indexOf('pb-') === 0) ? currTab : 'pb-' + currTab;
                const $currContent = $builder.find('#' + currId);
                let req = 0;
                if ($currContent.length) {
                    const free = parseInt($currContent.data('free'), 10);
                    if (!isNaN(free) && free > 0) req = free;
                    const mixFree = parseInt($currContent.data('mix-free'), 10);
                    if (!isNaN(mixFree) && mixFree > 0) req = mixFree;
                    if (req === 0) {
                        $currContent.find('.pb-subgroup').each(function () {
                            req += parseInt($(this).data('free'), 10) || 0;
                        });
                    }
                }
                const sel = $currContent.find('input[type="checkbox"]:checked').length;
                if (req > 0 && sel < req) {
                    const tabLabel = $active.data('base-label') || $active.text();
                    showTabError((tabLabel || 'This tab') + ' requires ' + req + ' selections.');
                    return;
                }
            }

            const tab = $(this).data('tab');
            $builder.find('.pb-tabs li').removeClass('active');
            $(this).addClass('active');
            $builder.find('.pb-content').hide();
            $builder.find('#pb-' + tab).show();
        });
        $builder.find('.pb-tabs li:first').trigger('click');

        /* ===========================
         * ADD TO CART
         * =========================== */
        $builder.find('#pb-add-cart').on('click', function () {

            if (!window.PB_MAIN_VARIATION_ID) {
                logError('Variation ID not set');
                alert('❌ Variation ID not set. Location → variation mapping missing.');
                return;
            }

            if (window.PB_BLOCK_ADD_TO_CART) {
                alert('❌ Please complete required selections');
                return;
            }

            const cartData = {
                action: 'pb_add_to_cart',
                variation_id: window.PB_MAIN_VARIATION_ID,
                extra_price: window.PB_GLOBAL_EXTRA,
                package_price: window.PB_PACKAGE_TOTAL || 0,
                persons: window.PB_PERSONS_COUNT || 1,
                items: window.PB_SELECTED_ITEMS
            };

            log('Add to cart triggered', { 
                packagePrice: cartData.package_price, 
                extraPrice: cartData.extra_price,
                persons: cartData.persons,
                itemsCount: cartData.items.length
            });

            $.post(PB_CONFIG.ajax_url, cartData, function (res) {

                if (res.success) {
                    window.location.href = PB_CONFIG.cart_url;
                } else {
                    logError('Add to cart failed', res.data);
                    alert(res.data || 'Add to cart failed');
                }
            });
        });

        calculatePackageTotal();
    }

    if ($packageSelector.length) {
        function updatePackagePrice($select) {
            const selectedPersons = $select.val();
            const $priceEl = $select.closest('.pb-package-option').find('.pb-package-price');

            if (!selectedPersons) {
                $priceEl.text('').hide();
                return;
            }

            const location = localStorage.getItem('selectedLocation');
            let price = null;

            const rawMap = $select.attr('data-package-price-map');
            if (rawMap) {
                try {
                    const map = JSON.parse(rawMap);
                    if (location && map && map[location] !== undefined) {
                        price = map[location];
                    }
                } catch (e) {
                    logWarning('Invalid price map JSON', e);
                }
            }

            if (price === null || price === undefined) {
                const basePrice = parseFloat($select.attr('data-package-price'));
                if (!isNaN(basePrice)) {
                    price = basePrice;
                }
            }

            if (price === null || price === undefined) {
                $priceEl.text('Price unavailable').show();
                return;
            }

            $priceEl.text('Price for 1 Person: £' + parseFloat(price).toFixed(2)).show();
        }

        $packageSelector.find('.pb-persons-select').each(function () {
            updatePackagePrice($(this));
        });

        $packageSelector.on('change', 'input[name="pb_package"]', function () {
            $('#pb-next-package').prop('disabled', false);
            $('#pb-clear-package').prop('disabled', false).show();
        });

        $packageSelector.on('change', '.pb-persons-select', function () {
            const $select = $(this);
            const $option = $select.closest('.pb-package-option');
            const $radio = $option.find('input[name="pb_package"]');
            const selectedValue = $select.val();

            // Disable radio only if value is empty (i.e., "Select Persons" was chosen)
            if (selectedValue === '' || selectedValue === null) {
                $radio.prop('checked', false).prop('disabled', true);
                $('#pb-next-package').prop('disabled', true);
                $('#pb-clear-package').prop('disabled', true).hide();
            } else {
                $radio.prop('disabled', false);
            }

            updatePackagePrice($select);
        });

        $packageSelector.on('click', 'input[name="pb_package"]', function (e) {
            const $radio = $(this);
            const $option = $radio.closest('.pb-package-option');
            const requiresPersons = $radio.data('requires-persons') === 1 || $option.data('requires-persons') === 1;
            const $select = $option.find('.pb-persons-select');

            if (requiresPersons && $select.length && !$select.val()) {
                e.preventDefault();
                $radio.prop('checked', false);
            }
        });

        $('#pb-clear-package').on('click', function () {
            $packageSelector.find('input[name="pb_package"]').prop('checked', false);
            $packageSelector.find('.pb-persons-select').val('1');
            $('#pb-next-package').prop('disabled', true);
            $('#pb-clear-package').prop('disabled', true).hide();
        });

        $('#pb-next-package').on('click', function () {
            const productId = $packageSelector.find('input[name="pb_package"]:checked').val();

            if (!productId) return;

            const $selectedOption = $packageSelector.find('input[name="pb_package"]:checked').closest('.pb-package-option');
            
            // For Ramzan packages, check if it's a Ramzan package (no persons select)
            const isRamzan = $selectedOption.data('requires-persons') === 0;
            
            if (isRamzan) {
                // For Ramzan packages, get price from the displayed price text
                const priceText = $selectedOption.find('.pb-package-price').text();
                const priceMatch = priceText.match(/£?([\d.]+)/);
                const packagePrice = priceMatch ? parseFloat(priceMatch[1]) : 0;
                window.PB_RAMZAN_BASE_PRICE = packagePrice;
                window.PB_PERSONS_COUNT = 1; // Will be updated when tab is selected (2 or 8)
                
                log('Ramzan package selected', { price: packagePrice });
            } else {
                // For regular packages, get persons count
                const personsVal = parseInt($selectedOption.find('.pb-persons-select').val(), 10);
                window.PB_PERSONS_COUNT = !isNaN(personsVal) && personsVal > 0 ? personsVal : 1;
                
                log('Regular package selected', { persons: window.PB_PERSONS_COUNT });
            }

            const $btn = $(this);

            // hide package list and show inline loader
            $('#pb-package-selector').hide();
            $builderContainer.html('<div class="pb-loading"><span class="loading-spinner"></span></div>');

            $btn.prop('disabled', true).text('Loading...');

            $.post(PB_CONFIG.ajax_url, {
                action: 'pb_get_builder',
                product_id: productId
            }, function (res) {

                $btn.prop('disabled', false).text('Next');

                if (!res || !res.success) {
                    logError('Failed to load package', res?.data);
                    alert(res?.data || 'Unable to load package');
                    // restore package selector on failure
                    $('#pb-package-selector').show();
                    $builderContainer.empty();
                    return;
                }

                log('Package builder loaded successfully');
                $builderContainer.html(res.data.html);
                initPackageBuilder(res.data.data);

                if ($builderContainer.length) {
                    $('html, body').animate({
                        scrollTop: $builderContainer.offset().top - 20
                    }, 300);
                }
            });
        });
    }

});

