<?php
class Quickpay_Payment_Model_Method_Anyday extends Quickpay_Payment_Model_Method_Abstract
{
    protected $_code = 'quickpay_anyday';
    protected $_formBlockType = 'quickpaypayment/payment_form_anyday';

    public function getPaymentMethods()
    {
        return 'anyday-split';
    }
}