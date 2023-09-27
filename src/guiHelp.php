<?php
declare(strict_types=1);

/*
 * 	Show change log and help
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\gui;

use syncgw\lib\XML;

class guiHelp {

    /**
     * 	Singleton instance of object
     * 	@var guiHelp
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiHelp {

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

		$xml->addVar('Opt', 'Help plugin');
	}

	/**
	 * 	Perform action
	 *
	 * 	@param	- Action to perform
	 * 	@return	- guiHandler status code
	 */
	public function Action(string $action): string {

		if ($action == 'Init') {
			$gui = guiHandler::getInstance();
			$gui->setVal($gui->getVar('Button').$gui->mkButton('Help', 'FAQ',
						 'var w = window.open(\'https://github.com/syncgw/syncgw/\');w.focus();'));
		}
		return guiHandler::CONT;
	}

}
