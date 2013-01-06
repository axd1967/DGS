<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

// Update rating

chdir('..');
require_once 'include/std_functions.php';
require_once 'include/std_classes.php';
require_once 'include/db/ratingchangeadmin.php';
require_once 'include/rating.php';


{
   disable_cache();
   connect2mysql();
   set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries


   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in', 'scripts.recalculate_ratings2');
   if( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.recalculate_ratings2');
   if( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'scripts.recalculate_ratings2');

   if( ($lim=@$_REQUEST['limit']) > '' )
      $limit = " LIMIT $lim";
   else
      $limit = "";

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();
   if( $lim > '' )
      $page_args['limit'] = $lim;

   start_html( 'recalculate_ratings2', 0);

echo ">>>> Must be enabled first in code. Make recalc-test. Do not run if you are unsure."; end_html(); exit;
   if( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) {
         if( !mysql_query( $s) )
            die("<BR>$s;<BR>" . mysql_error() );
         if( DBG_QUERY>1 ) error_log("dbg_query(DO_IT): $s");
         echo " --- fixed. ";
      }
      echo "<p>*** Fixes errors ***"
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>";
   }
   else
   {
      function dbg_query($s) {
         echo " --- query:<BR>$s; ";
         if( DBG_QUERY>1 ) error_log("dbg_query(SIMUL): $s");
      }
      $tmp = array_merge($page_args,array('do_it' => 1));
      echo "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."</p>";
   }


   if( !($lim > 0) )
   {
      echo "<br>Reset Players' ratings";
      dbg_query( "UPDATE Players SET " .
                 "Rating2=InitialRating, " .
                 "RatingMax=InitialRating+200+GREATEST(1600-InitialRating,0)*2/15, " .
                 "RatingMin=InitialRating-200-GREATEST(1600-InitialRating,0)*2/15" );

      echo "<br>Reset Ratinglog";
      dbg_query( "DELETE FROM Ratinglog" );
   }

   // load admin-rating-changes
   $iterator = new ListIterator( 'recalc_ratings', null, 'ORDER BY Created ASC, ID ASC' );
   $iterator = RatingChangeAdmin::load_ratingchangeadmin( $iterator );
   $arr_rca = array();
   while( list(,$arr_item) = $iterator->getListIterator() )
   {
      list( $rca, $orow ) = $arr_item;
      $arr_rca[] = $rca;
   }
   unset($iterator);


   // execute games
   $sql_have_rating = "IN ('".RATING_INIT."','".RATING_RATED."')";
   $query = "SELECT Games.ID as gid, " .
       "UNIX_TIMESTAMP(Games.Lastchanged) AS X_Lastchanged " .
       "FROM Games " .
         "INNER JOIN Players as white ON white.ID=Games.White_ID " .
         "INNER JOIN Players as black ON black.ID=Games.Black_ID " .
       "WHERE Games.Status='FINISHED' AND Games.Rated IN ('Y','Done') " . //redo Rated='Done' and do missed Rated='Y'
       "AND white.RatingStatus $sql_have_rating " .
       "AND black.RatingStatus $sql_have_rating " .
       "ORDER BY Games.Lastchanged, Games.ID $limit";

   $result = mysql_query( $query )
           or die("<BR>" . mysql_error() );

   echo "<p></p>Game:";
   $count=0; $tot=0;
   ta_begin();
   {//HOT-section to recalculate user-ratings
      while( $row = mysql_fetch_assoc( $result ) )
      {
         if( connection_aborted() ) break; // not sure this works if nothing is flushed

         echo ' ', $row['gid'];
         $rating_changes = find_rating_changes( $row['X_Lastchanged'], $arr_rca );

         if( $do_it )
         {
            // reset user-rating
            if( !is_null($rating_changes) )
               change_user_ratings_for_recalc( $rating_changes );

            // NOTE: MP-games are always unrated and are handled in update_rating2()-func
            $rated_status = update_rating2($row["gid"], false/*=check_done*/); //0=rated game
            if( $rated_status == RATEDSTATUS_RATED )
               $count++;
            else
               echo '--';
         }
         $tot++;
      }
      mysql_free_result($result);
   }
   ta_end();
   echo "\n<p></p>Finished!<br>$count/$tot rated games.\n";


   echo "<hr>Done!!!\n";
   end_html();
}//main


/*!
 * \brief Checks if user-rating-change is needed before game-end is executed.
 * \param $game_end_date Games.Lastchanged of next game to execute rating-recalc on
 * \return array of RatingChangeAdmin-objects to execute if user-rating-changes needed;
 *         null if nothing found for given date
 */
function find_rating_changes( $game_end_date, &$arr_rca )
{
   $out = array();
   $first = true;
   while( count($arr_rca) > 0 )
   {
      if( $arr_rca[0]->Created < $game_end_date )
      {
         $rca = array_shift( $arr_rca );
         $out[] = $rca;

         if( $first )
         {
            echo "<br>\n";
            $first = false;
         }
         echo " <b>RC{$rca->ID}:{$rca->Changes}:{$rca->uid}</b>";
      }
      else
         break;
   }
   return ( count($out) > 0 ) ? $out : null;
}//find_rating_change

function change_user_ratings_for_recalc( $rating_changes )
{
   foreach( $rating_changes as $rca )
   {
      $uid = $rca->uid;

      // load current ratings for user
      $urow = mysql_single_fetch( "recalc_ratings2.find_user1($uid,{$rca->ID})",
         "SELECT Rating2, RatingMin, RatingMax FROM Players " .
         "WHERE ID=$uid AND RatingStatus=".RATING_RATED." LIMIT 1");
      if( is_null($urow) )
         error('internal_error', "recalc_ratings2.find_user2($uid,{$rca->ID})");
      $rating = $urow['Rating2'];
      $rating_min = $urow['RatingMin'];
      $rating_max = $urow['RatingMax'];

      // update rating
      if( $rca->Changes & RCADM_CHANGE_RATING )
         $rating = $rca->Rating;
      if( $rca->Changes & RCADM_RESET_CONFIDENCE )
      {
         $rating_min = $rating - 200 - max(1600 - $rating, 0) * 2 / 15;
         $rating_max = $rating + 200 + max(1600 - $rating, 0) * 2 / 15;
      }
      if( abs($urow['Rating2'] - $rating) > 0.005 )
      {
         change_user_rating( $uid, $rca->Changes, $rating, $rating_min, $rating_max, /*rca-upd*/false );
         echo " RC{$rca->ID}:{$rca->Changes}:$uid:OK";
      }
   }
}//change_user_ratings_for_recalc

?>
