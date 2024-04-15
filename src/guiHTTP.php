<?php
declare(strict_types=1);

/*
 * 	Process HTTP input / output
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\gui;

use syncgw\lib\Config;
use syncgw\lib\HTTP;

class guiHTTP extends HTTP {

   /**
     * 	Singleton instance of object
     * 	@var guiHTTP
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiHTTP {

		if (!self::$_obj) {

            self::$_obj = new self();
            parent::getInstance();
		}

		return self::$_obj;
	}

	/**
	 * 	Check HTTP input
	 *
	 * 	@return - HTTP status code
	 */
	public function checkIn(): int {

		$cnf = Config::getInstance();

		// is debug running?
		if ($cnf->getVar(Config::DBG_LEVEL) != Config::DBG_OFF)
			return 200;

		// check for common browser types
		if ($ua = isset(self::$_http[HTTP::SERVER]['HTTP_USER_AGENT']) ?
						self::$_http[HTTP::SERVER]['HTTP_USER_AGENT'] : '')

	      	foreach ([ 'firefox', 'safari', 'webkit', 'opera', 'netscape', 'konqueror', 'gecko' ] as $name) {

			    // are we called by a internet browser?
      			if (stripos($ua, $name) !== false) {

				   $cnf->updVar(Config::HANDLER, 'GUI');
				   break;
		     	}
	    	}

		return 200;
	}

	/**
	 * 	Check HTTP output
	 *
	 * 	@return - HTTP status code
	 */
	public function checkOut(): int {

		// output processing
		if (Config::getInstance()->getVar(Config::HANDLER) != 'GUI')
			return 200;

		self::$_http[self::SND_HEAD]['Content-Length'] = strlen(self::$_http[self::SND_BODY]);

		return 200;
	}

}
