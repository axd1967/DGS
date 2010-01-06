<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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

 /* Author: Jens-Uwe Gaspar */

$TranslateGroups[] = "Tournament";

require_once( 'include/utilities.php' );
require_once( 'include/std_classes.php' );
require_once( 'tournaments/include/tournament_utils.php' );

 /*!
  * \file tournament_participant.php
  *
  * \brief Functions for handling tournament participants: tables TournamentParticipant
  */


 /*!
  * \class TournamentParticipant
  *
  * \brief Class to manage TournamentParticipant-table
  */

define('TP_STATUS_APPLY',     'APPLY');
define('TP_STATUS_REGISTER',  'REGISTER');
define('TP_STATUS_INVITE',    'INVITE');
define('CHECK_TP_STATUS', 'APPLY|REGISTER|INVITE');
define('TPCOUNT_STATUS_ALL',  '*');

define('TP_FLAGS_INVITED',       0x0001); // invited by TD
define('TP_FLAGS_ACK_INVITE',    0x0002); // invite by TD approved by user
define('TP_FLAGS_ACK_APPLY',     0x0004); // user-application approved by TD
define('TP_FLAGS_VIOLATE',       0x0008); // user-registration violates T-restrictions

// lazy-init in TournamentParticipant::get..Text()-funcs
global $ARR_GLOBALS_TOURNAMENT_PARTICIPANT; //PHP5
$ARR_GLOBALS_TOURNAMENT_PARTICIPANT = array();

class TournamentParticipant
{
   var $ID;
   var $tid;
   var $uid;
   var $User; // User-object
   var $Status;
   var $Flags;
   var $Rating;
   var $StartRound;
   var $AuthToken;
   var $Created;
   var $Lastchanged;
   var $Comment;
   var $Notes;
   var $UserMessage;
   var $AdminMessage;

   /*! \brief Constructs TournamentParticipant-object with specified arguments. */
   function TournamentParticipant( $id=0, $tid=0, $uid=0, $user=NULL, $status=null, $flags=0,
                  $rating=NULL, $start_round=1, $auth_token='', $created=0,
                  $lastchanged=0, $comment='', $notes='', $user_message='', $admin_message='' )
   {
      $this->ID = (int)$id;
      $this->tid = (int)$tid;
      $this->uid = (int)$uid;
      $this->User = (is_a($user, 'User')) ? $user : new User( $this->uid );
      $this->setStatus( $status );
      $this->Flags = (int)$flags;
      $this->setRating( $rating );
      $this->setStartRound( $start_round );
      $this->AuthToken = $auth_token;
      $this->Created = (int)$created;
      $this->Lastchanged = (int)$lastchanged;
      $this->Comment = $comment;
      $this->Notes = $notes;
      $this->UserMessage = $user_message;
      $this->AdminMessage = $admin_message;
   }

   function setStatus( $status )
   {
      if( !is_null($status) && !preg_match( "/^(".CHECK_TP_STATUS.")$/", $status ) )
         error('invalid_args', "TournamentParticipant.setStatus($status)");
      $this->Status = $status;
   }

   function setRating( $rating )
   {
      $this->Rating = TournamentUtils::normalizeRating( $rating );
   }

   function hasRating()
   {
      return (abs($this->Rating) < OUT_OF_RATING);
   }

   function setStartRound( $start_round )
   {
      $this->StartRound = limit( (int)$start_round, 1, 255, 1 );
   }

   /*! \brief Returns true, if customized tournament rating is needed for this participant. */
   function needTournamentRating()
   {
      $ratingStatus = $this->User->RatingStatus;
      if( $ratingStatus == RATING_INIT || $ratingStatus == RATING_RATED ) // user has rating
         return false;
      else // unrated user ( $ratingStatus == RATING_NONE )
         return true;
   }

   function to_string()
   {
      return " ID=[{$this->ID}]"
            . ", tid=[{$this->tid}]"
            . ", uid=[{$this->uid}]"
            . sprintf( ", User=[%s]", $this->User->to_string() )
            . ", Status=[{$this->Status}]"
            . sprintf( ",Flags=[0x%x]", $this->Flags)
            . ", Rating=[{$this->Rating}]"
            . ", StartRound=[{$this->StartRound}]"
            . ", AuthToken=[{$this->AuthToken}]"
            . ", Created=[{$this->Created}]"
            . ", Lastchanged=[{$this->Lastchanged}]"
            . ", Comment=[{$this->Comment}]"
            . ", Notes=[{$this->Notes}]"
            . ", UserMessage=[{$this->UserMessage}]"
            . ", AdminMessage=[{$this->AdminMessage}]"
         ;
   }

   /*! \brief Inserts or updates tournament-participant in database. */
   function persist()
   {
      if( $this->ID > 0 )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   /*! \brief Builds query-part for persistance (insert or update). */
   function build_persist_query_part( $withCreated )
   {
      // Rating/StartRound are checked
      if( is_null($this->Status) )
         error('invalid_args', 'TournamentParticipant.build_persist_query_part.check.status(null)');

      return  " tid='{$this->tid}'"
            . ",uid='{$this->uid}'"
            . ",Status='{$this->Status}'"
            . ",Flags='{$this->Flags}'"
            . ",Rating='{$this->Rating}'"
            . ",StartRound='{$this->StartRound}'"
            . ",AuthToken='" . mysql_addslashes($this->AuthToken) . "'"
            . ( $withCreated ? ",Created=FROM_UNIXTIME({$this->Created})" : '' )
            . ",Lastchanged=FROM_UNIXTIME({$this->Lastchanged})"
            . ",Comment='" . mysql_addslashes($this->Comment) . "'"
            . ",Notes='" . mysql_addslashes($this->Notes) . "'"
            . ",UserMessage='" . mysql_addslashes($this->UserMessage) . "'"
            . ",AdminMessage='" . mysql_addslashes($this->AdminMessage) . "'"
         ;
   }

   /*!
    * \brief Inserts TournamentParticipant-entry.
    * \note sets Created=NOW, Lastchanged=NOW
    * \note sets ID to inserted Tournament.ID
    */
   function insert()
   {
      global $NOW;
      $this->Created = $NOW;
      $this->Lastchanged = $NOW;

      $result = db_query( "TournamentParticipant::insert({$this->ID})",
            "INSERT INTO TournamentParticipant SET "
            . $this->build_persist_query_part(true)
         );
      if( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   /*!
    * \brief Updates TournamentParticipant-entry.
    * \note sets Lastchanged=NOW
    */
   function update()
   {
      global $NOW;
      $this->Lastchanged = $NOW;

      $result = db_query( "TournamentParticipant::update({$this->ID})",
            "UPDATE TournamentParticipant SET "
            . $this->build_persist_query_part()
            . " WHERE ID='{$this->ID}' LIMIT 1"
         );
      return $result;
   }

   // ------------ static functions ----------------------------

   /*!
    * \brief Returns non-null array with count of TournamentParticipants for given tournament and TP-status.
    * \return array( TP_STATUS_... => count, '*' => summary-count )
    */
   function count_tournament_participants( $tid, $status=NULL )
   {
      $query_status = (is_null($status)) ? '' : " AND Status='".mysql_addslashes($status)."'";
      $result = db_query( "TournamentParticipant.count_tournament_participants($tid,$status)",
            "SELECT Status, COUNT(*) AS X_Count FROM TournamentParticipant "
            . "WHERE tid='$tid' $query_status GROUP BY Status" );

      $out = array();
      $sum = 0;
      while( $row = mysql_fetch_array( $result ) )
      {
         $cnt = (int)@$row['X_Count'];
         $out[$row['Status']] = $cnt;
         $sum += $cnt;
      }
      mysql_free_result($result);
      $out[TPCOUNT_STATUS_ALL] = $sum;
      return $out;
   }

   /*! \brief Deletes TournamentParticipant-entry for given tournament- and reg-id. */
   function delete_tournament_participant( $tid, $rid )
   {
      $result = db_query( "TournamentParticipant::delete_tournament_participant($tid,$rid)",
         "DELETE FROM TournamentParticipant WHERE ID='$rid' AND tid='$tid' LIMIT 1" );
      return $result;
   }

   /*! \brief Returns db-fields to be used for query of TournamentParticipant-object. */
   function build_query_sql()
   {
      // TournamentParticipant: ID,tid,uid,Status,Flags,Rating,StartRound,AuthToken,Created,Lastchanged,Comment,Notes
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'TP.*',
         'UNIX_TIMESTAMP(TP.Created) AS X_Created',
         'UNIX_TIMESTAMP(TP.Lastchanged) AS X_Lastchanged',
         'TPP.Name', 'TPP.Handle', 'TPP.Country', 'TPP.Rating2', 'TPP.RatingStatus',
         'TPP.RatedGames', 'TPP.Finished',
         'UNIX_TIMESTAMP(TPP.Lastaccess) AS X_Lastaccess' );
      $qsql->add_part( SQLP_FROM,
         'TournamentParticipant AS TP',
         'INNER JOIN Players AS TPP ON TPP.ID=TP.uid' );
      return $qsql;
   }

   /*! \brief Returns TournamentParticipant-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $tp = new TournamentParticipant(
            // from TournamentParticipant
            @$row['ID'],
            @$row['tid'],
            @$row['uid'],
            User::new_from_row( $row ), // from Players TPP
            @$row['Status'],
            @$row['Flags'],
            @$row['Rating'],
            @$row['StartRound'],
            @$row['AuthToken'],
            @$row['X_Created'],
            @$row['X_Lastchanged'],
            @$row['Comment'],
            @$row['Notes'],
            @$row['UserMessage'],
            @$row['AdminMessage']
         );
      return $tp;
   }

   /*!
    * \brief Checks, if user is participant for given tournament and status
    *        (TP_STATUS_...) within; returns status if given status is null.
    */
   function isTournamentParticipant( $tid, $uid, $status=null )
   {
      $row = mysql_single_fetch( "TournamentParticipant.isTournamentParticipant($tid,$uid)",
         "SELECT Status FROM TournamentParticipant WHERE tid='$tid' AND uid='$uid' LIMIT 1" );
      if( is_null($status) )
         return @$row['Status'];
      else
         return ( strcmp($status, @$row['Status']) == 0 );
   }

   /*!
    * \brief Loads and returns TournamentParticipant-object for given tournament-ID
    *        and user-id and registration-id (PK); NULL if nothing found.
    */
   function load_tournament_participant( $tid, $uid, $rid=0, $check_tid=true, $check_uid=false )
   {
      $result = NULL;
      if( $tid > 0 && $uid > GUESTS_ID_MAX )
      {
         $qsql = TournamentParticipant::build_query_sql();
         if( $rid > 0 ) // primary-key
            $qsql->add_part( SQLP_WHERE, "TP.ID='$rid'" );
         else
            $qsql->add_part( SQLP_WHERE,
               "TP.tid='$tid'",
               "TP.uid='$uid'" );
         $qsql->add_part( SQLP_LIMIT, '1' );

         $row = mysql_single_fetch( "TournamentParticipant.load_tournament_participant($tid,$uid,$rid)",
            $qsql->get_select() );

         if( $row )
         {
            if( $rid > 0 ) // load by reg-id, check for matching tid and uid
            {
               if( $check_tid && @$row['tid'] != $tid )
                  error('invalid_args', "TournamentParticipant.load_tournament_participant.check.tid($tid,$uid,$rid)");
               if( $check_uid && @$row['uid'] != $uid )
                  error('invalid_args', "TournamentParticipant.load_tournament_participant.check.uid($tid,$uid,$rid)");
            }
            $result = TournamentParticipant::new_from_row( $row );
         }
      }
      return $result;
   }

   /*! \brief Returns enhanced (passed) ListIterator with TournamentParticipant-objects of given tournament. */
   function load_tournament_participants( $iterator, $tid )
   {
      $qsql = TournamentParticipant::build_query_sql();
      $qsql->add_part( SQLP_WHERE, "TP.tid='$tid'" );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentParticipant.load_tournament_participants", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $tourney = TournamentParticipant::new_from_row( $row );
         $iterator->addItem( $tourney, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   function getStatusText( $status=null, $with_value=false, $info_mode=false )
   {
      global $ARR_GLOBALS_TOURNAMENT_PARTICIPANT;

      // lazy-init of texts
      if( !isset($ARR_GLOBALS_TOURNAMENT_PARTICIPANT['STATUS']) )
      {
         $arr = array();
         $arr[TP_STATUS_APPLY]    = T_('Apply#TP_status');
         $arr[TP_STATUS_REGISTER] = T_('Register#TP_status');
         $arr[TP_STATUS_INVITE]   = T_('Invite#TP_status');
         $ARR_GLOBALS_TOURNAMENT_PARTICIPANT['STATUS'] = $arr;

         $arr = array();
         $arr['']                 = T_('You are not registered for this tournament.');
         $arr[TP_STATUS_APPLY]    = T_('Your registration needs to be verified by a tournament director.');
         $arr[TP_STATUS_REGISTER] = T_('You are registered for this tournament.');
         $arr[TP_STATUS_INVITE]   = T_('The invitation for this tournament needs your verification.');
         $ARR_GLOBALS_TOURNAMENT_PARTICIPANT['STATUS_INFO'] = $arr;
      }

      if( $info_mode )
      {
         $key = 'STATUS_INFO';
         if( !$status ) $status = '';
         if( !isset($ARR_GLOBALS_TOURNAMENT_PARTICIPANT[$key][$status]) )
            error('invalid_args', "TournamentParticipant.getStatusText($status,$key)");

         $status_str = $ARR_GLOBALS_TOURNAMENT_PARTICIPANT[$key][$status];
         return $status_str;
      }
      else
      {
         $key = 'STATUS';
         if( is_null($status) )
            return $ARR_GLOBALS_TOURNAMENT_PARTICIPANT[$key];

         if( !isset($ARR_GLOBALS_TOURNAMENT_PARTICIPANT[$key][$status]) )
            error('invalid_args', "TournamentParticipant.getStatusText($status,$key)");
         $status_str = $ARR_GLOBALS_TOURNAMENT_PARTICIPANT[$key][$status];
         return ( $with_value ) ? sprintf( '%s (%s)', $status, $status_str ) : $status_str;
      }
   }

   /*! \brief Returns flags-text for given int-bitmask or all flags-texts (if arg=null). */
   function getFlagsText( $flags=null )
   {
      global $ARR_GLOBALS_TOURNAMENT_PARTICIPANT;

      // lazy-init of texts
      if( !isset($ARR_GLOBALS_TOURNAMENT_PARTICIPANT['FLAGS']) )
      {
         $arr = array();
         $arr[TP_FLAGS_INVITED]     = T_('Invited#TP_flag');
         $arr[TP_FLAGS_ACK_INVITE]  = T_('ACK-Invite#TP_flag');
         $arr[TP_FLAGS_ACK_APPLY]   = T_('ACK-Apply#TP_flag');
         $arr[TP_FLAGS_VIOLATE]     = T_('REG-Violate#TP_flag');
         $ARR_GLOBALS_TOURNAMENT_PARTICIPANT['FLAGS'] = $arr;
      }
      else
         $arr = $ARR_GLOBALS_TOURNAMENT_PARTICIPANT['FLAGS'];
      if( is_null($flags) )
         return $arr;

      $out = array();
      foreach( $arr as $flagmask => $flagtext )
         if( $flags & $flagmask ) $out[] = $flagtext;
      return implode(', ', $out);
   }

   /*! \brief Returns array with notes about registering users. */
   function build_notes( $deny_reason=null, $intro=true )
   {
      $notes = array();
      if( !is_null($deny_reason) )
      {
         $notes[] = sprintf( '<color darkred><b>%s:</b></color> %s',
               T_('Registration restricted'), $deny_reason );
         $notes[] = null; // empty line
      }

      if( $intro )
      {
         $notes[] = T_('Questions and support requests regarding this tournament '
                     . 'can be directed to the tournament directors.');
         $notes[] = null; // empty line

         $notes[] = T_('You will need a custom rating when you don\'t have a DGS-rating yet or '
               .  "if you don't want to start with your DGS-rating.");
         $notes[] = T_('If you enter a non-default starting round or a custom rating, '
               . "your application needs to be verified by a tournament director.\n"
               . 'Therefore please add your reasoning for the changes in the user-message '
               . 'box to accellerate the registration process.');
         $notes[] = null; // empty line
      }

      $notes[] = sprintf(
            T_('Registration status:<ul>'
               . '<li>%1$s = user is not registered for tournament'."\n" // unregistered
               . '<li>%2$s = user-application needs verification by tournament director'."\n" // APPLY
               . '<li>%3$s = user has been invited by tournament director'."\n" // INVITE
               . '<li>%4$s = user has been successfully registered'."\n" // REGISTER
               . '</ul>'),
            NO_VALUE,
            TournamentParticipant::getStatusText(TP_STATUS_APPLY, true),
            TournamentParticipant::getStatusText(TP_STATUS_INVITE, true),
            TournamentParticipant::getStatusText(TP_STATUS_REGISTER, true)
         );
      return $notes;
   }

   /*! \brief Returns registration-link-text for given user. */
   function getLinkTextRegistration( $tourney, $uid, $reg_user_status=null )
   {
      if( is_null($reg_user_status) )
         $reg_user_status = TournamentParticipant::isTournamentParticipant( $tourney->ID, $uid );

      if( count($tourney->allow_register($uid)) ) // not allowed
         return ($reg_user_status) ? T_('Show my registration') : '';
      else
         return ($reg_user_status) ? T_('Edit my registration') : T_('Registration');
   }

} // end of 'TournamentParticipant'

?>
