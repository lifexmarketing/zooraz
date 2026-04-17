jQuery(document).ready(function($) {
    
    console.log('Zaraz Add to Cart tracking loaded');
    
    // Check if Zaraz is available
    if (typeof zaraz === 'undefined') {
        console.warn('Zaraz is not loaded yet');
    } else {
        console.log('Zaraz is available');
    }
    
    // Function to send tracking data to Zaraz
    function sendToZaraz(productData) {
        console.log('Attempting to send to Zaraz:', productData);
        
        var attempts = 0;
        var maxAttempts = 20;
        
        var checkZaraz = setInterval(function() {
            attempts++;
            
            if (typeof zaraz !== 'undefined' && typeof zaraz.ecommerce === 'function') {
                clearInterval(checkZaraz);
                console.log('Sending to Zaraz ecommerce:', productData);
                zaraz.ecommerce('Product Added', productData);
                console.log('✓ Zaraz event sent successfully');
            } else if (attempts >= maxAttempts) {
                clearInterval(checkZaraz);
                console.error('✗ Zaraz not available after', maxAttempts, 'attempts');
            }
        }, 100);
    }
    
    // Function to fetch product data and track
    function trackAddToCart(productId, variationId, quantity) {
        console.log('Tracking add to cart - Product:', productId, 'Variation:', variationId, 'Qty:', quantity);
        
        $.ajax({
            url: zarazTrackingData.ajax_url,
            type: 'POST',
            data: {
                action: 'get_product_data_for_tracking',
                nonce: zarazTrackingData.nonce,
                product_id: productId,
                variation_id: variationId || 0,
                quantity: quantity || 1
            },
            success: function(response) {
                console.log('AJAX response:', response);
                
                if (response.success) {
                    sendToZaraz(response.data);
                } else {
                    console.error('AJAX error:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX request failed:', status, error);
            }
        });
    }
    
    // Method 1: Listen to WooCommerce's added_to_cart event (AJAX add to cart)
    $(document.body).on('added_to_cart', function(event, fragments, cart_hash, button) {
        console.log('✓ added_to_cart event fired');
        
        var productId = $(button).data('product_id');
        var quantity = $(button).data('quantity') || 1;
        var variationId = 0;
        
        var form = $(button).closest('form.cart');
        if (form.length) {
            variationId = form.find('input[name="variation_id"]').val() || 0;
            var qtyInput = form.find('input[name="quantity"]').val();
            if (qtyInput) {
                quantity = parseInt(qtyInput);
            }
        }
        
        trackAddToCart(productId, variationId, quantity);
    });
    
    // Method 2: Listen for clicks on archive/shop page add to cart buttons
    $(document).on('click', '.add_to_cart_button:not(.product_type_variable)', function(e) {
        console.log('✓ Archive add to cart button clicked');
        
        var button = $(this);
        var productId = button.data('product_id');
        var quantity = button.data('quantity') || 1;
        
        console.log('Archive button - Product:', productId, 'Qty:', quantity);
        
        // Track immediately for archive pages
        trackAddToCart(productId, 0, quantity);
    });
    
    // Method 3: Listen for single product page add to cart form submission
    $('form.cart').on('submit', function(e) {
        console.log('✓ Single product form submitted');
        
        var form = $(this);
        var submitButton = form.find('button[type="submit"]');
        
        var productId = form.find('input[name="product_id"]').val() || 
                       form.find('button[name="add-to-cart"]').val() ||
                       submitButton.val();
        var variationId = form.find('input[name="variation_id"]').val() || 0;
        var quantity = form.find('input[name="quantity"]').val() || 1;
        
        console.log('Form submission - Product:', productId, 'Variation:', variationId, 'Qty:', quantity);
        
        if (productId) {
            // Check if form uses AJAX or standard submission
            if (submitButton.hasClass('ajax_add_to_cart') || form.hasClass('ajax_add_to_cart')) {
                console.log('AJAX form - waiting for added_to_cart event');
                // Will be handled by added_to_cart event
            } else {
                console.log('Standard form - tracking now');
                trackAddToCart(productId, variationId, quantity);
            }
        } else {
            console.warn('No product ID found in form');
        }
    });
    
    // Method 4: Alternative - Listen to updating_cart_totals (when cart updates)
    $(document.body).on('updating_cart_totals', function() {
        console.log('Cart totals updating event fired');
    });
    
    // Method 5: Watch for WooCommerce adding class to button
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.target.classList && mutation.target.classList.contains('added')) {
                console.log('✓ Product was added (detected via class change)');
                var button = $(mutation.target);
                if (button.hasClass('add_to_cart_button')) {
                    var productId = button.data('product_id');
                    var quantity = button.data('quantity') || 1;
                    console.log('Mutation observer - Product:', productId);
                    // Only track if not already tracked by other methods
                    setTimeout(function() {
                        trackAddToCart(productId, 0, quantity);
                    }, 100);
                }
            }
        });
    });
    
    // Observe all add to cart buttons
    $('.add_to_cart_button').each(function() {
        observer.observe(this, { attributes: true, attributeFilter: ['class'] });
    });
    
    console.log('All tracking methods initialized');
});