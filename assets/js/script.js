/**
 * Payment methods select class.
 *
 * @param string method_name
 * @return this
 */
function PaymentMethodsSelect(method_name)
{
    var _selectors = {
        wrapper: 'li.payment_method_' + method_name,
        labels: 'label.payment-method-option, label.payment-token-option',
        method_inputs: 'label.payment-method-option input',
        token_inputs: 'label.payment-token-option input',
        language_inputs: '.method-languages input'
    };

    var _listeners = {
        method_inputs: undefined,
        token_inputs: undefined,
        language_inputs: undefined
    };

    /**
     * Change language event.
     *
     * @param DOM radio
     * @return void
     */
    this.change_language = function(radio) {
        var self = this,
            country = radio.value;

        this.unselect();
        jQuery(_selectors.token_inputs, _selectors.wrapper).prop('checked', false);
        jQuery(_selectors.method_inputs, _selectors.wrapper).prop('checked', false).each(function() {
            var language = jQuery(this).data('language');
            if(!language || language == country) {
                jQuery(this).parents(_selectors.labels).removeClass('hidden');
            } else {
                jQuery(this).parents(_selectors.labels).addClass('hidden');
            }
        });
    };

    /**
     * Change method event.
     *
     * @param DOM radio
     * @return void
     */
    this.select = function(radio) {
        this.unselect();
        if(radio.checked) {
            jQuery(radio).parents(_selectors.labels).addClass('selected');
        }
    };

    /**
     * Deselect all methods.
     *
     * @return void
     */
    this.unselect = function() {
        jQuery(_selectors.labels, _selectors.wrapper).removeClass('selected');
    };

    /**
     * Update events.
     *
     * @return void
     */
    this.update = function() {
        this.clear();
        this.listeners();
    };

    /**
     * Remove events.
     *
     * @return void
     */
    this.clear = function() {
        if(_listeners.method_inputs) {
            _listeners.method_inputs.unbind('change');
        }
        if(_listeners.token_inputs) {
            _listeners.token_inputs.unbind('change');
        }
        if(_listeners.language_inputs) {
            _listeners.language_inputs.unbind('change');
        }
    };

    /**
     * Add events.
     *
     * @return void
     */
    this.listeners = function() {
        var self = this;
 
        _listeners.method_inputs = jQuery(_selectors.method_inputs, _selectors.wrapper).on('change', function(event) {
            jQuery(_selectors.token_inputs, _selectors.wrapper).prop('checked', false);
            self.select.call(self, this);
        });

        _listeners.token_inputs = jQuery(_selectors.token_inputs, _selectors.wrapper).on('change', function(event) {
            jQuery(_selectors.method_inputs, _selectors.wrapper).prop('checked', false);
            self.select.call(self, this);
        });

        _listeners.language_inputs = jQuery(_selectors.language_inputs, _selectors.wrapper).on('change', function(event) {
            self.change_language.call(self, this);
        });
    };
}


jQuery(function($) {
    var payment_methods_select = new PaymentMethodsSelect(payment_method_settings.name);

    $('body').on('updated_checkout', function() {
        payment_methods_select.update();
    });
});