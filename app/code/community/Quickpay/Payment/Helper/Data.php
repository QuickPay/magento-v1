<?php
class Quickpay_Payment_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $connTimeout = 10;
    // The connection timeout to Quickpay gateway
    protected $apiUrl = "https://api.quickpay.net";
    protected $apiVersion = 'v10';
    protected $apiKey = "";
    // Loaded from the configuration
    protected $format = "application/json";

    /**
     * Send a request to Quickpay.
     */
    function request($resource, $postdata = null, $synchronized="?synchronized")
    {
        if (!function_exists('curl_init')) {
            Mage::throwException('CURL is not installed, please install curl');
        }

        $curl = curl_init();
        $url = $this->apiUrl . "/" . $resource . $synchronized;
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->connTimeout);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . base64_encode(":" . $this->apiKey), 'Accept-Version: ' . $this->apiVersion, 'Accept: ' . $this->format));

        if (! is_null($postdata)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postdata));
        }

        $response = curl_exec($curl);

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (! in_array($httpCode, array(200, 201, 202))) {
            Mage::throwException($response);
        }

        return $response;
    }

    function put($resource, $postdata = null)
    {
        $curl = curl_init();
        $url = $this->apiUrl . "/" . $resource;
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->connTimeout);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . base64_encode(":" . $this->apiKey), 'Accept-Version: ' . $this->apiVersion, 'Accept: ' . $this->format));
        if (! is_null($postdata)) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postdata));
        }

        $response = curl_exec($curl);

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (! in_array($httpCode, array(200, 201, 202))) {
            Mage::throwException($response);
        }

        return $response;
    }

    /**
     * Create a payment at the quickpay gateway
     */
    function qpCreatePayment($orderid, $currency)
    {
        $postArray = array();
        $postArray['order_id'] = $orderid;
        $postArray['currency'] = $currency;
        $storeId = Mage::app()->getStore()->getStoreId();
        $this->apiKey = Mage::getStoreConfig('payment/quickpaypayment_payment/apikey', $storeId);
        $result = $this->request('payments', $postArray,"");
        $result = json_decode($result);

        return $result;
    }

    /**
    * Create a payment link at the quickpay gateway
    */
    function qpCreatePaymentLink($id, $array)
    {
        $storeId = Mage::app()->getStore()->getStoreId();
        $this->apiKey = Mage::getStoreConfig('payment/quickpaypayment_payment/apikey', $storeId);
        $result = $this->put('payments/' . $id . '/link', $array);
        $result = json_decode($result);
        return $result;
    }

    /**
     * Capture a payment at the quickpay gateway
     */
    function qpCapture($id, $amount, $extras = null)
    {
        $postArray = array();
        $postArray['id'] = $id;
        $postArray['amount'] = $amount;
        if (! is_null($extras)) {
            $postArray['extras'] = $extras;
        }
        $result = $this->request('payments/' . $id . '/capture', $postArray);
        $result = json_decode($result);

        return $result;
    }

    /**
     * Refund a payment at the quickpay gateway
     */
    function qpRefund($id, $amount, $extras = null)
    {
        $postArray = array();
        $postArray['id'] = $id;
        $postArray['amount'] = $amount;
        if (!is_null($extras)) {
            $postArray['extras'] = $extras;
        }
        $result = $this->request('payments/' . $id . '/refund', $postArray);
        $result = json_decode($result);

        return $result;
    }

    /**
     * Cancel a payment at the quickpay gateway
     */
    function qpCancel($id)
    {
        $postArray = array();
        $postArray['id'] = $id;
        $result = $this->request('payments/' . $id . '/cancel', $postArray);
        $result = json_decode($result);
        return $result;
    }

    public function capture($payment, $amount, $finalize = false)
    {
        Mage::log('start capture', null, 'qp_capture.log');
        $session = Mage::getSingleton('adminhtml/session');

        if ($payment->getInfoInstance()) {
            $order = $payment->getInfoInstance()->getOrder();
        } else {
            $order = $payment->getOrder();
        }
        if ($order->getStoreId()) {
            $storeId = $order->getStoreId();
        }

        $orderid = explode("-", $order->getIncrementId());

        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_read');
        $table = $resource->getTableName('quickpaypayment_order_status');
        $qpOrderStatus = $connection->fetchAll("SELECT * FROM $table WHERE ordernum=" . $orderid[0]);

        $qpOrderStatus = $qpOrderStatus[0];
        Mage::log($qpOrderStatus, null, 'qp_capture.log');
        $quickPayTransactionId = $qpOrderStatus['transaction'];
        $capturedAmount = (isset($qpOrderStatus['capturedAmount']) ? $qpOrderStatus['capturedAmount'] : 0);

        if ((int)($amount * 100) <= ((int)$qpOrderStatus['amount'] - (int)$capturedAmount)) {
            $this->apiKey = Mage::getStoreConfig('payment/quickpaypayment_payment/apikey', $storeId);

            if ($order->getTotalDue() == $amount || $qpOrderStatus['cardtype'] != 'dankort' || $finalize) {
                $msg['finalize'] = 1;
            } else {
                $msg['finalize'] = 0;
            }

            Mage::log($msg, null, 'qp_capture.log');

            $newCapturedAmount = $capturedAmount + ($amount * 100);
            try {
                $result = $this->qpCapture($quickPayTransactionId, round($amount * 100));
            } catch (Exception $e) {
                Mage::throwException("Failure to capture: " . $e->getMessage());
            }

            // Get the last operation
            $result = end($result->operations);

            Mage::log($result, null, 'qp_capture.log');

            if ($result->qp_status_code == "20000" && ($result->aq_status_code == "000" || $result->aq_status_code == "20000")) {
                $session->addSuccess(Mage::helper('quickpaypayment')->__('Betalingen er hævet online.'));
                $write = $resource->getConnection('core_write');

                $write->query("UPDATE $table SET " . 'status = "", ' . 'time = "' . ((isset($result->created_at)) ? $result->created_at : '') . '", ' . 'qpstat = "' . ((isset($result->qp_status_code)) ? $result->qp_status_code : '') . '", ' . 'qpstatmsg = "' . ((isset($result->qp_status_msg)) ? $result->qp_status_msg : '') . '", ' . 'chstat = "' . ((isset($result->aq_status_code)) ? $result->aq_status_code : '') . '", ' . 'chstatmsg = "' . ((isset($result->aq_status_msg)) ? $result->aq_status_msg : '') . '", ' . 'splitpayment = "' . ((isset($result->split_payment)) ? $result->split_payment : '') . '" ,' . 'md5check = "' . ((isset($response['md5check'])) ? $response['md5check'] : '') . '", ' . 'capturedAmount = ' . $newCapturedAmount . ' ' . 'WHERE ordernum=' . $orderid[0]);

                $this->createTransaction($order, $quickPayTransactionId, Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
            } else {
                Mage::throwException("Quickpay Response: " . $result->qp_status_msg);
            }
        } else {
            Mage::throwException(Mage::helper('quickpaypayment')->__('Der forsøges at hæve et højere beløb en tilladt'));
        }

        Mage::log('stop capture', null, 'qp_capture.log');
    }

    public function refund($orderid, $refundtotal)
    {
        $order = Mage::getModel('sales/order')->load($orderid);
        $orderid = explode("-", $order->getIncrementId());

        $session = Mage::getSingleton('adminhtml/session');
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_read');
        $table = $resource->getTableName('quickpaypayment_order_status');
        $qpOrderStatus = $connection->fetchAll("SELECT * FROM $table WHERE ordernum=" . $orderid[0]);
        $qpOrderStatus = $qpOrderStatus[0];

        $this->apiKey = Mage::getStoreConfig('payment/quickpaypayment_payment/apikey', $storeId);

        if ($refundtotal < 0) {
            $refundtotal = $refundtotal * -1;
        }

        if (($refundtotal * 100) <= $qpOrderStatus['capturedAmount']) {
            Mage::log($msg, null, 'qp_refund.log');
            $errorMessage = "";
            try {
                $result = $this->qpRefund($qpOrderStatus['transaction'], round($refundtotal * 100));
            } catch (Exception $e) {
                $errorMessage = $e->getMessage();
            }

            // Get the last operation
            $result = end($result->operations);

            if ($errorMessage !== "") {
                Mage::throwException("Failed to refund: " . $errorMessage);
            }
            if ($result->qp_status_code == "20000") {
                $session->addSuccess(Mage::helper('quickpaypayment')->__('Kreditnota refunderet online'));
                $write = Mage::getSingleton('core/resource')->getConnection('core_write');
                $write->query("UPDATE $table SET " . 'refundedAmount = ' . ($refundtotal * 100) . ', ' . 'status = "", ' . 'time = "' . ((isset($result->created_at)) ? $result->created_at : '') . '", ' . 'qpstat = "' . ((isset($result->qp_status_code)) ? $result->qp_status_code : '') . '", ' . 'qpstatmsg = "' . ((isset($result->qp_status_msg)) ? $result->qp_status_msg : '') . '", ' . 'chstat = "' . ((isset($result->aq_status_code)) ? $result->aq_status_code : '') . '", ' . 'chstatmsg = "' . ((isset($result->aq_status_msg)) ? $result->aq_status_msg : '') . '" ' . 'WHERE ordernum=' . $orderid[0]);

                $this->createTransaction($order, $qpOrderStatus['transaction'], Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND);
            } else {
                Mage::throwException($result->qp_status_msg);
            }
        } else {
            Mage::throwException(Mage::helper('quickpaypayment')->__('Max beløb der kan refunderes: %s', $qpOrderStatus['capturedAmount']));
        }

        $order->addStatusToHistory($order->getStatus(), Mage::helper('quickpaypayment')->__('Kreditnota refunderede % online', number_format($refundtotal, 2, ",", "")), false);
        $order->save();
    }

    public function cancel($orderid)
    {
        $order = Mage::getModel('sales/order')->load($orderid);
        $orderid = explode("-", $order->getIncrementId());

        $session = Mage::getSingleton('adminhtml/session');
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_read');
        $table = $resource->getTableName('quickpaypayment_order_status');
        $qpOrderStatus = $connection->fetchAll("SELECT * FROM $table WHERE ordernum=" . $orderid[0]);
        $qpOrderStatus = $qpOrderStatus[0];

        $this->apiKey = Mage::getStoreConfig('payment/quickpaypayment_payment/apikey');

        $msg = Array('protocol' => 7, 'msgtype' => 'cancel', 'merchant' => $merchant, 'transaction' => $qpOrderStatus['transaction'], );
        $msg['md5check'] = md5($msg['protocol'] . $msg['msgtype'] . $msg['merchant'] . $msg['transaction'] . $qpmd5);

        $errorMessage = "";
        try {
            $result = $this->qpCancel($qpOrderStatus['transaction']);
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }

        if ($errorMessage !== "") {
            Mage::throwException("Failed to cancel: " . $e->getMessage());
        }

        // Get the last operation
        $result = end($result->operations);

        if ($result->qp_status_code == "20000") {
            $session->addSuccess(Mage::helper('quickpaypayment')->__('Betalingen blev annulleret online'));
            $write = Mage::getSingleton('core/resource')->getConnection('core_write');

            $write->query("UPDATE $table SET " . 'status = "", ' . 'time = "' . ((isset($result->created_at)) ? $result->created_at : '') . '", ' . 'qpstat = "' . ((isset($result->qp_status_code)) ? $result->qp_status_code : '') . '", ' . 'qpstatmsg = "' . ((isset($result->qp_status_msg)) ? $result->qp_status_msg : '') . '", ' . 'chstat = "' . ((isset($result->aq_status_code)) ? $result->aq_status_code : '') . '", ' . 'chstatmsg = "' . ((isset($result->aq_status_msg)) ? $result->aq_status_msg : '') . '" ' . 'WHERE ordernum=' . $orderid[0]);
        } else {
            Mage::throwException($result->qp_status_msg);
        }

        $order->addStatusToHistory($order->getStatus(), Mage::helper('quickpaypayment')->__('Betalingen blev annulleret online'), false);
        $order->save();
    }

    public function createTransaction($order, $transactionId, $type)
    {
        $transaction = Mage::getModel('sales/order_payment_transaction');
        $transaction->setOrderPaymentObject($order->getPayment());

        if (! $transaction = $transaction->loadByTxnId($transactionId)) {
            $transaction = Mage::getModel('sales/order_payment_transaction');
            $transaction->setOrderPaymentObject($order->getPayment());
            $transaction->setOrder($order);
        }
        if ($type == Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH) {
            $transaction->setIsClosed(false);
        } else {
            $transaction->setIsClosed(true);
        }

        $transaction->setTxnId($transactionId);
        $transaction->setTxnType($type);
        $transaction->save();

        return $transaction;
    }

    public function getQuickPay($order_id)
    {
        if ($order_id) {
            $order = Mage::getModel('sales/order')->load($order_id);
            if ($order) {
                $order_increment_id = $order->getIncrementId();
                $resource = Mage::getSingleton('core/resource');
                $table = $resource->getTableName('quickpaypayment_order_status');
                $read = $resource->getConnection('core_read');
                $row = $read->fetchRow("SELECT * FROM $table WHERE ordernum = '$order_increment_id'");

                return $row;
            }
        }

        return false;
    }

    public function getFields($order_id)
    {
        $data = $this->getQuickPay($order_id);
        if ($data) {
            $fields = array('fraudremarks' => array('key' => 'Fraud Remarks', 'value' => $data['fraudremarks'] ? $data['fraudremarks'] : $this->__('No remarks'), ), );
            return $fields;
        }

        return false;
    }

    public function getInfoType($order_id)
    {
        $pay = $this->getQuickPay($order_id);
        if ($pay) {
            if (!$pay['ordernum']) {
                return 'no_cart';
            } else {
                return 'normal';
            }
        }

        return false;
    }

    public function getImage($order_id)
    {
        $image = 'state_clear';
        if ($order_id) {
            $order = Mage::getModel('sales/order')->load($order_id);
            if ($order) {
                $payment = $this->getQuickPay($order_id);
                if (isset($payment['fraudprobability']) && $payment['fraudprobability']) {
                    $image = 'state_' . $payment['fraudprobability'];
                }
            }
        }

        return $image;
    }

    public function removeFromStock($incrementId)
    {
        $payment = Mage::getModel('quickpaypayment/payment');
        $session = Mage::getSingleton('checkout/session');

        if (((int)$payment->getConfigData('handlestock')) == 1) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
            $items = $order->getAllItems();
            // Get all items from the order
            if ($items) {
                foreach ($items as $item) {
                    $quantity = $item->getQtyOrdered();
                    // get Qty ordered
                    $product_id = $item->getProductId();
                    // get it's ID

                    $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product_id);
                    // Load the stock for this product
                    $stock->setQty($stock->getQty() - $quantity);
                    // Set to new Qty
                    $stock->save();
                    // Save
                }
            }
        }
    }
}
