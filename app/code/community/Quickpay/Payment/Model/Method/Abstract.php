<?php
abstract class Quickpay_Payment_Model_Method_Abstract extends Mage_Payment_Model_Method_Abstract
{
    protected $_formBlockType = 'quickpaypayment/payment_form';
    protected $_infoBlockType = 'quickpaypayment/info_quickpay';

    protected $_canCapture              = true;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canUseForMultishipping  = false;

    /**
     * Allowed currency types
     * @var array
     */
    protected $_allowCurrencyCode = array(
        'ADP', 'AED', 'AFA', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZM', 'BAM', 'BBD', 'BDT', 'BGL', 'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB',
        'BOV', 'BRL', 'BSD', 'BTN', 'BWP', 'BYR', 'BZD', 'CAD', 'CDF', 'CHF', 'CLF', 'CLP', 'CNY', 'COP', 'CRC', 'CUP', 'CVE', 'CYP', 'CZK', 'DJF', 'DKK',
        'DOP', 'DZD', 'ECS', 'ECV', 'EEK', 'EGP', 'ERN', 'ETB', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL', 'GHC', 'GIP', 'GMD', 'GNF', 'GTQ', 'GWP', 'GYD', 'HKD',
        'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS', 'INR', 'IQD', 'IRR', 'ISK', 'JMD', 'JOD', 'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KPW', 'KRW', 'KWD', 'KYD',
        'KZT', 'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'LTL', 'LVL', 'LYD', 'MAD', 'MDL', 'MGF', 'MKD', 'MMK', 'MNT', 'MOP', 'MRO', 'MTL', 'MUR', 'MVR', 'MWK',
        'MXN', 'MXV', 'MYR', 'MZM', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'OMR', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'ROL', 'RUB',
        'RUR', 'RWF', 'SAR', 'SBD', 'SCR', 'SDD', 'SEK', 'SGD', 'SHP', 'SIT', 'SKK', 'SLL', 'SOS', 'SRG', 'STD', 'SVC', 'SYP', 'SZL', 'THB', 'TJS', 'TMM',
        'TND', 'TOP', 'TPE', 'TRL', 'TRY', 'TTD', 'TWD', 'TZS', 'UAH', 'UGX', 'USD', 'UYU', 'UZS', 'VEB', 'VND', 'VUV', 'XAF', 'XCD', 'XOF', 'XPF', 'YER',
        'YUM', 'ZAR', 'ZMK', 'ZWD'
    );

    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('quickpaypayment/payment/redirect');
    }

    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    public function canUseInternal()
    {
        // Må kun bruges når der redigeres i en order
        if (Mage::app()->getFrontController()->getAction() instanceof Mage_Adminhtml_Sales_Order_EditController) {
            // Her kunne testes om det i forvejen er en Quickpay order.
            // med noget i stilen af Mage::getSingleton('adminhtml/session_quote')->getOrder()
            return true;
        }
        return false;
    }

    /*validate the currency code is avaialable to use for Quickpay or not*/
    public function validate()
    {
        parent::validate();

        if (! Mage::app()->getFrontController()->getAction() instanceof Mage_Adminhtml_Sales_Order_EditController) {
            $currency_code = $this->getQuote()->getBaseCurrencyCode();
            if (!in_array($currency_code, $this->_allowCurrencyCode)) {
                Mage::throwException(Mage::helper('quickpaypayment')->__('Valutakoden (%s) er ikke kompatible med Quickpay', $currency_code));
            }
        }

        return $this;
    }

    public function calcLanguage($lan)
    {
        $map_codes = array(
            'nb' => 'no',
            'nn' => 'no'
        );

        $splitted = explode('_', $lan);
        $lang = $splitted[0];
        if ( isset ( $map_codes[$lang] ) ) return $map_codes[$lang];
        return $lang;
    }

    // Calculates if any of the trusted logos are to be shown - in that case return true
    public function showTrustedList()
    {
        $logoArray = explode(',', $this->getConfigData('trustedlogos'));
        foreach ($logoArray as $item) {
            if ($item == 'verisign_secure' ||
                $item == 'mastercard_securecode' ||
                $item == 'pci' ||
                $item == 'nets' ||
                $item == 'euroline'
            ) {
                return true;
            }
        }
        return false;
    }

    // Calculates if any of the card logos are to be shown - in that case return true
    public function showCardsList()
    {
        $logoArray = explode(',', $this->getConfigData('cardlogos'));

        $show_cards = array (
                'dankort',
                'edankort',
                'danskenetbetaling',
                'nordea',
                'ewire',
                'forbrugsforeningen',
                'visa',
                'visaelectron',
                'mastercard',
                'maestro',
                'jcb',
                'diners',
                'amex',
                'sofort',
                'viabill'
        );

        foreach ($logoArray as $item) {
                if(in_array($item, $show_cards)) {
                        return true;
                }
        }
        return false;
    }


    public function canCapturePartial()
    {
        $orderid = $this->getInfoInstance()->getOrder()->getIncrementId();
        $orderid = explode("-", $orderid);
        $resource = Mage::getSingleton('core/resource');
        $table = $resource->getTableName('quickpaypayment_order_status');
        $read = $resource->getConnection('core_read');

        $row = $read->fetchRow("SELECT cardtype FROM $table WHERE ordernum = '" . $orderid[0] . "'");
        if ($row['cardtype'] == 'dankort') {
            return true;
        }

        $controller = Mage::app()->getFrontController()->getAction();
        if($controller instanceof Mage_Adminhtml_Sales_Order_CreditmemoController) { // allow editing of qty in creditmemo
                return true;
        } else if ($controller instanceof Mage_Adminhtml_Sales_Order_InvoiceController) { // allow editing of qty in invoice
                return true;
        }

        return false;
    }

    public function getTitle()
    {
        // Tilføjer max beløb hvis vi er ved at ændre en order.
        if (Mage::app()->getFrontController()->getAction() instanceof Mage_Adminhtml_Sales_Order_EditController) {
            $orderid = Mage::getSingleton('adminhtml/session_quote')->getOrder()->getIncrementId();
            $orderid = explode("-", $orderid);

            $resource = Mage::getSingleton('core/resource');
            $table = $resource->getTableName('quickpaypayment_order_status');
            $read = $resource->getConnection('core_read');
            $row = $read->fetchRow("SELECT amount, currency FROM $table WHERE ordernum = '" . $orderid[0] . "'");

            return $this->getConfigData('title') . " - " . Mage::helper('quickpaypayment')->__('Maks beløb:') . " " . $row['amount'] / 100 . " " . $row['currency'];
        }

        return $this->getConfigData('title');
    }
}