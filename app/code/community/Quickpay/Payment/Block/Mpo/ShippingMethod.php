<?php
class Quickpay_Payment_Block_Mpo_ShippingMethod extends Mage_Checkout_Block_Onepage_Shipping_Method_Available
{
    /**
     * Get save shipping url
     *
     * @return string
     */
    public function getPostActionUrl()
    {
        return $this->getUrl('*/*/shippingPost');
    }

    /**
     * Get Cart URL
     *
     * @return mixed
     */
    public function getBackUrl()
    {
        return Mage::helper('checkout/cart')->getCartUrl();
    }
}