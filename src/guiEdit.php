<?php
declare(strict_types=1);

/*
 * 	Edit record
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
use syncgw\lib\XML;

class guiEdit {

 	/**
	 * 	Error message
	 * 	@var string
	 */
	private $_err;

    /**
     * 	Singleton instance of object
     * 	@var guiEdit
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiEdit {

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

		$xml->addVar('Opt', 'Edit record plugin');
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
		case 'ExpRecEdit':
			$b = $gui->mkButton(guiHandler::STOP, '', 'Explorer');
			$gui->updVar('Button', $b.$gui->mkButton('Save', 'Save updates', 'ExpRecSave', false));

			$db = DB::getInstance();
			// load record
			if (!($doc = $db->Query($hid, DataStore::RGID, $gid))) {

				$gui->putMsg(sprintf('Error reading record [%s]', $gid), Config::CSS_ERR);
				break;
			}

			$doc->getVar('syncgw');
			$gui->updVar('Script', '<script type="text/javascript">'.
						 // maximize message window
						 'var e = document.getElementById(\'sgwCmd\');'.
						 'e.style.visibility = \'hidden\';'.
						 'e.style.display = \'none\';'.
						 'document.getElementById(\'sgwMsg\').style.height = \'82%\';</script>');
			$gui->putMsg('<textarea name="ExpEditArea" cols="150" rows="30">'.
						 str_replace("\n", "\r", $doc->saveXML(false, true)).'</textarea>');
			break;

		case 'ExpRecSave':
			$db = DB::getInstance();
			$doc = new XML();
			$doc->loadXML($gui->getVar('ExpEditArea'));
			// dave record
			if (!$db->Query($hid, DataStore::UPD, $doc)) {

				$gui->putMsg(sprintf('Error writing record [%s]', $gid), Config::CSS_ERR);
				break;
			}

		default:
			break;
		}

		if (substr($a = $gui->getVar('Action'), 0, 3) == 'Exp' && substr($a, 6, 4) != 'Edit' && $gid &&
		    !($hid & (DataStore::TRACE|DataStore::ATTACHMENT)))
			$gui->setVal($gui->getVar('Button').$gui->mkButton('Edit', 'Edit selected record', 'ExpRecEdit'));

		return guiHandler::CONT;
	}

}
