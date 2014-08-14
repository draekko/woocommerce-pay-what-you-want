
/* =============================================

        JS SCRIPT - PAY WHAT YOU WANT
        Copyright 2014, Benoit Touchette.
        Some rights reserved.
        Licensed GPLv3.

   ============================================= */

function pwywMsgDialog(dlgmsg) {
    jQuery(".pwyw_spinner_container").css("display", "none");
	jQuery(".pwyw_error_container").css("display", "block");
	jQuery(".pwyw_error_message").text(dlgmsg);
    console.log('DLGMSG: '+dlgmsg);
	pwywCenterMsgDialog();
}

function pwywHideMsgDialog(dlgmsg) {
	jQuery(".pwyw_error_container").css("display", "none");
}

function pwywCenterMsgDialog() {
	wz = (jQuery(window).width() - jQuery('.pwyw_error_box').outerWidth())/2,
	hz = (jQuery(window).height() - jQuery('.pwyw_error_box').outerHeight())/2

	jQuery('.pwyw_error_box').css({
        position : 'fixed',
        margin: 0,
        left: wz,
        top: hz
	});
}

function pwywCenterSpinner() {
	wz1 = (jQuery(window).width() - jQuery('.pwyw_spinner_box').outerWidth())/2,
	hz1 = (jQuery(window).height() - jQuery('.pwyw_spinner_box').outerHeight())/2
	wz2 = (jQuery(window).width() - jQuery('.pwyw_spinner_msg').outerWidth())/2,
	hz2 = (jQuery(window).height() - jQuery('.pwyw_spinner_msg').outerHeight())/2

	jQuery('.pwyw_spinner_box').css({
        position : 'fixed',
        margin: 0,
        left: wz1,
        top: hz1
	});

	jQuery('.pwyw_spinner_msg').css({
        position : 'fixed',
        margin: 0,
        left: wz2+40,
        top: hz2+66
	});
}

function pwywShowSpinner() {
	jQuery(".pwyw_error_container").css("display", "none");
    jQuery(".pwyw_spinner_container").css("display", "block");
    pwywCenterSpinner();
}

function pwywHideSpinner() {
    jQuery(".pwyw_spinner_container").css("display", "none");
}

function pwyw_add_variation_to_cart(postitem) {
    var amount = 0;
    var price = 0;

    var amount = jQuery('#pwyw_input_pay_amount_'+postitem).maskMoney('unmasked')[0];
    if (amount == null || amount == 'undefined') {
        amount = jQuery('#pwyw_input_pay_amount_store_'+postitem).maskMoney('unmasked')[0];
    }
    if (amount == null || amount == 'undefined') {
        amount = jQuery('select.pwyw_sku_value').data('amount');
    }

    price = parseFloat(amount);

    var vri = jQuery( ".pwyw_sku_value  option:selected" ).data( 'product_id' )
    var qty = jQuery('input[type=number].input-text.qty.text').val();
    if (qty == null) qty = 1;

    var data = {
        'action': 'wc_pay_what_you_want_add_to_cart',
        'wc_pay_what_you_want_pid': postitem,
        'wc_pay_what_you_want_qty': qty,
        'wc_pay_what_you_want_amt': price.toFixed(2),
        'wc_pay_what_you_want_vri': vri
    };

    pwywShowSpinner();

    jQuery.post( ajaxurl, data, function(e) {
        var lastchar = e.charAt(e.length - 1);
        var servermessage = e;
        if ( lastchar == '0' ) {
            servermessage = servermessage.substring(0, servermessage.length - 1);
        }
        if (servermessage == null || servermessage == '' || servermessage == 'undefined') {
            pwywMsgDialog('SERVER_ERROR: Server returns empty string.');
            pwywHideSpinner();
            console.log('SERVER_ERROR: '+servermessage);
        } else if (e.indexOf('ERROR') > -1) {
            pwywMsgDialog(servermessage);
            pwywHideSpinner();
            console.log('SERVER_ERROR: '+servermessage);
        } else {
            pwywHideSpinner();
            console.log('SERVER_RETURNS:'+servermessage);
            window.location = servermessage;
        }
    });
}

function pwyw_add_to_cart(postitem) {
    var amount = jQuery('#pwyw_input_pay_amount_'+postitem).maskMoney('unmasked')[0];
    if (amount == null || amount == 'undefined') {
        amount = jQuery('#pwyw_input_pay_amount_store_'+postitem).maskMoney('unmasked')[0];
    }
    if (amount == null || amount == 'undefined') {
        amount = jQuery('input.single_add_to_cart_button.button.alt.pwyw_price_input').data('amount');
    }
    var price = parseFloat(amount);
    var qty = jQuery('input[type=number].input-text.qty.text').val();
    if (qty == null) qty = 1;

    var data = {
        'action': 'wc_pay_what_you_want_add_to_cart',
        'wc_pay_what_you_want_pid': postitem,
        'wc_pay_what_you_want_qty': qty,
        'wc_pay_what_you_want_amt': price.toFixed(2),
        'wc_pay_what_you_want_vri': 0
    };

    pwywShowSpinner();

    jQuery.post( ajaxurl, data, function(e) {
        var lastchar = e.charAt(e.length - 1);
        var servermessage = e;
        if ( lastchar == '0' ) {
            servermessage = servermessage.substring(0, servermessage.length - 1);
        }
        if (servermessage == null || servermessage == '' || servermessage == 'undefined') {
            pwywMsgDialog('SERVER_ERROR: Server returns empty string.');
            pwywHideSpinner();
            console.log('SERVER_ERROR: '+servermessage);
        } else if (e.indexOf('ERROR') > -1) {
            pwywMsgDialog(servermessage);
            pwywHideSpinner();
            console.log('SERVER_ERROR: '+servermessage);
        } else {
            pwywHideSpinner();
            console.log('SERVER_RETURNS:'+servermessage);
            window.location = servermessage;
        }
    });
}


(function ($) {
    "use strict";

    $(document).ready( function() {
        var $variation_form = $(document).closest( '.variations_form' );
        var $sku = $(document).find( '.product_meta_pwyw' ).find( '.sku' );
        var $image = $(document).find( '#main .woocommerce-main-image img' );
        var all_variations = $('.variations_form').data( 'product_variations' );
        $image.attr('src', get_option_data( 'image' ));
        $sku.text( get_option_data( 'sku' ) );
        if (is_use_pwyw()) {
            displayCurrentPrice();
        } else if (is_onsale()) {
            displaySales( getDisplaySales(), getDisplayPrice() );
        } else {
            displayPrice( getDisplayPrice() );
        }

    	$( 'div.quantity:not(.buttons_added), td.quantity:not(.buttons_added)' ).addClass( 'buttons_added' ).append( '<input type="button" value="+" class="plus" />' ).prepend( '<input type="button" value="-" class="minus" />' );

        $( '.woocommerce-ordering' ).on( 'change', 'select.orderby', function() {
            $( this ).closest( 'form' ).submit();
        });

        $( 'input.qty:not(.product-quantity input.qty)' ).each( function() {
            var min = parseFloat( $( this ).attr( 'min' ) );
            if ( min && min > 0 && parseFloat( $( this ).val() ) < min ) {
                $( this ).val( min );
            }
        });

        $( document ).on( 'click', '.plus, .minus', function() {
            var $qty = $( this ).closest( '.quantity' ).find( '.qty' );
			var currentVal	= parseFloat( $qty.val() );
			var max			= parseFloat( $qty.attr( 'max' ) );
			var min			= parseFloat( $qty.attr( 'min' ) );
			var step		= $qty.attr( 'step' );
            var v_min       = getMin();
            var v_max       = getMax();
            if ( v_max < 1 ) v_max = 5555;

            if (v_min != 'Nan' && v_min != '' && v_min > 0) {
                min = v_min;
            }

            if (v_max != 'Nan' && v_max != '' && v_max > 0) {
                max = v_max;
            }

            if ( ! currentVal || currentVal === '' || currentVal === 'NaN' ) currentVal = 0;
            if ( max === '' || max === 'NaN' ) max = '';
            if ( min === '' || min === 'NaN' ) min = 0;
            if ( step === 'any' || step === '' || step === undefined || parseFloat( step ) === 'NaN' ) step = 1;

            if ( $( this ).is( '.plus' ) ) {
                if ( max && ( max == currentVal || currentVal > max || currentVal > v_max ) ) {
                    $qty.val( max );
                } else {
                    var val = currentVal + parseFloat( step );
                    if (val > v_max || val > max ) {
                        $qty.val( max );
                    } else {
                        $qty.val( val );
                    }
                }
            } else {
                if ( min && ( min == currentVal || currentVal < min  || currentVal < v_min ) ) {
                    $qty.val( min );
                } else if ( currentVal > 0 ) {
                    var val = currentVal - parseFloat( step );
                    if (val < v_min || val < min ) {
                        $qty.val( min );
                    } else {
                        $qty.val( val );
                    }
                }
            }

            $qty.trigger( 'change' );
        });

        $('#pwyw_cancel_dialog').click( function () {
            pwywHideMsgDialog();
        });

        function getMin() {
            return parseFloat( get_option_data('min') );
        }

        function getMax() {
            return parseFloat( get_option_data('max') );
        }

        $(document).on( "change", ".pwyw_sku_value", function() {
            var $input_qty = $(document).find('.quantity').find('.qty');
            var sku_text = get_option_data( 'sku' );
            var sku_img  = get_option_data( 'image' );
			var min      = parseFloat( $input_qty.attr( 'min' ) );
            var v_min    = getMin();

            if (v_min != 'Nan' && v_min != '' && v_min > 0) {
                min = v_min;
            }

            if (sku_text == 'undefined') {
                sku_text = 'N/A';
            }

            if (is_use_pwyw()) {
                displayCurrentPrice();
            } else if (is_onsale()) {
                displaySales( getDisplaySales(), getDisplayPrice() );
            } else {
                displayPrice( getDisplayPrice() );
            }

            $sku.text( sku_text );
            $image.attr('src', sku_img);
            $input_qty.val(min);
        });

        function get_option_data( key ) {
            return $( ".pwyw_sku_value  option:selected" ).data( key );
        }

        function is_use_pwyw() {
            return true;
        }

        function is_onsale() {
            var sku_sales = get_option_data( 'sales' ); /* $( ".pwyw_sku_value  option:selected" ).data( 'd_sales' ); */
            var sales = parseFloat(sku_sales).toFixed(2);
            if (sales != 'Nan' && sku_sales != 'undefined' && sales > 0) {
                return true
            }
            return false;
        }

        function getDisplaySales( ) {
            var sku_sales = $( ".pwyw_sku_value  option:selected" ).data( 'd_sales' );
            return sku_sales;
        }

        function getSales( ) {
            var sku_sales = $( ".pwyw_sku_value  option:selected" ).data( 'sales' );
            return sku_sales;
        }

        function getDisplayPrice( ) {
            var sku_sales = $( ".pwyw_sku_value  option:selected" ).data( 'd_price' );
            return sku_sales;
        }

        function getPrice( ) {
            var sku_sales = $( ".pwyw_sku_value  option:selected" ).data( 'price' );
            return sku_sales;
        }

        function displaySales( sales, price ) {
            var $regular = $(document).find( '.single_variation' ).find( 'span.span_price' );
            var $sales = $(document).find( '.single_variation' ).find( 'ins' );
            var $price = $(document).find( '.single_variation' ).find( 'del' );
            $regular.css('display', 'none');
            $price.css('display', 'block');
            $sales.css('display', 'block');
            $sales.html(sales);
            $price.html(price);
        }


        function displayCurrentPrice() {
            var $regular = $(document).find( '.single_variation' ).find( 'span.span_price' );
            var $sales = $(document).find( '.single_variation' ).find( 'ins' );
            var $price = $(document).find( '.single_variation' ).find( 'del' );
            $regular.css('display', 'block');
            $price.css('display', 'none');
            $sales.css('display', 'none');
        }

        function displayPrice( price ) {
            var $regular = $(document).find( '.single_variation' ).find( 'span.span_price' );
            var $sales = $(document).find( '.single_variation' ).find( 'ins' );
            var $price = $(document).find( '.single_variation' ).find( 'del' );
            $regular.css('display', 'block');
            $price.css('display', 'none');
            $sales.css('display', 'none');
            $regular.html(price);
            $regular.html(price);
        }

    });

    $(window).resize(function () {
        pwywCenterSpinner();
        pwywCenterMsgDialog();
    });

})(window.jQuery);

    