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
echo "
    <META HTTP-EQUIV=\"Pragma\" CONTENT=\"no-cache\">
    <META HTTP-EQUIV=\"Expires\" CONTENT=\"0\">
";
     }
echo "
    <TITLE> Dragon Go Server - $title </TITLE>
  </HEAD>
  <BODY bgcolor=\"F7F5E3\">

    <table width=\"100%\" border=0 cellspacing=0 cellpadding=4>
        <tr bgcolor=\"0C41C9\">
          <td colspan=\"3\" width=\"50%\"><font color=\"FFFC70\"><B>Dragon Go Server</B></font></td>
";

if( $logged_in ) 
    echo "          <td colspan=\"3\" align=\"right\" width=\"50%\"><font color=\"FFFC70\"><B>Logged in as: " . 
        $player_row["Handle"] . " </B></font></td>\n";
else
    echo "          <td colspan=\"3\" align=\"right\" width=\"50%\"><font color=\"FFFC70\"><B>Not logged in</B></font></td>\n";

echo "
        </tr>
        <tr bgcolor=\"F7F5E3\" align=\"center\">
          <td><font color=\"FFFC70\"><B><A href=\"index.php\">Main</A></B></font></td>
          <td><font color=\"FFFC70\"><B><A href=\"status.php\">Status</A></B></font></td>
          <td><font color=\"FFFC70\"><B><A href=\"messages.php\">Messages</A></B></font></td>
          <td><font color=\"FFFC70\"><B><A href=\"invite.php\">Invite</A></B></font></td>
          <td><font color=\"FFFC70\"><B><A href=\"users.php\">Users</A></B></font></td>
          <td><font color=\"FFFC70\"><B><A href=\"faq.php\">FAQ</A></B></font></td>
        </tr>


    </table>
    
    <BR>
";
}

function end_page( $new_paragraph = true )
{
    global $time;
    
    if( $new_paragraph )
        echo "<p>";
echo "
    <table width=\"100%\" border=0 cellspacing=0 cellpadding=4>
      <tr bgcolor=\"0C41C9\">
        <td align=\"left\" width=\"50%\"><font color=\"FFFC70\"><B>Dragon Go Server</B></font></td>
        <td align=\"right\" width=\"50%\"><font color=\"FFFC70\"><B>Page created in " . 
              sprintf ("%0.5f", getmicrotime() - $time) . "&nbsp;s</B></font></td>
      </tr>
    </table>
  </BODY>
</HTML>
";

}

function make_session_code()
{
    mt_srand((double)microtime()*1000000); 
    return sprintf("%06X%06X%04X\0",mt_rand(0,16777215), mt_rand(0,16777215), mt_rand(0,65535));
}

function set_cookies($uid, $code)
{
    global $session_duration;
    global $SUB_PATH;

    setcookie ("handle", $uid, time()+$session_duration, "$SUB_PATH" );

    setcookie ("sessioncode", $code, time()+$session_duration, "$SUB_PATH" );
}



function is_logged_in($hdl, $scode, &$row)
{
    global $time;
    $time = getmicrotime();
    
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

    return true;
}

?>