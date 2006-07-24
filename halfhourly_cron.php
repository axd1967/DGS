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


$quick_errors = 1; //just store errors in log database
require_once( "include/std_functions.php" );
require_once( "include/board.php" );


if( !function_exists('html_entity_decode') ) //Does not exist on dragongoserver.sourceforge.net
{
   $reverse_htmlentities_table= get_html_translation_table(HTML_ENTITIES); //HTML_SPECIALCHARS or HTML_ENTITIES
   $reverse_htmlentities_table= array_flip($reverse_htmlentities_table);

   function html_entity_decode($str, $quote_style=ENT_COMPAT, $charset='iso-8859-1')
   {
    global $reverse_htmlentities_table;
      return strtr($str, $reverse_htmlentities_table);
   }
}

function mail_link( $nam, $lnk)
{
  global $HOSTBASE;

   $nam = trim($nam);
   $lnk = trim($lnk);
   if( $lnk )
   {
      if( strcspn($lnk,":?#") == strcspn($lnk,"?#") 
          && !is_numeric(strpos($lnk,'//'))
          && strtolower(substr($lnk,0,4)) != "www."
        )
         $lnk = $HOSTBASE.$lnk;
      $nam = ( $nam ? "$nam ($lnk)" : "$lnk" );
   }
   if( !$nam )
      return '';
   $nam = str_replace("\\\"","\"",$nam);
   return "[ $nam ]";
}

//see also make_html_safe()
$tmp = '[\\x1-\\x20]*=[\\x1-\\x20]*(\"|\'|)([^>\\x1-\\x20]*?)';
$strip_html_table = array(
    "%&nbsp;%si" => " ",
    "%<A([\\x1-\\x20]+((href$tmp\\4)|(\w+$tmp\\7)|(\w+)))*[\\x1-\\x20]*>(.*?)</A>%sie"
       => "mail_link('\\10','\\5')",
    "%</?(UL|BR)[\\x1-\\x20]*/?>%si"
       => "\n",
    "%</CENTER[\\x1-\\x20]*/?>\n?%si"
       => "\n",
    "%\n?<CENTER[\\x1-\\x20]*/?>%si"
       => "\n",
    "%</?P[\\x1-\\x20]*/?>%si"
       => "\n\n",
    "%[\\x1-\\x20]*<LI[\\x1-\\x20]*/?>[\\x1-\\x20]*%si"
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

   $result = mysql_query( "SELECT ($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff " .
                          "FROM Clock WHERE ID=202 LIMIT 1")
               or error('mysql_query_failed','halfhourly_cron1');

   $row = mysql_fetch_array( $result );
   mysql_free_result($result);

   if( $row['timediff'] < $half_diff )
      if( !@$_REQUEST['forced'] ) exit;

   mysql_query("UPDATE Clock SET Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=202")
               or error('mysql_query_failed','halfhourly_cron2');


// Send notifications


   $result = mysql_query( "SELECT ID as uid, Email, SendEmail, Lastaccess FROM Players " .
                          "WHERE SendEmail LIKE '%ON%' AND Notify='NOW'" )
               or error('mysql_query_failed','halfhourly_cron3');


   while( $row = mysql_fetch_array( $result ) )
   {
      extract($row);

      $msg = "A message or game move is waiting for you at:\n "
                . mail_link('',"status.php")."\n"
           . "(No more notifications will be send until your reconnection)\n"
           ;

      // Find games

      if( !(strpos($SendEmail, 'MOVE') === false) )
      {
         $query = "SELECT Games.*, " .
             "black.Name AS Blackname, " .
             "black.Handle AS Blackhandle, " .
             "white.Name AS Whitename, " .
             "white.Handle AS Whitehandle " .
             "FROM Games, Players AS black, Players AS white " .
             "WHERE ToMove_ID=$uid AND Black_ID=black.ID AND White_ID=white.ID" .
             " AND UNIX_TIMESTAMP(Lastchanged) >= UNIX_TIMESTAMP('$Lastaccess')";

         $res2 = mysql_query( $query )
               or error('mysql_query_failed','halfhourly_cron4' . $query);

         if( @mysql_num_rows($res2) > 0 )
         {
            $msg .= str_pad('', 47, '-') . "\n  Games:\n";

            while( $game_row = mysql_fetch_array( $res2 ) )
            {
               extract($game_row);

               $TheBoard = new Board( );
               if( !$TheBoard->load_from_db( $game_row) )
                  error('internal_error', "halfhourly_cron load_from_db $ID");
               $movemsg= $TheBoard->movemsg;

               $msg .= str_pad('', 47, '-') . "\n";
               $msg .= "Game ID: ".mail_link($ID,"game.php?gid=$ID")."\n";
               $msg .= "Black: ".mail_strip_html("$Blackname ($Blackhandle)")."\n";
               $msg .= "White: ".mail_strip_html("$Whitename ($Whitehandle)")."\n";
               $tmp = number2board_coords($Last_X, $Last_Y, $Size);
               if( empty($tmp) ) $tmp = "lead to $Status step";
               $msg .= "Move $Moves: $tmp\n";

               if( !(strpos($SendEmail, 'BOARD') === false) )
               {
                  //remove sgf tags
                  $movemsg = trim(preg_replace(
                     "'(<c(omment)? *>(.*?)</c(omment)? *>)".
                     "|(<h(idden)? *>(.*?)</h(idden)? *>)'is"
                     , '', $movemsg));
                  $movemsg = mail_strip_html( $movemsg);
                  $msg .= $TheBoard->draw_ascii_board( $movemsg);
               }
            }
         }
         mysql_free_result($res2);
      }


      // Find new messages

      if( !(strpos($SendEmail, 'MESSAGE') === false) )
      {
  $folderstring=FOLDER_NEW;
         $query = "SELECT Messages.*, " .
            "UNIX_TIMESTAMP(Messages.Time) AS date, " .
            "Players.Name AS FromName, Players.Handle AS FromHandle " .
            "FROM Messages, MessageCorrespondents AS me " .
            "LEFT JOIN MessageCorrespondents AS other " .
              "ON other.mid=me.mid AND other.Sender!=me.Sender " .
            "LEFT JOIN Players ON Players.ID=other.uid " .
            "WHERE me.uid=$uid AND Messages.ID=me.mid " .
              "AND me.Folder_nr IN ($folderstring) " .
              "AND me.Sender='N' " . //exclude message to myself
              "AND UNIX_TIMESTAMP(Messages.Time) > UNIX_TIMESTAMP('$Lastaccess') " .
            "ORDER BY Time DESC";


         $res3 = mysql_query( $query )
               or error('mysql_query_failed','halfhourly_cron5' . $query);
         if( @mysql_num_rows($res3) > 0 )
         {
            $msg .= str_pad('', 47, '-') . "\n  New messages:\n";
            while( $msg_row = mysql_fetch_array( $res3 ) )
            {
               extract($msg_row);

               if($FromName && $FromHandle)
                  $From= mail_strip_html("$FromName ($FromHandle)");
               else
                  $From= 'Server message';

               $msg .= str_pad('', 47, '-') . "\n" .
                   "Message: ".mail_link('',"message.php?mid=$ID") . "\n" .
                   "Date: ".date($date_fmt, $date) . "\n" .
                   "From: $From\n" .
                   "Subject: ".mail_strip_html($Subject) . "\n\n" .
                   wordwrap(mail_strip_html($Text),47) . "\n";
            }
         }
         mysql_free_result($res3);
      }

      $msg .= str_pad('', 47, '-');

      $headers = "From: $EMAIL_FROM\n";
      //if HTML in mail allowed:
      //$headers.= "MIME-Version: 1.0\n";
      //$headers.= "Content-type: text/html; charset=iso-8859-1\n";

      if( !function_exists('mail')
       or !mail( trim($Email), $FRIENDLY_LONG_NAME.' notification', $msg, $headers ) )
         error('mail_failure',"Uid:$uid Addr:$Email Text:$msg");
   }
   mysql_free_result($result);


   //Setting Notify to 'DONE' stop notifications until the player's visite
   mysql_query( "UPDATE Players SET Notify='DONE' " .
                "WHERE SendEmail LIKE '%ON%' AND Notify='NOW' " )
               or error('mysql_query_failed','halfhourly_cron6');

   mysql_query( "UPDATE Players SET Notify='NOW' " .
                "WHERE SendEmail LIKE '%ON%' AND Notify='NEXT' " )
               or error('mysql_query_failed','halfhourly_cron7');



// Update activities

   $factor = exp( -M_LN2 * 30 / $ActivityHalvingTime );

   mysql_query("UPDATE Players SET Activity=Activity * $factor")
               or error('mysql_query_failed','halfhourly_cron8');


// Check end of vacations

   $result = mysql_query("SELECT ID, ClockUsed from Players " .
                         "WHERE OnVacation>0 AND OnVacation <= 1/(2*24)")
               or error('mysql_query_failed','halfhourly_cron9');

   while( $row = mysql_fetch_array( $result ) )
   {
      $uid = $row['ID'];
      $ClockUsed = $row['ClockUsed'];

      $res2 = mysql_query("SELECT Games.ID as gid, LastTicks+Clock.Ticks AS ticks " .
                          "FROM Games, Clock " .
                          "WHERE Clock.ID=$ClockUsed AND ToMove_ID='$uid' " .
                          "AND ClockUsed=".VACATION_CLOCK." " .
                          "AND Status!='INVITED' AND Status!='FINISHED'")
               or error('mysql_query_failed','halfhourly_cron10');

      while( $row2 = mysql_fetch_array( $res2 ) )
      {
         mysql_query("UPDATE Games SET ClockUsed=$ClockUsed, " .
                     "LastTicks='" . $row2['ticks'] . "' " .
                     "WHERE ID='" . $row2['gid'] . "' LIMIT 1")
               or error('mysql_query_failed','halfhourly_cron11');
      }
      mysql_free_result($res2);
   }
   mysql_free_result($result);



// Change vacation days

   mysql_query("UPDATE Players SET " .
               "VacationDays=LEAST(365.24/12, VacationDays + 1/(12*2*24)), " .
               "OnVacation=GREATEST(0, OnVacation - 1/(2*24))")
               or error('mysql_query_failed','halfhourly_cron12');

}
?>