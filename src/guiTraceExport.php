<?php
declare(strict_types=1);

/*
 * 	Export trace file to HTML
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\gui;

use syncgw\lib\Config;
use syncgw\lib\DataStore;
use syncgw\lib\Log;
use syncgw\lib\Util;

class guiTraceExport {

    /**
     * 	Singleton instance of object
     * 	@var guiTraceExport
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): guiTraceExport {

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
		$ct  = null;

		switch ($action) {
		case 'ExpTraceExport':

			// load skeleton
			$skel = file_get_contents(__DIR__.'/../assets/export.html');

			// load trace data
			$recs = [];
			foreach (explode("\n", file_get_contents($_SESSION[$gui->getVar('SessionID')][guiHandler::BACKUP])) as $rec)
				if (substr($rec, 0, 1) == '7')
					$recs[] = substr($rec, 1);

			if (!$recs) {

				$gui->putMsg('No trace data found', Config::CSS_ERR);
				break;
			}

			$skel = str_replace('{TraceFile}', implode('', $recs), $skel);
			file_put_contents($html = Util::getTmpFile('html'), $skel);

			// create tmp file
			$dest = Util::getTmpFile('zip');
			$zip = new \ZipArchive();
			if (($rc = $zip->open($dest, \ZipArchive::CREATE|\ZipArchive::OVERWRITE)) !== true) {
				$gui->putMsg(sprintf('Error opening file [%s] (%s)', $dest, $rc), Config::CSS_ERR);
				break;
			}
			// swap all files
			foreach ([ $html, __DIR__.'/../assets/favicon.ico', __DIR__.'/../assets/qbox.min.js',
					   __DIR__.'/../assets/style.min.css', __DIR__.'/../assets/syncgw.png' ] as $file)

				if (!$zip->addFile($file, basename($file))) {

					$gui->putMsg(sprintf('Error writing file [%s]', $file), Config::CSS_ERR);
					break;
				}
			$zip->close();
			$ct   = 'application/zip';
			break;

		default:
			break;
		}

		// start download
		if ($ct) {

			// log unsolicted output
			Log::getInstance()->catchConsole(false);
			header('Pragma: public');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Cache-Control: public');
			header('Content-Description: File Transfer');
			header('Content-Type: '.$ct);
			header('Content-Disposition: attachment; filename="export.zip"');
			header('Content-Transfer-Encoding: binary');
			header('Content-Length: '.filesize($dest));
			readfile($dest);
   			unlink($dest);
   			unlink($html);
   			exit();
		}

		// allow only during explorer call
		$hid = intval($gui->getVar('ExpHID'));
		if (substr($gui->getVar('Action'), 0, 3) == 'Exp' && (($hid & DataStore::TRACE) || ($gui->getVar('ExpGRP') && ($hid & DataStore::DATASTORES))))
			$gui->updVar('Button', $gui->getVar('Button').$gui->mkButton('Export', 'Export trace in message window', 'ExpTraceExport'));

		return guiHandler::CONT;
	}

}
