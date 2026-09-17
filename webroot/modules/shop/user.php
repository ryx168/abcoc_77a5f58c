<?php
include 'common.php';

$xoopsOption['pagetype'] = 'user';

$op = isset($_REQUEST['op'])  ? $_REQUEST['op'] : 'center';
$uid = is_object($xoopsUser) ? $xoopsUser->getVar('uid') : 0;

$profile_handler =& xoops_getmodulehandler('profile');
$order_handler =& xoops_getmodulehandler('order');
$discount_handler =& xoops_getmodulehandler('discount');

// 身份保護
if (!$uid && $op != 'login') {
	header('location: user.php?op=login');
	exit();
}

switch ($op) {	
	// 登入
	case 'login':
		if ($_POST) {
			include_once SHOP_PATH.'/include/checklogin.php';
		} else {
			/*if ($uid) {
				header('location: user.php');
				exit();
			}*/
			// 登入表單
			include SHOP_PATH.'/header.php';
			$load_handler->loadJs('jquery/jquery.validate.min.js');
			$load_handler->rewrite();
			$xoopsTpl->display(SHOP_TPL_PATH.'/shop_login.html');
			include SHOP_PATH.'/footer.php';
		}
		break;
		
	// 登出
	case 'logout':
	    $_SESSION = array();
		session_destroy();
		if ($xoopsConfig['use_mysession'] && $xoopsConfig['session_name'] != '') {
			setcookie($xoopsConfig['session_name'], '', time()- 3600, '/',  '', 0);
		}
	    // clear entry from online users table
	    if (is_object($xoopsUser)) {
	        $online_handler =& xoops_gethandler('online');
	        $online_handler->destroy($uid);
	    }
	    redirect_header('user.php', 3, _SHOOPS_LOGOUT);
		break;
		
	// 會員中心
	case 'center':
	default:
		include SHOP_PATH.'/header.php';
		// xoops_module_header
		$load_handler->loadJs('jquery/jquery.validate.min.js');
		$load_handler->rewrite();
		
		// 抓出會員資料
		$user['uname'] = $xoopsUser->getVar('uname');
		$user['last_login'] = formatTimestamp($xoopsUser->getVar('last_login'), 'm');
		$user['user_avatar'] = $xoopsUser->getVar('user_avatar');
		$rank = $xoopsUser->rank();
		$user['user_rank'] = $rank['title'];
			
		$profile_obj = $profile_handler->get($uid);
			
		// For Xoops 已經有帳號 但沒填寫購物會員資料者
		if (!is_object($profile_obj)) {
			redirect_header('user.php?op=profile', 3, _SHOOPS_SET_PROFILE_FIRST);
			exit();
		}
		
		header('location: user.php?op=profile');
		/*
		$user['point'] = $profile_obj->getVar('point');
		$user['buy_times'] = $profile_obj->getBuyTimes(); // 個人消費次數
		$user['buy_total'] = shop_price_format($profile_obj->getBuyTotal()); // 個人消費金額
		$xoopsTpl->assign('user', $user);
			
		// 列出目前的優惠活動
		$now = time();
		$where = array();
		$where[] = 'status = 1';
		$where[] = 'start_time <= '.$xoopsDB->quoteString($now);
		$where[] = 'end_time >= '.$xoopsDB->quoteString($now);
		$discount_obj =& $discount_handler->getObjects($where, 'start_time DESC, end_time DESC');
		foreach (array_keys($discount_obj) as $d) {
			$discount['id'] = $discount_obj[$d]->getVar('id');
			$discount['name'] = $discount_obj[$d]->getVar('name');
			$discount['start_time'] = formatTimestamp($discount_obj[$d]->getVar('start_time'), 'Y-m-d');
			$discount['end_time']   = formatTimestamp($discount_obj[$d]->getVar('end_time'), 'Y-m-d');
			$xoopsTpl->append('discount', $discount);
		}
		
		// Order
		$where = array();
		$where[] = 'order_user = '.$uid;
		$where[] = 'checkout < 2';
		$orders_obj = $order_handler->getObjects($where, 'created DESC', 3);
		foreach (array_keys($orders_obj) as $o) {
			$order = array();
			$order['id'] = $orders_obj[$o]->getVar('id');
			$order['order_sn'] = $orders_obj[$o]->getVar('order_sn');
			$order['payable_price'] = shop_price_format($orders_obj[$o]->getPayablePrice());
			$order['created'] = formatTimestamp($orders_obj[$o]->getVar('created'), 'Y-m-d H:i');
			$order['pay_status'] = $orders_obj[$o]->getPayStatus();
			$order['ship_status'] = $orders_obj[$o]->getShipStatus();
			$order['order_status'] = $orders_obj[$o]->getUserOrderStatus();
			$xoopsTpl->append('orders', $order);
		}
		
		// PM
		$where = new where('to_userid', $uid);
		$where = new where('read_msg', 0);
		$where->setOrder('DESC');
		$where->setLimit(3);
		$pm_handler =& xoops_gethandler('privmessage');
		$pm_obj = $pm_handler->getObjects($where);
		foreach (array_keys($pm_obj) as $p) {
			$pmsg = array();
			$pmsg['msg_id'] = $pm_obj[$p]->getVar('msg_id');
			//$pmsg['sender_uid'] = $pm_obj[$p]->getVar('from_userid'); // 寄件者 uid
			$pmsg['sender'] = XoopsUser::getUnameFromId($pm_obj[$p]->getVar('from_userid')); // 寄件者
			$pmsg['read'] = $pm_obj[$p]->getVar('read_msg');
			$pmsg['subject'] = $pm_obj[$p]->getVar('subject');
			$pmsg['msg_text'] = $pm_obj[$p]->getVar('msg_text');
			$pmsg['msg_time'] = formatTimestamp($pm_obj[$p]->getVar('msg_time'), 'Y-m-d');
			$xoopsTpl->append('pmsg', $pmsg);
		}
		
		$xoopsTpl->assign('xoops_showrblock', 0);
		$xoopsTpl->assign('xoops_showcblock', 0);
		$xoopsTpl->assign('xoops_showlblock', 0);
		$xoopsTpl->display(SHOP_TPL_PATH.'/shop_user.html');
		
		include SHOP_PATH.'/footer.php';
		*/
		break;
		
	// 個人資料
	case 'profile':
		include SHOP_PATH.'/header.php';
		// xoops_module_header
		// loadPlugin('jquery/cascade') goes through RbLoad's loadDir(), which
		// silently drops files whose fopen() self-check fails or never actually
		// makes it into the interpolated template output for reasons not fully
		// tracked down - in practice the #district cascade plugin never reached
		// the page, so clicking Submit threw a silent JS error ($(...).cascade is
		// not a function) partway through the form's ready() handler, and
		// $('#profile-form').validate({...}) - registered right after in the
		// same handler - never ran either, leaving the submit button armed with
		// no validation and no visible feedback. Load the plugin directly.
		echo '<script type="text/javascript" src="'.FRAMEWORKS_URL.'/jquery/cascade/jquery.cascade.js"></script>'."\n";
		echo '<script type="text/javascript" src="'.FRAMEWORKS_URL.'/jquery/cascade/jquery.cascade.ext.js"></script>'."\n";
		$load_handler->loadJs('jquery/jquery.validate.min.js');
		$load_handler->rewrite();
		
		$user['uid']   = $uid;
		$user['uname'] = $xoopsUser->getVar('uname');
		$user['email'] = $xoopsUser->getVar('email');
		$user['pass']  = $xoopsUser->getVar('pass');
		$rank = $xoopsUser->rank();
		$user['rank'] = $rank['title'];
		
		$profile_obj = $profile_handler->get($uid);
		if (!is_object($profile_obj)) {
			$user['profile'] = false;
			$search_city = _SHOOPS_DEFAULT_CITY;
		} else {	
			//$user['name'] = $profile_obj->getRealname();
			$user['last_name'] = $profile_obj->getVar('last_name');
			$user['first_name'] = $profile_obj->getVar('first_name');
			$user['sex'] = $profile_obj->getSex();
			$user['age'] = $profile_obj->getAge();
			$user['birthday'] = $profile_obj->getBirthday();
			$user['constellation'] = $profile_obj->getConstellation(); // 星座
			$user['blood'] = $profile_obj->getVar('blood');
			$user['country'] = $profile_obj->getVar('country');
			$user['city'] = $profile_obj->getLocation('city');
			$user['district'] = $profile_obj->getLocation('district');
			$user['address'] = $profile_obj->getVar('address');
			$user['location'] = $profile_obj->getVar('location');
			$user['vocation'] = $profile_obj->getVar('vocation');
			$user['phone_area'] = $profile_obj->getPhone('area');
			$user['phone'] = $profile_obj->getPhone('num');
			$user['mobile'] = $profile_obj->getVar('mobile');
			$user['point'] = $profile_obj->getVar('point');
			$user['subscribe'] = $profile_obj->getVar('subscribe');
			$user['profile'] = true;
			$search_city = $profile_obj->getLocation('city');
		}
		
		// 選單資料
		$list_handler =& xoops_getmodulehandler('list');
		$xoopsTpl->assign('year_opt',  $list_handler->getBirthYear());
		$xoopsTpl->assign('month_opt', $list_handler->getBirthMonth());
		$xoopsTpl->assign('day_opt',   $list_handler->getBirthDay());
		$xoopsTpl->assign('vocation_opt',   $list_handler->getVocationList());
		// 國家列表
		include_once XOOPS_ROOT_PATH.'/class/xoopslists.php';
		//$xoopsTpl->assign('country_opt',  XoopsLists::getCountryList());
		$xoopsTpl->assign('country_opt',  array('Taiwan', 'China', 'United States'));
		// 城市列表
		$sql = 'SELECT city_id, city_name FROM '.$xoopsDB->prefix('shop_city').' ORDER by city_id';
		$city_data = $xoopsDB->query($sql);
		while(list($city_id, $city_name) = $xoopsDB->fetchRow($city_data)) {
			$city['id'] = $city_id;
			$city['name'] = $city_name;
			$xoopsTpl->append('city', $city);
		}
		$sql  = 'SELECT city_id FROM '.$xoopsDB->prefix('shop_city');
		$sql .= ' WHERE city_name = '.$xoopsDB->quoteString($search_city);
		$query = $xoopsDB->query($sql);
		list($city_id) = $xoopsDB->fetchRow($query);
		//echo $city_id;
		// 地區列表
		$sql  = 'SELECT district_id, district_name FROM '.$xoopsDB->prefix('shop_district');
		$sql .=	' WHERE city_id = '.$city_id.' ORDER by district_id';
		//echo $sql;
		$district_data = $xoopsDB->query($sql);
		while(list($district_id, $district_name) = $xoopsDB->fetchRow($district_data)) {
			$district = array();
			$district['id'] = $district_id;
			$district['name'] = $district_name;
			$xoopsTpl->append('district', $district);
		}
		$xoopsTpl->assign('user', $user);
		
		$xoopsTpl->assign('xoops_pagetitle',  _SHOOPS_PROFILE);
		//$xoopsTpl->assign('xoops_showlblock', 0);
		$xoopsTpl->assign('xoops_showcblock', 0);
		$xoopsTpl->assign('xoops_showrblock', 0);
		$xoopsTpl->display(SHOP_TPL_PATH.'/shop_user_profile.html');
		
		include SHOP_PATH.'/footer.php';
		break;
		
	// 訂單列表
	case 'order_list':
		include SHOP_PATH.'/header.php';
		
		$where = array();
		$where[] = 'order_user = '.$uid;
		$where[] = 'checkout < 2';
		$orders_obj = $order_handler->getObjects($where);
		foreach (array_keys($orders_obj) as $o) {
			$order = array();
			$order['id'] = $orders_obj[$o]->getVar('id');
			$order['order_sn'] = $orders_obj[$o]->getVar('order_sn');
			$order['payable_price'] = shop_price_format($orders_obj[$o]->getPayablePrice());
			$order['created'] = formatTimestamp($orders_obj[$o]->getVar('created'), 'Y-m-d H:i');
			$order['pay_status'] = $orders_obj[$o]->getPayStatus();
			$order['ship_status'] = $orders_obj[$o]->getShipStatus();
			$order['order_status'] = $orders_obj[$o]->getUserOrderStatus();
			$xoopsTpl->append('orders', $order);
		}
		
		$xoopsTpl->assign('xoops_pagetitle',  _SHOOPS_MY_ORDER_LOG);
		$xoopsTpl->display(SHOP_TPL_PATH.'/shop_user_order_list.html');
		
		include SHOP_PATH.'/footer.php';
		break;
		
	// 訂單詳細資訊
	case 'order_detail':
	case 'order_print':
		// RB 2009-02-16 由 sn 讀取資料
		$order_sn = isset($_GET['sn'])  ? $_GET['sn'] : 0;
		$order_obj = $order_handler->get($order_sn, 'order_sn');
		
		if (!is_object($order_obj)) {
			redirect_header('user.php', 3, _SHOOPS_ERROR_SN);
			exit();
		}
		
		$order_user = $order_obj->getVar('order_user');
		if ( $uid != $order_user) {
			// 非法操作，試圖檢視他人訂單內容
			header('Location: user.php');
			exit();
		}
		
		$order_id = $order_obj->getVar('id');
		
		if ($op == 'order_print') {
			include_once XOOPS_ROOT_PATH.'/class/template.php';
			$xoopsTpl = new XoopsTpl();
			$xoopsTpl->assign('config', $shopConfig);
			
			$load_handler = shop_rewrite_handler();
			$load_handler->loadCss('http://www.1cm.tw/demo/themes/shoops/style.css');
			$load_handler->loadCss('rb/style.css');
			$load_handler->loadCss(SHOP_URL.'/style.css');
			$load_handler->rewrite();
		} else {
			include SHOP_PATH.'/header.php';
		}
		
		$order['id']             = $order_id;
		$order['order_sn']       = $order_sn;
		$order['order_user']     = $order_user;
		$order['order_status']   = $order_obj->getUserOrderStatus(); // 訂單狀態
		$order['pay_status']     = $order_obj->getPayStatus();       // 訂單狀態
		$order['ship_status']    = $order_obj->getShipStatus();      // 訂單狀態
		$order['pay_fare']       = shop_price_format($order_obj->getVar('pay_fare'));
		$order['ship_fare']      = shop_price_format($order_obj->getVar('ship_fare'));
		$order['payable_price']  = shop_price_format($order_obj->getPayablePrice()); // 應付金額
		$order['point_cost']     = shop_price_format($order_obj->getVar('point_cost'));
		$order['point_use']      = $order_obj->getVar('point_use');
		$order['point_use']      = $order_obj->getVar('point_use');  // 使用點數
		$order['point_get']      = $order_obj->getCountPoint();      // 取得點數
		// 折扣金額
		$discount_price = $order_obj->getCountDiscount();
		$discount_price_format = shop_price_format($discount_price);
		if ($discount_price > 0) $discount_price_format = '-'.$discount_price_format;
		$order['discount_price'] = $discount_price_format;
		
		// 配送方式
		$shipping_handler  = shop_plugin_handler('shipping', $order_obj->getVar('ship_mode'));
		$shipping_object   = $shipping_handler->getObject();
		$order['shipping'] = $shipping_object->getVar('name');
		
		// 付款方式
		$payment_handler  = shop_plugin_handler('payment', $order_obj->getVar('pay_mode'));
		$payment_object   = $payment_handler->getObject();
		$order['payment'] = $payment_object->getVar('name');
		if ($order_obj->getVar('pay_status') != 1) {
			$order['payment_code'] = $payment_handler->getForm($order_sn);
		} else {
			$order['payment_code'] = false;
		}
		
		// 收件人
		$order['ship_name'] = $order_obj->getVar('ship_name');
		$order['ship_email'] = $order_obj->getVar('ship_email');
		$order['ship_phone'] = $order_obj->getVar('ship_phone');
		$order['ship_mobile'] = $order_obj->getVar('ship_mobile');
		$order['ship_address'] = $order_obj->getVar('ship_address');
		$order['note'] = $order_obj->getVar('note');
		// 發票
		$order['invoice_type'] = $order_obj->getVar('invoice_type');
		$order['invoice_title'] = $order_obj->getVar('invoice_title');
		$order['tax_id'] = $order_obj->getVar('tax_id');
		$xoopsTpl->assign('order', $order);
		
		// 訂購清單
		$subtotal = 0;
		
		/**
		* 一般商品
		*/
		$order_item_handler =& xoops_getmodulehandler('orderitem');
		$where = array();
		$where[] = 'order_id = '.$order_id;
		$where[] = 'discount_id = 0';
		$order_item_obj = $order_item_handler->getObjects($where);
		foreach (array_keys($order_item_obj) as $i) {
			$order_item = array();
			$order_item['title']       = $order_item_obj[$i]->getVar('title');
			$order_item['style']       = $order_item_obj[$i]->getVar('style');
			$order_item['sn']          = $order_item_obj[$i]->getVar('sn');
			$order_item['price']       = shop_price_format($order_item_obj[$i]->getVar('price'));
			$order_item['qty']         = $order_item_obj[$i]->getVar('qty');
			$order_item['point']       = $order_item_obj[$i]->getVar('point');
			$count_price = $order_item_obj[$i]->getCountPrice();
			$subtotal = $subtotal + $count_price;
			$order_item['count_price'] = shop_price_format($count_price);
			$xoopsTpl->append('order_item', $order_item);
		}
		
		/**
		* 優惠品 (額外加購)
		*/
		$where = array();
		$where[] = 'order_id = '.$order_id;
		$where[] = 'goods_id > 0';
		$where[] = 'discount_id > 0';
		$where[] = 'price > 0';
		$addon_obj =& $order_item_handler->where($where)->getObjects();
		foreach (array_keys($addon_obj) as $a) {
			$addon = array();
			$addon['id']      = $addon_obj[$a]->getVar('id');
			$addon['title']   = $addon_obj[$a]->getVar('title');
			$addon['style']   = $addon_obj[$a]->getVar('style');
			$addon['sn']      = $addon_obj[$a]->getVar('sn');
			$addon['price']   = shop_price_format($addon_obj[$a]->getVar('price'));
			$addon['point']   = $addon_obj[$a]->getVar('point');
			$addon['qty']     = $addon_obj[$a]->getVar('qty');
			$addon['cost']    = $addon_obj[$a]->getVar('cost');
			$addon_count_price = $addon_obj[$a]->getCountPrice();
			$subtotal = $subtotal + $addon_count_price;
			$addon['count_price']  = shop_price_format($addon_count_price);
			$xoopsTpl->append('addons', $addon);
		}
		
		// 小計金額
		$xoopsTpl->assign('subtotal', shop_price_format($subtotal));
		
		/**
		* 贈品 (免費贈送)
		*/
		$where = array();
		$where[] = 'order_id = '.$order_id;
		$where[] = 'goods_id > 0 ';
		$where[] = 'item_id > 0 ';
		$where[] = 'discount_id > 0 ';
		$where[] = 'price = 0';
		$gift_count = $order_item_handler->getCount($where);
		$xoopsTpl->assign('gift_count', $gift_count);
		$gift_obj = $order_item_handler->getObjects($where);
		foreach (array_keys($gift_obj) as $g) {
			$gift = array();
			$gift['id']          = $gift_obj[$g]->getVar('id');
			$gift['title']       = $gift_obj[$g]->getVar('title');
			$gift['style']       = $gift_obj[$g]->getVar('style');
			$gift['sn']          = $gift_obj[$g]->getVar('sn');
			$gift['price']       = shop_price_format($gift_obj[$g]->getVar('price'));
			$gift['qty']         = $gift_obj[$g]->getVar('qty');
			$gift['point']       = $gift_obj[$g]->getVar('point');
			$gift['cost']        = $gift_obj[$g]->getVar('cost');
			$gift_count_price    = $gift_obj[$g]->getCountPrice();
			$gift['count_price'] = shop_price_format($gift_count_price);
			$xoopsTpl->append('gifts', $gift);
		}
		
		/**
		* 優惠活動
		*/
		$where = array();
		$where[] = 'order_id = '.$order_id;
		$where[] = 'goods_id = 0 ';
		$where[] = 'item_id = 0 ';
		$where[] = 'discount_id > 0 ';
		$favorable_obj = $order_item_handler->getObjects($where);
		foreach (array_keys($favorable_obj) as $f) {
			$favorable = array();
			$favorable['id']          = $favorable_obj[$f]->getVar('id');
			$favorable['title']       = $favorable_obj[$f]->getVar('title');
			$favorable['point']       = $favorable_obj[$f]->getVar('point');
			$favorable['cost']        = $favorable_obj[$f]->getVar('cost');
			$favorable['count_price'] = '-'.shop_price_format($favorable_obj[$f]->getCountPrice());
			$xoopsTpl->append('favorables', $favorable);
		}
		
		$xoopsTpl->assign('xoops_pagetitle',  _SHOOPS_ORDER_DETAIL);
		if ($op == 'order_print') {
			$xoopsTpl->display(SHOP_TPL_PATH.'/shop_user_order_print.html');
		} else {
			//$xoopsTpl->assign('xoops_showlblock', 0);
			$xoopsTpl->assign('xoops_showcblock', 0);
			$xoopsTpl->assign('xoops_showrblock', 0);
			$xoopsTpl->display(SHOP_TPL_PATH.'/shop_user_order_detail.html');
			include SHOP_PATH.'/footer.php';
		}
		break;	
		
	// 取消訂單
	case 'order_cnacel':
		$order_sn = isset($_GET['sn'])  ? $_GET['sn'] : 0;
		
		// 檢查訂單
		$order_obj = $order_handler->get($order_sn, 'order_sn');
		if (!is_object($order_obj)) {
			header('Location: user.php');
			exit();
		}
		// 比對身分
		$order_user = $order_obj->getVar('order_user');
		if ( $uid != $order_user) {
			header('Location: user.php');
			exit();
		}
		$order_obj->setVar('order_status', 3);
		if (!$order_handler->insert($order_obj)) {
			redirect_header('user.php?op=order_list', 3, _SHOOPS_CANCEL_ORDER_NG);
		} else {
			redirect_header('user.php?op=order_list', 3, _SHOOPS_CANCEL_ORDER_OK);
		}
		break;
		
	case 'service_list':
		include SHOP_PATH.'/header.php';
		
		$service_handler =& xoops_getmodulehandler('service');
		
		$where = array();
		$where[] = 'pid = 0';
		$where[] = 'uid = '.$uid;
		$question_obj = $service_handler->getObjects($where);
		foreach (array_keys($question_obj) as $q) {
			$question = array();
			$question['id'] = $question_obj[$q]->getVar('id');
			$question['order_id'] = $question_obj[$q]->getVar('order_id');
			$order_obj = $order_handler->get($question['order_id']);
			$question['order_sn'] = $order_obj->getVar('order_sn');
			$question['title'] = $question_obj[$q]->getVar('title');
			$question['created'] = formatTimestamp($question_obj[$q]->getVar('created'), 'm');
			$question['message'] = nl2br($question_obj[$q]->getVar('message', 'n'));
			// 所有回覆
			$where = array();
			$where[] = 'pid = '.$question_obj[$q]->getVar('id');
			$answer_obj = $service_handler->getObjects($where, 'created ASC');
			foreach (array_keys($answer_obj) as $a) {
				$answer = array();
				$question['answer'][$a]['title'] = $answer_obj[$a]->getVar('title');
				$question['answer'][$a]['created'] = formatTimestamp($answer_obj[$a]->getVar('created'), 'm');
				$question['answer'][$a]['message'] = nl2br($answer_obj[$a]->getVar('message', 'n'));
			}
			$xoopsTpl->append('service_log', $question);
		}

		$xoopsTpl->assign('xoops_pagetitle',  _SHOOPS_MY_SERVICE_LOG);
		//$xoopsTpl->assign('xoops_showlblock', 0);
		$xoopsTpl->assign('xoops_showcblock', 0);
		$xoopsTpl->assign('xoops_showrblock', 0);
		$xoopsTpl->display(SHOP_TPL_PATH.'/shop_user_service_list.html');
		
		include SHOP_PATH.'/footer.php';
		break;
		
	case 'service_post':
		
		if (!$_POST) {
			include SHOP_PATH.'/header.php';
			$xoopsTpl->display(SHOP_TPL_PATH.'/shop_user_service_form.html');
			include SHOP_PATH.'/footer.php';
		} else {
			$sn      = isset($_POST['sn'])       ? $_POST['sn'] : 0;
			$title   = isset($_POST['title'])    ? $_POST['title'] : '';
			$pay     = isset($_POST['pay'])      ? intval($_POST['pay']) : 0;
			$message = isset($_POST['message'])  ? $_POST['message'] : '';
			
			// RB 2009-02-16 追加驗證
			if (empty($sn)) {
				redirect_header('user.php', 3, _SHOOPS_ERROR_SN);
				exit();
			}
			if (empty($title)) {
				redirect_header('user.php', 3, _SHOOPS_NEED_SEL_TITLE);
				exit();
			}
			
			if ($pay) {
				foreach($_POST['pay_opt'] as $key => $val) {
					$key = 'pay_'.$key;
					${$key} = isset($val) ? $val : '';
				}
				if (!$pay_time || !$pay_money || !$pay_check) {
					redirect_header('user.php?op=service_post&sn='.$sn.'&pay=1', 3, _SHOOPS_NEED_PAYMENT_DATD);
					exit();
				}
				$message  = "匯款時間: $pay_time\n\r";
				$message .= "匯款金額: $pay_money\n\r";
				$message .= "帳戶末五碼: $pay_check\n\r";
			}
			
			if (empty($message)) {
				redirect_header('user.php?op=service_post&sn='.$sn, 3, _SHOOPS_NEED_SERVICE_MSG);
				exit();
			}
			
			// 取的訂單 id
			$sql = 'SELECT id FROM '.$xoopsDB->prefix('shop_order').' WHERE order_sn = '.$xoopsDB->quoteString($sn);
			$result = $xoopsDB->query($sql);
			list($order_id) = $xoopsDB->fetchRow($result);
			// 檢查次訂單是否存在

			if (!$order_handler->isExists($order_id)) {
				redirect_header('user.php', 3, _SHOOPS_ERROR_SN);
				exit();
			}
			// RB 2009-02-16 改由 sn 取代 id 讀取資料 End
			
			// 驗證終了 資料寫入就緒
			$service_handler =& xoops_getmodulehandler('service');
			$service_obj = $service_handler->create();
			$service_obj->setVar('pid', 0);
			$service_obj->setVar('order_id', $order_id);
			$service_obj->setVar('type', 'U');
			$service_obj->setVar('title', $title);
			$service_obj->setVar('message', $message);
			$service_obj->setVar('uid', $uid);
			$service_obj->setVar('created', time());
			if (!$service_handler->insert($service_obj)) {
				redirect_header('user.php?op=order_list', 3, _SHOOPS_POST_SERVICE_MSG_NG);
			} else {
				redirect_header('user.php?op=service_list', 3, _SHOOPS_POST_SERVICE_MSG_OK);
			}
		}
		break;
		
	// 更新個人資料
	case 'profile_update':
		if ($uid) {
			$country    = isset($_POST['country'])    ? $myts->stripSlashesGPC($_POST['country']) : '';
			$city       = isset($_POST['city'])       ? $myts->stripSlashesGPC($_POST['city']) : '';
			$district   = isset($_POST['district'])   ? $myts->stripSlashesGPC($_POST['district']) : '';
			$location   = isset($_POST['location'])   ? $myts->stripSlashesGPC($_POST['location']) : '';
			$address    = isset($_POST['address'])    ? $myts->stripSlashesGPC($_POST['address']) : '';
			$phone_area = isset($_POST['phone_area']) ? trim($myts->stripSlashesGPC($_POST['phone_area'])) : '';
			$phone      = isset($_POST['phone'])      ? trim($myts->stripSlashesGPC($_POST['phone'])) : '';
			$mobile     = isset($_POST['mobile'])     ? trim($myts->stripSlashesGPC($_POST['mobile'])) : '';
			$email      = isset($_POST['email'])     ? trim($myts->stripSlashesGPC($_POST['email'])) : '';
			$vocation   = isset($_POST['vocation'])   ? intval($_POST['vocation']) : '';
			$subscribe  = isset($_POST['subscribe'])  ? intval($_POST['subscribe']) : '';
			
			$member_handler =& xoops_gethandler('member');
			$edituser =& $member_handler->getUser($uid);
			$edituser->setVar('email', $email);
			$password = '';
			if (!empty($_POST['pass'])) {
				$password = trim($myts->stripSlashesGPC($_POST['pass']));
			}
			if ($password != '') {
				$vpass = '';
				if (!empty($_POST['vpass'])) {
					$vpass = $myts->stripSlashesGPC(trim($_POST['vpass']));
				}
				if ($password == $vpass) {
					$edituser->setVar('pass', md5($password));
				}
			}
			if (!$member_handler->insertUser($edituser)) {
				redirect_header('user.php?op=profile', 3, _SHOOPS_INSERT_MEMBER_NG);
				exit();
			}

			$profile_obj = $profile_handler->get($uid);
			$profile_obj->setVar('uid',        $uid);
			$profile_obj->setVar('country',    $country);
			if ($country == 'Taiwan') {
				$profile_obj->setVar('location',   $city.' '.$district);
			} else {
				$profile_obj->setVar('location',   $location);
			}
			$profile_obj->setVar('address',    $address);
			$profile_obj->setVar('phone',      $phone_area.'-'.$phone);
			$profile_obj->setVar('mobile',     $mobile);
			$profile_obj->setVar('vocation',   $vocation);
			$profile_obj->setVar('subscribe',  $subscribe);
			if (!$profile_handler->insert($profile_obj)) {
				redirect_header('user.php?op=profile', 3, _SHOOPS_INSERT_CUSTOMER_NG);
			} else {
				redirect_header('user.php?op=profile', 3, _SHOOPS_UPDATE_CUSTOMER_OK);
			}
		} else {
			header('Location: user.php');
		}
		break;
		
	// For Xoops old user 當有 xoops 帳號在 shop DB 中沒有個人資料時用到
	case 'profile_insert':
		//print_r($_POST);
		if ($uid) {
			foreach($_POST as $key => $val) {
				$_SESSION['register'][$key] = $val;
			}
			$last_name   = isset($_POST['last_name'])    ? $myts->stripSlashesGPC($_POST['last_name']) : '';
			$first_name  = isset($_POST['first_name'])  ? $myts->stripSlashesGPC($_POST['first_name']) : '';
			$sex         = isset($_POST['sex'])         ? trim($myts->stripSlashesGPC($_POST['sex'])) : '';
			$blood       = isset($_POST['blood'])       ? trim($myts->stripSlashesGPC($_POST['blood'])) : '';
			// sex/blood 為資料庫 ENUM 欄位 NOT NULL，此表單並未收集這兩個值，
			// 若留空字串在嚴格模式下會被 MySQL 拒絕 (Data truncated for column 'sex')，
			// 因此沒有值時改採用資料庫預設值。
			if ($sex == '')   $sex   = 'M';
			if ($blood == '') $blood = 'A';
			
			$birth_year  = isset($_POST['birth_year'])  ? trim($myts->stripSlashesGPC($_POST['birth_year'])) : '';
			$birth_month = isset($_POST['birth_month']) ? trim($myts->stripSlashesGPC($_POST['birth_month'])) : '';
			$birth_day   = isset($_POST['birth_day'])   ? trim($myts->stripSlashesGPC($_POST['birth_day'])) : '';
		
			$country     = isset($_POST['country'])     ? $myts->stripSlashesGPC($_POST['country']) : '';
			$city        = isset($_POST['city'])        ? $myts->stripSlashesGPC($_POST['city']) : '';
			$district    = isset($_POST['district'])    ? $myts->stripSlashesGPC($_POST['district']) : '';
			$location    = isset($_POST['location'])    ? $myts->stripSlashesGPC($_POST['location']) : '';
			$address     = isset($_POST['address'])     ? $myts->stripSlashesGPC($_POST['address']) : '';
			
			$phone_area  = isset($_POST['phone_area'])  ? trim($myts->stripSlashesGPC($_POST['phone_area'])) : '';
			$phone       = isset($_POST['phone'])       ? trim($myts->stripSlashesGPC($_POST['phone'])) : '';
			$mobile      = isset($_POST['mobile'])      ? trim($myts->stripSlashesGPC($_POST['mobile'])) : '';
			$vocation    = isset($_POST['vocation'])    ? intval($_POST['vocation']) : '';
			$subscribe   = isset($_POST['subscribe'])   ? intval($_POST['subscribe']) : '';
			
			$member_handler =& xoops_gethandler('member');
			$edituser =& $member_handler->getUser($uid);
			$edituser->setVar('email', $email);
			$password = trim($myts->stripSlashesGPC($_POST['pass']));
			if ($password != '') {
				$edituser->setVar('pass', md5($password));
			}
			if (!$member_handler->insertUser($edituser)) {
				redirect_header('user.php?op=profile', 3, _SHOOPS_INSERT_MEMBER_NG);
				exit();
			}
			
			$profile_obj = $profile_handler->create();
			$profile_obj->setVar('uid',        $uid);
			$profile_obj->setVar('last_name',  $last_name);
			$profile_obj->setVar('first_name', $first_name);
			$profile_obj->setVar('sex',        $sex);
			$profile_obj->setVar('blood',      $blood);
			$profile_obj->setVar('birthday',   $birth_year.$birth_month.$birth_day);
			$profile_obj->setVar('vocation',   $vocation);
			$profile_obj->setVar('country',    $country);
			if ($country == 'Taiwan') {
				$profile_obj->setVar('location',   $city.' '.$district);
			} else {
				$profile_obj->setVar('location',   $location);
			}
			$profile_obj->setVar('address',    $address);
			$profile_obj->setVar('phone',      $phone_area.'-'.$phone);
			$profile_obj->setVar('mobile',     $mobile);
			$profile_obj->setVar('subscribe',  $subscribe);
			$profile_obj->setVar('point',      $shopConfig['point_for_register']);
			if (!$profile_handler->insert($profile_obj)) {
				redirect_header('user.php?op=profile', 3, _SHOOPS_INSERT_CUSTOMER_NG);
			} else {
				unset($_SESSION['register']);
				redirect_header('user.php?op=profile', 3, _SHOOPS_UPDATE_CUSTOMER_OK);
			}
		} else {
			header('Location: user.php');
		}
		break;
		
	case 'avatar_upload':
		break;
}
?>