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

 /* Author: Jens-Uwe Gaspar */

$TranslateGroups[] = "Bulletin";

require_once 'include/db/bulletin.php';
require_once 'include/classlib_user.php';
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'tournaments/include/tournament.php';


 /*!
  * \class GuiBulletin
  *
  * \brief Class to handle Bulletin-GUI-stuff
  */

// lazy-init in Bulletin::get..Text()-funcs
global $ARR_GLOBALS_BULLETIN; //PHP5
$ARR_GLOBALS_BULLETIN = array();

class GuiBulletin
{
   // ------------ static functions ----------------------------

   /*! \brief Returns category-text or all category-texts (if arg=null). */
   function getCategoryText( $category=null )
   {
      global $ARR_GLOBALS_BULLETIN;

      // lazy-init of texts
      $key = 'CAT';
      if( !isset($ARR_GLOBALS_BULLETIN[$key]) )
      {
         $arr = array();
         $arr[BULLETIN_CAT_MAINT]            = T_('Maintenance#B_cat');
         $arr[BULLETIN_CAT_ADMIN_MSG]        = T_('Admin Announcement#B_cat');
         $arr[BULLETIN_CAT_TOURNAMENT]       = T_('Tournament Announcement#B_cat');
         $arr[BULLETIN_CAT_TOURNAMENT_NEWS]  = T_('Tournament News#B_cat');
         $arr[BULLETIN_CAT_FEATURE]          = T_('Feature Info#B_cat');
         $arr[BULLETIN_CAT_PRIVATE_MSG]      = T_('Private Announcement#B_cat');
         $arr[BULLETIN_CAT_SPAM]             = T_('Advertisement#B_cat');
         $ARR_GLOBALS_BULLETIN[$key] = $arr;
      }

      if( is_null($category) )
         return $ARR_GLOBALS_BULLETIN[$key];

      if( !isset($ARR_GLOBALS_BULLETIN[$key][$category]) )
         error('invalid_args', "GuiBulletin::getCategoryText($category,$key)");
      return $ARR_GLOBALS_BULLETIN[$key][$category];
   }

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   function getStatusText( $status=null )
   {
      global $ARR_GLOBALS_BULLETIN;

      // lazy-init of texts
      $key = 'STATUS';
      if( !isset($ARR_GLOBALS_BULLETIN[$key]) )
      {
         $arr = array();
         $arr[BULLETIN_STATUS_NEW]        = T_('New#B_status');
         $arr[BULLETIN_STATUS_PENDING]    = T_('Pending#B_status');
         $arr[BULLETIN_STATUS_REJECTED]   = T_('Rejected#B_status');
         $arr[BULLETIN_STATUS_SHOW]       = T_('Show#B_status');
         $arr[BULLETIN_STATUS_ARCHIVE]    = T_('Archive#B_status');
         $arr[BULLETIN_STATUS_DELETE]     = T_('Delete#B_status');
         $ARR_GLOBALS_BULLETIN[$key] = $arr;
      }

      if( is_null($status) )
         return $ARR_GLOBALS_BULLETIN[$key];

      if( !isset($ARR_GLOBALS_BULLETIN[$key][$status]) )
         error('invalid_args', "GuiBulletin::getStatusText($status,$key)");
      return $ARR_GLOBALS_BULLETIN[$key][$status];
   }

   /*! \brief Returns target-type-text or all target-type-texts (if arg=null). */
   function getTargetTypeText( $trg_type=null )
   {
      global $ARR_GLOBALS_BULLETIN;

      // lazy-init of texts
      $key = 'TRGTYPE';
      if( !isset($ARR_GLOBALS_BULLETIN[$key]) )
      {
         $arr = array();
         $arr[BULLETIN_TRG_UNSET]    = NO_VALUE;
         $arr[BULLETIN_TRG_ALL]      = T_('All Users#B_trg');
         $arr[BULLETIN_TRG_TD]       = T_('T-Director#B_trg');
         $arr[BULLETIN_TRG_TP]       = T_('T-Participant#B_trg');
         $arr[BULLETIN_TRG_USERLIST] = T_('UserList#B_trg');
         $arr[BULLETIN_TRG_MPG]      = T_('MP-Game#B_trg');
         $ARR_GLOBALS_BULLETIN[$key] = $arr;
      }

      if( is_null($trg_type) )
         return $ARR_GLOBALS_BULLETIN[$key];

      if( !isset($ARR_GLOBALS_BULLETIN[$key][$trg_type]) )
         error('invalid_args', "GuiBulletin::getTargetTypeText($trg_type,$key)");
      return $ARR_GLOBALS_BULLETIN[$key][$trg_type];
   }

   /*! \brief Returns Flags-text or all Flags-texts (if arg=null). */
   function getFlagsText( $flag=null )
   {
      global $ARR_GLOBALS_BULLETIN;

      // lazy-init of texts
      $key = 'FLAGS';
      if( !isset($ARR_GLOBALS_BULLETIN[$key]) )
      {
         $arr = array();
         $arr[BULLETIN_FLAG_ADMIN_CREATED] = T_('Admin-Created#B_flag');
         $arr[BULLETIN_FLAG_USER_EDIT]     = T_('User-Changeable#B_flag');
         $ARR_GLOBALS_BULLETIN[$key] = $arr;
      }

      if( is_null($flag) )
         return $ARR_GLOBALS_BULLETIN[$key];
      if( !isset($ARR_GLOBALS_BULLETIN[$key][$flag]) )
         error('invalid_args', "GuiBulletin::getFlagsText($flag,$short)");
      return $ARR_GLOBALS_BULLETIN[$key][$flag];
   }//getFlagsText

   /*! \brief Returns text-representation of bulletin-flags. */
   function formatFlags( $flags, $zero_val='', $intersect_flags=0, $class=null )
   {
      $check_flags = ( $intersect_flags > 0 ) ? $flags & $intersect_flags : $flags;

      $arr = array();
      $arr_flags = GuiBulletin::getFlagsText();
      foreach( $arr_flags as $flag => $flagtext )
      {
         if( $check_flags & $flag )
            $arr[] = ($class) ? span($class, $flagtext) : $flagtext;
      }
      return (count($arr)) ? implode(', ', $arr) : $zero_val;
   }//formatFlags

   /*! \brief Prints formatted Bulletin with CSS-style with author, publish-time, text. */
   function build_view_bulletin( $bulletin, $mark_url='' )
   {
      global $rx_term;

      $category = span('Category', GuiBulletin::getCategoryText($bulletin->Category), '%s:' );
      $title = make_html_safe($bulletin->Subject, true, $rx_term);
      $title = preg_replace( "/[\r\n]+/", '<br>', $title ); //reduce multiple LF to one <br>
      $text = make_html_safe($bulletin->Text, true, $rx_term);
      $text = preg_replace( "/[\r\n]+/", '<br>', $text ); //reduce multiple LF to one <br>
      $publish_text = sprintf( T_('[%s] by %s#bulletin'),
         date(DATE_FMT2, $bulletin->PublishTime), $bulletin->User->user_reference() );

      if( $bulletin->tid > 0 )
      {
         if( is_null($bulletin->Tournament) )
            $bulletin->Tournament = Tournament::load_tournament($bulletin->tid);
         $ref_text = span('Reference', $bulletin->Tournament->build_info(5, 30) );
      }
      elseif( $bulletin->gid > 0 )
      {
         $ref_text = span('Reference',
            sprintf( '%s %s', // linked: (img) Game-info
                     echo_image_game_players($bulletin->gid),
                     game_reference( REF_LINK, 1, '', $bulletin->gid, 0, array( 'Status' => GAME_STATUS_SETUP ) ) ));
      }
      else
         $ref_text = '';

      if( $mark_url )
      {
         global $base_path;
         $mark_link = anchor( make_url( $base_path.$mark_url, array( 'mr' => $bulletin->ID ) ),
            T_('Mark as read#bulletin'), T_('Mark bulletin as read#bulletin') );
         $div_mark = "<div class=\"MarkRead\">$mark_link</div>";
      }
      else
         $div_mark = '';

      return
         "<div class=\"Bulletin\">\n" .
            "<div class=\"Category\">{$category}{$ref_text}</div>" .
            "<div class=\"PublishTime\">$publish_text</div>" .
            "<div class=\"Title\">$title</div>" .
            "<div class=\"Text\">$text</div>" .
            $div_mark .
         "</div>\n";
   }//build_view_bulletin

} // end of 'GuiBulletin'
?>
