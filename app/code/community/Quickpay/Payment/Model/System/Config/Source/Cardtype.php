<?php
class QuickPay_Payment_Model_System_Config_Source_Cardtype
{
    public function toOptionArray()
    {
        return array(
            array('value' => '', 'label' => Mage::helper('quickpaypayment')->__('Alle betalingsmetoder')),
            array('value' => 'creditcard', 'label' => Mage::helper('quickpaypayment')->__('Alle kreditkort')),
            array('value' => 'specific-cards', 'label' => Mage::helper('quickpaypayment')->__('Vælg specifikke betalingsmetoder')),
        );
    }
}
