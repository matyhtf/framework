<?php 
$field_model = array(
	array('name'=>'自动编号','field'=>'title','value'=>'`id` int(11) NOT NULL auto_increment','select'=>true),
	array('name'=>'标题','field'=>'title','value'=>'`title` varchar(128) NOT NULL','select'=>true),
	array('name'=>'名称','field'=>'name','value'=>'`name` varchar(32) NULL','select'=>false),
	array('name'=>'大类ID','field'=>'tid','value'=>'`tid` int(11) NOT NULL','select'=>false),
	array('name'=>'大类名称','field'=>'tidname','value'=>'`tidname` varchar(32) NOT NULL','select'=>false),
	array('name'=>'小类ID','field'=>'tid2','value'=>'`tid2` int(11) NOT NULL','select'=>false),
	array('name'=>'小类名称','field'=>'tid2name','value'=>'`tid2name` varchar(32) NOT NULL','select'=>false),
	array('name'=>'URL地址','field'=>'title','value'=>'`url` varchar(128) NULL','select'=>false),
	array('name'=>'缩略图','field'=>'title','value'=>'`image` varchar(32) NULL','select'=>false),
	array('name'=>'放大图','field'=>'title','value'=>'`picture` varchar(32) NULL','select'=>false),
	array('name'=>'内容','field'=>'title','value'=>'`content` MEDIUMBLOB Not Null','select'=>false),
	array('name'=>'邮件','field'=>'title','value'=>'`email` varchar(64) NULL,','select'=>false),
	array('name'=>'点击数','field'=>'title','value'=>'`hits` int(11) Not Null','select'=>false),
	array('name'=>'作者','field'=>'title','value'=>'`author` varchar(32) Not Null','select'=>false),
	array('name'=>'添加时间(字符)','field'=>'title','value'=>'`posttime` timestamp NOT NULL default CURRENT_TIMESTAMP','select'=>true),
	array('name'=>'添加时间(数字)','field'=>'title','value'=>'`posttime` int(11) NOT NULL','select'=>false)
	/*
	array('name'=>'小类名称','field'=>'title','value'=>'varchar(128) Not Null','select'=>true),
	array('name'=>'小类名称','field'=>'title','value'=>'varchar(128) Not Null','select'=>true),
	array('name'=>'小类名称','field'=>'title','value'=>'varchar(128) Not Null','select'=>true),
	array('name'=>'小类名称','field'=>'title','value'=>'varchar(128) Not Null','select'=>true),
	array('name'=>'小类名称','field'=>'title','value'=>'varchar(128) Not Null','select'=>true),
	array('name'=>'小类名称','field'=>'title','value'=>'varchar(128) Not Null','select'=>true),
	
	
	
	
CREATE TABLE `chq_product` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(32) NOT NULL,
  `title` varchar(64) NOT NULL,
  `color` varchar(7) NOT NULL default '#ff0000',
  `orderid` int(11) NOT NULL default '0',
  `tid` int(11) NOT NULL,
  `tidname` varchar(32) NOT NULL,
  `tid2` int(11) NOT NULL,
  `tid2name` varchar(32) NOT NULL,
  `description` varchar(255) NOT NULL,
  `image` varchar(64) NOT NULL,
  `picture` varchar(64) NOT NULL,
  `content` mediumtext NOT NULL,
  `author` varchar(32) NOT NULL default 'admin',
  `readnum` int(11) NOT NULL,
  `model` varchar(32) NOT NULL,
  `info` varchar(128) NOT NULL,
  `postdate` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `orderid` (`orderid`)
) ENGINE=MyISAM  DEFAULT CHARSET=gb2312 AUTO_INCREMENT=122 ;
	*/
	);
?>