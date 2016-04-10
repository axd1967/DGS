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

chdir( '../../../' );
require_once 'include/std_functions.php';
require_once 'include/game_functions.php';

$GLOBALS['ThePage'] = new Page('Script', PAGEFLAG_IMPLICIT_FLUSH );


{
   disable_cache();
   connect2mysql();
   set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries or for large-datasets

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.fix_new_game_expert_view-1_0_16');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.fix_new_game_expert_view-1_0_16');
   if ( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'scripts.fix_new_game_expert_view-1_0_16');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();


   start_html( 'fix_new_game_expert_view', false );

//echo ">>>> One shot fix. Do not run it again."; end_html(); exit;

   $do_it = @$_REQUEST['do_it'];
   if ( $do_it )
   {
      function dbg_query($s) {
         if ( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
         if ( DBG_QUERY>1 ) error_log("dbg_query(DO_IT): $s");
      }
      echo "<p>*** Fixes new-game-profiles with expert-view (old ".DEPRECATED_GSETVIEW_EXPERT.") ***"
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
      echo "<p><b><font color=red>Important Note:</font></b> Ensure, that script 'fix_default_max_handi-1_0_16.php' has run first (once only)!"
         ."<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."</p>";
   }


   echo "<hr>Find new-game profiles with deprecated expert-view ...";

   $arr_templates = load_profile_templates_new_game_expert_view(); // arr( Profiles.ID => ProfileTemplate, ...)

   $all_cnt = count($arr_templates);
   $curr_cnt = 0;
   echo "<br>Found $all_cnt profiles to fix ...\n";

   // fix view-mode
   $cnt_fix = 0;
   foreach ( $arr_templates as $profile_id => $tmpl )
   {
      if ( ($curr_cnt++ % 50) == 0 )
         echo "<br><br>... $curr_cnt of $all_cnt updated ...\n";

      $tmpl->GameSetup->ViewMode = GSETVIEW_STANDARD;
      $fix_text = $tmpl->encode_template();
      dbg_query( "UPDATE Profiles SET Text='".mysql_addslashes($fix_text)."' WHERE ID=$profile_id LIMIT 1" );
      $cnt_fix++;
   }

   echo "<br>Found $cnt_fix profiles that required fixing ...\n";

   if ( $do_it )
      echo 'Fix by merging profile deprecated expert-view finished.';

   end_html();
}//main


function load_profile_templates_new_game_expert_view()
{
   static $template_type = PROFTYPE_TMPL_NEWGAME;

   $arr = array();
   $result = db_query("fix_new_game_expert_view.load_profile_templates_new_game_expert_view()",
      "SELECT ID, Text FROM Profiles WHERE Type=$template_type" );
   while ( $row = mysql_fetch_assoc( $result ) )
   {
      $tmpl = ProfileTemplate::decode( $template_type, $row['Text'] );
      if ( $tmpl->GameSetup->ViewMode == DEPRECATED_GSETVIEW_EXPERT )
         $arr[$row['ID']] = $tmpl;
   }
   mysql_free_result($result);
   return $arr;
}//load_profile_templates_new_game_expert_view

?>
