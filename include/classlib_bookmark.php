<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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


$TranslateGroups[] = "Common";

// bookmark.Type
define('BMTYPE_USERS', 1);
define('BMTYPE_WAITINGROOM', 2);
define('BMTYPE_MSG_SEARCH', 3);
define('BMTYPE_CONTACTS', 4);
define('BMTYPE_FEATURES', 5);
define('BMTYPE_VOTES', 6);
define('BMTYPE_FORUM_SEARCH', 7);
define('BMTYPE_GAMES_STATUS', 8);
define('BMTYPE_GAMES_OBSERVED', 9);
define('BMTYPE_GAMES_RUNNING_MY', 10);
define('BMTYPE_GAMES_RUNNING_ALL', 11);
define('BMTYPE_GAMES_FINISHED_MY', 12);
define('BMTYPE_GAMES_FINISHED_ALL', 13);
define('MAX_BMTYPE', 13);


 /*!
  * \class Bookmark
  *
  * \brief Class to handle bookmarks to save filter-/table-form-entry-values
  */
class Bookmark
{
   /*! \brief ID (PK from db). */
   var $id;
   /*! \brief user-id of bookmark. */
   var $user_id;
   /*! \brief Type (can be one of BMTYPE_). */
   var $type;
   /*! \brief sort-order (1..n). */
   var $sortorder;
   /*! \brief bool active (Y|N-enum in DB). */
   var $active;
   /*! \brief user-chosen name */
   var $name;
   /*! \brief Date when bookmark has been created (unix-time). */
   var $created;
   /*! \brief bookmark-content/url/address. */
   var $text;

   /*!
    * \brief Constructs Bookmark-object with specified arguments: created are in UNIX-time.
    *        $id may be 0 to add a new bookmark
    */
   function Bookmark( $id=0, $user_id=0, $type=0, $sortorder=1, $active=false, $name='', $created=0, $text='' )
   {
      if( !is_numeric($user_id) || $user_id < 0 )
         error('invalid_user', "bookmark.Bookmark($id,$user_id)");
      if( !is_numeric($type) || $type < 1 || $type > MAX_BMTYPE )
         error('invalid_arg', "bookmark.Bookmark.type($id,$user_id,$type)");
      $this->id = (int) $id;
      $this->user_id = (int) $user_id;
      $this->type = (int) $type;
      $this->sortorder = (int) $sortorder;
      $this->active = (bool) $active;
      $this->name = $name;
      $this->created = (int) $created;
      $this->text = $text;
   }

   /*! \brief Updates current bookmark-data into database (may replace existing bookmark). */
   function update_bookmark()
   {
      global $NOW;

      $row = mysql_single_fetch( "bookmark.find_user({$this->user_id})",
         "SELECT ID FROM Players WHERE ID={$this->user_id} LIMIT 1" );
      if( !$row )
         error('unknown_user', "bookmark.find_user2({$this->user_id})");

      $update_query = 'REPLACE INTO Bookmark SET'
         . ' ID=' . (int)$this->id
         . ', User_ID=' . (int)$this->user_id
         . ', Type=' . (int)$this->type
         . ', SortOrder=' . (int)$this->sortorder
         . ", active='" . ($this->active ? 'Y' : 'N') . "'" // enum
         . ", Name='" . mysql_addslashes($this->name) . "'"
         . ', Created=FROM_UNIXTIME(' . (int)$this->created .')'
         . ", Text='" . mysql_addslashes($this->text) . "'" // blob
         ;
      $result = mysql_query( $update_query )
         or error('mysql_query_failed', "bookmark.update_bookmark({$this->id},{$this->user_id},{$this->type})");
   }

   /*! \brief Deletes current bookmark from database. */
   function delete_bookmark()
   {
      $delete_query = "DELETE FROM Bookmark WHERE ID='{$this->id}' LIMIT 1";
      $result = mysql_query( $delete_query )
         or error('mysql_query_failed', "bookmark.delete_bookmark({$this->id})" );
   }

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   function to_string()
   {
      return "Bookmark(id={$this->id}): "
         . "user_id=[{$this->user_id}], "
         . "type=[{$this->type}], "
         . "sortorder=[{$this->sortorder}], "
         . "active=[{$this->active}], "
         . "name=[{$this->name}], "
         . "created=[{$this->created}], "
         . "text=[{$this->text}]";
   }


   // ---------- Static Class functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Feature-object. */
   function get_query_fields()
   {
      return array(
         'ID', 'User_ID', 'Type', 'SortOrder', 'Active', 'Name', 'Created', 'Text',
         'IFNULL(UNIX_TIMESTAMP(Created),0) AS X_CreatedU',
      );
   }

   /*!
    * \brief Returns Bookmark-object for specified user and type set
    *        created=$NOW set and all others in default-state.
    */
   function new_bookmark( $user_id, $type, $bm_id=0 )
   {
      global $NOW;

      // id=set, user_id=?, type=?, sortoder=1, active=false, name='', created=NOW, text=''
      $bookmark = new Bookmark( $bm_id, $user_id, $type );
      $bookmark->created = $NOW;
      return $feature;
   }

   /*! \brief Returns Bookmark-object created from specified (db-)row with fields defined by func fields_bookmark. */
   function new_from_row( $row )
   {
      $bookmark = new Bookmark(
         $row['ID'], $row['User_ID'], $row['Type'],
         $row['SortOrder'], (@$row['Active'] == 'Y'), $row['Name'],
         $row['X_CreatedU'], $row['Text'] );
      return $bookmark;
   }

   /*!
    * \brief Returns Bookmark-object for specified bookmark-id $id;
    *        returns null if no bookmark found.
    */
   function load_bookmark( $id )
   {
      if( !is_numeric($id) )
         error('invalid_bookmark', "bookmark.load_bookmark($id)");

      $fields = implode(',', Bookmark::get_query_fields());
      $row = mysql_single_fetch("bookmark.load_bookmark2($id)",
            "SELECT $fields FROM Bookmark WHERE ID='$id' LIMIT 1");
      if( !$row )
         return null;

      return Feature::new_from_row( $row );
   }

} // end of 'Bookmark'

?>
