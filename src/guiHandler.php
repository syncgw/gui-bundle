<?php
declare(strict_types=1);

/*
 * 	User interface handler class
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

/**
 * 	Session variable used:
 *
 *	$_SESSION[parent::getVar('SessionID')][self::TYP]	= self::TYP_USR=User / self::TYPADM=Admin
 *	$_SESSION[parent::getVar('SessionID')][self::UID]	= User ID
 *	$_SESSION[parent::getVar('SessionID')][self::PWD]	= User password
 */

namespace syncgw\gui;

use syncgw\lib\Config;
use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\HTTP;
use syncgw\lib\User;
use syncgw\lib\XML;
use syncgw\lib\Log;
use syncgw\lib\Util;

class guiHandler extends XML {

	// handler status
	const CONT	  = '0';
	const STOP	  = '1';
	const RESTART = '2';

	const UID	  = 'UserID';				// userid field in session
	const PWD	  = 'UserPW';				// password field in session
	const TYP	  = 'Type';					// type field in session
	const AJAX	  = 'Ajax';					// ajax file in session
	const BACKUP  = 'Backup';				// ajax backup file in session

	const TYP_USR = '1';					// user
	const TYP_ADM = '2';					// administrator

	// color mapping table
    const COLOR   = [
			Log::ERR 	=> Config::CSS_ERR,
			Log::WARN	=> Config::CSS_WARN,
			Log::INFO	=> Config::CSS_INFO,
			Log::APP	=> Config::CSS_APP,
    		Log::DEBUG	=> Config::CSS_DBG,
	];

    // modules
    const MODS    = [
        'guiHelp',
        'guiMemory',
   		'guiCheck',
    	'guiSwitch',
    	'guiConfig',
        'guiLogFile',
        'guiTrunc',
    	'guiExplorer',
    	'guiDelete',
        'guiCleanUp',
    	'guiReload',
        'guiStats',
        'guiSync',
        'guiRename',
        'guiShow',
        'guiTrace',
        'guiTraceExport',
    	'guiEdit',
        'guiDownload',
        'guiUpload',
    	'guiSetUsr',
        'guiForceTrace',
    ];

    /**
	 * 	Ajax file pointer
	 * 	@var resource
	 */
	private $_fp   = null;

	/**
	 * 	Max. record length
	 * 	@var integer
	 */
	private $_max  = 1048576*3;				// get max record length (3 MB)

	/**
	 * 	Output window scroll position
	 * 	@var array
	 */
	private $_win  = [];

	/**
	 * 	Plugin array
	 * 	@var array
	 */
	private $_plug = [];

	/**
	 * 	Q-Box counter
	 * 	@var int
	 */
	private $_cnt  = 0;

	/**
	 * 	Command window counter
	 * 	@var int
	 */
	private $_cmd;

	/**
	 * 	Initialization flag
	 * 	@var bool
	 */
	private $_init = false;

    /**
     * 	Singleton instance of object
     * 	@var guiHandler
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiHandler {

		if (!self::$_obj) {

            self::$_obj = new self();

			$cnf  = Config::getInstance();
            $cnf->updVar(Config::DBG_EXCL, [ '*', ]);
            Log::getInstance()->Plugin('readLog', self::$_obj);

			$http = HTTP::getInstance();

			// setup variables
			self::$_obj->loadXML(
					'<syncgw>'.
					// URL to HTML directory
					'<HTML>vendor/syncgw/</HTML>'.
					// the script we're serving
					'<ScriptURL>'.base64_encode($http->getHTTPVar('PHP_SELF').'?'.$http->getHTTPVar('QUERY_STRING')).'</ScriptURL>'.
					// missing JavaScript message (translation)
					'<NoJavaScript>You need to turn on Javascript to access these pages!</NoJavaScript>'.
					// browser window height (0=full screen)
					'<WinHeight>0</WinHeight>'.
					// browser window width  (0=full screen)
					'<WinWidth>0</WinWidth>'.
					// hidden variables
					'<Hidden/>'.
					// additional script file data
					'<Script/>'.
					// buttons
					'<Button/>'.
					// message field
					'<Message/>'.
					// action to  perform
					'<Action/>'.
					// last command performed
					'<LastCommand/>'.
					// memory statistics
					'<Usage/>'.
					// login variables
					'<Logout>'.XML::cnvStr(self::$_obj->mkButton('Logout',
							'Logout from <strong>sync&bull;gw</strong>', 'LogOff')).'</Logout>'.
					'<UserText/><UserDisabled/><UserID/><PasswordText/><UserPW/><AdminText/><AdminFlag/>'.
			        '<LoginButton/><ExtButton/><LoginMsg/>'.
	    		    // syncgw version
					'<Version>'.$cnf->getVar(Config::VERSION).'</Version>'.
					// HTML skeleton
					'<Skeleton>interface.html</Skeleton>'.
					// set size of Q-box "icon"
					'<QBoxStyle>width:18px; padding:0px; font-size:9px;float:inline-start;margin-right:5px;</QBoxStyle>'.
					'</syncgw>');

			// start session
			if (($stat = session_status()) == \PHP_SESSION_NONE)
	       		ini_set('session.cookie_lifetime', '0');

			if ($stat != \PHP_SESSION_ACTIVE) {

				if (!isset($_POST['SessionID'])) {

					$id = session_create_id();
					self::$_obj->putHidden('SessionID', $id);
				} else
					self::$_obj->putHidden('SessionID', $id = $_POST['SessionID']);
				session_id($id);
				session_start();
			} else
				$id = self::$_obj->getVar('SessionID');

			// create ajax file
			if (!isset($_SESSION[$id][self::AJAX])) {

				$_SESSION[$id][self::AJAX] = Util::getTmpFile('ajax');
				$_SESSION[$id][self::BACKUP] = Util::getTmpFile('back');

			} elseif (file_exists($_SESSION[$id][self::AJAX]))

				file_put_contents($_SESSION[$id][self::BACKUP],
								  file_get_contents($_SESSION[$id][self::AJAX]));

			// load extensions
			foreach (self::MODS as $mod) {

			    if (!file_exists($file = $cnf->getVar(Config::ROOT).'gui-bundle/src/'.$mod.'.php'))
			        continue;

				$class = 'syncgw\\gui\\'.$mod;
				self::$_obj->_plug[$file] = $class::getInstance();
			}

			// swap window size
			if (isset($_GET['heigth']))
			    self::$_obj->updVar('WinHeight', $_GET['heigth']);
			if (isset($_GET['width']))
			    self::$_obj->updVar('WinWidth', $_GET['width']);

			// swap POST data
			foreach ($_POST as $k => $v)
				self::$_obj->updVar($k, is_array($v) ? base64_encode(serialize($v)) : $v);

			// get scroll position
			if (!(self::$_obj->_win['Cmd'] = self::$_obj->getVar('sgwCmdPos')))
				self::$_obj->_win['Cmd'] = -1;
			if (!(self::$_obj->_win['Msg'] = self::$_obj->getVar('sgwMsgPos')))
				self::$_obj->_win['Msg'] = -1;
		}

		return self::$_obj;
	}

 	/**
	 * 	Process client request
	 */
	public function Process(): void {

		// fuly initialized?
		if (!$this->_init) {

			// disable tracing
			Config::getInstance()->updVar(Config::TRACE_CONF, 'Off');

			$this->_init = true;
		}

		// check user login
		if (!self::Login()) {

			self::_flush();
			return;

		}

		$run = true;
		do {

			// clear buttons
			parent::updVar('Button', '');
			parent::updVar('Message', '');

			if (!($cmd = parent::getVar('Command')))
				$cmd = parent::getVar('Action');
			else
				parent::updVar('LastCommand', $cmd);

			// any command to execute?
			$stop = 0;
			if ($cmd) {

				// check for commands
				foreach ($this->_plug as $obj) {

					switch ($obj->Action($cmd)) {
					case self::CONT:
						$stop = 1;
					 	break;

					case self::STOP:
						$stop = 2;
						break;

					case self::RESTART:
						$stop = 3;
						break;

					default:
						break;
					}
					if ($stop > 1)
						break;
				}
				if ($stop == 2)
					$run = false;
			}
			if ($stop == 3)
				continue;

			if (substr($cmd, 0, 3) == 'Exp')
				$run = false;

			if ($run) {

				$this->_cmd = 0;
				foreach ($this->_plug as $obj) {

					switch ($obj->Action('Init')) {
					case self::CONT:
					 	break;

					case self::STOP:
						$run = false;
						break;

					default:
						break;
					}
					if (!$run)
						break;
				}
				if ($this->_cmd)
					parent::updVar('Button', parent::getVar('Button').self::mkButton('Run',
								'Execute selected command from list above'));
				$run = false;
			}

		} while ($run);;

		// set ajax data
		parent::updVar('Ajax', '<input type="hidden" id="sgwCmdPos" name="sgwCmdPos" value="'.$this->_win['Cmd'].'" />'.
					   '<input type="hidden" id="sgwMsgPos" name="sgwMsgPos" value="'.$this->_win['Msg'].'" />'.
					   '<script type="text/javascript">sgwAjaxStart(\''.parent::getVar('HTML').
					   'gui-bundle/src/guiAjax.php?n='.base64_encode($_SESSION[parent::getVar('SessionID')][self::AJAX]).'\',1);</script>');

		self::_flush();
	}

	/**
	 * 	Check server configuration status
	 *
	 * 	@return	- true=Ok; false=Unavailable
	 */
	public function isConfigured(): bool {

		$cnf = Config::getInstance();
		if (!file_exists($cnf->Path))
			return false;

		// is syncgw initalized?
		if (!$cnf->getVar(Config::DATABASE))
			return false;

		return true;
	}

	/**
	 * 	Read log messages and display
	 *
	 * 	@param	- Log color
	 * 	@param	- Message text
	 */
	public function readLog(int $typ, string $data): void {

		$pre = $typ & Log::ERR ? '+++' : '---';
		self::_writeMsg('Msg', '<code><div style="width:423px; float:left;">'.$pre.' '.date('Y M d H:i:s').'</div>'.
							   '<div>'.$data.'</div></code>', self::COLOR[$typ & ~Log::ONETIME]);
	}

	/**
	 * 	Set scroll position in window
	 *
	 * 	@param	- Window ID
	 * 	@param 	- Pixel scroll position in winow
	 */
	public function setScrollPos(string $w, int $pos): void {

		$this->_win[$w] = $pos;
	}

	/**
	 * 	Save hidden variable
	 *
	 * 	@param	- Variable name
	 * 	@param	- Value
	 */
	public function putHidden(string $var, string $val): void {

		if (($org = parent::getVar('Hidden')) !== null)
			$v = preg_replace('/(.*name="'.$var.'" value=")(.*)(".*)/', '${1}'.$val.'${3}', $org);

	    // anything changed?
		if (!$org || strpos($org, 'name="'.$var.'"') === false)
			$v = $org.'<input type="hidden" id="'.$var.'" name="'.$var.'" value="'.$val.'" />';
		parent::updVar('Hidden', $v);
		parent::getVar('syncgw');
		parent::updVar($var, $val, false);
	}

	/**
	 * 	Add tabbed message
	 *
	 * 	@param	- Message left
	 * 	@param	- Message color left; defaults to CSS_NONE
	 * 	@param	- Message right; defaults to none
	 * 	@param	- Message color right; defaults to CSS_NONE
	 */
	public function tabMsg(string $lmsg, string $lcss = Config::CSS_NONE, string $rmsg = '', string $rcss = Config::CSS_NONE): void {

		if (!$lmsg)
			$lmsg = '&nbsp;';
		self::_writeMsg('Msg', '<div style="width:70%;float:left;white-space:pre-wrap;'.$lcss.'">'.
				$lmsg.'</div><div style="width: 27%; float: left; '.$rcss.'">'.$rmsg.'</div>');
	}

	/**
	 * 	Show message in message window
	 *
	 *	@param	- Message text
	 * 	@param	- Message color; defaults to Config::CSS_NONE
	 */
	public function putMsg(string $msg, string $css = Config::CSS_NONE): void {

		self::_writeMsg('Msg', $msg, $css);
	}

	/**
	 * 	Create button
	 *
	 * 	@param	- Button name
	 * 	@param	- Button help text
	 * 	@param	- "Action" value or JavaScript code
	 * 	@param	- true=Delete message <DIV> (default); false=Do not delete
	 *  @return - HTML string
	 */
	public function mkButton(string $name, string $help = '', string $action = '', bool $del = true): string {

		if ($name == self::STOP) {

			$name = 'Return';
			if ($action == 'Explorer')
				$help = 'Return to explorer';
			else
				$help = 'Return to command selection menu';
		}
		if (!stripos($action, ';'))
			$js = 'document.getElementById(\'Action\').value=\''.$action.'\'';
		else
			$js = $action;
		return '<input type="submit" class="sgwBut" value="'.$name.'" '.
				'onclick="'.$js.';sgwAjaxStop('.($del ? '1' : '0').');" title="'.$help.'" />';
	}

	/**
	 * 	Clear message file
	 */
	public function clearAjax(): void {

		if ($this->_fp)
			ftruncate($this->_fp, 0);
	}

	/**
	 * 	Show message in command window
	 *
	 *  @param	- Message text
	 */
	public function putCmd(string $msg): void {

		$this->_cmd++;
		self::_writeMsg('Cmd', $msg);
	}

	/**
	 * 	Create Q-box
	 *
	 * 	@param	- Title
	 * 	@param	- HTML input field definition
	 * 	@param	- Help text
	 * 	@param	- true=Show open Q-box; false=Close Q-box
	 * 	@param	- Window ID (defaults to "Cmd" window)
	 */
	public function putQBox(string $title, string $input, string $cont, bool $open, string $win = 'Cmd'): void {

		if ($open) {

			$n = '-';
			$s = 'visibility: visible; ';
		} else {

			$n = '+';
			$s = 'visibility: hidden; display: none; ';
		}
		if (!$input) {

			$input = '<br style="clear: both;"/>';
			$dw = '';
		} else
			$dw = 'width: 50%;';

		$msg = '<div style="'.$dw.'float:left;">'.
				'<input id="QBox'.$this->_cnt.'B" type="button" value="'.$n.'" style="'.parent::getVar('QBoxStyle').
				'" onclick="QBox(\'QBox'.$this->_cnt.'\');" /> '.$title.'</div>'.
				'<div style="float:left;">'.$input.'</div><div id="QBox'.$this->_cnt.'" style="'.$s.Config::CSS_QBG.
				'padding: 3px 5px 5px 5px; overflow: auto; margin: 0 0 10px 0; '.
				'border: 1px solid; clear:left;" />'.$cont.'</div>'.
				'<div style="clear:left;"></div>';
		$this->_cnt++;

		self::_writeMsg($win, $msg, '', false);
	}

	/**
	 * 	Check wheter we are logged in as administrator
	 *
	 * 	@return	- true = Ok; false = Error
	 */
	public function isAdmin(): bool {

		if (!isset($_SESSION[$id = parent::getVar('SessionID')]))
			return false;

		return (bool)($_SESSION[$id][self::TYP] & intval(self::TYP_ADM));

	}

	/**
	 * 	Login user
	 *
	 *	@param	- force user ID to be loaded (none=default)
	 * 	@return	- true = Ok; false = Error
	 */
	function Login(string $uid = ''): bool {

		// force user id? - this is not a real login!
		if ($uid) {

			// normalize user id
			if (strpos($uid, '@') !== false)
				list($uid,) = explode('@', $uid);
			// get selected user
			$db = DB::getInstance();
			if (!($doc = $db->Query(DataStore::USER, DataStore::RGID, $uid)))
				return false;
			// load user object
			$usr = User::getInstance();
			$usr->loadXML($doc->saveXML());
			return true;
		}

		// check for logoff
		if (($action = parent::getVar('Action')) == 'LogOff') {

			unset($_SESSION[parent::getVar('SessionID')][self::UID]);
		    unset($_SESSION[parent::getVar('SessionID')][self::PWD]);
			if ($this->_fp) {

				fclose($this->_fp);
				$this->_fp = null;

			}
			unlink($_SESSION[parent::getVar('SessionID')][self::AJAX]);
		    unset($_SESSION[parent::getVar('SessionID')][self::AJAX]);

		}

		$cnf = Config::getInstance();
		// get administrator password
		$apw = $cnf->getVar(Config::ADMPW);

		// user already logged in?
		if (isset($_SESSION[parent::getVar('SessionID')][self::PWD])) {

			 User::getinstance()->loadUsr(base64_decode($_SESSION[parent::getVar('SessionID')][self::UID]));
			return true;
		}

		// check login data?
		if ($action == 'Login') {

			// first time login?
			if (!$cnf->getVar(Config::ADMPW)) {

				// save admin password
				if (($pw = parent::getVar('UserPW')) == parent::getVar('UserID')) {

					$cnf->updVar(Config::ADMPW, $pw = self::encrypt($pw));
					// save to .INI file
					$cnf->saveINI();
					$_SESSION[parent::getVar('SessionID')][self::TYP] = self::TYP_ADM;
					$_SESSION[parent::getVar('SessionID')][self::UID] = '';
					$_SESSION[parent::getVar('SessionID')][self::PWD] = $pw;
					return true;

				}

				parent::updVar('LoginMsg', 'Password does not match - please retry');

			}
			// admin login?
			elseif (parent::getVar('AdminFlag')) {

			    if (!($upw = parent::getVar('UserPW')))
				    parent::updVar('LoginMsg', 'Please enter administrator password');
			    elseif (self::encrypt($upw) == $apw) {

					$_SESSION[parent::getVar('SessionID')][self::TYP] = self::TYP_ADM;
					$_SESSION[parent::getVar('SessionID')][self::UID] = '';
					$_SESSION[parent::getVar('SessionID')][self::PWD] = $apw;
					return true;

			    } else
    				parent::updVar('LoginMsg', 'Invalid administrator password');

			}
			// check user login parameter
			else {

				if ($uid = parent::getVar('UserID')) {

					if ($pw = parent::getVar('UserPW')) {

						// perform first time login (we have a clear passwort available)
						$usr = User::getInstance();
						if (!$usr->Login($uid, $pw))
							parent::updVar('LoginMsg', 'Invalid password');
						else {

							$_SESSION[parent::getVar('SessionID')][self::TYP] = self::TYP_USR;
							$_SESSION[parent::getVar('SessionID')][self::UID] = base64_encode($uid);
							$_SESSION[parent::getVar('SessionID')][self::PWD] = self::encrypt($pw);
							return true;

						}

					} else
						parent::updVar('LoginMsg', 'Please enter password');

				} else
					parent::updVar('LoginMsg', 'Please enter user name');

			}
		}

		// set login skeleton
		if (isset($_GET['adm'])) {

    		parent::updVar('Skeleton', 'admin.html');
    		if (strstr($_SERVER['REQUEST_URI'], 'adm=plesk') !== false)
    		    parent::updVar('ExtButton', self::mkButton('External',
    		    			'Login to <strong>sync&bull;gw</strong> in a new browser window',
        		            'var w = window.open(\''.(isset($_SERVER['HTTPS']) ? 'https://' : 'http://').
		                    $_SERVER['REMOTE_ADDR'].$_SERVER['SCRIPT_NAME'].
		                   '?sess='.uniqid('sgw').(strpos($_SERVER['REQUEST_URI'], 'adm') ? '&adm' : '').'\');w.focus();'));

		} elseif (!$apw)
    		parent::updVar('Skeleton', 'init.html');
        else
    		parent::updVar('Skeleton', 'login.html');
        parent::updVar('LoginButton', self::mkButton('Login', 'Login to <strong>sync&bull;gw</strong>', 'Login'));

		// is administrator password defined?
		if (!$apw) {

		    parent::updVar('UserText', 'Administrator password');
			parent::updVar('PasswordText', 'Reenter password');

		} else {

		    // admin login status?
    		$adm = parent::getVar('AdminFlag');
    		parent::updVar('UserText', 'User name');
        	parent::updVar('UserDisabled', $adm ? 'disabled="disabled"' : '');
    		parent::updVar('PasswordText', 'Password');
    		parent::updVar('AdminText', 'Login as administrator');
            parent::updVar('AdminFlag', $adm ? '1' : '0');

		}

		return false;
	}

	/**
	 * 	Encrypt password
	 *
	 * 	@param	- Password
	 * 	@return	- Encrpted password
	 */
	public function encrypt(string $pw): string {

		for ($i=0; $i < 1000; $i++)
			$pw = md5($pw);
		return base64_encode($pw);
	}

	/**
	 * 	Write message to window
	 *
	 * 	@param 	- Window ID
	 *  @param	- Message text
	 *  @param	- Message color; defaults to CSS_NONE
	 *  @param	- true= Add line break at end of message (default) - used by putQBox()
	 */
	private function _writeMsg(string $w, string $msg, string $css = Config::CSS_NONE, bool $lbr = true): void {

		$dbg = Config::getInstance()->updVar(Config::DBG_LEVEL, Config::DBG_OFF);

		// everything flushed?
		if ($this->_fp == -1 || !isset($_SESSION[parent::getVar('SessionID')][self::AJAX])) {

			Config::getInstance()->updVar(Config::DBG_LEVEL, $dbg);
			return;

		}

		if (!$this->_fp) {

			if (!($this->_fp = @fopen($_SESSION[parent::getVar('SessionID')][self::AJAX], 'ab'))) {

			    $this->_fp = -1;
				Config::getInstance()->updVar(Config::DBG_LEVEL, $dbg);
			    return;

			} else
       			ftruncate($this->_fp, 0);
		}

		if (strlen($msg) > $this->_max)
			$msg = substr($msg, 0, $this->_max).' CUT@'.$this->_max;

		// write data
		fwrite($this->_fp, ($w == 'Cmd' ? '6' : '7').
			   '<font class="sgwDiv"'.($css != Config::CSS_NONE ? ' style="'.$css.'float:left;width:max-content;"' : '').'>'.$msg.
			   '</font>'.($lbr ? '<br style="clear: both;"/>' : '')."\n");

		Config::getInstance()->updVar(Config::DBG_LEVEL, $dbg);
	}

	/**
	 * 	Flush output to browser window
	 */
	private function _flush(): void {

		// replace data - do it this way to prevent memory exhausting
		$rk = [];
		$rv = [];

		$http = HTTP::getInstance();

		parent::getChild('syncgw');
		while (($v = parent::getItem()) !== null) {

			if (is_array($v))
				continue;

			$n = parent::getName();
			$rk[] = '{'.$n.'}';
			$rv[] = $n == 'ScriptURL' ? base64_decode($v) : $v;

		}

		// close message file
		if ($this->_fp) {

			fclose($this->_fp);
			$this->_fp = -1;
		}

		// send data
		$http->addHeader('Content-Type', 'text/html; charset=UTF-8');
		$http->addBody(str_replace($rk, $rv, file_get_contents(__DIR__.'/../assets/'.parent::getVar('Skeleton'))));
		$http->send(200);

	}

}
