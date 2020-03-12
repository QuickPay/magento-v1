<?php

class Quickpay_Payment_Adminhtml_QuickpayController extends Mage_Adminhtml_Controller_Action
{
    public function massCaptureAction()
    {
        $orderIds = $this->getRequest()->getPost('order_ids', array());

        foreach ($orderIds as $orderId) {
            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel("sales/order")->load($orderId);

            if (!$order->getPayment()->getMethodInstance() instanceof Quickpay_Payment_Model_Method_Abstract) {
                $this->_getSession()->addError($this->__('%s Order was not placed using QuickPay', $order->getIncrementId()));
                continue;
            }

            try {

                if (!$order->canInvoice()) {
                    $this->_getSession()->addError($this->__('Could not create invoice for %s', $order->getIncrementId()));
                }

                /* @var $invoice Mage_Sales_Model_Order_Invoice */
                $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

                if (!$invoice->getTotalQty()) {
                    $this->_getSession()->addError($this->__('Cannot create an invoice without products for %s.', $order->getIncrementId()));
                }

                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                $invoice->register();

                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());

                $transactionSave->save();
            } catch (Exception $e) {
                $this->_getSession()->addError($this->__('Invoice and capture failed for %s: %s', $order->getIncrementId(), $e->getMessage()));
            }
        }

        $this->_redirect('*/sales_order/');
    }
}