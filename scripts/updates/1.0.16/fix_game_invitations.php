<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Jens-Uwe Gaspar

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

chdir( '../../../' );
require_once 'include/std_functions.php';
require_once 'include/game_functions.php';
require_once 'include/make_game.php';
require_once 'include/deprecated_functions.php';
require_once 'include/message_functions.php';

/*!
 * \file fix_game_invitations-1_0_16.php
 *
 * \brief Script to migrate invitations using Games.GameSetup-field from release 1.0.15
 *        into using GameInvitation-table-entries introduced with release 1.0.16.
 */


$GLOBALS['ThePage'] = new Page('Script', PAGEFLAG_IMPLICIT_FLUSH );


{
   disable_cache();
   connect2mysql();
   set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries or for large-datasets

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.fix_game_invitations-1_0_16');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.fix_game_invitations-1_0_16');
   if ( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'scripts.fix_game_invitations-1_0_16');

   $limit = (int)get_request_arg('limit', 1);
   if ( $limit < 1 )
      $limit = 1;

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();
   $page_args['limit'] = $limit;


   start_html( 'fix_game_invitations', false );

//echo ">>>> One shot fix. Do not run it again."; end_html(); exit;

   $do_it = @$_REQUEST['do_it'];
   if ( $do_it )
   {
      function dbg_query($s) {
         if ( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
         if ( DBG_QUERY>1 ) error_log("dbg_query(DO_IT): $s");
      }
      echo "<p>*** Fixes default max-handicap ***"
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>";
   }
   else
   {
      function dbg_query($s) {
         echo "<BR>$s; ";
         if ( DBG_QUERY>1 ) error_log("dbg_query(SIMUL): $s");
      }
      $tmp = array_merge($page_args,array('do_it' => 1));
      echo "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."</p>";
   }


   echo "Used limit : $limit (use URL-argument '&limit=n' to change it, repeat till all games migrated)<br>\n";

   ta_begin();
   {//HOT-section to merge game-invitation
      $cnt_all = migrate_game_invitations( $do_it, $limit );
   }
   ta_end();

   echo "<br><br>Found $cnt_all entries in total to fix ...\n";
   if ( $do_it )
      echo "\nFix by migrating 1.0.15-style game-invitations to using GameInvitation-table finished.\n";

   end_html();
}//main


function migrate_game_invitations( $do_it, $limit )
{
   $cnt_fixed = 0;

   // find game-invitations, that are not migrated yet
   $qlimit = ( $limit > 0 ) ? " LIMIT $limit" : '';
   $result = db_query("fix_game_invitations.load_games_gamesetup()",
      "SELECT G.*, COALESCE(MCI.uid,0) AS X_inviting_uid, COALESCE(MCL.uid,0) AS X_last_send_uid " .
      "FROM Games AS G " .
         "LEFT JOIN GameInvitation AS GI ON GI.gid=G.ID AND G.Black_ID=GI.uid " .
         "LEFT JOIN Messages AS M ON M.ID=G.mid " .
            "LEFT JOIN MessageCorrespondents AS MCI ON MCI.mid=M.Thread AND MCI.Sender='Y' " . // identify initial inviting user
         "LEFT JOIN MessageCorrespondents AS MCL ON MCL.mid=G.mid AND MCL.Sender='Y' " . // identify last invitation/dispute-sender
      "WHERE G.Status='".GAME_STATUS_INVITED."' AND GI.gid IS NULL $qlimit" );
   while ( $grow = mysql_fetch_assoc( $result ) )
   {
      if ( $grow['Black_ID'] == $grow['White_ID'] )
      {
         printf( "<font color=red><b><br><br>\nData-error:</b> Found game #%s with Black_ID = White_ID!\n" .
                 "Fix required before migrating it!<br></font>\n", $grow['ID'] );
         continue;
      }
      fix_game_invitation( $do_it, $grow );
      $cnt_fixed++;

      if ( ($cnt_fixed % 50) == 0 )
         echo "<br><br>... $cnt_fixed updated ...\n";
   }
   mysql_free_result($result);

   return $cnt_fixed;
}//migrate_game_invitations


/*!
 * \brief Migrates single game-invitation from old 1.0.15 or former style to 1.0.16 style using GameInvitation-table.
 * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
 */
function fix_game_invitation( $do_it, $grow )
{
   $gid = (int)$grow['ID'];
   $mid = (int)$grow['mid'];
   $black_id = $new_black_id = (int)$grow['Black_ID'];
   $white_id = $new_white_id = (int)$grow['White_ID'];
   $tomove_id = (int)$grow['ToMove_ID'];
   $old_gamesetup = $grow['GameSetup'];
   $inviting_uid = (int)$grow['X_inviting_uid'];
   $last_send_uid = (int)$grow['X_last_send_uid'];

   // some fallbacks for cases like: invitation-typed-message not found, no message found at all, Games.mid=0
   if ( $inviting_uid <= 0 )
      $inviting_uid = $black_id;
   if ( $last_send_uid <= 0 )
      $last_send_uid = $black_id;

   // parse GameSetup for both players from old-style G.GameSetup (contained both GameSetup in one field)
   // NOTE: for old invitations, Games.ToMove_ID holds handicap-type (of INVITATION) for game on INVITED-status
   $gs_arr = DeprecatedGameSetup::parse_invitation_game_setup( $black_id, $old_gamesetup, $gid );
   $handitype = DeprecatedGameSetup::determine_handicaptype( $gs_arr[0], $gs_arr[1], $tomove_id, true );
   list( $black_gs, $white_gs ) = $gs_arr;
   if ( is_null($black_gs) )
      $black_gs = DeprecatedGameSetup::read_game_setup_from_gamerow( $black_id, $grow, $handitype );
   if ( is_null($white_gs) )
      $white_gs = GameSetup::create_opponent_game_setup( $black_gs, $white_id );

   if ( $inviting_uid > 0 && $inviting_uid == $white_id )
   {
      $new_black_id = $white_id;
      $new_white_id = $black_id;
   }
   $last_gs = ( $last_send_uid == $black_id ) ? $black_gs : $white_gs;

   // update Games-table; see also make_invite_game()-function
   $upd_game = build_invitation_game_update( $new_black_id, $new_white_id, $last_send_uid, $last_gs,
      /*upd-shape*/false, /*dispute*/false, /*upd-time*/false );
   dbg_query( "UPDATE Games SET " . $upd_game->get_query() . " WHERE ID=$gid LIMIT 1" );

   if ( $do_it )
   {
      // insert GameInvitation-table-entries
      GameInvitation::insert_game_invitations( array(
            $black_gs->build_game_invitation($gid),
            $white_gs->build_game_invitation($gid) ));
   }
}//fix_game_invitation

?>
