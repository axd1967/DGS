<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( "include/timezones.php" );
require_once( "include/rating.php" );
require_once( "include/form_functions.php" );


function find_category_box_text($cat)
{
   global $categories;

   if( in_array($cat, array_keys($categories)) )
      return $cat;
   else
      return 'Other:';
}

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $result = mysql_query("SELECT * FROM Bio where uid=" . $player_row["ID"]);


   $categories = array( 'Other:' => T_('Other:'),
                        'Country' => T_('Country'),
                        'City' => T_('City'),
                        'State' => T_('State'),
                        'Club' => T_('Club'),
                        'Homepage' => T_('Homepage'),
                        'Email' => T_('Email'),
                        'ICQ-number' => T_('ICQ-number'),
                        'Game preferences' => T_('Game preferences'),
                        'Hobbies' => T_('Hobbies'),
                        'Occupation' => T_('Occupation') );



   start_page(T_("Edit biopgraphical info"), true, $logged_in, $player_row );



   echo "<CENTER>\n";
   $bio_form = new Form( 'bioform', 'change_bio.php', FORM_POST );

   while( $row = mysql_fetch_array( $result ) )
   {
      $cat = find_category_box_text($row["Category"]);

      $bio_form->add_row( array( 'SELECTBOX', "category".$row["ID"], 1,
                                 $categories, $cat, false,

                                 'BR',

                                 'TEXTINPUT', "other".$row["ID"], 15, 40,
                                 ($cat == "Other:" ? $row["Category"] : "" ),

                                 'TD',

                                 'TEXTAREA', "text".$row["ID"],40,4,$row["Text"] ) );
    }

// And now three empty ones:

   for($i=1; $i<=3; $i++)
   {
      $bio_form->add_row( array( 'SELECTBOX', "newcategory" . $i, 1,
                            $categories, 'Other:', false,

                            'BR',

                            'TEXTINPUT', "newother" . $i, 15, 40, "",

                            'TD',

                            'TEXTAREA', "newtext" . $i, 40, 4, "" ) );
   }

   $bio_form->add_row( array( 'SUBMITBUTTON', 'action', T_('Change bio') ) );
   $bio_form->echo_string();

   echo "</CENTER><BR>\n";

   end_page();
}
?>
