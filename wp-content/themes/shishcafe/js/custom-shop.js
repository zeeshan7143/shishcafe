// Custom Shop Page

document.addEventListener("DOMContentLoaded", function () {
    // Add tags and id in li
    function updateProductAttributes() {
        let products = document.querySelectorAll("li.product");
        products.forEach(function (product) {
            let classList = product.classList;
            // Extract product tag classes
            let tagClasses = Array.from(classList)
                .filter((cls) => cls.startsWith("product_tag-"))
                .map((cls) => cls.replace("product_tag-", "").replace(/-/g, " "));
            if (tagClasses.length > 0) {
                product.setAttribute("data-category", tagClasses.join(", "));
            }
            // Extract product ID
            let productIdClass = Array.from(classList).find((cls) =>
                cls.startsWith("post-")
            );
            if (productIdClass) {
                let productId = productIdClass.replace("post-", "");
                product.setAttribute("data-product-id", productId);
            }
        });
    }
    // Run once on initial load
    updateProductAttributes();
    // **Watch for product list updates (AJAX Loaded Products)**
    const observer = new MutationObserver(updateProductAttributes);
    observer.observe(document.body, { childList: true, subtree: true });

    let page = 1;
    let loadMoreBtn = document.getElementById("load-more");
    let productList = document.getElementById("product-list");
    let categorySelect = document.getElementById("category-select");
    let categoryCheckboxes = document.querySelectorAll(
        '#category-checkboxes input[type="checkbox"]'
    );
    let resetFiltersBtn = document.getElementById("reset-filters");
    let loadingIndicator = document.createElement("div");
    loadingIndicator.className = "loading-spinner-box";
    loadingIndicator.innerHTML = '<div class="loading-spinner"></div>';
    loadingIndicator.style.display = "none";
    loadingIndicator.style.textAlign = "center";
    productList.parentNode.insertBefore(loadingIndicator, productList);
    let allLoadedProducts = []; // Store all loaded product HTML
    function getSelectedCategories() {
        let selectedCategories = [];
        if (categorySelect.value) {
            selectedCategories.push(categorySelect.value);
        }
        let checkedCategories = Array.from(categoryCheckboxes)
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => checkbox.value);
        return [...new Set([...selectedCategories, ...checkedCategories])];
    }
    function getSelectedLocation() {
        const locationKey = "selectedLocation";
        // const defaultLocation = "rochdale";
        let storedLocation = localStorage.getItem(locationKey);
        if (!storedLocation) {
            storedLocation = defaultLocation;
            localStorage.setItem(locationKey, storedLocation);
        }
        // return storedLocation.toLowerCase();
        const currentLocation = storedLocation.toLowerCase();
        return currentLocation;
    }
    function updateResetButtonVisibility() {
        let categories = getSelectedCategories();
        resetFiltersBtn.style.display = categories.length > 0 ? "block" : "none";
    }
    function filterVisibleProductsByLocation() {
        const selectedLocation = getSelectedLocation();
        const productItems = productList.querySelectorAll(
            "li.product[data-product-id]"
        );
        productItems.forEach((item) => {
            const productId = item.dataset.productId;
            const productInfo = allLoadedProducts[productId];
            item.style.display = "none"; // Hide all by default
            if (
                productInfo &&
                productInfo.tags &&
                productInfo.tags.includes(selectedLocation)
            ) {
                item.style.display = "flex";
                // console.log(
                //     `Showing product ${productId} for location: ${selectedLocation}`
                // );
            } else if (!selectedLocation) {
                item.style.display = "flex";
                // console.log(`Showing product ${productId} - no location selected.`);
            } else {
                item.style.display = "none";
                // console.log(
                //     `Hiding product ${productId}. Tags:`,
                //     productInfo ? productInfo.tags : "No info",
                //     "Selected:",
                //     selectedLocation
                // );
            }
        });
    }

    function updateProductPriceDisplay(productElement, prices) {
        const productId = productElement.dataset.productId;
        const selectedLocation = getSelectedLocation();
        const formattedLocation = selectedLocation
            ? selectedLocation.charAt(0).toUpperCase() + selectedLocation.slice(1).toLowerCase()
            : '';
        const priceContainer = productElement.querySelector('.price');

        if (priceContainer && prices && prices[productId]) {
            const locationPrices = prices[productId][formattedLocation];

            if (locationPrices) {
                if (locationPrices.length > 1) {
                    const minPrice = Math.min(...locationPrices).toFixed(2);
                    const maxPrice = Math.max(...locationPrices).toFixed(2);
                    priceContainer.innerHTML = `<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&pound;</span>${minPrice}</bdi></span> – <span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&pound;</span>${maxPrice}</bdi></span>`;
                } else if (locationPrices.length === 1) {
                    priceContainer.innerHTML = `<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&pound;</span>${locationPrices[0].toFixed(2)}</bdi></span>`;
                }
            } else if (Object.keys(prices[productId]).length > 0) {
                // Fallback to showing the min/max of the prices across all locations
                const allPrices = Object.values(prices[productId]).flat();
                if (allPrices.length > 1) {
                    const minPrice = Math.min(...allPrices).toFixed(2);
                    const maxPrice = Math.max(...allPrices).toFixed(2);
                    priceContainer.innerHTML = `<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&pound;</span>${minPrice}</bdi></span> – <span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&pound;</span>${maxPrice}</bdi></span>`;
                } else if (allPrices.length === 1) {
                    priceContainer.innerHTML = `<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&pound;</span>${allPrices[0].toFixed(2)}</bdi></span>`;
                }
            } else {
                // No specific location price and no other location prices
                const originalPrice = productElement.querySelector('.price');
                if (originalPrice) {
                    // Keep the original price or set a default message
                }
            }
        } else {
            // console.log('prices[productId] is undefined for productId:', productId);
            const originalPrice = productElement.querySelector('.price');
            if (originalPrice) {
                // Keep the original price or set a default message
            }
        }
    }

    function loadProducts(append = false) {
        if (!append) {
            page = 1;
            allLoadedProducts = {}; // Reset stored products on new filter
            allLoadedPrices = {};   // Reset stored prices
            productList.innerHTML = "";
        }
        let categories = getSelectedCategories();
        let categoryParam = categories.length > 0 ? categories.join(",") : "";
        let location = getSelectedLocation();
        let xhr = new XMLHttpRequest();
        xhr.open("POST", ajax_object.ajax_url, true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        loadingIndicator.style.display = "flex";
        loadMoreBtn.style.display = "none";
        xhr.onload = function () {
            if (this.status === 200) {
                let response = JSON.parse(this.responseText);
                // For Prouct category count Start 
                if (response.category_counts) {
                    // Update <select> options
                    const options = categorySelect.querySelectorAll("option[value]");
                    options.forEach(opt => {
                        const slug = opt.value;
                        if (slug && response.category_counts[slug] !== undefined) {
                            opt.innerHTML = `${opt.textContent.replace(/\(\d+\)$/, "")} (${response.category_counts[slug]})`;
                        }
                    });

                    // Update checkbox counts
                    categoryCheckboxes.forEach(cb => {
                        const slug = cb.value;
                        if (response.category_counts[slug] !== undefined) {
                            const span = cb.parentElement.querySelector(".product-count");
                            if (span) {
                                span.textContent = `(${response.category_counts[slug]})`;
                            }
                        }
                    });
                }
                // For Prouct category count End 
                allLoadedPrices = { ...allLoadedPrices, ...response.prices }; // Store loaded prices


                loadingIndicator.style.display = "none";
                if (response.html.trim() !== "") {
                    const tempDiv = document.createElement("div");
                    tempDiv.innerHTML = response.html;
                    const newProductItems = tempDiv.querySelectorAll("li.product"); // Select the li.product elements
                    newProductItems.forEach(item => {
                        const productId = item.dataset.productId;
                        if (response.product_tags && response.product_tags[productId]) {
                            allLoadedProducts[productId] = {
                                html: item.outerHTML,
                                tags: response.product_tags[productId]
                            };
                        }
                        productList.appendChild(item);
                        // updateProductPriceDisplay(item, allLoadedPrices); // Update price after appending
                    });
                    updateProductAttributes(); // Called AFTER appending new items

                    const updatedProductItems = productList.querySelectorAll(
                        "li.product[data-product-id]"
                    ); // Select li.product with the attribute
                    updatedProductItems.forEach((item) => {
                        // Use 'item' consistently
                        const productId = item.dataset.productId;
                        updateProductPriceDisplay(item, allLoadedPrices); // <-- UNCOMMENT THIS LINE

                        if (response.product_tags && response.product_tags[productId]) {
                            const productData = {
                                html: item.outerHTML,
                                tags: response.product_tags[productId],
                            };
                            allLoadedProducts[productId] = productData;
                        }
                    });
                    if (!response.has_more) {
                        loadMoreBtn.style.display = "none";
                    } else {
                        loadMoreBtn.style.display = "block";
                        loadMoreBtn.innerText = "Load More";
                    }
                } else if (!append) {
                    productList.innerHTML =
                        '<p style="text-align:center; font-size:16px; color:#888;">No products found.</p>';
                    loadMoreBtn.style.display = "none";
                }
                // console.log("productList after append:", productList.innerHTML);
                updateResetButtonVisibility();
                filterVisibleProductsByLocation(); // Filter after loading and attribute update
                // console.log("All Loaded Products:", allLoadedProducts);
            }
        };
        let formData = `action=load_more_products&page=${page}&categories=${categoryParam}&selected_location=${location}`;
        // console.log("AJAX request data:", formData);
        xhr.send(formData);
    }

    loadProducts(false);
    loadMoreBtn.addEventListener("click", function () {
        page++;
        loadProducts(true);
    });
    categorySelect.addEventListener("change", function () {
        loadProducts(false);
    });
    categoryCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener("change", function () {
            loadProducts(false);
        });
    });
    resetFiltersBtn.addEventListener("click", function () {
        categorySelect.value = "";
        categoryCheckboxes.forEach((checkbox) => (checkbox.checked = false));
        resetFiltersBtn.style.display = "none";
        loadProducts(false);
    });
});
