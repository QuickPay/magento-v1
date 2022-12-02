<?php
class Quickpay_Payment_Model_Method_Vipps extends Quickpay_Payment_Model_Method_Abstract
{
    protected $_code = 'quickpay_vipps';
    protected $_formBlockType = 'quickpaypayment/payment_form_vipps';

    public function getPaymentMethods()
    {
        return 'vipps,vippspsp';
    }
}