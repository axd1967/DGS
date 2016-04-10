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

$TranslateGroups[] = "Docs";

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/countries.php';

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row, LOGIN_DEFAULT_OPTS|LOGIN_SKIP_VFY_CHK );

   $page = "statistics.php";

   // stats: 0=default, 1=user-countries
   $stats = (int)get_request_arg('stats', 0);

   $statsmenu = array();
   $statsmenu[T_('Standard view')] = $page;
   $statsmenu[T_('User countries')] = "$page?stats=1";

   if ( $stats == 1 )
      show_stats_user_countries();
   else // default: stats=0 or illegal
   {
      $title = T_('Statistics');
      stats_start_page( $title );

      make_menu( $statsmenu );
      echo "<hr>\n";

      show_stats_default();
   }

   if ( $stats > 0 )
   {
      echo "<hr>\n";
      make_menu( $statsmenu );
   }

   end_page();
}//main


function stats_start_page( $title )
{
   global $logged_in, $player_row;

   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=\"Header\">$title</h3>\n";
}

function show_stats_default()
{
   global $player_row, $NOW;

   $arr_moves = load_cache_games_stats( 1 );
   $arr_activity = load_cache_games_stats( 2 );

   echo "<table border=1>\n",
      sprintf( "<tr><th>%s</th><th>%s</th><th>%s</th></tr>\n", T_('Status#stats'), T_('Games#stats'), T_('Moves#stats') );

   $col_fmt = '<td class="right">%s</td>';
   foreach ( $arr_moves as $row )
   {
      $col_title = ( is_null($row['Status']) ) ? T_('Total#stats') : $row['Status'];
      echo '<tr>'
         , "<td>$col_title</td>"
         , sprintf( $col_fmt, number_format($row['count']))
         , sprintf( $col_fmt, number_format($row['moves']))
         , "</tr>\n";
   }

   echo "</table>\n";

   if ( $arr_activity )
   {
      $row = $arr_activity;
      echo '<p>', sprintf( T_('%s hits by %s players#stats'), number_format($row['hits']), number_format($row['count']) ),
         '</p>';
      echo '<p>', T_('Activity#stats'), ': ', number_format(round($row['activity'])), "</p>\n";
   }

   // NOTE: only works under Linux-like systems and with safe_mode=off
   if ( @$player_row['admin_level'] & ADMIN_DEVELOPER )
   {
      $tmp = '/proc/loadavg';
      if ( ($tmp=trim(@read_from_file($tmp))) )
         echo '<p><span class=DebugInfo>', T_('Loadavg#stats'), ': ', $tmp, '</span></p>';
   }

   // pass URL-args to graph-generation-script
   $args= array();
   $args['show_time']= ( @$_REQUEST['show_time'] ) ? 1 : 0;
   $args['activity']= ( @$_REQUEST['activity'] ) ? 1 : 0;
   $args['size']= (int)@$_REQUEST['size'];
   $args['no_cache']= ( @$_REQUEST['no_cache'] ) ? 1 : 0;
   $args['dyna']= floor($NOW/CACHE_EXPIRE_GRAPH); //force caches refresh //FIXME what is this var!?
   $args= make_url('?', $args);

   $title = T_('Statistics graph');
   echo "<h3 class=Header>$title</h3>\n";
   echo "<img src=\"statisticspng.php$args\" alt=\"$title\">\n";

   $title = T_('Rating histogram');
   echo "<h3 class=Header>$title</h3>\n";
   echo "<img src=\"statratingspng.php$args\" alt=\"$title\">\n";
}//show_stats_default

function load_cache_games_stats( $num )
{
   $dbgmsg = 'statistics.load_cache_games_stats.'.$num;
   $key = "Statistics.games.$num";

   $result = DgsCache::fetch( $dbgmsg, CACHE_GRP_STATS_GAMES, $key );
   if ( is_null($result) )
   {
      if ( $num == 1 )
      {
         $result = array();
         $db_result = db_query( $dbgmsg,
            "SELECT SQL_SMALL_RESULT Status,SUM(Moves) AS moves, COUNT(*) AS count " .
            "FROM Games GROUP BY Status WITH ROLLUP" );
         while ( $row = mysql_fetch_array($db_result) )
            $result[] = $row;
         mysql_free_result($db_result);
      }
      else // num==2
      {
         global $ActivityForHit;
         $result = mysql_single_fetch( $dbgmsg,
            "SELECT SUM(Hits) AS hits, COUNT(*) AS count, SUM(Activity)/$ActivityForHit AS activity FROM Players" );
      }

      DgsCache::store( $dbgmsg, CACHE_GRP_STATS_GAMES, $key, $result, SECS_PER_DAY, 'Statistics.games' );
   }

   return $result;
}//load_cache_games_stats

function show_stats_user_countries()
{
   global $NOW, $page;

   $weeks = (int)get_request_arg('w',0);
   $last_access = $NOW - $weeks * 7 * SECS_PER_DAY;

   $page_user = "$page?stats=1";
   $title = T_('Statistics - User countries');
   stats_start_page( $title );

   $userstatsmenu = array();
   $userstatsmenu[T_('All users')] = $page_user;
   $userstatsmenu[sprintf( T_('Users online within %s weeks'), 8) ] = $page_user.URI_AMP."w=8";
   make_menu( $userstatsmenu, false );


   $result = db_query( 'statistics.users.count_countries',
      "SELECT SQL_SMALL_RESULT Country, COUNT(*) AS X_Count " .
      "FROM Players " .
      ( $weeks > 0 ? "WHERE Lastaccess>=FROM_UNIXTIME($last_access) " : '' ) .
      "GROUP BY Country " .
      "ORDER BY X_Count DESC" );

   echo "<table id=\"Statistics\" class=\"Table\">\n"
      , sprintf( "<tr><th>%s</th><th>%s</th><th>%s</th></tr>\n", T_('Count'), T_('Flag#country'), T_('Country-setting') );

   $arr_countries = getCountryText();
   $total = 0;
   while ( $row = mysql_fetch_array( $result ) )
   {
      $ccode = $row['Country'];
      $total += $row['X_Count'];
      unset($arr_countries[$ccode]);

      $ccimg = getCountryFlagImage($ccode);
      if ( $ccode == '' )
         $ccimg = NO_VALUE;
      elseif ( $ccimg == '' )
         $ccimg = "[$ccode]";

      $cctxt = getCountryText($ccode);
      if ( $ccode == '' )
         $cctxt = T_('Unset');
      elseif ( $cctxt == '' )
         $cctxt = NO_VALUE;

      echo sprintf( "<tr><td class=\"Number\">%s</td><td class=\"Image\">%s</td><td>%s</td></tr>\n",
                    $row['X_Count'], $ccimg, $cctxt );
   }
   mysql_free_result($result);
   echo sprintf( "<tr><td class=\"Number\"><b>%s</b></td><td class=\"Image\">%s</td><td><b>%s</b></td></tr>\n",
                 $total, '', T_('Total#stats') );
   echo "</table>\n";

   echo "<br>\n", T_('Remaining countries'), ":\n<p>\n";
   asort($arr_countries);
   foreach ( $arr_countries as $ccode => $cctxt )
   {
      echo getCountryFlagImage($ccode), SMALL_SPACING, "\n";
   }
}//show_stats_user_countries

?>
