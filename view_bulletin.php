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

$TranslateGroups[] = "Bulletin";

require_once 'include/std_functions.php';
require_once 'include/std_classes.php';
require_once 'include/table_columns.php';
require_once 'include/db/bulletin.php';
require_once 'include/gui_bulletin.php';

$GLOBALS['ThePage'] = new Page('BulletinView');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('login_if_not_logged_in', 'view_bulletin');
   $my_id = $player_row['ID'];

   $bid = (int) @$_REQUEST['bid'];
   if( $bid < 0 )
      error('invalid_args', "view_bulletin.check_args($bid)");

   $page = "view_bulletin.php";

   // init
   $qsql = Bulletin::build_query_sql( $bid );
   $qsql->merge( Bulletin::build_view_query_sql( false, /*count*/false, '', /*check*/true ) );
   list( $bulletin, $orow ) = Bulletin::load_bulletin_by_query( $qsql, /*withrow*/true );
   if( is_null($bulletin) )
      error('unknown_bulletin', "view_bulletin.find_bulletin1($bid)");
   if( (int)@$orow['B_View'] <= 0 )
      error('no_view_bulletin', "view_bulletin.find_bulletin2($bid)");

   // mark bulletin as read + reload (for recount remaining bulletins)
   $markread = (int)get_request_arg('mr');
   if( $markread > 0 )
   {
      Bulletin::mark_bulletin_as_read( $markread );
      jump_to("$page?bid=$bid");
   }


   $title = sprintf( T_('Bulletin View #%d'), $bid );
   start_page($title, true, $logged_in, $player_row );
   echo "<h3 class=Header>". $title . "</h3>\n";

   $mark_as_read_url = ( !@$orow['BR_Read'] && $bulletin->Status == BULLETIN_STATUS_SHOW ) ? "$page?bid=$bid" : '';
   echo "<br>\n", GuiBulletin::build_view_bulletin($bulletin, $mark_as_read_url);


   $menu_array = array();
   $menu_array[T_('Unread Bulletins')] = "list_bulletins.php?text=1".URI_AMP."view=1".URI_AMP."no_adm=1";

   end_page(@$menu_array);
}

?>
