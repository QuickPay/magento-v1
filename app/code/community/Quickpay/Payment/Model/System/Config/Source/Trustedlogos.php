<?php
class Quickpay_Payment_Model_System_Config_Source_Trustedlogos
{
    public function toOptionArray()
    {
        return array(
            array('value' => 'verisign_secure', 'label' => Mage::helper('quickpaypayment')->__('Verified by VISA')),
            array('value' => 'mastercard_securecode', 'label' => Mage::helper('quickpaypayment')->__('MasterCard Secure Code')),
            array('value' => 'pci', 'label' => Mage::helper('quickpaypayment')->__('PCI')),
            array('value' => 'nets', 'label' => Mage::helper('quickpaypayment')->__('Nets')),
            array('value' => 'euroline', 'label' => Mage::helper('quickpaypayment')->__('Euroline')),
        );
    }
}
