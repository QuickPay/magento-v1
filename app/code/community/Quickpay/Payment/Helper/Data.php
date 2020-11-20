<?php
class Quickpay_Payment_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $apiUrl = "https://api.quickpay.net";
    protected $apiVersion = 'v10';
    protected $apiKey = "";
    // Loaded from the configuration
    protected $format = "application/json";

    /**
     * Perform a POST request
     *
     * @param $resource
     * @param array $postdata
     * @param string $synchronized
     * @return string
     */
    protected function request($resource, $postdata = array(), $synchronized = "?synchronized")
    {
        $client = new Zend_Http_Client();

        $url = $this->apiUrl . "/" . $resource . $synchronized;

        $client->setUri($url);

        $headers = array(
            'Authorization'  => 'Basic ' . base64_encode(":" . $this->apiKey),
            'Accept-Version' => $this->apiVersion,
            'Accept'         => $this->format,
            'Content-Type'   => 'application/json',
            'Content-Length' => strlen(json_encode($postdata))
        );

        $client->setHeaders($headers);
        $client->setMethod(Zend_Http_Client::POST);
        $client->setRawData(json_encode($postdata));

        $request = $client->request();

        if (! in_array($request->getStatus(), array(200, 201, 202))) {
            Mage::throwException($request->getBody());
        }

        return $request->getBody();
    }

    /**
     * Perform a PUT request
     *
     * @param $resource
     * @param array $postdata
     * @return string
     */
    protected function put($resource, $postdata = array())
    {
        $client = new Zend_Http_Client();

        $url = $this->apiUrl . "/" . $resource;

        $client->setUri($url);

        $headers = array(
            'Authorization'  => 'Basic ' . base64_encode(":" . $this->apiKey),
            'Accept-Version' => $this->apiVersion,
            'Accept'         => $this->format,
            'Content-Type'   => 'application/json',
            'Content-Length' => strlen(json_encode($postdata))
        );

        $client->setHeaders($headers);
        $client->setMethod(Zend_Http_Client::PUT);
        $client->setRawData(json_encode($postdata));

        $request = $client->request();

        if (! in_array($request->getStatus(), array(200, 201, 202))) {
            Mage::throwException($request->getBody());
        }

        return $request->getBody();
    }

    /**
     * Create a Payment
     *
     * @param Mage_Sales_Model_Order $order
     * @return mixed|string
     */
    public function qpCreatePayment(Mage_Sales_Model_Order $order)
    {
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_write');
        $table = $resource->getTableName('quickpaypayment_order_status');
        $connection->insert($table, array('ordernum' => $order->getIncrementId()));

        $postArray = array();

        $postArray['order_id'] = $order->getIncrementId();
        $postArray['currency'] = $order->getOrderCurrency()->ToString();

        if ($textOnStatement = Mage::getStoreConfig('payment/quickpaypayment_payment/text_on_statement')) {
            $postArray['text_on_statement'] = $textOnStatement;
        }

        $shippingAddress = $order->getShippingAddress();
        $billingAddress = $order->getBillingAddress();

        //Add shipping_address
        if ($shippingAddress) { 
            $postArray['shipping_address']['name'] = $shippingAddress->getName();
            $postArray['shipping_address']['street'] = $shippingAddress->getStreetFull();
            $postArray['shipping_address']['city'] = $shippingAddress->getCity();
            $postArray['shipping_address']['zip_code'] = $shippingAddress->getPostcode();
            $postArray['shipping_address']['region'] = $shippingAddress->getRegion();
            $postArray['shipping_address']['country_code'] = Mage::app()->getLocale()->getTranslation($shippingAddress->getCountryId(), 'Alpha3ToTerritory');
            $postArray['shipping_address']['phone_number'] = $shippingAddress->getTelephone();
            $postArray['shipping_address']['email'] = $shippingAddress->getEmail();
            $postArray['shipping_address']['house_number'] = '';
            $postArray['shipping_address']['house_extension'] = '';
            $postArray['shipping_address']['mobile_number'] = '';
        }

        //Add billing_address
        if ($billingAddress) {
            $postArray['invoice_address']['name'] = $billingAddress->getName();
            $postArray['invoice_address']['street'] = $billingAddress->getStreetFull();
            $postArray['invoice_address']['city'] = $billingAddress->getCity();
            $postArray['invoice_address']['zip_code'] = $billingAddress->getPostcode();
            $postArray['invoice_address']['region'] = $billingAddress->getRegion();
            $postArray['invoice_address']['country_code'] = Mage::app()->getLocale()->getTranslation($billingAddress->getCountryId(), 'Alpha3ToTerritory');
            $postArray['invoice_address']['phone_number'] = $billingAddress->getTelephone();
            $postArray['invoice_address']['email'] = $billingAddress->getEmail();
            $postArray['invoice_address']['house_number'] = '';
            $postArray['invoice_address']['house_extension'] = '';
            $postArray['invoice_address']['mobile_number'] = '';
        }

        $postArray['shopsystem'] = [];
        $postArray['shopsystem']['name'] = 'Magento 1';
        $postArray['shopsystem']['version'] = $this->getModuleVersion();

        $postArray['basket'] = array();

        //Add order items to basket array
        foreach ($order->getAllVisibleItems() as $item) {
            $price = ($item->getBasePriceInclTax() - $item->getDiscountAmount()) * 100;
            $product = array(
                'qty'        => (int) $item->getQtyOrdered(),
                'item_no'    => $item->getSku(),
                'item_name'  => $item->getName(),
                'item_price' => round($price),
                'vat_rate'   => $item->getTaxPercent() / 100,
            );

            $postArray['basket'][] = $product;
        }

        //Send shipping amount
        $postArray['shipping']['method'] = 'pick_up_point';
        $postArray['shipping']['amount'] = (int) ($order->getShippingInclTax() * 100);

        $storeId = Mage::app()->getStore()->getStoreId();
        $this->apiKey = Mage::getStoreConfig('payment/quickpaypayment_payment/apikey', $storeId);
        $result = $this->request('payments', $postArray, '');
        $result = json_decode($result);

        return $result;
    }

    /**
     * Create a Payment
     *
     * @param Mage_Sales_Model_Order $order
     * @return mixed|string
     */
    public function qpCreateMobilepayPayment(Mage_Sales_Model_Order $order, $shippingData)
    {
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_write');
        $table = $resource->getTableName('quickpaypayment_order_status');
        $connection->insert($table, array('ordernum' => $order->getIncrementId()));

        $postArray = array();

        $postArray['order_id'] = $order->getIncrementId();
        $postArray['currency'] = $order->getOrderCurrency()->ToString();

        if ($textOnStatement = Mage::getStoreConfig('payment/quickpaypayment_payment/text_on_statement')) {
            $postArray['text_on_statement'] = $textOnStatement;
        }

        $postArray['basket'] = array();

        //Add order items to basket array
        foreach ($order->getAllVisibleItems() as $item) {
            $price = ($item->getBasePriceInclTax() - $item->getDiscountAmount()) * 100;
            $product = array(
                'qty'        => (int) $item->getQtyOrdered(),
                'item_no'    => $item->getSku(),
                'item_name'  => $item->getName(),
                'item_price' => round($price),
                'vat_rate'   => $item->getTaxPercent() / 100,
            );

            $postArray['basket'][] = $product;
        }

        if($shippingData){
            $postArray['shipping']['method'] = $shippingData['code'];
            $postArray['shipping']['amount'] = $shippingData['price'] * 100;
        }

        $storeId = Mage::app()->getStore()->getStoreId();
        $this->apiKey = Mage::getStoreConfig('payment/quickpaypayment_payment/apikey', $storeId);
        $result = $this->request('payments', $postArray, '');
        $result = json_decode($result);

        return $result;
    }

    /**
     * Create a Payment Link
     *
     * @param $id
     * @param $array
     * @return mixed|string
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
     * Capture Payment
     *
     * @param $id
     * @param $amount
     * @param null $extras
     * @return mixed|string
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
     * Refund Payment
     *
     * @param $id
     * @param $amount
     * @param null $extras
     * @return mixed|string
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
     * Cancel Payment
     *
     * @param $id
     * @return mixed|string
     */
    function qpCancel($id)
    {
        $postArray = array();
        $postArray['id'] = $id;
        $result = $this->request('payments/' . $id . '/cancel', $postArray);
        $result = json_decode($result);

        return $result;
    }

    /**
     * Capture Payment
     *
     * @param $payment
     * @param $amount
     */
    public function capture($payment, $amount)
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

        $query = "SELECT transaction, capturedAmount, amount FROM {$table} WHERE ordernum = :order_number";
        $binds = array(
            'order_number' => $orderid[0],
        );
        $qpOrderStatus = $connection->fetchRow($query, $binds);

        Mage::log($qpOrderStatus, null, 'qp_capture.log');
        $quickPayTransactionId = $qpOrderStatus['transaction'];
        $capturedAmount = (isset($qpOrderStatus['capturedAmount']) ? $qpOrderStatus['capturedAmount'] : 0);

        if ((int)($amount * 100) <= ((int)$qpOrderStatus['amount'] - (int)$capturedAmount)) {
            $this->apiKey = Mage::getStoreConfig('payment/quickpaypayment_payment/apikey', $storeId);

            $newCapturedAmount = $capturedAmount + ($amount * 100);

            try {
                $result = $this->qpCapture($quickPayTransactionId, round($amount * 100));
            } catch (Exception $e) {
                Mage::throwException("Failure to capture: " . $e->getMessage());
            }

            // Get the last operation
            $result = end($result->operations);

            Mage::log($result, null, 'qp_capture.log');

            if ($result->qp_status_code == "20000") {
                $session->addSuccess(Mage::helper('quickpaypayment')->__('Betalingen for ordre %s er hævet online.', $order->getIncrementId()));
                $write = $resource->getConnection('core_write');

                $query = "UPDATE {$table} SET status = :status, time = :time, qpstat = :qpstat, qpstatmsg = :qpstatmsg, chstat = :chstat, chstatmsg = :chstatmsg, splitpayment = :splitpayment, capturedAmount = :capturedAmount WHERE ordernum = :order_number";
                $binds = array(
                    'status'         => 0,
                    'time'           => isset($result->created_at) ? $result->created_at : '',
                    'qpstat'         => isset($result->qp_status_code) ? $result->qp_status_code : '',
                    'qpstatmsg'      => isset($result->qp_status_msg) ? $result->qp_status_msg : '',
                    'chstat'         => isset($result->aq_status_code) ? $result->aq_status_code : '',
                    'chstatmsg'      => isset($result->aq_status_msg) ? $result->aq_status_msg : '',
                    'splitpayment'   => isset($result->split_payment) ? $result->split_payment : '',
                    'capturedAmount' => $newCapturedAmount,
                    'order_number'   => $orderid[0],
                );
                $write->query($query, $binds);

                $this->createTransaction($order, $quickPayTransactionId, Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
            } else {
                Mage::throwException("Quickpay Response: " . $result->qp_status_msg);
            }
        } else {
            Mage::throwException(Mage::helper('quickpaypayment')->__('Der forsøges at hæve et højere beløb en tilladt'));
        }

        Mage::log('stop capture', null, 'qp_capture.log');
    }

    /**
     * Refund Payment
     *
     * @param $orderid
     * @param $refundtotal
     */
    public function refund(Mage_Sales_Model_Order $order, $refundtotal)
    {
        $orderid = explode("-", $order->getIncrementId());

        $session = Mage::getSingleton('adminhtml/session');
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_read');
        $table = $resource->getTableName('quickpaypayment_order_status');

        $query = "SELECT transaction, capturedAmount FROM {$table} WHERE ordernum = :order_number";
        $binds = array(
            'order_number' => $orderid[0],
        );

        $qpOrderStatus = $connection->fetchRow($query, $binds);

        $storeId = $order->getStoreId();
        $this->apiKey = Mage::getStoreConfig('payment/quickpaypayment_payment/apikey', $storeId);

        if ($refundtotal < 0) {
            $refundtotal = $refundtotal * -1;
        }

        if (($refundtotal * 100) <= $qpOrderStatus['capturedAmount']) {

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
                $query = "UPDATE {$table} SET refundedAmount = :refundedAmount, status = :status, time = :time, qpstat = :qpstat, qpstatmsg = :qpstatmsg, chstat = :chstat, chstatmsg = :chstatmsg WHERE ordernum = :order_number";
                $binds = array(
                    'refundedAmount' => $refundtotal * 100,
                    'status'         => 0,
                    'time'           => isset($result->created_at) ? $result->created_at : '',
                    'qpstat'         => isset($result->qp_status_code) ? $result->qp_status_code : '',
                    'qpstatmsg'      => isset($result->qp_status_msg) ? $result->qp_status_msg : '',
                    'chstat'         => isset($result->aq_status_code) ? $result->aq_status_code : '',
                    'chstatmsg'      => isset($result->aq_status_msg) ? $result->aq_status_msg : '',
                    'order_number'   => $orderid[0],
                );
                $write->query($query, $binds);

                $this->createTransaction($order, $qpOrderStatus['transaction'], Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND);
            } else {
                Mage::throwException($result->qp_status_msg);
            }
        } else {
            Mage::throwException(Mage::helper('quickpaypayment')->__('Max beløb der kan refunderes: %s', $qpOrderStatus['capturedAmount']));
        }

        $order->addStatusToHistory($order->getStatus(), Mage::helper('quickpaypayment')->__('Kreditnota refunderede %s online', number_format($refundtotal, 2, ",", "")), false);
        $order->save();
    }

    /**
     * Cancel Payment
     *
     * @param Mage_Sales_Model_Order $order
     */
    public function cancel(Mage_Sales_Model_Order $order)
    {
        $orderid = explode("-", $order->getIncrementId());

        $session = Mage::getSingleton('adminhtml/session');
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_read');
        $table = $resource->getTableName('quickpaypayment_order_status');

        $query = "SELECT transaction FROM {$table} WHERE ordernum = :order_number";
        $binds = array(
            'order_number' => $orderid[0],
        );
        $qpOrderStatus = $connection->fetchRow($query, $binds);

        $this->apiKey = Mage::getStoreConfig('payment/quickpaypayment_payment/apikey', $order->getStoreId());

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

            $query = "UPDATE {$table} SET status = :status, time = :time, qpstat = :qpstat, qpstatmsg = :qpstatmsg, chstat = :chstat, chstatmsg = :chstatmsg WHERE ordernum = :order_number";
            $binds = array(
                'status'       => 0,
                'time'         => isset($result->created_at) ? $result->created_at : '',
                'qpstat'       => isset($result->qp_status_code) ? $result->qp_status_code : '',
                'qpstatmsg'    => isset($result->qp_status_msg) ? $result->qp_status_msg : '',
                'chstat'       => isset($result->aq_status_code) ? $result->aq_status_code : '',
                'chstatmsg'    => isset($result->aq_status_msg) ? $result->aq_status_msg : '',
                'order_number' => $orderid[0],
            );
            $write->query($query, $binds);
        } else {
            Mage::throwException($result->qp_status_msg);
        }

        $order->addStatusToHistory($order->getStatus(), Mage::helper('quickpaypayment')->__('Betalingen blev annulleret online'), false);
        $order->save();
    }

    /**
     * Create transaction
     *
     * @param $order
     * @param $transactionId
     * @param $type
     * @return false|Mage_Core_Model_Abstract
     */
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

    /**
     * Get row from quickpay_order_status table
     *
     * @param $order_id
     * @return bool
     */
    public function getQuickPay($order_id)
    {
        if ($order_id) {
            $order = Mage::getModel('sales/order')->load($order_id);
            if ($order) {
                $order_increment_id = $order->getIncrementId();
                $resource = Mage::getSingleton('core/resource');
                $table = $resource->getTableName('quickpaypayment_order_status');
                $read = $resource->getConnection('core_read');

                $query = "SELECT * FROM {$table} WHERE ordernum = :order_number";
                $binds = array(
                    'order_number' => $order_increment_id,
                );
                $row = $read->fetchRow($query, $binds);

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
                    if ($stock->getId()) {
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

    /**
     * Get installed version of extension
     *
     * @return string
     */
    public function getInstalledVersion()
    {
        return (string) Mage::getConfig()->getNode()->modules->Quickpay_Payment->version;
    }

    /**
     * @param string $code
     * @return string
     */
    public function convertCountryAlphas3To2($code = 'DK') {
        $countries = json_decode('{"AFG":"AF","ALA":"AX","ALB":"AL","DZA":"DZ","ASM":"AS","AND":"AD","AGO":"AO","AIA":"AI","ATA":"AQ","ATG":"AG","ARG":"AR","ARM":"AM","ABW":"AW","AUS":"AU","AUT":"AT","AZE":"AZ","BHS":"BS","BHR":"BH","BGD":"BD","BRB":"BB","BLR":"BY","BEL":"BE","BLZ":"BZ","BEN":"BJ","BMU":"BM","BTN":"BT","BOL":"BO","BIH":"BA","BWA":"BW","BVT":"BV","BRA":"BR","VGB":"VG","IOT":"IO","BRN":"BN","BGR":"BG","BFA":"BF","BDI":"BI","KHM":"KH","CMR":"CM","CAN":"CA","CPV":"CV","CYM":"KY","CAF":"CF","TCD":"TD","CHL":"CL","CHN":"CN","HKG":"HK","MAC":"MO","CXR":"CX","CCK":"CC","COL":"CO","COM":"KM","COG":"CG","COD":"CD","COK":"CK","CRI":"CR","CIV":"CI","HRV":"HR","CUB":"CU","CYP":"CY","CZE":"CZ","DNK":"DK","DKK":"DK","DJI":"DJ","DMA":"DM","DOM":"DO","ECU":"EC","Sal":"El","GNQ":"GQ","ERI":"ER","EST":"EE","ETH":"ET","FLK":"FK","FRO":"FO","FJI":"FJ","FIN":"FI","FRA":"FR","GUF":"GF","PYF":"PF","ATF":"TF","GAB":"GA","GMB":"GM","GEO":"GE","DEU":"DE","GHA":"GH","GIB":"GI","GRC":"GR","GRL":"GL","GRD":"GD","GLP":"GP","GUM":"GU","GTM":"GT","GGY":"GG","GIN":"GN","GNB":"GW","GUY":"GY","HTI":"HT","HMD":"HM","VAT":"VA","HND":"HN","HUN":"HU","ISL":"IS","IND":"IN","IDN":"ID","IRN":"IR","IRQ":"IQ","IRL":"IE","IMN":"IM","ISR":"IL","ITA":"IT","JAM":"JM","JPN":"JP","JEY":"JE","JOR":"JO","KAZ":"KZ","KEN":"KE","KIR":"KI","PRK":"KP","KOR":"KR","KWT":"KW","KGZ":"KG","LAO":"LA","LVA":"LV","LBN":"LB","LSO":"LS","LBR":"LR","LBY":"LY","LIE":"LI","LTU":"LT","LUX":"LU","MKD":"MK","MDG":"MG","MWI":"MW","MYS":"MY","MDV":"MV","MLI":"ML","MLT":"MT","MHL":"MH","MTQ":"MQ","MRT":"MR","MUS":"MU","MYT":"YT","MEX":"MX","FSM":"FM","MDA":"MD","MCO":"MC","MNG":"MN","MNE":"ME","MSR":"MS","MAR":"MA","MOZ":"MZ","MMR":"MM","NAM":"NA","NRU":"NR","NPL":"NP","NLD":"NL","ANT":"AN","NCL":"NC","NZL":"NZ","NIC":"NI","NER":"NE","NGA":"NG","NIU":"NU","NFK":"NF","MNP":"MP","NOR":"NO","OMN":"OM","PAK":"PK","PLW":"PW","PSE":"PS","PAN":"PA","PNG":"PG","PRY":"PY","PER":"PE","PHL":"PH","PCN":"PN","POL":"PL","PRT":"PT","PRI":"PR","QAT":"QA","REU":"RE","ROU":"RO","RUS":"RU","RWA":"RW","BLM":"BL","SHN":"SH","KNA":"KN","LCA":"LC","MAF":"MF","SPM":"PM","VCT":"VC","WSM":"WS","SMR":"SM","STP":"ST","SAU":"SA","SEN":"SN","SRB":"RS","SYC":"SC","SLE":"SL","SGP":"SG","SVK":"SK","SVN":"SI","SLB":"SB","SOM":"SO","ZAF":"ZA","SGS":"GS","SSD":"SS","ESP":"ES","LKA":"LK","SDN":"SD","SUR":"SR","SJM":"SJ","SWZ":"SZ","SWE":"SE","CHE":"CH","SYR":"SY","TWN":"TW","TJK":"TJ","TZA":"TZ","THA":"TH","TLS":"TL","TGO":"TG","TKL":"TK","TON":"TO","TTO":"TT","TUN":"TN","TUR":"TR","TKM":"TM","TCA":"TC","TUV":"TV","UGA":"UG","UKR":"UA","ARE":"AE","GBR":"GB","USA":"US","UMI":"UM","URY":"UY","UZB":"UZ","VUT":"VU","VEN":"VE","VNM":"VN","VIR":"VI","WLF":"WF","ESH":"EH","YEM":"YE","ZMB":"ZM","ZWE":"ZW","GBP":"GB","RUB":"RU","NOK":"NO"}',true);

        if(!isset($countries[$code])){
            $defaultCountry = Mage::getStoreConfig('general/country/default', Mage::app()->getStore()->getStoreId());
            return $defaultCountry;
        } else {
            return $countries[$code];
        }
    }

    /**
     * @return string
     */
    public function getModuleVersion(){
        $module = 'Quickpay_Payment';
        $configFile = Mage::getConfig()->getModuleDir('etc', $module).DS.'config.xml';
        $string = file_get_contents($configFile);
        if($string){
            $xml = simplexml_load_string($string, 'Varien_Simplexml_Element');
            $json = json_encode($xml);
            $data = json_decode($json,TRUE);

            if(isset($data['modules'][$module]['version'])){
                return $data['modules'][$module]['version'];
            }
        }
        return '';
    }

}
