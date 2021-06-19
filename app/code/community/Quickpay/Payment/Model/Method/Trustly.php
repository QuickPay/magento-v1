<?php
class Quickpay_Payment_Model_Method_Trustly extends Quickpay_Payment_Model_Method_Abstract
{
    protected $_code = 'quickpay_trustly';
    protected $_formBlockType = 'quickpaypayment/payment_form_trustly';

    public function getPaymentMethods()
    {
        return 'trustly';
    }
}