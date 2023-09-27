<?php

/*
 *  Ajax request handler class
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\gui;

/**
 * 	Message layout:
 *
 *  n			0=Clear window; 1=Append; 2=Insert at top; 3=Control record (Position infile/EOF/Scroll direction[1/2])
 * 	n			6=sgwCmd DIV; 7=sgwMsg DIV
 *  HTML		HTL code
 */

// work buffer size 10KB
$len = 10240;
// max. record size is 10MB
$max = 10485760;

// enable UTF-( output
header("Content-Type: text/html; charset=utf-8");

// check parameter provided
foreach (explode('&', $_SERVER['QUERY_STRING']) as $v) {

	switch(substr($v, 0, 1)) {

	// file name
	case 'n':
		$file = base64_decode(substr($v, 2));
		break;

	// control record
	case 'c':
		list($wpos, $weof, $omod) = explode('/', substr($v, 2));
		$mod = $omod;
		break;

	default:
		break;
	}
}

// check variables
foreach ([ 'file', 'wpos', 'weof', 'mod' ] as $v) {

	// if we miss something, we abort execution
	if (!isset($v))
		exit;
}

// get file size
if (($eof = @filesize($file)) === false)
	$eof = 0;

// EOF saved?
if ($weof == -1)
	$weof = $eof;

// file data appended?
if ($mod == 2 && $weof < $eof) {

	$mod = 1;
	$wpos = $weof;
	$weof = $eof;
}

// check end of action conditions
if ($mod == 1) {

	// position at EOF & non-changed EOF
	if ($wpos == $weof && $weof >= $eof)
		exit;
} elseif (!$wpos && $weof >= $eof)
	exit;

// empty file or end of file reached
if ($wpos == -1) {

	// clear windows
	echo '06'."\n".'07'."\n";
	$wpos = $mod == 1 ? 0 : $weof;
}

// empty file?
if (!$eof)
	exit;

$fp = fopen($file, 'rb');

while (1) {

	// move backwards in file?
	if ($mod == 2) {

		if ($wpos > 10240)
			$wpos -= 10240;
		else {

			$len = $wpos;
			$wpos = 0;
		}
	}
	fseek($fp, $wpos, SEEK_SET);
	$wrk = fread($fp, $len);

	// get records in forward direction
	if ($mod == 1) {

		$recs = explode("\n", substr($wrk, 0, $p = strrpos($wrk, "\n") + 1));
		if ($wpos > $weof)
			$wpos = $weof;
	} else {

		$p = !$wpos ? 0 : (strpos($wrk, "\n") + 1);
		$recs = explode("\n", substr($wrk, $p));
	}
	if ($p > 1)
		$wpos += $p;
	$cnt = count($recs);

	// check break conidtion
	if ($wpos == $eof || $cnt > 1 || ($mod == 2 && !$wpos))
		break;

	// increase buffer limit
	$len += 10240;
	if ($len > $max) {

		send(1, sprintf('7<div style="color:red;margin:5px 0px 20px 0;border-style:groove;border-width:8px;padding:10px;"><strong>'.
				'Max. record length %s near %s in \'%s\' ajax file'.'</strong><br></div>', cnvBytes2String($len), $wpos, $file));
			break;
	}
}

// send output
if ($mod == 1) {

	for($i=0; $i < $cnt; $i++)
		send($mod, $recs[$i]);
} else {

	$cnt--;
	while ($cnt--)
		send($mod, $recs[$cnt]);
}

// send control record
echo '37'.($omod != $mod ? '0' : $wpos).'/'.$weof.'/'.$omod."\n";
fclose($fp);
exit;

/**
 * 	Send message to front end
 *
 * 	@param string $mod 			- Message typ (ERR, WARN, INFO, APP)
 * 	@param string $rec 			- Record
 */
function send($mod, $rec) {

	// check for unencoded log file entries
	if (($t = substr($rec, 0, 1)) != 6 && $t != 7) {

		// color description is a copy of Config.php - is defined here again to increase speed
		switch (substr($rec, 39, 4)) {

		case 'Warn':
			$c = 'color: #FF8000; font-weight: bold;';
			break;

		case 'Info':
			$c = 'color: #01DF01;';
			break;

		case 'Apps':
			$c = 'color: #01DFD7;';
			break;

		case 'Debu':
			$c = 'color: #D400FF;';
			break;

		default:
			$c = 'color: #DF0101; font-weight: bold;';
			$a = [];
			if (preg_match('/(\[.*\]) (PHP.*\s)/', $rec, $a))
				$rec = gmdate('Y M d H:i:s', strtotime(substr($a[1], 1, -1))).' '.
				str_pad($_SERVER['SERVER_ADDR'], 16, ' ').'Error 9999 '.str_replace("\r", null, substr($rec, 23));
			break;
		}

		if (!$rec)
			return;

		$rec = '7<span style="'.$c.'font-family: monospace;">'.
				str_replace(' ', '&nbsp;', htmlentities($rec, ENT_COMPAT|ENT_HTML401, 'UTF-8')).'</span>';
	}
	echo $mod.$rec."\n";
}

/**
 * 	Convert a number to human readable format
 *  This is a copy of Util::cnvByte2String() - to increase performance of Ajax.php processing
 *
 * 	@param	- Value to convert
 * 	@return	- Display string
 */
function cnvBytes2String(int $val): string {

	$f = [ null, 'KB', 'MB', 'GB' ];
	$o = 0;
	while ($val >= 1023) {

		$o++;
		$val = $val / 1024;
	}

	return number_format($val, 0, ',', '.').' '.$f[$o];
}
