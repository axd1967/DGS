<?php
/*
Dragon Go Server
Copyright (C) 2001-  Jens-Uwe Gaspar

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

require_once 'include/std_functions.php';


// categories for db-tables for checking & performing user-account-deletion
define('UACCDEL_DEL', 'UACCDEL_DEL'); // table-candidate for deletion
define('UACCDEL_BASE', 'UACCDEL_BASE'); // base-table of a user-account
define('UACCDEL_STOP', 'UACCDEL_STOP'); // table with entries prevent deletion
define('UACCDEL_WARN', 'UACCDEL_WARN'); // table with entries should be checked before deletion
define('UACCDEL_SKIP', 'UACCDEL_SKIP'); // table with entries irrelevant for checking


/*!
 * \class Admin
 *
 * \brief Class to provide admin-related functions.
 */
class Admin
{
   private static $USER_DB_TABLES = array(
         // table => [ category, fields with user-id, ... ] ; kept in order of importance for checking
         'Players' => array( UACCDEL_DEL, 'ID' ),
         'ConfigBoard' => array( UACCDEL_BASE, 'User_ID' ),
         'ConfigPages' => array( UACCDEL_BASE, 'User_ID' ),
         'Profiles' => array( UACCDEL_BASE, 'User_ID' ),
         'UserQuota' => array( UACCDEL_BASE, 'uid' ),
         'Verification' => array( UACCDEL_BASE, 'uid' ),

         'BulletinRead' => array( UACCDEL_DEL, 'uid' ),
         'FeatureVote' => array( UACCDEL_DEL, 'Voter_ID' ),
         'Forumreads' => array( UACCDEL_DEL, 'User_ID' ),
         'IpStats' => array( UACCDEL_DEL, 'uid' ),
         'MoveStats' => array( UACCDEL_DEL, 'uid' ),
         'TournamentVisit' => array( UACCDEL_DEL, 'uid' ),
         'WaitingroomJoined' => array( UACCDEL_DEL, 'opp_id' ),

         'Games' => array( UACCDEL_STOP, 'Black_ID', 'White_ID', 'ToMove_ID' ),
         'Ratinglog' => array( UACCDEL_STOP, 'uid' ),
         'GamePlayers' => array( UACCDEL_STOP, 'uid' ),
         'GameInvitation' => array( UACCDEL_STOP, 'uid' ),
         'MessageCorrespondents' => array( UACCDEL_STOP, 'uid' ),
         'Translationlog' => array( UACCDEL_STOP, 'Player_ID' ),
         'FAQlog' => array( UACCDEL_STOP, 'uid' ),
         'RatingChangeAdmin' => array( UACCDEL_STOP, 'uid' ),
         'Tournament' => array( UACCDEL_STOP, 'Owner_ID' ),
         'TournamentDirector' => array( UACCDEL_STOP, 'uid' ),
         'TournamentParticipant' => array( UACCDEL_STOP, 'uid' ),
         'Posts' => array( UACCDEL_STOP, 'User_ID' ), // perhaps WARN ?
         'Contribution' => array( UACCDEL_STOP, 'uid' ),
         'Bulletin' => array( UACCDEL_STOP, 'uid' ),
         'BulletinTarget' => array( UACCDEL_STOP, 'uid' ),
         'Feature' => array( UACCDEL_STOP, 'Editor_ID' ),
         'GameSgf' => array( UACCDEL_STOP, 'uid' ),
         'Shape' => array( UACCDEL_STOP, 'uid' ),
         'Survey' => array( UACCDEL_STOP, 'uid' ),
         'SurveyUser' => array( UACCDEL_STOP, 'uid' ),
         'SurveyVote' => array( UACCDEL_STOP, 'uid' ),
         'Waitingroom' => array( UACCDEL_STOP, 'uid' ),

         'Bio' => array( UACCDEL_WARN, 'uid' ),
         'Contacts' => array( UACCDEL_WARN, 'uid' ),
         'Contacts' => array( UACCDEL_WARN, 'cid' ),
         'Folders' => array( UACCDEL_WARN, 'uid' ),
         'Forumlog' => array( UACCDEL_WARN, 'User_ID' ),
         'Observers' => array( UACCDEL_WARN, 'uid' ),

         'GameStats' => array( UACCDEL_SKIP, 'uid', 'oid' ),
         'GamesNotes' => array( UACCDEL_SKIP, 'uid' ),
         'GamesPriority' => array( UACCDEL_SKIP, 'uid' ),
         'MoveSequence' => array( UACCDEL_SKIP, 'uid' ),
         'TournamentGames' => array( UACCDEL_SKIP, 'Challenger_uid', 'Defender_uid' ),
         'TournamentLadder' => array( UACCDEL_SKIP, 'uid' ),
         'TournamentNews' => array( UACCDEL_SKIP, 'uid' ),
         'TournamentResult' => array( UACCDEL_SKIP, 'uid' ),
         'Tournamentlog' => array( UACCDEL_SKIP, 'uid', 'actuid' ),
      );


   // ---------- Static Class functions ----------------------------

   /*!
    * \brief Checks, if user-account could be deleted or why not.
    * \return string with errors preventing account-deletion; false = account-deletion ok
    */
   public static function check_user_account_deletion( $uid )
   {
      if ( !is_numeric($uid) || $uid <= GUESTS_ID_MAX )
         error('invalid_args', "Admin:check_user_account_deletion.check_uid($uid)");
      $uid = (int)$uid;

      $out = array();
      foreach ( self::$USER_DB_TABLES as $table => $conf )
      {
         $category = $conf[0];
         if ( $category == UACCDEL_STOP || $category == UACCDEL_WARN )
         {
            for ( $i=1; $i < count($conf); $i++ )
            {
               $userkey = $conf[$i];
               $row = mysql_single_fetch("Admin::check_user_account_deletion.count($uid,$table,$userkey)",
                     "SELECT COUNT(*) AS X_Count FROM $table WHERE $userkey=$uid" );
               $count = $row['X_Count'];

               if ( $count > 0 )
                  $out[] = sprintf('%s.%s=%s', $table, $userkey, $count );
            }
         }
      }

      return implode(', ', $out);
   }//check_user_account_deletion

} // end of 'Admin'

?>
