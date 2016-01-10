var shrinkIframe = function (iframe, iframe_data) {
    iframe.css(iframe_data);
    jQuery("#wc_everypay_dimmed_background_box").remove();
};

var expandIframe = function () {
    var iframe_data = {
        position: iframe.attr("position") || "static",
        top: iframe.position().top,
        left: iframe.position().left,
        width: iframe.width(),
        height: iframe.height(),
        zIndex: iframe.attr("zIndex"),
        marginLeft: iframe.attr("marginLeft"),
        marginRight: iframe.attr("marginRight")
    };
    jQuery('body').append("<div id='wc_everypay_dimmed_background_box'></div>");
    jQuery('#wc_everypay_dimmed_background_box').css({
        height: '100%',
        width: '100%',
        position: 'fixed',
        top: 0,
        left: 0,
        zIndex: 9998,
        backgroundColor: '#000000',
        opacity: 0.5
    });
    var window_height = jQuery(window).height();
    var window_width = jQuery(window).width();
    if (window_width < 960) {
        iframe.css({
            height: window_height,
            width: window_width,
            top: 0
        });
    } else {
        iframe.css({
            height: 640,
            width: 960,
            top: (window_height - 640) / 2,
            left: (window_width - 960) / 2
        });
    }
    iframe.css({
        position: 'fixed',
        zIndex: 9999,
        margin: 'auto'
    });
    if (true == wc_everypay_params.sandbox) {
        console.log(iframe_data);
    }
    return iframe_data;
};

var shrinked_iframe_data;

var iframe = jQuery('#wc_everypay_iframe');

window.addEventListener('message', function (event) {

    if (event.origin !== wc_everypay_params.uri) {
        if (true === wc_everypay_params.sandbox) {
            console.log('Received message from non-authorised origin ' + event.origin + ', expected ' + wc_everypay_params.uri);
        }
        return;
    }
    var message = JSON.parse(event.data);

    if (true == wc_everypay_params.sandbox) {
        console.log(message);
    }

    // resize messages
    if (message.resize_iframe == "expand") {
        shrinked_iframe_data = expandIframe(iframe);
    } else if (message.resize_iframe == "shrink") {
        shrinkIframe(iframe, shrinked_iframe_data);
    }
    // transaction result message, possible states: completed, failed
    if (message.transaction_result) {
        // used during testing:
        // jQuery('.transaction_result').append(message.transaction_result);

        if ('completed' === message.transaction_result) {
            window.location = wc_everypay_params.completed;
        } else {
            // window.location = wc_everypay_params.failed;


            messager = jQuery("#wc_everypay_iframe_messager");
            // messaging area is present for token payments with hidden iframe
            if (messager.length) {
                message_html = '';
                message_html += '<p class="wc_everypay_iframe_message_title">' + message.message_title + '</p>';
                if (message.message_error.length) {
                    message_html += '<p>' + message.message_error + '</p>';
                }
                if (message.message_action.length) {
                    message_html += '<p>' + message.message_action + '</p>';
                }
                if (message.message_contact.length) {
                    message_html += '<p>' + message.message_contact + '</p>';
                }
                messager.html(message_html);
                
                jQuery('#wc_everypay_iframe_retry').show();
            } else {
                jQuery('#wc_everypay_iframe_cancel').show();
                jQuery('#wc_everypay_iframe_retry').show();
            }
        }

    }
}, false);

window.onload = function () {
    document.getElementById("wc_everypay_iframe_form").submit();
};
