<?php
class Quickpay_Payment_Block_Payment_Redirect extends Mage_Core_Block_Template
{
    public function __construct()
    {
        parent::__construct();

        $this->setTemplate('quickpaypayment/payment/redirect/paymentwindow.phtml');

        $payment = Mage::getModel('quickpaypayment/payment');

        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_write');
        $table = $resource->getTableName('quickpaypayment_order_status');
        $connection->insert($table, array('ordernum' => $payment->getCheckout()->getLastRealOrderId()));
    }
}
