function sgwSetSize(h,w){var i,n,v;if(!h){if(typeof(window.innerWidth)=="number"){h=window.innerHeight-30;w=window.innerWidth-20}else{if(document.documentElement&&document.documentElement.clientHeight){h=document.documentElement.clientHeight-45;w=document.documentElement.clientWidth-20}else{if(document.body&&document.body.clientHeight){h=document.body.clientHeight-45;w=document.body.clientWidth-20}}}}for(var a=new Array("sgwTit","sgwCmd","sgwBut","sgwMsg"),i=0;i<a.length;i++){if((v=document.getElementById(a[i]))!=null){if(a[i]=="sgwTit"||a[i]=="sgwBut"){v.style.width=w+"px"}else{v.style.width=(w-12)+"px"}}}v=document.getElementsByTagName("div");for(i=0;i<v.length;i++){if(v[i].className=="sgwDiv"&&v[i].id!="sgwMsg"){h-=(v[i].offsetHeight+parseInt(sgwPix(v[i].style.marginTop))+parseInt(sgwPix(v[i].style.marginBottom)))}}if((v=document.getElementById("sgwMsg"))!=null){v.style.height=h+"px"}}function sgwMaximize(id){var i,h,v,w;if(typeof(window.innerWidth)=="number"){h=window.innerHeight-30;w=window.innerWidth-20}else{if(document.documentElement&&document.documentElement.clientHeight){h=document.documentElement.clientHeight-45;w=document.documentElement.clientWidth-20}else{if(document.body&&document.body.clientHeight){h=document.body.clientHeight-45;w=document.body.clientWidth-20}}}var e=document.getElementsByTagName("div");for(i=0;i<e.length;i++){if(e[i].id=="sgwTit"||e[i].id=="sgwHead"){h-=(e[i].offsetHeight+parseInt(sgwPix(e[i].style.marginTop))+parseInt(sgwPix(e[i].style.marginBottom)))}}if((v=document.getElementById(id))!=null){v.style.height=h+"px"}}function sgwPix(v){if(v.length==0||v=="NaN"){return"0"}var n=v.indexOf("pt");if(n>-1){return v.slice(0,n)*0.72}n=v.indexOf("px");if(n>-1){return v.slice(0,n)}return v}function sgwPick(sub,rec,hid,grp,gid){var i=0;while(true){var e=document.getElementById("ExpRow"+i);if(e==null){break}if(i==rec){e.style.backgroundColor="#E6E6E6";document.getElementById("ExpHID").value=hid;document.getElementById("ExpGRP").value=grp;document.getElementById("ExpGID").value=gid;if(sub){document.getElementById("Action").value="Explorer";document.getElementById("ExpCmd").value=sub;sgwAjaxStop(1);document.syncgw.submit()}}else{e.style.backgroundColor="#FFFFFF"}i++}}function sgwAdmin(mod){var a=document.getElementById("AdminFlag");var u=document.getElementById("UserID");var p=document.getElementById("UserPW");if(mod==1){a.checked=true}else{if(mod==0){a.checked=false}}if(a.checked){u.value=null;u.disabled=true;u.style.backgroundColor="#EBEBE4";p.value=null;p.focus()}else{u.disabled=false;u.style.backgroundColor="#FFFFFF";u.focus()}};