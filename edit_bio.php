<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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

require( "include/std_functions.php" );
require( "include/timezones.php" );
require( "include/rating.php" );
require( "include/form_functions.php" );

$categories = array( T_('Other:') => T_('Other:'),
                     T_('Country') => T_('Country'),
                     T_('City') => T_('City'),
                     T_('State') => T_('State'),
                     T_('Club') => T_('Club'),
                     T_('Homepage') => T_('Homepage'),
                     T_('Email') => T_('Email'),
                     T_('ICQ-number') => T_('ICQ-number'),
                     T_('Game preferences') => T_('Game preferences'),
                     T_('Hobbies') => T_('Hobbies'),
                     T_('Occupation') => T_('Occupation') );

function find_category_box_text($cat)
{
   global $categories;

   if( in_array($cat, $categories) )
      return $cat;
   else
      return T_('Other:');
}

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");


   $result = mysql_query("SELECT * FROM Bio where uid=" . $player_row["ID"]);

   start_page(T_("Edit biopgraphical info"), true, $logged_in, $player_row );

   echo "<CENTER>\n";
   echo form_start( 'bioform', 'change_bio.php', 'POST' );

   while( $row = mysql_fetch_array( $result ) )
   {
      $cat = find_category_box_text($row["Category"]);

      echo form_insert_row( 'SELECTBOX', "category".$row["ID"],1,$categories, $cat, false,
                            'BR','BR',
                            'TEXTINPUT', "other".$row["ID"],15,40,
                            ($cat == "Other:" ? $row["Category"] : "" ),
                            'TD',
                            'TEXTAREA', "text".$row["ID"],40,4,$row["Text"] );
    }

// And now three empty ones:

   for($i=1; $i<=3; $i++)
   {
      echo form_insert_row( 'SELECTBOX', "newcategory".$i,1,$categories, 'Other:', false,
                            'BR','BR',
                            'TEXTINPUT', "newother".$i,15,40, "",
                            'TD',
                            'TEXTAREA', "newtext".$i,40,4,"" );
   }

   echo form_insert_row( 'SUBMITBUTTON', 'action', T_('Change bio') );
   echo form_end();
   echo "</CENTER><BR>\n";

   end_page();
}
?>
