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

include( "config.php" );
include( "connect2mysql.php" );

$session_duration = 3600*24*7; // 1 week

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

function getmicrotime()
{
    list($usec, $sec) = explode(" ",microtime()); 
    return ((float)$usec + (float)$sec); 
} 

function start_page( $title, $no_cache, $logged_in, &$player_row )
{
echo "
<HTML>
  <HEAD>
";

 if( $no_cache )
     {
echo '
    <META HTTP-EQUIV="Pragma" CONTENT="no-cache">
    <META HTTP-EQUIV="Expires" CONTENT="0">
';
     }
echo '
    <TITLE> Dragon Go Server - ' . $title . '</TITLE>
    <LINK rel="stylesheet" type="text/css" media="screen" href="dragon.css">
  </HEAD>
  <BODY bgcolor="F7F5E3">

    <table width="100%" border=0 cellspacing=0 cellpadding=4 class="title" bgcolor="9FED7B">
        <tr>
          <td colspan="3" width="50%">
          <A class="title" href="index.php"><B>Dragon Go Server</B></A></td>
';


if( $logged_in ) 
    echo '          <td colspan="3" align="right" width="50%" class="title"><font color="0C41C9"><B class="title">Logged in as: ' . $player_row["Handle"] . ' </B></font></td>';
else
    echo '          <td colspan="3" align="right" width="50%"><font color="0C41C9"><B class="title">Not logged in</B></font></td>';

echo '
        </tr>
        <tr bgcolor="F7F5E3" align="center">
          <td><B><A href="status.php">Status</A></B></td>
          <td><B><A href="messages.php">Messages</A></B></td>
          <td><B><A href="invite.php">Invite</A></B></td>
          <td><B><A href="users.php">Users</A></B></td>
          <td><B><A href="faq.php">FAQ</A></B></td>
        </tr>
    </table>
    <BR>
';
}

function end_page( $new_paragraph = true )
{
    global $time;
    global $show_time;

    if( $new_paragraph )
        echo "<p>";
echo '
    <table width="100%" border=0 cellspacing=0 cellpadding=4 class="title" bgcolor="9FED7B">
      <tr>
        <td align="left" width="50%">
          <A class="title" href="index.php"><B>Dragon Go Server</B></A></td>
        <td align="right" width="50%">';
 if( $show_time )
     echo '
        <font color="0C41C9"><B class="title">Page created in ' . 
         sprintf ("%0.5f", getmicrotime() - $time) . '&nbsp;s</B></font></td>';
 else
     echo '<A class="title" href="index.php?logout=t"><B>Logout</B></A></td>';

 echo '
      </tr>
    </table>
  </BODY>
</HTML>
';

}

function make_session_code()
{
    mt_srand((double)microtime()*1000000); 
    return sprintf("%06X%06X%04X\0",mt_rand(0,16777215), mt_rand(0,16777215), mt_rand(0,65535));
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


function make_html_safe(&$msg)
{
// Filter out HTML code
$msg = htmlspecialchars($msg);

  
// Strip out carriage returns
$msg = ereg_replace("\r","",$msg);
// Handle paragraphs
$msg = ereg_replace("\n\n","<P>",$msg);
// Handle line breaks
$msg = ereg_replace("\n","<BR>",$msg);
}

function score2text($score, $verbose)
{
    if( !isset($score) )
        $text = "?";        
    else if( $score == 0 )
        $text = "Jigo";
    else
        {
            if( $verbose )
                $text = ( $score > 0 ? "White wins by " : "Black wins by " );
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

function is_logged_in($hdl, $scode, &$row)
{
    global $time, $show_time;
    $time = getmicrotime();
    $show_time = false;


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

    if( $row["flags"] & WANT_EMAIL AND $row["Notify"] != 'NONE' )
        {
            $result = mysql_query( "UPDATE Players " .
                                   "SET Notify='NONE' WHERE Handle='$hdl'" );
            
            if( mysql_affected_rows() != 1 )
                return false;
        }

    if( $hdl=='ejlo' )
        $show_time = true;

    return true;
}

?>