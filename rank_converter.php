<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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

$GLOBALS['ThePage'] = new Page('RankConverter', 0, ROBOTS_NO_FOLLOW,
   "Converting ranks for the board game Go (aka Baduk or Weichi) between ranks from different Go servers.",
   'rank, rating, dan, kyu, gup, aga, euro, japanese, chinese, korean, igs, kgs, ogs, ficgs, iyt, tygem, wbaduk' );


{
   connect2mysql();
   $logged_in = who_is_logged($player_row, LOGIN_DEFAULT_OPTS|LOGIN_SKIP_VFY_CHK );
   if ( !$logged_in )
      db_close();

   $page = "rank_converter.php";
   $rcform = RankConverter::buildForm( $page, FORM_GET );

   start_page( T_('Rank Converter'), true, $logged_in, $player_row );
   $rcform->echo_string();
   end_page();
}
?>
