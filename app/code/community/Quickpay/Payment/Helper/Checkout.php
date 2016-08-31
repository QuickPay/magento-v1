<?php
class Quickpay_Payment_Helper_Checkout extends Mage_Core_Helper_Abstract
{
    /**
     * Restore last active quote based on checkout session
     *
     * @return bool True if quote restored successfully, false otherwise
     */
    public function restoreQuote()
    {
        $order = $this->_getSession()->getLastRealOrder();

        if ($order->getId()) {
            $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
            if ($quote->getId()) {
                $quote->setIsActive(1)
                    ->setReservedOrderId(null)
                    ->save();
                $this->_getSession()
                    ->replaceQuote($quote)
                    ->unsLastRealOrderId();

                return true;
            }
        }
        return false;
    }

    /**
     * Return checkout session
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('checkout/session');
    }
}