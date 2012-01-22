<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Game";

require_once 'include/rank_converter.php';


{
   connect2mysql();
   $logged_in = who_is_logged($player_row);
   if( !$logged_in )
      db_close();

   $page = "rank_converter.php";
   $rcform = RankConverter::buildForm( $page, FORM_GET );

   start_page( T_('Rank Converter'), true, $logged_in, $player_row );
   $rcform->echo_string();
   end_page();
}
?>
