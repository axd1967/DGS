<?php

$quick_errors = 1;
require_once( "include/quick_common.php" );
require_once( "include/connect2mysql.php" );

function slashed($string)
{
   return str_replace( array( '\\', '\''), array( '\\\\', '\\\''), $string );
}

function quick_warning($string) //Short one line message
{
   echo "\nWarning: " . ereg_replace( "[\x01-\x20]+", " ", $string);
}

if( $is_down )
{
   quick_warning($is_down_message);
}
else
{
   disable_cache();

   connect2mysql();

   // logged in?

   $uhandle= @$_COOKIE[COOKIE_PREFIX.'handle'];
   $result = @mysql_query( "SELECT ID, Timezone, " .
                           "UNIX_TIMESTAMP(Sessionexpire) AS Expire, Sessioncode " .
                           "FROM Players WHERE Handle='".addslashes($uhandle)."'" );

   if( @mysql_num_rows($result) != 1 )
   {
      error("not_logged_in",'qs1');
   }

   $player_row = mysql_fetch_assoc($result);

   if( $player_row['Sessioncode'] !== @$_COOKIE[COOKIE_PREFIX.'sessioncode']
       or $player_row["Expire"] < $NOW )
   {
      error("not_logged_in",'qs2');
   }

   setTZ( $player_row['Timezone']);

   $my_id = $player_row['ID'];

   $nothing_found = true;


   // New messages?

   $query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS date, me.mid, " .
      "Messages.Subject, Players.Name AS sender " .
      "FROM Messages, MessageCorrespondents AS me " .
      "LEFT JOIN MessageCorrespondents AS other " .
        "ON other.mid=me.mid AND other.Sender!=me.Sender " .
      "LEFT JOIN Players ON Players.ID=other.uid " .
      "WHERE me.uid=$my_id AND me.Folder_nr=".FOLDER_NEW." " .
              "AND Messages.ID=me.mid " .
              "AND me.Sender='N' " . //exclude message to myself
      "ORDER BY date DESC";

   $result = mysql_query( $query ) or error('mysql_query_failed','qs3');

   while( $row = mysql_fetch_assoc($result) )
   {
      $nothing_found = false;
      if( !@$row['sender'] ) $row['sender']='[Server message]';
      echo "'M', {$row['mid']}, '".slashed(@$row['sender'])."', '" .
         slashed(@$row['Subject']) . "', '" .
         date('Y-m-d H:i', @$row['date']) . "'\n";
   }


   // Games to play?

   $query = "SELECT Black_ID,White_ID,Games.ID, (White_ID=$my_id)+0 AS Color, " .
       "UNIX_TIMESTAMP(LastChanged) as date, " .
       "opponent.Name, opponent.Handle, opponent.ID AS pid " .
       "FROM Games,Players AS opponent " .
       "WHERE ToMove_ID=$my_id AND Status!='INVITED' AND Status!='FINISHED' " .
         "AND opponent.ID=(Black_ID+White_ID-$my_id) " .
       "ORDER BY date DESC, Games.ID";

   $result = mysql_query( $query ) or error('mysql_query_failed','qs4');

   $clrs="BW"; //player's color... so color to play.
   while( $row = mysql_fetch_assoc($result) )
   {
      $nothing_found = false;
      echo "'G', {$row['ID']}, '" . slashed(@$row['Name']) .
         "', '" . $clrs{@$row['Color']} . "', '" .
         date('Y-m-d H:i', @$row['date']) . "'\n";
   }

    
   if( $nothing_found )
      quick_warning('empty lists');
}
?>