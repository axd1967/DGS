<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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

require( "include/std_functions.php" );
require( "include/rating.php" );

{
   disable_cache();

   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");


   if( $player_row["Handle"] == "guest" )
      error("not_allowed_for_guest");



   $result = mysql_query("SELECT * FROM Bio where uid=" . $player_row["ID"]);




   while( $row = mysql_fetch_array( $result ) )
   {
      extract($row);

      $EnteredText = trim(${"text$ID"});
      $EnteredCategory = trim(${"category$ID"});

      if( $EnteredCategory == 'Other:' )
         $EnteredCategory = trim(${"other$ID"});

      if( $EnteredText == $Text and $EnteredCategory == $Category )
         continue;

      if( $EnteredText == "" )
         $query = "DELETE FROM Bio WHERE ID=$ID";
      else
         $query = "UPDATE Bio set uid=" . $player_row["ID"] .
            ", Text=\"$EnteredText\", Category=\"$EnteredCategory\" " .
            "WHERE ID=$ID";

      mysql_query( $query ) or error("mysql_query_failed",true);
   }

   for($i=1; $i<=3; $i++)
   {
      $EnteredText = trim(${"newtext$i"});
      $EnteredCategory = trim(${"newcategory$i"});

      if( $EnteredCategory == 'Other:' )
         $EnteredCategory = trim(${"newother$i"});

      if( $EnteredText == "" )
         continue;

      $query = "INSERT INTO Bio set uid=" . $player_row["ID"] .
          ", Text=\"$EnteredText\", Category=\"$EnteredCategory\"";

      mysql_query( $query ) or error("mysql_query_failed",true);

   }

   $msg = urlencode(T_('Bio updated!'));

   jump_to("userinfo.php?uid=" . $player_row["ID"] . "&msg=$msg");
}
?>