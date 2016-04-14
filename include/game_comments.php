<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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
 * Viewing of game messages while read (game-, game-comments-page) or downloaded (sgf):
 *    (Opponent also includes all game-players of multi-player-game)
 *
 * : Game  : Text ::         Viewed by         :: sgf+comments by : sgf only :
 * : Ended : Tag  :: Writer : Oppon. : Others  :: Writer : Oppon. : any ones :
 * : ----- : ---- :: ------ : ------ : ------- :: ------ : ------ : -------- :
 * : no    : none :: yes    : yes    : no      :: yes    : yes    : no       :
 * : no    : <c>  :: yes    : yes    : yes     :: yes    : yes    : yes      :
 * : no    : <h>  :: yes    : no     : no      :: yes    : no     : no       :
 * : no    : <m>  :: yes    : no     : no      :: yes    : no     : no       :
 * : ----- : ---- :: ------ : ------ : ------- :: ------ : ------ : -------- :
 * : yes   : none :: yes    : yes    : no      :: yes    : yes    : no       :
 * : yes   : <c>  :: yes    : yes    : yes     :: yes    : yes    : yes      :
 * : yes   : <h>  :: yes    : yes    : yes     :: yes    : yes    : yes      :
 * : yes   : <m>  :: yes    : no     : no      :: yes    : yes    : no       :
 * : ----- : ---- :: ------ : ------ : ------- :: ------ : ------ : -------- :
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
 * - Secret comments (surrounded by <m> + <mysecret> tags) can be only be viewed
 *   by author (player that wrote it) as long as the game is not finished,
 *   which depends on move-number & player that made the particular move.
 *   So "other" players and observers cannot see it.
 *   When the game is finished, secret comments are visible to author & all opponents,
 *   but everyone else (observer) can still not see the comments.
 *
 * - Private comments (outside of public/hidden/secret comment tags) can only be viewed by players.
 */
class GameCommentHelper
{
   private $gid;
   private $is_game_running;
   private $is_mpgame;
   private $game_players;
   private $handicap;
   private $mpg_users; // Players-fields, see last arg of GamePlayer::load_users_for_mpgame()-func
   private $mpg_active_user;

   // runtime (set by filter_comment-func)
   private $mpg_user = 0;
   private $mpg_move_color = 0;

   public function __construct( $gid, $game_status, $game_type, $game_players, $handicap, $mpg_users, $mpg_active_user )
   {
      $this->gid = (int)$gid;
      $this->is_game_running = ( $game_status != GAME_STATUS_FINISHED );
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
    * \param $html false = strip away HTML and strip starting/ending c/h/m-tags for observers (e.g. for SGF);
    *              true = HTML-format
    *
    * \note Also sets $this->mpg_user and $this->mpg_move_color if game is MPG; to 0 otherwise
    *
    * \note For observers also the <c>, <h> and <m> starting and closing tags are removed for non-html,
    *     because in pure text (without formatting like in HTML) differing between <c> and <h>/<m> blocks
    *     would not be possible for the players.
    */
   public function filter_comment( $text, $move_nr, $move_stone, $viewmode, $html )
   {
      $text = trim($text);
      if ( (string)$text == '' )
         return '';

      if ( $this->is_mpgame ) // MPG
      {
         // get player of current move
         list( $group_color, $group_order, $this->mpg_move_color ) =
            MultiPlayerGame::calc_game_player_for_move( $this->game_players, $move_nr, $this->handicap, -1 );
         $this->mpg_user = GamePlayer::get_user_info( $this->mpg_users, $group_color, $group_order );

         $is_player = is_array($this->mpg_active_user); // game-player of MP-game
         $is_move_player = $is_player && is_array($this->mpg_user) && ($this->mpg_user['uid'] == $this->mpg_active_user['uid']);
      }
      else // std-game
      {
         $this->mpg_user = $this->mpg_move_color = 0;
         $is_player = ( $viewmode == BLACK || $viewmode == WHITE );
         $is_move_player = $is_player && ( $viewmode == $move_stone );
      }

      if ( $is_player )
      {
         if ( $is_move_player ) // writer
            $remove_hidden = $remove_secret = false;
         else  // opponents
            $remove_hidden = $remove_secret = $this->is_game_running;
      }
      else // observer
      {
         $remove_secret = true;
         $remove_hidden = $this->is_game_running;
      }

      // ----------- adjust text -----------

      // only if non-html, because $html_mode='game' removes hidden & secret tags as well
      if ( !$html )
      {
         if ( $remove_hidden )
            $text = self::remove_hidden_game_tags($text);
         if ( $remove_secret )
            $text = self::remove_secret_game_tags($text);
      }

      if ( !$is_player ) // strip away private-comments (and tags if non-html)
         $text = self::game_tag_filter( $text, $remove_secret, /*incl-tags*/$html );

      if ( $html )
      {
         if ( $remove_hidden )
            $html_mode = 'game';
         else
            $html_mode = ( $remove_secret ) ? 'gameh' : 'gamehs';
         $text = make_html_safe( $text, $html_mode ); // HTML-format of comment-tags, 'game' strips hidden & secret comments
      }

      return trim( $text );
   }//filter_comment

   /*!
    * \brief Keeps and trims the parts readable by an observer, but removs the private parts
    *       (i.e. the text outside of <c>/<h>/<m> tags).
    * \param $includeTags if false also removes the <c>/<h>/<m>-tags itself keeping only the surrounded text
    * \note $includeTags==false MUST NOT be used to format HTML, because then style-applying is impossible with the tags gone
    */
   public static function game_tag_filter( $msg, $remove_secret=false, $includeTags=true )
   {
      if ( $includeTags )
      {
         $idx_c = 1;
         $idx_h = 5;
         $idx_m = 9;
      }
      else
      {
         $idx_c = 3;
         $idx_h = 7;
         $idx_m = 11;
      }

      $nr_matches = preg_match_all(
            "%(<c(omment)? *>(.*?)</c(omment)? *>)" .
            "|(<h(idden)? *>(.*?)</h(idden)? *>)" .
            "|(<m(ysecret)? *>(.*?)</m(ysecret)? *>)%is",
            $msg, $matches );

      $str = '';
      for ($i=0; $i<$nr_matches; $i++)
      {
         $msg = trim($matches[$idx_c][$i]); // keep <c> ?
         if ( (string)$msg == '' )
            $msg = trim($matches[$idx_h][$i]); // keep <h> ?
         if ( (string)$msg == '' && !$remove_secret )
            $msg = trim($matches[$idx_m][$i]); // keep <m> ?
         if ( (string)$msg != '' )
            $str .= "\n" . $msg;
      }

      return trim($str);
   }//game_tag_filter

   /*! \brief Removes hidden comment tags and included text. */
   public static function remove_hidden_game_tags( $msg )
   {
      return trim(preg_replace("'<h(idden)? *>(.*?)</h(idden)? *>'is", "", $msg));
   }

   /*! \brief Removes secret comment tags and included text. */
   public static function remove_secret_game_tags( $msg )
   {
      return trim(preg_replace("'<m(ysecret)? *>(.*?)</m(ysecret)? *>'is", "", $msg));
   }

} // end of 'GameCommentHelper'

?>
