<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
//require_once( "include/rating.php" );


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   $my_id = (int)@$player_row['ID'];
   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   if( (@$player_row['AdminOptions'] & ADMOPT_DENY_EDIT_BIO) )
      error('edit_bio_denied');

   $my_id = $player_row['ID'];
   $change_it = isset($_REQUEST['action']);


   // use default sort-order: SortOrder & ID (fallback)
   $result = db_query( 'change_bio.find_bio',
      "SELECT * FROM Bio WHERE uid=$my_id ORDER BY SortOrder" );

   $bios = array();
   $idx = 0;
   while( $row = mysql_fetch_assoc( $result ) )
   {
      $bid= $row['ID'];

      // delete entry
      if( $change_it )
      {
         $EnteredText = trim(get_request_arg("text$bid"));
         if( $EnteredText == "" )
         {
            $query = "DELETE FROM Bio WHERE ID=$bid LIMIT 1";
            db_query( "change_bio.delete_bio[$bid]", $query );
            continue;
         }
      }

      $idx++;
      $row['newpos'] = $idx;
      $bios[$idx] = $row;
   }
   mysql_free_result($result);
   $max_pos = $idx;

   // compute the new SortOrder
   if( !$change_it )
   {
      foreach( $bios as $idx => $row )
      {
         $bid= $row['ID'];

         // check for bios movements
         $pos = (int)@$_REQUEST['move'.$bid];
         if( !$pos )
            continue;

         $pos+= $idx;
         while( $pos < 1 )
            $pos+= $max_pos;
         while( $pos > $max_pos )
            $pos-= $max_pos;

         // swap in internal struct
         swap( $bios[$idx]['newpos'], $bios[$pos]['newpos']);
         // note: the result is slightly different in case of
         // multiple adjacent ups or multiple adjacent downs
         // but, usually, this is executed with only one move at a time.
      }
   }

   // update existing DB-entries
   foreach( $bios as $idx => $row )
   {
      $bid= $row['ID'];

      if( $change_it )
      {
         $EnteredText = trim(get_request_arg("text$bid"));
         $EnteredCategory = trim(get_request_arg("category$bid"));
         if( $EnteredCategory == '' )
            $EnteredCategory = '='.trim(get_request_arg("other$bid"));

         if( $EnteredText == $row['Text']
               && $EnteredCategory == $row['Category']
               && $row['SortOrder'] == $row['newpos'] )
            continue;

         $query = 'UPDATE Bio SET ' .
            'Text="'.mysql_addslashes($EnteredText).'"' .
            ', Category="'.mysql_addslashes($EnteredCategory).'"' .
            ', SortOrder="'.$row['newpos'].'"' .
            " WHERE ID=$bid AND uid=$my_id LIMIT 1";
      }
      else
      {
         if( $row['SortOrder'] == $row['newpos'] )
            continue;

         $query = 'UPDATE Bio SET ' .
            //', Text="'.mysql_addslashes($row['Text']).'"' .
            //', Category="'.mysql_addslashes($row['Category']).'"' .
            ', SortOrder="'.$row['newpos'].'"' .
            " WHERE ID=$bid AND uid=$my_id LIMIT 1";
      }

      db_query( "change_bio.alter_bio[$bid]", $query );
   }

   // add new entries
   $idx = get_request_arg("newcnt");
   if( $change_it )
   for( $bid=1; $bid <= $idx; $bid++ )
   {
      $EnteredText = trim(get_request_arg("newtext$bid"));
      $EnteredCategory = trim(get_request_arg("newcategory$bid"));

      if( $EnteredCategory == '' )
         $EnteredCategory = '='.trim(get_request_arg("newother$bid"));

      if( $EnteredText == "" )
         continue;

      $query = "INSERT INTO Bio SET uid=$my_id" .
            ', Text="'.mysql_addslashes($EnteredText).'"' .
            ', Category="'.mysql_addslashes($EnteredCategory).'"' .
            ', SortOrder="'.(++$max_pos).'"';

      db_query( 'change_bio.insert_bio', $query );
   }


   if( !$change_it )
   {
      // was up/down-move
      jump_to("edit_bio.php?editorder=1");
   }

   $msg = urlencode(T_('Bio updated!'));

   jump_to("edit_bio.php?sysmsg=$msg");
   //jump_to("userinfo.php?uid=$my_id" . URI_AMP."sysmsg=$msg");
}
?>
