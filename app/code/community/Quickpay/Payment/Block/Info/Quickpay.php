<?php
class Quickpay_Payment_Block_Info_Quickpay extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('quickpaypayment/info/default.phtml');
    }

    /**
     * Get QuickPay info HTML
     *
     * @return string
     */
    public function getQuickpayInfoHtml()
    {
        $res = "";
        if ($this->getInfo()->getOrder()) {
            $read = Mage::getSingleton('core/resource')->getConnection('core_read');

            $resource = Mage::getSingleton('core/resource');
            $table = $resource->getTableName('quickpaypayment_order_status');

            $query = "SELECT qpstat, transaction, cardtype, cardnumber, acquirer, is_3d_secure, currency FROM {$table} WHERE ordernum = :order_number";
            $binds = array(
                'order_number' => $this->getInfo()->getOrder()->getIncrementId(),
            );
            $row = $this->paymentData = $read->fetchRow($query, $binds);

            if (is_array($row)) {
                if ($row['qpstat'] == 20000) {
                    $res .= "<table border='0'>";

                    if ($row['transaction'] != '0') {
                        $res .= "<tr><td>" . $this->__('Transaktions ID:') . "</td>";
                        $res .= "<td>" . $row['transaction'] . "</td></tr>";
                    }

                    if ($row['qpstat'] == 20000) {
                        $res .= "<tr><td>" . $this->__('Korttype:') . "</td>";

                        $cardÍmagePath = sprintf('images/quickpaypayment/cards/%s.png', $row['cardtype']);
                        $cardImage = $this->getSkinUrl($cardÍmagePath);

                        $res .= '<td><img src="'. $cardImage .'" width="40" alt="' . $row['cardtype'] . '"></td></tr>';

                        $res .= "<tr><td>" . $this->__('Valuta:') . "</td>";
                        $res .= "<td>" . $row['currency'] . "</td></tr>";

                        if (! empty($row['cardnumber'])) {
                            $res .= "<tr><td>" . $this->__('Kortnummer:') . "</td>";
                            $res .= "<td>" . implode(" ", str_split($row['cardnumber'], 4)) . "</td></tr>";
                        }

                        if (! empty($row['acquirer'])) {
                            $res .= "<tr><td>" . $this->__('Acquirer:') . "</td>";
                            $res .= "<td>" . $row['acquirer'] . "</td></tr>";
                        }

                        if (! empty($row['is_3d_secure'])) {
                            $res .= "<tr><td>" . $this->__('Is 3D Secure:') . "</td>";
                            $res .= "<td>" . ($row['is_3d_secure'] ? $this->__('Yes') : $this->__('No')) . "</td></tr>";
                        }
                    }

                    $res .= "</table><br>";
                } else {
                    $res .= "<br>" . $this->__('Der er endnu ikke registreret nogen betaling for denne ordre!') . "<br>";
                }
            }
        }

        return $res;
    }
}
