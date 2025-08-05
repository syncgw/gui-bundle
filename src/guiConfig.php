<?php
declare(strict_types=1);

/*
 * 	Configuration
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 *
 */

namespace syncgw\gui;

use syncgw\lib\Config;
use syncgw\lib\DataStore;
use syncgw\lib\Log;
use syncgw\lib\User;
use syncgw\lib\Util;

class guiConfig {

   	/**
     * 	Singleton instance of object
     * 	@var guiConfig
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiConfig {

	   	if (!self::$_obj)
            self::$_obj = new self();

		return self::$_obj;
	}

 	/**
	 * 	Perform action
	 *
	 * 	@param	- Action to perform
	 * 	@return	- guiHandler status code
	 */
	public function Action(string $action): string {

		$gui = guiHandler::getInstance();
		$cnf = Config::getInstance();

		// only allowed for administrators
		if (!$gui->isAdmin())
			return guiHandler::CONT;

		switch ($action) {
		case 'Init':
			$gui->putCmd('<input id="Config" '.($gui->getVar('LastCommand') == 'Config' ? 'checked ' : '').'type="radio" name="Command" '.
						 'value="Config" onclick="document.syncgw.submit();"/>&nbsp;'.
						 '<label for="Config">'.'Configure <strong>sync&bull;gw</strong> server'.'</label>');
			break;

		case 'Config':
		case 'ConfDrop':
		case 'ConfSave':

			// set button
			$gui->updVar('Button', $gui->getVar('Button').$gui->mkButton(guiHandler::STOP, '', 'ConfReturn').
						$gui->mkButton('Save', 'Save configuration', 'ConfSave'));

			// check data base interface handler (priority #1)
			if (!self::_dbhandler($action)) {

				$gui->updVar('Action', 'ConfSave');
				return guiHandler::STOP;
			}

			$ok = true;
			foreach ([ '_datastore', '_admin', '_phperr', '_cron', '_logfile',
					'_debug',
					'_trace', '_session',
					// DAV configuration
					'_objsize',
					// ActiveSync configuration
					'_heartbeat',
					 ] as $func) {

				if (!self::$func($action))
					$ok = false;
			}

			// show status message
			if ($action == 'ConfSave') {

				if ($ok) {

					// save .INI file
					$cnf->saveINI();
										// be sure to disable tracing
				    $cnf->updVar(Config::TRACE_CONF, 'Off');
					$gui->putMsg('Configuration saved to \''.$cnf->Path.'\'');
				} else
					$gui->putMsg('Error in configuration - please check', Config::CSS_ERR);
				$gui->updVar('Action', 'Config');
			}
			return guiHandler::STOP;

		case 'ConfReturn':
			if ($cnf->getVar(Config::DATABASE) && !$cnf->getVar(Config::ENABLED)) {

				$gui->putMsg('You must enable at least one data store', Config::CSS_ERR);
				$gui->updVar('Action', 'Config');
			} else
				$gui->updVar('Action', '');
			return guiHandler::RESTART;

		default:
			break;
		}

		return guiHandler::CONT;
	}

	/**
	 * 	Configure data base interface handler
	 *
	 * 	@param	- Action to perform
	 * 	@return	- true = Ok; false = Error
	 */
	private function _dbhandler(string $action): bool {

		$gui = guiHandler::getInstance();
		$cnf = Config::getInstance();

		$tit  = 'Interface connection handler';
		$help = 'Select from list which data base interface handler <strong>sync&bull;gw</strong> should use. '.
				  'If you can\'t select a handler name, a connection is already established. '.
				  'To drop the connection, use the "Drop" button.';

		// load data base interface handler
		if (!($be = $gui->getVar('ConfBE')))
			$be = $cnf->getVar(Config::DATABASE);

		// back end available?
		if (!$be || $be == '--') {

			// clear enabled data stores
			$cnf->updVar(Config::ENABLED, 0);

			// load list of available data base interface handler
			$hd = [];
			foreach ([ 'file', 'mysql', 'roundcube', 'mail', 'myapp'] as $dir) {

				if (file_exists($cnf->getVar(Config::ROOT).$dir.'-bundle'))
					$hd[$dir] = true;

			}

			// remove sustainable handler
			if (isset($hd['mail'])) {

				unset($hd['roundcube']);
				unset($hd['mysql']);
				unset($hd['file']);
			} elseif (isset($hd['roundcube'])) {

				unset($hd['mysql']);
				unset($hd['file']);
			}

			// any data base available?
			if (!count($hd)) {

				$gui->putMsg('No data base interface handler found - please install appropriate software package',
							 Config::CSS_ERR);
				return false;
			}

			// create data base selection list
			$f = '<select name="ConfBE" onchange="document.getElementById(\'Action\').value=\'Config\';sgwAjaxStop(1);'.
				 'document.syncgw.submit();"><option>--</option>';
			foreach ($hd as $file => $unused)
				$f .= '<option>'.$file.'</option>';
			$unused; // disable Eclipse warning
			$f .= '</select>';
			$gui->putQBox($tit, $f, $help, false);

			// ready to get handler specific parameters
			$gui->putHidden('ConfGetBEParm', '1');

			return false;
		}

		// allocate handler
		$adm = 'syncgw\\interface\\'.$be.'\\Admin';
		$adm = $adm::getInstance();

		// drop data base connection
		if ($action == 'ConfDrop') {

			// disconnect
			if ($adm->DisConnect()) {

				$cnf->updVar(Config::DATABASE, '');
				$gui->updVar('ConfBE', '');
				// rebuild stored trace mode
				$cnf->updVar(Config::TRACE_CONF, $cnf->getVar(Config::TRACE_CONF, true));
				$cnf->saveINI();
				$cnf->updVar(Config::TRACE_CONF, 'Off');
			}
			return self::_dbhandler('Config');
		}

		// show current handler
		$gui->putQBox($tit, '<input type="text" size="20" readonly name="ConfBE" value="'.$be.'" />', $help, false);

		// save configuration?
		if ($action == 'ConfSave') {

			// connect
			if (!$adm->Connect()) {

				$gui->putHidden('ConfGetBEParm', '1');
				return self::_dbhandler('Config');
			}
			$gui->putHidden('ConfGetBEParm', '0');
			$gui->updVar('Action', 'Config');
			$cnf->updVar(Config::DATABASE, $be);
			$cnf->updVar(Config::TRACE_CONF, $cnf->getVar(Config::TRACE_CONF, true));
			$cnf->saveINI();
			$cnf->updVar(Config::TRACE_CONF, 'Off');
		}

		// get configuration parameter
		if ($gui->getVar('ConfGetBEParm')) {

			$adm->getParms();
			return false;
		}

		// set button
		$gui->updVar('Button', $gui->getVar('Button').
						$gui->mkButton('Drop', 'Drop <strong>sync&bull;gw</strong> interface connection',
						'var v=document.getElementById(\'Action\');'.
						'if (confirm(\''.sprintf('Do you really want to drop connection to data base '.
						'interface handler >%s<?', $be).'\') == true) {'.
						'v.value=\'ConfDrop\';'.
						'} else {'.
						'v.value=\'Config\'; } return true;'));

		return true;
	}

	/**
	 * 	Configure enabled data stores
	 *
	 * 	@param	- Action to perform
	 * 	@return	- true = Ok; false = Error
	 */
	private function _datastore(string $action): bool {

		$gui = guiHandler::getInstance();
		$cnf = Config::getInstance();

		if (!($be = $cnf->getVar(Config::DATABASE)))
			return true;

		// get supported data store handlers
		$adm = 'syncgw\\interface\\'.$be.'\\Admin';
		$adm = $adm::getInstance();
		$ava = $adm->SupportedHandlers();

		$ena = $cnf->getVar(Config::ENABLED);

		if ($action == 'ConfSave') {

			$ena = 0;
			foreach (Util::HID(Util::HID_CNAME, DataStore::DATASTORES, true) as $k => $v) {

				if ($c = $gui->getVar('ConfDSName'.$k))
					$ena |= $c;
			}
			if ($ava & DataStore::EXT)
				$ena |= DataStore::EXT;
			$cnf->updVar(Config::ENABLED, $ena);
		}

		// enabled data stores
		$f = '';
		$n = 1;
		$e = Util::HID(Util::HID_ENAME, DataStore::CALENDAR|DataStore::CONTACT|DataStore::TASK|
					   DataStore::MAIL|DataStore::NOTE, true);
		foreach ($e as $k => $v) {

			if (!($k & $ava))
				$s = 'disabled="disabled"';
			elseif ($k & $ena)
				$s = 'checked="checked"';
			else
				$s = '';
			$f .= '<div style="width: 120px; float: left;">'.
					'<input name="ConfDSName'.$k.'" type="checkbox" '.$s.' value="'.$k.'" /> '.$v.'</div>';
			if (!($n++ % 5))
				$f .= '<br>';
		}

		$gui->putQBox('Enabled data store', $f,
					  'Specify which data stores you want to be enabled and available for synchronization '.
					  'with devices. If a handler is not selectable, you did not install handler modules '.
					  'or your data base connection interface handler do not support this type of data store.', false);

		if (!$ena)
			$gui->putMsg('Please enable at least one data store', Config::CSS_WARN);

		return true;
	}

	/**
	 * 	Configure administrator
	 *
	 * 	@param	- Action to perform
	 * 	@return	- true = Ok; false = Error
	 */
	private function _admin(string $action): bool {

		$gui = guiHandler::getInstance();
		$cnf = Config::getInstance();

		if (($c = $gui->getVar('ConfPwd')) && $action == 'ConfSave')
			$cnf->updVar(Config::ADMPW, $gui->encrypt($c));

		$gui->putQBox('Administrator password',
				'<input name="ConfPwd" type="password" size="20" maxlength="30" value="'.$c.'" />',
				'Please enter new <strong>sync&bull;gw</strong> administrator password.', false);

		return true;
	}

	/**
	 * 	Configure PHP error logging
	 *
	 * 	@param	- Action to perform
	 * 	@return	- true = Ok; false = Error
	 */
	private function _phperr(string $action): bool {

		$gui = guiHandler::getInstance();
		$cnf = Config::getInstance();

		$yn = [
				'Yes'	=> 'Y',
				'No'	=> 'N',
		];

		if(($c = $gui->getVar('ConfPHPError')) === null)
			$c = $cnf->getVar(Config::PHPERROR);

		if ($action == 'ConfSave')
			$cnf->updVar(Config::PHPERROR, $c);

		$f = '<select name="ConfPHPError">';
		foreach ($yn as $k => $v) {

			$s = $v == $c ? 'selected="selected"' : '';
			$f .= '<option '.$s.' value="'.$v.'">'.$k.'</option>';
		}
		$f .= '</select>';

		$gui->putQBox('Capture PHP error', $f,
			 		   'By default <strong>sync&bull;gw</strong> is able to catch all PHP warning and notices. Setting this option to '.
						'<strong>Yes</strong> enables <strong>sync&bull;gw</strong> additionally to capture all PHP fatal errors '.
						'in the log file specified above. Please note <strong>sync&bull;gw</strong> will override locally some PHP.ini '.
						'settings.', false);

		return true;
	}

	/**
	 * 	Configure cron job
	 *
	 * 	@param	- Action to perform
	 * 	@return	- true = Ok; false = Error
	 */
	private function _cron(string $action): bool {

		$gui = guiHandler::getInstance();
		$cnf = Config::getInstance();

		$yn = [
				'Yes'	=> 'Y',
				'No'	=> 'N',
		];

		if(($c = $gui->getVar('ConfCron')) === null)
			$c = $cnf->getVar(Config::CRONJOB);

		if ($action == 'ConfSave')
			$cnf->updVar(Config::CRONJOB, $c);

		$f = '<select name="ConfCron">';
		foreach ($yn as $k => $v) {

			$s = $v == $c ? 'selected="selected"' : '';
			$f .= '<option '.$s.' value="'.$v.'">'.$k.'</option>';
		}
		$f .= '</select>';

		$gui->putQBox('Use CRON job', $f,
			 		   'By default <strong>sync&bull;gw</strong> is handling record expiration internally. This solution may have '.
			 		   	 'impact on synchronization performance. We recommend setup your own '.
			 		   	 '<a href="https://en.wikipedia.org/wiki/Cron" target="_blank">CRON</a> job. '.
			 		   	 'For this purpose please call <strong>sync.php?cleanup</strong> at least every hour. '.
			 		   	 'If you\'re using PLESK, you may call <strong>sync.php</strong> as script with parameter <strong>cleanup</strong>.', false);

		return true;
	}

	/**
	 * 	Configure log file
	 *
	 * 	@param	- Action to perform
	 * 	@return	- true = Ok; false = Error
	 */
	private function _logfile(string $action): bool {

		$gui = guiHandler::getInstance();
		$cnf = Config::getInstance();
		$rc = true;

		if(($val = $gui->getVar('ConfLogFile')) === null)
			$val = $cnf->getVar(Config::LOG_DEST);

		if ($action == 'ConfSave' && $val) {

			// be sure to convert path seperators
			$val = str_replace('\\', '/', $val);

			if (strtolower($val) != 'syslog' && strtolower($val) != 'off') {

				if (!@is_dir(dirname($val))) {

					$gui->putMsg(sprintf('Error accessing log file directory [%s]', dirname($val)), Config::CSS_ERR);
					$rc = false;
				}
			}
			if ($rc)
				$cnf->updVar(Config::LOG_DEST, $val);
		}

		$gui->putQBox('Logging destination',
					  '<input name="ConfLogFile" type="text" size="47" maxlength="250" value="'.$val.'" />',
					  'Specify where to store error, warning and informational messages.<br/><br/>'.
						'<strong>Off</strong><br/>Turn off any logging.<br/><br/>'.
						'<strong>SysLog</strong><br/>Msg messages to system log file.<br/><br/>'.
						'<strong>&lt;name&gt;</strong><br/>create log messages to file. You may specify either a '.
						'relative file name prefix (e.g. "../logs/syncgw-log") or an absolute path (e.g. "/var/logs/syncgw")', false);

		if(($lvl = $gui->getVar('ConfLogLvl')) === null)
			$lvl = $cnf->getVar(Config::LOG_LVL);

		if ($action == 'ConfSave') {

		    $l = 0;
		    $s = false;
		    foreach (Log::MSG_TYP as $k => $v) {

		    	if ($gui->getVar('ConLLogLvl'.$k) !== null) {

					$l |= $k;
					$s = true;
		    	}
		    }
		    if ($s)
			    $cnf->updVar(Config::LOG_LVL, $l);
		}

		$f = '';
		foreach (Log::MSG_TYP as $k => $v) {

			$s = (intval($lvl) & $k) ? 'checked="checked"' : '';
			$f .= '<div style="width: 120px; float: left;">'.
					'<input name="ConLLogLvl'.$k.'" type="checkbox" '.$s.' value="'.$k.'" /> '.$v.'</div>';
		}

		$gui->putQBox('Logging level', $f,
					  '<strong>sync&bull;gw</strong> server may write errors, warnings and other messages to log file. Depending '.
						'on your setting, your log '.
						'file will use more or less <a class="sgwA" href="http://www.logwatch.org/" target="_blank">disk space</a>.<br/><br/>'.
						'<strong>Error</strong><br/>'.
						'Show errors which <strong>sync&bull;gw</strong> either cannot handle or were unexpected (will always be logged).'.
						'<br/><br/>'.
						'<strong>Warn</strong><br/>Show additional warnings which <strong>sync&bull;gw</strong> can cover.<br/><br/>'.
						'<strong>Info</strong><br/>Show additional informational messages.<br/><br/>'.
						'<strong>Application</strong><br/>Additional application processing messages.<br><br>'.
					    '<strong>Debug</strong><br>More detailed processing messages.', false);

		if(($exp = $gui->getVar('ConfLogExpiration')) === null)
			$exp = $cnf->getVar(Config::LOG_EXP);

		if ($action == 'ConfSave') {

			if (!is_numeric($exp)) {

				$gui->putMsg(sprintf('Invalid value \'%s\' for log file expiration', $exp), Config::CSS_ERR);
				$rc = false;
			} else
				$cnf->updVar(Config::LOG_EXP, $exp);
		}

		$gui->putQBox('Log file expiration',
				 	  '<input name="ConfLogExpiration" type="text" size="5" maxlength="10" value="'.$exp.'" />',
					  'Specify how many log files should be kept before the eldest file will be deleted.', false);

		return $rc;
	}

	/**
	 * 	Configure debug user
	 *
	 * 	@param	- Action to perform
	 * 	@return	- true = Ok; false = Error
	 */
	private function _debug(string $action): bool {

		$gui = guiHandler::getInstance();
		$cnf = Config::getInstance();

		if(($uid = $gui->getVar('ConfDbgUsr')) === null)
			$uid = $cnf->getVar(Config::DBG_USR);

		$gui->putQBox('Debug user',
					  '<input name="ConfDbgUsr" type="text" size="20" maxlength="30" value="'.$uid.'" />',
					  'This user is used to access the internal and external data bases (e.g. during debugging traces). '.
					    'Debug user must be authorized to access internal and external data base. To disable debugging, please '.
						'leave this field empty. Please note, a couple of additional functions in "Explore data" panel will only be '.
						'available, if you have specified a debug user.', false);

		if(($upw = $gui->getVar('ConfDbgUpw')) === null)
			$upw = $cnf->getVar(Config::DBG_UPW);

		$gui->putQBox('Debug user password',
					  '<input name="ConfDbgUpw" type="password" size="20" maxlength="30" value="'.$upw.'" />',
					  'Password for debug user.', false);

		if ($action == 'ConfSave') {

			// user id set?
			if (!$uid)
				return true;

			// password set?
			if (!strlen($upw)) {

				$gui->putMsg('Password for debug user not set', Config::CSS_ERR);
				return false;
			}

			// authorize debug user
			$usr = User::getInstance();
			if (!$usr->Login($uid, $upw)) {

				$gui->putMsg('Unable to authorize debug user', Config::CSS_ERR);
				return false;
			}

			// save data
			$cnf->updVar(Config::DBG_USR, $uid);
			$cnf->updVar(Config::DBG_UPW, $upw);

		}

		return true;
	}

	/**
	 * 	Configure trace
	 *
	 * 	@param	- Action to perform
	 * 	@return	- true = Ok; false = Error
	 */
	private function _trace(string $action): bool {

		$gui = guiHandler::getInstance();
		$cnf = Config::getInstance();
		$rc  = true;

		if (!$cnf->getVar(Config::DATABASE))
			return true;

		if(($c = $gui->getVar('ConfTraceMode')) === null)
			$c = $cnf->getVar(Config::TRACE_CONF, true);

		if ($action == 'ConfSave')
			$cnf->updVar(Config::TRACE_CONF, strtolower($c) == 'on' ? 'On' :
						(strtolower($c) == 'off' ? 'Off' : $c));

		$gui->putQBox('Trace',
						'<input name="ConfTraceMode" type="text" size="20" maxlength="255" value="'.$c.'" />',
						'Trace data is used to enable debugging of any misbehavior of <strong>sync&bull;gw</strong> server. If you encounter '.
						'any problems, we need such a trace to analyze the situation. Available options:<br><br>'.
						'<strong>On</strong><br>'.
						'Activate tracing for all users.<br><br>'.
						'<strong>IP</strong><br>'.
						'Enable tracing for specific IP address.<br><br>'.
						'<strong>User name</strong><br>'.
						'Enable tracing only for specific user name.<br><br>'.
						'<strong>Off</strong><br>'.
						'Disable tracing for all users.', false);

		if(($c = $gui->getVar('ConfTraceDir')) === null)
			$c = $cnf->getVar(Config::TRACE_DIR);

		if ($action == 'ConfSave') {

			if ($c) {

				$c = realpath($c);
				if (!$c || !@is_dir($c) || !@is_writeable($c)) {

					$gui->putMsg(sprintf('Error accessing trace directory [%s]', $c), Config::CSS_ERR);
					$rc = false;
				} else {

					if (substr($c, -1) != '/')
						$c .= '/';
					$cnf->updVar(Config::TRACE_DIR, $c);
				}
			}
		}

		$gui->putQBox('Trace directory',
					  '<input name="ConfTraceDir" type="text" size="47" maxlength="250" value="'.$c.'" />',
					  'Specify where to store trace files. You may specify either a '.
						'relative directory name prefix (e.g. "../traces") or an absolute path (e.g. "/var/traces")<br>', false);

		if(($c = $gui->getVar('ConfTraceExpiration')) === null)
			$c = $cnf->getVar(Config::TRACE_EXP);

		if ($action == 'ConfSave') {

			if (!is_numeric($c)) {

				$gui->putMsg(sprintf('Invalid value \'%s\' for trace file expiration', $c), Config::CSS_ERR);
				$rc = false;
			} else
				$cnf->updVar(Config::TRACE_EXP, $c);
		}

		$gui->putQBox('Trace file expiration (in hours)',
					  '<input name="ConfTraceExpiration" type="text" size="5" maxlength="10" value="'.$c.'" />',
					  'After the given number of hours <strong>sync&bull;gw</strong> automatically removes expired trace files from '.
					    'trace directory. If you want to disable automatic file deletion, please enter a value of <strong>0</strong>.', false);

		return $rc;
	}

	/**
	 * 	Configure session
	 *
	 * 	@param	- Action to perform
	 * 	@return	- true = Ok; false = Error
	 */
	private function _session(string $action): bool {

		$gui = guiHandler::getInstance();
		$cnf = Config::getInstance();
		$rc = true;

		if (($c = $gui->getVar('ConfSessionMax')) === null)
			$c = $cnf->getVar(Config::SESSION_TIMEOUT);

		if ($action == 'ConfSave') {

			if (!is_numeric($c) || $c < 1) {

				$gui->putMsg(sprintf('Invalid value \'%s\' for session timeout - minimum of 1 seconds required', $c), Config::CSS_ERR);
				$rc = false;
			} else
				$cnf->updVar(Config::SESSION_TIMEOUT, $c);
		}

		$gui->putQBox('Session timeout (in seconds)',
					  '<input name="ConfSessionMax" type="text" size="5" maxlength="10" value="'.$c.'" />',
					  'Session between devices and <strong>sync&bull;gw</strong> server requires exchange of multiple packages '.
						'send over connection. '.
						'Each package depends on the previous package. During this operation data has to be temporary saved ensuring '.
						'synchronization integrity across the session. Depending on the performance of the connection and processing power '.
						'of server or device, delay between packages exchanged may vary. This parameter specifies how many seconds '.
						'between different session <strong>sync&bull;gw</strong> server should keep session data active.', false);

		if(($c = $gui->getVar('ConfSessionExp')) === null)
			$c = $cnf->getVar(Config::SESSION_EXP);

		if ($action == 'ConfSave') {

			if (!is_numeric($c)) {

				$gui->putMsg(sprintf('Invalid value \'%s\' for record expiration', $c), Config::CSS_ERR);
				$rc = false;
			} else
				$cnf->updVar(Config::SESSION_EXP, $c);
		}

		$gui->putQBox('Session record expiration (in hours)',
					  '<input name="ConfExpiration" type="text" size="5" maxlength="10" value="'.$c.'" />',
					  '<strong>sync&bull;gw</strong> stores record for managing synchronization sessions records in internal data stores. '.
					    'After the given number of hours <strong>sync&bull;gw</strong> automatically removes records expired from '.
					    'internal data stores. If you want to disable automatic record deletion, please enter a value of <strong>0</strong>.', false);

					  return $rc;
	}

	/**
	 * 	Configure max. object size
	 *
	 * 	@param	- Action to perform
	 * 	@return	- true = Ok; false = Error
	 */
	private function _objsize(string $action): bool {

		$gui = guiHandler::getInstance();
		$cnf = Config::getInstance();
		$rc = true;

		if(($c = $gui->getVar('ConfMaxObj')) === null)
			$c = self::_cnvBytes2String($cnf->getVar(Config::MAXOBJSIZE));

		if ($action == 'ConfSave') {

			if ($c = self::_cnvString2Bytes($c)) {

			    $rc = true;
				if ($c < 1024) {

					$gui->putMsg(sprintf('Maximum object size - %d bytes is too small', $c), Config::CSS_ERR);
					$rc = false;
				}
				if ($rc)
					$cnf->updVar(Config::MAXOBJSIZE, $c);
			}
		}

		if (is_numeric($c))
			$c = self::_cnvBytes2String($c);

		$gui->putQBox('Maximum object size in bytes for DAV synchronization',
						'<input name="ConfMaxObj" type="text" size="20" maxlength="20" value="'.$c.'" />',
						'This is the maximum size object <strong>sync&bull;gw</strong> server accepts (in bytes, "KB", "MB" or "GB") '.
						'for DAV synchronization.<br><br>'.
						'Please note the size is limited by two factors:<br>'.
						'<ul><li>The PHP <a class="sgwA" href="http://php.net/manual/en/ini.core.php" '.
						'target="_blank">maximum excution time</a>.</li>'.
						'<li>The PHP <a class="sgwA" href="http://php.net/manual/en/ini.core.php" target="_blank">memory_limit</a> size.'.
						'</li></ul>'.
						'We highly recommend if you want to use a bigger value, you should make some testing before taking over '.
						'value over to production system.<br><br>'.
						'Default: 1000 KB', false);
		return $rc;
	}

	/**
	 * 	Configure ActiveSync heartbeat
	 *
	 * 	@param	- Action to perform
	 * 	@return	- true = Ok; false = Error
	 */
	private function _heartbeat(string $action): bool {

		$gui = guiHandler::getInstance();
		$cnf = Config::getInstance();
		$rc = true;

		if (($hb = $gui->getVar('ConfHeartBeat')) === null)
			$hb = $cnf->getVar(Config::HEARTBEAT);
		if (($sw = $gui->getVar('ConfPingSleep')) === null)
			$sw = $cnf->getVar(Config::PING_SLEEP);

		if ($action == 'ConfSave') {

			if (!is_numeric($hb) || $hb < 10) {

				$gui->putMsg(sprintf('Invalid value \'%s\' for heartbeat - minimum of 10 seconds required', $hb), Config::CSS_ERR);
				$rc = false;
			} else
				$cnf->updVar(Config::HEARTBEAT, $hb);
			if (!is_numeric($sw) || $sw > $hb) {

				$gui->putMsg(sprintf('Invalid value \'%s\' for sleep time - it cannot be larger than heartbeat windows', $sw), Config::CSS_ERR);
				$rc = false;
			} else
				$cnf->updVar(Config::PING_SLEEP, $sw);
		}

		$gui->putQBox('ActiveSync Heartbeat window (in seconds)',
					  '<input name="ConfHeartBeat" type="text" size="5" maxlength="10" value="'.$hb.'" />',
					  'Using ActiveSync protocol, devices send a request to <strong>sync&bull;gw</strong> server '.
					    'asking server to check for changes on server. If a change is recognized in this time window, device '.
					    'is notified immediately. If no changes could be notified, <strong>sync&bull;gw</strong> server will send '.
					    'a notification after the heartbeat has expired. '.
                        'You can override the heartbeat client suggests to lower traffic between server and device. '.
					    'This parameter specifies how many seconds <strong>sync&bull;gw</strong> server will check for changes before '.
					    'client is notified nothing has changed.', false);

		$gui->putQBox('ActiveSync Sleep time (in seconds)',
					  '<input name="ConfPingWin" type="text" size="5" maxlength="10" value="'.$sw.'" />',
					  'Within the heartbeat window, <strong>sync&bull;gw</strong> will not constantly check for changes. '.
					    'This parameter specifies how many seconds <strong>sync&bull;gw</strong> will sleep '.
					    'before checking for changes.', false);

		return $rc;
	}

   	/**
	 * 	Convert a number to human readable format
	 *
	 * 	@param	- Value to convert
	 * 	@return	- Display string
	 */
	private function _cnvBytes2String(int $val): string {
	    static $_fmt = [ '', 'KB', 'MB', 'GB' ];

		$o = 0;
		while ($val >= 1023) {

			$o++;
			$val = $val / 1024;
		}
		return number_format($val, 0, ',', '.').' '.$_fmt[$o];
	}

	/**
	 * 	Convert a human readable string to a number
	 *
	 * 	@param	- Value to convert
	 * 	@return	- Display string
	 */
	private function _cnvString2Bytes(string $val): int {

		$val = str_replace('.', '', $val);
		if (($p = stripos($val, 'K')))
			return intval(trim(substr($val, 0, $p)) * 1024);
		if (($p = stripos($val, 'M')))
			return intval(trim(substr($val, 0, $p)) * 1048576);
		if (($p = stripos($val, 'G')))
			return intval(trim(substr($val, 0, $p)) * 1073741824);

		return $val;
	}

}
