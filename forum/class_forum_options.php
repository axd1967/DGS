<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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

/*!
 * \file class_forum_options.php
 *
 * \brief Helper-class to manage forum-options
 */

$TranslateGroups[] = "Forum";

require_once( "include/quick_common.php" );
require_once( "include/std_functions.php" );


// Forums.Options
define('FORUMOPT_MODERATED',     0x0001);
define('FORUMOPT_GROUP_ADMIN',   0x0002); // forum is of admin group
define('FORUMOPT_GROUP_DEV',     0x0004); // forum is of develop/advisor group
define('FORUMOPT_READ_ONLY',     0x0008);
// mask for normally hidden forums
define('FORUMOPTS_GROUPS_HIDDEN', FORUMOPT_GROUP_ADMIN|FORUMOPT_GROUP_DEV);


 /*!
  * \class ForumOptions
  * \brief Class to handle Forums.Options and user allowed to view hidden forums.
  */
class ForumOptions
{
   /*! \brief Players.ID */
   var $uid;
   /*! \brief from Players.Adminlevel */
   var $admin_level;
   /*! \brief true if admin-option ADMOPT_FGROUP_ADMIN for user set [bool] */
   var $view_admin;
   /*! \brief true if admin-option ADMOPT_FGROUP_DEV for user set [bool] */
   var $view_dev;

   /*! \brief Constructs ForumOptions from specified player_row. */
   function ForumOptions( $urow )
   {
      $this->uid = (int)@$urow['ID'];
      $this->admin_level = (int)@$urow['admin_level'];
      $this->view_admin = (@$urow['AdminOptions'] & ADMOPT_FGROUP_ADMIN);
      $this->view_dev = (@$urow['AdminOptions'] & ADMOPT_FGROUP_DEV);
   }

   function is_executive_admin()
   {
      return ($this->admin_level & ADMINGROUP_EXECUTIVE);
   }

   /*! \brief returns true if forum with given Options can be seen by user. */
   function is_visible_forum( $fopts )
   {
      $show = true;

      // hidden forums has precedence (overwriting other forum-visibility)
      if( $fopts & FORUMOPTS_GROUPS_HIDDEN )
      {// is hidden forum
         if( ($fopts & FORUMOPT_GROUP_DEV) && $this->view_dev )
            $show = true;
         elseif( ($fopts & FORUMOPT_GROUP_ADMIN) && $this->view_admin )
            $show = true;
         else
            $show = false;
      }

      return $show;
   }

   /*! \brief Returns bit-mask to use on Forum.Options to select only matching forums for user. */
   function build_db_options_exclude()
   {
      // choose mask, so that: Forums.Options & exclude_mask = 0 !!
      $mask = 0;
      if( !$this->view_admin )
         $mask |= FORUMOPT_GROUP_ADMIN;
      if( !$this->view_dev )
         $mask |= FORUMOPT_GROUP_DEV;
      return $mask;
   }

} // end of 'ForumOptions'

?>
