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

    /**
     * Send payment link to customer when admin creates an order
     *
     * @param Varien_Event_Observer $observer
     */
    public function onCheckoutSubmitAllAfter(Varien_Event_Observer $observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        $payment = Mage::getSingleton('quickpaypayment/payment');

        $parameters = array(
            "agreement_id"                 => $payment->getConfigData("agreementid"),
            "amount"                       => $order->getTotalDue() * 100,
            "continueurl"                  => $this->getSuccessUrl($order->getStore()),
            "cancelurl"                    => $this->getCancelUrl($order->getStore()),
            "callbackurl"                  => $this->getCallbackUrl($order->getStore()),
            "language"                     => $payment->calcLanguage(Mage::app()->getLocale()->getLocaleCode()),
            "autocapture"                  => $payment->getConfigData('instantcapture'),
            "autofee"                      => $payment->getConfigData('transactionfee'),
            "payment_methods"              => $order->getPayment()->getMethodInstance()->getPaymentMethods(),
            "google_analytics_tracking_id" => $payment->getConfigData('googleanalyticstracking'),
            "google_analytics_client_id"   => $payment->getConfigData('googleanalyticsclientid'),
            "customer_email"               => $order->getCustomerEmail() ?: '',
        );

        $result = Mage::helper('quickpaypayment')->qpCreatePayment($order);
        $result = Mage::helper('quickpaypayment')->qpCreatePaymentLink($result->id, $parameters);

        $paymentUrl = $result->url;

        //Send payment link email to customer

        /** @var Mage_Core_Model_Email_Template $mailTemplate */
        $mailTemplate = Mage::getModel('core/email_template');

        $emailVars = array(
            'increment_id' => $order->getIncrementId(),
            'paymentlink' => $paymentUrl,
        );

        $mailTemplate->setDesignConfig(array(
            'area'  => 'frontend',
            'store' => $order->getStoreId(),
        ));

        $mailTemplate->sendTransactional(
            'quickpay_payment_link',
            'sales',
            $order->getBillingAddress()->getEmail(),
            $order->getBillingAddress()->getFirstname() . ' ' . $order->getBillingAddress()->getLastname(),
            $emailVars,
            $order->getStoreId()
        );

        Mage::log($order->debug(), null, 'qp_order.log');

    }

    /**
     * Get success url
     *
     * @return string
     */
    public function getSuccessUrl(Mage_Core_Model_Store $store)
    {
        return $store->getUrl('quickpaypayment/payment/linksuccess');
    }

    /**
     * Get cancel url
     *
     * @return string
     */
    public function getCancelUrl(Mage_Core_Model_Store $store)
    {
        return $store->getUrl('quickpaypayment/payment/cancel');
    }

    /**
     * Get callback url
     *
     * @return string
     */
    public function getCallbackUrl(Mage_Core_Model_Store $store)
    {
        return $store->getUrl('quickpaypayment/payment/callback');
    }
}
