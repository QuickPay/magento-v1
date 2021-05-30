<?php
class QuickPay_Payment_Model_System_Config_Source_PaymentMethods
{
    public function toOptionArray()
    {
        return array(
            array('value' => '', 'label' => Mage::helper('quickpaypayment')->__('All payment methods')),
            array('value' => 'creditcard', 'label' => Mage::helper('quickpaypayment')->__('All credit cards')),
            array('value' => 'specified', 'label' => Mage::helper('quickpaypayment')->__('As defined')),
        );
    }
}
