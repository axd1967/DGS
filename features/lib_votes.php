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

 /* Author: Jens-Uwe Gaspar */

$TranslateGroups[] = "Common";

require_once( 'include/std_classes.php' );
require_once( 'include/classlib_userquota.php' ); // for FEATURE_POINTS_MAX_VALUE


/*!
 * \file lib_votes.php
 *
 * \brief Functions for feature-voting.
 */

define('FEAT_SUBJECT_WRAPLEN', 55);

// feature.status
define('FEATSTAT_NEW',  'NEW');  // newly added, can be voted or rejected (NACK)
define('FEATSTAT_WORK', 'WORK'); // in work by developers
define('FEATSTAT_DONE', 'DONE'); // feature implemented, but unreleased
define('FEATSTAT_LIVE', 'LIVE'); // feature released on production-server
define('FEATSTAT_NACK', 'NACK'); // feature rejected (will not be done)

// featurevote.points
define('FEATVOTE_MAXPOINTS', 5);

// date-format for features
define('DATEFMT_FEATLIST', 'Y-m-d');
define('DATEFMT_VOTELIST', 'Y-m-d');
define('DATEFMT_FEATURE',  'Y-m-d&\n\b\s\p;H:i');

// conditions on user to allow voting
define('VOTE_MIN_RATEDGAMES', 5); // #games
define('VOTE_MIN_DAYS_LASTMOVED', 30); // #days


 /*!
  * \class Feature
  *
  * \brief Class to handle feature-list with subject and status
  */
class Feature
{
   /*! \brief ID (PK from db). */
   var $id;
   /*! \brief Status (can be one of FEATSTAT_). */
   var $status;
   /*! \brief Subject-description of feature. */
   var $subject;
   /*! \brief Long description of feature. */
   var $description;
   /*! \brief user-id of feature-editor. */
   var $editor;
   /*! \brief Date when feature has been added (unix-time). */
   var $created;
   /*! \brief Date when feature has been last updated (unix-time). */
   var $lastchanged;

   /*! \brief FeatureVote-object, see func load_featurevote. */
   var $featurevote;

   /*!
    * \brief Constructs Feature-object with specified arguments: created and lastchanged are in UNIX-time.
    *        $id may be 0 to add a new feature
    */
   function Feature( $id=0, $status=FEATSTAT_NEW, $subject='', $description='', $editor=0, $created=0, $lastchanged=0 )
   {
      if( !is_numeric($editor) || $editor < 0 )
         error('invalid_user', "feature.Feature.check.editor($id,$editor)");
      $this->id = (int) $id;
      $this->set_status( $status );
      $this->set_subject( $subject );
      $this->set_description( $description );
      $this->editor = (int) $editor;
      $this->created = (int) $created;
      $this->lastchanged = (int) $lastchanged;
   }

   /*! \brief Sets valid status */
   function set_status( $status )
   {
      if( !preg_match( "/^(NEW|WORK|DONE|LIVE|NACK)$/", $status ) )
         error('invalid_status', "feature.set_status($status)");

      $this->status = $status;
   }

   /*! \brief Returns true, if feature-status is final (and can't be changed). */
   function is_final_status()
   {
      return ( $this->status == FEATSTAT_LIVE || $this->status == FEATSTAT_NACK );
   }

   /*! \brief Sets description */
   function set_description( $description )
   {
      if( is_null($description) )
         $this->description = '';
      else
         $this->description = preg_replace( "/(\r\n|\n|\r)/s", "\n", trim($description) );
   }

   /*!
    * \brief Sets subject after doing some replacements
    *        (remove double-LFs, remove starting/trailing whitespaces and LFs).
    */
   function set_subject( $subject )
   {
      if( is_null($subject) )
         $this->subject = '';
      else
         $this->subject = preg_replace( "/(\r\n|\n|\r)+/s", " ", trim($subject) );
   }

   /*! \brief Returns true, if feature can be voted on (NEW status); no user-specific checks. */
   function allow_vote()
   {
      return (bool) ( $this->status == FEATSTAT_NEW );
   }

   /*!
    * \brief Returns true, if current (admin) user is allowed to edit this feature (NEW status).
    *        Always allowed for super-admin.
    */
   function allow_edit()
   {
      if( Feature::is_super_admin() ) // super-admin can always edit
         return true;
      if( !Feature::is_admin() ) // only admin can edit
         return false;

      return (bool) ( $this->status == FEATSTAT_NEW );
   }


   /*!
    * \brief Updates current Feature-data into database (may replace existing feature
    *        and set editor=current-user and lastchanged=NOW).
    */
   function update_feature()
   {
      global $player_row, $NOW;
      $this->editor = @$player_row['ID'];
      $this->lastchanged = $NOW;

      $update_query = 'REPLACE INTO FeatureList SET'
         . ' ID=' . (int)$this->id
         . ", Status='" . mysql_addslashes($this->status) . "'"
         . ", Subject='" . mysql_addslashes($this->subject) . "'"
         . ", Description='" . mysql_addslashes($this->description) . "'"
         . ', Editor_ID=' . (int)$this->editor
         . ', Created=FROM_UNIXTIME(' . $this->created .')'
         . ', Lastchanged=FROM_UNIXTIME(' . $this->lastchanged .')'
         ;
      $result = db_query( "feature.update_feature({$this->id},{$this->subject})",
         $update_query );
   }

   /*! \brief Returns true, if delete-feature allowed (checks constraints). */
   function can_delete_feature()
   {
      // check if there are votes for this feature to delete
      if( $this->id )
      {
         $row = mysql_single_fetch( "feature.check_delete_feature({$this->id})",
            "SELECT fid FROM FeatureVote WHERE fid={$this->id} LIMIT 1" );
         $has_votes = (bool)$row;
      }
      else
         $has_votes = false;

      return !$has_votes;
   }

   /*!
    * \brief Deletes current Feature from database if no votes found (only as admin).
    * \return number of deleted rows
    */
   function delete_feature()
   {
      if( !Feature::is_admin() )
         error('feature_edit_not_allowed', "feature.delete_feature({$this->id})");

      if( !$this->can_delete_feature() )
         error('constraint_votes_delete_feature', "feature.delete_feature({$this->id})");

      $delete_query = "DELETE FROM FeatureList WHERE ID='{$this->id}' LIMIT 1";
      db_query( "feature.delete_feature({$this->id})", $delete_query );
      return mysql_affected_rows();
   }


   /*!
    * \brief Loads featurevote for this Feature-object (if existing).
    *        Returns null if no feature-vote found for feature.
    */
   function load_featurevote( $voter )
   {
      $fvote = FeatureVote::load_featurevote( $this->id, $voter );
      $this->featurevote = $fvote;
      return $fvote;
   }

   /*!
    * \brief Updates current FeatureVote-data into database (may replace existing featurevote
    *        and sets IP and lastchanged=NOW).
    * \return number of points to add to UserQuota-feature-points (can be negative)
    */
   function update_vote( $voter, $points )
   {
      $fpoints = 0; // consumed feature-points
      if( is_null($this->featurevote) )
      {// no vote so far
         $this->featurevote = FeatureVote::new_featurevote( $this->id, $voter, $points );
         $fpoints = -$points;
      }
      else
      {// already voted
         $old_points = $this->featurevote->get_points();
         $this->featurevote->set_points( $points );
         if( $old_points != $points )
            $fpoints = abs($old_points) - abs($points);
      }

      //error_log("F.update_vote: " . $this->to_string());
      $this->featurevote->update_vote();
      return $fpoints;
   }

   /*!
    * \brief Fixes user user-quota feature-points on feature-status change.
    *
    * \note "return" all feature-points if feature rejected: !NACK -> NACK
    * \note no "return" of feature-points for status-change back from rejected: NACK -> !NACK
    *       (because feature-points have already been returned for rejected features;
    *       votes are kept, principally allowing to increase ones points by "taking"
    *       back voted points => so better not undo feature-rejection)
    * \note "return" min(own-vote,average-vote) of feature-points if feature: !LIVE -> LIVE
    * \return sum of all "returned" feature-points
    */
   function fix_user_quota_feature_points( $old_status, $new_status )
   {
      if( $old_status == $new_status ) // no status-change
         return 0;
      if( $old_status == FEATSTAT_NACK ) // no "return" if NACK-status changed to something
         return 0;

      // feature rejected OR feature went live -> "return" all feature-points
      if( ( $old_status != FEATSTAT_NACK && $new_status == FEATSTAT_NACK )
            || ( $old_status != FEATSTAT_LIVE && $new_status == FEATSTAT_LIVE ) )
      {
         $row = FeatureVote::load_featurevote_summary( $this->id );
         if( !$row ) return 0;
         $sumPoints = @$row['sumAbsPoints'] + 0;

         // return all consumed points to respective voter
         db_query( "Feature({$this->id}).fix_user_quota_feature_points($old_status,$new_status)",
              'UPDATE UserQuota AS UQ '
               . "INNER JOIN FeatureVote AS FV ON FV.Voter_ID=UQ.uid AND FV.fid={$this->id} AND FV.Points<>0 "
               . 'SET UQ.FeaturePoints = LEAST('.FEATURE_POINTS_MAX_VALUE.', UQ.FeaturePoints + ABS(FV.Points))' );
         return $sumPoints;
      }

      return 0;
   }


   /*! \brief Returns string-representation of this object (for debugging purposes). */
   function to_string()
   {
      return "Feature(id={$this->id}): "
         . "status=[{$this->status}], "
         . "subject=[{$this->subject}], "
         . "description=[{$this->description}], "
         . "editor=[{$this->editor}], "
         . "created=[{$this->created}], lastchanged=[{$this->lastchanged}]"
         . ( ($this->featurevote ) ? $this->featurevote->to_string() : '');
   }


   // ---------- Static Class functions ----------------------------

   /*!
    * \brief Returns true, if current player has admin-rights for feature-functionality.
    * \param $superadmin true=superadmin has ALL rights, false=feature-admin has add-rights
    */
   function is_admin( $superadmin=false )
   {
      global $player_row;
      $chk_adminlevel = ( $superadmin ) ? ADMIN_DEVELOPER : (ADMIN_VOTE|ADMIN_DEVELOPER);
      $is_admin = (bool) ( @$player_row['admin_level'] & $chk_adminlevel );
      //return false; // for easy testing
      return $is_admin;
   }

   /*! \brief Returns true, if current player has super-admin-rights for feature-functionality. */
   function is_super_admin()
   {
      return Feature::is_admin(true);
   }

   /*! \brief Returns array used for filter on status for feature/votes-list. */
   function build_filter_selection_status( $fname )
   {
      return array(
         T_('All#filtervote')      => '',
         T_('New#filtervote')      => "$fname='".FEATSTAT_NEW."'",
         T_('Open#filtervote')     => "$fname IN ('".FEATSTAT_NEW."','".FEATSTAT_WORK."','".FEATSTAT_DONE."')",
         T_('In Work#filtervote')  => "$fname='".FEATSTAT_WORK."'",
         T_('Done#filtervote')     => "$fname='".FEATSTAT_DONE."'",
         T_('Online#filtervote')   => "$fname='".FEATSTAT_LIVE."'",
         T_('Rejected#filtervote') => "$fname='".FEATSTAT_NACK."'",
      );
   }

   /*! \brief Returns array with notes about feature-voting. */
   function build_feature_notes( $deny_reason=null, $intro=true )
   {
      $notes = array();
      if( !is_null($deny_reason) )
      {
         $notes[] = sprintf( '<color darkred><b>%s:</b></color> %s',
               T_('Voting restricted'), $deny_reason );
         $notes[] = null; // empty line
      }

      if( $intro )
      {
         $notes[] = T_('Feature or improvement suggestions are to be discussed in the '
                     . '<home forum/index.php>forums</home> first.');
         $notes[] = T_('Features can only be added to this list by a vote-admin.');
         $notes[] = null; // empty line
      }

      $intro_open_str = ($intro) // Open
         ? sprintf( T_('<li>%1$s = show status %2$s + %3$s'."\n"),
                    T_('Open#filtervote'), FEATSTAT_NEW, FEATSTAT_WORK )
         : '';
      $notes[] = sprintf(
            T_('Feature status:<ul>'
               . '<li>%1$s = new feature (can be voted upon)'."\n" // NEW
               . '%9$s' // Open (may be empty)
               . '<li>%2$s = %3$s = feature implementation started by developer'."\n" // WORK
               . '<li>%4$s = feature implemented (and tested), but not released yet'."\n" // DONE
               . '<li>%5$s = %6$s = feature released and online'."\n" // LIVE
               . '<li>%7$s = %8$s = feature rejected'."\n" // NACK
               . '</ul>'),
            FEATSTAT_NEW,
            FEATSTAT_WORK, T_('In Work#filtervote'),
            FEATSTAT_DONE,
            FEATSTAT_LIVE, T_('Online#filtervote'),
            FEATSTAT_NACK, T_('Rejected#filtervote'),
            $intro_open_str
         );
      return $notes;
   }

   /*!
    * \brief Returns null if current user ($player_row) is allowed to participate in voting;
    *        otherwise return deny-reason.
    */
   function allow_vote_check()
   {
      global $player_row, $NOW;

      if( @$player_row['ID'] <= GUESTS_ID_MAX )
         return T_('Voting is not allowed for guest user.');

      if( @$player_row['AdminOptions'] & ADMOPT_DENY_VOTE )
         return T_('Voting on features has been denied.');

      // minimum 5 finished+rated games, 500 moves, moved within 30 days
      if( @$player_row['RatedGames'] < VOTE_MIN_RATEDGAMES
            || ($NOW - @$player_row['X_LastMove']) > VOTE_MIN_DAYS_LASTMOVED * 86400 )
      {
         return sprintf( T_('To be able to vote you have to finish %s rated games and '."\n"
                           . 'actively play in games during the last %s days.'),
                         VOTE_MIN_RATEDGAMES, VOTE_MIN_DAYS_LASTMOVED );
      }

      //return 'testing'; // for easy testing
      return null;
   }

   /*!
    * \brief Returns QuerySQL for feature-list page.
    * param ftable: Table object
    * param user_id: id of current logged-in user
    */
   function build_query_feature_list( $ftable, $user_id )
   {
      if( !is_numeric($user_id) )
         error('invalid_user', "Feature.build_query_feature_list($user_id)");

      // build SQL-query
      $qsql = new QuerySQL();
      $qsql->add_part_fields( Feature::get_query_fields() );
      $qsql->add_part_fields( FeatureVote::get_query_fields() );
      $qsql->add_part( SQLP_FROM, 'FeatureList AS FL' );
      $qsql->add_part( SQLP_FROM, "LEFT JOIN FeatureVote AS FV ON FL.ID=FV.fid AND FV.Voter_ID='$user_id'" );
      $query_ffilter = $ftable->get_query(); // clause-parts for filter
      $qsql->merge( $query_ffilter );

      return $qsql;
   }

   /*! \brief Returns db-fields to be used for query of Feature-object. */
   function get_query_fields( $short=false )
   {
      if( $short )
         return array( 'FL.ID', 'FL.Status', 'FL.Subject' );

      return array(
         'FL.ID', 'FL.Status', 'FL.Subject', 'FL.Description', 'FL.Editor_ID',
         'IFNULL(UNIX_TIMESTAMP(FL.Created),0) AS FLCreatedU',
         'IFNULL(UNIX_TIMESTAMP(FL.Lastchanged),0) AS FLLastchangedU'
      );
   }

   /*!
    * \brief Returns Feature-object for specified user $editor,
    *        created=$NOW set and all others in default-state.
    */
   function new_feature( $editor, $fid=0 )
   {
      global $NOW;

      // id=set, status=NEW, subject='', description='', editor=$editor, created=NOW, lastchanged
      $feature = new Feature( $fid );
      $feature->editor  = $editor;
      $feature->created = $NOW;
      return $feature;
   }

   /*! \brief Returns Feature-object created from specified (db-)row with fields defined by func fields_feature. */
   function new_from_row( $row )
   {
      $feature = new Feature(
            $row['ID'], $row['Status'], $row['Subject'], @$row['Description'],
            @$row['Editor_ID']+0, @$row['FLCreatedU'], @$row['FLLastchangedU'] );
      return $feature;
   }

   /*!
    * \brief Returns Feature-object for specified feature-id $id;
    *        returns null if no feature found.
    */
   function load_feature( $id )
   {
      if( !is_numeric($id) )
         error('invalid_feature', "feature.load_feature($id)");

      $fields = implode(',', Feature::get_query_fields());
      $row = mysql_single_fetch("feature.load_feature2($id)",
            "SELECT $fields FROM FeatureList AS FL WHERE FL.ID='$id' LIMIT 1");
      if( !$row )
         return null;

      return Feature::new_from_row( $row );
   }

} // end of 'Feature'



 /*!
  * \class FeatureVote
  *
  * \brief Class to handle vote on feature
  */
class FeatureVote
{
   /*! \brief feature-id (FK). */
   var $fid;
   /*! \brief user-id of feature-voter. */
   var $voter;
   /*! \brief vote points on feature: 0=neutral, 1=low, 9=high points, -1=not-wanted. */
   var $points;
   /*! \brief Date when feature has been last updated (unix-time). */
   var $lastchanged;
   /*! \brief IP of voter. */
   var $ip;


   /*!
    * \brief Constructs Feature-object with specified arguments: created and lastchanged are in UNIX-time.
    *        $id may be 0 to add a new feature
    */
   function FeatureVote( $fid=0, $voter=0, $points=0, $lastchanged=0, $ip='' )
   {
      if( !is_numeric($voter) || !is_numeric($voter) || $voter < 0 )
         error('invalid_user', "featurevote.FeatureVote($id,$voter)");
      $this->fid = (int) $fid;
      $this->voter = (int) $voter;
      $this->set_points( $points );
      $this->lastchanged = (int) $lastchanged;
      $this->ip = $ip;
   }

   /*! \brief Sets valid points (<0,0,>0). */
   function set_points( $points )
   {
      if( !is_numeric($points) || abs($points) > FEATVOTE_MAXPOINTS )
         error('invalid_status', "featurevote.set_points($points)");

      $this->points = $points;
   }

   /*! \brief Returns points. */
   function get_points()
   {
      return $this->points;
   }


   /*!
    * \brief Updates current FeatureVote-data into database (may replace existing featurevote,
    *        set lastchanged=NOW and IP of voter).
    */
   function update_vote()
   {
      global $NOW;
      $this->lastchanged = $NOW;
      $this->ip = (string)@$_SERVER['REMOTE_ADDR'];

      $update_query = 'REPLACE INTO FeatureVote SET'
         . ' fid=' . (int)$this->fid
         . ', Voter_ID=' . (int)$this->voter
         . ', Points=' . (int)$this->points
         . ', Lastchanged=FROM_UNIXTIME(' . $this->lastchanged .')'
         . ", IP='{$this->ip}'"
         ;
      db_query( "feature.update_vote({$this->fid},{$this->voter},{$this->points})",
         $update_query );
   }


   /*! \brief Returns string-representation of this object (for debugging purposes). */
   function to_string()
   {
      return "FeatureVote(fid={$this->fid}): "
         . "voter=[{$this->voter}], "
         . "points=[{$this->points}], "
         . "lastchanged=[{$this->lastchanged}], "
         . "ip=[{$this->ip}]";
   }


   // ---------- Static Class functions ----------------------------

   /*!
    * \brief Returns error-message if points are invalid; otherwise return null (=points ok).
    * \param points the points to check
    * \param max_points max. amount of feature-points that can be used for voting;
    *                   used if user less than FEATVOTE_MAXPOINTS remaining feature-points
    *                   restricted by his quota.
    */
   function check_points( $points, $max_points=FEATVOTE_MAXPOINTS )
   {
      if( !is_numeric($points) )
         return sprintf( T_('points [%s] must be numeric'), $points );
      if( abs($points) > FEATVOTE_MAXPOINTS )
         return sprintf( T_('points [%1$s] must be in range [%2$s,%3$s]'),
                         $points, -FEATVOTE_MAXPOINTS, FEATVOTE_MAXPOINTS );
      if( abs($points) > $max_points )
         return sprintf( T_('points [%1$s] must be in range [%2$s,%3$s] (restricted by feature-points quota)'),
                         $points, -$max_points, $max_points );
      return null;
   }

   /*!
    * \brief Returns QuerySQL for feature-vote-list page.
    * \param mquery QuerySQL object to merge
    */
   function build_query_featurevote_list( $mquery=null )
   {
      // build SQL-query
      $qsql = new QuerySQL();
      $qsql->add_part_fields( Feature::get_query_fields(true) );
      $qsql->add_part( SQLP_FIELDS,
         'SUM(FV.Points) AS sumPoints',
         'COUNT(FV.fid) AS countVotes',
         'SUM(IF(FV.Points>0,1,0)) AS countYes',
         'SUM(IF(FV.Points<0,1,0)) AS countNo'
         );
      $qsql->add_part( SQLP_FROM, 'FeatureList AS FL' );
      $qsql->add_part( SQLP_FROM, 'LEFT JOIN FeatureVote AS FV ON FL.ID=FV.fid' );
      $qsql->add_part( SQLP_WHERE, 'FV.Points<>0' ); // abstention from voting
      $qsql->add_part( SQLP_GROUP, 'FV.fid' );
      $qsql->add_part( SQLP_HAVING, 'sumPoints is not null' );
      if( !is_null($mquery) )
         $qsql->merge( $mquery );

      return $qsql;
   }

   /*!
    * \brief Returns row-array with summary about feature-votes for given feature-id.
    * \return for avgPoints=0: row( avgAbsPoints => average, sumAbsPoints => sum-points, cntVotes => vote-count )
    *         for avgPoints>0: row( sumAvgPoints => sum-points )
    */
   function load_featurevote_summary( $fid, $avgPoints=0 )
   {
      if( !is_numeric($fid) || $fid < 0 )
         error('invalid_args', "FeatureVote.load_featurevote_summary($fid)");

      $qsql = new QuerySQL();
      if( $avgPoints > 0 )
         $qsql->add_part( SQLP_FIELDS,
            "SUM(IF($avgPoints<ABS(FV.Points),$avgPoints,ABS(FV.Points))) AS sumAvgPoints" );
      else
         $qsql->add_part( SQLP_FIELDS,
            'COUNT(*) AS cntVotes',
            'AVG(ABS(FV.Points)) AS avgAbsPoints',
            'SUM(ABS(FV.Points)) AS sumAbsPoints' );
      $qsql->add_part( SQLP_FROM, 'FeatureVote AS FV' );
      $qsql->add_part( SQLP_WHERE,
         "FV.fid='$fid'",
         'FV.Points<>0' ); // abstention from voting
      $query = $qsql->get_select() . ' LIMIT 1';

      $row = mysql_single_fetch( "FeatureVote.load_featurevote_summary($fid)", $query );
      return $row;
   }

   /*! \brief Returns db-fields to be used for query of Feature-object. */
   function get_query_fields()
   {
      return array(
         'FV.fid', 'FV.Voter_ID', 'FV.Points',
         'IFNULL(UNIX_TIMESTAMP(FV.Lastchanged),0) AS FVLastchangedU',
         'FV.IP',
      );
   }

   /*!
    * \brief Returns FeatureVote-object for specified feature-id, user $voter and points,
    *        lastchanged=$NOW set and all others in default-state.
    */
   function new_featurevote( $fid, $voter, $points )
   {
      // fid=set, voter=$voter, points=$points, lastchanged, ip
      $fvote = new FeatureVote( $fid, $voter, $points );
      $fvote->voter = $voter;
      return $fvote;
   }

   /*!
    * \brief Returns Feature-object created from specified (db-)row with fields defined by func fields_feature;
    *        returns null if row['fid']=0.
    */
   function new_from_row( $row )
   {
      if( $row['fid'] != 0 )
      {
         $fvote = new FeatureVote(
               $row['fid'], $row['Voter_ID'], $row['Points'], $row['FVLastchangedU'], $row['IP'] );
      }
      else
         $fvote = null;
      return $fvote;
   }

   /*!
    * \brief Returns FeatureVote-object for specified feature-id $id and voter $voter;
    *        returns null if no feature found.
    */
   function load_featurevote( $fid, $voter )
   {
      if( !is_numeric($fid) )
         error('invalid_feature', "featurevote.load_feature($fid,$voter)");

      $fields = implode(',', FeatureVote::get_query_fields());
      $row = mysql_single_fetch("featurevote.load_feature2($fid,$voter)",
            "SELECT $fields FROM FeatureVote AS FV WHERE FV.fid='$fid' and FV.Voter_ID='$voter' LIMIT 1");
      if( !$row )
         return null;

      return FeatureVote::new_from_row( $row );
   }

   /*! \brief Returns text informing about remaining feature-points. */
   function getFeaturePointsText( $points )
   {
      return sprintf( T_('You have %s points available for voting on features.'), $points );
   }

} // end of 'FeatureVote'

?>
