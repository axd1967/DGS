<?php

require_once( "include/config.php" );
require_once( "include/connect2mysql.php" );

define("FOLDER_NEW", 2);

function slashed($string)
{
   return str_replace( array( '\\', '\''), array( '\\\\', '\\\''), $string );
}

{

   connect2mysql();

   $timeadjust = 0;
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

   if( $player_row['Sessioncode'] !== @$_COOKIE['sessioncode']
       or $player_row["Expire"] < $NOW )
   {
      echo "Error: not logged in";
      exit;
   }

   if( !empty( $player_row["Timezone"] ) )
      putenv('TZ='.$player_row["Timezone"] );

   $my_id = $player_row['ID'];

   $nothing_found = true;

   // New messages?

   $query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS date, me.mid, " .
      "Messages.Subject, Players.Name AS sender " .
      "FROM MessageCorrespondents AS me " .
      "LEFT JOIN Messages ON Messages.ID=me.mid " .
      "LEFT JOIN MessageCorrespondents AS other " .
        "ON other.mid=me.mid AND other.Sender!=me.Sender " .
      "LEFT JOIN Players ON Players.ID=other.uid " .
      "WHERE me.uid=$my_id AND me.Folder_nr=".FOLDER_NEW." " .
      "ORDER BY date DESC";

   $result = mysql_query( $query ) or die(mysql_error());

   while( $row = mysql_fetch_array($result) )
   {
      $nothing_found = false;
      echo "'M', {$row['mid']}, '".slashed($row['sender'])."', '" .
         slashed($row['Subject']) . "', '" .
         date('Y-m-d H:i', $row['date']) . "'\n";
   }


   // Games to play?

   $query = "SELECT Black_ID,White_ID,Games.ID, (White_ID=$my_id)+1 AS Color, " .
       "UNIX_TIMESTAMP(LastChanged) as date, " .
       "opponent.Name, opponent.Handle, opponent.ID AS pid " .
       "FROM Games,Players AS opponent " .
       "WHERE ToMove_ID=$my_id AND Status!='INVITED' AND Status!='FINISHED' " .
         "AND (opponent.ID=Black_ID OR opponent.ID=White_ID) AND opponent.ID!=$my_id " .
       "ORDER BY date DESC, Games.ID";

   $result = mysql_query( $query ) or die(mysql_error());


   while( $row = mysql_fetch_array($result) )
   {
      $nothing_found = false;
      echo "'G', {$row['ID']}, '".slashed($row['Name'])."', '{$row['Color']}', '" .
         date('Y-m-d H:i', $row['date']) . "'\n";
   }

    
    if( $nothing_found )
      echo "Warning: nothing found";
}
?>