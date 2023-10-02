<?php
declare(strict_types=1);

/*
 * 	Enable forced trace
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\gui;

use syncgw\lib\DataStore;

class guiForceTrace {

    /**
     * 	Singleton instance of object
     * 	@var guiForceTrace
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiForceTrace {

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

		if (substr($gui->getVar('Action'), 0, 3) == 'Exp' && intval($gui->getVar('ExpHID') & DataStore::TRACE))
			$gui->setVal($gui->getVar('Button').'<div><input name="ForceTrace" type="checkbox" value="1" /> Force Trace</div>');

		return guiHandler::CONT;
	}

}
