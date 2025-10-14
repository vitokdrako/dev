// FarforRent JavaScript

$(document).ready(function() {
    
    // Update cart count in header
    function updateCartCount() {
        $.request('onGetCartCount', {
            success: function(data) {
                if (data.cartCount !== undefined) {
                    $('#cart-count').text(data.cartCount);
                }
            }
        });
    }
    
    // Add to cart functionality
    $(document).on('click', '.btn-add-to-cart', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var productId = $btn.data('product-id');
        var quantity = $btn.closest('.product-item').find('.quantity-input').val() || 1;
        var rentalDays = $btn.closest('.product-item').find('.rental-days-input').val() || 1;
        
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Додаємо...');
        
        $.request('cart::onAddToCart', {
            data: {
                product_id: productId,
                quantity: quantity,
                rental_days: rentalDays
            },
            success: function(data) {
                if (data.success) {
                    $btn.removeClass('btn-primary').addClass('btn-success')
                        .html('<i class="fa fa-check"></i> Додано!');
                    
                    // Update cart count
                    if (data.cartCount !== undefined) {
                        $('#cart-count').text(data.cartCount);
                    }
                    
                    // Show success message
                    showMessage('success', data.message || 'Товар додано до кошика');
                    
                    setTimeout(function() {
                        $btn.prop('disabled', false).removeClass('btn-success').addClass('btn-primary')
                            .html('<i class="fa fa-cart-plus"></i> До кошика');
                    }, 2000);
                } else {
                    showMessage('error', data.error || 'Помилка при додаванні товару');
                    $btn.prop('disabled', false).html('<i class="fa fa-cart-plus"></i> До кошика');
                }
            },
            error: function() {
                showMessage('error', 'Помилка при додаванні товару');
                $btn.prop('disabled', false).html('<i class="fa fa-cart-plus"></i> До кошика');
            }
        });
    });
    
    // Remove from cart
    $(document).on('click', '.btn-remove-from-cart', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var productId = $btn.data('product-id');
        
        if (confirm('Видалити товар з кошика?')) {
            $.request('cart::onRemoveFromCart', {
                data: {
                    product_id: productId
                },
                success: function(data) {
                    if (data.success) {
                        $btn.closest('.cart-item').fadeOut(300, function() {
                            $(this).remove();
                            updateCartCount();
                        });
                        showMessage('success', 'Товар видалено з кошика');
                    }
                }
            });
        }
    });
    
    // Update cart item quantity
    $(document).on('change', '.cart-quantity-input', function() {
        var $input = $(this);
        var productId = $input.data('product-id');
        var quantity = $input.val();
        
        $.request('cart::onUpdateCart', {
            data: {
                product_id: productId,
                quantity: quantity
            },
            success: function(data) {
                if (data.success) {
                    // Update cart totals (if needed)
                    updateCartCount();
                }
            }
        });
    });
    
    // Product image gallery
    $('.thumbnail-images img').on('click', function() {
        var newSrc = $(this).attr('src');
        $('.main-image img').attr('src', newSrc);
        $('.thumbnail-images img').removeClass('active');
        $(this).addClass('active');
    });
    
    // Quantity input controls
    $(document).on('click', '.qty-btn-plus', function() {
        var $input = $(this).siblings('.quantity-input');
        var currentVal = parseInt($input.val()) || 1;
        $input.val(currentVal + 1).trigger('change');
    });
    
    $(document).on('click', '.qty-btn-minus', function() {
        var $input = $(this).siblings('.quantity-input');
        var currentVal = parseInt($input.val()) || 1;
        if (currentVal > 1) {
            $input.val(currentVal - 1).trigger('change');
        }
    });
    
    // Rental days input controls
    $(document).on('click', '.days-btn-plus', function() {
        var $input = $(this).siblings('.rental-days-input');
        var currentVal = parseInt($input.val()) || 1;
        $input.val(currentVal + 1).trigger('change');
    });
    
    $(document).on('click', '.days-btn-minus', function() {
        var $input = $(this).siblings('.rental-days-input');
        var currentVal = parseInt($input.val()) || 1;
        if (currentVal > 1) {
            $input.val(currentVal - 1).trigger('change');
        }
    });
    
    // Calculate rental total
    $(document).on('change', '.quantity-input, .rental-days-input', function() {
        var $container = $(this).closest('.product-item, .product-detail');
        var quantity = parseInt($container.find('.quantity-input').val()) || 1;
        var days = parseInt($container.find('.rental-days-input').val()) || 1;
        var pricePerDay = parseFloat($container.find('.price-per-day').data('price')) || 0;
        
        var total = quantity * days * pricePerDay;
        $container.find('.total-price').text('₴ ' + total.toLocaleString());
    });
    
    // Search functionality
    $('#search-input').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            var query = $(this).val();
            if (query.length > 0) {
                window.location.href = '/search?q=' + encodeURIComponent(query);
            }
        }
    });
    
    // Mobile menu toggle
    $('.navbar-toggle').on('click', function() {
        $('.navbar-collapse').slideToggle(300);
    });
    
    // Back to top button
    var $backToTop = $('<div class="back-to-top"><i class="fa fa-chevron-up"></i></div>');
    $('body').append($backToTop);
    
    $(window).scroll(function() {
        if ($(window).scrollTop() > 300) {
            $backToTop.fadeIn();
        } else {
            $backToTop.fadeOut();
        }
    });
    
    $backToTop.on('click', function() {
        $('html, body').animate({ scrollTop: 0 }, 600);
    });
    
    // Show message function
    function showMessage(type, message) {
        var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        var icon = type === 'success' ? 'fa-check' : 'fa-exclamation-triangle';
        
        var $alert = $('<div class="alert ' + alertClass + ' alert-dismissible fade in" role="alert">' +
            '<button type="button" class="close" data-dismiss="alert">' +
                '<span aria-hidden="true">&times;</span>' +
            '</button>' +
            '<i class="fa ' + icon + '"></i> ' + message +
        '</div>');
        
        // Add to top of content area
        $('#content').prepend($alert);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $alert.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Initialize cart count on page load
    updateCartCount();
    
});

// Additional utility functions
function formatPrice(price) {
    return '₴ ' + parseFloat(price).toLocaleString('uk-UA');
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('uk-UA');
}