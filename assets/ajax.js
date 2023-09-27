/*
* 	Ajax handler class
* 
* 	@package 	sync*gw
*	@subpackage	GUI
*	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
* 	@license 	LGPL-3.0-or-later
*/

// [0] - XMLHTTP object
// [1] - timer object
// [2] - server URL
// [3] - save button text
// [4] - save button help
// [5] - refresh timer
// [6] - control data (Position in file/EOF/Scroll direction[1/2])
var sgwAjax = Array(8);

/**
 * Start catching output
 * 
 * @param string 	- URL of file data providing script
 * @param int 		- 2=Scroll to end of file
 */
function sgwAjaxStart(url, scroll) {
	// allocate Ajax object
	try {
		sgwAjax[0] = new XMLHttpRequest();
	} catch (trymicrosoft) {
		try {
			sgwAjax[0] = new ActiveXObject("Msxml2.XMLHTTP");
		} catch (othermicrosoft) {
			try {
				sgwAjax[0] = new ActiveXObject("Microsoft.XMLHTTP");
			} catch (failed) {
				sgwAjax[0] = null;
			}
		}
	}
	if (!sgwAjax[0]) {
		alert('Error creating request object - cannot retrieve any message data!');
		return;
	}
	sgwAjax[1] = null;
	sgwAjax[2] = url;
	sgwAjax[6] = '-1/-1/' + scroll;
	sgwQuery(1);
}

/**
 * Send query to server
 * 
 * @param bool 		- Reset timer
 */
function sgwQuery(res) {
	if (sgwAjax[1])
		clearTimeout(sgwAjax[1]);
	if (res)
		sgwAjax[5] = 0;
	// log file display?
	if (sgwAjax[3]) {
		// 3 seconds is the max. we wait in log file display
		if (sgwAjax[5] <= 3000)
			sgwAjax[5] += 500;
	} else {
		if (sgwAjax[5] <= 10000)
			sgwAjax[5] += 500;
	}
	// set new timer
	sgwAjax[1] = setTimeout('sgwQuery(0)', sgwAjax[5]);
	// send new query
	if (sgwAjax[0].readyState != 3) {
		var d = new Date();
		sgwAjax[0].open('GET', sgwAjax[2] + '&c=' + sgwAjax[6] + '&u='
				+ d.getTime(), true);
		// must be set every time
		sgwAjax[0].onreadystatechange = sgwGetData;
		sgwAjax[0].send(null);
	}
}

/**
 * Get and process file data
 */
function sgwGetData() {

	// https://developer.mozilla.org/en/XMLHttpRequest
	if (sgwAjax[0].readyState == 4) {
		if (sgwAjax[0].status == 200) {

			var recs = sgwAjax[0].responseText.split('\n');
			var top = null;
			var data = 0;
			var o;

			for ( var i = 0; i < recs.length - 1; i++) {

				// get <DIV>
				var n = recs[i].slice(1, 2);
				if (n == '6')
					o = document.getElementById('sgwCmd');
				else
					o = document.getElementById('sgwMsg');

				// check record received
				switch (recs[i].slice(0, 1)) {
				// clear?
				case '0':
					o.innerHTML = ' ';
					break;

				// append at end
				case '1':
					// need to create new element because of IE bug
					var txt = document.createElement('div');
					txt.innerHTML = recs[i].slice(2);
					var r = o.appendChild(txt);
					if (!top)
						top = r;
					data = 1;
					break;

				// add at top?
				case '2':
					// need to create new element because of IE bug
					var txt = document.createElement('div');
					txt.innerHTML = recs[i].slice(2);
					if (!top) {
						// we can't use firstElementChild property because of a
						// IE bug
						for (top = o.firstChild; top; top = top.nextSibling) {
							if (top.nodeType === 1)
								break;
						}
					}
					top = o.insertBefore(txt, top);
					data = 1;
					break;

				// control record
				case '3':
					sgwAjax[6] = recs[i].slice(2);
					break;

				default:
					break;
				}
			}

			// any data received?
			if (data) {
				// scroll to end?
				var s = sgwAjax[6].toString();
				var c = s.split('/');
				if (c[2] == 2)
					o.scrollTop = o.scrollHeight;
				else {
					// set scroll position
					o = document.getElementById('sgwCmd');
					top = document.getElementById('sgwCmdPos');
					if (top.value > -1 && o.scrollHeight > top.value)
						o.scrollTop = top.value;
					o = document.getElementById('sgwMsg');
					top = document.getElementById('sgwMsgPos');
					if (top.value > -1 && o.scrollHeight > top.value)
						o.scrollTop = top.value;
				}
				sgwQuery(1);
			}
		}
	}
}

/**
 * End Ajax display - save scroll position in window and stop Ajax
 * 
 * @param int		- 1=(default) delete messages <DIV>; 0=do not delete messages
 */
function sgwAjaxStop(dod) {
	var w, p, i;

	// set default value
	dod = typeof dod !== 'undefined' ? dod : 1;

	if (sgwAjax[1]) {
		clearTimeout(sgwAjax[1]);
		sgwAjax[1] = null;
	}
	w = document.getElementById('sgwCmd');
	p = document.getElementById('sgwCmdPos');
	if (w)
		p.value = w.scrollTop;
	w = document.getElementById('sgwMsg');
	p = document.getElementById('sgwMsgPos');
	if (w)
		p.value = w.scrollTop;
	if (!dod)
		return;
	// delete all messages shown
	for (i = (w == null ? -1 : w.childNodes.length - 1); i >= 0; i--) {
		if (w.childNodes[i].nodeName == 'DIV') {
			w.removeChild(w.childNodes[i]);
		}
	}
}

/**
 * Toggle message catching
 * 
 * @param string	- Button ID
 * @param string	- Pause message ID
 * @param strig		- New button text
 * @param string	- New button help text
 */
function sgwAjaxToggle(bid, mid, txt, htxt) {
	var e = document.getElementById(bid);
	var f = document.getElementById(mid);
	if (sgwAjax[1]) {
		sgwAjaxStop(0);
		sgwAjax[3] = e.value;
		sgwAjax[4] = e.title;
		e.value = txt;
		e.title = htxt;
		f.style.display = '';
		f.style.visibility = 'visible';
	} else {
		e.value = sgwAjax[3];
		e.title = sgwAjax[4];
		f.style.visibility = 'hidden';
		f.style.display = 'none';
		sgwQuery(1);
	}
}