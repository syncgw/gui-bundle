<?php
declare(strict_types=1);

/*
 * 	Set user
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

class guiSetUsr {

    /**
     * 	Singleton instance of object
     * 	@var guiSetUsr
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiSetUsr {

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

		$xml->addVar('Opt', 'Set user plugin');
	}

	/**
	 * 	Perform action
	 *
	 * 	@param	- Action to perform
	 * 	@return	- guiHandler status code
	 */
	public function Action(string $action): string {

		$gui = guiHandler::getInstance();

		// check administrator status
		if (!$gui->isAdmin())
			return guiHandler::CONT;

		$db = DB::getInstance();

		switch ($action) {
		case 'Init':
			$f = '&nbsp;&nbsp;<select name="SetUsrName" onchange="document.getElementById(\'SetUsr\').checked = true;'.
				 'document.syncgw.submit();">';

			// get current user id in use
			$uid = $_SESSION[$gui->getVar('SessionID')][guiHandler::UID] ?
				   base64_decode($_SESSION[$gui->getVar('SessionID')][guiHandler::UID]) : 0;

			if (!($usr = $db->Query(DataStore::USER, DataStore::RIDS)))
			    $usr = [];
			foreach ($usr as $id => $unused) {

				// user preselected?
				if (!$uid)
					$uid = $id;

				$f .= '<option '.($id == $uid ? 'selected="selected"' : '').'>'.$id.'</option>';
			}
			$unused; // disable Eclipse warning
			$f .= '</select>';
			$gui->putCmd('<input id="SetUsr" '.($gui->getVar('LastCommand') == 'SetUsr' ? 'checked ' : '').
						 'type="radio" name="Command" value="SetUsr" />&nbsp;'.
						 '<label for="SetUsr">Select user name to use</label>'.$f);
			break;

		case 'SetUsr':
			if ($uid = $gui->getVar('SetUsrName')) {

				$_SESSION[$gui->getVar('SessionID')][guiHandler::UID] = base64_encode($uid);
				$gui->putMsg(sprintf('User id \'%s\' activated', $uid), Config::CSS_WARN);
			}

		default:
			break;
		}

		return guiHandler::CONT;
	}

}
