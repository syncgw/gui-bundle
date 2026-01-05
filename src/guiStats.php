<?php
declare(strict_types=1);

/*
 * 	Get user statistics
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\gui;

use syncgw\lib\Config;
use syncgw\lib\DB;
use syncgw\lib\DataStore;

class guiStats {

    /**
     * 	Singleton instance of object
     * 	@var guiStats
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiStats {

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

		switch ($action) {
		case 'ExpUserStats':
			$gui->updVar('Action', 'Explorer');
			$gui->putMsg('User statistics', Config::CSS_TITLE);
			$gui->putMsg('');
			$db = DB::getInstance();
			foreach ($db->Query(DataStore::USER, DataStore::RIDS, '') as $gid => $unused) {

				$doc = $db->Query(DataStore::USER, DataStore::RGID, $gid);
				$gui->putMsg('<div style="width: 200px; float: left;">User name</div><div style="float:left;">'.
							$doc->getVar('GUID').'</div>');
				$gui->putMsg('<div style="width: 200px; float: left;">User ID</div><div style="float:left;">'.
							$doc->getVar('LUID').'</div>');
				$gui->putMsg('<div style="width: 200px; float: left;">Last login</div><div style="float:left;">'.gmdate('c',
							intval($doc->getVar('LastMod'))).'</div>');
				$gui->putMsg('<div style="width: 200px; float: left;">Number of logins</div><div style="float:left;">'.
							$doc->getVar('Logins').'</div>');
				$gui->putMsg('<div style="width: 200px; float: left;">Active device name</div><div style="float:left;">'.
							$doc->getVar('ActiveDevice').'</div>');
				$gui->putMsg('');
			}
			$unused; // disable Eclipse warning

		default:
			break;
		}

		if (substr($a = $gui->getVar('Action'), 0, 3) == 'Exp' && substr($a, 6, 4) != 'Edit' && ($gui->getVar('ExpHID') & DataStore::USER))
			$gui->setVal($gui->getVar('Button').$gui->mkButton('Stats', 'Show user statistics', 'ExpUserStats'));

		return guiHandler::CONT;
	}

}
