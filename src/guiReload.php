<?php
declare(strict_types=1);

/*
 * 	Reload cache tables
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\gui;

class guiReload {

    /**
     * 	Singleton instance of object
     * 	@var guiReload
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiReload {

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

		if (substr($a = $gui->getVar('Action'), 0, 3) == 'Exp' && substr($a, 6, 4) != 'Edit')
			$gui->updVar('Button', $gui->getVar('Button').$gui->mkButton('Reload', 'Reload table', 'Explorer'));

		return guiHandler::CONT;
	}

}
