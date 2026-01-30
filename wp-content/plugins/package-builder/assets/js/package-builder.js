jQuery(function ($) {

    let count = {};
    let extra = 0;

    $('.pb-tabs li').on('click', function () {
        let tab = $(this).data('tab');
        $('.pb-content').hide();
        $('#pb-' + tab).show();
    }).first().click();

    $('.pb-add').on('click', function () {

        let box = $(this).closest('.pb-content');
        let field = box.data('field');
        let free = parseInt(box.data('free'));
        let price = parseFloat($(this).closest('.pb-item').data('price'));

        count[field] = (count[field] || 0) + 1;

        if (count[field] > free) {
            extra += price;
        }

        $('#pb-extra').text(extra.toFixed(2));
    });

    $('#pb-cart').on('click', function () {
        $.post(PB.ajax, {
            action: 'pb_add_to_cart',
            product: $('#package-builder').data('product'),
            extra: extra
        }, function () {
            window.location.href = PB.cart;
        });
    });

});
