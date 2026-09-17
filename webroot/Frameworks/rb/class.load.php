<?php
/**
 * Warning: require PHP5
 * Xoops module header rewrite handler
 * 
 * @copyright      RB Lin (xtheme@gmail.com>
 * @license        WTFPL Version 2 - http://sam.zoy.org/wtfpl/
 * @author         RB Lin (xtheme@gmail.com>
 * @url            http://code.google.com/p/xoops-rb-framework/
 * @version        0.2
 */


/**
 * use after (need $xoopsTpl or $xoTheme)
 * include_once $GLOBALS['xoops']->path('header.php');
 * OR
 * include_once XOOPS_ROOT_PATH.'/header.php';
 * 
 * include_once XOOPS_ROOT_PATH.'/(Frameworks/rb/)class.load.php';
 * $handler = new RbLoad();
 * $handler // Method Chaining
 * ->setApiKey('↑ ↑ ↓ ↓ ← → ← → B A') // ← this API Key can run any webs
 * ->setLibrary('jquery', '1.4.2')
 * ->setBaseUrl(XOOPS_URL.'/Frameworks/jquery')
 * ->loadJs('001.js')
 * ->loadJs('002.js')
 * ->loadCss('001.css')
 * ->loadCss('002.css')
 * ->loadPlugin('fancybox')
 * ->rewrite();
 */

defined('XOOPS_ROOT_PATH') or die('Restricted access');
define('FRAMEWORKS_URL',  XOOPS_URL.'/Frameworks');

class RbLoad
{
	protected $supportLibs = array('jquery','jqueryui','prototype','scriptaculous','mootools','dojo','swfobject','yui','ext-core');
	
	public $_apikey     = null;
	public $_baseurl    = FRAMEWORKS_URL;
	public $_target     = 'xoops_module_header';
	public $_library    = array();
	public $_loaded     = array();
	public $_callback   = array();
	public $_attributes = array();
	public $_round      = 1;
	
	// $library = array('jquery' => '1.4.2', 'jqueryui' => '1.7.2')
	public function __construct($apikey = '', $baseurl = '', $target = '', $library = array())
	{
		$this->setApiKey($apikey);
		$this->setBaseUrl($baseurl);
		$this->setTarget($target);
		if (count($library) > 0):
		$this->setLibrary($library);
		endif;
	}
	
	public function RbLoad($apikey = '', $baseurl = '', $target = '', $library = 'jquery')
	{
		$this->__construct($apikey, $baseurl, $target, $library);
	}
	
	/**
	* set your Google API Key
	* $handler = new RbLoad('YOUR_API_KEY');
	* OR
	* $handler = new RbLoad();
	* $handler->setApiKey('YOUR_API_KEY');
	* 
	* Sign-up for an AJAX Search API Key here
	* http://code.google.com/intl/en/apis/ajaxsearch/signup.html
	*/
	public function setApiKey($apikey = '')
	{
		if ($apikey != '') $this->_apikey = $apikey;
		return $this;
	}
	
	/**
	* set base url to load files
	* $handler->setBaseUrl(XOOPS_URL.'/Frameworks/jquery');
	* $handler->loadJs('jquery.form.js');
	* load http://xoops_url/Frameworks/jquery/jquery.form.js
	*/
	public function setBaseUrl($baseurl = '')
	{
		if ($baseurl != '') $this->_baseurl = $this->checkUrl($baseurl);
		return $this;
	}
	
	/**
	* set use library and verson load from Google
	* $handler->setLibrary('jquery', '1.4.2');
	* $handler->setLibrary('jqueryui', '1.7.2');
	*/
	public function setLibrary($library, $verson = '')
	{
		if (!is_array($library)) {
			if (!empty($verson)) {
				$library = array($library => $verson);
			} else {
				return $this;
			}
		}
		
		foreach ($library as $lib => $ver) {
			$lib = strtolower($lib);
			if (in_array($lib, $this->supportLibs)) {
				if (array_key_exists($lib, $this->_library)) continue;
				$this->_library[$lib] = $ver;
			}
		}
		return $this;
	}
	
	/**
	* set callback functions
	*/
	public function setCallback($coding = array())
	{
		if (!is_array($coding)) $coding = (array)$coding;
		foreach ($coding as $code) {
			$code = trim($code);
			if ($code != '') $this->_callback[] = $code;
		}
		return $this;
	}
	
	/**
	* set smarty variable to rewrite
	* default target is "xoops_module_header"
	* $handler->setTarget('my_module_header');
	*/
	public function setTarget($target = '')
	{
		if ($target != '') $this->_target = $target;
		return $this;
	}
	
	/**
	* get smarty variable value
	* default target is "xoops_module_header"
	*/
	public function getTarget($debug = false)
	{
		global $xoopsTpl;
		$val = $xoopsTpl->get_template_vars($this->_target);
		if ($debug) {
			$val = htmlspecialchars($val);
		}
		return $val;
	}
	
	/**
	* rewrite meta tag
	* $handler->setMeta('keywords', 'new keywords');
	*/
	public function setMeta($tag = '', $val = '')
	{
		global $xoopsTpl, $xoTheme;
		if ($xoTheme) {
			$xoTheme->addMeta('meta', $tag, $val);
		} else {
			$xoopsTpl->assign('xoops_meta_'.$tag, $val);
		}
		return $this;
	}
	
	/**
	* check a url is valid and exist
	*/
	protected function checkUrl($url)
	{
		if (substr($url, 0, 7) != 'http://') {
			if (substr($url, 0, 1) != '/') $url = '/'.$url;
			$url = $this->_baseurl.$url;
		}
		$fp = @fopen($url, 'r');
		if ($fp === false) return false;
		return $url;
	}
	
	/**
	* add attr array
	*/
	protected function setAttr($attr = array())
	{
		$this->_attributes[] = $attr;
	}
	
	/**
	* render attr array
	*/
	protected function renderAttr($attr)
	{
		$str = '';
		foreach ($attr as $name => $val) {
			if ($name != 'mode' && $name != '>_<' && $val != '') {
				$str .= sprintf(' %s="%s"', $name, htmlspecialchars($val, ENT_QUOTES));
			}
		}
		return $str;
	}
	
	/**
	* format attr array (js || css) && (link || content)
	*/
	protected function formatAttr($attr)
	{
		$str = '';
		switch ($attr['mode'])
		{
			case 'js':
				$str .= "\n".'<script'.$this->renderAttr($attr).'>';
				if (!empty($attr['>_<'])) {
					$str .= "\n//<![CDATA[\n".$attr['>_<']."\n//]]>\n";
				}
				$str .= '</script>';
				break;
				
			case 'css':
				if (!empty($attr['>_<'])) {
					$str .= "\n<style".$this->renderAttr($attr).">\n/* <![CDATA[ */\n" . $attr['>_<'] . "\n/* //]]> */\n</style>";
				} else {
					$str .= "\n".'<link rel="stylesheet"'.$this->renderAttr($attr).' />';
				}
				break;
		}
		return $str;
	}
	
	/**
	* the same as $xoTheme->addScript();
	*/
	public function addScript($src = '', $attr = array(), $text = '')
	{
		$attr = !empty($attr) ? $attr : array();
		$attr['mode'] = 'js';
		$attr['type'] = !isset($attr['type']) ? 'text/javascript' : $attr['type'];
		$attr['src']  = !empty($src) ? $this->checkUrl($src) : '';
		$attr['>_<'] = !empty($text) ? $text : '';
		if ($attr['src'] || $attr['>_<'])
		$this->setAttr($attr);
		return $this;
	}
		
		/**
		* addScript aliases, quick load javascript file
		* $handler->loadJs('my.js');
		*/
		public function loadJs($url = '', $attr = array())
		{
			return $this->addScript($url, $attr);
		}
		
		/**
		* addScript aliases, quick write javascript code
		* $handler->writeJs('alert("hello world");');
		*/
		public function writeJs($text = '', $attr = array())
		{
			return $this->addScript('', $attr, $text);
		}

	/**
	* the same as $xoTheme->addStylesheet();
	*/
	public function addStylesheet($href = '', $attr = array(), $text = '')
	{
		$attr = !empty($attr) ? $attr : array();
		$attr['mode'] = 'css';
		$attr['type'] = !isset($attr['type']) ? 'text/css' : $attr['type'];
		$attr['href'] = !empty($href) ? $this->checkUrl($href) : '';
		$attr['>_<'] = !empty($text) ? $text : '';
		if ($attr['href'] || $attr['>_<'])
		$this->setAttr($attr);
		return $this;
	}
		
		/**
		* addStylesheet aliases, quick load css file
		* $handler->loadCss('my.css');
		*/
		public function loadCss($url = '', $attr = array())
		{
			return $this->addStylesheet($url, $attr);
		}
		
		/**
		* addStylesheet aliases, quick write css code
		* $handler->writeCss('a{color:#000;} a:hover {color:#F00;}');
		* OR
		* $style = 'a{color:#000;} a:hover {color:#F00;}';
		* $handler->writeCss($style);
		*/
		public function writeCss($text = '', $attr = array())
		{
			return $this->addStylesheet('', $attr, $text);
		}
	
	/**
	* if $_baseurl = XOOPS_URL./Frameworks/jquery
	* and your jquery plugin is a dir e.g. ../jquery/fancybox
	* you can use loadDir('fancybox') or loadPlugin('fancybox') to load "fancybox" dir all files
	*/
	public function loadDir($dir = '')
	{
		$dir = trim($dir);
		if (empty($dir)) return $this;
		if (substr($dir, 0, 1) != '/') $dir = '/'.$dir;
		$dirPath = str_replace(XOOPS_URL, '', $this->_baseurl);
		$dirPath = XOOPS_ROOT_PATH.$dirPath.$dir;
		
		require_once XOOPS_ROOT_PATH.'/class/xoopslists.php';
		$filesList =& XoopsLists::getFileListAsArray($dirPath);
		foreach ($filesList as $filename) {
			$ext = strtolower(array_pop(explode('.', $filename)));
			$url = $this->_baseurl.$dir.'/'.$filename;
			switch ($ext) {
				case 'js':
					$this->loadJs($url);
					break;
				case 'css':
					$this->loadCss($url);
					break;
			}
		}
		return $this;
	}
		/**
		* loadDir aliases
		*/
		public function loadPlugin($dir = '')
		{
			return $this->loadDir($dir);
		}
	
	/**
	* load Google AJAX Libraries API
	*/
	protected function googleLoad($callback = false)
	{
		static $loaded = null;
		$str = '';

		// Google's AJAX Libraries API (google.load()/jsapi) was shut down by Google
		// years ago, and was loaded over plain http:// on what may be an https page
		// regardless (blocked as mixed content even when it did work). Every caller
		// of setLibrary() silently got no jQuery at all, and with it none of the
		// dependent layout/interaction JS - load real scripts directly from a
		// working CDN instead, same as the front-end theme already does.
		if (count($this->_library) > 0):
			$cdn_map = array(
				'jquery'   => 'https://code.jquery.com/jquery-%s.min.js',
				'jqueryui' => 'https://code.jquery.com/ui/%s/jquery-ui.min.js',
			);

			$newlibs = array_diff($this->_library, $this->_loaded);
			$libs = count($newlibs);
			$calls = count($this->_callback);
			if (!empty($libs) || ($callback && !empty($calls))):
				foreach ($this->_library as $lib => $ver) {
					if (in_array($lib, $this->_loaded)) continue;
					$this->_loaded[] = $lib; // already loaded
					if (isset($cdn_map[$lib])) {
						$str .= "\n".'<script type="text/javascript" src="'.sprintf($cdn_map[$lib], $ver).'"></script>';
					}
				}
				// run any queued callbacks once jQuery itself is ready, in place of
				// the dead google.setOnLoadCallback()
				if (!empty($calls)):
				$str .= "\n".'<script type="text/javascript">';
				$str .= "\n//<![CDATA[\n";
				$str .= "$(function(){\n";
				$str .= implode("\n", $this->_callback);
				$str .= "\n});\n";
				$str .= "//]]>\n</script>";
				endif;
			endif;
		endif;
		return $str;
	}
	
	/**
	* after execute clear all attributes and back to start (or another) target
	* $handler = new RbLoad();
	* $handler->setBaseUrl('js')->loadJs('xxx.js')->restart('css')->loadCss('yyy.css')->rewrite();
	*/
	public function restart($baseurl = '', $target = '', $library = '')
	{
		// execute, don't run callback
		$this->rewrite(false);
		// clear
		$this->_callback = array();
		$this->_attributes = array();
		// restart
		$this->setBaseUrl($baseurl);
		$this->setTarget($target);
		$this->setLibrary($library);
		return $this;
	}
	
	/**
	* rewrite module header
	* $handler = new RbLoad('GOOGLE_API_KEY');
	* $handler->setLibrary('jquery', '1.4.2')->loadJs('jqueryui', '1.7.2');
	* $handler->loadJs('xxx.js')->loadCss('yyy.css')->rewrite();
	*/
	public function rewrite($callback = true, $interpolate = 'after')
	{
		global $xoopsTpl;
		static $loaded = null;
		
		$present = $this->getTarget();
		$insert = "<!-- RbLoad $this->_round -->";
		$this->_round++;
		if ($loaded === null):
			$title  = null;
			$loaded = true;
		endif;
		
		$insert .= $this->googleLoad($callback);
		foreach ($this->_attributes as $attr)
		{
			$insert .= $this->formatAttr($attr);
		}
		
		switch ($interpolate)
		{
			case 'after':   $content = $present ."\n". $insert; break;	
			case 'before':  $content = $insert ."\n". $present; break;
			case 'replace': $content = $insert; break;
		}
		$xoopsTpl->assign($this->_target, $content);
	}
}
?>