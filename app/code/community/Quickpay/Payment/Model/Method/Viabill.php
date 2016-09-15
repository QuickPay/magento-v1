<?php
class Quickpay_Payment_Model_Method_Viabill extends Quickpay_Payment_Model_Method_Abstract
{
    protected $_code = 'quickpay_viabill';
    protected $_formBlockType = 'quickpaypayment/payment_form_viabill';

    public function getPaymentMethods()
    {
        return 'viabill';
    }
}