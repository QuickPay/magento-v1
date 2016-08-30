<?php
require_once "Mage/Checkout/controllers/OnepageController.php";
class Quickpay_Payment_OnepageController extends Mage_Checkout_OnepageController
{
	public function savePaymentAction()
    {
		$params = $this->getRequest()->getParams();
		print_r($params);
		exit;
	}
}
