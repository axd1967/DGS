<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Game";

require_once 'include/globals.php';
require_once 'include/error_functions.php';
require_once 'include/game_functions.php';
require_once 'include/utilities.php';

/*!
 * \file deprecated_functions.php
 *
 * \brief Functions that should not be used any, but are still needed for some migration-scripts.
 */


// handicap-types for invitations, stored in Games.ToMove_ID, must be <0
define('INVITE_HANDI_CONV',   -1);
define('INVITE_HANDI_PROPER', -2);
define('INVITE_HANDI_NIGIRI', -3);
define('INVITE_HANDI_DOUBLE', -4);
define('INVITE_HANDI_FIXCOL', -5);
define('INVITE_HANDI_AUCTION_SECRET', -6);
define('INVITE_HANDI_AUCTION_OPEN', -7);
define('INVITE_HANDI_DIV_CHOOSE', -8);

define('GS_SEP_INVITATION', ' '); // separator for invitational game-setup


/*!
 * \class DeprecatedGameSetup
 * \brief collection of deprecated functions for game-setup
 * \see class GameSetup
 */
class DeprecatedGameSetup
{

   /*! \brief Encodes invitation GameSetup-objects into Games.GameSetup for invitations and disputes. */
   public static function build_invitation_game_setup( $game_setup1, $game_setup2 )
   {
      $out = array();
      if ( !is_null($game_setup1) )
         $out[] = $game_setup1->encode_game_setup( GSENC_FULL_GAME );
      if ( !is_null($game_setup2) )
         $out[] = $game_setup2->encode_game_setup( GSENC_FULL_GAME );
      return implode(GS_SEP_INVITATION, $out );
   }//build_invitation_game_setup

   /*!
    * \brief Decodes Games.GameSetup of invitation-type with additional data to make diffs on disputes.
    * \param $pivot_uid -1 = no match on uid in game-setup but just return found parts;
    *        otherwise uid for 1st returned game-setup
    * \param $gid only for dbg-info, can be Games.ID
    * \return non-null array [ GameSetup-obj for pivot-uid, GameSetup-obj for opponent ],
    *       elemements may be null (if non-matching game-setup found)
    *
    * \deprecated since 1.0.16 game-setup is stored in individual GameInvitation-table-entries,
    *       it's no longer stored in Games.GameSetup for both players.
    */
   public static function parse_invitation_game_setup( $pivot_uid, $game_setup, $gid=0 )
   {
      $arr_input = explode(GS_SEP_INVITATION, trim($game_setup)); // note: arr( Str ) if gs==empty
      $cnt_input = count($arr_input);
      if ( $cnt_input > 2 )
         error('invalid_args', "DeprecatedGameSetup:parse_invitation_game_setup.check.gs($pivot_uid,$cnt_input,$game_setup)");

      $result = array();
      foreach ( $arr_input as $gs_part )
      {
         if ( (string)$gs_part != '' )
            $result[] = GameSetup::new_from_game_setup( $gs_part, /*inv*/true );
      }

      $cnt_gs = count($result);
      if ( $cnt_gs == 0 )
         array_push( $result, null, null );
      elseif ( $cnt_gs == 1 )
      {
         if ( $pivot_uid < 0 || $result[0]->uid == $pivot_uid )
            $result[] = null;
         else
            array_unshift( $result, null );
      }
      elseif ( $cnt_gs == 2 && $pivot_uid >= 0 )
      {
         if ( $result[0]->uid != $pivot_uid && $result[1]->uid != $pivot_uid )
            error('invite_bad_gamesetup', "DeprecatedGameSetup:parse_invitation_game_setup.check.uid($pivot_uid,$gid)");
         if ( $result[1]->uid == $pivot_uid && $result[0]->uid != $pivot_uid )
            swap( $result[0], $result[1] );
      }

      return $result;
   }//parse_invitation_game_setup

   public static function read_game_setup_from_gamerow( $uid, $grow, $handicap_type )
   {
      $gs = new GameSetup($uid);
      $gs->read_waitingroom_fields( $grow );
      $gs->Handicaptype = $handicap_type;
      $gs->Handicap = (int)$grow['Handicap'];
      $gs->Komi = (float)$grow['Komi'];
      return $gs;
   }//read_game_setup_from_gamerow

   /*!
    * \brief Returns handicap-type for game-invitations.
    * \return non-null handicap-type (or else throw error what's missing/wrong)
    */
   public static function determine_handicaptype( $my_gs, $opp_gs, $tomove_id, $my_col_black )
   {
      if ( !is_null($opp_gs) ) // opponents swapped htype choice has precedence
         $my_htype = $opp_gs->get_htype_swapped();
      elseif ( !is_null($my_gs) ) // if opp-game-setup not set -> use my own choice
         $my_htype = $my_gs->Handicaptype;
      else // otherwise determine htype from Games.ToMove_ID (could also be old non-migrated game-invitation)
         $my_htype = null;

      $htype = self::get_handicaptype_for_invite( $tomove_id, $my_col_black, $my_htype );
      return $htype;
   }//determine_handicaptype

   // use is_black_col = fk_htype = null to get standard htype (e.g. for transition of Games without GameSetup)
   // NOTE: if $inv_handitype >0 for old game-invitations, $fk_htype can be null
   public static function get_handicaptype_for_invite( $inv_handitype, $is_black_col, $fk_htype )
   {
      // invite-handicap-type -> handicap-type
      static $ARR_HTYPES = array(
            INVITE_HANDI_CONV    => HTYPE_CONV,
            INVITE_HANDI_PROPER  => HTYPE_PROPER,
            INVITE_HANDI_NIGIRI  => HTYPE_NIGIRI,
            INVITE_HANDI_DOUBLE  => HTYPE_DOUBLE,
            //INVITE_HANDI_FIXCOL : calculated
            INVITE_HANDI_AUCTION_SECRET => HTYPE_AUCTION_SECRET,
            INVITE_HANDI_AUCTION_OPEN   => HTYPE_AUCTION_OPEN,
            //INVITE_HANDI_DIV_CHOOSE : calculated
         );
      static $ARR_HTYPES_CALC = array(
            INVITE_HANDI_FIXCOL => array( HTYPE_BLACK, HTYPE_WHITE ),
            INVITE_HANDI_DIV_CHOOSE => 1, // calculated (from GameSetup)
         );

      // handle OLD game-invitations with ToMove_ID > 0 (fix-color), see ToMove_ID in 'specs/db/table-Games.txt'
      if ( $inv_handitype > 0 )
      {
         if ( is_null($is_black_col) )
            error('invalid_args', "DeprecatedGameSetup:get_handicaptype_for_invite.check.is_black_col_null($inv_handitype,$fk_htype)");
         return ( $is_black_col ) ? HTYPE_BLACK : HTYPE_WHITE;
      }

      $arr_calc = @$ARR_HTYPES_CALC[$inv_handitype];
      if ( $arr_calc )
      {
         if ( is_null($is_black_col) )
            error('invalid_args', "DeprecatedGameSetup:get_handicaptype_for_invite.check.is_black_col_null($inv_handitype,$fk_htype)");
         if ( $inv_handitype == INVITE_HANDI_DIV_CHOOSE )
         {
            if ( !$fk_htype )
               error('invalid_args', "DeprecatedGameSetup:get_handicaptype_for_invite.check.fk_htype($inv_handitype,$is_black_col)");
            return $fk_htype;
         }
         else
            return ($is_black_col) ? $arr_calc[0] : $arr_calc[1];
      }

      $htype = @$ARR_HTYPES[$inv_handitype];
      if ( !$htype )
         error('invalid_args', "DeprecatedGameSetup:get_handicaptype_for_invite.check.bad_htype($inv_handitype,$is_black_col,$fk_htype)");

      return $htype;
   }//get_handicaptype_for_invite



   /*!
    * \brief Enriches 1.0.17 game-setup format (simple + invitation) with new default (empty) hero-ratio.
    * \return null = no change required; otherwise enriched game-setup-string
    */
   public static function enrich_game_setup_hero_ratio( $game_setup )
   {
      $rv = "[^:]*"; // regex for value between colons
      //GS-fmt since DGS 1.0.18 (with hero-ratio): $rx_gs = "/T$rv:U$rv:H$rv:$rv:$rv:$rv:K$rv:$rv:J$rv:FK$rv:R$rv:$rv:$rv:$rv:H%$rv:$rv:C/"
      //GS-fmt prior to DGS 1.0.18 (without hero-ratio H%...):
      $rx_gs_1_0_17 = "/(T$rv:U$rv:H$rv:$rv:$rv:$rv:K$rv:$rv:J$rv:FK$rv:R$rv:$rv:$rv:$rv)(:$rv:C)/";

      // add default (empty) hero-ratio
      $replace_count = 0;
      $enriched_game_setup = preg_replace( $rx_gs_1_0_17, '\1:H%\2', $game_setup, 2, $replace_count );

      return ( !is_null($enriched_game_setup) && strcmp($game_setup, $enriched_game_setup) != 0 ) ? $enriched_game_setup : null;
   }//enrich_game_setup_hero_ratio

} //end 'DeprecatedGameSetup'

?>
