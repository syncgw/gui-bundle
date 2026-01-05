<?php
declare(strict_types=1);

/*
 *	Explore data stores
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\gui;

use syncgw\lib\Msg;
use syncgw\lib\Config;
use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\Util;
use syncgw\lib\XML;
use syncgw\document\field\fldGroupName;

class guiExplorer {

	// internal record types
	const PARENT_GROUP	   	= 'P';
	const CURRENT_GROUP	   	= 'C';

	// table definitions
	const SUB 			   	= 'Submit';
	const CMD_SHOWDS	   	= '-1';			// show data store selection
	const CMD_RECORD	   	= '0';			// select record
	const CMD_DS		   	= '1';			// select data store
	const CMD_GROUP		   	= '2';			// select group
	const CMD_RELOAD 	   	= '3';			// select reload
	const CMD_RETURN	   	= '4';			// return
	const HID 			   	= 'HID';		// Handler ID
	const GRP 			   	= 'GRP';		// group ID
	const ID 			   	= 'ID';			// GUID
	const NAM 	   		   	= 'Name';		// Name
	const TYP 			   	= 'Typ';		// Record type
	const STAT 			   	= 'Status';		// Record status
	const SIZE 			   	= 'Size';		// Size of record
	const LMOD 			   	= 'LastMod';	// Last modification date (or false)

 	/**
	 * 	Status translation table
	 * 	@var array
	 */
	private $_stat;

	/**
	 * 	Record type
	 * 	@var array
	 */
	private $_type;

	/**
	 * 	Row counter
	 * 	@var int
	 */
	private $_row;

	/**
	 * 	Active "GUID"
	 * 	@var string
	 */
	private $_gid = -1;

    /**
     * 	Singleton instance of object
     * 	@var guiExplorer
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiExplorer {

		if (!self::$_obj) {

            self::$_obj = new self();

			// status translation table
			self::$_obj->_stat = [
					DataStore::STAT_OK	     => 'Ok',
					DataStore::STAT_ADD	     => 'Add',
					DataStore::STAT_DEL	     => 'Delete',
					DataStore::STAT_REP	     => 'Replace',
			];

			// record types
			self::$_obj->_type = [
			        DataStore::TYP_GROUP	 => 'Group',
					DataStore::TYP_DATA		 => 'Record',
					self::PARENT_GROUP	     => 'Parent Group',
					self::CURRENT_GROUP      => 'Current Group',
			];
		}

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

		if ($action == 'Init') {

			if (!$gui->isConfigured())
				return guiHandler::CONT;
			$gui->putCmd('<input id="Explorer" '.($gui->getVar('LastCommand') == 'Explorer' ? 'checked ' : '').'type="radio" name="Command" '.
						 'value="Explorer" onclick="document.syncgw.submit();"/>&nbsp;'.
						 '<label for="Explorer">'.'Explore data'.'</label>');
			return guiHandler::CONT;
		}

		// anything for us?
		if (substr($action, 0, 3) != 'Exp')
			return guiHandler::CONT;

		// we're startig up explorer
		$gui->updVar('Action', $action);

		// get administrator status
		$adm = $gui->isAdmin();

		// set button
		$gui->setVal($gui->getVar('Button').$gui->mkButton(guiHandler::STOP));

		// active datastore handler ID
		if (!($hid = intval($gui->getVar('ExpHID'))))
			$hid = 0;
		// group ID
		$grp = $gui->getVar('ExpGRP');
		// active record GUID
		$gid = $gui->getVar('ExpGID');
		// command
		if (!($cmd = $gui->getVar('ExpCmd')))
			$cmd = self::CMD_SHOWDS;
		// selection level
		if ($lvl = $gui->getVar('ExpLvl'))
			$lvl = unserialize(base64_decode($lvl));

		// set row counter
		$this->_row = 0;

		// special root reload
		if ($cmd == self::CMD_RELOAD && !$hid)
			$cmd = self::CMD_SHOWDS;

		if ($cmd == self::CMD_RETURN)
			array_pop($lvl);

		if ($cmd != self::CMD_SHOWDS)
			list($last_hid, $last_grp, $path) = end($lvl);
		else {

			$path = '/';
			$lvl = [];
			$last_hid = 0;
			$last_grp = '';
		}

		// return to root level?
		if ($cmd == self::CMD_RETURN) {

			array_pop($lvl);
			if (!count($lvl))
				$cmd = self::CMD_SHOWDS;
			$hid = $last_hid;
			$grp = $last_grp;
		}

		if (Config::getInstance()->getVar(Config::DBG_LEVEL) == Config::DBG_GUI)
			Msg::InfoMsg($lvl, 'Action="'.$action.'" ExpCmd="'.$cmd.'" Hid="'.$hid.
								'" Group="'.$grp.'" GUID="'.$gid.'" Path="'.$path.'"');

		// save active GUID
		$this->_gid = $gid;

		// show directory structure header line
		$wrk = 	'<table class="sgwRec" width="100%">'.
				'<colgroup><col width="200px"><col width="100px"><col width="50px"><col width="50px"><col width="250px"></colgroup>'.
				'<tr><th align="left">Name</th><th align="left">Type</th><th align="left">Status</th>'.
				'<th align="left">Size</th><th align="left">Last modified</th></tr>'.
				'<tr><td colspan="5"><hr /></td></tr>';

		// list all available data stores
		if ($cmd == self::CMD_SHOWDS) {

			foreach (Util::HID(Util::HID_ENAME, DataStore::ALL) as $k => $v) {

				// check user status
				if (!$adm && !($k & DataStore::DATASTORES))
					continue;
				$wrk .= self::_mkRow([
							self::SUB	=> self::CMD_DS,
							self::HID	=> $k,
							self::ID 	=> '',
							self::GRP	=> '',
							self::NAM	=> $v,
							self::TYP	=> $this->_type[DataStore::TYP_GROUP],
							self::STAT	=> $this->_stat[DataStore::STAT_OK],
							self::SIZE	=> 0,
							self::LMOD	=> 0,
				]);
			}
		} else {

			// extend path for data store?
			if ($cmd == self::CMD_DS)
				$path .= Util::HID(Util::HID_ENAME, $hid).'/';

			// we need to log in to get access to external data records
			if ($hid & DataStore::DATASTORES) {

				if ($adm) {

					// any user set?
					if (!($uid = $_SESSION[$gui->getVar('SessionID')][guiHandler::UID])) {

						$gui->updVar('Action', '');
						$gui->putMsg('You need to select a user if you want to browse data stores', Config::CSS_WARN);
						return guiHandler::RESTART;
					}
					$gui->Login($uid = base64_decode($uid));
				} else
					$uid = '';
				// extend path
				if ($cmd == self::CMD_DS)
					$path .= $uid.'/';
			}

			// set return command
			$wrk .= self::_mkRow([
							self::SUB	=> self::CMD_RETURN,
							self::HID	=> $last_hid,
							self::ID 	=> '',
							self::GRP	=> $last_grp,
							self::NAM	=> '..',
							self::TYP	=> $this->_type[self::PARENT_GROUP],
							self::STAT	=> $this->_stat[DataStore::STAT_OK],
							self::SIZE	=> 0,
							self::LMOD	=> 0,
			]);
			$last_hid = $hid;
			$last_grp = $grp;

			// show our own directory
			if (($hid & DataStore::DATASTORES) && $grp) {

				$db = DB::getInstance();
				$r = [
							self::SUB	=> self::CMD_RECORD,
							self::HID	=> $hid,
							self::ID 	=> $grp,
							self::GRP	=> $grp,
							self::NAM	=> '.',
							self::TYP	=> $this->_type[self::CURRENT_GROUP],
				];
				if ($doc = $db->Query($hid, DataStore::RGID, $grp)) {

					$doc->getVar('Data');
					$r[self::SIZE] = strlen($doc->saveXML(false, false));
					$r[self::LMOD] = gmdate('c', intval($doc->getVar('LastMod')));
					$r[self::STAT] = $this->_stat[$doc->getVar('SyncStat')];
					if ($cmd == self::CMD_GROUP)
						$path .= $doc->getVar(fldGroupName::TAG).'/';
				} else {

					$r[self::SIZE] = 0;
					$r[self::LMOD] = 0;
					$r[self::STAT] = $this->_stat[DataStore::STAT_OK];
				}
				$wrk .= self::_mkRow($r);
				if (!$gid)
					$gid = $this->_gid;
			}

			// load data records from table
			foreach (self::_getTab($hid, $grp) as $v) {

				if (!$this->_gid)
					$gid = $this->_gid = $v[self::ID];
				$wrk .= self::_mkRow($v);
			}
		}

		// finalize output
		$gui->putCmd($wrk.'<tr><td colspan="5"><hr /></td></tr></table>');
		$gui->setVal($gui->getVar('Message').'&nbsp;&nbsp;'.(strlen($path) > 50 ? '... ' : '').substr($path, -50));

		// save information
		$gui->putHidden('ExpHID', strval($hid));
		$gui->updVar('ExpHID', strval($hid));
		$gui->putHidden('ExpGRP', strval($grp));
		$gui->updVar('ExpGRP', strval($grp));
		$gui->putHidden('ExpGID', strval($gid));
		$gui->updVar('ExpGID', strval($gid));
		$gui->putHidden('ExpCmd', self::CMD_RELOAD);
		$gui->updVar('ExpCmd', self::CMD_RELOAD);
		if ($cmd != self::CMD_RELOAD)
			$lvl[] = [ $hid, $grp, $path ];
		$gui->putHidden('ExpLvl', base64_encode(serialize($lvl)));

		if (Config::getInstance()->getVar(Config::DBG_LEVEL) == Config::DBG_GUI)
			Msg::InfoMsg($lvl, 'Action="'.$action.'" ExpCmd="'.$cmd.'" Hid="'.$hid.
									'" Group="'.$grp.'" GUID="'.$gid.'" Path="'.$path.'"');

		return guiHandler::CONT;
	}

	/**
	 * 	Get table content
	 *
	 * 	@param	- Handler ID
	 * 	@param	- Group ID
	 * 	@return	- Table array
	 */
	private function _getTab(int $hid, string $grp): array {

		$db  = DB::getInstance();
		$cnf = Config::getInstance();

		// output buffer
		$recs = [];

		// special load for traces
		if ($hid & DataStore::TRACE) {

			if (!($path = $cnf->getVar(Config::TRACE_DIR)) || !($d = @opendir($path)))
				return [];

			while (($file = @readdir($d)) !== false) {

				if ($file == '.' || $file == '..' || !is_dir($path.$file))
					continue;

				$recs[] = [
							self::SUB	=> self::CMD_RECORD,
							self::HID	=> $hid,
							self::GRP	=> '',
							self::ID 	=> $file,
							self::NAM	=> $file,
							self::TYP	=> $this->_type[DataStore::TYP_DATA],
							self::STAT	=> $this->_stat[DataStore::STAT_OK],
							self::SIZE	=> 'Unknown',
							self::LMOD	=> gmdate('c', intval(filectime($path.$file))),
				];
			}
			@closedir($d);

		} else {
			// load records
			foreach ($db->Query($hid, DataStore::RIDS, $grp) as $id => $typ) {

			    if ($hid & (DataStore::ATTACHMENT) && $typ == DataStore::TYP_DATA)
			        continue;

			    if (!($doc = $db->Query($hid, DataStore::RGID, $id))) {

	        		if ($cnf->getVar(Config::DBG_LEVEL) == Config::DBG_GUI)
			          	Msg::InfoMsg('+Error reading record "'.$id.'"');
			        break;
			    }

				// check if we're in group
				if ($grp != $doc->getVar('Group'))
					continue;

				// compute length of record
				if ($hid & DataStore::DATASTORES)
					$doc->getVar('Data');
				else
					$doc->getVar('syncgw');
				$len = strlen($doc->saveXML(false, false));

				if ($typ != DataStore::TYP_DATA) {

					if ($hid & DataStore::DATASTORES) {

						$doc->getVar('Data');
						$n = $doc->getVar(fldGroupName::TAG, false);
						if (!$n)
							$n = '++ MISSING +++';
					} else {

						$n = $id;
						$typ = DataStore::TYP_DATA;
					}
					$recs[] = [
								self::SUB	=> $hid & DataStore::SYSTEM ? self::CMD_RECORD : self::CMD_GROUP,
								self::HID	=> $hid,
								self::GRP	=> str_replace('\\', '\\\\', $hid & DataStore::SYSTEM ? '' : $id),
								self::ID 	=> str_replace('\\', '\\\\', $id),
								self::NAM	=> $n,
								self::TYP	=> $this->_type[$typ],
								self::STAT	=> $this->_stat[$doc->getVar('SyncStat')],
								self::SIZE	=> $len,
								self::LMOD	=> gmdate('c', intval($doc->getVar('LastMod'))),
					];
				} elseif ($hid & (DataStore::DATASTORES|DataStore::SYSTEM))
					$recs[] = [
								self::SUB	=> self::CMD_RECORD,
								self::HID	=> $hid,
								self::GRP	=> str_replace('\\', '\\\\', $grp),
								self::ID 	=> str_replace('\\', '\\\\', strval($id)),
								self::NAM	=> $id,
								self::TYP	=> $this->_type[$typ],
								self::STAT	=> $this->_stat[$hid & DataStore::DATASTORES ? $doc->getVar('SyncStat') : DataStore::STAT_OK],
								self::SIZE	=> $len,
								self::LMOD	=> gmdate('c', intval($doc->getVar('LastMod'))),
					];
			}
		}

		if ($hid & DataStore::TRACE)
			usort($recs, [ 'syncgw\\gui\\guiExplorer', '_sort1' ]);
		else
			usort($recs, [ 'syncgw\\gui\\guiExplorer', '_sort2' ]);

		return $recs;
	}

	/**
	 * 	Create table row
	 *
	 * 	@param  - Record []
	 *  @return - HTML code
	 */
	private function _mkRow(array $rec): string {

		if (Config::getInstance()->getVar(Config::DBG_LEVEL) == Config::DBG_GUI)
            Msg::InfoMsg($rec, 'Creating list record "'.$this->_row.'"');

		return '<tr id="ExpRow'.$this->_row.'" onclick="'.
				'sgwPick('.$rec[self::SUB].','.($this->_row++).','.$rec[self::HID].',\''.$rec[self::GRP].'\',\''.$rec[self::ID].'\');" '.
				($this->_gid && !strcmp(strval($rec[self::ID]), strval($this->_gid)) && $rec[self::NAM] != '..' ? 'style="background-color:#E6E6E6;"' : '').'>'.
				'<td><div '.($rec[self::SUB] == self::CMD_DS || $rec[self::SUB] == self::CMD_GROUP ? 'class="sgwLink"' : '').'>'.
				XML::cnvStr(strval($rec[self::NAM])).'</div></td>'.
				'<td>['.$rec[self::TYP].']</td>'.'<td>'.$rec[self::STAT].'</td><td>'.$rec[self::SIZE].'</td>'.
				'<td>'.$rec[self::LMOD].'</td></tr>';
	}

	/**
	 * 	Sort function (LastMod)
	 *
	 * 	@param  - $a
	 * 	@param  - $b
	 */
	static private function _sort1($a, $b): int {

		return strcmp($b[self::LMOD], $a[self::LMOD]);
	}

	/**
	 * 	Sort function (GUID)
	 *
	 * 	@param  - $a
	 * 	@param  - $b
	 */
	static private function _sort2($a, $b): int {

		return $a[self::ID] < $b[self::ID] ? -1 : 1;
	}

}
