<?php
/*
Dragon Go Server
Copyright (C) 2001  Erik Ouchterlony

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

require( "include/config.php" );
require( "include/connect2mysql.php" );

$session_duration = 3600*24*7; // 1 week
$tick_frequency = 12; // ticks/hour
$date_fmt = 'Y-m-d H:i';

$is_down = false;

$ActivityHalvingTime = 4 * 24 * 60; // [minutes] four days halving time;
$ActivityForHit = 1.0;
$ActivityForMove = 10.0;

define("NONE", 0);
define("BLACK", 1);
define("WHITE", 2);
define("DAME", 3);
define("BLACK_TERRITORY", 4);
define("WHITE_TERRITORY", 5);

define("PASS_BLACK", 3);
define("PASS_WHITE", 4);
define("DONE_BLACK", 5);
define("DONE_WHITE", 6);

define("BLACK_DEAD", 7);
define("WHITE_DEAD", 8);


define("KO", 1);

define("WANT_EMAIL", 1);

define("LEFT",1);
define("UP",2);
define("RIGHT",4);
define("DOWN",8);

function getmicrotime()
{
   list($usec, $sec) = explode(" ",microtime()); 
   return ((float)$usec + (float)$sec); 
} 

function disable_cache()
{
   header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT');              // Date in the past
   header ('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
   header ('Cache-Control: no-cache, must-revalidate, max_age=0'); // HTTP/1.1
   header ('Pragma: no-cache');                                    // HTTP/1.0
}

function start_page( $title, $no_cache, $logged_in, &$player_row )
{
   global $HOSTBASE, $is_down;

   if( $no_cache )
      disable_cache();
    
   ob_start("ob_gzhandler");

   echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
  <HEAD>';

//   if( $no_cache )
//       {
//  echo '
//      <META HTTP-EQUIV="Pragma" CONTENT="no-cache">
//      <META HTTP-EQUIV="Expires" CONTENT="0">
//  ';
//       }
   echo '
    <TITLE> Dragon Go Server - ' . $title . '</TITLE>
    <LINK rel="stylesheet" type="text/css" media="screen" href="dragon.css">
  </HEAD>
  <BODY bgcolor="F7F5E3">

    <table width="100%" border=0 cellspacing=0 cellpadding=4 bgcolor=0C41C9>
        <tr>
          <td colspan=3 width="50%">
          <A href="' . $HOSTBASE . '/index.php"><B><font color=FFFC70>Dragon Go Server</font></B></A></td>
';


   if( $logged_in and !$is_down ) 
      echo '          <td colspan=3 align=right width="50%"><font color=FFFC70><B>Logged in as: ' . $player_row["Handle"] . ' </B></font></td>';
   else
      echo '          <td colspan=3 align=right width="50%"><font color=FFFC70><B>Not logged in</B></font></td>';

   echo '
        </tr>
        <tr bgcolor="F7F5E3" align="center">
          <td><B><A href="' . $HOSTBASE . '/status.php">Status</A></B></td>
          <td><B><A href="' . $HOSTBASE . '/messages.php">Messages</A></B></td>
          <td><B><A href="' . $HOSTBASE . '/invite.php">Invite</A></B></td>
          <td><B><A href="' . $HOSTBASE . '/users.php">Users</A></B></td>
          <td><B><A href="' . $HOSTBASE . '/phorum/index.php">Forums</A></B></td>
          <td><B><A href="' . $HOSTBASE . '/docs.php">Docs</A></B></td>
        </tr>
    </table>
    <BR>
';

   if( $is_down )
      {
         echo "Sorry, dragon is down for maintenance at the moment, ". 
            "please return in an hour or so.";
         end_page();
         exit;
      }
}

function end_page( $new_paragraph = true )
{
   global $time, $show_time, $HOSTBASE;

   if( $new_paragraph )
      echo "<p>";
   echo '
    <table width="100%" border=0 cellspacing=0 cellpadding=4 bgcolor=0C41C9>
      <tr>
        <td align="left" width="50%">
          <A href="' . $HOSTBASE . '/index.php"><font color=FFFC70><B>Dragon Go Server</B></font></A></td>
        <td align="right" width="50%">';
   if( $show_time )
      echo '
        <font color=FFFC70><B>Page created in ' . 
         sprintf ("%0.5f", getmicrotime() - $time) . '&nbsp;s</B></font></td>';
   else
      echo '<A href="' . $HOSTBASE . '/index.php?logout=t"><font color=FFFC70><B>Logout</B></font></A></td>';

   echo '
      </tr>
    </table>
  </BODY>
</HTML>
';

   ob_end_flush();
}

function error($err, $mysql=true)
{
   disable_cache();

   $uri = "error.php?err=" . urlencode($err);
   if( $mysql )
      $uri .= "&mysql=" . urlencode($mysql);

   jump_to( $uri );
}

function jump_to($uri)
{
   global $HOSTBASE;

   header( "Location: " . $HOSTBASE . '/' . $uri );
   exit;
}

function make_session_code()
{
   mt_srand((double)microtime()*1000000); 
   return sprintf("%06X%06X%04X\0",mt_rand(0,16777215), mt_rand(0,16777215), mt_rand(0,65535));
}

function random_letter()
{
   $c = mt_rand(0,61);
   if( $c < 10 )
      return chr( $c + ord('0'));
   else if( $c < 36 )
      return chr( $c - 10 + ord('a'));
   else
      return chr( $c - 36 + ord('A'));
}

function generate_random_password()
{
   mt_srand((double)microtime()*1000000); 
   for( $i=0; $i<8; $i++ )
      $return .= random_letter();

   return $return;
}

function set_cookies($uid, $code, $delete=false)
{
   global $session_duration;
   global $SUB_PATH;

   if( $delete )
   {
      $time_diff=-3600;
      $uid = "";
      $code = "";
   }
   else
      $time_diff = $session_duration;

   setcookie ("handle", $uid, time()+$time_diff, "$SUB_PATH" );

   setcookie ("sessioncode", $code, time()+$time_diff, "$SUB_PATH" );
}


function make_html_safe(&$msg, $some_html=false)
{

//   $msg = str_replace('&', '&amp;', $msg);
//   $msg = str_replace('"', '&quot;', $msg);


   if( $some_html )
   {
      // make sure the <, > replacements: {anglstart}, {anglend} are removed from the string
      $msg = str_replace("{anglstart}", "<", $msg);
      $msg = str_replace("{anglend}", ">", $msg);


      // replace <, > with {anglstart}, {anglend} for legal html code

      $msg=eregi_replace("<(mailto:)([^ >\n\t]+)>", 
                         "{anglstart}a href=\"\\1\\2\"{anglend}\\2{anglstart}/a{anglend}", $msg);
      $msg=eregi_replace("<([http|news|ftp]+://[^ >\n\t]+)>", 
                         "{anglstart}a href=\"\\1\"{anglend}\\1{anglstart}/a{anglend}", $msg);


      // Some allowed html tags

      $html_code = "a|b|i|u|center|li|ul|ol|font|p|br";

      $msg=eregi_replace("<(/?($html_code) *[^>]*)>", "{anglstart}\\1{anglend}", $msg);
   }

   // Filter out HTML code

   $msg = ereg_replace("<", "&lt;", $msg);
   $msg = ereg_replace(">", "&gt;", $msg);


   // Strip out carriage returns
   $msg = ereg_replace("\r","",$msg);
   // Handle paragraphs
   $msg = ereg_replace("\n\n","<P>",$msg);
   // Handle line breaks
   $msg = ereg_replace("\n","<BR>",$msg);

   if( $some_html )
   {
      // change back to <, > from {anglstart} , {anglend} 
      $msg = str_replace ("{anglstart}", "<", $msg);
      $msg = str_replace ("{anglend}", ">", $msg);
   }

   return $msg;
}

function make_mysql_safe(&$msg)
{
   $msg = str_replace("\\", "\\\\", $msg);
   $msg = str_replace("\"", "\\\"", $msg);
}

function score2text($score, $verbose)
{
   if( !isset($score) )
      $text = "?";        
   else if( $score == 0 )
      $text = "Jigo";
   else
   {
      $prep = ( abs($score) > 1999 ? 'on' : 'by' );
      if( $verbose )
         $text = ( $score > 0 ? "White wins $prep " : "Black wins $prep " );
      else 
         $text = ( $score > 0 ? "W+" : "B+" );
            
      if( abs($score) > 1999 )
         $text .= "Time";
      else if( abs($score) > 999 )
         $text .= "Resign";
      else 
         $text .= abs($score);
   }

   return $text;
}

function get_clock_used($nightstart)
{
   return gmdate('G', mktime ($nightstart,0,0,date("m"),date("d"),date("Y")));
}

function get_clock_ticks($clock_used)
{
   $result = mysql_query( "SELECT Ticks FROM Clock WHERE ID=$clock_used" );
   if( mysql_num_rows( $result ) != 1 )
      error("mysql_clock_ticks", true);

   $row = mysql_fetch_row($result);
   return $row[0]; 
}

function mod($a,$b) 
{ 
   if ($a <= 0) 
      return (int) ($b*(int)(-$a/$b+1)+$a) % $b;
   else 
      return (int) $a % $b; 
} 

function time_remaining($hours, &$main, &$byotime, &$byoper, $startmaintime, 
$byotype, $startbyotime, $startbyoper, $has_moved)
{
   $elapsed = $hours;

   if( $main > $elapsed ) // still have main time left
   {
      $main -= $elapsed;

      if( $has_moved and $byotype == 'FIS' )
         $main = min($startmaintime, $main + $startbyotime);

      return;
   }

   $elapsed -= $main;

   if( $main > 0 or $byoper < 0 ) // entering byoyomi
   {
      $byotime = $startbyotime;
      $byoper = $startbyoper;
   }
  
   if( $byotype == 'JAP' )
   {
      $byoper -= (int)(($startbyotime + $elapsed - $byotime)/$startbyotime);
      if( !$has_moved )
         $byotime = mod($byotime-$elapsed-1, $startbyotime)+1;

      if( $byoper < 0 )
         $byotime = $byoper = 0;  // time is up;
   }
   else if( $byotype == 'CAN' ) // canadian byoyomi
   {
      if( $has_moved )
         $byoper--; // byo stones;

      $byotime -= $elapsed;

      if( $byotime <= 0 )
         $byotime = 0;
      else if( $byoper <= 0 ) // get new stones;
      {
         $byotime = $startbyotime;
         $byoper = $startbyoper;
      }
            
   }
   else if( $byotype == 'FIS' )
   {
      $byotime = $byoper = 0;  // time is up;
   }

   $main = 0;    
}

function echo_time($hours)
{
   if( $hours <= 0 )
   {
      echo '-';
      return;
   }
   $days = (int)($hours/15);
   if( $days > 0 )
   {
      echo $days . '&nbsp;day';
      if( $days != 1 ) echo 's';
   }

   if( $hours % 15 > 0 )
   {
      if( $days > 0 )
         echo '&nbsp;and&nbsp;';
      echo $hours % 15 . '&nbsp;hour';
      if( $hours % 15 != 1 ) echo 's';
   }
}

function is_logged_in($hdl, $scode, &$row)
{
   global $time, $show_time, $HOSTBASE, $REQUEST_URI, $HOSTNAME, $HTTP_HOST;
   global $ActivityHalvingTime, $ActivityForHit;
   $time = getmicrotime();
   $show_time = false;

   if( $HTTP_HOST != $HOSTNAME )
      jump_to( "http://" . $HOSTNAME . $REQUEST_URI );


   if( !$hdl )
      return false;

   $result = mysql_query( "SELECT *, UNIX_TIMESTAMP(Sessionexpire) AS Expire, " .
                          "Flags+0 AS flags " .
                          "FROM Players WHERE Handle='$hdl'" );
    
   if( mysql_num_rows($result) != 1 )
      return false;

   $row = mysql_fetch_array($result);

   if( $row["Sessioncode"] != $scode or $row["Expire"] < time() )
      return false;

   $query = "UPDATE Players SET Hits=Hits+1, Activity=Activity + $ActivityForHit";

   if( $row["flags"] & WANT_EMAIL AND $row["Notify"] != 'NONE' )
      $query .= ", Notify='NONE'";
    
   $query .= " WHERE Handle='$hdl'";

   $result = mysql_query( $query );
    
   if( mysql_affected_rows() != 1 )
      return false;
    


   if( $row["Adminlevel"] >= 3 )
      $show_time = true;

   if( !empty( $row["Timezone"] ) )
      putenv('TZ='.$row["Timezone"] );



   return true;
}

?>