// Sticky top menu
jQuery(window).scroll(function () {
    const header = document.getElementById("header");
    const toggleClass = "navbar-fixed-sticky";
    if (jQuery(window).scrollTop() > 100) {
        jQuery(header).addClass(toggleClass);
    } else {
        jQuery(header).removeClass(toggleClass);
    }
});
