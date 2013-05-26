<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

/*!
 * \file mail_functions.php
 *
 * \brief Some helper functions for mail-sending.
 */


if ( !function_exists('html_entity_decode') ) //Does not exist on dragongoserver.sourceforge.net
{
   //HTML_SPECIALCHARS or HTML_ENTITIES, ENT_COMPAT or ENT_QUOTES or ENT_NOQUOTES
   $local_reverse_htmlentities_table = get_html_translation_table(HTML_ENTITIES, ENT_QUOTES);
   $local_reverse_htmlentities_table = array_flip($local_reverse_htmlentities_table);

   function html_entity_decode($str, $quote_style=ENT_COMPAT, $charset='ISO-8859-1')
   {
      global $local_reverse_htmlentities_table;
      return strtr($str, $local_reverse_htmlentities_table);
   }
}


function mail_link( $nam, $lnk )
{
   $nam = trim($nam);
   $lnk = trim($lnk);
   if ( $lnk )
   {
      if ( strcspn($lnk,":?#") == strcspn($lnk,"?#")
          && !is_numeric(strpos($lnk,'//'))
          && strtolower(substr($lnk,0,4)) != "www."
        )
      {
         //make it absolute to this server
         while ( substr($lnk,0,3) == '../' )
            $lnk = substr($lnk,3);
         $lnk = HOSTBASE.$lnk;
      }
      $nam = ($nam) ? "$nam ($lnk)" : $lnk;
   }
   if ( !$nam )
      return '';
   $nam = trim( str_replace("\\\"","\"", $nam) );
   return "[ $nam ]";
}//mail_link


//to be used as preg_exp. see also make_html_safe()
global $strip_html_table; //PHP5
$tmp = '[\\x1-\\x20]*=[\\x1-\\x20]*(\"|\'|)([^>\\x1-\\x20]*?)';
$strip_html_table = array(
    "%&nbsp;%si" => " ",
    "%<A([\\x1-\\x20]+((href$tmp\\4)|(\w+$tmp\\7)|(\w+)))*[\\x1-\\x20]*>(.*?)</A>%sie"
       => "mail_link('\\10','\\5')",
    "%</?(UL|BR)[\\x1-\\x20]*/?\>%si"
       => "\n",
    "%</CENTER[\\x1-\\x20]*/?\>\n?%si"
       => "\n",
    "%\n?<CENTER[\\x1-\\x20]*/?\>%si"
       => "\n",
    "%</?P[\\x1-\\x20]*/?\>%si"
       => "\n\n",
    "%[\\x1-\\x20]*<LI[\\x1-\\x20]*/?\>[\\x1-\\x20]*%si"
       => "\n - ",
   );

function mail_strip_html( $str )
{
   global $strip_html_table;

   //keep replaced tags
   $str = strip_tags( $str, '<a><br><p><center><ul><ol><li><goban>');
   $str = preg_replace( array_keys($strip_html_table), array_values($strip_html_table), $str);
   //remove remainding tags
   $str = strip_tags( $str, '<goban>');
   $str = html_entity_decode( $str, ENT_QUOTES, 'iso-8859-1');
   return $str;
}//mail_strip_html

?>
