<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Game";

require_once 'include/globals.php';
require_once 'include/time_functions.php';


 /*!
  * \file classlib_game.php
  *
  * \brief Classes to support game-handling.
  */


define('GSMODE_TERRITORY_SCORING', 'TERRITORY_SCORING');
define('GSMODE_AREA_SCORING', 'AREA_SCORING');

define('GSCOL_BLACK', 0);
define('GSCOL_WHITE', 1);

 /*!
  * \class GameScore
  * \brief Container class to help in calculating game-score.
  */

// lazy-init in GameScore::get..Text()-funcs
global $ARR_GLOBALS_GAMESCORE; //PHP5
$ARR_GLOBALS_GAMESCORE = array();

class GameScore
{
   /*! \brief GSMODE_TERRITORY_SCORING or GSMODE_AREA_SCORING. */
   var $mode;
   /*! \brief Number of handicap stones. */
   var $handicap;
   /*! \brief used komi [float] */
   var $komi;
   /*! \brief Prisoners of BLACK and WHITE stored in array[GSCOL_BLACK|WHITE] */
   var $prisoners; // [ B, W ]
   /*! \brief Stone-count on board of BLACK and WHITE. */
   var $stones; // [ B, W ]
   /*! \brief "dead" stones on board of BLACK and WHITE. */
   var $dead_stones; // [ B, W ]
   /*! \brief Territory of BLACK and WHITE. */
   var $territory; // [ B, W ]
   /*! \brief dame-count */
   var $dame;

   /*! \brief calculated scoring-information (may be independently set from $this->mode). */
   var $scoring_info;
   /*! \brief calculated score (may be independently set from $this->mode). */
   var $score;


   /*!
    * \brief Constructs GameScore-object.
    * \param $mode GSMODE_TERRITORY_SCORING or GSMODE_AREA_SCORING
    */
   function GameScore( $mode, $handicap, $komi )
   {
      GameScore::check_mode( $mode, 'constructor' );
      $this->mode = $mode;
      $this->handicap = $handicap;
      $this->komi = $komi;
      $this->prisoners = array( 0, 0 );
      $this->stones = array( 0, 0 );
      $this->dead_stones = array( 0, 0 );
      $this->territory = array( 0, 0 );
      $this->dame = 0;
      $this->scoring_info = null;
      $this->score = null;
   }

   /*! \brief Returns prisoners for given color GSCOL_WHITE|BLACK. */
   function get_prisoners( $gscol )
   {
      GameScore::check_gscol( $gscol, 'get_prisoners' );
      return $this->prisoners[$gscol];
   }

   /*! \brief Sets prisoners for given color GSCOL_WHITE|BLACK. */
   function set_prisoners( $gscol, $count )
   {
      GameScore::check_gscol( $gscol, 'set_prisoners' );
      $this->prisoners[$gscol] = $count;
   }

   /*! \brief Sets prisoners for black and white color. */
   function set_prisoners_all( $black_count, $white_count )
   {
      $this->prisoners[GSCOL_BLACK] = $black_count;
      $this->prisoners[GSCOL_WHITE] = $white_count;
   }

   /*! \brief Returns number of stones on the board of given color GSCOL_WHITE|BLACK. */
   function get_stones( $gscol )
   {
      GameScore::check_gscol( $gscol, 'get_stones' );
      return $this->stones[$gscol];
   }

   /*! \brief Sets number of stones on the board of given color GSCOL_WHITE|BLACK. */
   function set_stones( $gscol, $count )
   {
      GameScore::check_gscol( $gscol, 'set_stones' );
      $this->stones[$gscol] = $count;
   }

   /*! \brief Sets number of stones on the board for black and white color. */
   function set_stones_all( $black_count, $white_count )
   {
      $this->stones[GSCOL_BLACK] = $black_count;
      $this->stones[GSCOL_WHITE] = $white_count;
   }

   /*! \brief Returns number of captured stones on the board of given color GSCOL_WHITE|BLACK. */
   function get_dead_stones( $gscol )
   {
      GameScore::check_gscol( $gscol, 'get_dead_stones' );
      return $this->dead_stones[$gscol];
   }

   /*! \brief Sets number of captured stones on the board of given color GSCOL_WHITE|BLACK. */
   function set_dead_stones( $gscol, $count )
   {
      GameScore::check_gscol( $gscol, 'set_dead_stones' );
      $this->dead_stones[$gscol] = $count;
   }

   /*! \brief Sets number of captured stones on the board for black and white color. */
   function set_dead_stones_all( $black_count, $white_count )
   {
      $this->dead_stones[GSCOL_BLACK] = $black_count;
      $this->dead_stones[GSCOL_WHITE] = $white_count;
   }

   /*! \brief Returns number of territory points on the board of given color GSCOL_WHITE|BLACK. */
   function get_territory( $gscol )
   {
      GameScore::check_gscol( $gscol, 'get_territory' );
      return $this->territory[$gscol];
   }

   /*! \brief Sets number of territory points on the board of given color GSCOL_WHITE|BLACK. */
   function set_territory( $gscol, $count )
   {
      GameScore::check_gscol( $gscol, 'set_territory' );
      $this->territory[$gscol] = $count;
   }

   /*! \brief Sets number of territory points on the board for black and white color. */
   function set_territory_all( $black_count, $white_count )
   {
      $this->territory[GSCOL_BLACK] = $black_count;
      $this->territory[GSCOL_WHITE] = $white_count;
   }

   /*! \brief Sets number of neutral (=dame) points. */
   function set_dame( $count )
   {
      $this->dame = $count;
   }

   /*!
    * \brief Returns map with more detailed information about scoring in textual form,
    * filled in calculate_score-func.
    */
   function get_scoring_info()
   {
      return $this->scoring_info;
   }


   /*!
    * \brief Calculating score for given mode (territory or area scoring).
    * \param $mode null (use mode from constructing this object) or GSMODE_TERRITORY_SCORING or GSMODE_AREA_SCORING
    * \param $fill_scoring_info fill scoring-info map if not-false, 'sgf' for sgf-text scoring-info
    * \return overall score (=score-white - score-black), also set in $this->score
    * NOTE: for format of scoring-info map see code and game.php#draw_score_box()
    */
   function calculate_score( $mode=null, $fill_scoring_info=true )
   {
      // check args
      if( is_null($mode) )
         $mode = $this->mode;
      else
         GameScore::check_mode( $mode, 'get_score' );

      // calculate score
      if( $mode == GSMODE_TERRITORY_SCORING )
      {
         $handi_diff = $this->handicap;
         $score_black = $this->territory[GSCOL_BLACK]
                        + $this->prisoners[GSCOL_BLACK]
                        + 2 * $this->dead_stones[GSCOL_WHITE];
         $score_white = $this->territory[GSCOL_WHITE]
                        + $this->prisoners[GSCOL_WHITE]
                        + 2 * $this->dead_stones[GSCOL_BLACK]
                        + $this->komi;
      }
      else //if( $mode == GSMODE_AREA_SCORING )
      {
         // "why H-1?": http://www.dragongoserver.net/forum/read.php?forum=4&thread=25182#25620
         //             http://senseis.xmp.net/?TerritoryAndAreaScoring#toc6 "Handicap Go"
         $handi_diff = ($this->handicap >= 2 ) ? $this->handicap - 1 : 0;
         $score_black = $this->stones[GSCOL_BLACK]
                        + $this->dead_stones[GSCOL_WHITE]
                        + $this->territory[GSCOL_BLACK]
                        - $handi_diff;

         $score_white = $this->stones[GSCOL_WHITE]
                        + $this->dead_stones[GSCOL_BLACK]
                        + $this->territory[GSCOL_WHITE]
                        + $this->komi;
      }
      $this->score = $score_white - $score_black;

      if( $fill_scoring_info )
      {
         $fill_sgf = ( $fill_scoring_info == 'sgf' );

         $map = array(
            'mode_text' => $this->getModeText($mode),
            'mode'  => $mode,
            'dame'  => sprintf( '(%s)', $this->dame ),
            'score' => $this->score,
         );
         $isArea = ( $mode == GSMODE_AREA_SCORING );

         $arr_sgf = array(); // keep texts in english
         if( $fill_sgf && $isArea )
            $arr_sgf['Dame'] = ($this->dame == 1) ? '1 stone' : "{$this->dame} stones";

         $fmt_dead = ($isArea) ? '' : '2*';
         foreach( array( GSCOL_BLACK, GSCOL_WHITE ) as $gscol )
         {
            $gscol_opp = ( $gscol == GSCOL_BLACK ) ? GSCOL_WHITE : GSCOL_BLACK;
            $arr = array(
               'stones'      => sprintf( ( $isArea ? '+%s' : '(%s)' ), $this->stones[$gscol] ),
               'dead_stones' => sprintf( "(%s)<br>+{$fmt_dead}%s", $this->dead_stones[$gscol], $this->dead_stones[$gscol_opp] ),
               'prisoners'   => sprintf( ( $isArea ? '(%s)' : '+%s' ), $this->prisoners[$gscol] ),
               'territory'   => sprintf( '+%s', $this->territory[$gscol] ),
            );
            $map[$gscol] = $arr;

            if( $fill_sgf )
            {
               $sgf_text = sprintf("%d territories + {$fmt_dead}%d dead %s(%s)",
                  $this->territory[$gscol],
                  $this->dead_stones[$gscol_opp], ($this->dead_stones[$gscol_opp] == 1 ? 'stone' : 'stones'),
                     ($gscol_opp == GSCOL_BLACK ? 'B' : 'W') );
               $sgf_komi = ( $gscol == GSCOL_WHITE )
                  ? sprintf( ' %s %s komi', ($this->komi < 0.0 ? '-' : '+'), abs($this->komi) )
                  : '';
               if( $isArea )
               {
                  $sgf_text .= sprintf(' + %d %s(%s)%s', $this->stones[$gscol],
                     ($this->stones[$gscol] == 1 ? 'stone' : 'stones'), ($gscol == GSCOL_BLACK ? 'B' : 'W'),
                     ( $gscol == GSCOL_WHITE
                        ? $sgf_komi
                        : sprintf( ' - %d handicap %s', $handi_diff, ($handi_diff == 1 ? 'stone' : 'stones') ) ) );
               }
               else
               {
                  $sgf_text .= sprintf(' + %d %s%s',
                     $this->prisoners[$gscol], ($this->prisoners[$gscol] == 1 ? 'prisoner' : 'prisoners'),
                     $sgf_komi );
               }
               $sgf_text .= sprintf(' = %s', ($gscol == GSCOL_BLACK ? $score_black : $score_white) );
               $arr_sgf[($gscol == GSCOL_WHITE ? 'White' : 'Black')] = $sgf_text;
            }//sgf
         }

         $map['skip_dame'] = $map['skip_stones'] = !$isArea;
         $map['skip_prisoners'] = $isArea;

         $map[GSCOL_BLACK]['extra'] = ( $isArea && $handi_diff > 0 )
            ? sprintf( '-%s %s', $handi_diff, T_('(H)#scoring') ) : '';
         if( $this->komi != 0.0 )
         {
            $fmt_komi = ($this->komi < 0.0) ? '%s %s' : '+%s %s';
            $map[GSCOL_WHITE]['extra'] = sprintf( $fmt_komi, $this->komi, T_('(K)#scoring') );
         }
         else
            $map[GSCOL_WHITE]['extra'] = '';
         $map[GSCOL_BLACK]['score'] = $score_black;
         $map[GSCOL_WHITE]['score'] = $score_white;

         $map['sgf_texts'] = $arr_sgf;

         $this->scoring_info = $map;
      }

      return $this->score;
   }//calculate_score

   /*! \brief Recalculates score if given mode different from mode of this object. */
   function recalculate_score( $mode, $fill_scoring_info=true )
   {
      if( strcmp($this->mode, $mode) != 0 )
         $this->calculate_score($mode, $fill_scoring_info);
      return $this->score;
   }

   /*! \brief Returns mode-text or all mode-texts (if arg=null). */
   function getModeText( $mode=null )
   {
      global $ARR_GLOBALS_GAMESCORE;

      // lazy-init of texts
      $key = 'MODE';
      if( !isset($ARR_GLOBALS_GAMESCORE[$key]) )
      {
         $arr = array();
         $arr[GSMODE_TERRITORY_SCORING] = T_('Territory scoring#scoring');
         $arr[GSMODE_AREA_SCORING]      = T_('Area scoring#scoring');
         $ARR_GLOBALS_GAMESCORE[$key] = $arr;
      }

      if( is_null($mode) )
         return $ARR_GLOBALS_GAMESCORE[$key];
      if( !isset($ARR_GLOBALS_GAMESCORE[$key][$mode]) )
         error('invalid_args', "GameScore.getModeText($mode)");
      return $ARR_GLOBALS_GAMESCORE[$key][$mode];
   }

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   function to_string()
   {
      return "GameScore:"
         . "  mode={$this->mode}"
         . ", handicap={$this->handicap}"
         . ", komi={$this->komi}"
         . ", stones=[ B=" . $this->stones[GSCOL_BLACK] . ", W=" . $this->stones[GSCOL_WHITE] . " ]"
         . ", dead_stones=[ B=" . $this->dead_stones[GSCOL_BLACK] . ", W=" . $this->dead_stones[GSCOL_WHITE] . " ]"
         . ", territory=[ B=" . $this->territory[GSCOL_BLACK] . ", W=" . $this->territory[GSCOL_WHITE] . " ]"
         . ", prisoners=[ B=" . $this->prisoners[GSCOL_BLACK] . ", W=" . $this->prisoners[GSCOL_WHITE] . " ]"
         . ", dame={$this->dame}"
         . ", scores=[ B=" . $this->scores[GSCOL_BLACK] . ", W=" . $this->scores[GSCOL_WHITE] . " ]"
         . ", score.territory=" . $this->calculate_score(GSMODE_TERRITORY_SCORING, false)
         . ", score.area=" . $this->calculate_score(GSMODE_AREA_SCORING, false)
         ;
   }


   // ---------- internal static funcs -------------

   function check_mode( $mode, $method )
   {
      if( $mode != GSMODE_TERRITORY_SCORING && $mode != GSMODE_AREA_SCORING )
         error('invalid_args', "GameScore::$method($mode)");
   }

   function check_gscol( $gscol, $method )
   {
      if( $gscol != GSCOL_BLACK && $gscol != GSCOL_WHITE )
         error('invalid_args', "GameScore::$method($gscol)");
   }


   // ---------- static funcs ----------------------

   /*! \brief [GUI] Draws table of given GameScore and scoring-mode using echo(). */
   function draw_score_box( $game_score, $scoring_mode )
   {
      if( !is_a( $game_score, 'GameScore' ) )
         return;

      $game_score->recalculate_score($scoring_mode); // recalc if needed
      $score_info = $game_score->get_scoring_info();

      $fmtline3 = "<tr><td class=\"%s\">%s</td><td>%s</td><td>%s</td></tr>\n";
      $fmtline2 = "<tr><td class=\"%s\">%s</td><td colspan=\"2\">%s</td></tr>\n";

      global $base_path;
      $caption = T_('Scoring information#scoring');
      $caption2 = $score_info['mode_text'];
      echo "<table id=\"scoreInfo\" class=\"Scoring\">\n",
         "<tr><th colspan=\"3\">$caption<br>($caption2)</th></tr>\n";
      if( !$score_info['skip_dame'] )
         echo sprintf( $fmtline2, 'Header', T_('Dame#scoring'), $score_info['dame'] );
      echo sprintf( "<tr class=\"Header\"><td></td><td>%s</td><td>%s</td></tr>\n",
                  image( "{$base_path}17/b.gif", T_('Black'), null ),
                  image( "{$base_path}17/w.gif", T_('White'), null ) ),
         sprintf( $fmtline3, 'Header', T_('Territory#scoring'),
                  $score_info[GSCOL_BLACK]['territory'],
                  $score_info[GSCOL_WHITE]['territory'] ),
         sprintf( $fmtline3, 'Header', T_('Dead stones#scoring'),
                  $score_info[GSCOL_BLACK]['dead_stones'],
                  $score_info[GSCOL_WHITE]['dead_stones'] );
      if( !$score_info['skip_stones'] )
         echo sprintf( $fmtline3, 'Header', T_('Stones#scoring'),
                  $score_info[GSCOL_BLACK]['stones'],
                  $score_info[GSCOL_WHITE]['stones'] );
      if( !$score_info['skip_prisoners'] )
         echo sprintf( $fmtline3, 'Header', T_('Prisoners#scoring'),
                  $score_info[GSCOL_BLACK]['prisoners'],
                  $score_info[GSCOL_WHITE]['prisoners'] );
      echo sprintf( $fmtline3, 'Header', T_('Extra#scoring'),
                  $score_info[GSCOL_BLACK]['extra'],
                  $score_info[GSCOL_WHITE]['extra'] ),
         sprintf( $fmtline3, 'HeaderSum', T_('Score#scoring'),
                  $score_info[GSCOL_BLACK]['score'],
                  $score_info[GSCOL_WHITE]['score'] ),
         sprintf( $fmtline2, 'HeaderSum', T_('Difference#scoring'), $score_info['score'] ),
         "</table>\n";
   } //draw_score_box

} //end 'GameScore'




 /*!
  * \class NextGameOrder
  * \brief Static helper class to handle table next-game-order for status-games
  *        and GamesPriority-table.
  */

define('NGO_LASTMOVED', 'LASTMOVED');
define('NGO_MOVES', 'MOVES');
define('NGO_PRIO', 'PRIO');
define('NGO_TIMELEFT', 'TIMELEFT');

class NextGameOrder
{
   // ---------- static funcs ----------------------

   /*! \brief Returns array with orders for status-games-list order-selection. */
   function get_next_game_orders_selection()
   {
      return array(
         1 => T_('Last moved#nextgame'),
         2 => T_('Moves#nextgame'),
         3 => T_('Priority#nextgame'),
         4 => T_('Time remaining#nextgame'),
      );
   }

   /*!
    * \brief Maps NextGameOrder for status-games.
    * \param $idx if numeric (map selection-index to enum-value (sql_order=false) or to order-string;
    *             if string (map enum-value to selection-index ot to order-string)
    * \param $tablename return SQL 'ORDER BY...' if tablename given; return enum for numeric-index if empty
    * \return '' for unknown $idx; otherwise return enum-value or SQL-order-by-clause dependent on $tablename
    *
    * Examples:
    *   get_next_game_order( 3 ) -> PRIO
    *   get_next_game_order( 'MOVES' ) -> 2
    *   get_next_game_order( 'MOVES', 'G', true ) = get_..( 2, 'G', true ) -> 'ORDER BY G.Moves, ...'
    *   get_next_game_order( 'MOVES', 'Games' ) = 'G.Moves, ...'
    */
   function get_next_game_order( $idx, $tablename='', $with_order_by=true )
   {
      // SQL-ordering for Status-game list and "next game" on game-page (%G = Games-table)
      // NOTE: also adjust 'jump_to_next_game(..)' in confirm.php
      static $ARR_NEXT_GAME_ORDER = array(
         // idx => [ Players.NextGameOrder-value, order-string ]
         1 => array( NGO_LASTMOVED, '%G.Lastchanged ASC, %G.ID DESC' ),
         2 => array( NGO_MOVES,     '%G.Moves DESC, %G.Lastchanged ASC, %G.ID DESC' ),
         3 => array( NGO_PRIO,      'X_Priority DESC, %G.Lastchanged ASC, %G.ID DESC' ),
         4 => array( NGO_TIMELEFT,  '%G.TimeOutDate ASC, %G.Lastchanged ASC, %G.ID DESC' ),
         NGO_LASTMOVED => 1,
         NGO_MOVES     => 2,
         NGO_PRIO      => 3,
         NGO_TIMELEFT  => 4,
      );

      if( !isset($ARR_NEXT_GAME_ORDER[$idx]) )
         return '';

      $idx_value = $idx;
      if( !is_numeric($idx) ) // map enum-val to selection-index or order-string
      {
         $idx_value = $ARR_NEXT_GAME_ORDER[$idx];
         if( !$tablename )
            return $idx_value;
      }

      if( $tablename )
      {
         $order_fmt = $ARR_NEXT_GAME_ORDER[$idx_value][1];
         return ( $with_order_by ? 'ORDER BY ' : '' ) . str_replace( '%G', $tablename, $order_fmt );
      }
      else
         return $ARR_NEXT_GAME_ORDER[$idx_value][0];
   }//get_next_game_order

   /*! \brief Returns loaded Players.NextGameOrder for given user; or null otherwise. */
   function load_user_next_game_order( $uid )
   {
      $uid = (int) $uid;
      $user_row = mysql_single_col( "NextGameOrder::load_user_next_game_order.find($uid)",
         "SELECT NextGameOrder FROM Players WHERE ID=$uid LIMIT 1" );
      return ( $user_row ) ? $user_row[0] : null;
   }

   /*!
    * \brief Loads priority from GamesPriority-table for given game and user.
    * \return loaded integer-prio or else $defval
    */
   function load_game_priority( $gid, $uid, $defval=0 )
   {
      $prio_row = mysql_single_col( "NextGameOrder::load_game_priority.find($gid,$uid)",
         "SELECT Priority FROM GamesPriority WHERE gid=$gid AND uid=$uid LIMIT 1" );
      $prio = ( $prio_row ) ? $prio_row[0] : $defval;
      return $prio;
   }

   /*!
    * \brief Saves or deletes GamesPriority-table-entry for given arguments.
    * \param $prio '' to delete entry; integer-value to save it;
    *              non-integer will lead to an error
    */
   function persist_game_priority( $gid, $uid, $prio )
   {
      if( (string)$prio == '' )
      {
         db_query( "NextGameOrder::persist_game_priority.delete($gid,$uid)",
            "DELETE FROM GamesPriority WHERE gid=$gid AND uid=$uid LIMIT 1" );
      }
      else
      {
         if( !is_numeric($prio) )
            error('invalid_args', "NextGameOrder::persist_game_priority.check.prio.no_int($gid,$uid,$new_prio)");
         $new_prio = (int)$prio;
         if( $new_prio < -32768 || $new_prio > 32767 )
            error('invalid_args', "NextGameOrder::persist_game_priority.check.prio.range($gid,$uid,$new_prio)");

         db_query( "NextGameOrder::persist_game_priority.update($gid,$uid,$new_prio)",
            "INSERT INTO GamesPriority (gid,uid,Priority) VALUES ($gid,$uid,$new_prio) " .
            "ON DUPLICATE KEY UPDATE Priority=VALUES(Priority)" );
      }
   }//persist_game_priority

   /*! \brief Deletes all GamesPriority-table-entries for given game-id. */
   function delete_game_priorities( $gid )
   {
      db_query( "NextGameOrder::delete_game_priorities.delete_all($gid)",
         "DELETE FROM GamesPriority WHERE gid=$gid" ); // no 'LIMIT' because of multi-player-go
   }

   /*!
    * \brief Calculates timeout-date (in ticks) to be stored in Games.TimeOutDate.
    * \note used for status-games-ordering on remaining-time
    * \note ignores vacation and weekends
    *
    * \param $grow Games-table-row to read time-settings for game, expected fields: Maintime, Byotype,
    *              Byotime, Byoperiods, (White|Black)_(Maintime|Byotime|Byoperiods), X_(White|Black)Clock
    * \param $to_move BLACK | WHITE
    * \param $is_new_game if true get time-settings from $grow without prefix
    * \return "absolute" date (in ticks) aligned to Clock[ID=CLOCK_TIMELEFT]
    */
   function make_timeout_date( $grow, $to_move, $lastticks, $is_new_game=false )
   {
      // determine time-stuff for time-left-calculus
      $pfx = ($to_move == BLACK) ? 'Black' : 'White';
      if( $is_new_game )
      {
         $tl_Maintime   = $grow['Maintime'];
         $tl_Byotime    = $grow['Byotime'];
         $tl_Byoperiods = $grow['Byoperiods'];
      }
      else
      {
         $tl_Maintime   = $grow["{$pfx}_Maintime"];
         $tl_Byotime    = $grow["{$pfx}_Byotime"];
         $tl_Byoperiods = $grow["{$pfx}_Byoperiods"];
      }
      $tl_clockused = $grow["X_{$pfx}Clock"]; // ignore vacation and weekends

      $tl_clockused_ticks = get_clock_ticks( $tl_clockused, /*refresh-cache*/false );
      $elapsed_hours = ticks_to_hours( $tl_clockused_ticks - $lastticks);
      time_remaining($elapsed_hours, $tl_Maintime, $tl_Byotime, $tl_Byoperiods,
         $grow['Maintime'], $grow['Byotype'], $grow['Byotime'], $grow['Byoperiods'], false);

      $hours_left = time_remaining_value( $grow['Byotype'], $grow['Byotime'], $grow['Byoperiods'],
            $tl_Maintime, $tl_Byotime, $tl_Byoperiods );
      $timeout_date = time_left_ticksdate( $hours_left );

      return $timeout_date;
   }//make_timeout_date

} //end 'NextGameOrder'




 /*!
  * \class GameSnapshot
  * \brief Helper-class to build and parse Games.Snapshot for thumbnails and Shape-games.
  */
class GameSnapshot
{
   static $BASE64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";

   /*!
    * \brief Returns base64-encoded snapshot of game-positions with B/W- and dead-stones (without color).
    * \param $stone_reader interface with method $stone_reader->read_stone_value(x,y,$with_dead) with x/y=0..size-1
    *        returning 1 for black-stone, 2 for white-stone and 3 for dead-stone (black|white), 0 for empty-intersection
    * \param $with_dead true to include dead-stones, false to omit them (used for shape-game)
    * \return game-snapshot
    * \note one char in output = 3 positions of 2-bit-values (00=empty, 01=B, 10=W, 11=dead)
    */
   function make_game_snapshot( $size, $stone_reader, $with_dead=true )
   {
      $out = '';
      $enc_val = $enc_cnt = 0;
      for( $y = 0; $y < $size; $y++ )
      {
         for( $x = 0; $x < $size; $x++ )
         {
            $stone_val = $stone_reader->read_stone_value( $x, $y, $with_dead );
            $enc_val = ($enc_val << 2) + $stone_val;
            if( ++$enc_cnt == 3 )
            {
               $out .= self::$BASE64[$enc_val];
               $enc_cnt = $enc_val = 0;
            }
         }
      }

      if( $enc_cnt > 0 )
      {
         $enc_val <<= (2 * (3 - $enc_cnt));
         $out .= self::$BASE64[$enc_val];
      }

      $out = rtrim($out, 'A');
      if( (string)$out != '' )
      {
         $out = preg_replace( array(
               "/AAAAAAAAAAAAAAAA/", // *=16xA
               "/AAAAAAAA/", // @=8xA
               "/AAAA/", // #=4xA
               "/AAA/", // %=3xA
               "/AA/", // :=2xA
            ),
            array(
               "*", "@", "#",  "%", ":",
            ), $out );
      }
      else
         $out = 'A';

      return $out;
   }//make_game_snapshot

   /*! \brief Returns array with black/white stones and coordinates: [ $black/$white, x,y ] x/y=0..n */
   function parse_stones_snapshot( $size, $snapshot, $black, $white )
   {
      static $SKIPPOS_MAP = array(
            'A' =>  1, // 1xA
            ':' =>  2, // 2xA
            '%' =>  3, // 3xA
            '#' =>  4, // 4xA
            '@' =>  8, // 8xA
            '*' => 16, // 16xA
         );

      // 00=empty, 01=Black, 10=White, 11=Dead-stone
      $out = array();
      $psize = $size * $size;
      for( $i=0, $p=0; $p < $psize && $i < strlen($snapshot); $i++ )
      {
         $ch = $snapshot[$i];
         if( $ch == ' ' ) // stop on space (extended syntax)
            break;
         $skip_pos = @$SKIPPOS_MAP[$ch];
         if( $skip_pos )
            $p += 3 * $skip_pos; // skip empties
         else
         {
            $data = strpos(self::$BASE64, $ch);
            if( $data === false )
               error('invalid_snapshot', "GameSnapshot.parse_stones_snapshot($size,$ch,$p,$snapshot)");
            foreach( array( ($data >> 4) & 0x3, ($data >> 2) & 0x3, $data & 0x3 ) as $val )
            {
               if( $val == 1 || $val == 2 ) // 1=Black, 2=White, 3=Dead B|W
                  $out[] = array( ($val == 1 ? $black : $white), $p % $size, (int)($p / $size) );
               $p++;
            }
         }
      }

      return $out;
   }//parse_stones_snapshot

} //end 'GameSnapshot'

?>
