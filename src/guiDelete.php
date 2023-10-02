<?php
declare(strict_types=1);

/*
 * 	Delete record
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\gui;

use syncgw\lib\Config;
use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\Util;

class guiDelete {

    /**
     * 	Singleton instance of object
     * 	@var guiDelete
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiDelete {

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

		$hid = intval($gui->getVar('ExpHID'));
		$gid = $gui->getVar('ExpGID');

		switch ($action) {
		case 'ExpDelRec':
			if ($hid & DataStore::USER)
				self::delUser($gid);
			elseif ($hid & DataStore::TRACE) {

				$cnf = Config::getInstance();
				$path = $cnf->getVar(Config::TRACE_DIR);
				Util::rmDir($path.$gid);
				// we need to ensure existing session record is deleted to avoid prolongation
				$db = DB::getInstance();
				foreach ([ 'MAS', 'DAV' ] as $pref)
					$db->Query(DataStore::SESSION, DataStore::DEL, $pref.'-'.$gid);
			} else {

				$db = DB::getInstance();
				$db->Query($hid, DataStore::DEL, $gid);
				$gui->updVar('ExpGID', '');
			}
			$gui->updVar('Action', 'Explorer');
			$gui->clearAjax();
			return guiHandler::RESTART;

		default:
			break;
		}

		// only during explorer call
		if (substr($a = $gui->getVar('Action'), 0, 3) == 'Exp' && substr($a, 6, 4) != 'Edit' && $gid) {

			$gui->setVal($gui->getVar('Button').
							$gui->mkButton('Delete', 'Delete selected internal record. In case you selected a user, '.
							'all related internal user records will be deleted (no external record will be deleted)',
							'var v=document.getElementById(\'ExpGID\');'.
							'var a=document.getElementById(\'Action\');'.
							'if (confirm(\''.'Do you really want to delete record >\' + v.value + \'< in this group?'.'\')) {'.
							'a.value=\'ExpDelRec\';'.
							'} else '.
							'a.value=\'Explorer\';'));
		}

		return guiHandler::CONT;
	}

	/**
	 * 	Delete user
	 *
	 * 	@param	- User name ("GUID")
	 */
	static function delUser(string $uid): void {

		$gui = guiHandler::getInstance();
		$db  = DB::getInstance();

		// login as specific user
		$gui->Login($uid);

		foreach (array_reverse(Util::HID(Util::HID_ENAME, DataStore::USER|DataStore::ATTACHMENT|
		         DataStore::DATASTORES), true) as $hid => $unused) {

			foreach ($db->getRIDS($hid) as $id => $unused) {

				// only delete given user ID
				if (($hid & DataStore::USER) && $id != $uid)
					continue;

				// delete record
				$db->Query($hid, DataStore::DEL, $id);
			}
		}
		$unused; // disable Eclipse warning

		// delete user record itself
		$db->Query(DataStore::USER, DataStore::DEL, $uid);
	}

}
