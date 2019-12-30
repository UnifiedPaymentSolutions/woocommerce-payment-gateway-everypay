<?php

class WC_Everypay_Helper
{
    /**
     * @var string[]
     */
    protected static $allowed_countries = array('EE', 'LT', 'LV');
    
    /**
     * @var string
     */
    protected static $default_country = 'EE';

    /**
     * @var array[]
     */
    protected static $locales = array(
        'EE' => array('et', 'et_EE'),
        'LT' => array('lt', 'lt_LT'),
        'LV' => array('lv', 'lv_LV'),
        'RU' => array('ru', 'ru_RU')
    );

    /**
     * @var string
     */
    protected static $default_locale = 'EN';

    /**
     * Get locale used for gateway.
     *
     * @return string
     */
    public static function get_locale()
    {
        if(defined('ICL_LANGUAGE_CODE')) {
            $code = ICL_LANGUAGE_CODE;
        } else {
            $code = get_locale();
        }

        $code = strtolower($code);

        foreach (self::$locales as $locale => $codes) {
            if(in_array($code, $codes)) {
                return $locale;
            }
        }

        return self::$default_locale;
    }

    /**
     * Returns preferred country for order.
     *
     * @param WC_Order $order
     * @return string
     */
    public static function get_order_preferred_country(WC_Order $order)
    {
        $country = $order->get_meta(WC_Gateway_Everypay::META_COUNTRY);

        if(!$country) {
            $country = self::get_locale();
        }

        return in_array($country, self::$allowed_countries) ? $country : null;
    }

    /**
     * Filters payment methods array by type.
     *
     * @param array $payment_methods
     * @param string $type
     * @return array
     */
    public static function filter_payment_methods($payment_methods, $type)
    {
        return array_filter($payment_methods, function($payment_method) use ($type) {

            $card = strpos($payment_method->source, 'card') !== false;
            $bank = strpos($payment_method->source, '_ob_') !== false;

            switch ($type) {
                case WC_Gateway_Everypay::TYPE_CARD:
                    return $card;
                case WC_Gateway_Everypay::TYPE_BANK:
                    return $bank;
                case WC_Gateway_Everypay::TYPE_ALTER:
                    return !$card && !$bank;
                default:
                    return true;
            }
        });
    }

    /**
     * Checks existence of payment methods by type.
     *
     * @param array $payment_methods
     * @param string $type
     * @return array
     */
    public static function has_payment_methods($payment_methods, $type)
    {
        return count(self::filter_payment_methods($payment_methods, $type)) !== 0;
    }

    /**
     * Get preferred country.
     *
     * @return string
     */
    public static function get_preferred_country()
    {
        $locale = self::get_locale();
        return in_array($locale, self::$allowed_countries) ? $locale : self::$default_country;
    }

}