// This script requires GreaseMonkey 0.3 or later

// ==UserScript==
// @name        DGS Toggle Numbering
// @namespace   http://www.dragongoserver.net/goodies
// @description Insert a button to toggle the stone numbering of a board. Set your profile numbering first then you will be able to hide it.
// @include     *dragongoserver.net/game.php*
// @include     *dragongoserver.sourceforge.net/game.php*
// ==/UserScript==

/* <scriptinfos>
 You must set a valid - on board - stone numbering option in your Dragon
    profile settings (for instance, a 10 value and Hover unchecked)

 Tested with Win98 + FireFox 1.0.7 + GreaseMonkey 0.5.3
             WinXP + FireFox 2.0.0.1 + GreaseMonkey 0.6.7.20070131
             SuSE  + FireFox 2.0.0.1 by axd

 Creator:
   Rodival (rodival)
      http://www.dragongoserver.net/userinfo.php?uid=1056
 Contributors:
   alex (axd)
      http://www.dragongoserver.net/userinfo.php?uid=3209

 Version 0.2.2.20070224: rodival
   better way to handle custom properties.
 Version 0.2.1.20070223: rodival
   added separate server+user memorizations.
 Version 0.2.0.20070222: rodival
   added a memorization of the show/hide state while changing pages.
 Version 0.1.0.20070220: rodival
   first version
</scriptinfos> */


//alert('start');
var DGStn_stones = new Array();
var DGStn_hidden = false;
var DGStn_prefix = '';


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


var DGStn_toggle = function(event)
{
//alert('toggle');
   var elt;
   if( event != undefined )
   {
      event.preventDefault();
      event.stopPropagation();
   }
   DGStn_hidden = !DGStn_hidden;
   GM_setValue(DGStn_prefix+'hidden', DGStn_hidden);
   for( var i=DGStn_stones.length-1; i >= 0; i-- )
   {
      elt = DGStn_stones[i];
      if( DGStn_hidden )
         elt.src = elt.getAttribute('xsrc');
      else
         elt.src = elt.getAttribute('nsrc');
   }
} //DGStn_toggle


var DGStn_init = function()
{
   var snapshot, where, elt, num, nsrc, xsrc;
   //var forms = document.getElementsByTagName('form');


   //first find the stones
   snapshot = xPath(document.documentElement,
   //   "//form/table//table[@style]//img[@class='brdx' and (@alt='X' or @alt='O')]"
      "//form/table//table//img[@class='brdx' and (@alt='X' or @alt='O')]"
      );
   if( snapshot.snapshotLength <= 0 )
      return 1;


   //then only keep the numbered stones
   num = 0;
   for( var i=snapshot.snapshotLength-1; i >= 0; i-- )
   {
      elt = snapshot.snapshotItem(i);
      nsrc = elt.src;
      xsrc = nsrc.replace( /\x2F(b|w)\d+\./i , '/$1.');
      if( nsrc == xsrc )
         continue;
      elt.setAttribute('nsrc', nsrc);
      elt.setAttribute('xsrc', xsrc);
      DGStn_stones[num]= elt;
      num++;
   }
//alert('num='+num);
   if( num <= 0 )
      return 2;


   //next find a place where insert the toggle button
   //this is difficult because of the various pages and layouts
   snapshot = xPath(document.documentElement, "//select[@name='gotomove']");
   where = DGStn_stones[0];
   while( where=where.parentNode )
      if( where.tagName.toUpperCase() == 'TABLE' )
         break;
   if( where )
   {
      if( where.parentNode.tagName.toUpperCase() == 'DIV' )
         where = where.parentNode;
      where = where.nextSibling;
   }
   else if( snapshot.snapshotLength > 0 )
   {
      where = snapshot.snapshotItem(0);
   }
   if( !where )
      return 3;


   //build and insert the button
   elt = document.createElement('a');
   //elt.style.display = 'block';
   //elt.style.padding = '0.2em 6px';
   elt.style.margin = '0px 4px 2px 4px';
   //elt.href = "javascript:this.blur();";
   //elt.href = "javascript:void(0);";
   elt.href = '#';

   // The event listener can't be added twice but 'mouseup' doesn't
   // catch keyboard clicks and 'click' doesn't catch middle-clicks
   //elt.addEventListener('mouseup', DGStn_toggle, true);
   elt.addEventListener('click', DGStn_toggle, true);

   while( where.nodeName == '#text' )
      where = where.nextSibling;
//alert('where='+where.nodeName);
   /*
   if( where.tagName.toUpperCase() == 'BR' )
      where.parentNode.replaceChild(elt, where);
   else
   */
      where.parentNode.insertBefore(elt, where);

   elt.parentNode.style.textAlign = 'center';
   elt.innerHTML = "<img id=DGStnButton class=brdx alt='#'>";
   elt = elt.firstChild;
   //elt.style.margin = '0px 6px -0.2em 0px';
   elt.style.margin = '0px';
   elt.style.border = '0px solid black';
   xsrc = DGStn_stones[0].getAttribute('xsrc');
   xsrc = xsrc.replace(/\x2Fw\./i, '/b.');
   nsrc = xsrc.replace(/\x2Fb\./i, '/b99.');
   elt.setAttribute('nsrc', xsrc); //swapped for button
   elt.setAttribute('xsrc', nsrc);
   elt.src = xsrc;
   DGStn_stones[num]= elt;

   return 0; //no errors
} //DGStn_init


DGStn_prefix = getUserHandle()+':'+window.location.hostname+':';
//alert('prefix='+DGStn_prefix);

if( !DGStn_init() ) //if goes bad, keep the numbered stones
{
   if( DGStn_hidden != GM_getValue(DGStn_prefix+'hidden', false) )
      DGStn_toggle();
}
//else alert('Toggle number: board not found!');
//alert('Toggle number: found '+DGStn_stones.length+' stones');
//EOF