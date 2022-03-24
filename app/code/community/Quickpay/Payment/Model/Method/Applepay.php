<?php
class Quickpay_Payment_Model_Method_Applepay extends Quickpay_Payment_Model_Method_Abstract
{
    protected $_code = 'quickpay_applepay';
    protected $_formBlockType = 'quickpaypayment/payment_form_applepay';

    public function getPaymentMethods()
    {
        return 'apple-pay';
    }

    public function canUseCheckout()
    {
        return $this->isSafariBrowser();
    }

    public function isSafariBrowser(){
        $u_agent = $_SERVER['HTTP_USER_AGENT'];
        $issafari = false;
        if (preg_match('/Safari/i',$u_agent)){
            $issafari = (!preg_match('/Chrome/i',$u_agent));
        }

        return $issafari;
    }
}