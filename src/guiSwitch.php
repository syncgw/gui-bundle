<?php
declare(strict_types=1);

/*
 * 	Switch data base back end
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\gui;

use syncgw\lib\Config;

class guiSwitch {

    /**
     * 	Singleton instance of object
     * 	@var guiSwitch
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiSwitch {

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
		$cnf = Config::getInstance();

		switch ($action) {
		case 'SwitchBE':
			$l = [];
			foreach ([ 'file', 'mysql', 'roundcube', 'mail', 'myapp'] as $name) {

				if (!($dir = opendir($path = $cnf->getVar(Config::ROOT).$name.'-bundle/src'))) {

					$gui->putMsg(sprintf('Can\'t open \'%s\'', $path), Config::CSS_ERR);
					break;
				}

				while (($file = readdir($dir)) !== false) {

					if ($file == '.' || $file == '..' || substr($file, -11) != 'Handler.php')
						continue;

					$l[] = $name;
				}
				closedir($dir);
			}
			ksort($l);

			$f = ' <select name="IntSwitch">';
			$be = $cnf->getVar(Config::DATABASE);
			foreach ($l as $name) {

				$s = $name == $be ? ' selected="selected"' : '';
				$f .= '<option'.$s.'>'.$name.'</option>';
			}
			$f .= '</select>';

			// clear command window
			$gui->putQBox('Application interface data base', $f, '', false);
			$gui->putMsg('Warning: Switching data base may end up with unexpected results, if data base is not properly '.
						 'initialized.', Config::CSS_WARN);
			$gui->putMsg('Please select "new" data base from list and hit button "Switch" again or hit "Cancel" to cancel change');
			$gui->updVar('Button', $gui->mkButton('Cancel', 'Return to command selection menu', 'Config').
							$gui->mkButton('Run', 'Switch data base installation without initialization.', 'SwitchBEDO'));
			$gui->updVar('Action', 'Config');

			return guiHandler::STOP;

		case 'SwitchBEDO':
			$n = $gui->getVar('IntSwitch');
			switch ($n) {
			case 'myapp':
			case 'mysql':
				$be = $n;
				$db = 'myapp';
				break;

			case 'roundcube':
			case 'mail':
				$be = $n;
				$db = 'mail';
				break;

			case 'file':
				$be = $n;
				$db = '';
				break;

			default:
				$gui->putMsg(sprintf('Unknown interface handler \'%s\'', $n), Config::CSS_WARN);
				return guiHandler::CONT;
			}
			$cnf->updVar(Config::DATABASE, $be);
			$cnf->updVar(Config::DB_NAME, $db);

			// save .INI file
			$cnf->saveINI();
			$gui->putMsg(sprintf('Active interface handler switched to \'%s\'', $be), Config::CSS_INFO);
			$gui->putMsg('Please check enabled data store handlers', Config::CSS_INFO);
			$gui->updVar('Action', 'Config');

			return guiHandler::RESTART;

		default:
			break;
		}

		if (substr($action, 0, 4) == 'Conf')
			$gui->updVar('Button', $gui->getVar('Button').
	 					 $gui->mkButton('Switch', 'Switch data base installation without initialization.', 'SwitchBE'));

		return guiHandler::CONT;
	}

}
