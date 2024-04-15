<?php
declare(strict_types=1);

/*
 * 	Show statistics
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\gui;

class guiMemory {

    /**
     * 	Singleton instance of object
     * 	@var guiMemory
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiMemory {

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
		$gui->putHidden('Usage', sprintf('Script usage: %.2f MB', memory_get_usage() / (1024*1024)).
						sprintf(' (peak: %.2f MB)', memory_get_peak_usage() / (1024*1024)));
		return guiHandler::CONT;
	}

}
