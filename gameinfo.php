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

$TranslateGroups[] = "Game";

require_once( 'include/std_functions.php' );
require_once( 'include/std_classes.php' );
require_once( 'include/table_columns.php' );
require_once( 'include/table_infos.php' );
require_once( 'include/time_functions.php' );
require_once( 'include/rating.php' );

$ThePage = new Page('GameInfo');


// Status: enum('INVITED','PLAY','PASS','SCORE','SCORE2','FINISHED')
// -> INVITED, RUNNING, FINISHED
function build_game_status( $status )
{
   return ( $status === 'INVITED' || $status === 'FINISHED' ) ? $status : 'RUNNING';
}

function build_rating_diff( $rating_diff )
{
   if( isset($rating_diff) )
      return ( $rating_diff > 0 ? '+' : '' ) . sprintf( "%0.2f", $rating_diff / 100 );
   else
      return '';
}


{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   $my_id = $player_row['ID'];
   $is_admin = (@$player_row['admin_level'] & ADMIN_DEVELOPER);

   $gid = (int) get_request_arg('gid', 0);
   if( $gid < 1 )
      error('unknown_game', "gameinfo($gid)");

   // load game-values
   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_FIELDS,
      'Games.*',
      'BP.Name AS Black_Name', 'BP.Handle AS Black_Handle',
      'BP.Rating2 AS Black_Rating', 'BP.OnVacation AS Black_OnVacation',
      'WP.Name AS White_Name', 'WP.Handle AS White_Handle',
      'WP.Rating2 AS White_Rating', 'WP.OnVacation AS White_OnVacation',
      'BRL.RatingDiff AS Black_RatingDiff',
      'WRL.RatingDiff AS White_RatingDiff',
      'UNIX_TIMESTAMP(Starttime) AS X_Starttime',
      'UNIX_TIMESTAMP(Lastchanged) AS X_Lastchanged',
      "IF(Games.Rated='N','N','Y') AS X_Rated"
      );
   $qsql->add_part( SQLP_FROM,
      'Games',
      'INNER JOIN Players AS BP ON BP.ID=Games.Black_ID',
      'INNER JOIN Players AS WP ON WP.ID=Games.White_ID',
      'LEFT JOIN Ratinglog AS BRL ON BRL.gid=Games.ID AND BRL.uid=Games.Black_ID',
      'LEFT JOIN Ratinglog AS WRL ON WRL.gid=Games.ID AND WRL.uid=Games.White_ID' );
   $qsql->add_part( SQLP_WHERE,
      'Games.ID='.$gid );
   $query = $qsql->get_select() . ' LIMIT 1';

   $grow = mysql_single_fetch( "gameinfo.find($gid)", $query );
   if( !$grow )
      error('unknown_game', "gameinfo($gid)");

   // init some vars
   $is_my_game = ( $my_id == $grow['Black_ID'] || $my_id == $grow['White_ID'] );
   $arr_status = array(
      'INVITED'  => T_('Inviting'),
      'RUNNING'  => T_('Running'),
      'FINISHED' => T_('Finished'),
      );
   $status = build_game_status($grow['Status']);
   $game_finished = ( $grow['Status'] === 'FINISHED' );


   // ------------------------
   // build table-info: game settings

   $itable = new Table_info('game');
   $itable->add_row( array( 'caption' => T_('Game settings') ));
   $itable->add_row( array(
         'sname' => T_('Game ID'),
         'sinfo' => anchor( "{$base_path}game.php?gid=$gid", "#$gid" ),
         ));
   if( $is_my_game && $grow['mid'] > 0 )
   {
      $itable->add_row( array(
            'sname' => T_('Message'),
            'sinfo' => anchor( "{$base_path}message.php?mode=ShowMessage".URI_AMP.'mid='.$grow['mid'],
                               T_('Show invitation') ),
            ));
   }
   $itable->add_row( array(
         'sname' => T_('Status'),
         'sinfo' => $arr_status[$status] .
                    ( $is_admin ? " (<span class=\"DebugInfo\">{$grow['Status']}</span>)" : ''),
         ));
   if( $game_finished )
   {
      $itable->add_row( array(
            'sname' => T_('Score'),
            'sinfo' => score2text(@$grow['Score'], false),
            ));
   }
   $itable->add_row( array(
         'sname' => T_('Start time'),
         'sinfo' => date(DATE_FMT3, @$grow['X_Starttime'] ),
         ));
   $itable->add_row( array(
         'sname' => T_('Lastchanged'),
         'sinfo' => date(DATE_FMT3, @$grow['X_Lastchanged'] ),
         ));
   $itable->add_row( array(
         'sname' => T_('Size'),
         'sinfo' => $grow['Size'],
         ));
   $itable->add_row( array(
         'sname' => T_('Handicap'),
         'sinfo' => $grow['Handicap'],
         ));
   $itable->add_row( array(
         'sname' => T_('Komi'),
         'sinfo' => $grow['Komi'],
         ));
   $itable->add_row( array(
         'sname' => T_('Rated'),
         'sinfo' => yesno($grow['X_Rated']),
         ));
   $itable->add_row( array(
         'sname' => T_('Weekend Clock'),
         'sinfo' => yesno($grow['WeekendClock']),
         ));
   $itable->add_row( array(
         'sname' => T_('Standard Handicap'),
         'sinfo' => yesno($grow['StdHandicap']),
         ));
   $itable_str_game = $itable->make_table();
   unset($itable);


   // ------------------------
   // build table-info: opponents

   $itable = new Table_info('opponents');
   $itable->add_row( array( 'caption' => T_('Opponents') ));
   $itable->add_row( array(
         'iattb' => 'class=Colors',
         'sname' => T_('Color'),
         'sinfo' => array(
               image( "{$base_path}17/b.gif", T_('Black'), T_('Black') ),
               image( "{$base_path}17/w.gif", T_('White'), T_('White') ),
            ),
         ));
   $itable->add_row( array(
         'sname' => T_('Player'),
         'sinfo' => array(
               user_reference( REF_LINK, 1, '', @$grow['Black_ID'],
                  @$grow['Black_Name'], @$grow['Black_Handle'] ),
               user_reference( REF_LINK, 1, '', @$grow['White_ID'],
                  @$grow['White_Name'], @$grow['White_Handle'] ),
            ),
         ));
   if( @$grow['Black_OnVacation'] > 0 || @$grow['White_OnVacation'] > 0 )
   {
      $itable->add_row( array(
            'nattb' => 'class=OnVacation',
            'sname' => T_('On vacation'),
            'sinfo' => array(
                  echo_onvacation(@$grow['Black_OnVacation']),
                  echo_onvacation(@$grow['White_OnVacation']),
               ),
            ));
   }
   $itable->add_row( array(
         'sname' => T_('Current rating'),
         'sinfo' => array(
               echo_rating( @$grow['Black_Rating'], true, $grow['Black_ID'] ),
               echo_rating( @$grow['White_Rating'], true, $grow['White_ID'] ),
            ),
         ));
   $itable->add_row( array(
         'sname' => T_('Start rating'),
         'sinfo' => array(
               echo_rating( @$grow['Black_Start_Rating']),
               echo_rating( @$grow['White_Start_Rating']),
            ),
         ));
   if( $game_finished )
   {
      $itable->add_row( array(
            'sname' => T_('End rating'),
            'sinfo' => array(
                  echo_rating( @$grow['Black_End_Rating']),
                  echo_rating( @$grow['White_End_Rating']),
               ),
            ));

      if( $grow['X_Rated'] === 'Y' &&
            ( isset($grow['Black_RatingDiff']) || isset($grow['White_RatingDiff']) ))
      {
         $itable->add_row( array(
               'sname' => T_('Rating diff'),
               'sinfo' => array(
                     build_rating_diff( @$grow['Black_RatingDiff'] ),
                     build_rating_diff( @$grow['White_RatingDiff'] ),
                  ),
               ));
      }
   }
   $itable_str_opponents = $itable->make_table();
   unset($itable);


   // ------------------------
   // build table-info: time settings

   $short = true; // use short-time?
   $itable = new Table_info('time');
   $itable->add_row( array( 'caption' => T_('Remaining time and Time settings') ));
   $itable->add_row( array(
         'iattb' => 'class=Colors',
         'sname' => T_('Color'),
         'sinfo' => array(
               T_('Game setting'),
               image( "{$base_path}17/b.gif", T_('Black'), T_('Black') ),
               image( "{$base_path}17/w.gif", T_('White'), T_('White') ),
            ),
         ));
   $itable->add_row( array(
         'sname' => T_('Time system'),
         'sinfo' => array(
               echo_byotype($grow['Byotype']),
               '&nbsp;',
               '&nbsp;',
            ),
         ));
   $itable->add_row( array(
         'sname' => T_('Main time'),
         'sinfo' => array(
               echo_time($grow['Maintime'], false, false&&$short),
               echo_time($grow['Black_Maintime'], false, $short),
               echo_time($grow['White_Maintime'], false, $short),
            ),
         ));
   $game_extratime = echo_time_limit( -1, $grow['Byotype'],
         $grow['Byotime'], $grow['Byoperiods'], false, $short, false );
   $itable->add_row( array(
         'sname' => T_('Extra time'),
         'sinfo' => array(
               echo_time_limit( -1, $grow['Byotype'],
                     $grow['Byotime'], $grow['Byoperiods'], false, false&&$short, false ),
               (( $grow['Black_Maintime'] > 0 )
                  ? $game_extratime
                  : echo_time_limit( -1, $grow['Byotype'], $grow['Black_Byotime'],
                        $grow['Black_Byoperiods'], false, $short, false )
               ),
               (( $grow['White_Maintime'] > 0 )
                  ? $game_extratime
                  : echo_time_limit( -1, $grow['Byotype'], $grow['White_Byotime'],
                        $grow['White_Byoperiods'], false, $short, false )
               ),
            ),
         ));
   if( $is_admin )
   {
      $itable->add_row( array(
            'rattb' => 'class=DebugInfo',
            'sname' => T_('Clock used'),
            'sinfo' => array( $grow['ClockUsed'], '&nbsp;', '&nbsp;' ),
            ));
   }
   $itable_str_time = $itable->make_table();
   unset($itable);

   // ------------------------ END of building


   $title = T_('Game information');
   start_page( $title, true, $logged_in, $player_row );

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query) ."<br>\n";
   echo "<h3 class=Header>$title</h3>\n";

   echo "<table><tr valign=\"top\">",
      "<td>$itable_str_game<br>$itable_str_opponents</td>",
      "<td>$itable_str_time</td>",
      "</tr></table>\n";

   $menu_array = array();
   $menu_array[T_('Show game')] = 'game.php?gid='.$gid;

   end_page(@$menu_array);
}

?>
