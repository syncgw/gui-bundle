<?php
declare(strict_types=1);

/*
 * 	View record
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\gui;

use syncgw\lib\DB;
use syncgw\lib\DataStore;

class guiShow {

    /**
     * 	Singleton instance of object
     * 	@var guiShow
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiShow {

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
		$gid = $gui->getVar('ExpGID');
		$hid = intval($gui->getVar('ExpHID'));

		switch ($action) {
		case 'ExpRecShow':

			// load record
			if (!($doc = DB::getInstance()->Query($hid, DataStore::RGID, $gid)))
				break;

			$gui->putQBox('<code>XML document</code>', '', $doc->mkHTML(), true, 'Msg');

		default:
			break;
		}

		// allow only during explorer call
		if (substr($a = $gui->getVar('Action'), 0, 3) == 'Exp' &&
			substr($a, 6, 4) != 'Edit' && $gid && !($hid & DataStore::TRACE))
			$gui->setVal($gui->getVar('Button').$gui->mkButton('View', 'View internal record', 'ExpRecShow'));

		return guiHandler::CONT;
	}

}
