<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/db/games.php';
require_once 'include/game_functions.php';
require_once 'include/time_functions.php';
require_once 'include/classlib_user.php';


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];

   $mode = (int) get_request_arg('mode');
   if( $mode != REMATCH_INVITE && $mode != REMATCH_NEWGAME )
      error('invalid_args', "game_rematch.check.mode($mode)");

   $gid = (int) get_request_arg('gid', 0);
   if( $gid < 1 )
      error('unknown_game', "game_rematch.check.game($mode,$gid)");

   // load game
   $game = Games::load_game( $gid );
   if( is_null($game) )
      error('unknown_game', "game_rematch.find_game($mode,$gid)");


   // build URL-values

   $url = array( 'rematch' => 1 ); // field => value
   if( $game->GameType != GAMETYPE_GO ) // MPG
   {
      if( @$player_row['RatingStatus'] == RATING_NONE )
         error('invalid_args', "game_rematch.check.mpg.rating($mode,$gid,$my_id)");

      $mode = REMATCH_NEWGAME;
      build_url_new_game_from_game( $url, $game, /*MPG*/true );
   }
   elseif( $game->tid > 0 || $game->Status != GAME_STATUS_INVITED ) // tourney or normal-game
   {
      $game_setup = GameSetup::new_from_game_setup( $game->GameSetup, /*inv*/false, /*null-empty*/true );
      if( is_null($game_setup) ) // no game-setup available -> use only game-info
      {
         if( $mode == REMATCH_NEWGAME )
            build_url_new_game_from_game( $url, $game, /*MPG*/false );
         else //REMATCH_INVITE
            build_url_invite_from_game( $url, $game );
      }
      else // use game-setup
      {
         if( $mode == REMATCH_NEWGAME )
            build_url_new_game_from_game_setup( $url, $game, $game_setup );
         else //REMATCH_INVITE
            build_url_invite_from_game_setup( $url, $game, $game_setup );
      }
   }
   else
      error('invalid_args', "game_rematch.check.state($mode,$gid)");


   // jump to other page

   if( $mode == REMATCH_INVITE )
      $page = 'message.php?mode=Invite'.URI_AMP;
   else //if( $mode == REMATCH_NEWGAME )
      $page = 'new_game.php?';

   $out = array();
   foreach( $url as $field => $value )
      $out[] = $field . '=' . urlencode($value);

   jump_to($page . implode(URI_AMP, $out));
}//main



function build_url_new_game_from_game( &$url, $game, $is_mpg )
{
   $url['view'] = ( $is_mpg ) ? GSETVIEW_MPGAME : GSETVIEW_EXPERT;

   build_url_game_basics( $url, $game );
   $url['game_players'] = $game->GamePlayers;

   if( !$is_mpg )
   {
      build_url_cat_htype_manual( $url, $game, CAT_HTYPE_MANUAL, null );
      build_url_handi_komi_rated( $url, $game, null );
   }
}//build_url_new_game_from_game

function build_url_new_game_from_game_setup( &$url, $game, $game_setup )
{
   $cat_htype = get_category_handicaptype($game_setup->Handicaptype);

   $url['view'] = ($cat_htype === CAT_HTYPE_FAIR_KOMI) ? GSETVIEW_FAIRKOMI : GSETVIEW_EXPERT;

   build_url_game_basics( $url, $game );
   $url['game_players'] = $game->GamePlayers;

   build_url_cat_htype_manual( $url, $game, $cat_htype, $game_setup->Handicaptype );
   build_url_handi_komi_rated( $url, $game, $game_setup );

   $url['fk_htype'] = ($cat_htype === CAT_HTYPE_FAIR_KOMI) ? $game_setup->Handicaptype : HTYPE_AUCTION_SECRET;
   $url['adj_komi'] = $game_setup->AdjustKomi;
   $url['jigo_mode'] = $game_setup->JigoMode;
   $url['adj_handicap'] = $game_setup->AdjustHandicap;
   $url['min_handicap'] = $game_setup->MinHandicap;
   $url['max_handicap'] = $game_setup->MaxHandicap;

   $url['mb_rated'] = bool_YN($game_setup->MustBeRated);
   if( $game_setup->RatingMin < OUT_OF_RATING )
      $url['rat1'] = $game_setup->RatingMin;
   if( $game_setup->RatingMax < OUT_OF_RATING )
      $url['rat2'] = $game_setup->RatingMax;
   $url['min_rg'] = $game_setup->MinRatedGames;
   $url['same_opp'] = $game_setup->SameOpponent;
   $url['comment'] = $game_setup->Message;
}//build_url_new_game_from_game_setup


function build_url_invite_from_game( &$url, $game )
{
   build_url_invite_to( $url, $game );
   build_url_game_basics( $url, $game );
   build_url_cat_htype_manual( $url, $game, CAT_HTYPE_MANUAL, null );
   build_url_handi_komi_rated( $url, $game, null );
}//build_url_invite_from_game

function build_url_invite_from_game_setup( &$url, $game, $game_setup )
{
   $cat_htype = get_category_handicaptype($game_setup->Handicaptype);

   build_url_invite_to( $url, $game );
   build_url_game_basics( $url, $game );
   build_url_cat_htype_manual( $url, $game, $cat_htype, $game_setup->Handicaptype );
   build_url_handi_komi_rated( $url, $game, $game_setup );

   $url['fk_htype'] = ($cat_htype === CAT_HTYPE_FAIR_KOMI) ? $game_setup->Handicaptype : HTYPE_AUCTION_SECRET;
   $url['jigo_mode'] = $game_setup->JigoMode;

   $url['message'] = $game_setup->Message;
}//build_url_invite_from_game_setup


function build_url_game_basics( &$url, $game )
{
   if( $game->ShapeID > 0 )
   {
      $url['shape'] = $game->ShapeID;
      $url['snapshot'] = $game->ShapeSnapshot;
   }

   $url['ruleset'] = $game->Ruleset;
   $url['size'] = $game->Size;
   $url['stdhandicap'] = bool_YN($game->StdHandicap);

   build_url_time( $url, $game );
}//build_url_game_basics

function build_url_time( &$url, $game )
{
   $MaintimeUnit = 'hours';
   $Maintime = $game->Maintime;
   time_convert_to_longer_unit($Maintime, $MaintimeUnit);
   $url['timeunit'] = $MaintimeUnit;
   $url['timevalue'] = $Maintime;

   $url['byoyomitype'] = $game->Byotype;
   $ByotimeUnit = 'hours';
   $Byotime = $game->Byotime;
   time_convert_to_longer_unit($Byotime, $ByotimeUnit);
   $url['byotimevalue_jap'] = $url['byotimevalue_can'] = $url['byotimevalue_fis'] = $Byotime;
   $url['timeunit_jap'] = $url['timeunit_can'] = $url['timeunit_fis'] = $ByotimeUnit;

   if( $game->Byoperiods > 0 )
      $url['byoperiods_jap'] = $url['byoperiods_can'] = $game->Byoperiods;

   $url['weekendclock'] = bool_YN($game->WeekendClock);
}//build_url_time

function build_url_cat_htype_manual( &$url, $game, $cat_htype, $gs_htype )
{
   global $my_id;

   $url['cat_htype'] = $cat_htype;
   if( !is_null($gs_htype) && $cat_htype === CAT_HTYPE_MANUAL )
      $url['color_m'] = $gs_htype;
   elseif( $game->DoubleGame_ID != 0 )
      $url['color_m'] = HTYPE_DOUBLE;
   elseif( $my_id == $game->Black_ID )
      $url['color_m'] = HTYPE_BLACK;
   elseif( $my_id == $game->White_ID )
      $url['color_m'] = HTYPE_WHITE;
   else
      $url['color_m'] = HTYPE_NIGIRI; // default
}//build_url_cat_htype_manual

function build_url_handi_komi_rated( &$url, $game, $game_setup )
{
   if( is_null($game_setup) )
   {
      $url['handicap_m'] = $game->Handicap;
      $url['komi_m'] = $game->Komi;
   }
   else
   {
      $url['handicap_m'] = $game_setup->Handicap;
      $url['komi_m'] = $game_setup->Komi;
   }
   $url['rated'] = ( $game->Rated == 'N' ) ? 'N' : 'Y';
}//build_url_handi_komi_rated

function build_url_invite_to( &$url, $game )
{
   global $my_id;

   if( $my_id == $game->Black_ID )
      $opp_id = $game->White_ID;
   elseif( $my_id == $game->White_ID )
      $opp_id = $game->Black_ID;
   else
      $opp_id = 0;

   $opp_to = '';
   if( $opp_id > 0 )
   {
      $users = User::load_quick_userinfo( array( $opp_id ) );
      if( isset($users[$opp_id]) )
         $opp_to = $users[$opp_id]['Handle'];
   }
   $url['to'] = $opp_to;
}//build_url_invite_to

?>
