<?php

class QuickPay_Payment_Model_System_Config_Source_Specificcardtypes
{

  public function toOptionArray()
  {
    return array(
      array('value' => 'american-express', 'label' => Mage::helper('quickpaypayment')->__('American Express')),
      //array('value' => 'american-express-dk', 'label' => Mage::helper('quickpaypayment')->__('American Express (udstedt i Danmark)')),
      array('value' => 'dankort', 'label' => Mage::helper('quickpaypayment')->__('Dankort')),
      //array('value' => 'danske-dk', 'label' => Mage::helper('quickpaypayment')->__('Danske Net Bank')),
      array('value' => 'diners', 'label' => Mage::helper('quickpaypayment')->__('Diners Club')),
      //array('value' => 'diners-dk', 'label' => Mage::helper('quickpaypayment')->__('Diners Club (udstedt i Danmark)')),
      array('value' => 'edankort', 'label' => Mage::helper('quickpaypayment')->__('eDankort')),
      array('value' => 'fbg1886', 'label' => Mage::helper('quickpaypayment')->__('Forbrugsforeningen af 1886')),
      array('value' => 'jcb', 'label' => Mage::helper('quickpaypayment')->__('JCB')),
      array('value' => 'mastercard', 'label' => Mage::helper('quickpaypayment')->__('Mastercard')),
      //array('value' => 'mastercard-dk', 'label' => Mage::helper('quickpaypayment')->__('Mastercard (udstedt i Danmark)')),
      array('value' => 'mastercard-debet-dk', 'label' => Mage::helper('quickpaypayment')->__('Mastercard Debet (udstedt i Danmark)')),
      array('value' => 'mobilepay', 'label' => Mage::helper('quickpaypayment')->__('Mobilepay')),
      //array('value' => 'nordea-dk', 'label' => Mage::helper('quickpaypayment')->__('Nordea Net Bank')),
      array('value' => 'visa', 'label' => Mage::helper('quickpaypayment')->__('Visa')),
      //array('value' => 'visa-dk', 'label' => Mage::helper('quickpaypayment')->__('Visa (udstedt i Danmark)')),
      //array('value' => 'visa-electron', 'label' => Mage::helper('quickpaypayment')->__('Visa Electron')),
      array('value' => 'visa-electron-dk', 'label' => Mage::helper('quickpaypayment')->__('Visa Electron (udstedt i Danmark)')),
      array('value' => 'paypal', 'label' => Mage::helper('quickpaypayment')->__('PayPal')),
      array('value' => 'sofort', 'label' => Mage::helper('quickpaypayment')->__('Sofort')),
      array('value' => 'viabill', 'label' => Mage::helper('quickpaypayment')->__('Viabill')),
      array('value' => 'maestro', 'label' => Mage::helper('quickpaypayment')->__('Maestro debit card')),
      array('value' => 'paii', 'label' => Mage::helper('quickpaypayment')->__('Paii'))
    );
  }

}
