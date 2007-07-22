<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival, Ragnar Ouchterlony

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


/**
 * $level=0 print the start of the container and intialize the function
 * $level=-1 print the end of the container
 * $args is the '<A $args></A>' arguments which will enclose the $Qtxt
 **/
function faq_item_html( $level=2, $Qtext='', $Atext='', $args='')
{
   static $first;
   $str = '';
   switch( $level )
   {
      case -1: {
         if( !$first )
            $str = "</ul>\n";
      } break;
      case 0: {
         $first = -1;
      } break;
      case 1: {
         $tmp = make_html_safe( T_( $Qtext ), 'cell');
         if( $args )
            $tmp = "<A $args>$tmp</A>";
         $str = "<strong>$tmp</strong><p></p>\n";
         if( $first >= 0 )
            $str = "<hr><p></p>".$str;
         if( $first == 0 )
            $str = "</ul>\n".$str;
         $first = 1;
      } break;
      default: {
         $tmp = make_html_safe( T_( $Qtext ), 'cell');
         if( $args )
            $tmp = "<A $args>$tmp</A>";
         $str = "<strong>$tmp</strong><p></p>\n";
         $tmp = make_html_safe( T_( $Atext ), 'faq');
         $str.= "$tmp<br>&nbsp;<p></p>";
         $str = "<li>$str</li>\n";
         if( $first )
            $str = "<ul class=\"FAQsection\">\n".$str;
         $first = 0;
      } break;
   }
   return $str;
}

?>
