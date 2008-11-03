<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival, Ragnar Ouchterlony

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


/**
 * $level=0 print the start of the container and intialize the function
 * $level=-1 print the end of the container
 * $attbs is the '<A $attbs></A>' arguments which will enclose the $Qtxt
 **/
function faq_item_html( $level=2, $Qtext='', $Atext='', $attbs='', $rx_term='' )
{
   static $prevlevel= 0;

   $str = '';
   switch( $level )
   {
      case -1: {
         if( $prevlevel > 1 )
            $str.= "\n</ul>";
         if( $prevlevel > 0 )
            $str.= "\n</div>\n";
         $level = 0;
      } //break;
      case 0: {
      } break;
      case 1: {
         $tmp = make_html_safe( $Qtext, 'cell', $rx_term );
         if( !$tmp )
            $tmp = UNKNOWN_VALUE;
         if( $attbs )
            $tmp = "<A $attbs>$tmp</A>";
         $itm = "<strong class=Rubric>$tmp</strong>";

         if( $prevlevel > 1 )
            $str.= "\n</ul>";
         if( $prevlevel > 0 )
            $str.= "\n<hr></div>\n";

         //if( $prevlevel < 1 )
            $str.= "\n<div class=FAQlevel1>";
         $str.= "\n$itm";
      } break;
      default: {
         $tmp = make_html_safe( $Qtext, 'cell', $rx_term );
         if( !$tmp )
            $tmp = UNKNOWN_VALUE;
         if( $attbs )
            $tmp = "<A $attbs>$tmp</A>";
         $itm = "<strong class=Question>$tmp</strong>";

         $tmp = make_html_safe( $Atext, 'faq', $rx_term );
         if( $tmp )
            $itm.= "<br>\n<div class=Answer>$tmp</div>";

         if( $prevlevel < 1 )
            $str.= "\n<div class=FAQlevel1>";
         if( $prevlevel < 2 )
            $str.= "\n<ul class=FAQlevel2>";
         $str.= "\n<li>$itm</li>";
      } break;
   }
   $prevlevel = $level;
   return $str;
}

// returns true, if passed question and answer-text matches regex-terms
function search_faq_match_terms( $Qtext, $Atext, $rx_term )
{
   if( (string)$rx_term == '')
      return false;
   $tmp = make_html_safe( $Qtext, 'cell', $rx_term );
   if( contains_mark_terms( $tmp ) )
      return true;
   $tmp = make_html_safe( $Atext, 'faq', $rx_term );
   if( contains_mark_terms( $tmp ) )
      return true;
   return false;
}

function TD_button( $title, $href, $isrc, $ialt)
{
   //image( $src, $alt, $title='', $attbs='', $height=-1, $width=-1)
   $str = image( $isrc, $ialt, $title);
   //anchor( $href, $text, $title='', $attbs='')
   $str = anchor( $href, $str);
   $str = "<td class=Button>$str</td>\n";
   return $str;
}

// returns error-message, or '' if query-terms are ok to be searched for
function check_faq_search_terms( $query_terms )
{
   $qterms = trim( preg_replace( "/\s+/", ' ', $query_terms ) );
   if( (string)$qterms != '' )
   {
      $arr_words = explode(' ', $qterms );
      foreach( $arr_words as $term )
      {
         if( strlen($term) < 2 )
            return sprintf( T_('Search term [%s] too short#FAQ'), $term );
      }
   }
   return '';
}

// build regex from query-term:
// - spaces = separate alternatives
// - otherwise no special characters
function build_regex_term( $query_term )
{
   $regex = preg_quote( $query_term );
   $regex = preg_replace( "/\s+/", '|', $regex );
   return ( (string)$regex != '' ) ? "($regex)" : '';
}

?>
