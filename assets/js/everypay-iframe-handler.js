var shrinkIframe = function(iframe, iframe_data) {
        iframe.css(iframe_data);
        jQuery("#dimmed_background_box").remove();
    };
var expandIframe = function() {
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
        jQuery('body').append("<div id='dimmed_background_box'></div>");
        jQuery('#dimmed_background_box').css({
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
                top: 0,
            });
        } else {
            iframe.css({
                height: 640,
                width: 960,
                top: (window_height - 640) / 2,
                left: iframe.position().left - (window_width - 960) / 2
            });
        }
        iframe.css({
            position: 'absolute',
            zIndex: 9999,
            margin: 'auto'
        });
        console.log(iframe_data);
        return iframe_data;
    };
var shrinked_iframe_data;
var iframe = jQuery('#iframe-payment-container iframe');
window.addEventListener('message', function(event) {
    if (event.origin !== "https://igw-demo.every-pay.com") {
        return;
    } // production or demo URL should be used (production URL: https://pay.every-pay.eu)
    var message = JSON.parse(event.data);
    console.log(message);
    if (message.resize_iframe == "expand") {
        shrinked_iframe_data = expandIframe(iframe);
    } else if (message.resize_iframe == "shrink") {
        shrinkIframe(iframe, shrinked_iframe_data);
    }
    // It receives a message from the iframe about transaction's result. Possible states: completed, failed.
    if (message.transaction_result) {
        jQuery('.transaction_result').append(message.transaction_result);
    }
}, false);

window.onload = function() {
  document.getElementById("iframe_form").submit();
}