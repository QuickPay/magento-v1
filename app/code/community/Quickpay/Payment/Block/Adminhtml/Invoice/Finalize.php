<?php

class Quickpay_Payment_Block_Adminhtml_Invoice_Finalize extends Mage_Core_Block_Template {

    /**
     * Retrieve invoice model instance
     *
     * @return Mage_Sales_Model_Invoice
     */
    public function getInvoice()
    {
        return Mage::registry('current_invoice');
    }

    public function isQuickpayOrder() {
        return Mage::helper('quickpaypayment/order')->isQuickpayOrder($this->getInvoice()->getOrder());
    }

    protected function _toHtml() {
        if($this->isQuickpayOrder()) {
            return parent::_toHtml();
        }
    }
    
}
