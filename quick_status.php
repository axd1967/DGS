<?php

require_once( "include/config.php" );
require_once( "include/connect2mysql.php" );



{

   connect2mysql();

   if( @is_readable("timeadjust.php" ) )
      include( "timeadjust.php" );

   if( !is_numeric($timeadjust) )
      $timeadjust = 0;

   $NOW = time() + (int)$timeadjust;

   // logged in?

   $result = @mysql_query( "SELECT ID, Timezone, " .
                           "UNIX_TIMESTAMP(Sessionexpire) AS Expire, Sessioncode " .
                           "FROM Players WHERE Handle='{$_COOKIE['handle']}'" );


   if( @mysql_num_rows($result) != 1 )
   {
      echo "Error: not logged in";
      exit;
   }

   $player_row = mysql_fetch_array($result);

   if( $player_row['Sessioncode'] !== $_COOKIE['sessioncode']
       or $player_row["Expire"] < $NOW )
   {
      echo "Error: not logged in";
      exit;
   }

   if( !empty( $player_row["Timezone"] ) )
      putenv('TZ='.$player_row["Timezone"] );

   $my_id = $player_row['ID'];

   // New messages?

   $query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS time, me.mid, " .
      "Messages.Subject, Players.Name AS sender " .
      "FROM MessageCorrespondents AS me " .
      "LEFT JOIN Messages ON Messages.ID=me.mid " .
      "LEFT JOIN MessageCorrespondents AS other " .
      "ON other.mid=me.mid AND other.Sender != me.Sender " .
      "LEFT JOIN Players ON Players.ID=other.uid " .
      "WHERE me.uid=$my_id AND me.Folder_nr=2 " .
      "ORDER BY Time DESC";

   $result = mysql_query( $query ) or die(mysql_error());

   while( $row = mysql_fetch_array($result) )
   {
      echo "'M', {$row['mid']}, '{$row['sender']}', '" .
         str_replace('\'', '\\\'', $row['Subject']) . "', '" .
         date('Y-m-d H:i', $row['time']) . "'\n";
   }


   $query = "SELECT Black_ID,White_ID,Games.ID, (White_ID=$my_id)+1 AS Color, " .
       "opponent.Name, opponent.Handle, opponent.ID AS pid " .
       "FROM Games,Players AS opponent " .
       "WHERE ToMove_ID=$my_id AND Status!='INVITED' AND Status!='FINISHED' " .
       "AND (opponent.ID=Black_ID OR opponent.ID=White_ID) AND opponent.ID!=$my_id " .
       "ORDER BY LastChanged, Games.ID";

   $result = mysql_query( $query ) or die(mysql_error());


   while( $row = mysql_fetch_array($result) )
   {
      echo "'G', {$row['ID']}, '{$row['Name']}', '{$row['Color']}'\n";
   }
}
?>