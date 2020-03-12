<?php
class Quickpay_Payment_MobilepayController extends Mage_Core_Controller_Front_Action
{
    public function redirectAction(){
        $params = $this->getRequest()->getParams();
        $error = false;

        if(empty($params['shipping'])){
            $error = Mage::helper('quickpaypayment')->__('Please specify a shipping method.');
        } else {
            $shippingData = Mage::getModel('quickpaypayment/carrier_shipping')->getMethodByCode($params['shipping']);
            if(empty($shippingData)){
                $error = Mage::helper('quickpaypayment')->__('Please specify a shipping method.');
            }
        }

        if($error) {
            Mage::getSingleton('core/session')->addError($error);
            $this->_redirectReferer();
            return;
        }

        //Create order from quote
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $session = $this->_getSession();
        $items = $quote->getAllVisibleItems();
        try {
            if(!$quote->getCustomerId() && !$quote->getCustomerEmail()){
                $quote->setCustomerEmail('dnk@dnk.dk');
                $quote->setCustomerIsGuest(1);
            }

            $defaultValue = 'DNK';
            $defaultAddress = [
                'firstname' => $defaultValue,
                'lastname' => $defaultValue,
                'street' => $defaultValue,
                'city' => $defaultValue,
                'country_id' => 'DK',
                'region' => $defaultValue,
                'postcode' => $defaultValue,
                'telephone' => $defaultValue,
                'vat_id' => '',
                'save_in_address_book' => 0
            ];

            $quote->getBillingAddress()->addData($defaultAddress);
            $quote->getShippingAddress()->addData($defaultAddress);

            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setCollectShippingRates(true)->collectShippingRates()->setShippingMethod('quickpay_mobilepay_quickpay_mobilepay');

            // Set Sales Order Payment
            $quote->getPayment()->importData(['method' => 'quickpaypayment_payment']);

            // Collect Totals & Save Quote
            $quote->collectTotals()->save();

            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();

            // Create Order From Quote
            $order = $service->getOrder();

            $shippingPrice = $shippingData['price'];
            $grandTotal = $order->getGrandTotal() + $shippingPrice;
            $order->setShippingAmount($shippingPrice);
            $order->setBaseShippingAmount($shippingPrice);
            $order->setShippingDescription('MobilePay - '.$shippingData['title']);
            $order->setGrandTotal($grandTotal);
            $order->setBaseGrandTotal($grandTotal);
            $order->save();

            $quote->setIsActive(0)->save();

            if ($order->getId()) {
                $session
                    ->setLastRealOrderId($order->getIncrementId())
                    ->setLastSuccessQuoteId($quote->getId())
                    ->setLastQuoteId($quote->getId())
                    ->setLastOrderId($order->getId());
            }

            //Save quote id in session for retrieval later
            $session->setQuickpayQuoteId($session->getQuoteId());

            $session->unsQuoteId();
            $session->unsRedirectUrl();

            $payment = Mage::getModel('quickpaypayment/payment');
            $quickpay_state = Mage::getSingleton('core/session')->getQuickpayState();

// Get array of selections
            if (isset($quickpay_state[0]) && $quickpay_state[0] == 'checkbox1') {
                $brandingId = $payment->getConfigData('brandingidchecked');
            } else {
                $brandingId = $payment->getConfigData('brandingid');
            }

            $parameters = array(
                "agreement_id"                 => $payment->getConfigData("agreementid"),
                "amount"                       => $order->getTotalDue() * 100,
                "continueurl"                  => $this->getSuccessUrl(),
                "cancelurl"                    => $this->getCancelUrl(),
                "callbackurl"                  => $this->getCallbackUrl(),
                "language"                     => $payment->calcLanguage(Mage::app()->getLocale()->getLocaleCode()),
                "autocapture"                  => 0,
                "autofee"                      => $payment->getConfigData('transactionfee'),
                "payment_methods"              => 'mobilepay',
                "branding_id"                  => $brandingId,
                "google_analytics_tracking_id" => $payment->getConfigData('googleanalyticstracking'),
                "google_analytics_client_id"   => $payment->getConfigData('googleanalyticsclientid'),
                "customer_email"               => $order->getCustomerEmail() ?: '',
                "invoice_address_selection"    => 1,
                "shipping_address_selection"   => 1,
            );

            $shippingServiceData = [
                'code' => $params['shipping'],
                'price' => $shippingData['price']
            ];
            $result = Mage::helper('quickpaypayment')->qpCreateMobilepayPayment($order, $shippingServiceData);
            $result = Mage::helper('quickpaypayment')->qpCreatePaymentLink($result->id, $parameters);

            $this->_redirectUrl($result->url);

        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
            $this->_redirectReferer();
            return;
        }
    }

    /**
     * Retrieve checkout session
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get success url
     *
     * @return string
     */
    public function getSuccessUrl()
    {
        return Mage::getUrl('quickpaypayment/payment/success');
    }

    /**
     * Get cancel url
     *
     * @return string
     */
    public function getCancelUrl()
    {
        return Mage::getUrl('quickpaypayment/payment/cancel');
    }

    /**
     * Get callback url
     *
     * @return string
     */
    public function getCallbackUrl()
    {
        return Mage::getUrl('quickpaypayment/payment/callback');
    }
}