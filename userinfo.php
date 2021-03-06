<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/globals.php';
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/time_functions.php';
require_once 'include/table_infos.php';
require_once 'include/rating.php';
require_once 'include/countries.php';
require_once 'include/contacts.php';
require_once 'include/classlib_user.php';
require_once 'include/classlib_userpicture.php';
require_once 'include/translation_functions.php';

$GLOBALS['ThePage'] = new Page('UserInfo');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if ( !$logged_in )
      error('login_if_not_logged_in', 'userinfo');

   $my_id = $player_row['ID'];
   $is_admin_dev = (@$player_row['admin_level'] & ADMIN_DEVELOPER);
   $is_game_admin = (@$player_row['admin_level'] & ADMIN_GAME);

   get_request_user( $uid, $uhandle, true);
   if ( $uhandle )
      $where = "Handle='".mysql_addslashes($uhandle)."'";
   elseif ( $uid > 0 )
      $where = "ID=$uid";
   else
      $where = "ID=$my_id";

   $row = mysql_single_fetch( "userinfo.find_player($uid,$uhandle)",
      "SELECT *"
      .",(Activity>$ActiveLevel1)+(Activity>$ActiveLevel2) AS ActivityLevel"
      //i.e. RatedWinPercent = 100*(Won+Jigo/2)/RatedGames
      .",ROUND(50*(RatedGames+Won-Lost)/RatedGames) AS RatedWinPercent"
      .",UNIX_TIMESTAMP(Registerdate) AS X_Registerdate"
      .",UNIX_TIMESTAMP(Lastaccess) AS X_Lastaccess"
      .",UNIX_TIMESTAMP(LastQuickAccess) AS X_LastQuickAccess"
      .",UNIX_TIMESTAMP(LastMove) AS X_LastMove"
      ." FROM Players WHERE $where" );

   if ( !$row )
      error('unknown_user', "userinfo.find_player2($uid,$uhandle)");
   $uid = (int)$row['ID'];
   $user_handle = $row['Handle'];
   $hide_bio = (@$row['AdminOptions'] & ADMOPT_HIDE_BIO);
   $my_info = ( $my_id == $uid );

   // load bio
   $bio_result = db_query( "userinfo.find_bio($uid)",
      "SELECT * FROM Bio WHERE uid=$uid ORDER BY SortOrder");
   $count_bio = @mysql_num_rows($bio_result);

   if ( $hide_bio )
   {
      mysql_free_result($bio_result);
      $bio_result = NULL; // hide bio
   }

   $count_mpg_run = 0; // count MP-games of user
   $show_mpg = ( $my_info || $is_admin_dev || $is_game_admin );
   if ( $show_mpg )
   {
      $gp_row = mysql_single_fetch( "userinfo.count_gameplayer($uid)",
         "SELECT COUNT(*) AS X_Count " .
         "FROM GamePlayers AS GP INNER JOIN Games AS G ON G.ID=GP.gid " .
         "WHERE GP.uid=$uid AND G.Status ".IS_RUNNING_GAME." LIMIT 1" ); // no fair-komi for MPG
      if ( $gp_row )
         $count_mpg_run = (int)$gp_row['X_Count'];
   }

   $has_contact = Contact::has_contact($my_id, $uid);

   $game_stats = User::load_game_stats_for_users( 'userinfo', $my_id, $uid );
   $diff_opps = User::load_different_opponents_for_all_games( 'userinfo', $uid );


   $name_safe = make_html_safe($row['Name']);
   $handle_safe = $row['Handle'];

   $title = ( $my_info ? T_('My user info') :
              sprintf(T_('User info for %s'), user_reference( 0, 0, '', 0, $name_safe, $handle_safe)) );

   start_page($title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $fmt_block = "<p><font color=\"red\"><b>( %s )</b></font><br>\n";
   if ( (@$row['AdminOptions'] & ADMOPT_DENY_LOGIN) )
      echo sprintf( $fmt_block, T_('Account blocked - Login denied') );

   $run_link = "show_games.php?uid=$uid";
   $run_mpg_link = "show_games.php?uid=$uid".URI_AMP.'mp=1';
   $fin_link = $run_link.URI_AMP.'finished=1';
   $rat_link = $fin_link.URI_AMP.'rated=1'.REQF_URL.'rated'; //Rated=yes
   $won_link = $rat_link.URI_AMP.'won=1'.REQF_URL.'rated,won'; //Won?=Won
   $los_link = $rat_link.URI_AMP.'won=2'.REQF_URL.'rated,won'; //Won?=Lost
   $fin_link_timeout = $fin_link.URI_AMP.'fsf10r=4'.URI_AMP.'won=2'.URI_AMP.'sf_req=10,won'; //lost by timeout


   // get player clock
   $tmpTZ = setTZ($row['Timezone']); //for get_clock_used() and local time
   $user_gmt_offset = date('O', $NOW);
   $user_localtime  = format_translated_date(DATE_FMT5, $NOW); // +timezone-name
   $user_clockused  = get_clock_used($row['Nightstart']);
   setTZ($tmpTZ);
   $img_nighttime = ( is_nighttime_clock($user_clockused) ) ? SMALL_SPACING . echo_image_nighttime(true) : '';
   $user_night_start_str = sprintf('%02d:00 - %02d:59', $row['Nightstart'], ($row['Nightstart'] + NIGHT_LEN - 1) % 24 );
   $mytime_nightclock = ($my_info) ? $user_clockused : get_clock_used($row['Nightstart']);
   $mytime_nightstart = '';
   if ( $mytime_nightclock != $user_clockused )
   {
      $mytime_nightstart = ( $row['Nightstart'] - ($mytime_nightclock - $user_clockused) + 24 ) % 24;
      $mytime_night_start_str = sprintf('%02d:00 - %02d:59',
         $mytime_nightstart, ($mytime_nightstart + NIGHT_LEN - 1) % 24 );
   }

   { //User infos
      $activity = activity_string( $row['ActivityLevel']);
      $registerdate = ( @$row['X_Registerdate'] > 0 ) ? date(DATE_FMT_YMD, $row['X_Registerdate']) : '';
      $lastaccess = ( @$row['X_Lastaccess'] > 0 ) ? date(DATE_FMT2, $row['X_Lastaccess']) : '';
      $lastquickaccess = ( @$row['X_LastQuickAccess'] > 0 ) ? date(DATE_FMT2, $row['X_LastQuickAccess']) : NO_VALUE;
      $lastmove = ( @$row['X_LastMove'] > 0 ) ? date(DATE_FMT2, $row['X_LastMove']) : '';
      $rated_win_percent = ( is_numeric($row['RatedWinPercent']) ) ? $row['RatedWinPercent'].'%' : NO_VALUE;
      $hero_ratio = User::calculate_hero_ratio( $row['GamesWeaker'], $row['Finished'], $row['Rating2'], $row['RatingStatus'] );
      $hero_img = echo_image_hero_badge($hero_ratio);
      $games_next_herolevel = User::determine_games_next_hero_level( $hero_ratio,
         $row['Finished'], $row['GamesWeaker'], $row['RatingStatus'] );
      $hero_info = build_hero_info( ($my_info || $is_admin_dev || $is_game_admin),
         $hero_ratio, $hero_img, $games_next_herolevel );

      // draw user-info fields in two separate columns
      $twoCols = true;
      $itable1 = new Table_info('user');
      $itable2 = ($twoCols) ? new Table_info('user') : $itable1;

      if ( @$row['Type'] )
         $itable1->add_sinfo( T_('Type'), build_usertype_text(@$row['Type']) );
      $itable1->add_sinfo( T_('Name'),    $name_safe );
      $itable1->add_sinfo( T_('Userid'),  $handle_safe
         . (( @$row['Adminlevel'] & ADMINGROUP_EXECUTIVE ) ? echo_image_admin(@$row['Adminlevel']) : '') );
      $itable1->add_sinfo( T_('Country'), getCountryFlagImage(@$row['Country']) );

      $itable2->add_sinfo( T_('Time zone'),       $row['Timezone'] . " [GMT$user_gmt_offset]" );
      $itable2->add_sinfo( T_('User local time'), $user_localtime );
      $night_time_str = $user_night_start_str;
      if ( $mytime_nightstart )
         $night_time_str = sprintf( '<span title="%s: %s">%s</span>',
            basic_safe(T_('User local nighttime')), basic_safe($night_time_str), $mytime_night_start_str );
      $itable2->add_sinfo( T_('Nighttime'), $night_time_str . $img_nighttime );
      if ( $is_admin_dev )
      { // show player clock
         $itable2->add_row( array(
                  'rattb' => 'class="DebugInfo"',
                  'sname' => 'used, used(change)',
                  'sinfo' => $row['ClockUsed'] .', '.$user_clockused .' ('.$row['ClockChanged'].')'
                  ));
      }

      if ( $is_game_admin && $row['RatingStatus'] == RATING_RATED )
      {
         $admin_rating = SMALL_SPACING . span('AdminLink',
            anchor("admin_rating.php?uid=$uid",
               image( $base_path.'images/edit.gif', 'E' ),
               T_('Admin user rating') ));
      }
      else
         $admin_rating = '';
      $itable1->add_sinfo( T_('Open for matches?'), make_html_safe(@$row['Open'],INFO_HTML) );
      $itable1->add_sinfo( T_('Activity'),  $activity );
      $itable1->add_sinfo( T_('Rating'),    echo_rating(@$row['Rating2'],true,$row['ID']) . $admin_rating );
      $itable1->add_sinfo( T_('Rank info'), make_html_safe(@$row['Rank'],INFO_HTML) );
      $itable1->add_sinfo( T_('Registration date'), $registerdate );
      $itable1->add_sinfo( T_('Last access'), $lastaccess );
      $itable1->add_sinfo( T_('Last client access'), $lastquickaccess );
      $itable1->add_sinfo( T_('Last move'),   $lastmove );

      $str_vac_days = T_('Vacation days left');
      $itable1->add_sinfo( ($my_info) ? anchor( "edit_vacation.php", $str_vac_days ) : $str_vac_days,
         TimeFormat::echo_day(floor($row["VacationDays"])) );
      if ( $row['OnVacation'] > 0 )
      {
         $onVacationText = TimeFormat::echo_onvacation($row['OnVacation']);
         $itable1->add_sinfo(
               T_('On vacation') . MINI_SPACING
                  . echo_image_vacation($row['OnVacation'], $onVacationText, true),
               $onVacationText,
               '', 'class=OnVacation' );
      }

      $other_info_running_games = $other_info_finished_games = '';
      if ( !$my_info )
      {
         $other_info_running_games = MED_SPACING . echo_image_opp_games( $my_id, $user_handle, /*fin*/false )
               . MINI_SPACING . span('none smaller', '%s', $game_stats['Running'], T_('Running games with opponent'));
         $other_info_finished_games = MED_SPACING . echo_image_opp_games( $my_id, $user_handle, /*fin*/true )
               . MINI_SPACING . span('none smaller', '%s', $game_stats['Finished'], T_('Finished games with opponent'));
      }
      $itable2->add_sinfo( anchor( $run_link, T_('Running games')) . $other_info_running_games,
         $row['Running'] );
      $itable2->add_sinfo( anchor( $fin_link, T_('Finished games')) . $other_info_finished_games,
         $row['Finished'] . MED_SPACING . '/ '
            . span('smaller', sprintf( '(%s)', anchor( $fin_link_timeout, T_('Games lost by timeout')))) );
      $itable2->add_sinfo( anchor( $rat_link, T_('Rated games')),    $row['RatedGames'] );
      $itable2->add_sinfo( anchor( $won_link, T_('Won games')),      $row['Won'] );
      $itable2->add_sinfo( anchor( $los_link, T_('Lost games')),     $row['Lost'] );
      $itable2->add_sinfo( T_('Rated Win %') . ' / ' . T_('Hero %'),
         $rated_win_percent . ' / ' . implode('', $hero_info) );

      if ( $diff_opps['count_all_games'] > 0 )
      {
         $itable2->add_sinfo( T_('Different opponents'),
               textWithTitle( $diff_opps['count_diff_opps_all_games'],
                     sprintf('%s (%s)', T_('Different opponents of all games'), T_('without MP-games')) )
               . MED_SPACING . '/ ' .
               textWithTitle( sprintf('%1.1f%%', 100 * $diff_opps['ratio_all_games']),
                     sprintf('%s (%s)', T_('Ratio of different opponents of all games'), T_('without MP-games') )) );
      }

      if ( $show_mpg )
      {
         $itable2->add_row( array(
               'rattb' => ($is_admin_dev && !$my_info) ? 'class="DebugInfo"' : '',
               'sname' => anchor( $run_mpg_link, T_('MP-games')),
               'sinfo' => sprintf( T_('%s (Running), %s (Setup)#mpg'), $count_mpg_run, $row['GamesMPG'] ) ));
      }

      if ( $my_info || $is_admin_dev || $is_game_admin )
      {
         $reject_timeout = (int)@$row['RejectTimeoutWin'];
         $itable2->add_row( array(
               'rattb' => (!$my_info ? 'class="DebugInfo"' : ''),
               'sname' => T_('Reject win by timeout'),
               'sinfo' => ( $reject_timeout < 0 ? T_('disabled#rwt') : sprintf(T_('%s days#rwt'), $reject_timeout) )));
      }

      $lang_text = get_language_text(@$row['Lang']);
      $itable2->add_sinfo( sprintf('%s (%s)', T_('Language'), T_('Encoding#lang') ),
         sprintf('%s (%s)', get_language_description_translated($lang_text), @$row['Lang']) );

      // show user-info
      if ( $twoCols )
      {
         echo '<table id="UserInfo"><tr><td class="UserInfo">',
            $itable1->make_table(),
            '</td><td class="UserInfo">',
            $itable2->make_table(),
            '</td></tr></table>', "\n";
      }
      else
         $itable1->echo_table();
      unset($itable1);
      unset($itable2);
   } //User infos


   if ( USERPIC_FOLDER != '' )
   {//User Picture
      echo name_anchor('pic');
      if ( is_null($bio_result) )
      {//User picture hidden by admin (together with bio)
         if ( $count_bio > 0 )
            echo '<p></p><h3 class=Header>' . T_('User picture (hidden)') . "</h3>\n";
      }
      else
      {
         list( $tmp,$tmp,$tmp, $pic_url, $pic_exists ) = UserPicture::getPicturePath($row);
         if ( $pic_exists )
            echo '<p></p><h3 class="Header">' . T_('User picture') . "</h3>\n",
               UserPicture::getImageHtml( $user_handle, false, $row['UserPicture'], -1 );
      }
   }//User Picture


   echo name_anchor('bio');
   if ( is_null($bio_result) )
   {//Bio infos hidden by admin
      if ( $count_bio > 0 )
         echo '<p></p><h3 class=Header>' . T_('Biographical info (hidden)') . "</h3>\n";
   }
   elseif ( $count_bio > 0 )
   {//Bio infos + User picture
      echo '<p></p><h3 class=Header>' . T_('Biographical info') . "</h3>\n";

      $itable= new Table_info('bio');
      $TW_ = 'T_'; // for non-const translation-texts

      while ( $row = mysql_fetch_assoc( $bio_result ) )
      {
         $cat = $row['Category'];
         if ( substr( $cat, 0, 1) == '=' )
            $cat = make_html_safe(substr( $cat, 1), INFO_HTML);
         else
         {
            $tmp = $TW_($cat); // for defined categories see 'edit_bio.php'
            if ( $tmp == $cat ) // no translation defined
               $cat = make_html_safe($cat, INFO_HTML);
            else
               $cat = $tmp;
         }
         $itable->add_sinfo( $cat,
                  //don't use add_info() to avoid the INFO_HTML here:
                  make_html_safe($row['Text'], true) );
      }

      $itable->echo_table();
      unset($itable);
   }//Bio infos
   if ( !is_null($bio_result) )
      mysql_free_result($bio_result);
   db_close();


   $menu_array = array();
   if ( $my_info )
   {
      $menu_array[T_('Edit profile')] = 'edit_profile.php';
      $menu_array[T_('Change rating & rank')] = 'edit_rating.php';
      $menu_array[T_('Change email & notifications')] = 'edit_email.php';
      $menu_array[T_('Change password')] = 'edit_password.php';
      $menu_array[T_('Edit bio')] = 'edit_bio.php';
      if ( USERPIC_FOLDER != '' )
         $menu_array[T_('Edit user picture')] = 'edit_picture.php';
      $menu_array[T_('Edit message folders')] = 'edit_folders.php';

      $days_left = floor($player_row['VacationDays']);
      $minimum_days = 7 - floor($player_row['OnVacation']);

      if ( $player_row['OnVacation'] > 0 )
      {
         if (!( $minimum_days > $days_left ||
               ( $minimum_days == $days_left && $minimum_days == 0 )))
            $menu_array[T_('Change vacation length')] = 'edit_vacation.php';
      }
      else
         if ( $days_left >= 7 )
            $menu_array[T_('Start vacation')] = 'edit_vacation.php';

      $menu_array[T_('Show my opponents')] = 'opponents.php';
   }
   else // others info
   {
      $menu_array =
         array( T_('Show opponents') => "opponents.php?uid=$uid",
                T_('Invite this user') => "message.php?mode=Invite".URI_AMP."uid=$uid",
                T_('Send message to user') => "message.php?mode=NewMessage".URI_AMP."uid=$uid" );
      if ( !$my_info )
      {
         $w_hdl = urlencode($handle_safe);
         $b_hdl = urlencode($player_row['Handle']);
         if ( $player_row['Rating2'] > @$row['Rating2'] )
            swap( $w_hdl, $b_hdl );
         $menu_array[T_('Show rating changes')] = "rating_changes.php?w=$w_hdl".URI_AMP."b=$b_hdl";
      }

      if ( $has_contact >= 0 )
      {
         $cstr = ( $has_contact ) ? T_('Edit contact') : T_('Add contact');
         $menu_array[$cstr] = "edit_contact.php?cid=$uid";
      }
   }

   if ( $is_admin_dev )
   {
      $menu_array[T_('Admin user')] =
         array( 'url' => 'admin_users.php?show_user=1'.URI_AMP.'user='.urlencode($user_handle),
                'class' => 'AdminLink' );
      $menu_array[T_('Admin user contributions')] =
         array( 'url' => "admin_contrib.php?edit=new".URI_AMP."uid=$uid", 'class' => 'AdminLink' );
   }
   if ( $uid > GUESTS_ID_MAX && Bulletin::is_bulletin_admin() )
   {
      $menu_array[T_('New bulletin')] =
         array( 'url' => "admin_bulletin.php?n_uid=$uid", 'class' => 'AdminLink' );
   }

   end_page(@$menu_array);
}//main


function build_hero_info( $show_info, $hero_ratio, $hero_img, $games_next_herolevel )
{
   $info = array();

   // always show badge (if $show_info=true), but ratio only for own-info or if badge awarded (or for admin)
   if ( $hero_ratio > 0 )
   {
      $str = ( $hero_img ) ? $hero_img.' ' : '';
      if ( $show_info || 100*$hero_ratio >= HERO_BRONZE )
      {
         $str .= sprintf('<span title="%s">%1.1f%%</span>',
            basic_safe(T_('Percentage of games with weaker players')), 100*$hero_ratio);
      }
      else
         $str .= NO_VALUE;
      $info[] = $str;
   }
   else
      $info[] = NO_VALUE;

   if ( $show_info )
   {
      if ( $games_next_herolevel < 0 )
      {
         $info[] = MED_SPACING .
            sprintf('<span class="smaller" title="%s">[%s]</span>',
               sprintf( T_('Need rating and min. %s finished games (%s%% with weaker players >1k-diff) to get hero badge'),
                  MIN_FIN_GAMES_HERO_AWARD, HERO_BRONZE ),
               MIN_FIN_GAMES_HERO_AWARD );
      }
      elseif ( $games_next_herolevel > 0 )
      {
         $info[] = span('smaller', $games_next_herolevel, MED_SPACING.'[%s]',
            T_('Games with weaker players >1k-diff needed to reach next hero badge level') );
      }
   }

   return $info;
}//build_hero_info

?>
