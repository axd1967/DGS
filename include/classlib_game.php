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
    * \param $fill_scoring_info fill scoring-info map if true
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
                        - 2 * $this->dead_stones[GSCOL_BLACK];
         $score_white = $this->territory[GSCOL_WHITE]
                        + $this->prisoners[GSCOL_WHITE]
                        - 2 * $this->dead_stones[GSCOL_WHITE]
                        + $this->komi;
      }
      else //if( $mode == GSMODE_AREA_SCORING )
      {
         // "why H-1?": http://www.dragongoserver.net/forum/read.php?forum=4&thread=25182#25620
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
         $map = array(
            'mode_text' => $this->getModeText($mode),
            'mode'  => $mode,
            'dame'  => sprintf( '(%s)', $this->dame ),
            'score' => $this->score,
         );
         $isArea = ( $mode == GSMODE_AREA_SCORING );

         foreach( array( GSCOL_BLACK, GSCOL_WHITE ) as $gscol )
         {
            $gscol_rev = ( $gscol == GSCOL_BLACK ) ? GSCOL_WHITE : GSCOL_BLACK;
            $arr = array(
               'stones'      => sprintf( ( $isArea ? '+%s' : '(%s)' ), $this->stones[$gscol] ),
               'dead_stones' => ( $isArea )
                  ? sprintf( '(%s)<br>+%s', $this->dead_stones[$gscol], $this->dead_stones[$gscol_rev] )
                  : sprintf( '-2*%s', $this->dead_stones[$gscol] ),
               'prisoners'   => sprintf( ( $isArea ? '(%s)' : '+%s' ), $this->prisoners[$gscol] ),
               'territory'   => sprintf( '+%s', $this->territory[$gscol] ),
            );
            $map[$gscol] = $arr;
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

         $this->scoring_info = $map;
      }

      return $this->score;
   }

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
         error('invalid_args', "GameScore.$method($mode)");
   }

   function check_gscol( $gscol, $method )
   {
      if( $gscol != GSCOL_BLACK && $gscol != GSCOL_WHITE )
         error('invalid_args', "GameScore.$method($gscol)");
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

// SQL-ordering for Status-game list and "next game" on game-page (%G = Games-table)
// NOTE: also adjust 'jump_to_next_game(..)' in confirm.php
$ARR_NEXT_GAME_ORDER = array(
   // idx => [ Players.NextGameOrder-value, order-string ]
   1 => array( 'LASTMOVED', '%G.Lastchanged ASC, %G.ID DESC' ),
   2 => array( 'MOVES',     '%G.Moves DESC, %G.Lastchanged ASC, %G.ID DESC' ),
   3 => array( 'PRIO',      'X_Priority DESC, %G.Lastchanged ASC, %G.ID DESC' ),
   'LASTMOVED' => 1,
   'MOVES'     => 2,
   'PRIO'      => 3,
);

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
      );
   }

   /*!
    * \brief Maps NextGameOrder for status-games.
    * \param $idx if numeric (map selection-index to enum-value (sql_order=false) or to order-string;
    *             if string (map enum-value to selection-index ot to order-string)
    * Examples:
    *   get_next_game_order( 'Games', 3, false ) -> PRIO
    *   get_next_game_order( '',  'MOVES', false ) -> 2
    *   get_next_game_order( 'G', 'MOVES', true ) = get_..( 'G', 2, true ) -> sql-order
    */
   function get_next_game_order( $tablename, $idx, $sql_order=false )
   {
      global $ARR_NEXT_GAME_ORDER;

      $idx_value = $idx;
      if( !is_numeric($idx) ) // map enum-val to selection-index or order-string
      {
         $idx_value = $ARR_NEXT_GAME_ORDER[$idx];
         if( !$sql_order )
            return $idx_value;
      }

      $arr_idx = ($sql_order) ? 1 : 0;
      $order_fmt = $ARR_NEXT_GAME_ORDER[$idx_value][$arr_idx];
      return str_replace( '%G', $tablename, $order_fmt );
   }


   /*!
    * \brief Loads priority from GamesPriority-table for given game and user.
    * \return loaded integer-prio or else $defval
    */
   function load_game_priority( $gid, $uid, $defval=0 )
   {
      $prio_row = mysql_single_col( "nextgameorder.gameprio.find($gid,$uid)",
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
         db_query( "nextgameorder.gameprio.delete($gid,$uid)",
            "DELETE FROM GamesPriority WHERE gid=$gid AND uid=$uid LIMIT 1" );
      }
      else
      {
         if( !is_numeric($prio) )
            error('invalid_args', "nextgameorder.gameprio.check.prio.no_int($gid,$uid,$new_prio)");
         $new_prio = (int)$prio;
         if( $new_prio < -32768 || $new_prio > 32767 )
            error('invalid_args', "nextgameorder.gameprio.check.prio.range($gid,$uid,$new_prio)");

         db_query( "nextgameorder.gameprio.update($gid,$uid,$new_prio)",
            "INSERT INTO GamesPriority (gid,uid,Priority) VALUES ($gid,$uid,$new_prio) " .
            "ON DUPLICATE KEY UPDATE Priority=VALUES(Priority)" );
      }
   }

   /*! \brief Deletes all GamesPriority-table-entries for given game-id. */
   function delete_game_priorities( $gid )
   {
      // for now only 2 players, change with RENGO
      db_query( "nextgameorder.gameprio.delete_all($gid)",
         "DELETE FROM GamesPriority WHERE gid=$gid LIMIT 2" );
   }

} //end 'NextGameOrder'

?>
