<?php
if( ! defined( 'XOOPS_ROOT_PATH' ) ) die('root path not defined');

/**
 * @id          plugin.php
 * @desc        plugin handler
 * 
 * @copyright   The XOOPS E-commerce project - Shoops
 * @license     Business License
 * @package     Shop
 * @subpackage  Plugin
 * @author      RB Lin
 * @email       xtheme@gmail.com
 * @version     0.2
 * @update      2010-04-13
 */
 
// include dirname( __FILE__ ).'/../../plugin.php';

class shoopsPlugin
{
	var $option = array();
	
	function __set($k, $c) {
		$this->option[$k]= $c;
	}
	
	function __get($k) {
		return $this->option[$k];
	}
	
	function __construct($type = 'payment')
	{
		global $xoopsConfig;
		
		if (!is_object($xoopsConfig)) {
			$config_handler =& xoops_gethandler('config');
			$xoopsConfig =& $config_handler->getConfigsByCat(XOOPS_CONF);
		}
		
		$option['module_dir']  = basename( dirname( dirname( __FILE__  ) ) );
		$option['module_url']  = XOOPS_URL.'/modules/'.$option['module_dir'];
		$option['plugin_type'] = $type;
		$option['plugin_dir']  = get_class($this);
		
		// load base language
		if (!@include_once XOOPS_ROOT_PATH.'/modules/'.$option['module_dir'].'/language/'.$xoopsConfig['language'].'/plugin.php') {
			include_once XOOPS_ROOT_PATH.'/modules/'.$option['module_dir'].'/language/tchinese_utf8/plugin.php';
		}
		
		// load plugin language
		if (!@include_once XOOPS_ROOT_PATH.'/modules/'.$option['module_dir'].'/plugins/'.$type.'/'.$option['plugin_dir'].'/language/'.$xoopsConfig['language'].'.php') {
			include_once XOOPS_ROOT_PATH.'/modules/'.$option['module_dir'].'/plugins/'.$type.'/'.$option['plugin_dir'].'/language/tchinese_utf8.php';
		}
		
		$this->option = $option;
	}
	
	function shoopsPlugin($type = 'payment')
    {
		$this->__construct($type);
    }
	
	function getException()
    {
		//echo '例外處理';
	}
	
	function getObject()
    {
		$handler =& xoops_getmodulehandler($this->plugin_type, $this->option['module_dir']);
		$object = $handler->getByDirname($this->option['plugin_dir']);
		if (!is_object($object)) return false;
		return $object;
	}
	
	function getConfig($configName)
    {
		static $configs = NULL;
		if ($configs === NULL) {
			$object =& $this->getObject();
			if (!is_object($object)) return false;
			$config_array = $object->getVar('config');
			foreach ($config_array as $row) {
				$configs[$row['name']] = $row['value'];
			}
		}
		//print_r($configs);
		$ret = false;
		if (isset($configs[$configName])) {
			$ret =& $configs[$configName];
		}
		return $ret;
	}
	
	function loadInfo()
    {
		$info = array();
		return $info;
	}
	
	// fare free?
	function getFare($count_price)
    {
        $object =& $this->getObject();
		if (is_object($object)) {
			$fare      = $object->getVar('fare');
			$fare_free = $object->getVar('fare_free');
			// 付款方式可另外設定 手續費百分比(fare_percent)，與固定手續費(fare)相加
			if ($this->plugin_type == 'payment') {
				$fare_percent = $object->getVar('fare_percent');
				if (!empty($fare_percent)) {
					$fare += $count_price * ($fare_percent / 100);
				}
			}
			if (!empty($fare_free) && ($count_price > $fare_free)) {
				$fare = 0;
			}
			return ceil($fare);
		} else {
			trigger_error(__CLASS__ .'::'. __FUNCTION__ .' cant load object', E_USER_WARNING);
		}
    }
	
    // operate form
    function getForm($order_sn = '')
    {
		$ret = '';
		return $ret;
    }
	
    // order response code
    function getResponseCode($order_sn = '')
    {
		$order_handler =& xoops_getmodulehandler('order', $this->module_dir);
		$order_obj = $order_handler->get($order_sn, 'order_sn');
		if (!is_object($order_obj)) return false;
		
		$code_field = ($this->plugin_type == 'payment') ? 'pay_code' : 'ship_code';
		$ret = $order_obj->getVar($code_field);
		return $ret;
    }
	
	// order response
    function getResponse($order_sn = '')
    {
		$response_code =& $this->getResponseCode($order_sn);
		/*
		switch ($response_code) {
			case 'AAA':
			default:
				$ret = 'response_code: '.$response_code;
			break;
		}
		*/
		$ret = 'response_code: '.$response_code;
		return $ret;
    }
	
}

?>