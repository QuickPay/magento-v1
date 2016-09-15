<?php
class Quickpay_Payment_Block_Payment_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        $this->setTemplate('quickpaypayment/payment/form.phtml');
        parent::_construct();
    }
}
