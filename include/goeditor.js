
var number_of_gobans = 0;
var goban_numbers = [];
var version = 0;

var goban = [];
var mark = [];
var index = [];

var dirx = [-1,0,1,0];
var diry = [0,-1,0,1];
var col = ['e','b','w'];
var hoshi_dist = [0,0,0,0,0,3,0,4,3,3,3,3,4,4,4,4,4,4,4,4,4,4,4,4,4,4];
var hoshi_pos  = [0,0,0,0,0,1,0,1,4,5,4,5,4,7,7,7,7,7,7,7,7,7,7,7,7,7];

var col_next = [];
var size = [];
var stonesize = [];
var hoshi = [];
var lastx = [];
var lasty = [];

var startx = [];
var endx = [];
var starty = [];
var endy = [];

var current_number = [];
var current_letter = [];
var current_mode = [];
var current_index = [];

var img = 'gif';

var letters = ['', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n',
               'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'];
function init(nr)
{
   var x, y;

   goban_numbers[number_of_gobans++] = nr;

   col_next[nr] = 1;
   lastx[nr] = -1;
   lasty[nr] = -1;

   current_number[nr] = 1;
   current_letter[nr] = 1;
   current_mode[nr] = 'play';
   current_index[nr] = 0;

   goban[nr] = [];
   index[nr] = [];
   mark[nr] = [];

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

  document.write('<table border=0 cellpadding=0 cellspacing=0 background="images/wood2.gif" align=center><tr><td valign=top><table border=0 cellpadding=0 cellspacing=0 align=center valign=center background="">');
  for( y=starty[nr]; y<endy[nr]; y++)
  {
     document.write('<tr>');
     for( x=startx[nr]; x<endx[nr]; x++ )
     {
        fig = get_empty_image(x, y, size[nr]);

        if( version == 1 )
           document.write ('<td><img name="pos'+nr+'_'+x+'_'+y+'" src="'+stonesz+'/'+fig+'.'+img+'" onClick="click('+nr+','+x+','+y+')">');
        else
           document.write ('<td><a href="javascript:click('+nr+','+x+','+y+');"><img name="pos'+nr+'_'+x+'_'+y+'" border=0 src="'+stonesz+'/'+fig+'.'+img+'" width='+stonesz+' height='+stonesz+'></a>');
     }
     document.write('</tr>');
  }

  document.write('</table></td></tr></table>');
}

function change_mode(nr, new_mode)
{
   document.images[current_mode[nr]+'_'+nr+'_1'].src = 'images/gr.png';
   document.images[current_mode[nr]+'_'+nr+'_2'].src = 'images/gr.png';
   document.images[current_mode[nr]+'_'+nr+'_3'].src = 'images/gr.png';
   document.images[current_mode[nr]+'_'+nr+'_4'].src = 'images/gr.png';
   current_mode[nr] = new_mode;
   document.images[current_mode[nr]+'_'+nr+'_1'].src = 'images/bl.png';
   document.images[current_mode[nr]+'_'+nr+'_2'].src = 'images/bl.png';
   document.images[current_mode[nr]+'_'+nr+'_3'].src = 'images/bl.png';
   document.images[current_mode[nr]+'_'+nr+'_4'].src = 'images/bl.png';
}

function show_button(nr, button_mode, image, alt, on)
{
  var stonesz = stonesize[nr];
  document.write('<td><table border=0 cellpadding=0 cellspacing=0 align=center valign=center bgcolor=#fdd69b>');
   document.write('<tr><td colspan=3><img width='+(stonesz+20)+' height=5 name="'+button_mode+'_'+nr+'_1" src="images/'+(on ? 'bl.png':'gr.png')+'\"></td></tr>');
   document.write('<td><img width=5 height='+(stonesz+10)+' name="'+button_mode+'_'+nr+'_2" src="images/'+(on ? 'bl.png':'gr.png')+'"></td>');

   if( version == 1 )
      document.write('<td width='+(stonesz+10)+' height='+(stonesz+10)+' align=center><img width='+stonesz+' height='+stonesz+' border=0 hspace=5 vspace=5 name="'+button_mode+'_'+nr+'" src="'+image+'" onClick="change_mode('+nr+',\''+button_mode+'\')"></td>');
   else
      document.write('<td width='+(stonesz+10)+' height='+(stonesz+10)+' align=center><a href="javascript:change_mode('+nr+',\''+button_mode+'\');"><img width='+stonesz+' height='+stonesz+' border=0 hspace=5 vspace=5 name="'+button_mode+'_'+nr+'" src="'+image+'"></a></td>');

   document.write('<td><img width=5 height='+(stonesz+10)+' name="'+button_mode+'_'+nr+'_3" src="images/'+(on ? 'bl.png':'gr.png')+'"></td></tr>');
   document.writeln('<tr><td colspan=3><img width='+(stonesz+20)+' height=5 name="'+button_mode+'_'+nr+'_4" src="images/'+(on ? 'bl.png':'gr.png')+'"></td></tr></table>');
  document.write('</td>');
}

function show_editor_buttons(nr)
{
   var stonesz = stonesize[nr];
   document.writeln("<table border=1 cellspadding=0 cellspacing=2 bgcolor=#F7F5E3><tr>\n");
   show_button(nr, 'play', stonesz+'/b.'+img, 'Play', 1);
   show_button(nr, 'score', stonesz+'/y.'+img, 'Score');
   document.writeln("</tr><tr>");
   show_button(nr, 'triangle', stonesz+'/bt.'+img, 'Triangle');
   show_button(nr, 'square', stonesz+'/bs.'+img, 'Square');
   document.writeln("</tr><tr>");
   show_button(nr, 'circle', stonesz+'/bc.'+img, 'Circle');
   show_button(nr, 'cross', stonesz+'/bx.'+img, 'Cross');
   document.writeln("</tr><tr>");
   show_button(nr, 'letter', stonesz+'/la.'+img, 'Letter');
   show_button(nr, 'number', stonesz+'/b1.'+img, 'Number');
   document.writeln("</tr></table>");
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
                        goban[nr][nx][ny] = 0;
                        setImage(nr, nx, ny, 'e');
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
   if( x < 0 || y < 0 || x >= size[nr] || y >= size[nr] )
      return;

   if( lastx[nr] >= 0 && lasty[nr] >=0 && mark[nr][lastx[nr]][lasty[nr]] == 'm' &&
       !( current_mode[nr] == 'play' && goban[nr][x][y] > 0 ) )
   {
      mark[nr][lastx[nr]][lasty[nr]] = '';
      setImage(nr, lastx[nr], lasty[nr]);
      lastx[nr] = lasty[nr] = -1;
   }

   switch( current_mode[nr] )
   {
      case 'play':
         if( goban[nr][x][y] > 0 ) return;
         if( mark[nr][x][y] == '' || mark[nr][x][y].charAt(0) == 'l')
            mark[nr][x][y] = 'm';

         goban[nr][x][y] = col_next[nr];

         if( x > 0 && goban[nr][x-1][y] == 3-col_next[nr] )
            has_liberty(nr, x-1, y, true);

         if( y > 0 && goban[nr][x][y-1] == 3-col_next[nr] )
            has_liberty(nr, x, y-1, true);

         if( x < size[nr]-1 && goban[nr][x+1][y] == 3-col_next[nr] )
            has_liberty(nr, x+1, y, true);

         if( y < size[nr]-1 && goban[nr][x][y+1] == 3-col_next[nr] )
            has_liberty(nr, x, y+1, true);

         has_liberty(nr, x, y, true);

         col_next[nr] = 3-col_next[nr];
         lastx[nr] = x;
         lasty[nr] = y;
         break;

      case 'triangle':
         mark[nr][x][y] = ( mark[nr][x][y] == 't' ? '' : 't' );
         break;

      case 'circle':
         mark[nr][x][y] = ( mark[nr][x][y] == 'c' ? '' : 'c' );
         break;

      case 'square':
         mark[nr][x][y] = ( mark[nr][x][y] == 's' ? '' : 's' );
         break;

      case 'cross':
         mark[nr][x][y] = ( mark[nr][x][y] == 'x' ? '' : 'x' );
         break;

      case 'letter':
         if( goban[nr][x][y] > 0 ) return;

         if( mark[nr][x][y] == 'l'+letters[current_letter[nr]-1] )
         {
            mark[nr][x][y] = '';
            current_letter[nr]--;
         }
         else
         {
            mark[nr][x][y] = 'l'+letters[current_letter[nr]];
            current_letter[nr]++;
         }
         document.images['letter_'+nr].src = stonesize[nr]+'/l'+letters[current_letter[nr]]+'.'+img;
         break;

      case 'number':
         if( goban[nr][x][y] == 0 ) return;

         if( mark[nr][x][y] == ''+(current_number[nr]-1) )
         {
            mark[nr][x][y] = '';
            current_number[nr]--;
         }
         else
         {
            mark[nr][x][y] = ''+current_number[nr];
            current_number[nr]++;
         }
         document.images['number_'+nr].src = stonesize[nr]+'/b'+current_number[nr]+'.'+img;
         break;

   }

   setImage(nr, x, y);
}

function dump_data(nr)
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
   document.forms['goeditor'+nr].dimensions.value = size[nr] +','+ startx[nr] +','+ endx[nr] +
      ','+ starty[nr] +','+ endy[nr];
   document.forms['goeditor'+nr].data.value = string;
}

function dump_all_data()
{
   var i;

   for(i=0; i<number_of_gobans; i++)
      dump_data(goban_numbers[i]);
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
function setImage(nr, x, y)
{
   var prefix = col[goban[nr][x][y]];

   if( prefix == 'e' )
      prefix = get_empty_image(x, y, size[nr]);

   if( mark[nr][x][y].charAt(0) == 'l' )
      prefix = '';

   prefix += mark[nr][x][y];

   document.images["pos"+nr+"_"+x+"_"+y].src=stonesize[nr]+'/'+prefix+'.'+img;
}


/* Main function
*/
function goeditor(nr, sz, stonesz, start_x, end_x, start_y, end_y)
{
/*image_preload();*/

   size[nr] = sz;
   stonesize[nr] = stonesz;
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
