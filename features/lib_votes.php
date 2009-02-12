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

$TranslateGroups[] = "Common";

// feature.status
define('FEATSTAT_NEW',  'NEW');  // newly added, needs ACK to be voteable
define('FEATSTAT_NACK', 'NACK'); // will not be done
define('FEATSTAT_ACK',  'ACK');  // ready to be voted
define('FEATSTAT_WORK', 'WORK'); // can be voted, though already in work by developers
define('FEATSTAT_DONE', 'DONE'); // feature implemented

// featurevote.points
define('FEATVOTE_MAXPOINTS', 5);

// date-format for features
define('DATEFMT_FEATLIST', 'Y-m-d');
define('DATEFMT_VOTELIST', 'Y-m-d');
define('DATEFMT_FEATURE',  'Y-m-d&\n\b\s\p;H:i');

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
      if( !is_numeric($editor) || !is_numeric($editor) || $editor < 0 )
         error('invalid_user', "feature.Feature($id,$editor)");
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
      if( !preg_match( "/^(NEW|NACK|ACK|WORK|DONE)$/", $status ) )
         error('invalid_status', "feature.set_status($status)");

      $this->status = $status;
   }

   /*! \brief Sets description */
   function set_description( $description )
   {
      if( is_null($description) )
         $this->description = '';
      else
         $this->description = preg_replace( "/(\r\n|\n|\r)+/s", "\n", trim($description) );
   }

   /*!
    * \brief Sets subject after doing some replacements
    *        (remove double-LFs, remove starting/trailing whitespaces).
    */
   function set_subject( $subject )
   {
      if( is_null($subject) )
         $this->subject = '';
      else
         $this->subject = preg_replace( "/(\r\n|\n|\r)+/s", "\n", trim($subject) );
   }

   /*!
    * \brief Returns true, if specified user is allowed to edit this feature (ACK or WORK status).
    *        Not allowed for invalid user, guest or non-current user.
    */
   function allow_vote( $uid )
   {
      if( !Feature::allow_voting() )
         return false;

      // not allowed for invalid user, guest or not current user
      global $player_row;
      $my_id = (int)@$player_row['ID'];
      if( !is_numeric($uid) || $uid != $my_id || $uid <= GUESTS_ID_MAX )
         return false;

      return (bool) ( $this->status == FEATSTAT_ACK || $this->status == FEATSTAT_WORK );
   }

   /*!
    * \brief Returns true, if specified user is allowed to edit this feature (not in WORK or DONE status).
    *        Not allowed for invalid user or guest. Always allowed for admin.
    * \see allow_user_edit()
    */
   function allow_edit( $uid )
   {
      if( !Feature::allow_voting() ) // not even for admin
         return false;

      if( Feature::is_super_admin() )
         return true;
      if( !Feature::allow_user_edit( $uid ) )
         return false;

      $allow_edit_status = (bool) ( $this->status != FEATSTAT_WORK && $this->status != FEATSTAT_DONE );
      return (bool) ( $this->editor == $uid && $allow_edit_status );
   }


   /*!
    * \brief Updates current Feature-data into database (may replace existing feature
    *        and set lastchanged=NOW).
    */
   function update_feature()
   {
      global $NOW;
      $this->lastchanged = $NOW;

      $result = mysql_query("SELECT ID FROM Players WHERE ID={$this->editor} LIMIT 1")
         or error('mysql_query_failed', "feature.find_user({$this->editor})");
      if( !$result || mysql_num_rows($result) != 1 )
         error('unknown_user', "feature.find_user2({$this->editor})");
      mysql_free_result($result);

      $update_query = 'REPLACE INTO FeatureList SET'
         . ' ID=' . (int)$this->id
         . ", Status='" . mysql_addslashes($this->status) . "'"
         . ", Subject='" . mysql_addslashes($this->subject) . "'"
         . ", Description='" . mysql_addslashes($this->description) . "'"
         . ', Editor_ID=' . (int)$this->editor
         . ', Created=FROM_UNIXTIME(' . $this->created .')'
         . ', Lastchanged=FROM_UNIXTIME(' . $this->lastchanged .')'
         ;
      $result = mysql_query( $update_query )
         or error('mysql_query_failed', "feature.update_feature({$this->id},{$this->subject})");
   }

   /*! \brief Deletes current Feature from database. */
   function delete_feature()
   {
      ## TODO: only delete if no votes existing, or cascading delete
      $delete_query = "DELETE FROM FeatureList "
         . "WHERE ID='{$this->id}' LIMIT 1";
      $result = mysql_query( $delete_query )
         or error('mysql_query_failed', 'feature.delete_feature');
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
    *        and set lastchanged=NOW).
    */
   function update_vote( $voter, $points )
   {
      if( is_null($this->featurevote) )
         $this->featurevote = FeatureVote::new_featurevote( $this->id, $voter, $points );
      else
         $this->featurevote->set_points( $points );

//error_log("F.update_vote: " . $this->to_string());
      $this->featurevote->update_vote();
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
      $chk_adminlevel = ( $superadmin ) ? ADMIN_DEVELOPER : ADMINGROUP_EXECUTIVE;
      $is_admin = (bool) ( @$player_row['admin_level'] & $chk_adminlevel );
      //return false; // for easy testing
      return $is_admin;
   }

   /*!
    * \brief Returns true, if current player has super-admin-rights for feature-functionality.
    */
   function is_super_admin()
   {
      return Feature::is_admin(true);
   }

   /*! \brief Returns true, if current user is allowed to participate in voting (add/edit/vote features). */
   function allow_voting()
   {
      global $player_row;
      // TODO: check number of moves, number of games, not-blocked from voting, etc ...?
      //return false; // for easy testing
      return true;
   }

   /*!
    * \brief Returns true, if specified user is allowed to edit a feature (check on user).
    *        Not allowed for invalid user or guest. Always allowed for admin.
    */
   function allow_user_edit( $uid )
   {
      if( !Feature::allow_voting() )
         return false;

      // not allowed for invalid user or guest
      if( !is_numeric($uid) || $uid <= GUESTS_ID_MAX )
         return false;

      global $player_row;
      if( $uid != (int)@$player_row['ID'] && !Feature::is_super_admin() )
         return false;
      else
         return true;
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
   function get_query_fields()
   {
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
            $row['ID'], $row['Status'], $row['Subject'], $row['Description'],
            $row['Editor_ID'], $row['FLCreatedU'], $row['FLLastchangedU'] );
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
      if( !is_numeric($points) || $points < -FEATVOTE_MAXPOINTS || $points > FEATVOTE_MAXPOINTS )
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
      $result = mysql_query( $update_query )
         or error('mysql_query_failed', "feature.update_vote({$this->fid},{$this->voter},{$this->points})");
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

   /*! \brief Returns error-message if points are invalid; otherwise return null (=points ok). */
   function check_points( $points )
   {
      if( !is_numeric($points) )
         return sprintf( T_('points [%s] must be numeric'), $points );
      if( $points < -FEATVOTE_MAXPOINTS || $points > FEATVOTE_MAXPOINTS )
         return sprintf( T_('points [%1$s] must be in range [%2$s,%3$s]'),
                         $points, -FEATVOTE_MAXPOINTS, FEATVOTE_MAXPOINTS );
      return null;
   }

   /*!
    * \brief Returns QuerySQL for feature-vote-list page.
    * param ftable: Table object
    */
   function build_query_featurevote_list( $vtable )
   {
      // build SQL-query
      $qsql = new QuerySQL();
      $qsql->add_part_fields( Feature::get_query_fields() );
      $qsql->add_part( SQLP_FIELDS,
         'SUM(FV.Points) AS sumPoints',
         'COUNT(FV.fid) AS countVotes',
         'SUM(IF(FV.Points>0,1,0)) AS countYes',
         'SUM(IF(FV.Points<0,1,0)) AS countNo'
         );
      $qsql->add_part( SQLP_FROM, 'FeatureList AS FL' );
      $qsql->add_part( SQLP_FROM, 'LEFT JOIN FeatureVote AS FV ON FL.ID=FV.fid' );
      $qsql->add_part( SQLP_GROUP, 'FV.fid' );
      $qsql->add_part( SQLP_HAVING, 'sumPoints is not null' );
      $query_vfilter = $vtable->get_query(); // clause-parts for filter
      $qsql->merge( $query_vfilter );

      return $qsql;
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

} // end of 'FeatureVote'

?>
