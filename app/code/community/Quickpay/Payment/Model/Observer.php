<?php
class Quickpay_Payment_Model_Observer
{
    public function autoRegisterState(Varien_Event_Observer $observer)
    {
        $data = $observer->getEvent()->getControllerAction()->getRequest()->getPost();
        if (isset($data['quickpay_state'])) {
            Mage::getSingleton('core/session')->setQuickpayState($data['quickpay_state']); // Branding
        }

        return $this;
    }

    public function capture($observer)
    {
        Mage::log('start capture', null, 'qp_capture.log');

        try {
            $payment = $observer->getPayment()->getMethodInstance();

            if ($payment instanceof Quickpay_Payment_Model_Method_Abstract) {
                $invoice = $observer->getInvoice();
                Mage::log($invoice->getGrandTotal(), null, 'qp_capture.log');
                Mage::helper('quickpaypayment')->capture($payment, $invoice->getGrandTotal());
                $payment->processInvoice($invoice, $payment);
            } else {
                throw new Exception(Mage::helper('quickpaypayment')->__('Max beløb der kan refunderes'));
            }
        } catch (Exception $e) {

            Mage::throwException(Mage::helper('quickpaypayment')->__('Ikke muligt at hæve betalingen online, grundet denne fejl: %s',$e->getMessage()));
            //throw new Exception("Failed to create Invoice on online capture");
        }

        Mage::log('stop capture', null, 'qp_capture.log');
        return $this;
    }

    public function checkOrder($incrementId)
    {
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_read');
        $table = $resource->getTableName('quickpaypayment_order_status');

        $query = "SELECT * FROM $table WHERE ordernum = :order_number";
        $binds = array(
            'order_number' => $incrementId,
        );

        if ($orders = $connection->fetchAll($query, $binds)) {
            foreach ($orders as $order) {
                if (($order['qpstat'] === "20000")/* && ($order['status'] == 1 || $order['status'] == 3)*/) return true;
            }
        }
        return false;
    }

    public function returnTranscationAmount($observer)
    {
        $session = Mage::getSingleton('adminhtml/session');
        try {
            $creditmemo = $observer->getEvent()->getCreditmemo();
            $refundtotal = $creditmemo->getGrandTotal();

            // Ignore refund if done in 5 seconds with same amount
            // This makes the refund behave well, if a messy extension fires the sales_order_creditmemo_refund event twice or more
            $previousCall = $session->getTimeAndAmount();
            $nowCall = array('time' => time(),'amount' => $refundtotal);
            $session->setTimeAndAmount($nowCall);
            if (count($previousCall) > 0) {
                $timeSpacing = $nowCall['time'] - $previousCall['time'];
                $amountSpacing = $previousCall['amount'] - $nowCall['amount'];
            }

            $ignoreRefund = true;
            if (isset($timeSpacing) && isset($amountSpacing)) {
                if ($timeSpacing < 5 && $amountSpacing == 0){
                    $ignoreRefund = false;
                }
            }

            $order = Mage::getModel('sales/order')->load($creditmemo->getOrderId());
            $payment = $order->getPayment()->getMethodInstance();

            if ($payment instanceof Quickpay_Payment_Model_Method_Abstract && $ignoreRefund) {
                Mage::helper('quickpaypayment')->refund($creditmemo->getOrderId(), $refundtotal);
            }

        } catch (Exception $e) {
            $session->addException($e, Mage::helper('quickpaypayment')->__('Ikke muligt at refundere betalingen online, grundet denne fejl: %s', $e->getMessage()));
        }
        return $this;
    }

    public function cancel($observer)
    {
        $session = Mage::getSingleton('adminhtml/session');

        $order = $observer->getEvent()->getOrder();
        if (!$order->getPayment()) return $this;
        if (!$order->getPayment()->getMethodInstance()) return $this;
        $payment = $order->getPayment()->getMethodInstance();

        if (! $payment instanceof Quickpay_Payment_Model_Method_Abstract) {
            return $this;
        }

        $qp_order_check = $this->checkOrder($order->getIncrementId());

        try {
            Mage::helper('quickpaypayment')->cancel($order->getId());
        } catch (Exception $e) {
            $session->addException($e, Mage::helper('quickpaypayment')->__('Ikke muligt at annullerer betalingen online, grundet denne fejl: %s', $e->getMessage()));
        }
        /*
            if order does not exists in Quickpay table, or it was not autorized/captured, then the stock was not actually deducted,
            because this module changes the logic to deduct stock only when payment is completed. Thus, we need to account for Magento
            return-to-stock operation.
        */
        if (! $qp_order_check) {
            if ((int)Mage::getStoreConfig('cataloginventory/options/can_subtract') == 1 &&
                (int)Mage::getStoreConfig('cataloginventory/item_options/manage_stock') == 1
            ) {
                Mage::helper('quickpaypayment')->removeFromStock($order->getIncrementId());
            }
        }
        return $this;
    }

    public function addToStock($order)
    {
        $payment = Mage::getModel('quickpaypayment/payment');

        if (((int)$payment->getConfigData('handlestock')) == 1) {
            $items = $order->getAllItems(); // Get all items from the order
            if ($items) {
                foreach ($items as $item) {
                    $quantity = $item->getQtyOrdered(); // get Qty ordered
                    $product_id = $item->getProductId(); // get it's ID
                    $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product_id); // Load the stock for this product
                    $stock->setQty($stock->getQty() + $quantity); // Set to new Qty
                    $stock->setIsInStock(true);
                    $stock->save(); // Save
                }
            }
        }
    }

    /*
          The idea here is to nullify the default Magento stock decrement, and then subtract the stock ourselves when the payment is completed.
    */
    public function placeOrder($observer)
    {
        try {
            $order = $observer->getEvent()->getOrder();
            if (!$order->getPayment()) return;
            if (!$order->getPayment()->getMethodInstance()) return;
            $payment = $order->getPayment()->getMethodInstance();
            if ($payment instanceof Quickpay_Payment_Model_Method_Abstract) {
                if ((int)Mage::getStoreConfig('cataloginventory/options/can_subtract') == 1 &&
                    (int)Mage::getStoreConfig('cataloginventory/item_options/manage_stock') == 1
                ) {
                    $this->addToStock($order);
                }

            }
        } catch (Exception $e) {
        }
    }

    public function saveOrder($observer)
    {
        $session = Mage::getSingleton('adminhtml/session');

        try {
            $order = $observer->getEvent()->getOrder();
            $payment = $order->getPayment()->getMethodInstance();
            if ($payment instanceof Quickpay_Payment_Model_Method_Abstract) {
                $order->setStatus('pending');
            }

        } catch (Exception $e) {
            $session->addException($e, Mage::helper('quickpaypayment')->__("Can't change status of order", $e->getMessage()));
        }

        return $this;
    }

    /**
     * Add fraud probability to order grid
     *
     * @param  Varien_Event_Observer $observer
     * @return $this
     */
    public function onBlockHtmlBefore(Varien_Event_Observer $observer)
    {
        $block = $observer->getEvent()->getBlock();
        if (! isset($block)) return;

        if ($block->getType() === 'adminhtml/sales_order_grid') {
            /* @var $block Mage_Adminhtml_Block_Sales_Order_Grid */
            $block->addColumnAfter('fraudprobability', array(
                'header'   => '',
                'index'    => 'fraudprobability',
                'type'     => 'action',
                'filter'   => false,
                'sortable' => false,
                'width'    => '40px',
                'weight'   => '100',
                'renderer' => 'Quickpay_Payment_Model_Sales_Order_Grid_Fraudprobability',
            ), 'status');

            // order columns
            $block->addColumnsOrder('fraudprobability', 'massaction')->sortColumnsByOrder();
            $block->addColumnsOrder('massaction', 'fraudprobability')->sortColumnsByOrder();
        }
    }
}
