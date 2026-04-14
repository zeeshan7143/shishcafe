(function() {

    function removeBanner() {
        document.querySelectorAll('a[href*="s-sols.com/products/wordpress/accelerator"]').forEach(el => {
            el.remove();
        });
    }

    // Run immediately
    removeBanner();

    // After DOM ready
    document.addEventListener("DOMContentLoaded", removeBanner);

    // Watch for dynamic changes
    const observer = new MutationObserver(removeBanner);
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

})();