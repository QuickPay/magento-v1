<?php
class Quickpay_Payment_Model_Method_Klarna extends Quickpay_Payment_Model_Method_Abstract
{
    protected $_code = 'quickpay_klarna';
    protected $_formBlockType = 'quickpaypayment/payment_form_klarna';

    public function getPaymentMethods()
    {
        return 'klarna';
    }
}