<?php

class Quickpay_Payment_Model_Type_Mobilepay
{
    /**
     * Prepare quote for checkout
     */
    public function initCheckout()
    {
        $collectTotals = false;
        $quoteSave = false;

        /**
         * Reset multishipping flag
         */
        if ($this->getQuote()->getIsMultiShipping()) {
            $this->getQuote()->setIsMultiShipping(false);
            $quoteSave = true;
        }

        /**
         *  Reset customer balance
         */
        if ($this->getQuote()->getUseCustomerBalance()) {
            $this->getQuote()->setUseCustomerBalance(false);
            $quoteSave = true;
            $collectTotals = true;
        }
        /**
         *  Reset reward points
         */
        if ($this->getQuote()->getUseRewardPoints()) {
            $this->getQuote()->setUseRewardPoints(false);
            $quoteSave = true;
            $collectTotals = true;
        }

        if ($collectTotals) {
            $this->getQuote()->collectTotals();
        }

        if ($quoteSave) {
            $this->getQuote()->save();
        }

        //Set dummy address to include shipping cost
        $this->getQuote()->getShippingAddress()
             ->setCountryId("DK")
             ->setPostcode("9000");
        $this->getQuote()->getShippingAddress()->collectTotals();
        $this->getQuote()->getShippingAddress()->setCollectShippingRates(true);
        $this->getQuote()->getShippingAddress()->collectShippingRates();

        $this->saveShippingMethod($this->getQuote());
    }

    /**
     * Save payment method on quote
     *
     * @param Mage_Sales_Model_Quote $quote
     */
    public function savePayment(Mage_Sales_Model_Quote $quote)
    {
        if ($quote->isVirtual()) {
            $quote->getBillingAddress()->setPaymentMethod('quickpay_mobilepay');
        } else {
            $quote->getShippingAddress()->setPaymentMethod('quickpay_mobilepay');
        }

        $data = array();
        $data['method'] = 'quickpay_mobilepay';
        $data['checks'] = Mage_Payment_Model_Method_Abstract::CHECK_USE_CHECKOUT
                          | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY
                          | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY
                          | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX
                          | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL;

        $payment = $quote->getPayment();
        $payment->importData($data);

        $quote->save();
    }

    /**
     * Save shipping method on quote
     *
     * @param Mage_Sales_Model_Quote $quote
     */
    public function saveShippingMethod(Mage_Sales_Model_Quote $quote, $shippingMethod = null)
    {
        if (is_null($shippingMethod)) {
            $shippingMethod = Mage::getStoreConfig('payment/quickpay_mobilepay/default_shipping_method');
        }

        $rate = $this->getQuote()->getShippingAddress()->getShippingRateByCode($shippingMethod);

        if (!$rate) {
            Mage::throwException(Mage::helper('checkout')->__('Invalid shipping method.'));
        }

        $quote->getShippingAddress()
              ->setShippingMethod($shippingMethod)
              ->setCollectShippingRates(true)
              ->collectTotals();

        $quote->save();
    }

    /**
     * Save billing address
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param $request
     */
    public function saveBilling(Mage_Sales_Model_Quote $quote, $request)
    {
        $invoiceAddress = $request->invoice_address;
        $nameParts = explode(' ', $invoiceAddress->name);

        $data = array(
            'firstname' => array_shift($nameParts),
            'lastname' => join(' ', $nameParts),
            'email' => $invoiceAddress->email,
            'street' => array(
                $invoiceAddress->street . " " . $invoiceAddress->house_number . $invoiceAddress->house_extension
            ),
            'city' => $invoiceAddress->city,
            'postcode' => $invoiceAddress->zip_code,
            'country_id' => $invoiceAddress->country_code,
            'telephone' => $invoiceAddress->phone_number,
        );

        //Set company if available
        if (isset($invoiceAddress->company_name)) {
            $data['company'] = $invoiceAddress->company_name;
        }

        $address = $quote->getBillingAddress();
        /* @var $addressForm Mage_Customer_Model_Form */
        $addressForm = Mage::getModel('customer/form');
        $addressForm->setFormCode('customer_address_edit')
                    ->setEntityType('customer_address')
                    ->setIsAjaxRequest(Mage::app()->getRequest()->isAjax());

        $addressForm->setEntity($address);

        // emulate request object
        $addressData    = $addressForm->extractData($addressForm->prepareRequest($data));
        $addressErrors  = $addressForm->validateData($addressData);

        if ($addressErrors !== true) {
            Mage::log(var_export(array_values($addressErrors), true), null, 'qp_mpo_addresserror.log');
        }

        $addressForm->compactData($addressData);

        //unset billing address attributes which were not shown in form
        foreach ($addressForm->getAttributes() as $attribute) {
            if (!isset($data[$attribute->getAttributeCode()])) {
                $address->setData($attribute->getAttributeCode(), NULL);
            }
        }

        $address->setCustomerAddressId(null);

        // Additional form data, not fetched by extractData (as it fetches only attributes)
        $address->setSaveInAddressBook(0);

        // validate billing address
        if (($validateRes = $address->validate()) !== true) {
            return array('error' => 1, 'message' => $validateRes);
        }

        $address->implodeStreetAddress();

        if (true !== ($result = $this->_validateCustomerData($data, $quote))) {
            return $result;
        }

        if (!$quote->isVirtual()) {
            /**
             * Billing address using otions
             */
            $usingCase = empty($request->shipping_address) ? 1 : 0;

            switch ($usingCase) {
                case 0:
                    $shipping = $quote->getShippingAddress();
                    $shipping->setSameAsBilling(0);
                    $this->saveShipping($quote, $request);
                    break;
                case 1:
                    $billing = clone $address;
                    $billing->unsAddressId()->unsAddressType();
                    $shipping = $quote->getShippingAddress();
                    $shippingMethod = $shipping->getShippingMethod();

                    // Billing address properties that must be always copied to shipping address
                    $requiredBillingAttributes = array('customer_address_id');

                    // don't reset original shipping data, if it was not changed by customer
                    foreach ($shipping->getData() as $shippingKey => $shippingValue) {
                        if (!is_null($shippingValue) && !is_null($billing->getData($shippingKey))
                            && !isset($data[$shippingKey]) && !in_array($shippingKey, $requiredBillingAttributes)
                        ) {
                            $billing->unsetData($shippingKey);
                        }
                    }
                    $shipping->addData($billing->getData())
                             ->setSameAsBilling(1)
                             ->setSaveInAddressBook(0)
                             ->setShippingMethod($shippingMethod)
                             ->setCollectShippingRates(true);
                    break;
            }
        }

        $quote->save();
    }

    /**
     * Save shipping address
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param $request
     */
    public function saveShipping(Mage_Sales_Model_Quote $quote, $request)
    {
        $shippingAddress = $request->shipping_address;
        $invoiceAddress = $request->invoice_address;
        $nameParts = explode(' ', $shippingAddress->name);

        $data = array(
            'firstname' => array_shift($nameParts),
            'lastname' => join(' ', $nameParts),
            'email' => $shippingAddress->email,
            'street' => array(
                $shippingAddress->street . " " . $shippingAddress->house_number . $shippingAddress->house_extension
            ),
            'city' => $shippingAddress->city,
            'postcode' => $shippingAddress->zip_code,
            'country_id' => $shippingAddress->country_code,
        );

        //Set telephone
        if (!empty($shippingAddress->phone_number)) {
            $data['telephone'] = $shippingAddress->phone_number;
        } else {
            $data['telephone'] = $invoiceAddress->phone_number;
        }

        //Set company if available
        if (isset($shippingAddress->company_name)) {
            $data['company'] = $shippingAddress->company_name;
        }

        $address = $quote->getShippingAddress();

        /* @var $addressForm Mage_Customer_Model_Form */
        $addressForm    = Mage::getModel('customer/form');
        $addressForm->setFormCode('customer_address_edit')
                    ->setEntityType('customer_address')
                    ->setIsAjaxRequest(Mage::app()->getRequest()->isAjax());

        $addressForm->setEntity($address);
        // emulate request object
        $addressData    = $addressForm->extractData($addressForm->prepareRequest($data));
        $addressErrors  = $addressForm->validateData($addressData);

        if ($addressErrors !== true) {
            Mage::log(var_export(array_values($addressErrors), true), null, 'qp_mpo_addresserror.log');
        }

        $addressForm->compactData($addressData);
        // unset shipping address attributes which were not shown in form
        foreach ($addressForm->getAttributes() as $attribute) {
            if (!isset($data[$attribute->getAttributeCode()])) {
                $address->setData($attribute->getAttributeCode(), NULL);
            }
        }

        $address->setCustomerAddressId(null);
        // Additional form data, not fetched by extractData (as it fetches only attributes)
        $address->setSaveInAddressBook(false);
        $address->setSameAsBilling(false);

        $address->implodeStreetAddress();
        $address->setCollectShippingRates(true);
    }

    /**
     * Validate customer data and set some its data for further usage in quote
     * Will return either true or array with error messages
     *
     * @param array $data
     * @return true|array
     */
    protected function _validateCustomerData(array $data, Mage_Sales_Model_Quote $quote)
    {
        /** @var $customerForm Mage_Customer_Model_Form */
        $customerForm = Mage::getModel('customer/form');
        $customerForm->setFormCode('checkout_register')
                     ->setIsAjaxRequest(Mage::app()->getRequest()->isAjax());

        if ($quote->getCustomerId()) {
            $customer = $quote->getCustomer();
            $customerForm->setEntity($customer);
            $customerData = $quote->getCustomer()->getData();
        } else {
            /* @var $customer Mage_Customer_Model_Customer */
            $customer = Mage::getModel('customer/customer');
            $customerForm->setEntity($customer);
            $customerRequest = $customerForm->prepareRequest($data);
            $customerData = $customerForm->extractData($customerRequest);
        }

        $customerErrors = $customerForm->validateData($customerData);

        if ($customerErrors !== true) {
            return array(
                'error'     => -1,
                'message'   => implode(', ', $customerErrors)
            );
        }

        if ($quote->getCustomerId()) {
            return true;
        }

        $customerForm->compactData($customerData);

        // spoof customer password for guest
        $password = $customer->generatePassword();
        $customer->setPassword($password);
        $customer->setPasswordConfirmation($password);
        // set NOT LOGGED IN group id explicitly,
        // otherwise copyFieldset('customer_account', 'to_quote') will fill it with default group id value
        $customer->setGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);

        $result = $customer->validate();
        if (true !== $result && is_array($result)) {
            return array(
                'error'   => -1,
                'message' => implode(', ', $result)
            );
        }

        // copy customer/guest email to address
        $quote->getBillingAddress()->setEmail($customer->getEmail());

        // copy customer data to quote
        Mage::helper('core')->copyFieldset('customer_account', 'to_quote', $customer, $quote);

        return true;
    }

    /**
     * Sets cart coupon code from checkout to quote
     *
     * @return $this
     */
    protected function _setCartCouponCode()
    {
        if ($couponCode = $this->getCheckout()->getCartCouponCode()) {
            $this->getQuote()->setCouponCode($couponCode);
        }
        return $this;
    }

    /**
     * Get frontend checkout session object
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get quote
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }
}