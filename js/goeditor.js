/*
Dragon Go Server
Copyright (C) 2002-2003  Erik Ouchterlony

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

var number_of_gobans = 0;
var goban_numbers = [];
var version = 0;

var goban = [];
var mark = [];
var index = [];
var move_history = [];

var dirx = [-1,0,1,0];
var diry = [0,-1,0,1];
var col = ['e','b','w'];
var hoshi_dist = [0,0,0,0,0,3,0,4,3,3,3,3,4,4,4,4,4,4,4,4,4,4,4,4,4,4];
var hoshi_pos  = [0,0,0,0,0,1,0,1,4,5,4,5,4,7,7,7,7,7,7,7,7,7,7,7,7,7];

var col_next = [];
var size = [];
var stonesize = [];
var woodcolor = [];
var hoshi = [];
var lastx = [];
var lasty = [];

var move_nr = [];
var max_move_nr = [];

var startx = [];
var endx = [];
var starty = [];
var endy = [];

var current_number = [];
var current_letter = [];
var current_mode = [];
var current_index = [];

var img = 'gif';
var path = '';

var letters = ['', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n',
               'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'];

var woodbgcolors = ['', '#e8c878','#e8b878','#e8a858', '#d8b878', '#b88848'];

function init(nr)
{
   var x, y;

   goban_numbers[number_of_gobans++] = nr;

   col_next[nr] = 1;
   lastx[nr] = -1;
   lasty[nr] = -1;
   move_nr[nr] = 0;
   max_move_nr[nr] = 0;

   current_number[nr] = 1;
   current_letter[nr] = 1;
   current_mode[nr] = 'play';
   current_index[nr] = 0;

   number_mode = 'number2';

   goban[nr] = [];
   index[nr] = [];
   mark[nr] = [];
   move_history[nr] = [];

   if( size[nr] < 1 ) size[nr] = 19;
   if( size[nr] > 25 ) size[nr] = 25;

   if( startx[nr] < 0 ) startx[nr] = 0;
   if( endx[nr] > size[nr] ) endx[nr] = size[nr];
   if( starty[nr] < 0 ) starty[nr] = 0;
   if( endy[nr] > size[nr] ) endy[nr] = size[nr];


   for(x=0; x<size[nr]; x++)
   {
      goban[nr][x] = [];
      index[nr][x] = [];
      mark[nr][x] = [];
      for(y=0; y<size[nr]; y++)
      {
         goban[nr][x][y] = 0;
         index[nr][x][y] = 0;
         mark[nr][x][y] = '';
      }
   }

}

/* Function to insert HTML source for
*/
function show_goban(nr)
{
  var x, y, fig;
  var stonesz = stonesize[nr];
  var woodstring = ( woodcolor[nr] > 10
                    ? 'bgcolor=' + woodbgcolors[woodcolor[nr] - 10]
                    : 'background="' + path + 'images/wood' + woodcolor[nr] + '.gif"');

  document.write('<table border=0 cellpadding=0 cellspacing=0 '+woodstring+' align=center><tr><td valign=top><table border=0 cellpadding=0 cellspacing=0 align=center valign=center background="">');
  for( y=starty[nr]; y<endy[nr]; y++)
  {
     document.write('<tr>');
     for( x=startx[nr]; x<endx[nr]; x++ )
     {
        fig = get_empty_image(x, y, size[nr]);

        if( version == 1 )
           document.write ('<td><img name="pos'+nr+'_'+x+'_'+y+'" src="'+path+stonesz+'/'+fig+'.'+img+'" onClick="click('+nr+','+x+','+y+')">');
        else
           document.write ('<td><a href="javascript:click('+nr+','+x+','+y+');"><img name="pos'+nr+'_'+x+'_'+y+'" border=0 src="'+path+stonesz+'/'+fig+'.'+img+'" width='+stonesz+' height='+stonesz+'></a>');
     }
     document.write('</tr>');
  }

  document.write('</table></td></tr></table>');
}

function change_mode(nr, new_mode)
{
   if( current_mode[nr] == new_mode )
   {
      if( new_mode == 'play' || new_mode == 'number2' )
         change_col_next(nr, 3-col_next[nr]);

      return;
   }
   document.images[current_mode[nr]+'_'+nr+'_1'].src = path+'images/gr.png';
   document.images[current_mode[nr]+'_'+nr+'_2'].src = path+'images/gr.png';
   document.images[current_mode[nr]+'_'+nr+'_3'].src = path+'images/gr.png';
   document.images[current_mode[nr]+'_'+nr+'_4'].src = path+'images/gr.png';
   current_mode[nr] = new_mode;
   document.images[current_mode[nr]+'_'+nr+'_1'].src = path+'images/bl.png';
   document.images[current_mode[nr]+'_'+nr+'_2'].src = path+'images/bl.png';
   document.images[current_mode[nr]+'_'+nr+'_3'].src = path+'images/bl.png';
   document.images[current_mode[nr]+'_'+nr+'_4'].src = path+'images/bl.png';
}

function show_button(nr, button_mode, button_function, image, alt, width, height, border, on)
{
  var stonesz = stonesize[nr];
  document.write('<table border=0 cellpadding=0 cellspacing=0 align=center valign=center bgcolor=#fdd69b>');
   document.write('<tr><td colspan=3><img width='+(width+2*border)+' height='+border+' name="'+button_mode+'_'+nr+'_1" src="'+path+'images/'+(on ? 'bl.' : 'gr.')+img+'\"></td></tr>');
   document.write('<tr><td><img width='+border+' height='+height+' name="'+button_mode+'_'+nr+'_2" src="'+path+'images/'+(on ? 'bl.' : 'gr.')+img+'"></td>');

   if( version == 1 )
      document.write('<td width='+width+' height='+height+' align=center><img border=0 name="'+button_mode+'_'+nr+'" src="'+path+image+'" onClick="'+button_function+'('+nr+',\''+button_mode+'\')"></td>');
   else
      document.write('<td width='+width+' height='+height+' align=center><a href="javascript:'+button_function+'('+nr+',\''+button_mode+'\');"><img border=0 align=center name="'+button_mode+'_'+nr+'" src="'+path+image+'"></a></td>');

   document.write('<td align=right><img width='+border+' height='+height+' name="'+button_mode+'_'+nr+'_3" src="'+path+'images/'+(on ? 'bl.' : 'gr.')+img+'"></td></tr>');
   document.writeln('<tr><td colspan=3><img width='+(width+2*border)+' height='+border+' name="'+button_mode+'_'+nr+'_4" src="'+path+'images/'+(on ? 'bl.' : 'gr.')+img+'"></td></tr></table>');
}


function show_editor_buttons(nr)
{
   var stonesz = stonesize[nr];
   var border = Math.round(stonesz / 7);
   var sz = stonesize[nr] + 2*border;

   document.writeln("<table border=0 cellspadding=0 cellspacing=2 bgcolor=#F7F5E3>");
   document.writeln("<tr><td colspan=2>");
   show_button(nr, 'play', 'change_mode', stonesz+'/pb.'+img, 'Play', Math.round(sz*1.5), sz, border, 1);
   //   show_button(nr, 'score', 'change_mode', stonesz+'/y.'+img, 'Score', sz, sz, border, 0);
   document.writeln("</td></tr><tr><td>");
   show_button(nr, 'black', 'change_mode', stonesz+'/b.'+img, 'Black', sz, sz, border, 0);
   document.writeln("</td><td>");
   show_button(nr, 'white', 'change_mode', stonesz+'/w.'+img, 'White', sz, sz, border, 0);
   document.writeln("</td></tr><tr><td>");
   show_button(nr, 'triangle', 'change_mode', stonesz+'/bt.'+img, 'Triangle', sz, sz, border, 0);
   document.writeln("</td><td>");
   show_button(nr, 'square', 'change_mode', stonesz+'/bs.'+img, 'Square', sz, sz, border, 0);
   document.writeln("</td></tr><tr><td>");
   show_button(nr, 'circle', 'change_mode', stonesz+'/bc.'+img, 'Circle', sz, sz, border, 0);
   document.writeln("</td><td>");
   show_button(nr, 'cross', 'change_mode', stonesz+'/bx.'+img, 'Cross', sz, sz, border, 0);
   document.writeln("</td></tr><tr><td>");
   show_button(nr, 'letter', 'change_mode', stonesz+'/la.'+img, 'Letter', sz, sz, border, 0);
   document.writeln("</td><td>");
   show_button(nr, number_mode, 'change_mode', stonesz+'/b1.'+img, 'Number', sz, sz, border, 0);
   document.writeln('</td></tr><tr><td><img src="'+path+'images/blank.gif" width=1 height='+(border*2)+'></td></tr><tr><td colspan=2>');
   show_button(nr, 'undo', 'undo', stonesz+'/undo.'+img, 'Undo', Math.round(sz*1.8), Math.round(sz*0.8), border, 0);
   document.writeln("</td></tr><tr><td colspan=2>");
   show_button(nr, 'redo', 'redo', stonesz+'/redo.'+img, 'Redo', Math.round(sz*1.8), Math.round(sz*0.8), border, 0);
   document.writeln("</td></tr></table>");
}

function has_liberty(nr, start_x, start_y, remove)
{
   var c, m, dir, new_color;
   var nx, ny;
   var x = start_x;
   var y = start_y;

   current_index[nr] += 64;
   c = goban[nr][x][y]; // Color of this stone

   index[nr][x][y] = current_index[nr] + 7;


   while( true )
   {
      if( index[nr][x][y] >= current_index[nr] + 32 )  // Have looked in all directions
      {
         m = index[nr][x][y] % 8;

         if( m == 7 )   // At starting point, no liberties found
         {
            if( remove )
            {
               for( nx=0; nx<size[nr]; nx++ )
                  for( ny=0; ny<size[nr]; ny++ )
                     if( index[nr][nx][ny] >= current_index[nr] )
                     {
                        change_goban(nr, nx, ny, 0);
                     }
            }
            return false;
         }
         x -= dirx[m];  // Go back
         y -= diry[m];
      }
      else
      {
         dir = (index[nr][x][y] & 31) >> 3;
         index[nr][x][y] += 8;

         nx = x+dirx[dir];
         ny = y+diry[dir];

         if( ( nx >= 0 ) && (nx < size[nr]) && (ny >= 0) && (ny < size[nr]) )
         {
            new_color = goban[nr][nx][ny];

            if( new_color == 0 )
               return true; // found liberty

            if( new_color == c && index[nr][nx][ny] < current_index[nr] )
            {
               x = nx;  // Go to the neigbour
               y = ny;
               index[nr][x][y] = current_index[nr] + dir;
            }
         }
      }
   }
}


/* Handler for clicking on the grid
*/
function click(nr,x,y)
{
   if( x < 0 || y < 0 || x >= size[nr] || y >= size[nr] ||
       ( goban[nr][x][y] > 0 && ( current_mode[nr] == 'play' ||
                                  current_mode[nr] == 'letter' ) ) ||
       ( goban[nr][x][y] == 0 && current_mode[nr] == 'number' ) )
      return;

   move_nr[nr]++;

   if( max_move_nr[nr] == move_nr[nr] )
       max_move_nr[nr]++;  // make sure history is cleaned.

   if( lastx[nr] >= 0 && lasty[nr] >=0 && mark[nr][lastx[nr]][lasty[nr]] == 'm' &&
       !( current_mode[nr] == 'play' && goban[nr][x][y] > 0 ) )
   {
      change_last(nr, -1, -1);
   }

   old_goban = goban[nr][x][y];
   old_mark = mark[nr][x][y];


   switch( current_mode[nr] )
   {
      case 'play':
         change_goban(nr, x, y, col_next[nr]);
         change_last(nr, x, y);

         if( x > 0 && goban[nr][x-1][y] == 3-col_next[nr] )
            has_liberty(nr, x-1, y, true);

         if( y > 0 && goban[nr][x][y-1] == 3-col_next[nr] )
            has_liberty(nr, x, y-1, true);

         if( x < size[nr]-1 && goban[nr][x+1][y] == 3-col_next[nr] )
            has_liberty(nr, x+1, y, true);

         if( y < size[nr]-1 && goban[nr][x][y+1] == 3-col_next[nr] )
            has_liberty(nr, x, y+1, true);

         has_liberty(nr, x, y, true);

         change_col_next(nr, 3-col_next[nr]);
         break;

      case 'black':
         if( goban[nr][x][y] == 1 )
            change_goban(nr, x, y, 0);
         else
         {
            change_goban(nr, x, y, 1);
            change_col_next(nr, 2);
         }
         break;

      case 'white':
         if( goban[nr][x][y] == 2 )
            change_goban(nr, x, y, 0);
         else
         {
            change_goban(nr, x, y, 2);
            change_col_next(nr, 1);
         }
         break;

      case 'triangle':
         change_mark(nr, x, y, (mark[nr][x][y] == 't' ? '' : 't') );
         break;

      case 'circle':
         change_mark(nr, x, y, (mark[nr][x][y] == 'c' ? '' : 'c') );
         break;

      case 'square':
         change_mark(nr, x, y, (mark[nr][x][y] == 's' ? '' : 's') );
         break;

      case 'cross':
         change_mark(nr, x, y, (mark[nr][x][y] == 'x' ? '' : 'x') );
         break;

      case 'letter':
         change_mark(nr, x, y, 'l'+letters[current_letter[nr]]);
         increase_letter(nr);
         break;

      case 'number':
         change_mark(nr, x, y, ''+(current_number[nr]));
         increase_number(nr);
         break;

      case 'number2':
         change_goban(nr, x, y, col_next[nr]);
         change_mark(nr, x, y, ''+(current_number[nr]));
         increase_number(nr);
         change_col_next(nr, 3-col_next[nr]);
         break;

   }
}

function undo(nr, mode)
{
   var a;
   if( move_nr[nr] == 0 ) return;

   for(var i=move_history[nr][move_nr[nr]].length-1; i>=0; i--)
   {
      a = move_history[nr][move_nr[nr]][i];

      switch( a[0] )
      {
         case 'l':
            lastx[nr] = a[1];
            lasty[nr] = a[2];
            break;

         case '1':
            current_number[nr] = a[1];
            document.images[number_mode+'_'+nr].src =
               path+stonesize[nr]+'/b'+(current_number[nr])+'.'+img;
            break;

         case 'a':
            current_letter[nr] = a[1];
            document.images['letter_'+nr].src =
               path+stonesize[nr]+'/l'+letters[current_letter[nr]]+'.'+img;
            break;

         case 'g':
            goban[nr][a[1]][a[2]] = a[3];
            set_image(nr, a[1], a[2]);
            break;

         case 'm':
            mark[nr][a[1]][a[2]] = a[3];
            set_image(nr, a[1], a[2]);
            break;

         case 'n':
            col_next[nr] = a[1];
            document.images['play_'+nr].src = path+stonesize[nr]+'/p'+col[a[1]]+'.'+img;
            if( number_mode == 'number2' )
               document.images['number2_'+nr].src =
                  path+stonesize[nr]+'/'+col[a[1]]+current_number[nr]+'.'+img;
            break;
      }
   }

   move_nr[nr]--;
}

function redo(nr, mode)
{
   var a;

   if( move_nr[nr] == max_move_nr[nr] )
   {
      refresh(nr);
      return;
   }

   move_nr[nr]++;

   for(var i=0; i<move_history[nr][move_nr[nr]].length; i++)
   {
      a = move_history[nr][move_nr[nr]][i];

      switch( a[0] )
      {
         case 'l':
            lastx[nr] = a[3];
            lasty[nr] = a[4];
            break;

         case '1':
            current_number[nr] = a[2];
            document.images[number_mode+'_'+nr].src =
               path+stonesize[nr]+'/b'+(current_number[nr])+'.'+img;
            break;

         case 'a':
            current_letter[nr] = a[2];
            document.images['letter_'+nr].src =
               path+stonesize[nr]+'/l'+letters[current_letter[nr]]+'.'+img;
            break;

         case 'g':
            goban[nr][a[1]][a[2]] = a[4];
            set_image(nr, a[1], a[2]);
            break;

         case 'm':
            mark[nr][a[1]][a[2]] = a[4];
            set_image(nr, a[1], a[2]);
            break;

         case 'n':
            col_next[nr] = a[2];
            document.images['play_'+nr].src = path+stonesize[nr]+'/p'+col[a[2]]+'.'+img;
            if( number_mode == 'number2' )
               document.images['number2_'+nr].src =
                  path+stonesize[nr]+'/'+col[a[2]]+current_number[nr]+'.'+img;
            break;
      }
   }
}

function dump_data(nr, formname)
{
   var x,y;
   var string = '';
   var separator = '';

   for(y=starty[nr]; y<endy[nr]; y++)
   {
      for(x=startx[nr]; x<endx[nr]; x++)
      {
         string += separator + col[goban[nr][x][y]] + mark[nr][x][y];
         separator = ',';
      }
      separator = ';';
   }
   //   document.forms[formname].elements['dimensions'+nr].value =
   //      size[nr] +','+ (startx[nr]+1) +','+ endx[nr] + ','+ (starty[nr]+1) +','+ endy[nr];
   document.forms[formname].elements['altered'+nr].value =
       ( move_history[nr].length > 0 ? 'Y' : 'N' );
   document.forms[formname].elements['data'+nr].value = string;
}

function dump_all_data(formname)
{
   var i;

   for(i=0; i<number_of_gobans; i++)
      dump_data(goban_numbers[i], formname);
}

function get_empty_image(x, y, sz)
{
   var fig = 'e';

   if( hoshi_pos[sz] & ( ( ( x == hoshi_dist[sz]-1 || x == sz-hoshi_dist[sz] ? 2 : 0 ) +
                           ( x*2+1 == sz ? 1 : 0) ) *
                         ( ( y == hoshi_dist[sz]-1 || y == sz-hoshi_dist[sz] ? 2 : 0 ) +
                           ( y*2+1 == sz ? 1 : 0) ) ) )
      fig = 'h';

   if( y == 0 ) fig = 'u';
   if( y == sz-1 ) fig = 'd';
   if( x == 0 ) fig += 'l';
   if( x == sz-1 ) fig += 'r';

   return fig;
}

/* Function to change an image
*/
function set_image(nr, x, y)
{
   var prefix = col[goban[nr][x][y]];
//   alert('x: '+x+'  y: '+y+'   g: '+prefix+'   m: '+mark[nr][x][y]);
   if( prefix == 'e' )
      prefix = get_empty_image(x, y, size[nr]);

   if( mark[nr][x][y].charAt(0) == 'l' )
      prefix = '';

   prefix += mark[nr][x][y];

   document.images["pos"+nr+"_"+x+"_"+y].src = path+stonesize[nr]+'/'+prefix+'.'+img;
}

function push_history(nr, push_array)
{
   var i;

   if( move_nr[nr] != max_move_nr[nr] )
   {
      for( i=move_nr[nr]; i<=max_move_nr[nr]+1; i++ )
         move_history[nr][i] = [];

      max_move_nr[nr] = move_nr[nr];
   }

   move_history[nr][move_nr[nr]][move_history[nr][move_nr[nr]].length] = push_array;
   //   move_history[nr][move_nr[nr]].push(push_array);
}

function change_last(nr, x, y)
{
   push_history(nr, ['l', lastx[nr], lasty[nr], x , y]);
   if( lastx[nr] >= 0 && lasty[nr] >= 0 && mark[nr][lastx[nr]][lasty[nr]] == 'm' )
   {
      push_history(nr, ['m', lastx[nr], lasty[nr], mark[nr][lastx[nr]][lasty[nr]], '']);
      mark[nr][lastx[nr]][lasty[nr]] = '';
      set_image(nr, lastx[nr], lasty[nr]);
   }
   lastx[nr] = x;
   lasty[nr] = y;

   if( x >= 0 && y >= 0 && ( mark[nr][x][y] == '' || mark[nr][x][y].charAt(0) == 'l' ) )
   {
      push_history(nr, ['m', x, y, mark[nr][x][y], 'm']);
      mark[nr][x][y] = 'm';
      set_image(nr, x, y);
   }
}

function change_col_next(nr, val)
{
   if( val == col_next[nr] )
      return;

   if( move_nr[nr] > 0 )
      push_history(nr, ['n', col_next[nr], val]);
   col_next[nr] = val;
   document.images['play_'+nr].src = path+stonesize[nr]+'/p'+col[val]+'.'+img;
   if( number_mode == 'number2' )
      document.images['number2_'+nr].src =
         path+stonesize[nr]+'/'+col[val]+current_number[nr]+'.'+img;
}

function increase_number(nr)
{
   push_history(nr, ['1', current_number[nr], current_number[nr] + 1]);
   current_number[nr] ++;
   if( current_number[nr] > 100 )
      current_number[nr] = 1;
   document.images[number_mode+'_'+nr].src =
      path+stonesize[nr]+'/b'+(current_number[nr])+'.'+img;
}

function increase_letter(nr)
{
   push_history(nr, ['a', current_letter[nr], current_letter[nr] + 1]);
   current_letter[nr] ++;
   if( current_letter[nr] > 26 )
      current_letter[nr] = 1;
   document.images['letter_'+nr].src =
      path+stonesize[nr]+'/l'+letters[current_letter[nr]]+'.'+img;
}

function change_goban(nr, x, y, val)
{
   var m = mark[nr][x][y];


   push_history(nr, ['g', x, y, goban[nr][x][y], val]);
   goban[nr][x][y] = val;
   if( (val > 0 && (m.charAt(0) == 'l')) ||
       (( val == 0 && Number(m) >= 1 && !isNaN(Number(m))) ) )
   {
      push_history(nr, ['m', x, y, mark[nr][x][y], '']);
      mark[nr][x][y] = '';
   }

   set_image(nr, x, y);
}

function change_mark(nr, x, y, val)
{
   var g = goban[nr][x][y];
   if( g > 0 && (val.charAt(0) == 'l') ||
       g == 0 && Number(val) >= 1 && !isNaN(Number(val)) )
      return;

   push_history(nr, ['m', x, y, mark[nr][x][y], val]);
   mark[nr][x][y] = val;

   set_image(nr, x, y);
}

function refresh(nr)
{
   for(y=starty[nr]; y<endy[nr]; y++)
   {
      for(x=startx[nr]; x<endx[nr]; x++)
      {
         set_image(nr, x, y);
      }
   }

}

function enter_data(nr, data)
{
   var row;
   var data_rows = data.split(';');
   var c, v;

   for(y=starty[nr]; y<endy[nr]; y++)
   {
      row = data_rows[y-starty[nr]].split(',');

      for(x=startx[nr]; x<endx[nr]; x++)
      {
         c = row[x-startx[nr]].charAt(0);
         v = ( c == 'b' ? 1 : ( c == 'w' ? 2 : 0 ) );
         goban[nr][x][y] = v;
         mark[nr][x][y] = row[x-startx[nr]].substr(1);
         if( mark[nr][x][y] == 'm' )
             {
                 lastx[nr] = x;
                 lasty[nr] = y;
             }
         set_image(nr, x, y);
      }
   }
}

/* Main function
*/
function goeditor(nr, sz, start_x, end_x, start_y, end_y, stonesz, wood_color, subdir)
{
   path = ( subdir ? '../' : '' );

   size[nr] = sz;
   stonesize[nr] = stonesz;
   woodcolor[nr] = wood_color;

   startx[nr] = start_x-1;
   endx[nr] = end_x;
   starty[nr] = start_y-1;
   endy[nr] = end_y;

   init(nr);
   document.write("<center><table><tr><td align=center>");
   show_goban(nr);
   document.write("</td><td valign=top>");
   show_editor_buttons(nr);
   document.write("</td></tr></table></center>");
}
