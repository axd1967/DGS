// <!--
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

window.DGS = {
   constants: {},
   data: {},
   run: {} // runtime
};

// supporting %s %x %b
function sprintf() {
   if ( sprintf.arguments.length < 1 )
      throw "sprintf(): Missing arguments ["+arguments.join(',')+"]";

   var data = sprintf.arguments[0];
   for ( var i=1; i < sprintf.arguments.length; ++i ) {
      var arg = sprintf.arguments[i];
      data = data.replace(/(%[sxb])/, function(m) {
         if ( m == '%x' )
            return arg.toString(16);
         else if ( m == '%b' )
            return arg.toString(2);
         else
            return arg;
      });
   }

   return data;
}
if ( !String.sprintf )
   String.sprintf = sprintf;

function toggle_class( obj, cl1, cl2 ) {
   obj.className = ( obj.className == cl1 ) ? cl2 : cl1;
}

function showInfo( e, contentHTML, y_add ) {
   var infoBoxElem = document.getElementById('InfoBox');
   infoBoxElem.innerHTML = contentHTML;

   var x, y;
   if ( document.all ) {
      x = event.clientX + document.body.scrollLeft;
      y = event.clientY + document.body.scrollTop;
   } else {
      x = e.pageX;
      y = e.pageY;
   }

   var boxStyle = infoBoxElem.style;
   boxStyle.top  = (y + y_add + 10) + 'px';
   boxStyle.left = (x + 10) + 'px';
   boxStyle.visibility = 'visible';
}

function hideInfo()
{
   document.getElementById('InfoBox').style.visibility = 'hidden';
}

function updateRulesetDefaulKomi( e )
{
   var ruleset = e.value;
   if ( !(typeof ARR_RULESET_DEF_KOMI === 'undefined') )
   {
      // NOTE: see also PHP-function game_settings_form()
      document.getElementById('RulesetDefKomi').textContent = ARR_RULESET_DEF_KOMI[ruleset];
      document.getElementById('GSF_komi_m').value = ARR_RULESET_DEF_KOMI[ruleset];
   }
}

function updateDefaultMaxHandicap( e )
{
   var size = e.value;
   if ( !(typeof ARR_DEF_MAX_HANDICAP === 'undefined') )
   {
      // NOTE: see also PHP-function game_settings_form()
      var content = String.sprintf( T_defaultMaxHandicap, ARR_DEF_MAX_HANDICAP[size], size );
      document.getElementById('DefMaxHandicap').textContent = content;
   }
}

// ---------- Tournament-Ladder --------------------

// NOTE: requires global vars with translated texts: T_rankInfoTitle/Format
function showTLRankInfo( e, rank, best_rank, period_rank, history_rank )
{
   var diff1 = buildRankDiff( rank, period_rank );
   var diff2 = buildRankDiff( rank, history_rank );

   var content = String.sprintf( T_rankInfoFormat, rank, best_rank, diff1, diff2 );
   content = "<table><tr><th>" + T_rankInfoTitle + "</th></tr>" + "<tr><td>" + content + "</td></tr></table>";
   showInfo( e, content, 0 );
}

// see PHP TournamentLadder::build_rank_diff()
function buildRankDiff( rank, prev_rank )
{
   if ( prev_rank == 0 )
      return '---';

   var rank_diff;
   if ( rank == prev_rank )
      rank_diff = '=';
   else if ( rank < prev_rank )
      rank_diff = '+' + (prev_rank - rank);
   else //rank > prev_rank
      rank_diff = '-' + (rank - prev_rank);
   return String.sprintf( "%s. (%s)", prev_rank, rank_diff );
}


// ---------- Game-Thumbnail -----------------------

DGS.data.BASE64_CHARS = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
DGS.data.ARR_THUMBNAIL = [
      // global base_path
      // 00=empty, 01=Black, 10=White, 11=Dead-stone
      "<img src=\"" + base_path + "images/tbne.gif\" width=7 height=7>",
      "<img src=\"" + base_path + "images/tbnb.gif\" width=7 height=7>",
      "<img src=\"" + base_path + "images/tbnw.gif\" width=7 height=7>",
      "<img src=\"" + base_path + "images/tbnd.gif\" width=7 height=7>"
   ];

// shows game-thumbnail for board-size $size and given dgs-base64-encoded game $snapshot
function showGameThumbnail( e, size, snapshot )
{
   const LF = "<br>\n";
   const SPC = DGS.data.ARR_THUMBNAIL[0];
   var output = '';

   var data, data1, data2, data3, repcount;
   var p = 0; // board-pos
   var psize = size * size;
   for ( var i=0; p < psize && i < snapshot.length; i++ ) {
      var ch = snapshot.charAt(i);
      if ( ch == ' ' ) // stop for extended snapshot
         break;

      data = 0;
      if ( ch == 'A' ) // 1xA
         repcount = 1;
      else if ( ch == ':' ) // 2xA
         repcount = 2;
      else if ( ch == '%' ) // 3xA
         repcount = 3;
      else if ( ch == '#' ) // 4xA
         repcount = 4;
      else if ( ch == '@' ) // 8xA
         repcount = 8;
      else if ( ch == '*' ) // 16xA
         repcount = 16;
      else {
         data = DGS.data.BASE64_CHARS.indexOf(ch);
         data1 = (data >> 4) & 0x3;
         data2 = (data >> 2) & 0x3;
         data3 = data & 0x3;
      }

      if ( data == 0 ) {
         for ( var j=0; j < 3 * repcount; j++ ) {
            output += SPC;
            if ( ++p % size == 0 ) output += LF;
            if ( p >= psize ) break;
         }
      } else {
         output += DGS.data.ARR_THUMBNAIL[data1];
         if ( ++p % size == 0 ) output += LF;
         if ( p >= psize ) break;
         output += DGS.data.ARR_THUMBNAIL[data2];
         if ( ++p % size == 0 ) output += LF;
         if ( p >= psize ) break;
         output += DGS.data.ARR_THUMBNAIL[data3];
         if ( ++p % size == 0 ) output += LF;
      }
   }

   var first = true;
   while ( p < psize ) { // append empties
      if ( (p++ % size == 0) && !first ) output += LF;
      first = false;
      output += SPC;
   }

   var content = "<table class=\"GameThumbnail\" bgcolor=\"#e8a858\"><tr><td>" + output + "</td></tr></table>";
   showInfo( e, content, 10 );
}//showGameThumbnail

// -->
