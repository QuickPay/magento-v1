<?php
class Quickpay_Payment_Model_Method_Paypal extends Quickpay_Payment_Model_Method_Abstract
{
    protected $_code = 'quickpay_paypal';
    protected $_formBlockType = 'quickpaypayment/payment_form_paypal';

    public function getPaymentMethods()
    {
        return 'paypal';
    }
}