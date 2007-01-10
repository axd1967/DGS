<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
//require_once( "include/rating.php" );


{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   if( $player_row["Handle"] == "guest" )
      error("not_allowed_for_guest");

   $change_it = isset($_REQUEST['action']);


   // use default sort-order: SortOrder & ID (fallback)
   $result = mysql_query("SELECT * FROM Bio WHERE uid=" . $player_row["ID"]
               . " order by SortOrder, ID")
      or error('mysql_query_failed','change_bio.find_bio');

   $bios = array();
   $idx = 0;
   while( $row = mysql_fetch_assoc( $result ) )
   {
      $ID= $row['ID'];

      // delete entry
      if ( $change_it )
      {
         $EnteredText = trim(get_request_arg("text$ID"));
         if( $EnteredText == "" )
         {
            $query = "DELETE FROM Bio WHERE ID=$ID";
            mysql_query( $query )
               or error('mysql_query_failed', "change_bio.delete_bio[$ID]");
            continue;
         }
      }

      $idx++;
      $row['newpos'] = $idx;
      $bios[$idx] = $row;
   }
   $max_pos = $idx;

   // compute the new SortOrder
   if ( !$change_it )
   foreach( $bios as $idx => $row )
   {
      $ID= $row['ID'];

      // check for bios movements
      $move = (int)@$_REQUEST['move'.$ID];
      if ( !$move )
         continue;

      $pos = $idx + $move;
      while( $pos < 1 )
         $pos+= $max_pos;
      while( $pos > $max_pos )
         $pos-= $max_pos;

      // swap in internal struct
      $move = $bios[$idx]['newpos'];
      $bios[$idx]['newpos'] = $bios[$pos]['newpos'];
      $bios[$pos]['newpos'] = $move;
      // note: the result is slightly different if multiple adjacent ups or
      // multiple adjacent downs but it is called with only one move at a time.
   }

   // update existing DB-entries
   foreach( $bios as $idx => $row ) {
      $ID= $row['ID'];

      if ( $change_it )
      {
         $EnteredText = trim(get_request_arg("text$ID"));
         $EnteredCategory = trim(get_request_arg("category$ID"));
         if( $EnteredCategory == '' )
            $EnteredCategory = trim(get_request_arg("other$ID"));

         if( $EnteredText == $row['Text']
            && $EnteredCategory == $row['Category']
            && $row['SortOrder'] == $row['newpos']
            )
            continue;

         $query = "UPDATE Bio set uid=" . $player_row["ID"] .
            ', Text="'.addslashes($EnteredText).'"' .
            ', Category="'.addslashes($EnteredCategory).'"' .
            ', SortOrder="'.$row['newpos'].'"' .
            " WHERE ID=$ID";
      }
      else
      {
         if( $row['SortOrder'] == $row['newpos'] )
            continue;

         $query = "UPDATE Bio set uid=" . $player_row["ID"] .
            //', Text="'.addslashes($row['Text']).'"' .
            //', Category="'.addslashes($row['Category']).'"' .
            ', SortOrder="'.$row['newpos'].'"' .
            " WHERE ID=$ID";
      }

      mysql_query( $query )
         or error('mysql_query_failed', "change_bio.alter_bio[$ID]");
   }

   // add new entries
   $idx = get_request_arg("newcnt");
   if ( $change_it )
   for( $ID=1; $ID <= $idx; $ID++ )
   {
      $EnteredText = trim(get_request_arg("newtext$ID"));
      $EnteredCategory = trim(get_request_arg("newcategory$ID"));

      if( $EnteredCategory == '' )
         $EnteredCategory = trim(get_request_arg("newother$ID"));

      if( $EnteredText == "" )
         continue;

      $query = "INSERT INTO Bio set uid=" . $player_row["ID"] .
            ', Text="'.addslashes($EnteredText).'"' .
            ', Category="'.addslashes($EnteredCategory).'"' .
            ', SortOrder="'.(++$max_pos).'"';

      mysql_query( $query )
         or error('mysql_query_failed','change_bio.insert_bio');
   }


   if ( !$change_it )
   {
      // was up/down-move
      jump_to("edit_bio.php?editorder=1");
   }

   $msg = urlencode(T_('Bio updated!'));

   jump_to("edit_bio.php?sysmsg=$msg");
   //jump_to("userinfo.php?uid=" . $player_row["ID"] . URI_AMP."sysmsg=$msg");

}
?>