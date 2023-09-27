/*
 *  QBox handler
 *
 *	@package	sync*gw
 *	@subpackage	GUI
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

/**
 * Show Q-Box
 * 
 * @param string	- Name of <Div>
 * 
 * Implementation:
 * 
 * <input id="QBox1B" value="+" onclick="QBox('QBox1');" type="button" /> Text 
 * collapsed <div id="QBox1" style="visibility: hidden; display: none;"> Text to show </div><br>
 */

function QBox(nam) {
	var e = document.getElementById(nam);
	var b = document.getElementById(nam + 'B');
	if (e.style.visibility == 'visible') {
		e.style.visibility = 'hidden';
		e.style.display = 'none';
		b.value = '+';
	} else {
		e.style.display = '';
		e.style.visibility = 'visible';
		b.value = '-';
	}
}