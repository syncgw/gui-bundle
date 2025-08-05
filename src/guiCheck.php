<?php
declare(strict_types=1);

/*
 * 	Environement and server check
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\gui;

use syncgw\lib\Config;
use syncgw\lib\Server;
use syncgw\lib\Util;

class guiCheck {

    /**
     * 	Singleton instance of object
     * 	@var guiCheck
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiCheck {

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
		case 'Init':
			$gui->putCmd('<input id="Check" '.($gui->getVar('LastCommand') == 'Check' ? 'checked ' : '').
						 'type="radio" name="Command" '.
						 'value="Check" onclick="document.syncgw.submit();"/>&nbsp;'.
						 '<label for="Check">'.'Environment and server check'.'</label>');
			break;

		case 'Check':

			// perform basic checks
			$err = 0;
			$ok = 'Available';
			$gui->tabMsg('Checking PHP version ...', '', phpversion());

		    $s  = array( 'B', 'KB', 'MB', 'GB', 'TB' );
    		$b1 = disk_free_space('.');
		    $c1 = min((int)log($b1, 1024) , count($s) - 1);
    		$b2 = disk_total_space('.');
		    $c2 = min((int)log($b2, 1024) , count($s) - 1);
    		$gui->tabMsg('Checking disk space ...', '', sprintf('%1.2f', $b1 / pow(1024, $c1)).' '.$s[$c1].' / '.
									 				       sprintf('%1.2f', $b2 / pow(1024, $c2)).' '.$s[$c2]);

			$m = 'Checking \'Document Object Model\' (DOM) PHP extension';
			if (!class_exists('DOMDocument')) {

				$gui->tabMsg($m, '', 'PHP extension must be enabled in PHP.INI (<a class="sgwA" '.
							 'href="http://www.php.net/manual/en/dom.setup.php'.
							 '" target="_blank">more information</a>)', Config::CSS_ERR);
				$err++;
			} else
				$gui->tabMsg($m, '', $ok);

			$m = 'Checking \'DOM_XML\' PHP extension ...';
			if (function_exists('domxml_new_doc')) {

				$gui->tabMsg($m, '', 'PHP extension must be disabled in PHP.INI (<a class="sgwA" '.
							 'href="http://www.php.net/manual/en/domxml.installation.php'.
							 '" target="_blank">more information</a>)', Config::CSS_ERR);
				$err++;
			} else
				$gui->tabMsg($m, '', $ok);

			$m = 'Checking \'GD\' PHP extension ...';
			if (!function_exists('gd_info')) {

				$gui->tabMsg($m, '', 'PHP extension must be enabled in PHP.INI (<a class="sgwA" '.
							 'href="http://www.php.net/manual/en/image.setup.php'.
							 '" target="_blank">more information</a>)', Config::CSS_ERR);
				$err++;
			} else
				$gui->tabMsg($m, '', $ok);

			$m = 'Checking \'ZIP\' PHP extension ...';
			if (!class_exists('ZipArchive'))
				$gui->tabMsg($m, '', 'You need to enable this PHP extension in if you want to download '.
							 'or upload data in administrator panel '.
							 '(<a class="sgwA" '.
							 'href="http://www.php.net/manual/en/zip.setup.php" target="_blank">more information</a>)',
							 Config::CSS_WARN);
			else
				$gui->tabMsg($m, '', $ok);

			if (class_exists('syncgw\\interface\\mysql\\Handler')) {

				$m = 'Checking \'MySQL improved\' PHP extension ...';
				if (!function_exists('mysqli_connect')) {
					$gui->tabMsg($m, '', 'You need to enable this PHP extension in PHP.INI if you want to use '.
								 'a MySQL based data base interface handler'.
								 '(<a class="sgwA" href="http://www.php.net/manual/en/mysqli.installation.php" '.
								 'target="_blank">more information</a>)', Config::CSS_WARN);
					$err++;
				} else
					$gui->tabMsg($m, '', $ok);
			}

			$m = 'Checking \'Multibyte string\' PHP extension ...';
			if (!function_exists('mb_convert_encoding'))
				$gui->tabMsg($m, '', 'You need to enable this PHP extension in PHP.INI if you want to synchronize multi-byte data '.
							 '(<a class="sgwA" href="http://www.php.net/manual/en/mbstring.installation.php" '.
							 'target="_blank">more information</a>)', Config::CSS_WARN);
			else
				$gui->tabMsg($m, '', $ok);

			if (!$err) {

				$m = 'Checking directory path used for temporary files ...';
				if ($tmp = Util::getTmpFile()) {

				    unlink($tmp);
					$rc = true;
				} else
					$rc = false;

				if ($rc === false) {

					$gui->tabMsg($m, '', 'Please enable access to directory in PHP.INI (<a class="sgwA" href="'.
								 'http://www.php.net/manual/en/ini.core.php#ini.open-basedir" target="_blank">more information</a>',
								 Config::CSS_ERR);
					$err++;
				} else
					$gui->tabMsg($m, '', $ok);
			}

			// create footer
			$gui->putMsg('');
			$m = 'Environment status';
			$cnf = Config::getInstance();
			if ($err)
				$gui->tabMsg($m, Config::CSS_TITLE, sprintf('%d errors found - please fix errors and run script again', $err),
							 Config::CSS_ERR);
			elseif (!$cnf->getVar(Config::DATABASE)) {

				$gui->tabMsg($m, Config::CSS_TITLE, 'Warning - no data base connected', Config::CSS_WARN);
				$err = 1;
			}
			if (!$err)
				$gui->tabMsg($m, Config::CSS_TITLE, 'Ok', Config::CSS_TITLE);

			// is server configured?
			if (!$gui->isConfigured()) {

				$gui->tabMsg('<strong>sync&bull;gw</strong> status', Config::CSS_TITLE,
							 'Server not configured', Config::CSS_ERR);
				break;;
			}

			// create server object
			$tit = 'Ready for synchronizing!';
			$col = Config::CSS_TITLE;

			// get server information
			$srv = Server::getInstance();
			$xml = $srv->getInfo();
			$gui->putMsg('');

			$xml->getChild('syncgw');
			while (($v = $xml->getItem()) !== null) {
				switch ($xml->getName()) {
				case 'Name':
					$gui->tabMsg($v, Config::CSS_TITLE, '', '');
					$m = $v;
					break;

				case 'Opt':
					$m = '&raquo; '.$v;
					break;

				case 'Stat':
					if (stripos($v, '+++') !== false) {

						$gui->tabMsg($m, '', $v, Config::CSS_ERR);
						$tit = 'Configuration error. Please see above...';
						$col = Config::CSS_ERR;
					} else
						$gui->tabMsg($m, '', $v, '');
					break;

				default:
					break;
				}
			}
			$gui->putMsg('');
			$gui->tabMsg('Overall status', Config::CSS_TITLE, $tit, $col);
			$gui->putMsg('');
			break;

		default:
			break;
		}

		return guiHandler::CONT;
	}

}
