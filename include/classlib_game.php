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
   var $handicap;
   var $komi; // float
   /*! \brief Prisoners of BLACK and WHITE. */
   var $prisoners; // [ B, W ]
   /*! \brief Stone-count on board of BLACK and WHITE. */
   var $stones; // [ B, W ]
   /*! \brief "dead" stones on board of BLACK and WHITE. */
   var $dead_stones; // [ B, W ]
   /*! \brief Territory of BLACK and WHITE. */
   var $territory; // [ B, W ]
   /*! \brief dame-count */
   var $dame;

   /*! \brief calculated scoring-information. */
   var $scoring_info;

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
   }

   function get_prisoners( $gscol )
   {
      GameScore::check_gscol( $gscol, 'get_prisoners' );
      return $this->prisoners[$gscol];
   }

   function set_prisoners( $gscol, $count )
   {
      GameScore::check_gscol( $gscol, 'set_prisoners' );
      $this->prisoners[$gscol] = $count;
   }

   function set_prisoners_all( $black_count, $white_count )
   {
      $this->prisoners[GSCOL_BLACK] = $black_count;
      $this->prisoners[GSCOL_WHITE] = $white_count;
   }

   function get_stones( $gscol )
   {
      GameScore::check_gscol( $gscol, 'get_stones' );
      return $this->stones[$gscol];
   }

   function set_stones( $gscol, $count )
   {
      GameScore::check_gscol( $gscol, 'set_stones' );
      $this->stones[$gscol] = $count;
   }

   function set_stones_all( $black_count, $white_count )
   {
      $this->stones[GSCOL_BLACK] = $black_count;
      $this->stones[GSCOL_WHITE] = $white_count;
   }

   function get_dead_stones( $gscol )
   {
      GameScore::check_gscol( $gscol, 'get_dead_stones' );
      return $this->dead_stones[$gscol];
   }

   function set_dead_stones( $gscol, $count )
   {
      GameScore::check_gscol( $gscol, 'set_dead_stones' );
      $this->dead_stones[$gscol] = $count;
   }

   function set_dead_stones_all( $black_count, $white_count )
   {
      $this->dead_stones[GSCOL_BLACK] = $black_count;
      $this->dead_stones[GSCOL_WHITE] = $white_count;
   }

   function get_territory( $gscol )
   {
      GameScore::check_gscol( $gscol, 'get_territory' );
      return $this->territory[$gscol];
   }

   function set_territory( $gscol, $count )
   {
      GameScore::check_gscol( $gscol, 'set_territory' );
      $this->territory[$gscol] = $count;
   }

   function set_territory_all( $black_count, $white_count )
   {
      $this->territory[GSCOL_BLACK] = $black_count;
      $this->territory[GSCOL_WHITE] = $white_count;
   }

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
         $score_black = $this->territory[GSCOL_BLACK]
                        + $this->prisoners[GSCOL_BLACK]
                        + 2 * $this->dead_stones[GSCOL_WHITE];

         $score_white = $this->territory[GSCOL_WHITE]
                        + $this->prisoners[GSCOL_WHITE]
                        + 2 * $this->dead_stones[GSCOL_BLACK]
                        + $this->komi;

         $score = $score_white - $score_black;
      }
      else //if( $mode == GSMODE_AREA_SCORING )
      {
         $score_black = $this->stones[GSCOL_BLACK]
                        + $this->dead_stones[GSCOL_WHITE]
                        + $this->territory[GSCOL_BLACK]
                        - $this->handicap;

         $score_white = $this->stones[GSCOL_WHITE]
                        + $this->dead_stones[GSCOL_BLACK]
                        + $this->territory[GSCOL_WHITE]
                        + $this->komi;

         $score = $score_white - $score_black;
      }

      if( $fill_scoring_info )
      {
         $map = array(
            'mode_text' => $this->getModeText($mode),
            'mode'  => $mode,
            'dame'  => sprintf( '(%s)', $this->dame ),
            'score' => $score,
         );
         $isArea = ( $mode == GSMODE_AREA_SCORING );

         foreach( array( GSCOL_BLACK, GSCOL_WHITE ) as $gscol )
         {
            $gscol_rev = ( $gscol == GSCOL_BLACK ) ? GSCOL_WHITE : GSCOL_BLACK;
            $arr = array(
               'stones'      => sprintf( ( $isArea ? '+%s' : '(%s)' ), $this->stones[$gscol] ),
               'dead_stones' => sprintf( ( $isArea ? '+%s' : '+2*%s' ), $this->dead_stones[$gscol_rev] ),
               'prisoners'   => sprintf( ( $isArea ? '(%s)' : '+%s' ), $this->prisoners[$gscol] ),
               'territory'   => sprintf( '+%s', $this->territory[$gscol] ),
            );
            $map[$gscol] = $arr;
         }

         $map['skip_dame'] = $map['skip_stones'] = !$isArea;
         $map['skip_prisoners'] = $isArea;

         $map[GSCOL_BLACK]['extra'] = ( $isArea ) ? sprintf( '-%s %s', $this->handicap, T_('(H)#scoring') ) : '';
         $map[GSCOL_WHITE]['extra'] = sprintf( '+%s %s', $this->komi, T_('(K)#scoring') );
         $map[GSCOL_BLACK]['score'] = $score_black;
         $map[GSCOL_WHITE]['score'] = $score_white;

         $this->scoring_info = $map;
      }

      return $score;
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


} //end 'GameScore'

?>
