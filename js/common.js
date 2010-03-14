// <!--
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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

function sprintf() {
   if( sprintf.arguments.length < 2 )
      return;

   var data = sprintf.arguments[0];
   for( var i=1; i < sprintf.arguments.length; ++i )
      data = data.replace( /%s/, sprintf.arguments[i] );

   return data;
}
if( !String.sprintf )
   String.sprintf = sprintf;

function toggle_class( obj, cl1, cl2 )
{
   obj.className = ( obj.className == cl1 ) ? cl2 : cl1;
}

function showInfo( e, title, content )
{
   var infoBoxElem = document.getElementById('InfoBox');
   infoBoxElem.innerHTML = "<table><tr><th>" + title + "</th></tr>" + "<tr><td>" + content + "</td></tr></table>";

   var x, y;
   if( document.all )
   {
      x = event.clientX + document.body.scrollLeft;
      y = event.clientY + document.body.scrollTop;
   }
   else
   {
      x = e.pageX;
      y = e.pageY;
   }

   var boxStyle = infoBoxElem.style;
   boxStyle.top  = (y + 10) + 'px';
   boxStyle.left = (x + 10) + 'px';
   boxStyle.visibility = 'visible';
}

function hideInfo()
{
   document.getElementById('InfoBox').style.visibility = 'hidden';
}

// NOTE: requires global vars with translated texts: T_rankInfoTitle/Format
function showTLRankInfo( e, rank, best_rank, period_rank, history_rank )
{
   var diff1 = buildRankDiff( rank, period_rank );
   var diff2 = buildRankDiff( rank, history_rank );
   showInfo( e, T_rankInfoTitle, String.sprintf( T_rankInfoFormat, rank, best_rank, diff1, diff2 ) );
}

// see TournamentLadder::build_rank_diff()
function buildRankDiff( rank, prev_rank )
{
   if( rank == prev_rank )
      rank_diff = '=';
   else if( rank < prev_rank )
      rank_diff = '+' + (prev_rank - rank);
   else //rank > prev_rank
      rank_diff = '-' + (rank - prev_rank);
   return String.sprintf( "%s. (%s)", prev_rank, rank_diff );
}

// -->
