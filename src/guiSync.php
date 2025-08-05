<?php
declare(strict_types=1);

/*
 * 	Sync with external data base
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\gui;

use syncgw\lib\DataStore;
use syncgw\lib\Util;
use syncgw\lib\Config;

class guiSync {

    /**
     * 	Singleton instance of object
     * 	@var guiSync
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiSync {

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

		// only allowed for non-administrators
		if ($gui->isAdmin())
			return guiHandler::CONT;

		$hid = intval($gui->getVar('ExpHID'));

		switch ($action) {
		case 'ExpSync':
			// clear window
			$gui->clearAjax();

			// sync records
			$ds = Util::HID(Util::HID_CNAME, $hid);
			$ds::getInstance()->syncDS($gui->getVar('ExpGID'), true);
			$gui->putMsg('Done');
			// reload explorer view
			$gui->updVar('Action', 'Explorer');
			return guiHandler::RESTART;

		default:
			break;
		}

		// not available for administrators
		if (substr($a = $gui->getVar('Action'), 0, 3) == 'Exp' && substr($a, 6, 4) != 'Edit' &&
			$hid & DataStore::DATASTORES && $_SESSION[$gui->getVar('SessionID')][guiHandler::TYP] == guiHandler::TYP_USR)
			$gui->setVal($gui->getVar('Button').$gui->mkButton('Sync',
						 'Synchronize external data store records with internal data store', 'ExpSync'));

		return guiHandler::CONT;
	}

}
