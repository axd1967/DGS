// This script requires GreaseMonkey 0.3 or later

// ==UserScript==
// @name        DGS Section Hide
// @namespace   http://www.dragongoserver.net/goodies
// @description Allow to hide/show some page sections.
// @include     *dragongoserver.net/status.php*
// @include     *dragongoserver.sourceforge.net/status.php*
// ==/UserScript==


/* <scriptinfos>
 Actually, it only works in the Status page.

 Tested with Win98 + FireFox 1.0.7 + GreaseMonkey 0.5.3
             WinXP + FireFox 2.0.0.1 + GreaseMonkey 0.6.7.20070131

 Creator:
   Rodival (rodival)
      http://www.dragongoserver.net/userinfo.php?uid=1056
 Contributors:

 Version 0.1.1.20070224: rodival
   better way to handle custom properties.
 Version 0.1.0.20070223: rodival
   first version
</scriptinfos> */


//alert('start');
var DGSsh_divs = new Array();
var DGSsh_prefix = '';


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

function createButton(func, tit, alt, width, height, src, bgcol) {
   var img, but;
   img = document.createElement('img');
   img.alt = alt;
   img.width = width;
   img.height = height;
   img.src = src;
   img.style.backgroundColor = bgcol;
   img.style.border = "1px outset "+bgcol;
/*
   img.style.borderTop = img.style.borderLeft = "1px solid #ccc";
   img.style.borderRight = img.style.borderBottom = "1px solid #888";
*/
   img.style.margin = "0px 4px";
   img.style.verticalAlign = "top !important";

   but = document.createElement('a');
   but.title = tit;
   but.href = '#';
   but.addEventListener('click', func, true);
   but.appendChild(img);
   return but;
}

function getbgcolor(elt)
{
   var col = '';
   while( elt != undefined )
   {
      col = getComputedStyle(elt, '');
      col= col.backgroundColor;
      if( col == undefined || col == 'transparent' )
         col = '';
      if( col > '' )
         break;
      elt= elt.parentNode;
   }
   return col;
}

var DGSsh_toggle = function(event) {
//alert('e='+typeof(event));
   if( event == undefined )
      return;
   if( typeof(event) != 'object' )
      return;

   var link, gmvar, div, but, hidden;

   if( event.currentTarget == undefined )
   {
      link = event;
      if( link.getAttribute('_gmvar') == undefined )
         return;
      gmvar = link.getAttribute('_gmvar');
      hidden = GM_getValue(gmvar, false); //reset state
   }
   else
   {
      event.preventDefault();
      event.stopPropagation();
      link = event.currentTarget;
      if( link.getAttribute('_gmvar') == undefined )
         return;
      gmvar = link.getAttribute('_gmvar');
      hidden = !GM_getValue(gmvar, false); //toggle state
      GM_setValue(gmvar, hidden);
   }
//alert('e.gmvar='+gmvar+'='+hidden);

   link.blur();
   div = DGSsh_divs[link.getAttribute('_div')];
   but = link.firstChild;
   if( hidden )
   {
      but.src= but.getAttribute('_hsrc');
      but.alt= but.getAttribute('_halt');
      but.title= but.getAttribute('_htit');
      div.style.display= 'none';
   }
   else
   {
      but.src= but.getAttribute('_ssrc');
      but.alt= but.getAttribute('_salt');
      but.title= but.getAttribute('_stit');
      div.style.display= 'block';
   }
//alert('e.x');
} //DGSsh_toggle


var DGSsh_init = function()
{
   var sects, elt, node;
   sects = xPath(document.documentElement,
               "//td[@id='pageBody']");
   if( sects.snapshotLength <= 0 )
      sects = xPath(document.documentElement,
                  "//td[@id='page_body']");
   if( sects.snapshotLength <= 0 )
      return 1;

   sects = xPath(sects.snapshotItem(0),
               "//h3");
               //"h3 | form//h3[1]");
   if( sects.snapshotLength <= 1 )
      return 2;

   var splus= 'data:image/gif;base64,'+
         'R0lGODdhCAAIAIEAAQAAAFVVVaqqqv///yH5BAEAAAMALAAAAAAIAAgAAQgbAAcI'+
         'HEhwAAAABQ0iHHiw4UKHBwlGLDgx4cCAADs=';
   var smnus= 'data:image/gif;base64,'+
         'R0lGODdhCAAIAIEAAQAAAFVVVaqqqv///yH5BAEAAAMALAAAAAAIAAgAAQgVAAcI'+
         'HEiwoEGCABIqFKhw4cGHEAP+ADs=';
/*
   var scros= 'data:image/gif;base64,'+
         'R0lGODdhCAAIAIIAAf////8AAP9VVQAAAAAAAAAAAAAAAAAAACH5BAEAAAAALAAA'+
         'AAAIAAgAAgggAAEIHDgwQACBBhEGECAgocCGCwlCFFBwIUSFGAkSDPgAOw==';
         //'R0lGODdhCAAIAIIAAQAAAAAA/wD/AAD///8AAP8A////AP///yH5BAEAAAcALAAA'+
         //'AAAIAAgAAggdAA8IHDiQAAGBBhEaXFiQYcOEChcePAARIsGBAf8AOw==';
*/

   //alert('h3='+sects.snapshotLength);
   for( var i=sects.snapshotLength-1; i>=0 ; i--)
   {
      node = sects.snapshotItem(i);
      //alert('h3.0='+node.textContent);

      var id = '';
      var div = document.createElement('div');
      while( elt=node.nextSibling )
      {
         //alert('d0.1='+elt.nodeName);
         if( id == '' && elt.id != undefined )
            id = elt.id;
         if( elt.nodeName.substr(0,1).toUpperCase() == 'H' ) //'HR' or 'H3'
            break;
         div.appendChild(elt); //move it, so remove it from node.nextSibling
         //alert('d0.2='+id);
      }
      //alert('h3.1='+div.innerHTML);
      node.parentNode.insertBefore(div, node.nextSibling);
      if( id == '' )
         id = i;
      //alert('h3.2='+id);

      var but= getbgcolor(node);
      if( but == '' )
         but = '#F7F5e3'; // #F7F5E3 page background-color
      but= createButton(DGSsh_toggle, 'Hide section', '-', 8, 8, smnus, but);
      but.setAttribute('_gmvar', DGSsh_prefix+id+'.hidden'); //GM var name
      DGSsh_divs[i] =div;
      but.setAttribute('_div', i);
      elt= but.firstChild;
      //alert('h3.3');
      elt.setAttribute('_ssrc', smnus);
      elt.setAttribute('_salt', '-');
      elt.setAttribute('_stit', 'Hide section');
      elt.setAttribute('_hsrc', splus);
      elt.setAttribute('_halt', '+');
      elt.setAttribute('_htit', 'Show section');

      node.insertBefore(but, node.firstChild);
      //alert('h3.4='+DGSsh_divs[but.getAttribute('_div')].innerHTML);
      elt= GM_getValue(but.getAttribute('_gmvar'), false); //fails if var is reseted with about:config??
      //alert('h3.5='+elt);
      if( elt == undefined )
         elt= false;
      if( elt )
         DGSsh_toggle(but); //reset state

      //alert('h3.6='+but.getAttribute('_div'));
   }
} //DGSsh_init


DGSsh_prefix = getUserHandle()+':'+
            window.location.hostname+window.location.pathname+':';
//alert('prefix='+DGSsh_prefix);

DGSsh_init();
//alert('end');