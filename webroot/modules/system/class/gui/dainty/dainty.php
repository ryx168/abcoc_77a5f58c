<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

xoops_load('gui', 'system');

/**
 * Xoops GUI Dainty Class
 *
 * @copyright   The XOOPS project http://sf.net/projects/xoops/
 * @license     7-ELEVEN License - Always Open
 * @package     System
 * @usbpackage  GUI
 * @since       2.3.3
 * @author      RB Lin
 * @emaul       xtheme@gmail.com
 * @web         http://xoops.tnc.edu.tw/
 * @url         http://code.google.com/p/xoops-rb-framework/
 * @version     0.3
 * @update      2010-04-09 
 */

class XoopsGuiDainty extends /* implements */ XoopsSystemGui
{
	protected $option = array();
	
	function __set($k, $c) {
		$this->option[$k]= $c;
	}
	
	function __get($k) {
		return $this->option[$k];
	}
	
	function __construct() {
		
		global $xoopsModule;
		
		include_once dirname(__FILE__).'/include/functions.php';
		$is_index = ( !is_object($xoopsModule) && !isset($_GET['xoopsorgnews']) ) ? true : false;
		
		$folderName = strtolower(str_replace('XoopsGui', '', get_class()));
		
		$option = array(
			'folderName'     => $folderName,
			'theme_path'     => XOOPS_ROOT_PATH.'/modules/system/class/gui/'.$folderName,
			'theme_url'      => XOOPS_URL.'/modules/system/class/gui/'.$folderName,
			'is_index'       => $is_index,
			'framework_url'  => XOOPS_URL.'/Frameworks',
			'called_modules' => array(),
			'bind_module'    => array(),
			'redirect_msg'   => '',
			'redirect_time'  => '',
			'gui_conf'       => array(),
			'ero_msgs'       => array()
		);
		
		foreach ($option as $k => $c ) {
			$this->option[$k] = $c;
		}
		
		// load gui configs
		$data = array();
		require_once dirname(__FILE__).'/include/data_db.php';
		foreach ($data as $k => $v ) {
			$this->option['gui_conf'][$k] = gui_decode($v);
		}
		
		// save redirect cookie info to smarty
		if(!empty($_COOKIE['redirect_message'])) {
			$this->redirect_msg = $_COOKIE['redirect_message'];
			$this->redirect_time = $_COOKIE['redirect_time'];
			setcookie('redirect_message', '', time()-1); 
			setcookie('redirect_time', '', time()-1); 
		}
		
		// show / hide panel (toolbar)
		if (!isset($_COOKIE['hide_panel'])) setcookie('hide_panel', 0, time() + 3600 * 24, '/');
    }

    function XoopsGuiDainty() {
        $this->__construct();
    }

    // validate theme can use or not
	function validate() {
        $ret = ( version_compare(PHP_VERSION, '5.2.0', '<') ) ? false : true; // check PHP5 && JSON
		
		if ($ret)
		$ret = ( substr(XOOPS_VERSION, 6, 3) <> 2.3 ) ? false : true;
		
		return $ret;
    }
	
	function header() {
		global $xoopsConfig, $xoopsUser, $xoopsModule, $xoopsTpl, $xoopsLogger;
		
		ob_start();
		
		xoops_loadLanguage('modinfo', 'system');
		xoops_loadLanguage('admin',   'system');
		xoops_loadLanguage('cpanel',  'system');
		
		if (!@include_once $this->theme_path.'/language/'.$xoopsConfig['language'].'/admin.php') {
			include_once $this->theme_path.'/language/english/admin.php';
		}
		
		$xoopsLogger->startTime('ACP init');
		
		if (!headers_sent()) {
            header('Content-Type:text/html; charset='._CHARSET);
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
        }
		
		include_once XOOPS_ROOT_PATH . '/class/template.php';
		$xoopsTpl = new XoopsTpl();
		
		// xos_opal_AdminThemeFactory
		$xoopsTpl->assign(array(
			'theme_path'  => $this->theme_path,
			'theme_tpl'   => $this->theme_path.'/xotpl',
			'theme_url'   => $this->theme_url,
			'theme_img'   => $this->theme_url.'/img',
            'theme_icons' => $this->theme_url.'/icons',
            'theme_css'   => $this->theme_url.'/css',
            'theme_js'    => $this->theme_url.'/js',
            'theme_lang'  => $this->theme_url.'/language',
		));
		
		 // Meta tags
        $config_handler =& xoops_gethandler('config');
        $criteria = new CriteriaCompo(new Criteria('conf_modid', 0));
        $criteria->add(new Criteria('conf_catid', XOOPS_CONF_METAFOOTER));
        $config = $config_handler->getConfigs($criteria, true);
        foreach (array_keys($config) as $i) {
            $name = $config[$i]->getVar('conf_name', 'n');
            $value = $config[$i]->getVar('conf_value', 'n');
			// prefix each tag with 'xoops_'
			$xoopsTpl->assign("xoops_$name", $value);
        }
		
		// theme smarty vars
		$xoopsTpl->assign(array(
			'xoops_theme'      => $xoopsConfig['theme_set'],
			'xoops_imageurl'   => XOOPS_THEME_URL.'/'.$xoopsConfig['theme_set'].'/',
			'xoops_themecss'   => xoops_getcss($xoopsConfig['theme_set']),
			'xoops_requesturi' => htmlspecialchars($GLOBALS['xoopsRequestUri'], ENT_QUOTES),
			'xoops_sitename'   => htmlspecialchars($xoopsConfig['sitename'], ENT_QUOTES),
			'xoops_slogan'     => htmlspecialchars($xoopsConfig['slogan'], ENT_QUOTES),
			'xoops_dirname'    => @$xoopsModule ? $xoopsModule->getVar('dirname') : 'system',
			'xoops_modulename' => @$xoopsModule ? $xoopsModule->getVar('name') : '',
			'xoops_banner'     => $xoopsConfig['banners'] ? xoops_getbanner() : '&nbsp;',
			'xoops_pagetitle'  => isset($xoopsModule) && is_object($xoopsModule) ? $xoopsModule->getVar('name') : htmlspecialchars( $xoopsConfig['slogan'], ENT_QUOTES )
		));
		
		$xoopsTpl->assign(array(
			'gui'         => $this->gui_conf, // GUI config
			'welcome'     => sprintf(_AD_WELCOME, $xoopsUser->getVar('uname')),
			'is_index'    => $this->is_index,
			'prem_menu'   => $this->prefMenu(),
			'footer_menu' => $this->systemMenu()
		));
		
		if (true === $this->is_index):
		$xoopsTpl->assign(array(
			'xoops_pagetitle' => _AD_ACP_HOME,
			'sysinfo' => $this->systemInfo(),
			'modules' => $this->modulesInfo()
		));
		endif;
		
		$xoopsLogger->startTime('Rewrite init');
		$this->headerRewrite();
		$xoopsLogger->stopTime('Rewrite init');
		
		/*
		 * ### navigation menu start ###
		 */
		$module_handler =& xoops_gethandler('module');
		$module_perm_handler =& xoops_gethandler('groupperm');
		$nav_menu = array();
		
		/*
		 * get my custom navigation links set  'include/custom_menu.php'
		 */
		include dirname(__FILE__).'/include/custom_menu.php';
		foreach ($custom as $key => $type) {
			$nav_menu[$key]['title'] = $type['title'];
			$nav_menu[$key]['link'] = $type['link'];
			foreach ($type['list'] as $kk => $dir) {
				$mod_obj = $module_handler->getByDirname($dir);
				if (!$mod_obj || !$mod_obj->getVar('isactive')) continue;
				
				$is_admin = $module_perm_handler->checkRight('module_admin', $mod_obj->getVar('mid'), $xoopsUser->getGroups());
				if (!$is_admin) continue;
				
				$nav_menu[$key]['list'][$kk] = $this->generateNav($mod_obj);
			}
		}
		
		/*
		 * get all install and active modules, but preclude custom items 'include/custom_menu.php'
		 */
		++$key;
		$nav_menu[$key]['title'] = _AD_MODULES;
		$nav_menu[$key]['link'] = _AD_LINK_NULL;
		$criteria = new CriteriaCompo();
		$criteria->add(new Criteria('hasadmin', 1));
		$criteria->add(new Criteria('isactive', 1));
		$criteria->setSort('mid');
		$module_obj = $module_handler->getObjects($criteria);
		foreach (array_keys($module_obj) as $m) {
			$mod_obj = $module_obj[$m];
			if (!$mod_obj || !$mod_obj->getVar('isactive')) continue;
			
			// preclude called modules
			if (in_array($module_obj[$m]->getVar('dirname', 'n'), $this->called_modules)) continue;
			
			$is_admin = $module_perm_handler->checkRight('module_admin', $mod_obj->getVar('mid'), $xoopsUser->getGroups());
			if (!$is_admin) continue;
			
			$nav_menu[$key]['list'][$m] = $this->generateNav($mod_obj);
		}
		
		/*
		 * get links 'include/custom_links.php'
		 */
		include dirname(__FILE__).'/include/custom_links.php';
		
		++$key;
		$nav_menu[$key]['title'] = _AD_XOOPS_LINKS;
		$nav_menu[$key]['link'] = _AD_LINK_NULL;
		foreach ($somelinks as $link) {
			$nav_menu[$key]['list'][] = array(
				'title' => $link['title'],
				'link'  => $link['link']
			);
		}
		
		++$key;
		$nav_menu[$key]['title'] = _AD_CREDIT;
		$nav_menu[$key]['link'] = _AD_LINK_NULL;
		foreach ($credit as $link) {
			$nav_menu[$key]['list'][] = array(
				'title' => $link['title'],
				'link'  => $link['link']
			);
		}
		/*
		 * ### navigation menu end ###
		 */
		
		/*
		 * assign to template
		 */
		$xoopsTpl->assign('nav_menu',    $nav_menu);
		$xoopsTpl->assign('module_menu', $this->bind_module);
		
		/*
		 * system (quick) configs
		 * enable some system configs for our customers without System Admin rights
		 * support conf_formtype : text / yesno / select
		 */
		$allow_config = array('debug_mode');
		$xoopsTpl->assign('sys_conf', $this->confMenu($allow_config));
		
		//echo '$_COOKIE[\'hide_panel\'] = '.$_COOKIE['hide_panel'];
		
		/*
		 * custom error message
		 */
		foreach ($this->ero_msgs as $chijo) {
			xoops_error($chijo);
			echo '<br />';
		}
	}
	
	function footer() {
		global $xoopsTpl, $xoopsLogger;
		
		$xoops_contents = ob_get_clean();
		$xoopsTpl->assign_by_ref('xoops_contents', $xoops_contents);
		
		$xoopsLogger->activated = true;
		$xoopsTpl->assign('xoops_dumptime', sprintf(_AD_DUMP_TIME, number_format($xoopsLogger->dumpTime(), 5, '.', '')));
		
		$xoopsTpl->display($this->theme_path.'/theme.html');
		
        $xoopsLogger->stopTime('ACP init');
		ob_end_flush();
    }
	
	// ######## protected function ########
	
	protected function setError($msg) {
		$msg = (is_array($msg)) ? $msg : (array)$msg;
		$this->ero_msgs = array_merge($this->ero_msgs, $msg);
	}
	
	protected function headerRewrite() {
		$extra_js  = "var \$guiurl = '".$this->theme_url."';\n";
		// RbLoad's checkUrl() silently drops any loadJs()/loadCss() call whose
		// fopen() self-check fails, with no error surfaced anywhere - in practice
		// css/style.css and js/gui.panel.js (the admin theme's own layout-critical
		// files, queued a few lines below via the RbLoad chain) were consistently
		// missing from the rendered page even though both files exist and load
		// fine on their own. Write them out directly with plain document.write()
		// instead - this text is already proven to render and execute correctly
		// (it becomes this exact <script> block below), so it carries no risk of
		// depending on an admin-context global (e.g. $xoTheme) that may not
		// actually be initialized here.
		$extra_js .= "document.write('<link rel=\"stylesheet\" type=\"text/css\" href=\"'+\$guiurl+'/css/style.css\" />');\n";
		$extra_js .= "document.write('<script type=\"text/javascript\" src=\"'+\$guiurl+'/js/gui.panel.js\"><\\/script>');\n";
		$extra_js .= "var \$lang_process = '"._AD_PROCESS."';\n";
		$extra_js .= "var \$feed_url = '"._AD_FEED_URL."';\n";
		$extra_js .= "$(document).ready(function(){\n";
		$extra_js .= "$.getScript(\$guiurl + '/js/gui.main.js');\n";
		$extra_js .= "$.getScript(\$guiurl + '/js/gui.feed.js');\n";
		if ($this->is_index):
		$extra_js .= "$.getScript(\$guiurl + '/js/gui.config.js');\n";
		endif;
		if ($this->redirect_msg != ''):
		$extra_js .= "showMessage('".$this->redirect_msg."', ".$this->redirect_time.");\n";
		endif;
		$extra_js .= "});\n";
		// use jGrowl show redirect message
		$extra_js .= "function showMessage(str, msec) {\n";
		$extra_js .= "if (!parseInt(msec)) msec = 2000;\n";
		$extra_js .= "if (str != '') $.jGrowl(str, { life:msec, position:'center', speed:'slow' });\n";
		$extra_js .= "}";
		
		// header rewrite handler, default use RBF class.load.php
		$hander_path = XOOPS_ROOT_PATH.'/Frameworks/rb/class.load.php';
		$hander_exists = ( !file_exists($hander_path) ) ? false : true;
		if (!$hander_exists):
			global $xoTheme; // Project A require 'Jackie Chan's nose'
			// google.load()/jsapi was shut down by Google years ago, and was loaded
			// over plain http:// on an https page anyway (blocked as mixed content
			// even when it did work) - this silently prevented jQuery from ever
			// loading, so none of the admin panel's layout/styling JS ever ran and
			// the page rendered as an unstyled list. Load real jQuery directly from
			// a working CDN instead, matching the front-end theme's own approach.
			$xoTheme->addScript('https://code.jquery.com/jquery-1.4.2.min.js');
			$xoTheme->addScript('https://code.jquery.com/ui/1.7.2/jquery-ui.min.js');
			$xoTheme->addStylesheet($this->framework_url.'/rb/style.css');
			$xoTheme->addScript(XOOPS_URL.'/include/xoops.js');
			$xoTheme->addScript($this->framework_url.'/jquery/jquery.livequery.js');
			$xoTheme->addScript($this->framework_url.'/jquery/jquery.jfeed.pack.js');
			//$xoTheme->addScript($this->framework_url.'/jquery/jquery.960grid.js');
			$xoTheme->addScript($this->framework_url.'/jquery/jgrowl/jquery.jgrowl.js');
			$xoTheme->addStylesheet($this->framework_url.'/jquery/jgrowl/jquery.jgrowl.css');
			$xoTheme->addScript($this->theme_url.'/js/gui.panel.js');
			$xoTheme->addScript('', null, $extra_js);
			$xoTheme->addStylesheet($this->theme_url.'/css/style.css');
			//$ero_msg = sprintf(_AD_WARNING_RBF, $hander_path);
			//$this->setError($ero_msg);
		else:
			include_once $hander_path; // Project B
			$loader = new RbLoad();
			$loader->setLibrary('jquery', '1.4.2')->setLibrary('jqueryui', '1.7.2');
			$loader->loadJs(XOOPS_URL.'/include/xoops.js')
			->loadCss('rb/style.css')
			->loadJs('jquery/jquery.livequery.js')
			->loadJs('jquery/jquery.jfeed.pack.js')
			//->loadJs('jquery/jquery.960grid.js')
			->loadPlugin('jquery/jgrowl')
			->setBaseUrl($this->theme_url)
			->loadCss('css/style.css')
			->loadJs('js/gui.panel.js')
			->writeJs($extra_js)->rewrite();
		endif;
    }
	
	protected function generateNav($mod_obj) {
		global $xoopsModule;
		
		$mod_dir = $mod_obj->getVar('dirname', 'n');
		$mod_mid = $mod_obj->getVar('mid');
		$this->called_modules = array_merge($this->called_modules, (array)$mod_dir);
		
		$mod_info = $mod_obj->getInfo();
		if (!empty($mod_info['adminindex'])) {
			$mod_link = XOOPS_URL.'/modules/'.$mod_dir.'/'.$mod_info['adminindex'];
		} else {
			$mod_link = XOOPS_URL.'/modules/system/admin.php?fct=preferences&amp;op=showmod&amp;mod='.$mod_mid;
		}
		
		$arr = array(
			'title' => $mod_obj->name(),
			'link'  => $mod_link,
			'icon'  => '', // $mod_info['adminicon']
			'submenu' => false
		);
		// has admin menu
		if (!empty($mod_info['adminmenu'])) {
			$mod_submenu = $mod_obj->getAdminMenu();
			if(isset($mod_submenu) && is_array($mod_submenu)) {
				foreach ($mod_submenu as $submenu) {
					$arr['submenu'][] = array(
						'title' => $submenu['title'],
						'link' => XOOPS_URL.'/modules/'.$mod_dir.'/'.$submenu['link'],
						'icon' => ''
					);
				}
			}
		}
		// has config
		if ($mod_obj->getVar('hasconfig')) {
			$arr['submenu'][] = array(
				'title' => _AD_MOD_SETTING,
				'link' => XOOPS_URL.'/modules/system/admin.php?fct=preferences&amp;op=showmod&amp;mod='.$mod_mid,
			//	'icon' => $this->theme_url.'/images/cog.png'
			);
		}
		// has front
		if ($mod_obj->getVar('hasmain')) {
			$arr['submenu'][] = array(
				'title' => _AD_MOD_FRONT,
				'link' => XOOPS_URL.'/modules/'.$mod_dir.'/',
			//	'icon' => $this->theme_url.'/images/layout.png' //IE8 �䤣��� by YAL
			);
		}
		// bind now module menu
		if (is_object($xoopsModule) && ($xoopsModule->getVar('dirname') == $mod_dir)) {
			$this->bind_module = $arr['submenu'];
		}
		unset($mod_obj);
		return $arr;
	}
	
	protected function statusIcon($status) {
		if ($status) {
		//	$str = '<img src="'.$this->theme_url.'/images/action_check.gif" alt="ON" />';
			$str = 'ON';
		} else {
		//	$str = '<img src="'.$this->theme_url.'/images/action_delete.gif" alt="OFF" />';
			$str = 'OFF';
		}
		return $str;
	}
	
	protected function isSec($val) {
		$str = $val._AD_SECOND;
		return $str;
	}
	
	protected function iniFormat($conf, $callFunc = '') {
		$value = ini_get($conf);
		if ($callFunc != '') {
			$value = $this->$callFunc($value);
		}
		return $value;
	}
	
	protected function systemInfo() {
		$i = 0;
		$sysinfo[$i]['label'] = sprintf(_MD_CPANEL_VERSION, 'XOOPS');
		$sysinfo[$i]['value'] = XOOPS_VERSION;
		$i++;
		$sysinfo[$i]['label'] = sprintf(_MD_CPANEL_VERSION, 'PHP');
		$sysinfo[$i]['value'] = PHP_VERSION;
		$i++;
		$sysinfo[$i]['label'] = sprintf(_MD_CPANEL_VERSION, 'MySQL');
		$sysinfo[$i]['value'] = mysql_get_server_info();
		$i++;
		$sysinfo[$i]['label'] = sprintf(_MD_CPANEL_VERSION, 'Server API');
		$sysinfo[$i]['value'] = PHP_SAPI;
		$i++;
		$sysinfo[$i]['label'] = sprintf(_MD_CPANEL_VERSION, 'OS');
		$sysinfo[$i]['value'] = PHP_OS;
		/*
		 * custom check functions here, use 'display title' => 'function name'
		 */
		$checkFunctions = array(
			'GD library' => 'gd_info',
			'iconv',
			'mbstring' => 'mb_strlen',
			'cURL' => 'curl_init'
		);
		foreach ($checkFunctions as $lab => $func) {
			$i++;
			$sysinfo[$i]['label'] = (!empty($lab)) ? $lab : $func;
			$sysinfo[$i]['value'] = $this->statusIcon(function_exists($func));
		}
		/*
		 * custom check configs here, use 'config name' => 'custom format mrthod'
		 */
		$checkConfiguration = array(
			'safe_mode' => 'statusIcon',
			'register_globals' => 'statusIcon',
			'magic_quotes_gpc' => 'statusIcon',
			'allow_url_fopen' => 'statusIcon',
			'allow_url_include' => 'statusIcon',
			'memory_limit' => '',
			'max_execution_time' => 'isSec',
			'max_input_time' => 'isSec',
			'post_max_size' => '',
			'upload_max_filesize' => ''
		);
		foreach ($checkConfiguration as $conf => $format) {
			$i++;
			$sysinfo[$i]['label'] = $conf;
			$sysinfo[$i]['value'] = $this->iniFormat($conf, $format);
		}
		
		return $sysinfo;
	}
	
	protected function modulesInfo() {
		global $xoopsUser;
		// Install Modules Info
		$install = array();
		$module_handler =& xoops_gethandler('module');
		$criteria = new CriteriaCompo();
		$criteria->add(new Criteria('hasadmin', 1));
		$criteria->add(new Criteria('isactive', 1));
		$criteria->setSort('mid');
		$module_obj = $module_handler->getObjects($criteria);
		$module_perm_handler =& xoops_gethandler('groupperm');
		foreach (array_keys($module_obj) as $m) {
			$module = array();
			$is_admin = $module_perm_handler->checkRight('module_admin', $module_obj[$m]->getVar('mid'), $xoopsUser->getGroups());
			if ($is_admin) {
				$module['title'] = $module_obj[$m]->name();
				$module['dir'] = $module_obj[$m]->getVar('dirname', 'n');
				$module['mid'] = $module_obj[$m]->getVar('mid');
				$info = $module_obj[$m]->getInfo();
				if ($info['image']) {
					$icon = XOOPS_URL.'/modules/'.$module['dir'].'/'.$info['image'];
				} else {
					$icon = $this->theme_url.'/images/slogo.gif';
				}
				$module['icon'] = $icon;
				if ($module_obj[$m]->getVar('hasmain')) {
					$module['front_link'] = XOOPS_URL.'/modules/'.$module['dir'].'/';
				}
				if ($module_obj[$m]->getVar('hasadmin')) {
					if (!empty($info['adminindex'])) {
						$module['backend_link'] = XOOPS_URL.'/modules/'.$module['dir'].'/'.$info['adminindex'];
					} else {
						$module['backend_link'] = XOOPS_URL.'/modules/system/admin.php?fct=preferences&amp;op=showmod&amp;mod='.$module['mid'];
					}
				}
				$install[] = $module;
				unset($info);
			}
		}
		return $install;
    }
	
	protected function systemMenu() {
		include dirname(__FILE__).'/include/system_menu.php';
		return $adminmenu;
	}
	
	protected function prefMenu() {
		global $xoopsConfig;
		$fct = 'preferences';
		if (!@include_once XOOPS_ROOT_PATH.'/modules/system/language/'.$xoopsConfig['language'].'/admin/'.$fct.'.php') {
			include_once XOOPS_ROOT_PATH.'/modules/system/language/english/admin/'.$fct.'.php';
		}
		
		$confcat_handler = xoops_gethandler('configcategory');
        $confcats = $confcat_handler->getObjects();
        $catcount = count($confcats);
        for ($i = 0; $i < $catcount; $i++) {
			$pref[$i]['title'] = constant($confcats[$i]->getVar('confcat_name'));
			$pref[$i]['link'] = XOOPS_URL.'/modules/system/admin.php?fct=preferences&op=show&confcat_id='.$confcats[$i]->getVar('confcat_id');
		}

		return $pref;
	}
	
	protected function confMenu($enable_config = array('banners')) {
		global $xoopsConfig;
		$fct = 'preferences';
		if (!@include_once XOOPS_ROOT_PATH.'/modules/system/language/'.$xoopsConfig['language'].'/admin/'.$fct.'.php') {
			include_once XOOPS_ROOT_PATH.'/modules/system/language/english/admin/'.$fct.'.php';
		}
		
		$arr = array();
		$config_handler =& xoops_gethandler('config');
		
		$criteria = new CriteriaCompo();
		$allow_config = array('sitename', 'debug_mode');
		foreach($enable_config as $conf_name) {
			$criteria->add(new Criteria('conf_name', $conf_name), 'OR');
		}
		$criteria->setSort('conf_order');
		
		$configs = $config_handler->getConfigs($criteria);
		foreach (array_keys($configs) as $i) {
			$conf = array();
			$conf['id']    = $configs[$i]->getVar('conf_id');
			$conf['title'] = constant($configs[$i]->getVar('conf_title'));
			$conf['name']  = $configs[$i]->getVar('conf_name');
			$conf['value'] = $configs[$i]->getVar('conf_value');
			$conf['formtype'] = $configs[$i]->getVar('conf_formtype');
			$conf['valuetype'] = $configs[$i]->getVar('conf_valuetype');
			
			$options = $config_handler->getConfigOptions(new Criteria('conf_id', $configs[$i]->getVar('conf_id')));
			foreach($options as $opt) {
				$conf['options'][] = array(
					'val' => defined($opt->getVar('confop_value')) ? constant($opt->getVar('confop_value')) : $opt->getVar('confop_value'),
					'key' => defined($opt->getVar('confop_name'))  ? constant($opt->getVar('confop_name'))  : $opt->getVar('confop_name')
				);
			}
			$arr[] = $conf;
		}
		return $arr;
	}
	
}
?>