<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival

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

chdir( '../' );
require_once( "include/std_functions.php" );

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'message_consistency');

   $uid = (int)@$_REQUEST['uid'];

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();
   if( $uid > 0 )
      $page_args['uid'] = $uid;

   start_html( 'message_consistency', 0);

//echo ">>>> One shot fix. Do not run it again."; end_html(); exit;
   if( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) {
        if( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
        echo " --- fixed. ";
      }
      echo "<p>*** Fixes errors ***"
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; ";}
      $tmp = array_merge($page_args,array('do_it' => 1));
      echo "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."</p>";
   }


   if( $uid > 0 )
      check_myself_message( $uid);
   else
      check_myself_message();

   if( $uid > 0 )
      check_system_message( $uid);
   else
      check_system_message();

   if( $uid > 0 )
      check_result_message( $uid);
   else
      check_result_message();



   echo "<hr>Lost replied:";

   $query = "SELECT org.*, cor.Replied, cor.Sender, cor.ID as cid, rep.ID as rid"
     ." FROM (Messages as rep, Messages as org, MessageCorrespondents AS cor, MessageCorrespondents AS cre)"
     ." WHERE rep.ReplyTo=org.ID"
     .  " AND cor.mid=org.ID AND cor.Replied!='Y' AND cor.Sender!='Y'"
     .  " AND cre.mid=rep.ID AND cre.Sender!='N' AND cor.uid=cre.uid"
     ." ORDER BY org.ID";
   $result = mysql_query( $query ) or die(mysql_error());

   while( ($row = mysql_fetch_assoc( $result )) )
   {
      echo '<br>mid='.$row['ID'] .' &lt;-'.$row['rid'];
      dbg_query("UPDATE MessageCorrespondents SET Replied='Y' " .
                   "WHERE ID=".$row['cid']." LIMIT 1" );
   }
   mysql_free_result($result);

   echo "<br>Lost replied done.\n";


   echo "<hr>Messages without correspondent:";

   $query = "SELECT M.ID as mid, M.Type"
      .", me.uid as uid, me.ID as me_mcID"
      ." FROM Messages AS M"
      ." LEFT JOIN MessageCorrespondents AS me"
         ." ON M.ID=me.mid"
      ." WHERE me.uid IS NULL"
      ." ORDER BY M.ID";
   $result = mysql_query( $query ) or die(mysql_error());

   while( ($row = mysql_fetch_assoc( $result )) )
   {
      if( $do_it )
      {
         echo "<br> >>> CAN'T BE FIXED\n";
         break;
      }
      echo '<br>mid='.$row['mid'] ;//.' -&gt;???';
      /*
      dbg_query("UPDATE MessageCorrespondents SET $$$ " .
                   "WHERE ID=".$row['cid']." LIMIT 1" );
      */
   }
   mysql_free_result($result);

   echo "<br>Messages without correspondent done.\n";


   echo "<hr>Correspondents without message:";

   $query = "SELECT me.mid as mid, M.Type"
      .", me.uid as uid, me.ID as me_mcID"
      ." FROM MessageCorrespondents AS me"
      ." LEFT JOIN Messages AS M"
         ." ON M.ID=me.mid"
      ." WHERE M.Type IS NULL"
      ." ORDER BY me.ID";
   $result = mysql_query( $query ) or die(mysql_error());

   while( ($row = mysql_fetch_assoc( $result )) )
   {
      if( $do_it )
      {
         echo "<br> >>> CAN'T BE FIXED\n";
         break;
      }
      echo '<br>corr.ID='.$row['me_mcID'] .' -&gt;'.$row['mid'];
   /* see also the note about MessageCorrespondents.mid==0 in message_list_query() */
      /*
      dbg_query("UPDATE MessageCorrespondents SET $$$ " .
                   "WHERE ID=".$row['cid']." LIMIT 1" );
      */
   }
   mysql_free_result($result);

   echo "<br>Correspondents without message done.\n";


   echo "<hr>Done!!!\n";
   end_html();
}

// Try to find myself messages
//see also: message_list_query and message_list_head/body
function check_myself_message( $user_id=false)
{
   echo "<hr>Messages to myself:";

//Find old way *messages to myself*, i.e. where sender and receiver are the same user.
   $query = "SELECT me.uid as uid, me.mid as mid, " .
      "me.ID as me_mcID, other.ID as other_mcID, " .
      "me.Replied AS replied, other.Replied AS other_replied, " .
      "me.Folder_nr AS folder, other.Folder_nr AS other_folder " .
      "FROM (MessageCorrespondents AS me, MessageCorrespondents AS other) " .
      "WHERE other.mid=me.mid AND other.uid=me.uid " .
        "AND me.Sender='N' AND other.Sender != me.Sender " .
        ( $user_id>0 ? "AND me.uid=$user_id " : "" ) .
      "ORDER BY me.uid, me.mid";

   $result = mysql_query( $query ) or die(mysql_error());

   while( ($row = mysql_fetch_assoc( $result )) )
   {
      echo '<br>uid='.$row['uid'] .' mid='.$row['mid'];

      $folder = @$row['folder'];
      if( !isset($folder) ) $folder = @$row['other_folder'];
      if( !isset($folder) ) $folder = FOLDER_MAIN; /* or simply FOLDER_DESTROYED */

      $replied = @$row['replied'];
      if( !isset($replied) || $replied=='N' ) $replied = @$row['other_replied'];
      if( !isset($replied) ) $replied = 'N';

      $mcID = $row['me_mcID'];
      dbg_query("UPDATE MessageCorrespondents SET Sender='M', Folder_nr=$folder, Replied='$replied' " .
                   "WHERE ID=$mcID LIMIT 1" );
      $mcID = $row['other_mcID'];
      dbg_query("DELETE FROM MessageCorrespondents WHERE ID=$mcID LIMIT 1" );
   }
   mysql_free_result($result);

   echo "<br>Messages to myself done.\n";
} //check_myself_message

// Try to find system messages
//see also: message_list_query and message_list_head/body
function check_system_message( $user_id=false)
{
   echo "<hr>Messages from system:";

//Find old way *messages from system*, i.e. where no sender and receiver.Sender=='N'.
   $query = "SELECT me.uid as uid, me.mid as mid, " .
      "me.ID as me_mcID, other.ID as other_mcID, " .
      "me.Replied AS replied, other.Replied AS other_replied, " .
      "me.Folder_nr AS folder, other.Folder_nr AS other_folder " .
      "FROM MessageCorrespondents AS me " .
      "LEFT JOIN MessageCorrespondents AS other"
         ." ON other.mid=me.mid AND other.Sender!='N' " .
      "WHERE me.Sender='N' AND other.Sender IS NULL " .
        ( $user_id>0 ? "AND me.uid=$user_id " : "" ) .
      "ORDER BY me.uid, me.mid";

   $result = mysql_query( $query )
      or die(mysql_error());

   while( ($row = mysql_fetch_assoc( $result )) )
   {
      echo '<br>uid='.$row['uid'] .' mid='.$row['mid'];

      $folder = @$row['folder'];
      if( !isset($folder) ) $folder = FOLDER_DESTROYED; //keep it deleted

      $replied = @$row['replied'];
      if( !isset($replied) ) $replied = 'N';

      $mcID = $row['me_mcID'];
      dbg_query("UPDATE MessageCorrespondents SET Sender='S', Folder_nr=$folder, Replied='$replied' " .
                   "WHERE ID=$mcID LIMIT 1" );
   }
   mysql_free_result($result);

   echo "<br>Messages from system done.\n";
} //check_system_message

// Try to find game result messages
function check_result_message( $user_id=false)
{
   echo "<hr>Game result messages:";

//Find old way *game result essages*, i.e. the last comment of a game.
   $query = "SELECT M.ID as mid, M.Type, M.Game_ID as gid, M.Subject"
      .", me.uid as uid, me.ID as me_mcID"
      .", G.Status, G.Score"
      ." FROM (Messages AS M, Games as G)"
      ." LEFT JOIN MessageCorrespondents AS me"
         ." ON M.ID=me.mid"
      ." WHERE M.Type NOT IN('INVITATION','DISPUTED','RESULT') AND M.Game_ID>0"
         ." AND M.Subject LIKE '%esult%' AND LEFT(M.Subject,3)!='RE:'"
         ." AND G.ID=M.Game_ID AND G.Status='FINISHED'"
         .( $user_id>0 ? " AND me.uid=$user_id" : "" )
      ." ORDER BY me.uid, M.ID";

   $result = mysql_query( $query )
      or die(mysql_error());

   while( ($row = mysql_fetch_assoc( $result )) )
   {
      echo '<br>uid='.@$row['uid'] .' mid='.$row['mid'];
         echo ' gid='.$row['gid'] .' ='.$row['Score'];

      $mid = $row['mid'];
      dbg_query("UPDATE Messages SET Type='RESULT' " .
                   "WHERE ID=$mid LIMIT 1" );
   }
   mysql_free_result($result);

   echo "<br>Game result messages done.\n";
} //check_result_message
?>
