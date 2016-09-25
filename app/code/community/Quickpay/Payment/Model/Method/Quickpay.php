<?php
class Quickpay_Payment_Model_Method_Quickpay extends Quickpay_Payment_Model_Method_Abstract
{
    protected $_code = 'quickpaypayment_payment';

    public function getPaymentMethods()
    {
        if ($this->getConfigData('payment_method') === 'specified') {
            return $this->getConfigData('payment_method_specified');
        }

        return $this->getConfigData('payment_method');
    }
}