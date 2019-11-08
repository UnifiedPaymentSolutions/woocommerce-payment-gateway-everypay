<?php

class WC_Everypay_Logger extends WC_Logger
{
    /**
     * @var boolean
     */
    protected $is_debug = false;

    /**
     * @var string
     */
    protected $handle = 'everypay';

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, $context = array())
    {
        $context['source'] = $this->handle;
        parent::log($level, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function debug($message, $context = array())
    {
        if($this->is_debug) {
            parent::debug($message, $context);
        }
    }

    /**
     * Enable debug logs.
     *
     * @param boolean $enabled
     * @return $this
     */
    public function set_debug($enabled)
    {
        $this->is_debug = $enabled ? true : false;
        return $this;
    }
}