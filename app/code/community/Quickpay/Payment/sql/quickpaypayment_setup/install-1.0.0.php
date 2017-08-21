<?php

$installer = $this;
/* @var $installer Quickpay_Payment_Model_Mysql4_Setup */

$installer->startSetup();

$installer->run("
	CREATE TABLE IF NOT EXISTS {$installer->getTable('quickpaypayment_order_status')} (
	  id 				int(32) 	NOT NULL auto_increment,
	  status 			int(2)		NOT NULL,
	  ordernum 			varchar(32) default NULL,
	  amount 			varchar(32) default NULL,
	  time 				varchar(20) default NULL,
	  pbsstat 			varchar(32) default NULL,
	  qpstat 			varchar(32) default NULL,
	  qpstatmsg 		varchar(60) default NULL,
	  chstat 			int(32) 	default NULL,
	  chstatmsg 		text,
	  merchantemail 	varchar(128) default NULL,
	  merchant 			varchar(128) default NULL,
	  currency 			varchar(10) default NULL,
	  cardtype 			varchar(32) default NULL,
	  transaction 		varchar(32) default NULL,
	  md5check			varchar(128) default NULL,
	  capturedAmount 	varchar(32) DEFAULT NULL,
	  refundedAmount 	varchar(32) DEFAULT NULL,
	  splitpayment 		varchar(32) DEFAULT NULL,
	  fraudprobability 	varchar(254) DEFAULT NULL,
	  fraudremarks 		TEXT DEFAULT NULL,
	  fraudreport 		TEXT DEFAULT NULL,
	  fee				varchar(32) DEFAULT NULL,
	  cardnumber 		varchar(32) DEFAULT NULL,
	  PRIMARY KEY  (id),
	  KEY ordernum (ordernum)
	) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
");


$installer->endSetup();

