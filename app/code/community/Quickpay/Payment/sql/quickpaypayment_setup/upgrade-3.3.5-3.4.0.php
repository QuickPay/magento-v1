<?php
/** @var $installer Mage_Api2_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

$installer->getConnection()->addColumn($installer->getTable('quickpaypayment/order_status'), 'acquirer', array(
    'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length' => 50,
    'comment' => 'Acquirer',
    'nullable' => false,
));

$installer->getConnection()->addColumn($installer->getTable('quickpaypayment/order_status'), 'is_3d_secure', array(
    'type' => Varien_Db_Ddl_Table::TYPE_BOOLEAN,
    'default' => 0,
    'comment' => 'Is 3D Secure',
));

$installer->endSetup();