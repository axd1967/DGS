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

chdir( '../' );
require_once( "include/std_functions.php" );

{
   disable_cache();

   connect2mysql();

   //init_standard_folders();


  $logged_in = who_is_logged( $player_row);

  if( !$logged_in )
    error("not_logged_in");

  $player_level = (int)$player_row['admin_level'];
  if( !($player_level & ADMIN_DATABASE) )
    error("adminlevel_too_low");


   if( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) { 
        if( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
        echo " --- fixed. ";
      }
      echo "<p>*** Fixes errors:<br>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; ";}
      echo "<p>(just show queries needed):<br>";
   }


   if( ($uid=@$_REQUEST['uid']) > 0 )
      check_myself_message( $uid);
   else
      check_myself_message();



   echo "<p>Lost replied:";

   $query = "SELECT org.*, cor.Replied, cor.Sender, cor.ID as cid, rep.ID as rid"
     ." FROM Messages as rep, Messages as org, MessageCorrespondents AS cor, MessageCorrespondents AS cre"
     ." WHERE rep.ReplyTo=org.ID"
     .  " AND cor.mid=org.ID AND cor.Replied!='Y' AND cor.Sender!='Y'"
     .  " AND cre.mid=rep.ID AND cre.Sender!='N' AND cor.uid=cre.uid"
     ." ORDER BY org.ID";
   $result = mysql_query( $query ) or die(mysql_error());

   while( ($row = mysql_fetch_assoc( $result )) )
   {
      echo '<br>mid= ' . $row['ID'] . ' &lt;- ' . $row['rid'];
      dbg_query("UPDATE MessageCorrespondents SET Replied='Y' " .
                   "WHERE ID=".$row['cid']." LIMIT 1" );
   }

   echo "<br>Lost replied done.\n";

}

// Try to find Myself message
//see also: message_list_query and message_list_table
function check_myself_message( $user_id=false)
{
   echo "<p>Messages to myself:";

//Find old way *messages to myself*, i.e. where sender and receiver are the same user.
   $query = "SELECT me.mid as mid, " .
      "me.ID as me_mcID, other.ID as other_mcID, " .
      "me.Replied AS replied, other.Replied AS other_replied, " .
      "me.Folder_nr AS folder, other.Folder_nr AS other_folder " .
      "FROM MessageCorrespondents AS me, MessageCorrespondents AS other " .
      "WHERE other.mid=me.mid AND other.uid=me.uid " .
        "AND me.Sender='N' AND other.Sender != me.Sender " .
        ( $user_id>0 ? "AND me.uid=$user_id " : "" ) .
      "ORDER BY me.uid, me.mid";

   $result = mysql_query( $query ) or die(mysql_error());

   while( ($row = mysql_fetch_assoc( $result )) )
   {
      echo '<br>mid ' . $row['mid'];

      $folder = @$row['folder'];
      if( !isset($folder) ) $folder = @$row['other_folder'];
      if( !isset($folder) ) $folder = FOLDER_MAIN; /* or simply "NULL" */

      $replied = @$row['replied'];
      if( !isset($replied) or $replied=='N' ) $replied = @$row['other_replied'];
      if( !isset($replied) ) $replied = 'N';

      $mcID = $row['me_mcID'];
      dbg_query("UPDATE MessageCorrespondents SET Sender='M', Folder_nr=$folder, Replied='$replied' " .
                   "WHERE ID=$mcID LIMIT 1" );
      $mcID = $row['other_mcID'];
      dbg_query("DELETE FROM MessageCorrespondents WHERE ID=$mcID LIMIT 1" );
   }

   echo "<br>Messages to myself done.\n";
}
?>