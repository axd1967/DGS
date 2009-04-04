<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once( 'include/std_classes.php' );

 /*!
  * \file tournament_rules.php
  *
  * \brief Functions for handling tournament rules (games-settings): tables TournamentRules
  */


 /*!
  * \class TournamentRules
  *
  * \brief Class to manage TournamentRules-table with games-related tournament-settings
  */

define('TRULE_HANDITYPE_CONV', 'CONV');
define('TRULE_HANDITYPE_PROPER', 'PROPER');
define('TRULE_HANDITYPE_NIGIRI', 'NIGIRI');
//define('TRULE_HANDITYPE_DOUBLE', 'DOUBLE');
define('CHECK_TRULE_HANDITYPE', 'CONV|PROPER|NIGIRI');

//TODO(later) Flags
//define('TR_FLAGS_MANUAL',  0x0001); // TD setups T-games manually (H/K, what else?)

// lazy-init in TournamentRules::get..Text()-funcs
$ARR_GLOBALS_TOURNAMENT_RULES = array();

class TournamentRules
{
   var $ID;
   var $tid;
   var $Lastchanged;
   var $Flags;
   var $Notes;

   // keep fields in "sync" with waiting-room functionality:

   var $Size;
   var $Handicaptype;
   var $Handicap;
   var $Komi;
   var $AdjHandicap;
   var $MinHandicap;
   var $MaxHandicap;
   var $StdHandicap;
   var $Maintime;
   var $Byotype;
   var $Byotime;
   var $Byoperiods;
   var $WeekendClock;
   var $Rated;

   /*! \brief Constructs TournamentRules-object with specified arguments. */
   function TournamentRules( $id=0, $tid=0, $lastchanged=0, $flags=0, $notes='',
         $size=19, $handicaptype='CONV', $handicap=0, $komi=6.5, $adj_handicap=0,
         $min_handicap=0, $max_handicap=127, $std_handicap=true,
         $maintime=450, $byotype='JAP', $byotime=15, $byoperiods=10,
         $weekendclock=true, $rated=false )
   {
      $this->ID = (int)$id;
      $this->tid = (int)$tid;
      $this->Lastchanged = (int)$lastchanged;
      $this->Flags = (int)$flags;
      $this->Notes = $notes;
      $this->Size = (int)$size;
      $this->Handicaptype = $handicaptype;
      $this->Handicap = (int)$handicap;
      $this->Komi = (float)$komi;
      $this->AdjHandicap = (int)$adj_handicap;
      $this->MinHandicap = (int)$min_handicap;
      $this->MaxHandicap = (int)$max_handicap;
      $this->StdHandicap = (bool)$std_handicap;
      $this->Maintime = (int)$maintime;
      $this->Byotype = $byotype;
      $this->Byotime = (int)$byotime;
      $this->Byoperiods = (int)$byoperiods;
      $this->WeekendClock = (bool)$weekendclock;
      $this->Rated = (bool)$rated;
   }

   function to_string()
   {
      return " ID=[{$this->ID}]"
            . ", tid=[{$this->tid}]"
            . ", Lastchanged=[{$this->Lastchanged}]"
            . sprintf( ",Flags=[0x%x]", $this->Flags)
            . ", Notes=[{$this->Notes}]"
            . ", Size=[{$this->Size}]"
            . ", Handicaptype=[".strtoupper($this->Handicaptype)."]"
            . ", Handicap=[{$this->Handicap}]"
            . ", Komi=[{$this->Komi}]"
            . ", AdjHandicap=[{$this->AdjHandicap}]"
            . ", MinHandicap=[{$this->MinHandicap}]"
            . ", MaxHandicap=[{$this->MaxHandicap}]"
            . ", StdHandicap=[{$this->StdHandicap}]"
            . ", Maintime=[{$this->Maintime}]"
            . ", Byotype=[{$this->Byotype}]"
            . ", Byotime=[{$this->Byotime}]"
            . ", Byoperiods=[{$this->Byoperiods}]"
            . ", WeekendClock=[{$this->WeekendClock}]"
            . ", Rated=[{$this->Rated}]"
         ;
   }

   /*! \brief Inserts or updates tournament-rules in database. */
   function persist()
   {
      if( $this->ID > 0 )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   /*! \brief Builds query-part for persistance (insert or update). */
   function build_persist_query_part()
   {
      // Handicaptype/Byotype
      if( !preg_match( "/^(".CHECK_TRULE_HANDITYPE.")$/", $this->Handicaptype ) )
         error('invalid_args', "TournamentRules.build_persist_query_part.check.Handicaptype({$this->tid},{$this->Handicaptype})");
      if( !preg_match( "/^(JAP|CAN|FIS)$/", $this->Byotype ) )
         error('invalid_args', "TournamentRules.build_persist_query_part.check.Byotype({$this->tid},{$this->Byotype})");

      return  " tid='{$this->tid}'"
            . ",Lastchanged=FROM_UNIXTIME({$this->Lastchanged})"
            . ",Flags='{$this->Flags}'"
            . ",Notes='" . mysql_addslashes($this->Notes) . "'"
            . ",Size='{$this->Size}'"
            . sprintf( ",Handicaptype='%s'", mysql_addslashes(strtoupper($this->Handicaptype)) )
            . ",Handicap='{$this->Handicap}'"
            . ",Komi='{$this->Komi}'"
            . ",AdjHandicap='{$this->AdjHandicap}'"
            . ",MinHandicap='{$this->MinHandicap}'"
            . ",MaxHandicap='{$this->MaxHandicap}'"
            . sprintf( ",StdHandicap='%s'", ($this->StdHandicap ? 'Y' : 'N') )
            . ",Maintime='{$this->Maintime}'"
            . sprintf( ",Byotype='%s'", mysql_addslashes($this->Byotype) )
            . ",Byotime='{$this->Byotime}'"
            . ",Byoperiods='{$this->Byoperiods}'"
            . sprintf( ",WeekendClock='%s'", ($this->WeekendClock ? 'Y' : 'N') )
            . sprintf( ",Rated='%s'", ($this->Rated ? 'Y' : 'N') )
         ;
   }

   /*!
    * \brief Inserts TournamentRules-entry.
    * \note sets Lastchanged=NOW
    * \note sets ID to inserted TournamentRules.ID
    */
   function insert()
   {
      global $NOW;
      $this->Lastchanged = $NOW;

      $result = db_query( "TournamentRules::insert({$this->tid})",
            "INSERT INTO TournamentRules SET "
            . $this->build_persist_query_part()
         );
      if( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   /*!
    * \brief Updates TournamentRules-entry.
    * \note sets Lastchanged=NOW
    */
   function update()
   {
      global $NOW;
      $this->Lastchanged = $NOW;

      $result = db_query( "TournamentRules::update({$this->ID})",
            "UPDATE TournamentRules SET "
            . $this->build_persist_query_part()
            . " WHERE ID='{$this->ID}' LIMIT 1"
         );
      return $result;
   }

   /*!
    * \brief Converts this TournamentRules-object to hashmap to be used as
    *        form-value-hash for tournaments/edit_rule.php.
    */
   function convertTournamentRules_to_EditForm( &$vars )
   {
      $vars['_tr_notes'] = $this->Notes;

      $vars['size'] = (int)$this->Size;
      $vars['handicap_type'] = strtolower($this->Handicaptype);
      $vars['komi_n'] = $this->Komi;
      $vars['adj_handicap'] = (int)$this->AdjHandicap;
      $vars['min_handicap'] = (int)$this->MinHandicap;
      $vars['max_handicap'] = min( MAX_HANDICAP, max( 0, (int)$this->MaxHandicap ));
      $vars['stdhandicap'] = (bool)$this->StdHandicap;

      $vars['byoyomitype'] = $this->Byotype;
      if( $this->Byotype == 'JAP' )
         $suffix = '_jap';
      elseif( $this->Byotype == 'CAN' )
         $suffix = '_can';
      elseif( $this->Byotype == 'FIS' )
         $suffix = '_fis';

      $vars['timeunit'] = 'hours';
      $vars['timevalue'] = $this->Maintime;
      time_convert_to_longer_unit( $vars['timevalue'], $vars['timeunit'] );

      $byo_timeunit  = 'hours';
      $byo_timevalue = $this->Byotime;
      time_convert_to_longer_unit( $byo_timevalue, $byo_timeunit );
      $vars["timeunit$suffix"] = $byo_timeunit;
      $vars["byotimevalue$suffix"] = $byo_timevalue;
      if( $this->Byotype != 'FIS' )
         $vars["byoperiods$suffix"] = $this->Byoperiods;

      $vars['weekendclock'] = ($this->WeekendClock) ? 'Y' : 'N';
      $vars['rated'] = ($this->Rated) ? 'Y' : 'N';
   }

   /*! \brief Converts and sets (parsed) form-values in this TournamentRules-object. */
   function convertEditForm_to_TournamentRules( $vars )
   {
      // NOTE: keep "sync'ed with add_to_waitingroom.php"

      $handicap_type = @$vars['handicap_type'];
      switch( (string)$handicap_type )
      {
         case 'conv':
         {
            $handicap = 0; //further computing
            $komi = 0.0;
         }
         break;

         case 'proper':
         {
            $handicap = 0; //further computing
            $komi = 0.0;
         }
         break;

         default: //always available even if waiting room or unrated
            $handicap_type = 'nigiri';
         case 'nigiri':
         {
            $handicap = 0;
            $komi = (float)@$vars['komi_n'];
         }
         break;
      }

      if( !( $komi >= -MAX_KOMI_RANGE && $komi <= MAX_KOMI_RANGE ) )
         error('komi_range', "TournamentRules.convertEditForm_to_TournamentRules.check.komi($komi)");

      if( !( $handicap >= 0 && $handicap <= MAX_HANDICAP ) )
         error('handicap_range', "TournamentRules.convertEditForm_to_TournamentRules.check.handicap($handicap)");

      // handicap adjustment
      $adj_handicap = (int)@$vars['adj_handicap'];
      if( abs($adj_handicap) > MAX_HANDICAP )
         $adj_handicap = ($adj_handicap<0 ? -1 : 1) * MAX_HANDICAP;

      $min_handicap = min( MAX_HANDICAP, max( 0, (int)@$vars['min_handicap'] ));

      $max_handicap = (int)@$vars['max_handicap'];
      if( $max_handicap > MAX_HANDICAP )
         $max_handicap = -1; // don't save potentially changeable "default"

      if( $max_handicap >= 0 && $min_handicap > $max_handicap )
         swap( $min_handicap, $max_handicap );


      $size = min(MAX_BOARD_SIZE, max(MIN_BOARD_SIZE, (int)@$vars['size']));

      // time settings
      $byoyomitype = @$vars['byoyomitype'];
      $timevalue = @$vars['timevalue'];
      $timeunit = @$vars['timeunit'];

      $byotimevalue_jap = @$vars['byotimevalue_jap'];
      $timeunit_jap = @$vars['timeunit_jap'];
      $byoperiods_jap = @$vars['byoperiods_jap'];

      $byotimevalue_can = @$vars['byotimevalue_can'];
      $timeunit_can = @$vars['timeunit_can'];
      $byoperiods_can = @$vars['byoperiods_can'];

      $byotimevalue_fis = @$vars['byotimevalue_fis'];
      $timeunit_fis = @$vars['timeunit_fis'];

      list($hours, $byohours, $byoperiods) =
         interpret_time_limit_forms($byoyomitype, $timevalue, $timeunit,
                                    $byotimevalue_jap, $timeunit_jap, $byoperiods_jap,
                                    $byotimevalue_can, $timeunit_can, $byoperiods_can,
                                    $byotimevalue_fis, $timeunit_fis);

      if( $hours < 1 && ($byohours < 1 || $byoyomitype == 'FIS') )
         error('time_limit_too_small');


      $rated = ( @$vars['rated'] == 'Y' );

      if( ENA_STDHANDICAP )
         $stdhandicap = ( @$vars['stdhandicap'] == 'Y' );
      else
         $stdhandicap = false;

      $weekendclock = ( @$vars['weekendclock'] == 'Y' );


      // parse into this TournamentRules-object
      $this->Notes = @$vars['_tr_notes'];
      $this->Size = $size;
      $this->Handicaptype = strtoupper($handicap_type);
      $this->Handicap = $handicap;
      $this->Komi = round( 2 * $komi ) / 2;
      $this->AdjHandicap = $adj_handicap;
      $this->MinHandicap = $min_handicap;
      $this->MaxHandicap = $max_handicap;
      $this->StdHandicap = (bool)$stdhandicap;
      $this->Maintime = $hours;
      $this->Byotype = $byoyomitype;
      $this->Byotime = $byohours;
      $this->Byoperiods = $byoperiods;
      $this->WeekendClock = (bool)$weekendclock;
      $this->Rated = (bool)$rated;
   } //convertTournamentRules_to_EditForm

   /*! \brief Returns true, if handicap needs to be calculated for this ruleset. */
   function needsCalculatedHandicap()
   {
      return ( $this->Handicaptype == TRULE_HANDITYPE_CONV    // calc
            || $this->Handicaptype == TRULE_HANDITYPE_PROPER  // calc
            || $this->Handicaptype == TRULE_HANDITYPE_NIGIRI  // no handicap
         );
   }

   /*! \brief Returns true, if komi needs to be calculated for this ruleset. */
   function needsCalculatedKomi()
   {
      return ( $this->Handicaptype == TRULE_HANDITYPE_CONV    // calc
            || $this->Handicaptype == TRULE_HANDITYPE_PROPER  // calc
         );
   }

   // ------------ static functions ----------------------------

   /*! \brief Deletes TournamentRules-entry for given id. */
   function delete_tournament_rules( $id )
   {
      $result = db_query( "TournamentRules::delete_tournament_rules($id)",
         "DELETE FROM TournamentRules WHERE ID='$id' LIMIT 1" );
      return $result;
   }

   /*! \brief Returns db-fields to be used for query of TournamentRules-objects for given tournament-id. */
   function build_query_sql( $tid )
   {
      // TournamentRules: ID,tid,Lastchanged,Flags,Size,Handicaptype,Handicap,Komi,
      //     AdjHandicap,MinHandicap,MaxHandicap,StdHandicap,Maintime,Byotype,
      //     Byotime,Byoperiods,WeekendClock,Rated,Notes
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'TR.*',
         'UNIX_TIMESTAMP(TR.Lastchanged) AS X_Lastchanged' );
      $qsql->add_part( SQLP_FROM,
         'TournamentRules AS TR' );
      $qsql->add_part( SQLP_WHERE, "TR.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentRules-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $tp = new TournamentRules(
            // from TournamentRules
            @$row['ID'],
            @$row['tid'],
            @$row['X_Lastchanged'],
            @$row['Flags'],
            @$row['Notes'],
            @$row['Size'],
            @$row['Handicaptype'],
            @$row['Handicap'],
            @$row['Komi'],
            @$row['AdjHandicap'],
            @$row['MinHandicap'],
            @$row['MaxHandicap'],
            ( @$row['StdHandicap'] == 'Y' ),
            @$row['Maintime'],
            @$row['Byotype'],
            @$row['Byotime'],
            @$row['Byoperiods'],
            ( @$row['WeekendClock'] == 'Y' ),
            ( @$row['Rated'] == 'Y' )
         );
      return $tp;
   }

   /*!
    * \brief Loads and returns TournamentRules-object for given tournament-ID.
    */
   function load_tournament_rule( $tid )
   {
      $result = NULL;
      if( $tid > 0 )
      {
         $qsql = TournamentRules::build_query_sql( $tid );
         $qsql->add_part( SQLP_LIMIT, '1' );
         $qsql->add_part( SQLP_ORDER, 'TR.ID DESC' );
         $row = mysql_single_fetch( "TournamentRules.load_tournament_rule($tid)",
            $qsql->get_select() );
         if( $row )
            $result = TournamentRules::new_from_row( $row );
      }
      return $result;
   }

   /*! \brief Returns enhanced (passed) ListIterator with Tournament-objects for given tournament-id. */
   function load_tournament_rules( $iterator, $tid )
   {
      $qsql = TournamentRules::build_query_sql( $tid );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentRules.load_tournament_rules", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while( $row = mysql_fetch_array( $result ) )
      {
         $tourney = TournamentRules::new_from_row( $row );
         $iterator->addItem( $tourney, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }

   /*! \brief Returns flags-text for given int-bitmask or all flags-texts (if arg=null). */
   function getFlagsText( $flags=null )
   {
      // lazy-init of texts
      if( !isset($ARR_GLOBALS_TOURNAMENT_RULES['FLAGS']) )
      {
         $arr = array();
         //TODO $arr[TR_FLAGS_]     = T_('Invited#TP_flag');
         $ARR_GLOBALS_TOURNAMENT_RULES['FLAGS'] = $arr;
      }
      else
         $arr = $ARR_GLOBALS_TOURNAMENT_RULES['FLAGS'];
      if( is_null($flags) )
         return $arr;

      $out = array();
      foreach( $arr as $flagmask => $flagtext )
         if( $flags & $flagmask ) $out[] = $flagtext;
      return implode(', ', $out);
   }

   /*! \brief Returns type-text. */
   function getHandicaptypeText( $type )
   {
      // lazy-init of texts
      $key = 'HANDICAPTYPE';
      if( !isset($ARR_GLOBALS_TOURNAMENT_RULES[$key]) )
      {
         $arr = array();
         $arr[TRULE_HANDITYPE_CONV]   = T_('Conventional handicap#TR_handitype');
         $arr[TRULE_HANDITYPE_PROPER] = T_('Proper handicap#TR_handitype');
         $arr[TRULE_HANDITYPE_NIGIRI] = T_('Even game with nigiri#TR_handitype');
         //$arr[TRULE_HANDITYPE_DOUBLE] = T_('Double game#TR_handitype');
         $ARR_GLOBALS_TOURNAMENT_RULES[$key] = $arr;
      }

      if( is_null($type) )
         return $ARR_GLOBALS_TOURNAMENT_RULES[$key];
      if( !isset($ARR_GLOBALS_TOURNAMENT_RULES[$key][$type]) )
         error('invalid_args', "TournamentRules.getHandicaptypeText($type)");
      return $ARR_GLOBALS_TOURNAMENT_RULES[$key][$type];
   }

} // end of 'TournamentRules'
?>
