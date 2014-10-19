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
require_once 'include/deprecated_functions.php';

$GLOBALS['ThePage'] = new Page('Script', PAGEFLAG_IMPLICIT_FLUSH );


{
   disable_cache();
   connect2mysql();
   set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries or for large-datasets

   // may need to be adjusted for large game-count fixed as load_profiles_gamesetup() loads all games in one step
   @ini_set('memory_limit', '320M');

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.fix_default_max_handi-1_0_16');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.fix_default_max_handi-1_0_16');
   if ( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'scripts.fix_default_max_handi-1_0_16');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();


   start_html( 'fix_default_max_handi', false );

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


   $cnts = array(); // [ [cnt-all, cnt-fix], ... ]
   $cnts[] = handle_games_gamesetup();
   $cnts[] = handle_profiles_gamesetup( PROFTYPE_TMPL_INVITE );
   $cnts[] = handle_profiles_gamesetup( PROFTYPE_TMPL_NEWGAME );
   $cnts[] = handle_waiting_room_gamesetup();
   $cnts[] = handle_tournament_rules_gamesetup();

   $cnt_all = $cnt_fix = 0;
   foreach ( $cnts as $cnt_arr )
   {
      $cnt_all += $cnt_arr[0];
      $cnt_fix += $cnt_arr[1];
   }

   echo "<br><br>Found $cnt_all entries in total to fix ...\n";

   if ( $do_it )
   {
      echo "<br>Found $cnt_fix entries in total that required fixing ...\n";
      echo 'Fix by setting default-max-handicap finished.';
   }

   end_html();
}//main


// return: arr( [ID/Status/Size/Handicap/Komi/GameSetup/ShapeID => ...], ... )
function load_games_gamesetup()
{
   $arr = array();
   $result = db_query("fix_default_max_handi.load_games_gamesetup()",
      "SELECT ID, Status, Size, Handicap, Komi, GameSetup, ShapeID FROM Games " .
      "WHERE GameSetup>'' AND GameType='".GAMETYPE_GO."'" );
   while ( $row = mysql_fetch_assoc( $result ) )
   {
      if ( $row['Status'] == GAME_STATUS_SETUP ) // skip MPGs (shouldn't occur)
         continue;
      $arr[] = $row;
   }
   mysql_free_result($result);
   return $arr;
}//load_games_gamesetup

// return: arr( Profiles.ID => ProfileTemplate-obj, ... )
function load_profiles_gamesetup( $template_type )
{
   $arr = array();
   $result = db_query("fix_default_max_handi.load_profiles_gamesetup($template_type)",
      "SELECT ID, Text FROM Profiles WHERE Type=$template_type" );
   while ( $row = mysql_fetch_assoc( $result ) )
   {
      $tmpl = ProfileTemplate::decode( $template_type, $row['Text'] );
      $arr[$row['ID']] = $tmpl;
   }
   mysql_free_result($result);
   return $arr;
}//load_profiles_gamesetup


function handle_games_gamesetup()
{
   echo "<hr>Find game-setup for games ...";

   // load Games.GameSetup: arr( [ID/Status/Size/Handicap/Komi/GameSetup/ShapeID => ...], ... )
   $arr_games = load_games_gamesetup();

   $all_cnt = count($arr_games);
   $curr_cnt = 0;
   echo "<br>Found $all_cnt games to fix ...\n";

   $cnt_fix = 0;
   foreach ( $arr_games as $grow )
   {
      if ( ($curr_cnt++ % 50) == 0 )
         echo "<br><br>... $curr_cnt of $all_cnt updated ...\n";

      if ( $grow['Status'] == GAME_STATUS_INVITED ) // invitation or dispute
      {
         if ( fix_game_invite_dispute( $grow ) )
            $cnt_fix++;
      }
      else // finished or running game
      {
         if ( fix_game_finished_running( $grow ) )
            $cnt_fix++;
      }
   }

   echo "<br>Found $cnt_fix games that required fixing ...\n";

   return array( $all_cnt, $cnt_fix );
}//handle_games_gamesetup

// \param $row [ID/Status/Size/Handicap/Komi/GameSetup/ShapeID => ...]
function fix_game_invite_dispute( $row )
{
   $gid = (int)$row['ID'];
   $gs_arr = DeprecatedGameSetup::parse_invitation_game_setup( -1, $row['GameSetup'], $gid );
   if ( count($gs_arr) != 2 || is_null($gs_arr[0]) || is_null($gs_arr[1]) )
      error('invite_bad_gamesetup', "fix_default_max_handi.fix_game_invite_dispute.find_bad_gamesetup_inv.need2($gid,{$row['GameSetup']})");

   $update = false;
   foreach ( $gs_arr as $gs )
   {
      if ( $gs->MaxHandicap == 0 )
      {
         $gs->MaxHandicap = DEFAULT_MAX_HANDICAP;
         $update = true;
      }
   }

   if ( $update )
   {
      $new_gs = DeprecatedGameSetup::build_invitation_game_setup( $gs_arr[0], $gs_arr[1] );
      dbg_query( "UPDATE Games SET GameSetup='".mysql_addslashes($new_gs)."' WHERE ID=$gid LIMIT 1" );
      return true;
   }
   else
      return false;
}//fix_game_invite_dispute

// \param $row [ID/Status/Size/Handicap/Komi/GameSetup/ShapeID => ...]
function fix_game_finished_running( $row )
{
   $gs = GameSetup::new_from_game_setup( $row['GameSetup'], /*inv*/false, /*null-empty*/true );
   if ( is_null($gs) )
      return false;

   $def_max_handi = DefaultMaxHandicap::calc_def_max_handicap( $row['Size'] );
   $update = false;
   if ( $gs->MaxHandicap == 0 )
      $update = true;
   elseif ( $gs->MaxHandicap == MAX_HANDICAP && $gs->Handicap <= $def_max_handi )
      $update = true;

   if ( $update )
   {
      $gs->MaxHandicap = DEFAULT_MAX_HANDICAP;
      $new_gs = $gs->encode_game_setup();
      $gid = (int)$row['ID'];
      dbg_query( "UPDATE Games SET GameSetup='".mysql_addslashes($new_gs)."' WHERE ID=$gid LIMIT 1" );
      return true;
   }
   else
      return false;
}//fix_game_finished_running


function handle_profiles_gamesetup( $templ_type )
{
   echo "<hr>Find game-setup for profiles of type [$templ_type] ...";

   // load Profiles.Text: arr( Profiles.ID => ProfileTemplate-obj, ... )
   $arr_templates = load_profiles_gamesetup( $templ_type );

   $all_cnt = count($arr_templates);
   $curr_cnt = 0;
   echo "<br>Found $all_cnt profiles type [$templ_type] to fix ...\n";

   $cnt_fix = 0;
   foreach ( $arr_templates as $profile_id => $tmpl )
   {
      if ( ($curr_cnt++ % 50) == 0 )
         echo "<br><br>... $curr_cnt of $all_cnt updated ...\n";

      if ( fix_profile( $templ_type, $profile_id, $tmpl ) )
         $cnt_fix++;
   }

   echo "<br>Found $cnt_fix profiles that required fixing ...\n";

   return array( $all_cnt, $cnt_fix );
}//handle_profiles_gamesetup

function fix_profile( $templ_type, $prof_id, $tmpl )
{
   $update = false;
   if ( $templ_type == PROFTYPE_TMPL_INVITE )
   {
      if ( $tmpl->GameSetup->MaxHandicap == 0 )
         $update = true;
   }
   elseif ( $templ_type == PROFTYPE_TMPL_NEWGAME )
   {
      if ( $tmpl->GameSetup->MaxHandicap == MAX_HANDICAP )
         $update = true;
      elseif ( $tmpl->GameSetup->MaxHandicap == 0 && $tmpl->GameSetup->ViewMode == GSETVIEW_STANDARD )
         $update = true;
   }

   if ( $update )
   {
      $tmpl->GameSetup->MaxHandicap = DEFAULT_MAX_HANDICAP;
      $new_gs = $tmpl->encode_template();
      dbg_query( "UPDATE Profiles SET Text='".mysql_addslashes($new_gs)."' WHERE ID=$prof_id AND Type=$templ_type LIMIT 1" );
      return true;
   }
   else
      return false;
}//fix_profile


function handle_waiting_room_gamesetup()
{
   global $do_it;

   echo "<hr>Fix waiting-room entries ...";

   if ( $do_it )
   {
      dbg_query( "UPDATE Waitingroom SET MaxHandicap=".DEFAULT_MAX_HANDICAP." WHERE MaxHandicap IN (".MAX_HANDICAP.",127)" );
      $cnt_fix = mysql_affected_rows();
   }
   else
   {
      $row = mysql_single_fetch( "scripts.fix_default_max_handi.handle_waiting_room_gamesetup",
         $query = "SELECT COUNT(*) AS X_Count FROM Waitingroom WHERE MaxHandicap IN (".MAX_HANDICAP.",127)" );
      echo "<BR>$query; ";
      $cnt_fix = ($row) ? (int)$row['X_Count'] : 0;
   }

   echo "<br>Found $cnt_fix waiting-room entries that required fixing ...\n";

   return array( $cnt_fix, $cnt_fix );
}//handle_waiting_room_gamesetup

function handle_tournament_rules_gamesetup()
{
   global $do_it;

   echo "<hr>Fix tournament-rules entries ...";

   if ( $do_it )
   {
      dbg_query( "UPDATE TournamentRules SET MaxHandicap=".DEFAULT_MAX_HANDICAP." WHERE MaxHandicap IN (".MAX_HANDICAP.",127)" );
      $cnt_fix = mysql_affected_rows();
   }
   else
   {
      $row = mysql_single_fetch( "scripts.fix_default_max_handi.handle_tournament_rules_gamesetup",
         $query = "SELECT COUNT(*) AS X_Count FROM TournamentRules WHERE MaxHandicap IN (".MAX_HANDICAP.",127)" );
      echo "<BR>$query; ";
      $cnt_fix = ($row) ? (int)$row['X_Count'] : 0;
   }

   echo "<br>Found $cnt_fix tournament-rules entries that required fixing ...\n";

   return array( $cnt_fix, $cnt_fix );
}//handle_tournament_rules_gamesetup

?>
