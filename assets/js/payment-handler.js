jQuery(function($) {

    function PingOrderStatus(settings)
    {
        var ajax_url = settings.ajax_url,
            order_id = settings.order_id,
            redirect_url = settings.redirect_url
            cancel_url = settings.cancel_url;

        var interval,
            timeout,
            requestInProgress = false,
            pingActive = false,
            redirectActive = false;

        var intervalTimeout = 4000,
            pingLimitTimeout = 30000;

        var messages = {
            $pending: $('.ping-message-wrapper .status-message.pending'),
            $success: $('.ping-message-wrapper .status-message.success'),
            $failed: $('.ping-message-wrapper .status-message.failed'),
            hide: function() {
                this.$pending.hide();
                return this;
            }
        };

        var pingOrderStatus = function() {
            if(requestInProgress) return;

            requestInProgress = true;
            var data = {
                'action': 'wc_payment_ping_status',
                'order_id': order_id
            };

            $.post(ajax_url, data).always($.proxy(function(response) {
                requestInProgress = false;
                handleResponse(response);
            }, this));
        };

        var handleResponse = function(response) {
            if(pingActive) {
                if(response !== 'PENDING') {

                    this.stop();

                    if(response === 'SUCCESS') {
                        messages.hide().$success.show();
                    } else {
                        messages.hide().$failed.show();
                    }
                      
                    redirect();
                } else {
                    messages.hide().$pending.show();
                }
            }
        };

        var callbackTimeout = function() {
            if(!redirectActive) {
                this.stop();
                messages.hide().$failed.show();
                redirect();
            }
        };

        var redirect = function() {
            if(!redirectActive) {
                redirectActive = true;
                setTimeout(function() {
                    window.location.href = redirect_url;
                }, 2000);
            }
        };

        this.start = function() {
            pingActive = true;
            messages.hide().$pending.show();
            interval = setInterval($.proxy(pingOrderStatus, this), intervalTimeout);
            timeout = setTimeout($.proxy(callbackTimeout, this), pingLimitTimeout);
        }

        this.stop = function() {
            pingActive = false;
            clearInterval(interval);
            clearTimeout(timeout);
        }
    }

    var ping = new PingOrderStatus({
        ajax_url: wc_payment_params.ajax_url,
        order_id: wc_payment_params.order_id,
        redirect_url: wc_payment_params.redirect
    });

    // Start status ping instantly
    if(wc_payment_params.ping) {
        ping.start();
    }

    var $iframe = $('#wc_payment_iframe');
    var eventMethod = window.addEventListener ? "addEventListener" : "attachEvent",
        eventer = window[eventMethod],
        messageEvent = eventMethod == "attachEvent" ? "onmessage" : "message";

    // Start status ping when child iframe sends message
    eventer(messageEvent, function(event) {
        if(event.origin === wc_payment_params.uri && event.data == 'start_ping') {
            $iframe.hide();
            ping.start();
        }
    }, false);
});
