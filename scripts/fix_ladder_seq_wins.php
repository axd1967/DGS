<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

// translations remove for admin page: $TranslateGroups[] = "Admin";

chdir('..');
require_once 'include/std_functions.php';
require_once 'include/std_classes.php';
require_once 'include/form_functions.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_ladder.php';

define('SEPLINE', "\n<p><hr>\n");

$GLOBALS['ThePage'] = new Page('Script', PAGEFLAG_IMPLICIT_FLUSH );

define('FIXTYPE_SEQWINS_TLADDER', 1);
define('FIXTYPE_SEQWINS_TRESULT', 2);


{
   connect2mysql();
   set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries or for large-datasets

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scrips.fix_ladder_seq_wins');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.fix_ladder_seq_wins');
   if ( !(@$player_row['admin_level'] & (ADMIN_DEVELOPER|ADMIN_TOURNAMENT|ADMIN_DATABASE)) )
      error('adminlevel_too_low', 'scripts.fix_ladder_seq_wins');

   $page = "fix_ladder_seq_wins.php";

/* Actual REQUEST calls used
     preview&tid=&type=      : preview for fix of all users for specific ladder-tournament & fix-type
     confirm&tid=&type=      : fix all users for specific ladder-tournament & fix-type
*/

   $tid = (int)get_request_arg('tid');
   if ( $tid <= 0 ) $tid = '';
   $fix_type = (int)get_request_arg('type');


   $title = T_('Fix Ladder Consecutive Wins');
   start_page( $title, true, $logged_in, $player_row );

   echo "<h3 class=Header>$title</h3>\n";

   show_form();


   section( 'result', T_('Result') );

   $errmsg = null;
   if ( !$tid )
      $errmsg = "Missing tournament-id";
   else
   {
      $tourney = Tournament::load_tournament( $tid );
      if ( is_null($tourney) )
         $errmsg = "Tournament #$tid not found!";
      else if ( $tourney->Type != TOURNEY_TYPE_LADDER )
         $errmsg = "Tournament #$tid is NOT a ladder-tournament!";
   }
   if ( !is_null($errmsg) )
      echo span('ErrMsgCode', "<br>$errmsg<br>\n");
   else if ( $tid && (@$_REQUEST['preview'] || @$_REQUEST['confirm']) ) // fix single ladder-tournament
   {
      if ( $fix_type == FIXTYPE_SEQWINS_TLADDER )
         fix_ladder_seqwins( $tid );
      elseif ( $fix_type == FIXTYPE_SEQWINS_TRESULT )
         fix_results_seqwins( $tid );
      else
         error('invalid_args', "scripts.fix_ladder_seq_wins($fix_type)");
   }


   $menu_array = array( T_('Fix ladder consecutive wins') => "scripts/$page" );
   end_page(@$menu_array);
}//main


function show_form()
{
   global $page, $tid, $fix_type;

   $tform = new Form('tform', $page, FORM_GET, true);
   $tform->add_row( array(
         'DESCRIPTION', T_('Ladder-Tournament (tid)'),
         'TEXTINPUT',   'tid', 8, -1, get_request_arg('tid'), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Fix Type'),
         'RADIOBUTTONS', 'type', array( FIXTYPE_SEQWINS_TLADDER => T_('Fix Consecutive Wins in ladder') ), $fix_type, ));
   $tform->add_row( array(
         'TAB',
         'RADIOBUTTONS', 'type', array( FIXTYPE_SEQWINS_TRESULT => T_('Fix Max. Consecutive Wins in results (Preview = Confirm!)') ), $fix_type, ));

   $tform->add_row( array(
         'TAB', 'CELL', 1, '',
         'SUBMITBUTTON', 'preview', T_('Preview'),
      ));

   if ( @$_REQUEST['preview'] ) // fix single ladder-tournament (confirm)
   {
      $tform->add_row( array(
            'TAB', 'CELL', 1, '',
            'SUBMITBUTTON', 'confirm', T_('Confirm Fix'),
      ));
   }

   $tform->echo_string(1);
}//show_form


function fix_ladder_seqwins( $tid )
{
   // load all finished tournament-games
   $qsql = TournamentGames::build_query_sql( $tid );
   $qsql->add_part( SQLP_OPTS, SQLOPT_CALC_ROWS );
   $qsql->add_part( SQLP_FIELDS, 'IF(ISNULL(G.ID),TG.Lastchanged,G.Lastchanged) AS X_FixOrder' );
   $qsql->add_part( SQLP_FROM,  'LEFT JOIN Games AS G ON G.ID=TG.gid' ); // games may be deleted
   $qsql->add_part( SQLP_ORDER, 'X_FixOrder ASC' );
   $qsql->add_part( SQLP_WHERE, "TG.Status IN ('".TG_STATUS_WAIT."','".TG_STATUS_DONE."')" );

   $begin = getmicrotime();
   echo SEPLINE;
   echo "Fix TournamentGames.SeqWins ...<br>\n";

   $result = db_query( "fix_ladder_seqwins.find_tgames($tid)", $qsql->get_select() );
   $count_rows = (int)@mysql_num_rows($result);
   echo sprintf( "<br>Found %d tournament-games to process ...<br><br>\n", $count_rows );

   // calculate all consecutive-wins for all ever existing ladder-users
   $arr_tladders = array(); // rid => TournamentLadder
   while ( $tg_row = mysql_fetch_assoc($result) )
   {
      $tgame = TournamentGames::new_from_row( $tg_row );

      if ( isset($arr_tladders[$tgame->Challenger_rid]) )
         $tladder_ch = $arr_tladders[$tgame->Challenger_rid];
      else
         $arr_tladders[$tgame->Challenger_rid] = $tladder_ch = new TournamentLadder( $tid, $tgame->Challenger_rid, $tgame->Challenger_uid );

      if ( isset($arr_tladders[$tgame->Defender_rid]) )
         $tladder_df = $arr_tladders[$tgame->Defender_rid];
      else
         $arr_tladders[$tgame->Defender_rid] = $tladder_df = new TournamentLadder( $tid, $tgame->Defender_rid, $tgame->Defender_uid );

      $tladder_ch->update_seq_wins( $tgame->Score, $tgame->Flags, /*db*/false );
      $tladder_df->update_seq_wins( -$tgame->Score, $tgame->Flags, /*db*/false );
   }
   mysql_free_result($result);


   // load all still existing ladder-users and update differences in seq-wins-counts
   $cnt_fixed = 0;
   $tl_iterator = new ListIterator( 'scripts.fix_ladder_seq_wins.TL', null, 'ORDER BY TL.rid ASC' );
   $tl_iterator = TournamentLadder::load_tournament_ladder( $tl_iterator, $tid );
   while ( list(,$arr_item) = $tl_iterator->getListIterator() )
   {
      list( $tladder_db, $orow ) = $arr_item;
      $rid = $tladder_db->rid;

      if ( isset($arr_tladders[$rid]) )
      {
         $tladder_cmp = $arr_tladders[$rid];
         if ( $tladder_cmp->SeqWins != $tladder_db->SeqWins || $tladder_cmp->SeqWinsBest != $tladder_db->SeqWinsBest )
         {
            $result = dbg_query( "scripts.fix_ladder_seq_wins.update_tl($tid,$rid)",
               "UPDATE TournamentLadder SET SeqWins={$tladder_cmp->SeqWins}, SeqWinsBest={$tladder_cmp->SeqWinsBest} " .
               "WHERE tid=$tid AND rid=$rid LIMIT 1" );
            if ( $result )
               $cnt_fixed++;
         }
      }
   }

   echo "<br>\n",
      sprintf( "Tournament #%s -> fixed %s TournamentLadder-entries for %s tournament-games<br>\n", $tid, $cnt_fixed, $count_rows );

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin))
      , " - Fix Done.";
}//fix_ladder_seqwins


function fix_results_seqwins( $tid )
{
   $tl_props = TournamentCache::load_cache_tournament_ladder_props(
      "scrips.fix_ladder_seq_wins.fix_results_seqwins($tid)", $tid );
   if ( $tl_props->SeqWinsThreshold <= 0 )
   {
      echo span('TInfo', "<br><br>TournamentProperties.SeqWinsThreshold is 0 (=disabled).<br>\n");
      return;
   }

   $begin = getmicrotime();
   echo SEPLINE;
   echo "Fix TournamentResult for max. consecutive wins ...<br>\n";

   $cnt_fixed = TournamentResultControl::create_tournament_result_best_seq_wins(
      $tid, null, $tl_props->SeqWinsThreshold );

   echo "<br>\n",
      sprintf( "Tournament #%s -> fixed %s TournamentResult-entries<br>\n", $tid, $cnt_fixed );

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin))
      , " - Fix Done.";
}//fix_results_seqwins


function dbg_query( $dbgmsg, $query )
{
   if ( @$_REQUEST['confirm'] )
      $result = db_query( $dbgmsg, $query );
   else
   {
      echo $query, "<br>\n";
      $result = true;
   }
   return $result;
}//dbg_query

?>
