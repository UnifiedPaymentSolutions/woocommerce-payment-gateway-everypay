<?php

namespace Everypay;

if(!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly.

use WC_Order;

class Helper
{
    /**
     * @var string[]
     */
    protected static $allowed_countries = array('EE', 'LT', 'LV');

    /**
     * @var array[]
     */
    protected static $locales = array(
        'EN' => array('en', 'en_US', 'en_AU', 'en_CA', 'en_NZ', 'en_GB'),
        'EE' => array('et', 'et_EE'),
        'FI' => array('fi', 'fi_FI'),
        'DE' => array('de', 'de_DE', 'de_AT', 'de_CH'),
        'LV' => array('lv', 'lv_LV'),
        'LT' => array('lt', 'lt_LT'),
        'RU' => array('ru', 'ru_RU'),
        'ES' => array('es', 'es_ES', 'es_AR', 'es_MX'),
        'SV' => array('sv', 'sv_SE'),
        'DA' => array('da', 'da_DK'),
        'PL' => array('pl', 'pl_PL'),
        'IT' => array('it', 'it_IT'),
        'FR' => array('fr', 'fr_FR', 'fr_CA'),
        'NL' => array('nl', 'nl_NL', 'nl_BE'),
        'PT' => array('pt', 'pt-br', 'pt-pt', 'pt_BR', 'pt_PT'),
        'NO' => array('no', 'nb_NO', 'nn_NO')
    );

    /**
     * @var string
     */
    protected static $default_locale = 'en';

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
        $country = $order->get_meta(Gateway::META_COUNTRY);

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
        $sources = array();
        return array_filter($payment_methods, function($payment_method) use ($type, &$sources) {

            if(in_array($payment_method->source, $sources)) {
                return false;
            }
            array_push($sources, $payment_method->source);

            $card = strpos($payment_method->source, 'card') !== false;
            $bank = strpos($payment_method->source, '_ob_') !== false;

            switch ($type) {
                case Gateway::TYPE_CARD:
                    return $card;
                case Gateway::TYPE_BANK:
                    return $bank;
                case Gateway::TYPE_ALTER:
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
     * If available countries supplied and
     * preferred country not listed
     * then returns first available country.
     *
     * @param array $available_countries
     * @param string|null $default_country
     * @return string
     */
    public static function get_preferred_country($available_countries, $default_country = null)
    {
        $preferred = null;

        if($default_country) {
            $preferred = $default_country;
        } else {
            $locale = self::get_locale();
            if(in_array($locale, self::$allowed_countries)) {
                $preferred = $locale;
            }
        }

        if(is_null($preferred) || !in_array($preferred, $available_countries)) {
            $preferred = reset($available_countries);
        }

        return $preferred;
    }

    /**
     * Convert string suitable for api url.
     *
     * @param string $string
     * @return string
     */
    public static function api_url_case($string)
    {
        $parts = explode('_', $string);
        $parts = array_map('ucfirst', $parts);
        return implode('_', $parts);
    }
}