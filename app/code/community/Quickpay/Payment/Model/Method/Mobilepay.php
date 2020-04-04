<?php
class Quickpay_Payment_Model_Method_Mobilepay extends Quickpay_Payment_Model_Method_Abstract
{
    protected $_code = 'quickpay_mobilepay';
    protected $_formBlockType = 'quickpaypayment/payment_form_mobilepay';

    public function getPaymentMethods()
    {
        return 'mobilepay';
    }
}