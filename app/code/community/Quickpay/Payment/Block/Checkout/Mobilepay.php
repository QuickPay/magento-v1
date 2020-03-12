<?php

class Quickpay_Payment_Block_Checkout_Mobilepay extends Mage_Core_Block_Template
{
    const MOBILEPAY_ACTICE_XML_PATH      = 'payment/quickpay_mobilepay/active';
    const MOBILEPAY_TITLE_XML_PATH      = 'payment/quickpay_mobilepay/title';
    const MOBILEPAY_DESCRIPTION_XML_PATH  = 'payment/quickpay_mobilepay/instructions';
    const MOBILEPAY_POPUP_DESCRIPTION_XML_PATH  = 'payment/quickpay_mobilepay/popup_description';

    /**
     * @return mixed
     */
    public function getTitle(){
        return Mage::getStoreConfig(self::MOBILEPAY_TITLE_XML_PATH, Mage::app()->getStore());
    }

    /**
     * @return mixed
     */
    public function getDescription(){
        return Mage::getStoreConfig(self::MOBILEPAY_DESCRIPTION_XML_PATH, Mage::app()->getStore());
    }

    /**
     * @return mixed
     */
    public function getPopupDescription(){
        return Mage::getStoreConfig(self::MOBILEPAY_POPUP_DESCRIPTION_XML_PATH, Mage::app()->getStore());
    }

    /**
     * @return mixed
     */
    public function isActive(){
        return Mage::getStoreConfig(self::MOBILEPAY_ACTICE_XML_PATH, Mage::app()->getStore());
    }

    /**
     * @return string
     */
    public function getRedirectUrl(){
        return $this->getUrl('quickpaypayment/mobilepay/redirect');
    }

    /**
     * @return mixed
     */
    public function getShippingMethods(){
        return Mage::getModel('quickpaypayment/carrier_shipping')->getMobilePayMethods();
    }
}