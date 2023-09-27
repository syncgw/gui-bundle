<?php
declare(strict_types=1);

/*
 * 	View or debug trace
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\gui;

use syncgw\lib\Attachment;
use syncgw\lib\Config;
use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\Device;
use syncgw\lib\HTTP;
use syncgw\lib\Log;
use syncgw\lib\Msg;
use syncgw\lib\Server;
use syncgw\lib\Trace;
use syncgw\lib\User;
use syncgw\lib\Util;
use syncgw\lib\XML;
use syncgw\activesync\masFolderType;
use syncgw\document\field\fldAttribute;
use syncgw\document\field\fldRelated;

class guiTrace {

	const T_SERVER		= 'TServer';	// HTTP $_SERVER in trace file
	const T_HEADER 		= 'THeader';	// HTTP header in trace file
	const T_BODY   		= 'TBody';    	// BODY data in trace file
	const N_HEADER 		= 'NHeader';	// new HTTP header
	const N_BODY   		= 'NBody';    	// new BODY data

	const CUR_CONF 		= 'CurConfig';	// current configuration
	const TRC_RECS		= 'TrcRecs';	// trace records
	const TRC_MAP		= 'TrcMap';		// record mapping table (old -> new)
	const TRC_MAX		= 'TrcMax';		// max. # of records
	const TRC_CONF 		= 'TrcConfig';	// trace configuration
	const TRC_PATH 		= 'TrcPath';	// path to trace file
	const TRC_TIME 		= 'TrcTime';	// trace file time

	// message excluded from comparison
	const EXCLUDE  		= [

 			// header
 			'user:',
			'date:',
			'content-length:',
			'x-starttime:',
			'set-cookie:',
			'x-requestid:',

            // SabreDAV
 	        'd:href',
			'd:displayname',
            ':getetag',
	        ':getctag',
    		':sync-token',
    		'related-to:',
    		'etag:',

    		// ActiveSync
            'action',
			'<policykey>',
			'<accountid>',
			'<primarysmtpaddress>',
			'<related>',
			'<lastmodifieddate>',
			'<emailaddress>',
			'<deploymentid>',
			'<legacydn>',
			'<to>',

			// MAPI
			'<dn',

            // vCal, vCard
            'last-modified:',
			'created:',
    		'uid:',
    		'rev:',
			// Roundcube - Calendar
            // sequence may vary during debugging - this is clandar plugin specific - database_driver:php:_insert_event()
 	];

	// funcions/classes to exclude from debugging
	const EXCLUDE_DBG 	= [
		'syncgw\\lib\\XML',
		'syncgw\\lib\\WBXML',
		'syncgw\\lib\\Encoding',
		'syncgw\\lib\\Config',
		'syncgw\\interface\\mysql\\Handler',
		'syncgw\\interface\\roundcube\\Handler',
		'syncgw\\interface\\roundcube\\Contact',
		'syncgw\\interface\\roundcube\\Calendar',
		'syncgw\\interface\\roundcube\\Task',
		'syncgw\\interface\\roundcube\\Note',
		'syncgw\\interface\\mail\\Handler',
		'syncgw\\lib\\Attachment::getVar',
		'syncgw\\lib\\Log:LogMsg',
		'syncgw\\lib\\Session::updSessVar',
		'syncgw\\lib\\Trace',
		'syncgw\\lib\\Server:shutDown',
		'syncgw\\lib\\HTTP',
	];

	/**
     *  Trace control file
     *  @var array
     */
	public $_ctl;

    /**
     * 	Singleton instance of object
     * 	@var guiTrace
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiTrace {

	   	if (!self::$_obj)
            self::$_obj = new self();

		return self::$_obj;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
     *	@param 	- true = Provide status information only (if available)
	 */
	public function getInfo(XML &$xml, bool $status): void {

		$xml->addVar('Opt', 'View or debug trace plugin');
	}

	/**
	 * 	Perform action
	 *
	 * 	@param	- Action to perform
	 * 	@return	- guiHandler status code
	 */
	public function Action(string $action): string {

		$gui  = guiHandler::getInstance();
		$hid  = intval($gui->getVar('ExpHID'));
		$gid  = $gui->getVar('ExpGID');
		$http = HTTP::getInstance();
		$cnf  = Config::getInstance();

		$this->_ctl 				= [];
        $this->_ctl[self::N_BODY]   = null;
		$this->_ctl[self::N_HEADER] = [];

		switch ($action) {
		case 'ExpTraceDebug':
		case 'ExpTraceShow':

			$gui->putMsg(sprintf('Starting processing of trace [%s]', $gid), Config::CSS_TITLE);
			$gui->putMsg('');

			// load trace control file
			if (!self::_loadTrace($gid))
				break;

			// delete trace record?
			if ($idx = $gui->getVar('DelTraceRec'))
				self::_delRec($gid, intval($idx));

			$gui->putHidden('DelTraceRec', '0');

			// load and show trace configuration
			if (!self::_loadConfig($gid))
				break;

			// show trace record only
			if ($action == 'ExpTraceShow') {

				// exclude from debugging
				$cnf->updVar(Config::DBG_EXCL, self::EXCLUDE_DBG);
		    	// enable debug messages
				$cnf->updVar(Config::DBG_LEVEL, Config::DBG_VIEW);

				// process all trace records
			    for($idx=1; $idx <= $this->_ctl[self::TRC_MAX]; $idx++) {

	    			if (isset($this->_ctl[self::TRC_RECS][$idx]))
		    			self::_showRec(true, $action, $idx, $this->_ctl[self::TRC_RECS][$idx]);
			    }
			    break;
			}

			// set configuration
	    	foreach ($this->_ctl[self::TRC_CONF] as $k => $v)
	    		if ($k != 'Loaded')
	    	   		$cnf->updVar($k, $v);

	   		// cleanup records
			if (!self::_cleanRecs($gid))
				break;

	    	// restart server
			$srv = Server::getInstance();
			$srv->shutDown();

			$srv  = Server::getInstance();
			$cnf  = Config::getInstance();
			$gui  = guiHandler::getInstance();
			$http = HTTP::getInstance();
			$trc  = Trace::getInstance();

			// process all trace records
		    for($idx=1; $idx <= $this->_ctl[self::TRC_MAX]; $idx++) {

		    	if (!isset($this->_ctl[self::TRC_RECS][$idx]))
		    		continue;

   				// disable some debug messages
				$cnf->updVar(Config::DBG_EXCL, self::EXCLUDE_DBG);
		    	// enable debug messages
				$cnf->updVar(Config::DBG_LEVEL, Config::DBG_TRACE);
				$cnf->updVar(Config::DBG_INCL, [
					'chkTrcReferences',
					'syncgw\mapi\mapiHTTP',
				]);

		    	// show trace record
		    	self::_showRec(false, $action, $idx, $this->_ctl[self::TRC_RECS][$idx]);

	    		if ($this->_ctl[self::TRC_RECS][$idx][0] != Trace::RCV)
	    			continue;

				// is trace forced?
	    		if ($gui->getVar('ForceTrace')) {

					$cnf->updVar(Config::TRACE, Config::TRACE_FORCE);

	    			// special hack to catch received server environment and data
	    			$trc->Start($http->getHTTPVar(HTTP::SERVER), $this->_ctl[self::TRC_RECS][$idx][3]);
	    		}

				// enable http reader
				$http->catchHTTP('readHTTP', $this);

				// check for record operations
				self::_chkRecs($action, $gid, $idx);

	    		// stop external logging
   				Log::getInstance()->delInstance();

          		// the magic place...
				// process data
				$srv->Process();

				// only allow warnings and error messages
				$cnf->updVar(Config::LOG_LVL, Log::ERR|Log::WARN);

				// save request buffer for MAPI debugging purpose
				$bdy = $http->getHTTPVar(HTTP::RCV_BODY);

				// shutdown server
				$srv->shutDown();

           		// restart server
				$srv  = Server::getInstance();
           		$cnf  = Config::getInstance();
           		$http = HTTP::getInstance();
           		$http->updHTTPVar(HTTP::RCV_BODY, null, $bdy);
				$trc  = Trace::getInstance();
		    }

		    // last record not send, but new record created?
		    if (!empty($this->_ctl[self::N_HEADER]) && !empty($this->_ctl[self::TRC_RECS][$idx]))
	    		self::_showHTTP($idx, false, gmdate('Y-m-d H:i:s', $this->_ctl[self::TRC_RECS][$idx][1] ?
	    						$this->_ctl[self::TRC_RECS][$idx][1] : time()).' ', HTTP::SND_HEAD);

	    	$gui->putMsg('<br/><hr>+++ End of trace');
		}

		// restore configuration
		if (isset($this->_ctl[self::CUR_CONF]))
			foreach ($this->_ctl[self::CUR_CONF] as $k => $v)
		    	if ($k != 'Loaded')
	    	   		$cnf->updVar($k, $v);

		// set debug status
		$cnf->updVar(Config::DBG_LEVEL, Config::DBG_OFF);

		// allow only during explorer call
		if (substr($a = $gui->getVar('Action'), 0, 3) == 'Exp' && substr($a, 6, 4) != 'Edit' && ($hid & DataStore::TRACE)) {

			$but = $gui->getVar('Button').$gui->mkButton('View', 'View internal record', 'ExpTraceShow');
			if ($hid & DataStore::TRACE && $cnf->getVar(Config::DBG_USR))
				$but .= $gui->mkButton('Debug',
						'Debug selected trace. All user references are redirected to debug user', 'ExpTraceDebug');
			$gui->setVal($but);
		}

		return guiHandler::CONT;
	}

	/**
	 * 	Load trace control data
	 *
	 * 	@param 	- Trace record <GUID>
	 * 	@return - true = Ok; false = Error
	 */
	private function _loadTrace(string $gid): bool {

		$gui = guiHandler::getInstance();
		$cnf = Config::getInstance();
		$trc = Trace::getInstance();

		// check for trace directory
		$path = $cnf->getVar(Config::TRACE_DIR);
		if (!is_dir($file = $path.$gid)) {

			$gui->putMsg(sprintf('Trace directory [%s] not found', $file), Config::CSS_ERR);
			return false;
		}

		// build trace control file
		$this->_ctl[self::TRC_MAP] = [];

		for ($idx=1; $r = $trc->Read($gid, $idx); $idx++) {

			if ($r[0] != Trace::ADD)
				$this->_ctl[self::TRC_RECS][$idx] = $r;
			else {

				// check if record is already loaded
				if (!isset($this->_ctl[self::TRC_MAP][$r[1]][$r[2]])) {
					$this->_ctl[self::TRC_MAP][$r[1]][$r[2]] = 0;
					$this->_ctl[self::TRC_RECS][$idx] = $r;
				}
			}
		}
		$this->_ctl[self::TRC_MAX] = $idx;

		// save trace information
		$this->_ctl[self::TRC_PATH] = $path.$gid;
		$this->_ctl[self::TRC_TIME] = filectime($path.$gid);

		return true;
	}

	/**
	 * 	Load and show trace configuration
	 *
	 * 	@param 	- Trace record <GUID>
	 * 	@return - true = Ok; false = Error
	 */
	private function _loadConfig(string $gid): bool {

	    $cnf = Config::getInstance();
		$gui = guiHandler::getInstance();

		// load configuration?
		if (!isset($this->_ctl[self::TRC_CONF]['Loaded'])) {

			// save some default settings
			$this->_ctl[self::TRC_CONF] = [
					'Loaded' 			=> 1,
					Config::TRACE		=> Config::TRACE_OFF,
					Config::TRACE_CONF 	=> 'Off',
					Config::LOG_DEST	=> 'Off',
					Config::CRONJOB		=> 'Y',
					Config::IMAP_HOST	=> -1,
					Config::IMAP_PORT	=> -1,
					Config::IMAP_ENC	=> -1,
					Config::IMAP_CERT	=> -1,
					Config::SMTP_HOST	=> -1,
					Config::SMTP_PORT	=> -1,
					Config::SMTP_AUTH	=> -1,
					Config::SMTP_ENC	=> -1,
			];
			$this->_ctl[self::CUR_CONF] = [
	            	Config::HANDLER			=> 'GUI',
			];

			// we need to find and save debug trace configuration
   		    for($idx=1; $idx < $this->_ctl[self::TRC_MAX]; $idx++ ) {

   		    	if (!isset($this->_ctl[self::TRC_RECS][$idx]) || $this->_ctl[self::TRC_RECS][$idx][0] != Trace::CONF)
   		    		continue;

				$out = '';
				foreach ($this->_ctl[self::TRC_RECS][$idx][1] as $kc => $v) {

				    // support old class names
					$k = 'syncgw\\lib\\'.$kc;
					if (!defined($k))
						$out .= '<code style="'.Config::CSS_WARN.'">'.
								sprintf('Ingnoring unknown parameter "%s" with value "%s"', $k, $v).'</code><br>';
					else {

						$out .= '<code style="'.Config::CSS_CODE.'">'.sprintf('Configuration parameter "%s" = "%s"', $kc, $v);
						$k 	  = constant($k);
						// special check  for mail parameter
						$org  = $cnf->getVar($k);
						if (isset($this->_ctl[self::TRC_CONF][$k]) && $this->_ctl[self::TRC_CONF][$k] == -1) {

							$out .= ' -- <code style="'.Config::CSS_WARN.'">'.sprintf('Updating to value "%s"', $org).'</code>';
							$this->_ctl[self::TRC_CONF][$k] =  $org;
						} elseif (!isset($this->_ctl[self::TRC_CONF][$k]) && $v != $org) {

							$out .= ' -- <code style="'.Config::CSS_WARN.'">'.sprintf('Updating from value "%s"', $org).'</code>';
							$this->_ctl[self::TRC_CONF][$k] =  $v;
						}
						if ($k == Config::DATABASE) {

							if ($v != $cnf->getVar(Config::DATABASE)) {

								$gui->putMsg(sprintf('Data base "%s" not active - cannot proceed', $v), Config::CSS_ERR);
								return false;
							}
						}
						$out .= '</code><br>';
					}
				}

				$gui->putQBox('<strong>sync&bull;gw</strong> trace file environment data', '', $out, false, 'Msg');
				$gui->putMsg('');
   		    }
	    }

	    return true;
	}

	/**
	 * 	Show trace record
	 *
	 *	@param  - true= Show data records only
	 *	@param 	- Running action
	 *	@param	- Trace record #
	 * 	@param 	- Trace record
	 */
	private function _showRec(bool $show, string $action, int $idx, array $rec): void {

		$gui  = guiHandler::getInstance();
		$http = HTTP::getInstance();
		$cnf  = Config::getInstance();

		// save time when trace was created
		$tme = gmdate('Y-m-d H:i:s', $this->_ctl[self::TRC_TIME]).' ';

		switch($rec[0]) {
		case Trace::TVER:
			// check supported trace version
			if ($rec[1] != Trace::TRACE_VER) {

				$gui->putMsg(sprintf('This version of <strong>sync&bull;gw</strong> does not support trace version \'%s\'',
							 $rec[1]), Config::CSS_ERR);
				return;
			}
			$gui->putMsg(sprintf('Trace created with <strong>sync&bull;gw</strong> trace version %s', $rec[1]));
			break;

		case Trace::PVER:
			$gui->putMsg(sprintf('<strong>sync&bull;gw</strong> running on PHP version %s', $rec[1]));
			break;

		case Trace::LOG:
            $rec[1] = str_replace("\r", '', $rec[1]);
			foreach (explode("\n", $rec[1]) as $r) {

      			$gui->putMsg('<code style="float:left;"><div style="width:423px;float:left;">'.
       						 '<code style="width:26px;display:inline-block">---</code>'.
       						 gmdate('Y-m-d H:i:s', intval(Util::unxTime(strval(substr($r, 0, 20))))).
       						 '</div><div>'.XML::cnvStr(strval(substr($r, 21))).'</div></code>');
			}
			break;

		case Trace::RCV:
			$msg = '<br/><hr>';
					$gui->mkButton('Del', 'Delete trace record',
										'document.getElementById(\'Action\').value=\''.$action.'\';'.
										'document.getElementById(\'DelTraceRec\').value=\''.$idx.'\';', false).'  ';
			$msg .= '<strong>Processing trace record '.sprintf('[R%03d]', $idx).'</strong>';
			$gui->putMsg($msg);

			$http->updHTTPVar(HTTP::SERVER, null, $this->_ctl[self::T_SERVER] = $rec[2]);
			$http->updHTTPVar(HTTP::RCV_BODY, null, $rec[3]);
			// clear handler
            $cnf->updVar(Config::HANDLER, '');
            $http->checkIn();

			// save formatted data
			$this->_ctl[self::T_HEADER] = $http->getHTTPVar(HTTP::RCV_HEAD);
			$this->_ctl[self::T_BODY]   = $http->getHTTPVar(HTTP::RCV_BODY);

			// show _SERVER data
			$out = '';
			foreach ($http->getHTTPVar(HTTP::SERVER) as $k => $v)
				$out .= '<code style="'.Config::CSS_CODE.'">'.XML::cnvStr(str_replace("\n", '', $k.': '.$v)).'</code><br>';
			$gui->putQBox('<code>'.$tme.'SERVER data (including received header', '', $out.'</code>', false, 'Msg');

		    // show received header and body
            self::_showHTTP($idx, $show, gmdate('Y-m-d H:i:s', $rec[1]).' ', HTTP::RCV_HEAD);
            break;

		case Trace::SND:
			// inject and convert HTTP data
			$http->updHTTPVar(HTTP::SERVER, null, $this->_ctl[self::T_SERVER]);

			$sh = $http->getHTTPVar(HTTP::SND_HEAD);
			$sb = $http->getHTTPVar(HTTP::SND_BODY);

			$http->updHTTPVar(HTTP::SND_HEAD, null, $rec[2]);
			$http->updHTTPVar(HTTP::SND_BODY, null, $rec[3]);
			$http->checkOut();

			// save formatted data
			$this->_ctl[self::T_HEADER] = $http->getHTTPVar(HTTP::SND_HEAD);
			$this->_ctl[self::T_BODY]   = $http->getHTTPVar(HTTP::SND_BODY);

			$http->updHTTPVar(HTTP::SND_HEAD, null, $sh);
			$http->updHTTPVar(HTTP::SND_BODY, null, $sb);

			// now we need to inject new output
			if (!$show) {

				$sh = $http->getHTTPVar(HTTP::SND_HEAD);
				$sb = $http->getHTTPVar(HTTP::SND_BODY);

				$http->updHTTPVar(HTTP::SND_HEAD, null, $this->_ctl[self::N_HEADER]);
				$http->updHTTPVar(HTTP::SND_BODY, null, $this->_ctl[self::N_BODY]);

				// clear handler
     			$cnf->updVar(Config::HANDLER, '');
				$http->checkOut();

				// save formatted data
				$this->_ctl[self::N_HEADER] = $http->getHTTPVar(HTTP::SND_HEAD);
				$this->_ctl[self::N_BODY]   = $http->getHTTPVar(HTTP::SND_BODY);

				$http->updHTTPVar(HTTP::SND_HEAD, null, $sh);
				$http->updHTTPVar(HTTP::SND_BODY, null, $sb);
			}

			// show send header and body (if available)
			self::_showHTTP($idx, $show, gmdate('Y-m-d H:i:s', $rec[1]).' ', HTTP::SND_HEAD);
			$this->_ctl[self::N_HEADER] = null;
			break;

		// data store record
		case Trace::ADD:
			if (!$show || $rec[1] & DataStore::ATTACHMENT)
				break;
			$xml = new XML();
			if ($rec[3])
				$xml->loadXML($rec[3]);
			$out = '<code style="'.Config::CSS_CODE.'">'.$xml->mkHTML();
			$hdr = '';
			$hdr = $gui->mkButton('Del', 'Delete trace record',
						  'document.getElementById(\'Action\').value=\''.$action.'\';'.
						  'document.getElementById(\'DelTraceRec\').value=\''.$idx.'\';', false).'  ';
			$id  = $xml->getVar($rec[1] & DataStore::EXT ? 'extID' : 'GUID');
			$gui->putQBox($hdr.'<code style="'.Config::CSS_CODE.'">[R'.sprintf('%03d', $idx).'] '.
						  ($rec[1] & DataStore::EXT ? 'External' : 'Internal').' record ['.$id.'] in datastore '.
						  Util::HID(Util::HID_ENAME, $rec[1], true), '', $out.'</code>', false, 'Msg');

		// configuration already loaded
		case Trace::CONF:
			break;

		default:
			$gui->putMsg(sprintf('Unknown trace record [R%s] type \'%s\'', $idx, $rec[0]), Config::CSS_ERR);
			break;
		}
	}

	/**
	 * 	HTTP output reader
	 *
	 * 	@param	- HTTP output header
	 *  @param  - HTTP Body string or XML object
	 * 	@return - true = Ok; false = Stop sending output
	 */
	public function readHTTP(array $header, $body): bool {

		$this->_ctl[self::N_HEADER] = $header;
		$this->_ctl[self::N_BODY]   = $body;

		return false;	// disable sending out data
	}

	/**
	 * 	Delete trace record
	 *
	 * 	@param 	- Trace record <GUID>
	 * 	@param	- Trace record number
	 */
	private function _delRec(string $trc_id, int $idx): void {

		// delete trace record
		unlink($this->_ctl[self::TRC_PATH].'/R'.$idx);
		unset($this->_ctl[self::TRC_RECS][$idx]);

		// check remaining records
		for ($idx++; $idx <= $this->_ctl[self::TRC_MAX]; $idx++) {

			if (!isset($this->_ctl[self::TRC_RECS][$idx]))
				continue;
			if ($this->_ctl[self::TRC_RECS][$idx][0] == Trace::RCV)
				break;
			if ($this->_ctl[self::TRC_RECS][$idx][0] == Trace::LOG || $this->_ctl[self::TRC_RECS][$idx][0] == Trace::SND) {

				unlink($this->_ctl[self::TRC_PATH].'/R'.$idx);
				unset($this->_ctl[self::TRC_RECS][$idx]);
			}
		}
	}

	/**
	 * 	Cleanup data records
	 *
	 * 	@param 	- Trace record <GUID>
	 * 	@return - true = Ok; false = Error
	 */
	private function _cleanRecs(string $trc_id): bool {

		$usr = User::getInstance();
		$cnf = Config::getInstance();
		$db  = DB::getInstance();
		$att = Attachment::getInstance();
		$gui = guiHandler::getInstance();

		// ------------------------------------------------------------------------------------------------------------------------------

		$gui->putMsg('Deleting internal and external data records in all data stores for debug user');

		$db->Query(DataStore::USER, DataStore::DEL, $uid = $cnf->getVar(Config::DBG_USR));
		$usr->updVar('GUID', '');

		// we need to make a real login here to enable access to database handler
		if (!$usr->Login($uid, $cnf->getVar(Config::DBG_UPW))) {

			$gui->putMsg('Unable to authorize debug user - debugging terminated', Config::CSS_ERR);
			return false;
		}

		// disable logging
		$lvl  = $cnf->updVar(Config::LOG_LVL, Config::CSS_ERR);
		$log  = Log::getInstance();
		$stat = $log->LogSuspend();

   	    // we only delete enabled data store to prevent RoundCube synchronization status to disappear
		foreach (Util::HID(Util::HID_ENAME, DataStore::DATASTORES|DataStore::SESSION) as $hid => $name) {

	    	// delete internal records
			if (count($recs = $db->getRIDS($hid))) {

				foreach ($recs as $gid => $typ)
					$db->Query($hid, DataStore::DEL, $gid);
			}

			if ($hid & DataStore::SYSTEM)
				continue;

      		// delete external records
			if (count($recs = $db->getRIDS(DataStore::EXT|$hid))) {

		    	foreach ($recs as $gid => $typ)

		    		// try to delete record even if it is read-only
					if (!$db->Query(DataStore::EXT|$hid, DataStore::DEL, $gid))
						// group is read-only, so we save group mapping (old -> new)
						if ($typ == DataStore::TYP_GROUP)
							$this->_ctl[self::TRC_MAP][DataStore::EXT|$hid][$gid] = $gid;
			}
		}
		$name; // disable Eclipse warnings
		$log->LogResume($stat);

		// ------------------------------------------------------------------------------------------------------------------------------

		$gui->putMsg('Restoring attachment records');

		foreach ($this->_ctl[self::TRC_RECS] as $rec) {

			if ($rec[0] == Trace::ADD && $rec[1] & DataStore::ATTACHMENT) {

		   		$att->_gid = $rec[2];
	   			$att->create($rec[5], $rec[3], $rec[4]);
				$db->Query(DataStore::ATTACHMENT, DataStore::UPD, $att);
				// we don't need to save id's in mapping table, since attachment GUIDs were unique
	    	}
		}

   	    $gui->putMsg('');

		$cnf->updVar(Config::LOG_LVL, $lvl);

   	    return true;
	}

	/**
	 * 	Process data store add/upd/del (only external records)
	 *
	 *	@param 	- Running action
	 * 	@param 	- Trace record <GUID>
	 * 	@param 	- Trace record #
	 * 	@return - true = Ok; false = Error
	 */
	private function _chkRecs(string $action, string $trc_id, int $idx): bool {

		// be aware we only expect external records!
		$db   = DB::getInstance();
		$gui  = guiHandler::getInstance();
		$xml  = new XML();
		$org  = $idx;
		$usr  = User::getInstance();
		$cnf  = Config::getInstance();
		$ngid = '';

		// we need to make a real login here to enable access to database handler
		$uid = $cnf->getVar(Config::DBG_USR);
		if (!$usr->Login($uid, $cnf->getVar(Config::DBG_UPW))) {

			$gui->putMsg('Unable to authorize debug user - debugging terminated', Config::CSS_ERR);
			return false;
		}

		// ------------------------------------------------------------------------------------------------------------------------------
		// check for external records

		$recid = [];

		while(++$idx < $this->_ctl[self::TRC_MAX]) {

			// skip log records and non-existing records
			if (!isset($this->_ctl[self::TRC_RECS][$idx]) || $this->_ctl[self::TRC_RECS][$idx][0] == Trace::LOG)
				continue;

			// HTTP received data?
			if ($this->_ctl[self::TRC_RECS][$idx][0] == Trace::RCV)
				break;

			// get handler
			$hid = $this->_ctl[self::TRC_RECS][$idx][1];

			// only external record actions
			if ($this->_ctl[self::TRC_RECS][$idx][0] != Trace::ADD || !($hid & DataStore::EXT))
				continue;

			// load record
			$xml->loadXML($this->_ctl[self::TRC_RECS][$idx][3]);
			$xrid = $xml->getVar('extID');

			// check for non-editable group records
			$a = $xml->getVar(fldAttribute::TAG);
			if (($typ = $xml->getVar('Type')) == DataStore::TYP_GROUP && !($a & fldAttribute::EDIT)) {

				// should we add to mapping table?
				if (!isset($this->_ctl[self::TRC_MAP][$hid][$xrid])) {

					foreach ($db->getRIDS($hid) as $rid => $unused) {

						$doc = $db->Query($hid, DataStore::RGID, $rid);
						if ($doc->getVar(fldAttribute::TAG) & $a) {

							// old -> new
							$this->_ctl[self::TRC_MAP][$hid][$xrid] = $doc->getVar('extID');
							break;
						}
					}
					$unused; // disable Eclipse warning
				}

				$out = '<code style="'.Config::CSS_CODE.'">'.$xml->mkHTML();
	       		$hdr = '';
				$hdr = $gui->mkButton('Del', 'Delete trace record',
							  'document.getElementById(\'Action\').value=\''.$action.'\';'.
							  'document.getElementById(\'DelTraceRec\').value=\''.$idx.'\';', false).'  ';
				$gui->putQBox($hdr.'<code style="'.Config::CSS_CODE.'">[R'.sprintf('%03d', $idx).'] Group in external datastore '.
							  Util::HID(Util::HID_ENAME, $hid).' is not editable - skipping', '', $out.'</code>', false, 'Msg');
				continue;
			}

			// if we cannot add, we assume synchronization is not enabled for this data store and we skip processing
			if ($ngid = $db->Query($hid, DataStore::ADD, $xml)) {

				// save new mapping (old -> new)
				$this->_ctl[self::TRC_MAP][$hid][$xrid] = $ngid;

				// new internal handler?
				if (!isset($this->_ctl[self::TRC_MAP][$hid & ~DataStore::EXT]))
					$this->_ctl[self::TRC_MAP][$hid & ~DataStore::EXT] = [];

				// save processed record id
				$recid[$hid][] = $ngid;
			}

			// update external record reference
			$out = '<code style="'.Config::CSS_CODE.'">'.$xml->mkHTML();
			$hdr = '';
			$hdr = $gui->mkButton('Del', 'Delete trace record',
						  'document.getElementById(\'Action\').value=\''.$action.'\';'.
						  'document.getElementById(\'DelTraceRec\').value=\''.$idx.'\';', false).'  ';
			$gui->putQBox($hdr.'<code style="'.Config::CSS_CODE.'">[R'.sprintf('%03d', $idx).'] 	 external '.
						  Util::HID(Util::HID_ENAME, $hid).' '.($typ == DataStore::TYP_GROUP ? 'group' : 'record').
						  ' ['.$xrid.($ngid ? ' -> '.$ngid : '').']',
						  '', $out.'</code>', false, 'Msg');
		}

		// allow external database handler to check every record
		foreach ($recid as $hid => $recs)
			$db->chkTrcReferences($hid, $recs, $this->_ctl[self::TRC_MAP]);

		// ------------------------------------------------------------------------------------------------------------------------------
		// process internal records

		$idx   = $org;
		$recid = [];

		while(++$idx < $this->_ctl[self::TRC_MAX]) {

			// skip log records and non-existing records
			if (!isset($this->_ctl[self::TRC_RECS][$idx]) || $this->_ctl[self::TRC_RECS][$idx][0] == Trace::LOG)
				continue;

			// HTTP received data?
			if ($this->_ctl[self::TRC_RECS][$idx][0] == Trace::RCV)
				break;

			// get handler
			$hid = $this->_ctl[self::TRC_RECS][$idx][1];

			// only internal record actions (without attachments)
			if ($this->_ctl[self::TRC_RECS][$idx][0] != Trace::ADD || ($hid & (DataStore::ATTACHMENT|DataStore::EXT)))
				continue;

			$xml->loadXML($this->_ctl[self::TRC_RECS][$idx][3]);
			$gid = $xml->getVar('GUID');

			if ($hid & DataStore::TASK) {

				if (($id = $xml->getVar(fldRelated::TAG)) &&
					$id != ($ngid = $this->_ctl[self::TRC_MAP][DataStore::TASK|DataStore::EXT][$id])) {

					if (gettype($ngid) == 'integer') {

						$ngid = strval($ngid);
						Msg::ErrMsg($this->_ctl[self::TRC_MAP][DataStore::TASK|DataStore::EXT],
								   'Assignment for record "'.$id.'" missing!');
					}

					$xml->setVal($ngid);

					Msg::InfoMsg('['.$gid.'] Updating reference field <Related> from ['.$id.'] to ['.$ngid.']');
				}
			}

			if ($hid & DataStore::DEVICE) {

				// change device name (for debugging purposes)
				if (strncmp($gid, Config::DBG_PREF, Config::DBG_PLEN))
				    $gid = Config::DBG_PREF.$gid;

	   		   	$xml->updVar('GUID', $gid);

				// set owner to debug user
				$xml->updVar('LUID', $usr->getVar('LUID'));

	          	// be sure to delete existing record
	            $db->Query($hid, DataStore::DEL, $gid);

				// update suspended session
				if ($s = $xml->getVar('Suspended'))
					if (strncmp($s, Config::DBG_PREF, Config::DBG_PLEN))
						$xml->setVal(Config::DBG_PREF.$s);

	            // disable saving of active device
				$dev = Device::getInstance();
				$dev->updVar('GUID', '');
	    	}

			// user record?
			if ($hid & DataStore::USER) {

				// update active device
				if (($d = $xml->getVar('ActiveDevice')) && strncmp($d, Config::DBG_PREF, Config::DBG_PLEN))
					$xml->setVal(Config::DBG_PREF.$d);

				// update all alternate devices
				if ($xml->xpath('//DeviceId/.')) {

					while ($d = $xml->getItem())
						if (strncmp($d, Config::DBG_PREF, Config::DBG_PLEN))
							$xml->setVal(Config::DBG_PREF.$d);
				}

				// update attachment names
			    $xml->xpath('//Attachments/Name');
			    while (($v = $xml->getItem()) !== null) {

			        if (substr($v, 0, Attachment::PLEN) == Attachment::PREF) {

			            list(, , $i) = explode(Attachment::SEP, $v);
			            $xml->setVal(Attachment::PREF.$usr->getVar('LUID').Attachment::SEP.$i);
			        }
			    }

			    // get debug user id
			    if (strpos($gid = $cnf->getVar(Config::DBG_USR), '@'))
			    	list($gid, ) = explode('@', $gid);

			    // update primary e-Mail
				$xml->updVar('EMailPrime', $gid.'@'.$cnf->getVar(Config::IMAP_HOST));

			    // change userid to debug user
				$xml->updVar('GUID', $gid);
				$xml->updVar('LUID', $usr->getVar('LUID'));

				// be sure to delete existing record
	            $db->Query($hid, DataStore::DEL, $gid);

	            // force reload
	            $usr->updVar('GUID', '');
			}

			if (!$db->Query($hid, DataStore::RGID, $gid)) {

				if (!($ngid = $db->Query($hid, DataStore::ADD, $xml))) {

					$gui->putMsg('+++ '.sprintf('Error adding record [%s] in internal data store %s', $gid,
					             		   Util::HID(Util::HID_ENAME, $hid, true)), Config::CSS_WARN);
				}

				// save processed record id
				$recid[$hid][] = $ngid;
			} elseif ($hid & DataStore::DATASTORES)
				// save record id
				$recid[$hid][] = $gid;

			$out = '<code style="'.Config::CSS_CODE.'">'.$xml->mkHTML();
			$hdr = '';
			$hdr = $gui->mkButton('Del', 'Delete trace record',
						  'document.getElementById(\'Action\').value=\''.$action.'\';'.
						  'document.getElementById(\'DelTraceRec\').value=\''.$idx.'\';', false).'  ';
			$gui->putQBox($hdr.'<code style="'.Config::CSS_CODE.'">[R'.sprintf('%03d', $idx).'] '.($ngid ? 'Adding' :
						  'Skipping').' internal '.Util::HID(Util::HID_ENAME, $hid, true).' record '.
						  '['.$gid.($ngid ? ' -> '.$ngid : ' already available').']',
						  '', $out.'</code>', false, 'Msg');
		}

		// allow external database handler to check every record
		foreach ($recid as $hid => $recs)
			$db->chkTrcReferences($hid, $recs, $this->_ctl[self::TRC_MAP]);

		return true;
	}

	/**
	 *  Show HTTP send/received header and body
	 *
	 *	@param 	- Trace record #
	 *	@param  - true= Show data records only
	 *  @param  - Time stamp
	 *  @param 	- HTTP data
	 */
	private function _showHTTP(int $idx, bool $show, string $tme, string $typ): void {

		$gui = guiHandler::getInstance();

		if ($show || $typ == HTTP::RCV_HEAD) {

			$out = '';
			foreach ($this->_ctl[self::T_HEADER] as $k => $v)
	        	$out .= '<code style="'.Config::CSS_CODE.'">'.XML::cnvStr($k.': '.$v).'</code><br>';
			$gui->putQBox('<code>'.$tme.'Header '.($typ == HTTP::RCV_HEAD ? 'received' : 'send'),
						  '', $out.'</code>', false, 'Msg');

			if (empty($wrk = $this->_ctl[self::T_BODY]))
				$gui->putMsg('<code style="width:26px;display:inline-block"> </code><code>'.$tme.'Body is empty</code>');
			else {

				// reload XML data to get nice formatting
			    if (is_object($wrk) || substr($wrk, 0, 2) == '<?') {

			    	if (!is_object($wrk)) {

						$xml = new XML();
						$xml->loadXML(str_replace([ 'xmlns=',  'xmlns:', ], [ 'xml-ns=', 'xml-ns:', ], $wrk));
						$wrk = $xml;
			    	}
			    	$wrk = self::_comment($wrk);
			    	$wrk = $wrk->saveXML(true, true);
			    }

				// convert to array
				$body = explode("\n", str_replace("\r", '', $wrk));
				array_pop($body);

				$out = '';
			    foreach ($body as $rec) {

			        $c = strpos($rec, '<!--') !== false ? Config::CSS_INFO : Config::CSS_CODE;
	    	        $out .= '<code style="'.$c.'">'.XML::cnvStr($rec).'</code><br>';
			    }
	            $gui->putQBox('<code>'.$tme.'Body', '', $out.'</code>', false, 'Msg');
	 		}
			return;
		}

		list($cnt, $arr) = Util::diffArray($this->_ctl[self::T_HEADER],
										   empty($this->_ctl[self::N_HEADER]) ? [] : $this->_ctl[self::N_HEADER],
										   self::EXCLUDE);
		if ($cnt)
        	$gui->putQBox('<code style="'.Config::CSS_ERR.'">'.$tme.'+++ '.
              			  sprintf('Header send (%d changes  "-" - stored in trace; "+" - newly created)', $cnt / 2),
                          '',  $arr.'</code>', false, 'Msg');
		else
        	$gui->putQBox('<code>'.$tme.'Header send (0 changes)', '', $arr.'</code>', false, 'Msg');

       	if (empty($this->_ctl[self::T_BODY]) && empty($this->_ctl[self::N_BODY])) {

			$gui->putMsg('<code style="width:26px;display:inline-block"> </code><code>'.$tme.'Body is empty</code>');
			return;
       	}

		// reload XML data to get nice formatting
		$bdy = [];
 	   	foreach ([ 'tbdy' => self::T_BODY, 'nbdy'=> self::N_BODY, ] as $k => $v) {

			$wrk = $this->_ctl[$v];
			// check for empty or xml version="1.0" encoding="UTF-8" string
			if (is_null($wrk) || strlen($wrk) == 39)
				$wrk = '';

			if (is_object($wrk) || substr($wrk, 0, 2) == '<?') {

				if (!is_object($wrk)) {

					$xml = new XML();
					$xml->loadXML(str_replace([ 'xmlns=',  'xmlns:', ], [ 'xml-ns=', 'xml-ns:', ], $wrk));
					$wrk = $xml;
				}
				$wrk = self::_comment($wrk);
	       		$wrk = $wrk->saveXML(true, true);
				$wrk = str_replace([ 'xml-ns=', 'xml-ns:' ], [ 'xmlns=', 'xmlns:' ], $wrk);
				// delete optional character set attribute
				$wrk = preg_replace('/(\sCHARSET)(=[\'"].*[\'"])/iU', '', $wrk);
				// remove DOCTYPE
				$wrk = preg_replace('/(.*)(<!.*">)(.*)\n/', '${1}${3}', $wrk);
			}

			// convert to array
	       	$bdy[$k] = explode("\n", str_replace("\r", '', strval($wrk)));
			array_pop($bdy[$k]);
       	}

		list($cnt, $arr) = Util::diffArray($bdy['tbdy'], $bdy['nbdy'], self::EXCLUDE);
        if ($cnt)
			$gui->putQBox('<code style="'.Config::CSS_ERR.'">'.$tme.'+++ '.
						  sprintf('Body send (%d changes  "-" - stored in trace; "+" - newly created)', $cnt / 2),
                          '',  $arr.'</code>', false, 'Msg');
        else
			$gui->putQBox('<code>'.$tme.'Body send (0 changes)', '', $arr.'</code>', false, 'Msg');

        $this->_ctl[self::N_BODY]   = null;
		$this->_ctl[self::N_HEADER] = [];
 	}

 	/**
 	 * 	Convert XML document
 	 *
 	 * 	@param 	- XML object to send
 	 */
 	private function _comment($xml) {

 		if (!is_object($xml))
 			return $xml;

		$tags = [
			    // Tag                   Path
				[ 'Autodiscover',		'Response/Action/Status'                              ],
				[ 'Sync', 				'Status'                                              ],
				[ 'Sync', 				'Collections/Collection/Status'                       ],
				[ 'Sync', 				'Collections/Collection/Responses/Add/Status'         ],
				[ 'Sync', 				'Collections/Collection/Responses/Change/Status'      ],
			    [ 'Sync', 				'Collections/Collection/Responses/Delete/Status'      ],
				[ 'Sync', 				'Collections/Collection/Responses/Fetch/Status'       ],
			    [ 'GetItemEstimate',	'Status'                                              ],
			    [ 'GetItemEstimate', 	'Response/Status'                                     ],
				[ 'FolderCreate', 		'Status'                                              ],
				[ 'FolderUpdate', 		'Status'                                              ],
				[ 'FolderSync', 		'Status'                                              ],
				[ 'Settings', 			'Status'                                              ],
				[ 'Settings', 			'Oof/Status'                                          ],
			    [ 'Settings', 			'Oof/Get/OofState'                                    ],
				[ 'Settings', 			'DeviceInformation/Status'                            ],
				[ 'Settings', 			'DevicePassword/Status'                               ],
				[ 'Settings', 			'UserInformation/Status'                              ],
				[ 'Settings', 			'RightsManagementInformation/Status'                  ],
				[ 'Provision', 		    'Status'                                              ],
				[ 'Provision', 		    'Policies/Policy/Status'                              ],
				[ 'Provision', 		    'DeviceInformation/Status'                            ],
				[ 'Provision', 		    'RemoteWipe/Status'                                   ],
				[ 'ValidateCert', 		'Status'                                              ],
				[ 'ValidateCert', 		'Certificate/Status'                                  ],
				[ 'Ping', 				'Status'                                              ],
				[ 'MoveItems', 		    'Response/Status'                                     ],
				[ 'SmartFormward', 	    'Status'                                              ],
				[ 'SmartReply', 		'Status'                                              ],
			    [ 'SendMail', 			'Status'                                              ],
				[ 'MeetingResponse', 	'Result/Status'                                       ],
				[ 'Search', 			'Status'                                              ],
				[ 'Search', 			'Response/Store/Status'                               ],
				[ 'Search', 			'Response/Result/Properties/Picture/Status'           ],
				[ 'ItemOperations', 	'Response/Fetch/Status'                               ],
			    [ 'ItemOperations', 	'Response/Move/Status'                                ],
				[ 'ItemOperations', 	'Response/EmptyFolderContent/Status'                  ],
				[ 'ItemOperations', 	'Status'                                              ],
				[ 'ResolveRecipients',  'Status'                                              ],
				[ 'ResolveRecipients',  'Response/Status'                                     ],
				[ 'ResolveRecipients',  'Response/Recipient/Availability/Status'              ],
				[ 'ResolveRecipients',  'Response/Recipient/Certificates/Status'              ],
				[ 'ResolveRecipients',  'Response/Recipient/Picture/Status'                   ],
			];
		foreach ($tags as $t) {

			$xml->xpath('//'.$t[0].($t[1] ? '/'.$t[1] : ''));
			while (($v = $xml->getItem()) !== null) {

				$c = 'syncgw\activesync\mas'.$t[0];
				$xml->addComment($c::status($t[1], $v));
			}
		}
		$xml->xpath('//FolderSync/*/*/Type');
		while (($v = $xml->getItem()) !== null)
			$xml->addComment(masFolderType::type($v));
		$xml->xpath('//FolderCreate/Type');
		while (($v = $xml->getItem()) !== null)
			$xml->addComment(masFolderType::type($v));

		return $xml;
 	}

}
