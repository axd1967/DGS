<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/dgs_cache.php';
require_once 'include/error_codes.php';
require_once 'include/game_functions.php';
require_once 'include/make_game.php';
require_once 'include/rating.php';
require_once 'include/db/shape.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_helper.php';

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

// note: keep as upper-case to HTYPE_...-consts
define('TRULE_HANDITYPE_CONV',   'CONV');
define('TRULE_HANDITYPE_PROPER', 'PROPER');
define('TRULE_HANDITYPE_NIGIRI', 'NIGIRI');
define('TRULE_HANDITYPE_BLACK',  'BLACK');
define('TRULE_HANDITYPE_WHITE',  'WHITE');
define('TRULE_HANDITYPE_DOUBLE', 'DOUBLE');
define('CHECK_TRULE_HANDITYPE', 'CONV|PROPER|NIGIRI|BLACK|WHITE|DOUBLE');

global $ENTITY_TOURNAMENT_RULES; //PHP5
$ENTITY_TOURNAMENT_RULES = new Entity( 'TournamentRules',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_CHBY,
      FTYPE_INT,  'ID', 'tid', 'ShapeID', 'Flags', 'Size', 'Handicap', 'AdjHandicap', 'MinHandicap', 'MaxHandicap',
                  'Maintime', 'Byotime', 'Byoperiods',
      FTYPE_FLOAT, 'AdjKomi', 'Komi',
      FTYPE_TEXT, 'Notes', 'Ruleset', 'ShapeSnapshot',
      FTYPE_DATE, 'Lastchanged',
      FTYPE_ENUM, 'Handicaptype', 'JigoMode', 'StdHandicap', 'Byotype', 'WeekendClock', 'Rated'
   );

class TournamentRules
{
   private static $ARR_TRULES_TEXTS = array(); // lazy-init in TournamentRules::get..Text()-funcs: [key][id] => text

   public $ID;
   public $tid;
   public $Lastchanged;
   public $ChangedBy;
   public $Flags;
   public $Notes;

   // keep fields in "sync" with waiting-room functionality:

   public $Ruleset;
   public $Size;
   public $Handicaptype;
   public $Handicap;
   public $Komi;
   public $AdjKomi;
   public $JigoMode;
   public $AdjHandicap;
   public $MinHandicap;
   public $MaxHandicap;
   public $StdHandicap;
   public $Maintime;
   public $Byotype;
   public $Byotime;
   public $Byoperiods;
   public $WeekendClock;
   public $Rated;
   public $ShapeID;
   public $ShapeSnapshot;

   // non-DB fields

   public $TourneyType = '';

   /*! \brief Constructs TournamentRules-object with specified arguments. */
   public function __construct( $id=0, $tid=0, $lastchanged=0, $changed_by='', $flags=0, $notes='',
         $ruleset=RULESET_JAPANESE, $size=19, $handicaptype=TRULE_HANDITYPE_CONV,
         $handicap=0, $komi=DEFAULT_KOMI, $adj_komi=0.0, $jigo_mode=JIGOMODE_KEEP_KOMI,
         $adj_handicap=0, $min_handicap=0, $max_handicap=127, $std_handicap=true,
         $maintime=450, $byotype=BYOTYPE_FISCHER, $byotime=15, $byoperiods=10,
         $weekendclock=true, $rated=false, $shape_id=0, $shape_snapshot='' )
   {
      $this->ID = (int)$id;
      $this->tid = (int)$tid;
      $this->Lastchanged = (int)$lastchanged;
      $this->ChangedBy = $changed_by;
      $this->Flags = (int)$flags;
      $this->Notes = $notes;
      $this->setRuleset( $ruleset );
      $this->Size = (int)$size;
      $this->Handicaptype = $handicaptype;
      $this->Handicap = (int)$handicap;
      $this->Komi = (float)$komi;
      $this->AdjKomi = (float)$adj_komi;
      $this->JigoMode = $jigo_mode;
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
      $this->ShapeID = (int)$shape_id;
      $this->ShapeSnapshot = $shape_snapshot;
   }//__construct

   public function setRuleset( $ruleset )
   {
      if ( !preg_match( "/^(".CHECK_RULESETS.")$/", $ruleset ) )
         error('invalid_args', "TournamentRules.setRuleset($ruleset)");
      if ( !preg_match( "/^(".ALLOWED_RULESETS.")$/", $ruleset ) )
         error('feature_disabled', "TournamentRules.setRuleset($ruleset)");
      $this->Ruleset = $ruleset;
   }

   public function to_string()
   {
      return print_r($this, true);
   }

   /*! \brief Inserts or updates tournament-rules in database. */
   public function persist()
   {
      if ( $this->ID > 0 )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   public function insert()
   {
      $this->Lastchanged = $GLOBALS['NOW'];
      $this->checkData();

      $entityData = $this->fillEntityData();
      $result = $entityData->insert( "TournamentRules.insert(%s,{$this->tid})" );
      if ( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   public function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];
      $this->checkData();

      $entityData = $this->fillEntityData();
      $result = $entityData->update( "TournamentRules.update(%s,{$this->tid})" );
      self::delete_cache_tournament_rules( 'TournamentRules.update', $this->ID );
      return $result;
   }

   public function delete()
   {
      $entityData = $this->fillEntityData();
      $result = $entityData->delete( "TournamentRules.delete(%s,{$this->tid})" );
      self::delete_cache_tournament_rules( 'TournamentRules.delete', $this->ID );
      return $result;
   }

   // \internal
   private function checkData()
   {
      if ( !preg_match( "/^(".CHECK_TRULE_HANDITYPE.")$/", $this->Handicaptype ) )
         error('invalid_args', "TournamentRules.checkData.Handicaptype({$this->tid},{$this->Handicaptype})");
      if ( !preg_match( "/^".REGEX_BYOTYPES."$/", $this->Byotype ) )
         error('invalid_args', "TournamentRules.checkData.Byotype({$this->tid},{$this->Byotype})");
   }

   public function fillEntityData()
   {
      // checked fields: Handicaptype/Byotype
      $data = $GLOBALS['ENTITY_TOURNAMENT_RULES']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'ChangedBy', $this->ChangedBy );
      $data->set_value( 'Flags', $this->Flags );
      $data->set_value( 'Notes', $this->Notes );
      $data->set_value( 'Ruleset', $this->Ruleset );
      $data->set_value( 'Size', $this->Size );
      $data->set_value( 'Handicaptype', strtoupper($this->Handicaptype) );
      $data->set_value( 'Handicap', $this->Handicap );
      $data->set_value( 'Komi', $this->Komi );
      $data->set_value( 'AdjKomi', $this->AdjKomi );
      $data->set_value( 'JigoMode', $this->JigoMode );
      $data->set_value( 'AdjHandicap', $this->AdjHandicap );
      $data->set_value( 'MinHandicap', $this->MinHandicap );
      $data->set_value( 'MaxHandicap', $this->MaxHandicap );
      $data->set_value( 'StdHandicap', ($this->StdHandicap ? 'Y' : 'N') );
      $data->set_value( 'Maintime', $this->Maintime );
      $data->set_value( 'Byotype', $this->Byotype );
      $data->set_value( 'Byotime', $this->Byotime );
      $data->set_value( 'Byoperiods', $this->Byoperiods );
      $data->set_value( 'WeekendClock', ($this->WeekendClock ? 'Y' : 'N') );
      $data->set_value( 'Rated', ($this->Rated ? 'Y' : 'N') );
      $data->set_value( 'ShapeID', $this->ShapeID );
      $data->set_value( 'ShapeSnapshot', $this->ShapeSnapshot );
      return $data;
   }

   /*! \brief Converts this TournamentRules-object to hashmap to be used as game-row to create game. */
   public function convertTournamentRules_to_GameRow()
   {
      // NOTE: keep "sync'ed" with new-game handle_add_game()-func
      // NOTE: see also create_game() in 'include/make_game.php'

      $grow = array();
      $grow['double_gid'] = 0;
      $grow['Ruleset'] = $this->Ruleset;
      $grow['Size'] = limit( (int)$this->Size, MIN_BOARD_SIZE, MAX_BOARD_SIZE, 19 );

      $grow['Handicap'] = (int)$this->Handicap;
      $grow['AdjHandicap'] = (int)$this->AdjHandicap;
      $grow['MinHandicap'] = (int)$this->MinHandicap;
      $grow['MaxHandicap'] = DefaultMaxHandicap::limit_max_handicap( (int)$this->MaxHandicap );
      $grow['StdHandicap'] = ($this->StdHandicap) ? 'Y' : 'N';
      $grow['Komi'] = (float)$this->Komi;
      $grow['AdjKomi'] = (float)$this->AdjKomi;
      $grow['JigoMode'] = $this->JigoMode;

      $grow['Byotype'] = $this->Byotype;
      $grow['Maintime'] = (int)$this->Maintime;
      $grow['Byotime'] = (int)$this->Byotime;
      $grow['Byoperiods'] = (int)$this->Byoperiods;

      $grow['WeekendClock'] = ($this->WeekendClock) ? 'Y' : 'N';
      $grow['Rated'] = ($this->Rated) ? 'Y' : 'N';
      $grow['ShapeID'] = $this->ShapeID;
      $grow['ShapeSnapshot'] = $this->ShapeSnapshot;
      return $grow;
   }//convertTournamentRules_to_GameRow

   /*!
    * \brief Converts this TournamentRules-object to hashmap to be used as
    *        form-value-hash for tournaments/edit_rules.php.
    */
   public function convertTournamentRules_to_EditForm( &$vars )
   {
      // NOTE: keep "sync'ed" with new-game handle_add_game()-func

      $vars['_tr_notes'] = $this->Notes;

      $vars['ruleset'] = $this->Ruleset;
      $vars['size'] = (int)$this->Size;
      $std_htype = self::convert_trule_handicaptype_to_stdhtype($this->Handicaptype);
      $cat_htype = get_category_handicaptype($std_htype);
      $vars['cat_htype'] = $cat_htype;
      $vars['color_m'] = $std_htype;
      if ( $cat_htype == CAT_HTYPE_MANUAL )
      {
         $vars['handicap_m'] = (int)$this->Handicap;
         $vars['komi_m'] = (float)$this->Komi;
      }
      else
         $vars['komi_m'] = DEFAULT_KOMI;
      $vars['adj_komi'] = (float)$this->AdjKomi;
      $vars['jigo_mode'] = $this->JigoMode;
      $vars['adj_handicap'] = (int)$this->AdjHandicap;
      $vars['min_handicap'] = (int)$this->MinHandicap;
      $vars['max_handicap'] = DefaultMaxHandicap::limit_max_handicap( (int)$this->MaxHandicap );
      $vars['stdhandicap'] = ($this->StdHandicap) ? 'Y' : 'N';

      $vars['timeunit'] = 'hours';
      $vars['timevalue'] = (int)$this->Maintime;
      time_convert_to_longer_unit( $vars['timevalue'], $vars['timeunit'] );

      $byo_timeunit  = 'hours';
      $byo_timevalue = (int)$this->Byotime;
      time_convert_to_longer_unit( $byo_timevalue, $byo_timeunit );
      $vars['byoyomitype'] = $this->Byotype;
      foreach ( array( 'jap', 'can', 'fis' ) as $suffix )
      {
         $vars["timeunit_$suffix"] = $byo_timeunit;
         $vars["byotimevalue_$suffix"] = $byo_timevalue;
         if ( $suffix == 'jap' || $suffix == 'can' )
            $vars["byoperiods_$suffix"] = (int)$this->Byoperiods;
      }

      $vars['weekendclock'] = ($this->WeekendClock) ? 'Y' : 'N';
      $vars['rated'] = ($this->Rated) ? 'Y' : 'N';

      // shape-game
      $vars['shape'] = $this->ShapeID;
      $vars['snapshot'] = $this->ShapeSnapshot;
   }//convertTournamentRules_to_EditForm

   /*! \brief Converts and sets (parsed) form-values in this TournamentRules-object. */
   public function convertEditForm_to_TournamentRules( $vars, &$errors )
   {
      // NOTE: keep "sync'ed" with new-game handle_add_game()-func

      if ( !$this->TourneyType )
         error('invalid_args', "TournamentRules.convertEditForm_to_TournamentRules.miss_var.TourneyType({$this->tid})");

      $size = min(MAX_BOARD_SIZE, max(MIN_BOARD_SIZE, (int)@$vars['size']));

      $cat_handicap_type = @$vars['cat_htype'];
      $color_m = @$vars['color_m'];
      $handicap_type = ( $cat_handicap_type == CAT_HTYPE_MANUAL ) ? $color_m : $cat_handicap_type;
      switch ( (string)$handicap_type )
      {
         case HTYPE_CONV:
            $handicap = 0; //further computing
            $komi = 0.0;
            break;

         case HTYPE_PROPER:
            $handicap = 0; //further computing
            $komi = 0.0;
            break;

         case HTYPE_DOUBLE:
            if ( $this->TourneyType != TOURNEY_TYPE_ROUND_ROBIN )
               error('invalid_args', "TournamentRules.convertEditForm_to_TournamentRules.bad_htype({$this->tid},{$this->TourneyType},$handicap_type)");
            // fall-through setting H/K

         case HTYPE_BLACK:
         case HTYPE_WHITE:
            $handicap = (int)@$vars['handicap_m'];
            $komi = (float)@$vars['komi_m'];
            break;

         case HTYPE_AUCTION_SECRET:
         case HTYPE_AUCTION_OPEN:
         case HTYPE_YOU_KOMI_I_COLOR:
         case HTYPE_I_KOMI_YOU_COLOR:
            // not supported for tournaments -> fallback to default NIGIRI

         default: //always available even if waiting room or unrated
            $cat_handicap_type = CAT_HTYPE_MANUAL;
            $handicap_type = HTYPE_NIGIRI;
         case HTYPE_NIGIRI:
            $handicap = (int)@$vars['handicap_m'];
            $komi = (float)@$vars['komi_m'];
            break;
      }

      if ( !( $komi >= -MAX_KOMI_RANGE && $komi <= MAX_KOMI_RANGE ) )
         $errors[] = ErrorCode::get_error_text('komi_range');

      if ( !( $handicap >= 0 && $handicap <= MAX_HANDICAP ) )
         $errors[] = ErrorCode::get_error_text('handicap_range');

      // ruleset
      $ruleset = @$vars['ruleset'];
      if ( !preg_match( "/^(".CHECK_RULESETS.")$/", $ruleset ) )
         $errors[] = ErrorCode::get_error_text('unknown_ruleset');
      elseif ( !preg_match( "/^(".ALLOWED_RULESETS.")$/", $ruleset ) )
         $errors[] = ErrorCode::get_error_text('unknown_ruleset');

      // komi adjustment
      $adj_komi = (float)@$vars['adj_komi'];
      if ( abs($adj_komi) > MAX_KOMI_RANGE )
         $adj_komi = ($adj_komi<0 ? -1 : 1) * MAX_KOMI_RANGE;
      if ( floor(2 * $adj_komi) != 2 * $adj_komi ) // <>x.0|x.5
         $adj_komi = ($adj_komi<0 ? -1 : 1) * round(2 * abs($adj_komi)) / 2.0;

      $jigo_mode = (string)@$vars['jigo_mode'];
      if ( $jigo_mode != JIGOMODE_KEEP_KOMI && $jigo_mode != JIGOMODE_ALLOW_JIGO && $jigo_mode != JIGOMODE_NO_JIGO )
         $jigo_mode = JIGOMODE_KEEP_KOMI;

      // handicap adjustment
      $adj_handicap = (int)@$vars['adj_handicap'];
      if ( abs($adj_handicap) > MAX_HANDICAP )
         $adj_handicap = ($adj_handicap<0 ? -1 : 1) * MAX_HANDICAP;

      $min_handicap = min( MAX_HANDICAP, max( 0, (int)@$vars['min_handicap'] ));
      list( $min_handicap, $max_handicap ) =
         DefaultMaxHandicap::limit_min_max_with_def_handicap( $size, $min_handicap, (int)@$vars['max_handicap'] );

      // time settings
      list( $hours, $byohours, $byoperiods ) = self::convertFormTimeSettings( $vars );
      $byoyomitype = @$vars['byoyomitype'];

      if ( $hours < 1 && ($byohours < 1 || $byoyomitype == BYOTYPE_FISCHER) )
         $errors[] = ErrorCode::get_error_text('time_limit_too_small');


      $rated = ( @$vars['rated'] == 'Y' );

      if ( ENABLE_STDHANDICAP )
         $stdhandicap = ( @$vars['stdhandicap'] == 'Y' );
      else
         $stdhandicap = false;

      $weekendclock = ( @$vars['weekendclock'] == 'Y' );

      // handle shape-game
      $shape_id = trim(@$vars['shape']);
      $shape_snapshot = '';
      if ( $shape_id )
      {
         if ( !is_numeric($shape_id) || $shape_id < 0 )
            $errors[] = ErrorCode::get_error_text('bad_shape_id');
         else
         {
            $shape = Shape::load_shape($shape_id, false);
            if ( is_null($shape) )
               $errors[] = ErrorCode::get_error_text('unknown_shape');
            else
            {
               $shape_snapshot = GameSnapshot::build_extended_snapshot( $shape->Snapshot, $shape->Size, $shape->Flags );

               // implicit shape-game defaults
               $size = $shape->Size;
               $stdhandicap = false;
               $rated = false;
            }
         }
      }


      // parse into this TournamentRules-object
      $this->Notes = @$vars['_tr_notes'];
      $this->Ruleset = $ruleset;
      $this->Size = $size;
      $this->Handicaptype = strtoupper($handicap_type);
      $this->Handicap = $handicap;
      $this->Komi = ($komi < 0 ? -1 : 1) * floor( 2 * abs($komi) ) / 2.0;
      $this->AdjKomi = $adj_komi;
      $this->JigoMode = $jigo_mode;
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
      $this->ShapeID = (int)$shape_id;
      $this->ShapeSnapshot = $shape_snapshot;
   } //convertTournamentRules_to_EditForm

   // returns array [ $main_hours, $byohours, $byoperiods ]
   public function convertFormTimeSettings( $map )
   {
      // time settings
      $byoyomitype = @$map['byoyomitype'];
      $timevalue = @$map['timevalue'];
      $timeunit = @$map['timeunit'];

      $byotimevalue_jap = @$map['byotimevalue_jap'];
      $timeunit_jap = @$map['timeunit_jap'];
      $byoperiods_jap = @$map['byoperiods_jap'];

      $byotimevalue_can = @$map['byotimevalue_can'];
      $timeunit_can = @$map['timeunit_can'];
      $byoperiods_can = @$map['byoperiods_can'];

      $byotimevalue_fis = @$map['byotimevalue_fis'];
      $timeunit_fis = @$map['timeunit_fis'];

      return
         interpret_time_limit_forms($byoyomitype, $timevalue, $timeunit,
                                    $byotimevalue_jap, $timeunit_jap, $byoperiods_jap,
                                    $byotimevalue_can, $timeunit_can, $byoperiods_can,
                                    $byotimevalue_fis, $timeunit_fis);
   }//convertFormTimeSettings

   /*! \brief Converts this TournamentRules-object to GameSetup-object to be used to create game; uid must be set later. */
   public function convertTournamentRules_to_GameSetup()
   {
      // store only fields that are no Games-fields already
      $gs = new GameSetup( /*uid*/0 );
      $gs->Handicaptype = self::convert_trule_handicaptype_to_stdhtype($this->Handicaptype);
      $gs->Handicap = (int)$this->Handicap;
      $gs->Komi = (float)$this->Komi;
      $gs->AdjKomi = (float)$this->AdjKomi;
      $gs->JigoMode = $this->JigoMode;
      $gs->AdjHandicap = (int)$this->AdjHandicap;
      $gs->MinHandicap = (int)$this->MinHandicap;
      $gs->MaxHandicap = (int)$this->MaxHandicap;
      return $gs;
   }//convertTournamentRules_to_GameSetup

   /*! \brief Returns true, if handicap needs to be calculated for this ruleset. */
   public function needsCalculatedHandicap()
   {
      return ( $this->Handicaptype == TRULE_HANDITYPE_CONV
            || $this->Handicaptype == TRULE_HANDITYPE_PROPER );
   }

   /*! \brief Returns true, if komi needs to be calculated for this ruleset. */
   public function needsCalculatedKomi()
   {
      return ( $this->Handicaptype == TRULE_HANDITYPE_CONV
            || $this->Handicaptype == TRULE_HANDITYPE_PROPER );
   }

   /*!
    * \brief Checks what score for tournament-game with this tournamet-rules is allowed.
    * \return Returns 0 if only x.0 is allowed, 1 if x.5 allowed, otherwise -1 if x.5 and x.0 allowed.
    * \see TournamentRules.getJigoBehaviourText()
    */
   public function determineJigoBehaviour()
   {
      if ( $this->JigoMode == JIGOMODE_ALLOW_JIGO )
         return 0;
      if ( $this->JigoMode == JIGOMODE_NO_JIGO )
         return 1;

      if ( $this->Handicaptype == TRULE_HANDITYPE_CONV || $this->Handicaptype == TRULE_HANDITYPE_PROPER )
         return -1; // can be x.0|x.5 for CONV|PROPER and AdjkustKomi doesn't change that

      if ( $this->Handicaptype == TRULE_HANDITYPE_NIGIRI
            || $this->Handicaptype == TRULE_HANDITYPE_DOUBLE
            || $this->Handicaptype == TRULE_HANDITYPE_BLACK
            || $this->Handicaptype == TRULE_HANDITYPE_WHITE )
      { // manual-handicap-type
         $chk_komi = floor( abs( 2 * (float)($this->Komi + $this->AdjKomi) ) );
         if ( $chk_komi & 1 )
            return 1; // can be only x.5
         else
            return 0; // can be only x.0
      }

      // unknown rule-type
      error('invalid_args', "TournamentRules.determineJigoBehaviour({$this->JIGOMODE_NO_JIGO},{$this->Handicaptype},{$this->Komi},{$this->AdjKomi})");
   }//determineJigoBehaviour

   /*!
    * \brief Creates normal game(s) and updates all game-stuff for two given users.
    * \param $user_ch User-object of challenger with set urow['Rating2'] (according to rating-use-mode)
    * \param $user_df User-object of defender with set urow['Rating2'] (dito)
    * \return array of created Games.ID (can be multiple, e.g. for DOUBLE-game)
    *
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section if used with other db-writes!!
    * \note Expect filled var this->TourneyType
    */
   public function create_tournament_games( $user_ch, $user_df )
   {
      $ch_uid = $user_ch->ID;
      $df_uid = $user_df->ID;

      $game_setup = $this->convertTournamentRules_to_GameSetup();
      $game_row = $this->convertTournamentRules_to_GameRow();
      $game_row['tid'] = $this->tid;
      $is_double = ( $this->Handicaptype == TRULE_HANDITYPE_DOUBLE );

      $ch_is_black = $this->prepare_create_game_row( $game_row, $game_setup,
         $ch_uid, $user_ch->urow['Rating2'],
         $df_uid, $user_df->urow['Rating2'] );

      $gids = array();
      if ( $ch_is_black || $is_double )
         $gids[] = create_game($user_ch->urow, $user_df->urow, $game_row, $game_setup);
      else // challenger is white
         $gids[] = create_game($user_df->urow, $user_ch->urow, $game_row, $game_setup);
      $gid = $gids[0];

      if ( $is_double )
      {
         // provide a link between the two paired "double" games
         $game_row['double_gid'] = $gid;
         $double_gid2 = create_game($user_df->urow, $user_ch->urow, $game_row, $game_setup);
         $gids[] = $double_gid2;

         db_query( "TRules.create_tournament_games.upd_double2($gid)",
            "UPDATE Games SET DoubleGame_ID=$double_gid2 WHERE ID=$gid LIMIT 1" );
      }

      GameHelper::update_players_start_game( "TRules.create_tournament_games({$this->tid})",
         $ch_uid, $df_uid, count($gids), $this->Rated );

      return $gids;
   }//create_tournament_games

   /*!
    * \brief Prepares game_row and game_setup setting fields: game_row (Handicap/Komi), game_setup (uid).
    * \return true if challenger is black.
    */
   public function prepare_create_game_row( &$game_row, &$game_setup, $ch_uid, $ch_rating, $df_uid, $df_rating )
   {
      if ( !$this->TourneyType )
         error('invalid_args', "TournamentRules.prepare_create_game_row.miss_var.TourneyType($ch_uid,$df_uid)");

      $game_row['Handicaptype'] = self::convert_trule_handicaptype_to_stdhtype($this->Handicaptype);
      $gs_uid = $ch_uid; // default

      switch ( (string)$this->Handicaptype )
      {
         case TRULE_HANDITYPE_CONV:
            list( $game_row['Handicap'], $game_row['Komi'], $ch_is_black, $is_nigiri ) =
               suggest_conventional( $ch_rating, $df_rating, $this->Size );
            break;

         case TRULE_HANDITYPE_PROPER:
            list( $game_row['Handicap'], $game_row['Komi'], $ch_is_black, $is_nigiri ) =
               suggest_proper( $ch_rating, $df_rating, $this->Size );
            break;

         case TRULE_HANDITYPE_NIGIRI:
            $game_row['Handicap'] = 0;
            mt_srand((double) microtime() * 1000000);
            $ch_is_black = mt_rand(0,1);
            break;

         case TRULE_HANDITYPE_DOUBLE:
            $ch_is_black = true;
            break;

         case TRULE_HANDITYPE_BLACK:
            if ( $this->TourneyType == TOURNEY_TYPE_LADDER ) // challenger is black
               $ch_is_black = true;
            else //TOURNEY_TYPE_ROUND_ROBIN : stronger is black
               $ch_is_black = ( $ch_rating > $df_rating );
            $gs_uid = ($ch_is_black) ? $ch_uid : $df_uid;
            break;

         case TRULE_HANDITYPE_WHITE:
            if ( $this->TourneyType == TOURNEY_TYPE_LADDER ) // challenger is white
               $ch_is_black = false;
            else //TOURNEY_TYPE_ROUND_ROBIN : stronger is white
               $ch_is_black = ( $ch_rating < $df_rating );
            $gs_uid = ($ch_is_black) ? $df_uid : $ch_uid;
            break;

         default:
            error('not_implemented', "TournamentRules.prepare_create_game_row.unknown_htype"
               . "({$this->tid},$ch_uid,$df_uid,{$this->Handicaptype})");
            break;
      }

      if ( $game_setup )
         $game_setup->uid = $gs_uid;

      return $ch_is_black;
   }//prepare_create_game_row


   // ------------ static functions ----------------------------

   /*! \brief Deletes TournamentRules-entry for given id. */
   public static function delete_tournament_rules( $id )
   {
      $t_rules = new TournamentRules( $id );
      return $t_rules->delete( "TournamentRules:delete_tournament_rules(%s)" );
   }

   /*! \brief Returns db-fields to be used for query of TournamentRules-objects for given tournament-id. */
   public static function build_query_sql( $tid )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_RULES']->newQuerySQL('TR');
      $qsql->add_part( SQLP_WHERE, "TR.tid='$tid'" );
      return $qsql;
   }

   /*! \brief Returns TournamentRules-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $tp = new TournamentRules(
            // from TournamentRules
            @$row['ID'],
            @$row['tid'],
            @$row['X_Lastchanged'],
            @$row['ChangedBy'],
            @$row['Flags'],
            @$row['Notes'],
            @$row['Ruleset'],
            @$row['Size'],
            @$row['Handicaptype'],
            @$row['Handicap'],
            @$row['Komi'],
            @$row['AdjKomi'],
            @$row['JigoMode'],
            @$row['AdjHandicap'],
            @$row['MinHandicap'],
            @$row['MaxHandicap'],
            ( @$row['StdHandicap'] == 'Y' ),
            @$row['Maintime'],
            @$row['Byotype'],
            @$row['Byotime'],
            @$row['Byoperiods'],
            ( @$row['WeekendClock'] == 'Y' ),
            ( @$row['Rated'] == 'Y' ),
            @$row['ShapeID'],
            @$row['ShapeSnapshot']
         );
      return $tp;
   }

   /*! \brief Loads and returns TournamentRules-object for given tournament-ID. */
   public static function load_tournament_rule( $tid )
   {
      $result = NULL;
      if ( $tid > 0 )
      {
         $qsql = self::build_query_sql( $tid );
         $qsql->add_part( SQLP_LIMIT, '1' );
         $qsql->add_part( SQLP_ORDER, 'TR.ID DESC' );
         $row = mysql_single_fetch( "TournamentRules:load_tournament_rule($tid)",
            $qsql->get_select() );
         if ( $row )
            $result = self::new_from_row( $row );
      }
      return $result;
   }//load_tournament_rule

   /*! \brief Returns enhanced (passed) ListIterator with Tournament-objects for given tournament-id. */
   public static function load_tournament_rules( $iterator, $tid )
   {
      $qsql = self::build_query_sql( $tid );
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "TournamentRules:load_tournament_rules($tid)", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $tourney = self::new_from_row( $row );
         $iterator->addItem( $tourney, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_tournament_rules

   /*! \brief Returns flags-text for given int-bitmask or all flags-texts (if arg=null). */
   public static function getFlagsText( $flags=null )
   {
      // lazy-init of texts
      if ( !isset(self::$ARR_TRULES_TEXTS['FLAGS']) )
      {
         $arr = array();
         self::$ARR_TRULES_TEXTS['FLAGS'] = $arr;
      }
      else
         $arr = self::$ARR_TRULES_TEXTS['FLAGS'];
      if ( is_null($flags) )
         return $arr;

      $out = array();
      foreach ( $arr as $flagmask => $flagtext )
         if ( $flags & $flagmask ) $out[] = $flagtext;
      return implode(', ', $out);
   }//getFlagsText

   /*! \brief Returns type-text. */
   public static function getHandicaptypeText( $type, $tourney_type )
   {
      // lazy-init of texts
      $key = 'HANDICAPTYPE'.$tourney_type;
      if ( !isset(self::$ARR_TRULES_TEXTS[$key]) )
      {
         $arr = array();
         $arr[TRULE_HANDITYPE_CONV]   = T_('Conventional handicap');
         $arr[TRULE_HANDITYPE_PROPER] = T_('Proper handicap');
         $arr[TRULE_HANDITYPE_NIGIRI] = T_('Even game with nigiri');
         if ( $tourney_type == TOURNEY_TYPE_LADDER )
         {
            $arr[TRULE_HANDITYPE_BLACK] = T_('Manual game with Challenger getting Black#T_ladder');
            $arr[TRULE_HANDITYPE_WHITE] = T_('Manual game with Challenger getting White#T_ladder');
         }
         elseif ( $tourney_type == TOURNEY_TYPE_ROUND_ROBIN )
         {
            $arr[TRULE_HANDITYPE_BLACK] = T_('Manual game with stronger player getting Black#tourney');
            $arr[TRULE_HANDITYPE_WHITE] = T_('Manual game with stronger player getting White#tourney');
            $arr[TRULE_HANDITYPE_DOUBLE] = T_('Double game');
         }
         self::$ARR_TRULES_TEXTS[$key] = $arr;
      }

      if ( is_null($type) )
         return self::$ARR_TRULES_TEXTS[$key];
      if ( !isset(self::$ARR_TRULES_TEXTS[$key][$type]) )
         error('invalid_args', "TournamentRules:getHandicaptypeText($type)");
      return self::$ARR_TRULES_TEXTS[$key][$type];
   }//getHandicaptypeText

   public static function convert_trule_handicaptype_to_stdhtype( $trule_htype )
   {
      static $map_trule_htype_stdhtype = array(
         TRULE_HANDITYPE_CONV    => HTYPE_CONV,
         TRULE_HANDITYPE_PROPER  => HTYPE_PROPER,
         TRULE_HANDITYPE_NIGIRI  => HTYPE_NIGIRI,
         TRULE_HANDITYPE_BLACK   => HTYPE_BLACK,
         TRULE_HANDITYPE_WHITE   => HTYPE_WHITE,
         TRULE_HANDITYPE_DOUBLE  => HTYPE_DOUBLE,
      );
      return (isset($map_trule_htype_stdhtype[$trule_htype]))
         ? $map_trule_htype_stdhtype[$trule_htype]
         : HTYPE_NIGIRI; // default
   }//convert_trule_handicaptype_to_stdhtype

   public static function get_edit_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_NEW );
      return $statuslist;
   }

   public static function getJigoBehaviourText( $jigo_behaviour )
   {
      if ( $jigo_behaviour == 0 )
         return T_('Tournament-rules enforces Jigo, so game score must be an integer, not ending on .5');
      elseif ( $jigo_behaviour == 1 )
         return T_('Tournament-rules forbid Jigo, so game score must be a float ending on .5');
      else
         return '';
   }

   public static function delete_cache_tournament_rules( $dbgmsg, $tid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_TRULES, "TRules.$tid" );
   }

} // end of 'TournamentRules'
?>
