<?php
/**
 * @id          plugin.php
 * @desc        金物流模組後台
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
 
include 'cp_common.php';

$op   = isset($_REQUEST['op'])   ?  $_REQUEST['op']   : 'list';
$type = isset($_REQUEST['type']) ?  $_REQUEST['type'] : 'payment';

// 檢証項目
switch($type) {
	case 'shipping':
		$type_title = '配送';
		$check_item = BACK_AREA_SHIPPING;
		break;
	case 'payment':
	default:
		$type_title = '付款';
		$check_item = BACK_AREA_PAYMENT;
		break;
}

// 權限檢証
if(!shop_checkRight($check_item, BACK_VIEW_PERM)) {
	redirect_header('index.php', 3, _NOPERM);
	exit();
}

switch($op) {
	// 外掛列表
	case 'list':
		include 'cp_header.php';
		$handle = opendir(SHOP_PATH.'/plugins/'.$type);
		$plugin_list = array();
		while ($plugin_dir = readdir($handle)) {
			array_push($plugin_list, $plugin_dir);
		}
		sort($plugin_list); // sort by name
		foreach($plugin_list as $plugin_dir) {
			if ($plugin_dir != '' && $plugin_dir != 'index.html' && !preg_match("/^\..*$/",$plugin_dir) && is_dir(SHOP_PATH.'/plugins/'.$type.'/'.$plugin_dir)) {
				// load plugin configs file [ plugin_version.php ]
				$config_file = SHOP_PATH.'/plugins/'.$type.'/'.$plugin_dir.'/plugin_version.php';
				if (!@include $config_file) {
					echo "$type plugin $plugin_dir's configs file plugin_version.php not found!";
					continue;
				}
				$plugin_handler = new $plugin_dir();
				$plugin_info = $plugin_handler->loadInfo();
				/*echo '<pre>';
				print_r($plugin_info);
				echo '</pre>';*/
				$xoopsTpl->append('plugins', $plugin_info);
			}
		}
		closedir($handle);
		$xoopsTpl->assign('xoops_pagetitle', sprintf('%s方式一覽', $type_title));
		$xoopsTpl->display('file:'.SHOP_PATH.'/admin/templates/plugin_list.html');
		
		include 'cp_footer.php';
		break;
		
	// 設定
	case 'setup':
	// 啟用
	case 'install':
		include 'cp_header.php';
		
		$plugin_dir = isset($_GET['name']) ? $myts->stripSlashesGPC($_GET['name']) : '';
		// load plugin configs file [ plugin_version.php ]
		$config_file = SHOP_PATH.'/plugins/'.$type.'/'.$plugin_dir.'/plugin_version.php';
		if (!@include $config_file) {
			echo "$type plugin $plugin_dir's configs file plugin_version.php not found!";
			exit();
		}
		$plugin_handler = new $plugin_dir();
		$install = $plugin_handler->loadInfo();
		
		// 差異處
		switch($op) {
			case 'setup':
				$install['op'] = 'update';
				$pagetitle = sprintf('設定%s', $install['name']);
				break;
			case 'install':
				$install['op'] = 'insert';
				$pagetitle = sprintf('啟用%s模組', $install['name']);
				break;
		}
		
		// shipping only
		if ($type == 'shipping') {
			// 列出可以選擇的付費方式
			$payment_handler =& xoops_getmodulehandler('payment');
			$criteria = array();
			$payment_obj = $payment_handler->getObjects($criteria);
			foreach (array_keys($payment_obj) as $p) {
				$payment = array();
				$payment['id']          = $payment_obj[$p]->getVar('id');
				$payment['name']        = $payment_obj[$p]->getVar('name');
				$payment['dirname']     = $payment_obj[$p]->getVar('dirname');
				$payment['checked']     = 0;
				if ($op == 'setup') {
					$sql = sprintf('SELECT COUNT(*) FROM %s WHERE pay_name = %s AND ship_name = %s', $xoopsDB->prefix('shop_shipping_link'), $xoopsDB->quote($payment_obj[$p]->getVar('dirname')), $xoopsDB->quote($plugin_dir));
					$result = $xoopsDB->query($sql);
					list($count) = $xoopsDB->fetchRow($result);
					if ($count) $payment['checked'] = 1;
				}
				$xoopsTpl->append('payment', $payment);
			}
		}
		
		$install['response'] = false;
		$response_file = SHOP_PATH.'/plugins/'.$install['type'].'/'.$install['dirname'].'/response.php';
		$response_url  = SHOP_URL.'/plugins/'.$install['type'].'/'.$install['dirname'].'/response.php';
		if (is_file($response_file)) $install['response'] = $response_url;
		
		$xoopsTpl->assign('install', $install);
		$xoopsTpl->assign('xoops_pagetitle', $pagetitle);
		$xoopsTpl->display('file:'.SHOP_PATH.'/admin/templates/plugin_setup.html');
		
		include 'cp_footer.php';
		break;
		
		
	// 更新
	case 'update':
	// 新增
	case 'insert':
		
		/* 設定參數 serialize */
	    $config = array();
	    if (isset($_POST['cfg_value']) && is_array($_POST['cfg_value']))
	    {
	        for ($i = 0; $i < count($_POST['cfg_value']); $i++)
	        {
	            $config[] = array('name' => trim($_POST['cfg_name'][$i]), 'value' => trim($_POST['cfg_value'][$i]));
	        }
	    }
		$handler =& xoops_getmodulehandler($type);
		
		// shipping only 寫入關聯付款方式
		$shippinglink_handler =& xoops_getmodulehandler('shippinglink');
		foreach ($_POST['usepay'] as $pay_name) {
			$shippinglink_handler->insert($_POST['dirname'], $pay_name);
		}
		
		// 差異處
		switch($op) {
			case 'update':
				$object = $handler->getByDirname($_POST['dirname']);
				$msg = '更新';
				break;
			case 'insert':
				$object = $handler->create();
				$msg = '啟用';
				break;
		}
		
		$object->setVar('name',         $_POST['name']);
		$object->setVar('description',  $_POST['description']);
		$object->setVar('dirname',      $_POST['dirname']);
		$object->setVar('fare',         $_POST['fare']);
		$object->setVar('fare_free',    $_POST['fare_free']);
		if ($type == 'payment') {
			$object->setVar('fare_percent', isset($_POST['fare_percent']) ? $_POST['fare_percent'] : 0);
		}
		$object->setVar('config',       $config);
		if (!$handler->insert($object)) {
			//echo sprintf('模組%s時發生錯誤!', $msg);
			redirect_header('plugin.php?type='.$type, 3, sprintf('模組%s時發生錯誤!', $msg));
		} else {
			//echo sprintf('模組已%s完成!', $msg);
			redirect_header('plugin.php?type='.$type, 3, sprintf('模組已%s完成!', $msg));
		}
		break;
		
		
	// 停用 AJAX
	case 'uninstall':
	
		$plugin_dir = isset($_POST['name']) ? $myts->stripSlashesGPC($_POST['name']) : '';
		$response = '';
		$update = false;
		
		if ($xoopsUser->getVar('level') < 5) {
			$response = '只有最高管理員才能執行此動作!!';
			exit();
		}
		
		if ($response == '') {
			// 檢查是否有訂單使用該模組
			$filed_name = ($type == 'payment') ? 'pay_mode' : 'ship_mode';
			$sql = 'SELECT COUNT(*) FROM '.$xoopsDB->prefix('shop_order').' WHERE '.$filed_name.' = '.$xoopsDB->quote($plugin_dir);
			if ($result = $xoopsDB->query($sql)) {
				list($count) = $xoopsDB->fetchRow($result);
				if ( $count > 0 ){
					$response = '有使用此模組的訂單存在!!<br />為避免錯誤發生，系統將取消停用動作!!';
				}
			}
		}
		
		if ($response == '') {
			$handler =& xoops_getmodulehandler($type);
			$object = $handler->getByDirname($plugin_dir);
			if (!$handler->delete($object)) {
				$response = '停用模組時發生錯誤!';
			} else {
				$response = '模組已停用!';
				$update = true;
			}
		}
		
		$arr = array();
		$arr['response'] = $response;
		$arr['update'] = $update;
		echo rb_json_encode($arr);
		break;
}
?>