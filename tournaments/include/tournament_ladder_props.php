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

require_once 'include/db_classes.php';
require_once 'include/std_classes.php';
require_once 'tournaments/include/tournament_globals.php';

 /*!
  * \file tournament_ladder_props.php
  *
  * \brief Functions for ladder-specific tournament properties: tables TournamentLadderProps
  */


 /*!
  * \class TournamentLadderProps
  *
  * \brief Class to manage TournamentLadderProps-table for ladder-specific properties
  */

global $ENTITY_TOURNAMENT_LADDER_PROPS; //PHP5
$ENTITY_TOURNAMENT_LADDER_PROPS = new Entity( 'TournamentLadderProps',
      FTYPE_PKEY, 'tid',
      FTYPE_CHBY,
      FTYPE_INT,  'tid', 'ChallengeRangeAbsolute',
      FTYPE_DATE, 'Lastchanged'
   );

class TournamentLadderProps
{
   var $tid;
   var $Lastchanged;
   var $ChangedBy;
   var $ChallangeRangeAbsolute;

   /*! \brief Constructs TournamentLadder-object with specified arguments. */
   function TournamentLadderProps( $tid=0, $lastchanged=0, $changed_by='', $challenge_range_abs=0 )
   {
      $this->tid = (int)$tid;
      $this->Lastchanged = (int)$lastchanged;
      $this->ChangedBy = $changed_by;
      $this->ChallengeRangeAbsolute = (int)$challenge_range_abs;
   }

   function to_string()
   {
      return print_r($this, true);
   }

   /*! \brief Inserts or updates tournament-ladder-props in database. */
   function persist()
   {
      if( TournamentLadderProps::isTournamentLadderProps($tid) )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   function insert()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->insert( "TournamentLadderProps.insert(%s)" );
   }

   function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->update( "TournamentLadderProps.update(%s)" );
   }

   function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "TournamentLadderProps.delete(%s)" );
   }

   function fillEntityData( &$data=null )
   {
      if( is_null($data) )
         $data = $GLOBALS['ENTITY_TOURNAMENT_LADDER_PROPS']->newEntityData();
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'ChangedBy', $this->ChangedBy );
      $data->set_value( 'ChallengeRangeAbsolute', $this->ChallengeRangeAbsolute );
      return $data;
   }

   /*! \brief Checks if all ladder-properties are valid; return error-list, empty if ok. */
   function check_properties()
   {
      $errors = array();

      if( $this->ChallengeRangeAbsolute == 0 )
         $errors[] = T_('There must be at least one USED challenge range configuration.');

      return $errors;
   }

   /*! \brief Returns array( header, notes-array ) with this properties in textual form. */
   function build_notes_props()
   {
      $arr_props = array();

      // challenge-range
      $arr = array( T_('You can challenge the highest position matching the following conditions') );
      if( $this->ChallengeRangeAbsolute < 0 )
         $arr[] = T_('anyone above your own ladder position');
      elseif( $this->ChallengeRangeAbsolute > 0 )
         $arr[] = sprintf( T_('%s positions above your own'), $this->ChallengeRangeAbsolute );
      $arr_props[] = $arr;

      return array( T_('The ladder is configured by the following properties') . ':', $arr_props );
   }

   /*!
    * \brief Enhances ladder with additional info/data (Challenge-range).
    * \return TournamentLadder of given user or null if not in ladder.
    */
   function make_ladder_info( &$iterator, $uid )
   {
      $tl_user = null;
      while( is_null($tl_user) && list(,$arr_item) = $iterator->getListIterator() )
      {
         $tladder = $arr_item[0];
         if( $tladder->uid == $uid )
            $tl_user = $tladder;
      }
      $iterator->resetListIterator();
      if( is_null($tl_user) )
         return null;

      // highest challenge-rank
      $user_rank = $tl_user->Rank;
      $high_rank = $tl_user->Rank;
      if( $this->ChallengeRangeAbsolute < 0 )
         $high_ranks = 1;
      elseif( $this->ChallengeRangeAbsolute > 0 )
         $high_rank = $user_rank - $this->ChallengeRangeAbsolute;

      while( list(,$arr_item) = $iterator->getListIterator() )
      {
         list( $tladder, $orow ) = $arr_item;
         if( $tladder->Rank >= $high_rank && $tladder->Rank < $user_rank )
            $tladder->AllowChallenge = true;
      }
      $iterator->resetListIterator();

      return $tl_user;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of TournamentLadderProps-objects for given tournament-id. */
   function build_query_sql( $tid )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_LADDER_PROPS']->newQuerySQL('TLP');
      $qsql->add_part( SQLP_WHERE, "TLP.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentLadder-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $tlp = new TournamentLadderProps(
            // from TournamentLadderProps
            @$row['tid'],
            @$row['X_Lastchanged'],
            @$row['ChangedBy'],
            @$row['ChallengeRangeAbsolute']
         );
      return $tlp;
   }

   /*! \brief Checks, if tournament ladder-props existing for given tournament. */
   function isTournamentLadderProps( $tid )
   {
      return (bool)mysql_single_fetch( "TournamentLadderProps::isTournamentLadderProps($tid)",
         "SELECT 1 FROM TournamentLadderProps WHERE tid='$tid' LIMIT 1" );
   }

   /*!
    * \brief Loads and returns TournamentLadderProps-object for given tournament-ID.
    * \return NULL if nothing found; TournamentLadderProps otherwise
    */
   function load_tournament_ladder_props( $tid )
   {
      if( $tid <=0 )
         return NULL;

      $qsql = TournamentLadderProps::build_query_sql( $tid );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "TournamentLadderProps::load_tournament_ladder_props($tid)",
         $qsql->get_select() );
      return ($row) ? TournamentLadderProps::new_from_row($row) : NULL;
   }

   function get_edit_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_NEW );
      return $statuslist;
   }

} // end of 'TournamentLadderProps'
?>
