<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( "include/gui_functions.php" );
require_once( "include/std_classes.php" );
require_once( "include/countries.php" );
require_once( "include/rating.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/filter.php" );
require_once( "include/filterlib_country.php" );
require_once( "include/classlib_profile.php" );
require_once( 'include/classlib_userconfig.php' );
require_once( 'include/classlib_userpicture.php' );

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $uid = $player_row['ID'];
   //$user = $player_row['Handle'];
   $cfg_tblcols = ConfigTableColumns::load_config( $uid, CFGCOLS_USERS );

   $page = "users.php?";

   // observers of game
   $observe_gid = (int)get_request_arg('observe');

   // config for usertype-filter
   $query_usertype = 'Type>0 AND (Type & %d)';
   $usertypes_array = array(
         T_('All')      => '',
         T_('Special')  => 'Type>0',
         T_('Pro')      => sprintf( $query_usertype, USERTYPE_PRO),
         T_('Teacher')  => sprintf( $query_usertype, USERTYPE_TEACHER),
         T_('Robot')    => sprintf( $query_usertype, USERTYPE_ROBOT),
         //T_('Team')     => sprintf( $query_usertype, USERTYPE_TEAM),
         );

   // init search profile
   $profile_type = ($observe_gid) ? PROFTYPE_FILTER_OBSERVERS : PROFTYPE_FILTER_USERS;
   $search_profile = new SearchProfile( $uid, $profile_type );
   $ufilter = new SearchFilter( '', $search_profile );
   $search_profile->register_regex_save_args( 'name|user|active' ); // named-filters FC_FNAME
   $utable = new Table( 'user', $page, $cfg_tblcols, '', TABLE_ROW_NUM|TABLE_ROWS_NAVI );
   $utable->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // table filters
   $ufilter->add_filter( 1, 'Numeric', 'P.ID', true);
   $ufilter->add_filter( 2, 'Text',    'P.Name', true,
         array( FC_FNAME => 'name', FC_SIZE => 12, FC_STATIC => 1, FC_START_WILD => STARTWILD_OPTMINCHARS ));
   $ufilter->add_filter( 3, 'Text',    'P.Handle', true,
         array( FC_FNAME => 'user', FC_SIZE => 12, FC_STATIC => 1, FC_START_WILD => STARTWILD_OPTMINCHARS ));
   //$ufilter->add_filter( 4, 'Text',    'P.Rank', true); # Rank info (don't use here, no index)
   $ufilter->add_filter( 5, 'Rating',  'P.Rating2', true);
   //$ufilter->add_filter( 6, 'Text',    'P.Open', true); # Open for matches (don't use here, no index)
   $ufilter->add_filter( 7, 'Numeric', 'Games', true,    # =P.Running+P.Finished
         array( FC_SIZE => 4, FC_ADD_HAVING => 1 ));
   $ufilter->add_filter( 8, 'Numeric', 'P.Running', true,
         array( FC_SIZE => 4 ));
   $ufilter->add_filter( 9, 'Numeric', 'P.Finished', true,
         array( FC_SIZE => 4 ));
   $ufilter->add_filter(10, 'Numeric', 'P.Won', true,
         array( FC_SIZE => 4 ));
   $ufilter->add_filter(11, 'Numeric', 'P.Lost', true,
         array( FC_SIZE => 4 ));
   $ufilter->add_filter(13, 'Boolean', "P.Activity>$ActiveLevel1", true,
         array( FC_FNAME => 'active', FC_LABEL => T_('Active'), FC_STATIC => 1,
                FC_DEFAULT => ($observe_gid ? 0 : 1) ) );
   $ufilter->add_filter(14, 'RelativeDate', 'P.Lastaccess', true);
   $ufilter->add_filter(15, 'RelativeDate', 'P.LastMove', true,
         array( FC_TIME_UNITS => FRDTU_DHM ));
   $ufilter->add_filter(16, 'Country', 'P.Country', false,
         array( FC_HIDE => 1 ));
   $ufilter->add_filter(17, 'Numeric', 'P.RatedGames', true,
         array( FC_SIZE => 4 ));
   $ufilter->add_filter(18, 'Selection', $usertypes_array, true );
   $ufilter->add_filter(19, 'Boolean', "P.UserPicture", true );
   $f_active =& $ufilter->get_filter(13);

   $ufilter->init(); // parse current value from _GET

   // init table
   $utable->register_filter( $ufilter );
   $utable->add_or_del_column();

   if( $observe_gid )
   {
      $rp = new RequestParameters();
      $rp->add_entry( 'observe', $observe_gid );
      $utable->add_external_parameters( $rp, true );
   }

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   // table: use same table-IDs as in opponents.php(!)
   // NOTE: The TABLE_NO_HIDEs are needed, because the columns are needed
   //       for the "static" filtering(!) of: Activity; also see named-filters
   $utable->add_tablehead( 1, T_('ID#header'), 'Button', TABLE_NO_HIDE, 'ID+');
   $utable->add_tablehead(18, T_('Type#header'), 'Enum', 0, 'Type+');
   if( USERPIC_FOLDER != '' )
      $utable->add_tablehead(19, new TableHead( T_('User picture#header'),
         'images/picture.gif', T_('Indicator for existing user picture') ), 'Image', 0, 'UserPicture+' );
   $utable->add_tablehead( 2, T_('Name#header'), 'User', 0, 'Name+');
   $utable->add_tablehead( 3, T_('Userid#header'), 'User', 0, 'Handle+');
   $utable->add_tablehead(16, T_('Country#header'), 'Image', 0, 'Country+');
   $utable->add_tablehead( 4, T_('Rank info#header'), null, TABLE_NO_SORT );
   $utable->add_tablehead( 5, T_('Rating#header'), 'Rating', 0, 'Rating2-');
   $utable->add_tablehead( 6, T_('Open for matches?#header'), null, TABLE_NO_SORT );
   $utable->add_tablehead( 7, T_('#Games#header'), 'Number', 0, 'Games-');
   $utable->add_tablehead( 8, T_('Running#header'), 'Number', 0, 'Running-');
   $utable->add_tablehead( 9, T_('Finished#header'), 'Number', 0, 'Finished-');
   $utable->add_tablehead(17, T_('Rated#header'), 'Number', 0, 'RatedGames-');
   $utable->add_tablehead(10, T_('Won#header'), 'Number', 0, 'Won-');
   $utable->add_tablehead(11, T_('Lost#header'), 'Number', 0, 'Lost-');
   $utable->add_tablehead(12, T_('Percent#header'), 'Number', 0, 'Percent-');
   $utable->add_tablehead(13, T_('Activity#header'), 'Image', TABLE_NO_HIDE, 'ActivityLevel-');
   $utable->add_tablehead(20, new TableHead( T_('User online#header'),
      'images/online.gif', sprintf( T_('Indicator for being online up to %s mins ago'), SPAN_ONLINE_MINS) ), 'Image', 0 );
   $utable->add_tablehead(14, T_('Last access#header'), 'Date', 0, 'Lastaccess-');
   $utable->add_tablehead(15, T_('Last move#header'), 'Date', 0, 'LastMove-');

   $utable->set_default_sort( 1); //on ID
   $order = $utable->current_order_string();
   $limit = $utable->current_limit_string();

   // build SQL-query
   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_FIELDS,
      'P.*', 'P.Rank AS Rankinfo',
      "(P.Activity>$ActiveLevel1)+(P.Activity>$ActiveLevel2) AS ActivityLevel",
      'P.Running+P.Finished AS Games',
      //i.e. Percent = 100*(Won+Jigo/2)/RatedGames
      'ROUND(50*(RatedGames+Won-Lost)/RatedGames) AS Percent',
      'UNIX_TIMESTAMP(P.Lastaccess) AS LastaccessU',
      'UNIX_TIMESTAMP(P.LastMove) AS LastMoveU' );
   $qsql->add_part( SQLP_FROM, 'Players AS P' );

   if( $observe_gid )
      $qsql->add_part( SQLP_FROM, "INNER JOIN Observers AS OB ON P.ID=OB.uid AND OB.gid=$observe_gid" );

   $query_ufilter = $utable->get_query(); // clause-parts for filter
   $qsql->merge( $query_ufilter );
   $query = $qsql->get_select() . "$order$limit";

   $result = db_query( 'users.find_data', $query );
   $show_rows = $utable->compute_show_rows(mysql_num_rows($result));
   $utable->set_found_rows( mysql_found_rows('users.found_rows') );


   if( $f_active->get_value() )
      $title = T_('Active users');
   else
      $title = T_('Users');
   if( $observe_gid )
   {
      $title .= ' - '
         . sprintf( T_('Observers of game %s'),
                    "<a href=\"game.php?gid=$observe_gid\">$observe_gid</a>" );
   }


   start_page( $title, true, $logged_in, $player_row,
               button_style($player_row['Button']) );
   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query);

   echo "<h3 class=Header>$title</h3>\n";


   while( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
   {
      $ID = $row['ID'];

      $urow_strings = array();
      if( $utable->Is_Column_Displayed[1] )
         $urow_strings[1] = button_TD_anchor( "userinfo.php?uid=$ID", $ID );
      if( $utable->Is_Column_Displayed[2] )
         $urow_strings[2] = "<A href=\"userinfo.php?uid=$ID\">" .
            make_html_safe($row['Name']) . "</A>";
      if( $utable->Is_Column_Displayed[3] )
         $urow_strings[3] = "<A href=\"userinfo.php?uid=$ID\">" . $row['Handle'] . "</A>";
      if( $utable->Is_Column_Displayed[16] )
         $urow_strings[16] = getCountryFlagImage( @$row['Country'] );
      if( $utable->Is_Column_Displayed[4] )
         $urow_strings[4] = make_html_safe(@$row['Rankinfo'],INFO_HTML);
      if( $utable->Is_Column_Displayed[5] )
         $urow_strings[5] = echo_rating(@$row['Rating2'],true,$ID);
      if( $utable->Is_Column_Displayed[6] )
         $urow_strings[6] = make_html_safe($row['Open'],INFO_HTML);
      if( $utable->Is_Column_Displayed[7] )
         $urow_strings[7] = $row['Games'];
      if( $utable->Is_Column_Displayed[8] )
         $urow_strings[8] = $row['Running'];
      if( $utable->Is_Column_Displayed[9] )
         $urow_strings[9] = $row['Finished'];
      if( $utable->Is_Column_Displayed[17] )
         $urow_strings[17] = $row['RatedGames'];
      if( $utable->Is_Column_Displayed[10] )
         $urow_strings[10] = $row['Won'];
      if( $utable->Is_Column_Displayed[11] )
         $urow_strings[11] = $row['Lost'];
      if( $utable->Is_Column_Displayed[12] )
      {
         $percent = ( is_numeric($row['Percent']) ? $row['Percent'].'%' : '' );
         $urow_strings[12] = $percent;
      }
      if( $utable->Is_Column_Displayed[13] )
      {
         $activity = activity_string( $row['ActivityLevel']);
         $urow_strings[13] = $activity;
      }
      if( $utable->Is_Column_Displayed[14] )
      {
         $lastaccess = ($row['LastaccessU'] > 0 ? date(DATE_FMT2, $row['LastaccessU']) : '' );
         $urow_strings[14] = $lastaccess;
      }
      if( $utable->Is_Column_Displayed[15] )
      {
         $lastmove = ($row['LastMoveU'] > 0 ? date(DATE_FMT2, $row['LastMoveU']) : '' );
         $urow_strings[15] = $lastmove;
      }
      if( $utable->Is_Column_Displayed[18] )
         $urow_strings[18] = build_usertype_text($row['Type'], ARG_USERTYPE_NO_TEXT, true, ' ');
      if( @$row['UserPicture'] && $utable->Is_Column_Displayed[19] )
         $urow_strings[19] = UserPicture::getImageHtml( @$row['Handle'], true );
      if( $utable->Is_Column_Displayed[20] )
      {
         $is_online = ($NOW - @$row['LastaccessU']) < SPAN_ONLINE_MINS * 60; // online up to X mins ago
         $urow_strings[20] = echo_image_online( $is_online, @$row['LastaccessU'], false );
      }

      $utable->add_row( $urow_strings );
   }
   mysql_free_result($result);
   $utable->echo_table();

   // end of table

   $menu_array = array(
      T_('Show my opponents') => "opponents.php" );

   end_page(@$menu_array);
}
?>
