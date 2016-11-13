<?php
class Quickpay_Payment_Block_System_Config_Form_Field_Version extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return Mage::helper('quickpaypayment')->getInstalledVersion();
    }
}