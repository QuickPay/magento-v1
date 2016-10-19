<?php
class Quickpay_Payment_Helper_Order extends Mage_Core_Helper_Abstract {
    public function isQuickpayOrder($order) {
        return $order->getPayment()->getMethod() == 'quickpaypayment_payment';
    }
}
