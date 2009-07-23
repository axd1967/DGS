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


require_once( "include/std_functions.php" );
require_once( "include/board.php" );

$TheErrors->set_mode(ERROR_MODE_COLLECT);

if( !function_exists('html_entity_decode') ) //Does not exist on dragongoserver.sourceforge.net
{
   //HTML_SPECIALCHARS or HTML_ENTITIES, ENT_COMPAT or ENT_QUOTES or ENT_NOQUOTES
   $reverse_htmlentities_table= get_html_translation_table(HTML_ENTITIES, ENT_QUOTES);
   $reverse_htmlentities_table= array_flip($reverse_htmlentities_table);

   function html_entity_decode($str, $quote_style=ENT_COMPAT, $charset='ISO-8859-1')
   {
    global $reverse_htmlentities_table;
      return strtr($str, $reverse_htmlentities_table);
   }
}

function mail_link( $nam, $lnk)
{
   $nam = trim($nam);
   $lnk = trim($lnk);
   if( $lnk )
   {
      if( strcspn($lnk,":?#") == strcspn($lnk,"?#")
          && !is_numeric(strpos($lnk,'//'))
          && strtolower(substr($lnk,0,4)) != "www."
        )
      {
         //make it absolute to this server
         while( substr($lnk,0,3) == '../' )
            $lnk = substr($lnk,3);
         $lnk = HOSTBASE.$lnk;
      }
      $nam = ( $nam ? "$nam ($lnk)" : "$lnk" );
   }
   if( !$nam )
      return '';
   $nam = str_replace("\\\"","\"",$nam);
   return "[ $nam ]";
}

//to be used as preg_exp. see also make_html_safe()
$tmp = '[\\x1-\\x20]*=[\\x1-\\x20]*(\"|\'|)([^>\\x1-\\x20]*?)';
$strip_html_table = array(
    "%&nbsp;%si" => " ",
    "%<A([\\x1-\\x20]+((href$tmp\\4)|(\w+$tmp\\7)|(\w+)))*[\\x1-\\x20]*>(.*?)</A>%sie"
       => "mail_link('\\10','\\5')",
    "%</?(UL|BR)[\\x1-\\x20]*/?\>%si"
       => "\n",
    "%</CENTER[\\x1-\\x20]*/?\>\n?%si"
       => "\n",
    "%\n?<CENTER[\\x1-\\x20]*/?\>%si"
       => "\n",
    "%</?P[\\x1-\\x20]*/?\>%si"
       => "\n\n",
    "%[\\x1-\\x20]*<LI[\\x1-\\x20]*/?\>[\\x1-\\x20]*%si"
       => "\n - ",
   );
function mail_strip_html( $str)
{
 global $strip_html_table;
   //keep replaced tags
   $str = strip_tags( $str, '<a><br><p><center><ul><ol><li><goban>');
   $str = preg_replace( array_keys($strip_html_table), array_values($strip_html_table), $str);
   //remove remainding tags
   $str = strip_tags( $str, '<goban>');
   $str = html_entity_decode( $str, ENT_QUOTES, 'iso-8859-1');
   return $str;
}


if( !$is_down )
{
   if( @$chained ) //when chained after clock_tick.php
   {
      $i = 3600/2;
      $half_diff = $i - $chained/2;
      $chained = $i;
   }
   else
   {
      $half_diff = 1500;
      connect2mysql();
   }


   // Check that updates are not too frequent

   $row = mysql_single_fetch( 'halfhourly_cron.check_frequency',
      "SELECT ($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff"
      ." FROM Clock WHERE ID=202 LIMIT 1" );
   if( !$row ) $TheErrors->dump_exit('halfhourly_cron');

   if( $row['timediff'] < $half_diff )
      //if( !@$_REQUEST['forced'] )
         $TheErrors->dump_exit('halfhourly_cron');

   db_query( 'halfhourly_cron.set_lastchanged',
      "UPDATE Clock SET Ticks=1, Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=202 LIMIT 1" )
      or $TheErrors->dump_exit('halfhourly_cron');


   $this_ticks_per_day = 2*24;

// Send notifications
// Email notification if the SendEmail 'ON' flag is set
// more infos if other SendEmail flags are set

   $result = db_query( 'halfhourly_cron.find_notifications',
            "SELECT ID as uid, Email, SendEmail, UNIX_TIMESTAMP(Lastaccess) AS X_Lastaccess FROM Players"
            ." WHERE Notify='NOW' AND FIND_IN_SET('ON',SendEmail)");
            //." WHERE SendEmail LIKE '%ON%' AND Notify='NOW'");


   while( $row = mysql_fetch_assoc( $result ) )
   {
      extract($row);
      $Email= trim($Email);
      if( !$Email || !verify_email( false, $Email) )
      {
         //error('bad_mail_address', "halfhourly_cron=$Email");
         continue;
      }

      $msg = "A message or game move is waiting for you at:\n "
                . mail_link('',"status.php")."\n"
           . "(No more notifications will be send until your reconnection)\n"
           ;

      // Find games

      if( is_numeric(strpos($SendEmail, 'MOVE')) )
      {
         $query = "SELECT Games.*, " .
             "black.Name AS Blackname, " .
             "black.Handle AS Blackhandle, " .
             "white.Name AS Whitename, " .
             "white.Handle AS Whitehandle " .
             "FROM (Games, Players AS black, Players AS white) " .
             "WHERE ToMove_ID=$uid AND Black_ID=black.ID AND White_ID=white.ID" .
             " AND Lastchanged >= FROM_UNIXTIME($X_Lastaccess)";

         $gres = db_query( 'halfhourly_cron.find_games', $query );

         if( @mysql_num_rows($gres) > 0 )
         {
            $msg .= str_pad('', 47, '-') . "\n  Games:\n";

            while( $game_row = mysql_fetch_array( $gres ) )
            {

               $TheBoard = new Board();
               if( !$TheBoard->load_from_db( $game_row) )
                  error('internal_error', 'halfhourly_cron load_from_db '.$game_row['ID']);
               $movemsg= $TheBoard->movemsg;

               $msg .= str_pad('', 47, '-') . "\n";
               $msg .= "Game ID: ".mail_link($game_row['ID'],'game.php?gid='.$game_row['ID'])."\n";
               $msg .= "Black: ".mail_strip_html(
                        $game_row['Blackname'].' ('.$game_row['Blackhandle'].')')."\n";
               $msg .= "White: ".mail_strip_html(
                        $game_row['Whitename'].' ('.$game_row['Whitehandle'].')')."\n";
               $tmp = number2board_coords($game_row['Last_X'], $game_row['Last_Y'], $game_row['Size']);
               if( empty($tmp) ) $tmp = 'lead to '.$game_row['Status'].' step';
               $msg .= 'Move '.$game_row['Moves'].": $tmp\n";

               if( is_numeric(strpos($SendEmail, 'BOARD')) )
               {
                  //remove all sgf tags
                  $movemsg = trim(preg_replace(
                     "'(<c(omment)? *>(.*?)</c(omment)? *>)".
                     "|(<h(idden)? *>(.*?)</h(idden)? *>)'is"
                     , '', $movemsg));
                  $movemsg = mail_strip_html( $movemsg);
                  $msg .= $TheBoard->draw_ascii_board( $movemsg);
               }
               unset($TheBoard);
            }
         }
         mysql_free_result($gres); unset($game_row);
      }


      // Find new messages

      if( is_numeric(strpos($SendEmail, 'MESSAGE')) )
      {
         $folderstring = FOLDER_NEW;
         $query = "SELECT Messages.ID,Subject,Text, " .
            "UNIX_TIMESTAMP(Messages.Time) AS date, " .
            "Players.Name AS FromName, Players.Handle AS FromHandle " .
            "FROM (Messages, MessageCorrespondents AS me) " .
            "LEFT JOIN MessageCorrespondents AS other " .
              "ON other.mid=me.mid AND other.Sender='Y' " .
            "LEFT JOIN Players ON Players.ID=other.uid " .
            "WHERE me.uid=$uid AND Messages.ID=me.mid " .
              "AND me.Folder_nr IN ($folderstring) " .
              "AND me.Sender IN('N','S') " . //exclude message to myself
              "AND Messages.Time > FROM_UNIXTIME($X_Lastaccess) " .
            "ORDER BY Time DESC";

         $res3 = db_query( 'halfhourly_cron.find_new_messages', $query );
         if( @mysql_num_rows($res3) > 0 )
         {
            $msg .= str_pad('', 47, '-') . "\n  New messages:\n";
            while( $msg_row = mysql_fetch_array( $res3 ) )
            {

               if($msg_row['FromName'] && $msg_row['FromHandle'])
                  $From= mail_strip_html(
                     $msg_row['FromName'].' ('.$msg_row['FromHandle'].')');
               else
                  $From= 'Server message';

               $msg .= str_pad('', 47, '-') . "\n" .
                   "Message: ".mail_link('','message.php?mid='.$msg_row['ID']) . "\n" .
                   "Date: ".date(DATE_FMT, $msg_row['date']) . "\n" .
                   "From: $From\n" .
                   "Subject: ".mail_strip_html($msg_row['Subject']) . "\n\n" .
                   wordwrap(mail_strip_html($msg_row['Text']),47) . "\n";
            }
         }
         mysql_free_result($res3); unset($msg_row);
      }

      $msg .= str_pad('', 47, '-');

      send_email('halfhourly_cron', $Email, $msg);
   } //notifications found
   mysql_free_result($result);


   //Setting Notify to 'DONE' stop notifications until the player's visite
   db_query( 'halfhourly_cron.update_players_notify_Done',
         "UPDATE Players SET Notify='DONE'"
         ." WHERE Notify='NOW' AND FIND_IN_SET('ON',SendEmail)"); // LIMIT ?
         //." WHERE SendEmail LIKE '%ON%' AND Notify='NOW'" ); // LIMIT ?

   db_query( 'halfhourly_cron.update_players_notify_Now',
         "UPDATE Players SET Notify='NOW'"
         ." WHERE Notify='NEXT' AND FIND_IN_SET('ON',SendEmail)"); // LIMIT ?
         //." WHERE SendEmail LIKE '%ON%' AND Notify='NEXT'" ); // LIMIT ?



// Update activities

   //close to 0.9964 for a four days halving time (in minutes)
   $factor = exp( -M_LN2 * 30 / $ActivityHalvingTime );

   //the WHERE is just added here to avoid to update the *whole* table each time
   //the FLOOR and integer type also help the re-construction of the index of the column
   db_query( 'halfhourly_cron.activity',
      "UPDATE Players SET Activity=FLOOR($factor*Activity) WHERE Activity>0" );


// Check end of vacations and reset associated game clocks

   $result = db_query( 'halfhourly_cron.onvacation',
      "SELECT ID, ClockUsed FROM Players WHERE OnVacation>0 AND OnVacation<=1/($this_ticks_per_day)" );

   while( $prow = mysql_fetch_assoc( $result ) )
   {
      $uid = $prow['ID'];
      $ClockUsed = $prow['ClockUsed'];

      // LastTicks handle -(time spend) at the moment of the start of vacations
      // inserts this spend time into the (possibly new) ClockUsed by the player
if(1){//new
      db_query( 'edit_vacation.update_games',
         "UPDATE Games"
         ." INNER JOIN Clock ON Clock.ID=$ClockUsed"
         ." SET Games.ClockUsed=$ClockUsed"
            .", Games.LastTicks=Games.LastTicks+Clock.Ticks"
         ." WHERE Games.Status" . IS_RUNNING_GAME
         ." AND Games.ToMove_ID=$uid"
         ." AND Games.ClockUsed<0" // VACATION_CLOCK
         );
}else{//old
      $gres = db_query( 'halfhourly_cron.find_vacation_games',
         "SELECT Games.ID as gid, LastTicks+Clock.Ticks AS ticks " .
                         "FROM (Games, Clock) " .
                         "WHERE Status" . IS_RUNNING_GAME .
                         "AND Games.ClockUsed < 0 " . // VACATION_CLOCK
                         "AND Clock.ID=$ClockUsed " .
                         "AND ToMove_ID='$uid'" );

      while( $game_row = mysql_fetch_assoc( $gres ) )
      {
         db_query( 'halfhourly_cron.update_vacation_games',
            "UPDATE Games SET ClockUsed=$ClockUsed"
                      . ", LastTicks='" . $game_row['ticks'] . "'"
                      . " WHERE ID='" . $game_row['gid'] . "' LIMIT 1" );
      }
      mysql_free_result($gres); //unset($game_row);
}//new/old
   }
   mysql_free_result($result); //unset($prow);



// Change vacation days

   $max_vacations = 365.24/12; //1 month
   db_query( 'halfhourly_cron.vacation_days',
      "UPDATE Players SET OnVacation=GREATEST(0, OnVacation - 1/($this_ticks_per_day))" //1 day after 1 day
      .",VacationDays=LEAST($max_vacations, VacationDays + 1/(12*$this_ticks_per_day))" //1 day after 12 days
      ." WHERE VacationDays<$max_vacations OR OnVacation>0" // LIMIT ???
      );

   db_query( 'halfhourly_cron.reset_tick',
         "UPDATE Clock SET Ticks=0 WHERE ID=202 LIMIT 1" );
if( !@$chained ) $TheErrors->dump_exit('halfhourly_cron');
//the whole cron stuff in one cron job (else comments this line):
include_once( "daily_cron.php" );
}
?>
