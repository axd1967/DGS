<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

 /* The code in this file is written by Ragnar Ouchterlony */

require_once( "include/std_functions.php" );
require_once( "include/tournament.php" );

{

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   if( !isset($_POST['name'],$_POST['description']) )
     error("tournament_error_message_to_be_decided_later");

   $new_tour = Tournament::Create( $_POST['name'], $_POST['description'], $player_row['ID'] );

   jump_to("show_tournament.php?tid=" . $new_tour->ID . URI_AMP."modify=t");
}
?>
