// This script requires GreaseMonkey 0.3 or later

// ==UserScript==
// @name        DGS Forum Indicator
// @namespace   http://www.dragongoserver.net/goodies
// @description Add the red "new" indicator to forums with new messages.
// @include     *dragongoserver.net/forum/index.php*
// @include     *dragongoserver.sourceforge.net/forum/index.php*
// ==/UserScript==


/*
 Known bugs:
   When you post a message, the "new" flag will be set because there
   is a post in the forum that is newer that your last entrance.
   This could not be avoided. Just re-enter and quit the forum.

 Tested with Win98 + FireFox 1.0.7 + GreaseMonkey 0.5.3
             WinXP + FireFox 2.0.0.1 + GreaseMonkey 0.6.7.20070131

 Creator:
   Daniel Wagner (dmwit)
      http://www.dragongoserver.net/userinfo.php?uid=4155
      http://www.stanford.edu/~wagnerd
 Contributors:
   Rodival (rodival)
      http://www.dragongoserver.net/userinfo.php?uid=1056

 Version 0.2.0.20070223: rodival
   added separate server+user memorizations of the flags.
 Version 0.1.0.20050526: dmwit
   first version
*/


//alert('start');
var DGSfi_forumlinks = new Array();
var DGSfi_prefix = '';


var xPath = function(node, str)
{
   return document.evaluate( str, node, null,
      XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null);
} //xPath

var getUserHandle = function()
{
   var snap;
   
   snap = xPath(document.documentElement, "//table[@id='page_head']");
   if( snap.snapshotLength > 0 )
      snap = xPath(snap.snapshotItem(0), "*//td[last()]//*[text()]");

   if( snap.snapshotLength <= 0 )
      snap = xPath(document.documentElement, "//a[@id='loggedId']");

   if( snap.snapshotLength <= 0 )
      return '';
   snap = snap.snapshotItem(0).textContent;
   snap = snap.match(/([\-\+_a-zA-Z0-9]+)\s*$/);
   if( !snap )
      return '';
   return snap[1];
} //getUserHandle


var forumid = function(name)
{
   return DGSfi_prefix+name.replace(/\[.*$/,'').replace(/\s+/g,'');
} //forumid


var DGSfi_eventhandler = function(event) {
    var flink = event.target; //children[0].getElementsByTagName('a')[0]; // HACK
    var gmvar = DGSfi_forumlinks[flink][0];
    if( gmvar == undefined ) return;
    var stamp = DGSfi_forumlinks[flink][1];
    if( stamp == undefined ) return;
//window.alert('gmvar='+gmvar+' stamp='+stamp+' t='+typeof(stamp));
    GM_setValue(gmvar, stamp);
} //DGSfi_eventhandler


var DGSfi_init = function()
{
   var forumTRs, children, gmvar, stamp, flink;
   forumTRs = xPath(document.documentElement,
               "//table[@id='forumIndex']//tr[count(td)=3]");
   if( forumTRs.snapshotLength <= 0 )
      forumTRs = xPath(document.documentElement,
                  "//table[@bgcolor='#e0e8ed']//tr[count(td)=3]");

   for( var i = 0; i < forumTRs.snapshotLength; i++)
   {
      children = forumTRs.snapshotItem(i).getElementsByTagName('td');

      gmvar = forumid(children[0].textContent);
      stamp = children[2].textContent;
      //children[0].innerHTML +=' stamp='+stamp;
      stamp = stamp.match(/\d+-\d+-\d+[^\d]+\d+:\d+/);
      //children[0].innerHTML +=' match='+stamp[0];
      if( !stamp )
         stamp = '';
      else
         stamp = stamp[0];

      //children[0].innerHTML +=' gmvar='+gmvar+' stamp='+stamp;
      if( stamp > GM_getValue(gmvar, '') )
         children[0].innerHTML +=
            '<font color="#ff0000" size="-1">&nbsp;&nbsp;new</font>';

      // N.B.: flink must be set after the .innerHTML modification
      flink = children[0].getElementsByTagName('a')[0]; // HACK
      DGSfi_forumlinks[flink] = new Array(gmvar,stamp);

      // The event listener is added twice because 'mouseup' doesn't
      // catch keyboard clicks and 'click' doesn't catch middle-clicks
      // but, most of other times, this will call it two times...
      flink.addEventListener('mouseup', DGSfi_eventhandler, false);
      flink.addEventListener('click', DGSfi_eventhandler, false);
   }
} //DGSfi_init


DGSfi_prefix = getUserHandle()+':'+window.location.hostname+':';
//alert('prefix='+DGSfi_prefix);

DGSfi_init();
//alert('end');