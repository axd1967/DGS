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

   /*! \brief Constructs TournamentLadderProps-object with specified arguments. */
   function TournamentLadderProps( $tid=0, $lastchanged=0, $changed_by='',
         $challenge_range_abs=0 )
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

      return array( T_('The ladder is configured with the following properties') . ':', $arr_props );
   }

   /*!
    * \brief Enhances ladder with additional info/data (Challenge-range).
    * \param $iterator ListIterator on ordered TournamentLadder with iterator-Index on Rank & uid
    * \return TournamentLadder of given user or null if not in ladder.
    */
   function fill_ladder_challenge_range( &$iterator, $uid )
   {
      $tl_user = $iterator->getIndexValue( 'uid', $uid, 0 );
      if( is_null($tl_user) )
         return null;

      // highest challenge-rank
      $user_rank = $tl_user->Rank;
      $high_rank = $this->calc_highest_challenge_rank( $tl_user->Rank );

      for( $pos=$user_rank; $pos >= $high_rank; $pos-- )
      {
         $tladder = $iterator->getIndexValue( 'Rank', $pos, 0 );
         if( $tladder->Rank >= $high_rank && $tladder->Rank < $user_rank )
            $tladder->AllowChallenge = true;
      }

      return $tl_user;
   }

   /*!
    * \brief Enhances ladder with additional info/data (incoming challenge-games).
    * \param $iterator ListIterator on ordered TournamentLadder with iterator-Index on uid
    * \param $tgame_iterator ListIterator on TournamentGames
    */
   function fill_ladder_running_games( &$iterator, $tgame_iterator )
   {
      while( list(,$arr_item) = $tgame_iterator->getListIterator() )
      {
         list( $tgame, $orow ) = $arr_item;

         // identify TLadder of defender and challenger
         $df_tladder = $iterator->getIndexValue( 'uid', $tgame->Defender_uid, 0 );
         if( !is_null($df_tladder) )
         {
            $ch_tladder = $iterator->getIndexValue( 'uid', $tgame->Challenger_uid, 0 );
            if( !is_null($ch_tladder) )
            {
               $tgame->Defender_tladder = $df_tladder;
               $tgame->Challenger_tladder = $ch_tladder;
               $df_tladder->add_running_game( $tgame );
            }
         }
      }
   }

   /*!
    * \brief Returns highest allowed challenge rank.
    * \param $ch_rank challenger-rank
    * \note wording: 1 is "higher" than 2 in ladder.
    */
   function calc_highest_challenge_rank( $ch_rank )
   {
      $high_rank = $ch_rank;

      if( $this->ChallengeRangeAbsolute < 0 )
         $high_rank = 1;
      elseif( $this->ChallengeRangeAbsolute > 0 )
         $high_rank = $ch_rank - $this->ChallengeRangeAbsolute;

      return $high_rank;
   }

   /*!
    * \brief Verifies challenge to defender; return errors-list or empty if ok.
    * \param $tladder_ch TournamentLadder from challenger
    * \param $tladder_df TournamentLadder from defender
    */
   function verify_challenge( $tladder_ch, $tladder_df )
   {
      $errors = array();

      $high_rank = $this->calc_highest_challenge_rank( $tladder_ch->Rank );
      if( $tladder_ch->Rank < $tladder_df->Rank )
         $errors[] = T_('You can only challenge users above your own position.');
      if( $tladder_df->Rank < $high_rank )
         $errors[] = sprintf( T_('Defender rank #%s is out of your rank challenge range [#%s..#%s].'),
                              $tladder_df->Rank, $high_rank, $tladder_ch->Rank - 1 );

      return $errors;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of TournamentLadderProps-objects for given tournament-id. */
   function build_query_sql( $tid )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_LADDER_PROPS']->newQuerySQL('TLP');
      $qsql->add_part( SQLP_WHERE, "TLP.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentLadderProps-object created from specified (db-)row. */
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
