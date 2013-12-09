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

$TranslateGroups[] = "Game";

require_once 'include/std_functions.php';
require_once 'include/game_functions.php';


/*!
 * \class GameCommentHelper
 *
 * \brief Helper class to filter game comments dependent on move-number, player and requested view-mode.
 *
 : Viewing of game messages while read (game-, game-comments-page) or downloaded (sgf):
   (Opponent also includes all game-players of multi-player-game)
 : Game  : Text ::         Viewed by         :: sgf+comments by : sgf only :
 : Ended : Tag  :: Writer : Oppon. : Others  :: Writer : Oppon. : any ones :
 : ----- : ---- :: ------ : ------ : ------- :: ------ : ------ : -------- :
 : no    : none :: yes    : yes    : no      :: yes    : yes    : no       :
 : no    : <c>  :: yes    : yes    : yes     :: yes    : yes    : yes      :
 : no    : <h>  :: yes    : no     : no      :: yes    : no     : no       :
 : yes   : none :: yes    : yes    : no      :: yes    : yes    : no       :
 : yes   : <c>  :: yes    : yes    : yes     :: yes    : yes    : yes      :
 : yes   : <h>  :: yes    : yes    : yes     :: yes    : yes    : yes      :
 : ----- : ---- :: ------ : ------ : ------- :: ------ : ------ : -------- :
  corresponding $html_mode (F= a filter only keeping <c> and <h> blocks removing private comments):
 : no    : -    :: gameh  : game   : F+game  ::   ... see sgf.php ...
 : yes   : -    :: gameh  : gameh  : F+gameh ::
 *
 * Visibility of comments:
 * - Public comments (surrounded by <c> + <comment> tags) can be viewed by anyone:
 *   players (MPG or std-game) or observers.
 *
 * - Hidden comments (surrounded by <h> + <hidden> tags) can be only be viewed
 *   by author (player that wrote it) as long as the game is not finished,
 *   which depends on move-number & player that made the particular move.
 *   So "other" players and observers cannot see it.
 *   When the game is finished, everyone can see the comments.
 *
 * - Private comments (outside of public and hidden comment tags) can only be viewed by players.
 */
class GameCommentHelper
{
   private $gid;
   private $is_game_finished;
   private $is_mpgame;
   private $game_players;
   private $handicap;
   private $mpg_users; // Players-fields, see last arg of GamePlayer::load_users_for_mpgame()-func
   private $mpg_active_user;

   // runtime
   private $mpg_user = 0;
   private $mpg_move_color = 0;

   public function __construct( $gid, $game_status, $game_type, $game_players, $handicap, $mpg_users, $mpg_active_user )
   {
      $this->gid = (int)$gid;
      $this->is_game_finished = ( $game_status == GAME_STATUS_FINISHED );
      $this->is_mpgame = ( $game_type != GAMETYPE_GO );
      $this->game_players = $game_players;
      $this->handicap = (int)$handicap;
      $this->mpg_users = $mpg_users;
      $this->mpg_active_user = $mpg_active_user;
   }

   public function get_mpg_user()
   {
      return $this->mpg_user;
   }

   public function get_mpg_move_color()
   {
      return $this->mpg_move_color;
   }

   /*!
    * \brief Returns filtered comments with stripped away text-parts.
    * \param $text raw-comment to filter
    * \param $move_stone BLACK|WHITE
    * \param $viewmode BLACK|WHITE|other, only used for std-game (leaving detection outside this function)
    * \param $html false = strip away HTML and strip starting/ending c/h-tags for observers (e.g. for SGF);
    *              true = HTML-format
    *
    * \note Also sets $this->mpg_user and $this->mpg_move_color if game is MPG; to 0 otherwise
    *
    * \note For observers also the <c> and <h> starting and closing tags are removed for non-html,
    *     because in pure text (without formatting like in HTML) differing between <c> and <h> blocks
    *     would not be possible for the players.
    */
   public function filter_comment( $text, $move_nr, $move_stone, $viewmode, $html )
   {
      $text = trim($text);
      $html_mode = ( $this->is_game_finished ) ? 'gameh' : 'game';
      $remove_hidden_no_html = $use_game_tag_filter = false;

      if ( $this->is_mpgame ) // MPG
      {
         // get player of current move
         list( $group_color, $group_order, $this->mpg_move_color ) =
            MultiPlayerGame::calc_game_player_for_move( $this->game_players, $move_nr, $this->handicap, -1 );
         $this->mpg_user = GamePlayer::get_user_info( $this->mpg_users, $group_color, $group_order );

         if ( is_array($this->mpg_active_user) ) // is game-player of MP-game
         {
            $is_move_player = ( is_array($this->mpg_user) && $this->mpg_user['uid'] == $this->mpg_active_user['uid'] );
            if ( $is_move_player )
               $html_mode = 'gameh';
            if ( !$this->is_game_finished && !$is_move_player )
            {
               $html_mode = 'game';
               $remove_hidden_no_html = true;
            }
         }
         else // observer
         {
            if ( !$this->is_game_finished )
               $remove_hidden_no_html = true;
            $use_game_tag_filter = true;
         }
      }
      else // std-game
      {
         $this->mpg_user = $this->mpg_move_color = 0;

         if ( $viewmode == BLACK || $viewmode == WHITE )
         {
            $html_mode = 'gameh';
            if ( !$this->is_game_finished && $viewmode != $move_stone )
            {
               $html_mode = 'game';
               $remove_hidden_no_html = true;
            }
         }
         else // observer
         {
            if ( !$this->is_game_finished )
               $remove_hidden_no_html = true;
            $use_game_tag_filter = true;
         }
      }

      if ( (string)$text != '' )
      {
         if ( !$html && $remove_hidden_no_html ) // only if non-html, because $html_mode='game' removes hidden tags as well
            $text = remove_hidden_game_tags($text);
         if ( $use_game_tag_filter )
            $text = game_tag_filter( $text, /*incl-tags*/$html ); // strip away private-comments (and tags if non-html)
         if ( $html )
            $text = make_html_safe( $text, $html_mode ); // HTML-format of comment-tags, 'game' strips hidden comments
      }

      return trim( $text );
   }//filter_comment

} // end of 'GameCommentHelper'

?>
