<?php
class QuickPay_Payment_Model_System_Config_Source_Specificcardtypes
{
    public function toOptionArray()
    {
        return array(
            array('value' => 'american-express', 'label' => Mage::helper('quickpaypayment')->__('American Express')),
            array('value' => 'dankort', 'label' => Mage::helper('quickpaypayment')->__('Dankort')),
            array('value' => 'diners', 'label' => Mage::helper('quickpaypayment')->__('Diners Club')),
            array('value' => 'edankort', 'label' => Mage::helper('quickpaypayment')->__('eDankort')),
            array('value' => 'fbg1886', 'label' => Mage::helper('quickpaypayment')->__('Forbrugsforeningen af 1886')),
            array('value' => 'jcb', 'label' => Mage::helper('quickpaypayment')->__('JCB')),
            array('value' => 'maestro', 'label' => Mage::helper('quickpaypayment')->__('Maestro debit card')),
            array('value' => 'mastercard', 'label' => Mage::helper('quickpaypayment')->__('Mastercard')),
            array('value' => 'mastercard-debet', 'label' => Mage::helper('quickpaypayment')->__('Mastercard Debet')),
            array('value' => 'mobilepay', 'label' => Mage::helper('quickpaypayment')->__('MobilePay Online')),
            array('value' => 'visa', 'label' => Mage::helper('quickpaypayment')->__('Visa')),
            array('value' => 'visa-electron', 'label' => Mage::helper('quickpaypayment')->__('Visa Electron')),
            array('value' => 'paypal', 'label' => Mage::helper('quickpaypayment')->__('PayPal')),
            array('value' => 'sofort', 'label' => Mage::helper('quickpaypayment')->__('Sofort')),
            array('value' => 'viabill', 'label' => Mage::helper('quickpaypayment')->__('ViaBill')),
            array('value' => 'klarna', 'label' => Mage::helper('quickpaypayment')->__('Klarna')),
            array('value' => 'swipp', 'label' => Mage::helper('quickpaypayment')->__('Swipp')),
        );
    }
}
