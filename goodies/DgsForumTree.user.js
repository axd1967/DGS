// This script requires GreaseMonkey 0.3 or later

// ==UserScript==
// @name        DGS Forum Thread Manager
// @namespace   http://www.dragongoserver.net/goodies
// @description Allow to hide/show/glue some forum-thread branches.
// @include     *dragongoserver.net/forum/read.php*
// @include     *dragongoserver.sourceforge.net/forum/read.php*
// ==/UserScript==


/* DGS infos : <scriptinfos>

 Allow to organise the posts of a thread in a DGS forum.

 Add two buttons to each message :
 - a Collapse/Expand button to collapse the message and hide
     all its responses.
 - a Glue/Free button to prevent a message from being hidden.

 The messages with a "new" flag are automatically glued at
   the opening of the thread page.


 Tested with WinXP + FireFox 3.6.4 + GreaseMonkey 0.8.20100408.6

 Creator:
   Rodival (rodival)
      http://www.dragongoserver.net/userinfo.php?uid=1056
 Contributors:

 Version 0.1.0.20100624: rodival
   first version
</scriptinfos> */


//alert('start');

function xpath(query, node) {
    if (node == null) node = document;
    return document.evaluate(query, node, null
      , XPathResult.UNORDERED_NODE_SNAPSHOT_TYPE, null);
}
function xpathSingle(query, node) { return xpath(query, node).snapshotItem(0); }
/*
function xpathText  (query, node) { return xpathSingle(query, node).textContent; }
function id(s) { return document.getElementById(s); }
*/
function parentTag(node, tag) {
   while ( node != undefined && node.tagName != tag )
      node = node.parentNode;
   return node;
}

function butState(name, node) {
   return xpathSingle(".//img[@"+name+"]", node).getAttribute(name) != 0;
}

/*
function argSplit(s) {
   var out = new Array();
   s = s.split('?')[1];
   s = s.split('#')[0];
   s = s.split('&');
   for (i = 0; i < s.length; i++) {
      out.push(s[i].split('='));
   }
   return out;
} //argSplit

function getbgcolor(elt)
{
   var col = '';
   while ( elt != undefined )
   {
      col = getComputedStyle(elt, '');
      col= col.backgroundColor;
      if ( col == undefined || col == 'transparent' )
         col = '';
      if ( col > '' )
         break;
      elt= elt.parentNode;
   }
   return col;
}
*/

//---------------------------------------------

//datas[state] := [title, alt, backgroundColor, border, src]
var butHideDat = [ [ //when expanded :
   'Collapse', '-', 'transparent', '1px outset white',
   'data:image/gif;base64,'+ //imgMinus
      'R0lGODdhCAAIAIEAAQAAAFVVVaqqqv///yH5BAEAAAMALAAAAAAIAAgAAQgVAAcI'+
      'HEiwoEGCABIqFKhw4cGHEAP+ADs='
   ] , [ //when collapsed :
   'Expand', '+', 'transparent', '1px outset white',
   'data:image/gif;base64,'+ //imgPlus
      'R0lGODdhCAAIAIEAAQAAAFVVVaqqqv///yH5BAEAAAMALAAAAAAIAAgAAQgbAAcI'+
      'HEhwAAAABQ0iHHiw4UKHBwlGLDgx4cCAADs='
   ] ];
var butGlueDat = [ [ //when not glued :
   'Glue it', '.', 'transparent', '1px outset white',
   'data:image/gif;base64,'+ //imgSpot
      'R0lGODlhCAAIAIABAAAAAP///yH5BAEAAAEALAAAAAAIAAgAAAIJjI+pCsC+ojQFADs='
   ] , [ //when glued :
   'Free it', 'x', '#000000', '1px inset white',
   'data:image/gif;base64,'+ //imgCross
      'R0lGODdhCAAIAIIAAf////8AAP9VVQAAAAAAAAAAAAAAAAAAACH5BAEAAAAALAAA'+
      'AAAIAAgAAgggAAEIHDgwQACBBhEGECAgocCGCwlCFFBwIUSFGAkSDPgAOw=='
      //'R0lGODdhCAAIAIIAAQAAAAAA/wD/AAD///8AAP8A////AP///yH5BAEAAAcALAAA'+
      //'AAAIAAgAAggdAA8IHDiQAAGBBhEaXFiQYcOEChcePAARIsGBAf8AOw=='
   ] ];
/*
var ScrosImg= 'data:image/gif;base64,'+
      'R0lGODdhCAAIAIIAAf////8AAP9VVQAAAAAAAAAAAAAAAAAAACH5BAEAAAAALAAA'+
      'AAAIAAgAAgggAAEIHDgwQACBBhEGECAgocCGCwlCFFBwIUSFGAkSDPgAOw==';
      //'R0lGODdhCAAIAIIAAQAAAAAA/wD/AAD///8AAP8A////AP///yH5BAEAAAcALAAA'+
      //'AAAIAAgAAggdAA8IHDiQAAGBBhEaXFiQYcOEChcePAARIsGBAf8AOw==';
*/


/*
var Account = xpathText("//a[@id='loggedId']");
var PagePrefix = Account+':'+
            window.location.hostname+':'+
            window.location.pathname+':'+
            window.location.search+':'+
            window.location.hash+':';
//alert('prefix='+PagePrefix);
var PostSnap;
*/


/* Create a button (not yet clickable).
   Had to be completed with the setting of the default state.
*/
function butCreate(width, height) {
   var img;
   img = document.createElement('img');
   img.width = width;
   img.height = height;
   img.style.margin = "0px 4px 0px 0px";

   //the next line had been removed because it seems impossible
   // to "cloneNode" this property. See butInsert()
   //img.addEventListener('click', func, true);
   return img;
} //butCreate

/*
   img.alt = alt;
   img.title = tit;
   img.setAttribute(type, stt);
   img.src = src;
   img.style.verticalAlign = "top !important";
   img.style.backgroundColor = bgcol;
   img.style.border = "1px outset "+bgcol;
   img.style.borderTop = img.style.borderLeft = "1px solid #ccc";
   img.style.borderRight = img.style.borderBottom = "1px solid #888";
*/


/* Change the appearence of a button */
function butFace(but, dat) {
   but.title= dat[0];
   but.alt= dat[1];
   but.style.backgroundColor = dat[2];
   but.style.border = dat[3];
   but.src= dat[4];
} //butFace

function butGlueState(but, state) {
//console.log(">butGlueState(",but.alt,",", state,")");

   state = state ? 1 : 0;
   //set the state
   but.setAttribute('_butGlue', state);
   // and the appearence to "un-state"
   butFace(but, butGlueDat[state]);

//console.log("<butGlueState(",but.alt,",", but.getAttribute('_butGlue'),")");
} //butGlueState

function butHideState(but, state) {
//console.log(">butHideState(",but.alt,",", state,")");

   state = state ? 1 : 0;
   //set the state
   but.setAttribute('_butHide', state);
   // and the appearence to "un-state"
   butFace(but, butHideDat[state]);

//console.log("<butHideState(",but.alt,",", but.getAttribute('_butHide'),")");
} //butHideState


/* Hide/show a node.
   N.B.: Suppose that the node is displayed on the first call
     to record its correct display value.
*/
var nodHide = function(nod, state) {
//console.log("nD="+nod+'='+nod.style.display);
   if ( nod == undefined || nod.style.display == undefined )
      return;
   var onAtb = nod.getAttribute('_display');
   if ( onAtb == undefined ) {
      onAtb = nod.style.display;
      nod.setAttribute('_display', onAtb);
   }
   if ( state ) {
//console.log("nD=off");
      nod.style.display = 'none';
   } else {
//console.log("nD=ON");
      nod.style.display = onAtb;
   }
} //nodHide


/* Show/hide the body of the message */
var msgCollapse = function(msg, state) {
   var snap, n;
   snap = xpath(".//tr[contains(@class,'PostHead') = false]", msg);
   n = snap.snapshotLength;
//alert("nmsg="+n);
   for (i = 0; i < n; i++)
       nodHide(snap.snapshotItem(i), state);

   snap = xpath(".//img[@_butHide]", msg);
   n = snap.snapshotLength;
//alert("nhid="+n);
   for (i = 0; i < n; i++)
      butHideState(snap.snapshotItem(i), state);
} //msgCollapse


/* Returns the size of the indentation of the (message) row. */
var rowIndent = function(tr) {
   var snap = xpath(".//td[contains(@class,'Indent')]", tr);
   var n = snap.snapshotLength;
   var indent = 0;
   for (i = 0; i < n; i++) {
      indent += snap.snapshotItem(i).colSpan;
         //alert("col="+t+'='+typeof(t));
         //      if ( typeof(t) == 'number' )
   }
//alert("nind="+n+'='+indent);
   return indent;
} //rowIndent


/* Toggles a branch of the thread */
var branchToggle = function(event) {
//alert('e='+typeof(event));

   if ( event == undefined )
      return true;
   if ( typeof(event) != 'object' )
      return true;

   var img, collapse=false;

   if ( event.currentTarget == undefined )
   {
alert('eu='+event.currentTarget);
      img = event;
      collapse = false; //state reset
   }
   else
   {
//alert('ed='+event.currentTarget);
      event.preventDefault();
      event.stopPropagation();
      img = event.currentTarget;
      //img.blur(); img = img.firstChild;
      //collapse = (img.alt == '-'); //state toggle
      collapse = (img.getAttribute('_butHide') == 0); //state toggle
   }
//console.log('e.collapse=',collapse);

   //Find the TABLE of the message and its TR in the thread layout
   var msg = parentTag(img, 'TABLE');
   if ( msg == undefined )
      return true;
   var row = parentTag(msg, 'TR');
   if ( row == undefined )
      return true;

   //Show/hide the body of the message
   msgCollapse(msg, collapse);

   //Show/hide the responses to the message
   //  i.e. the sibling rows with a bigger indentation
   var indent = rowIndent(row) + 1; //min child indent
   while ( (row = row.nextSibling ) != undefined ) {
      var lvl = rowIndent(row) - indent;
      if ( lvl < 0 ) //sibling or parent message
         break;
/* ** Reactions of the children of the message being shown/hidden **
           \         not glued           :           glued            :
   collapse \ FirstLevel  : HighterLevel : FirstLevel  : HighterLevel :
   ---------:-------------:--------------:-------------:--------------:
    1-close : row- & msg- : row- & msg-  :        msg- :        msg-  :
    0+open  : row+        : nothing      : row+        : nothing      :
*/
      if ( collapse ) {
         if ( !butState('_butGlue', row) )
            nodHide(row, true); //hide the whole row
         msgCollapse(row, true);
      }
      else if ( lvl == 0 )
         nodHide(row, false); //show the whole row

      /* collapse \ FirstLevel  : HighterLevel
          1-close : row-        : row-
          0+open  : msg- & row+ : nothing
      */
      /*
      if ( !collapse ) {
         if ( lvl > 0 )
            continue;
         //var sub = xpathSingle("//table", row);
         //if ( !butState('_butGlue', row) )
            msgCollapse(row, true);
         nodHide(row, false); //show the whole row
      }
      else if ( !butState('_butGlue', row) )
         nodHide(row, true); //hide the whole row
      */
   }
//alert('e.x');
   return false;
} //branchToggle


/* Toggles the glue of a message */
function glueToggle(event) {
//alert('g='+typeof(event));

   if ( event == undefined )
      return true;
   if ( typeof(event) != 'object' )
      return true;

   var img, state="";

   if ( event.currentTarget == undefined )
   {
alert('gu='+event.currentTarget);
      img = event;
      state = false; //state reset
   }
   else
   {
//alert('ed='+event.currentTarget);
      event.preventDefault();
      event.stopPropagation();
      img = event.currentTarget;
      //img.blur(); img = img.firstChild;
      //state = (img.alt == '-'); //state toggle
      state = (img.getAttribute('_butGlue') == 0); //state toggle
   }
//console.log('g.state=',state);

   //toggle the state
   butGlueState(img, state);

//alert('g.x');
   return false;
} //glueToggle

var butGlue = butCreate(8, 8);
      //butGlueState( butGlue, false); //set in butInsert()
var butHide = butCreate(8, 8);
      butHideState( butHide, false); //default common state

/* Insert the buttons before the ref node */
var butInsert = function(ref)
{
   /* Initially, butCreate was containing the addEventListener line :
         but.addEventListener('click', branchToggle, true);
      but then...
    * cloneNode seems to not copy(activate?) the addEventListener
      so this is insuffisant :
         ref.parentNode.insertBefore(butHide.cloneNode(true), ref);
    * this try is worst... the button don't appear :
         ref.parentNode.insertBefore(butHide, ref);
    * creating a new button each time it is needed, works fine :
         var but = butCreate(...);
         ref.parentNode.insertBefore(but, ref);
    * finally, I have choose to remove the addEventListener line
      from butCreate and add it after the cloneNode, here :
    */
   var but = butHide.cloneNode(true);
   //butHideState( but, false); //yet the default state
   but.addEventListener('click', branchToggle, true);
   ref.parentNode.insertBefore(but, ref);

   //if the Newflag exists, the message had to be glued
   // alas, this will not  resist to a post or preview action.
   //TODO: record somewhere the buttons' states
   var headerRow = parentTag( ref, 'TR');
   var newFlag = xpath(".//*[contains(@class,'NewFlag')]",
                        headerRow).snapshotLength > 0;

   but = butGlue.cloneNode(true);
   butGlueState( but, newFlag);
   but.addEventListener('click', glueToggle, true);
   ref.parentNode.insertBefore(but, ref);

   return;

/* trash (a try to record the state of collapsed/expanded/glued parts...)
   but.setAttribute('_gmvar', PagePrefix+id+'.hidden'); //GM var name
   but.setAttribute('_divnb', divcnt);
   divcnt++;

   node.insertBefore(but, node.firstChild);
   elt= GM_getValue(but.getAttribute('_gmvar'), false); //fails if var is reseted with about:config??
   //alert('h3.5='+elt);
   if ( elt == undefined )
      elt= false;
   if ( elt )
      branchToggle(but); //reset state
*/
} //butInsert


/* Insert the buttons in each message header */
var butInstall = function()
{
   var PostSnap = xpath("//table[@id='forumRead']//a[@class='PostSubject']");
   var n = PostSnap.snapshotLength;
//alert('n='+n);
   if ( n <= 0 )
      return 1;

/*
   var elt = PostSnap.snapshotItem(0).search; //or .href because it's a link
   var elt = window.location.search;
   var args = argSplit(elt);
   var str = 'args='+elt+'\n';
   for (i = 0; i < args.length; i++)
      str = str + i + '=' + args[i].join("#") + '\n';
   alert(str);
*/

/*
   var outset= getbgcolor(node);
   if ( outset == '' )
      outset = 'white'; //'#F7F5e3'; // #F7F5E3 DGS page background-color
*/

   //Insert the buttons before the subject link
   //N.B.: also transfert the Newflag to the butGlue button
   for (i = 0; i < n; i++)
       butInsert(PostSnap.snapshotItem(i));

   return 0;
} //butInstall


//butInstall();
//window.onload = butInstall;
window.addEventListener('load', butInstall, false);
//alert('end');
