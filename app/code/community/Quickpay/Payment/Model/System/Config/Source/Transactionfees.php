<?php
class Quickpay_Payment_Model_System_Config_Source_Transactionfees
{
    public function toOptionArray()
    {
        return array(
            // DANSKE
            array('value' => 'american-express-dk', 'label' => Mage::helper('quickpaypayment')->__('American Express (Dansk)')),
            array('value' => 'dankort', 'label' => Mage::helper('quickpaypayment')->__('Dankort')),
            array('value' => 'diners-dk', 'label' => Mage::helper('quickpaypayment')->__('Diners (Dansk)')),
            array('value' => 'edankort', 'label' => Mage::helper('quickpaypayment')->__('edankort')),
            array('value' => 'maestro-dk', 'label' => Mage::helper('quickpaypayment')->__('Maestro (Dansk)')),
            array('value' => 'mastercard-dk', 'label' => Mage::helper('quickpaypayment')->__('Mastercard (Dansk)')),
            array('value' => 'mastercard-debet-dk', 'label' => Mage::helper('quickpaypayment')->__('Mastercard debit (Dansk)')),
            array('value' => 'mobilepay', 'label' => Mage::helper('quickpaypayment')->__('Mobilepay')),
            array('value' => 'visa-dk', 'label' => Mage::helper('quickpaypayment')->__('Visa (Dansk)')),
            array('value' => 'visa-electron-dk', 'label' => Mage::helper('quickpaypayment')->__('Visa Electron (Dansk)')),
            array('value' => 'fbg1886', 'label' => Mage::helper('quickpaypayment')->__('Forbrugsforeningen')),

            // UDENLANDSKE
            array('value' => 'american-express', 'label' => Mage::helper('quickpaypayment')->__('American Express')),
            array('value' => 'diners', 'label' => Mage::helper('quickpaypayment')->__('Diners')),
            array('value' => 'jcb', 'label' => Mage::helper('quickpaypayment')->__('JCB')),
            array('value' => 'maestro', 'label' => Mage::helper('quickpaypayment')->__('Maestro')),
            array('value' => 'mastercard', 'label' => Mage::helper('quickpaypayment')->__('Mastercard')),
            array('value' => 'visa', 'label' => Mage::helper('quickpaypayment')->__('Visa')),
            array('value' => 'visa-electron', 'label' => Mage::helper('quickpaypayment')->__('Visa Electron'))
        );
    }
}
