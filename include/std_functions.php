<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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
require( "include/translator.php" );

if( @is_readable("timeadjust.php" ) )
   include( "timeadjust.php" );

if( !is_numeric($timeadjust) )
   $timeadjust = 0;

$NOW = time() + (int)$timeadjust;

$session_duration = 3600*12*61; // 1 month
$tick_frequency = 12; // ticks/hour
$date_fmt = 'Y-m-d H:i';
$date_fmt2 = 'Y-m-d&\n\b\s\p;H:i';

$is_down = false;

$hostname_jump = false;  // ensure $HTTP_HOST is same as $HOSTNAME

$ActivityHalvingTime = 4 * 24 * 60; // [minutes] four days halving time;
$ActivityForHit = 1.0;
$ActivityForMove = 10.0;

$ActiveLevel1 = 10.0;
$ActiveLevel2 = 150.0;

$RowsPerPage = 50;
$MaxRowsPerPage = 70;

$has_sgf_alias = false;

$gid_color='"#d50047"';
$bg_color='"#F7F5E3"';  // change in dragon.css too!

$menu_bg_color='"#0C41C9"';
$menu_fg_color='"#FFFC70"';

$table_head_color='"#CCCCCC"';
$table_row_color1='"#FFFFFF"';
$table_row_color2='"#E0E8ED"';
$table_row_color_del1='"#FFCFCF"';
$table_row_color_del2='"#F0B8BD"';

$h3_color='"#800000"';

$buttonfiles = array('button0.gif','button1.gif','button2.gif','button3.gif',
                     'button4.gif','button5.gif','button6.gif','button7.gif',
                     'button8.png','button9.png','button10.png');

$buttoncolors = array('white','white','white','white',
                      '#990000','white','white','white',
                      'white','white','white');

$button_max = 10;

$update_script = false; /* Should always be false false for include/std_functions.php */

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

define("LEFT",1);
define("UP",2);
define("RIGHT",4);
define("DOWN",8);
define("SMOOTH_EDGE",16);


// If no gettext
//if( !function_exists("_") )
//{
//   function _($string) { return $string; }
//}


function getmicrotime()
{
   list($usec, $sec) = explode(" ",microtime());
   return ((float)$usec + (float)$sec);
}

function unix_timestamp($date)
{
   $pattern = "/(19|20)(\d{2})-(\d{1,2})-(\d{1,2}) (\d{1,2}):(\d{1,2}):(\d{1,2})/";
   $m = preg_match ($pattern, $date, $matches);

   if(empty($date) or $date == "0000-00-00" or !$m)
   {
      return NULL;
   }

   list($whole, $y1, $y2, $month, $day, $hour, $minute, $second) = $matches;
   return mktime($hour,$minute,$second,$month,$day,$y1.$y2);
}

function disable_cache($stamp=NULL)
{
   global $NOW;
  // Force revalidation
   header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
   header ('Cache-Control: no-store, no-cache, must-revalidate, max_age=0'); // HTTP/1.1
   header ('Pragma: no-cache');                                              // HTTP/1.0
   if( !$stamp )
      header ('Last-Modified: ' . gmdate('D, d M Y H:i:s', $NOW) . ' GMT');    // Always modified
   else
      header ('Last-Modified: ' . gmdate('D, d M Y H:i:s',$stamp) . ' GMT');
}

function start_page( $title, $no_cache, $logged_in, &$player_row,
                     $style_string=NULL, $last_modified_stamp=NULL )
{
   global $HOSTBASE, $is_down, $bg_color, $menu_bg_color, $menu_fg_color,
      $the_translator, $CHARACTER_ENCODINGS;

   if( $no_cache )
      disable_cache($last_modified_stamp);


//     $use_gz = true;
//     if (eregi("NetCache|Hasd_proxy", $HTTP_SERVER_VARS['HTTP_VIA'])
//         || eregi("^Mozilla/4\.0[^ ]", $USER_AGENT))
//     {
//        $use_gz = false;
//     }
//     if ($use_gz)
//        ob_start("ob_gzhandler");
//     else
//        ob_start();
//     header("Vary: Accept-Encoding");

   ob_start("ob_gzhandler");

   $charenc = ( strcmp($the_translator->current_language,'C') == 0 ?
                'ISO-8859-1' :
                $CHARACTER_ENCODINGS[ $the_translator->current_language ] );
   header ('Content-Type: text/html; charset=$charenc'); // Character-encoding

   echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
  <HEAD>';

  echo "
  <meta http-equiv=\"Content-Type\" content=\"text/html; charset=$charenc\">\n";

//   if( $no_cache )
//       {
//  echo '
//      <META HTTP-EQUIV="Pragma" CONTENT="no-cache">
//      <META HTTP-EQUIV="Expires" CONTENT="0">
//  ';
//       }
   echo '
    <TITLE> Dragon Go Server - ' . $title . '</TITLE>
    <LINK rel="stylesheet" type="text/css" media="screen" href="dragon.css">';

   if( $style_string )
      echo "<STYLE TYPE=\"text/css\">\n" .$style_string . "\n</STYLE>";

      echo '
  </HEAD>
  <BODY bgcolor=' . $bg_color . '>

    <table width="100%" border=0 cellspacing=0 cellpadding=4 bgcolor=' . $menu_bg_color . '>
        <tr>
';

   $menu_array = array( T_('Status') => 'status.php',
                        T_('Messages') => 'list_messages.php',
                        T_('Tournaments') => 'tournaments.php',
                        T_('Invite') => 'message.php?mode=Invite',
                        T_('Users') => 'users.php',
                        T_('Forums') => 'forum/index.php');

   if( $logged_in && !empty($player_row['Translator']) )
      $menu_array[T_('Translate')] = 'translate.php';

   if( $logged_in && $player_row['Adminlevel'] >= 2 )
      $menu_array[T_('Admin')] = 'admin.php';

   $menu_array[T_('Docs')] = 'docs.php';


   echo '         <td colspan=' . (count($menu_array)-3) . ' width="50%">
          <A href="' . $HOSTBASE . '/index.php"><B><font color=' . $menu_fg_color .
      '>Dragon Go Server</font></B></A></td>
          <td colspan=3 align=right width="50%"><font color=' . $menu_fg_color .'><B>' .
      ( ($logged_in and !$is_down) ? T_("Logged in as") . ': ' . $player_row["Handle"]
        : T_("Not logged in") ) .
      " </B></font></td>\n";
   echo "     </tr>\n";

   make_menu($menu_array);

   echo "    </table>
    <BR>\n";

   if( $is_down )
      {
         echo "Sorry, dragon is down for maintenance at the moment, ".
            "please return in an hour or so.";
         end_page();
         exit;
      }
}

function end_page( $menu_array=NULL )
{
   global $time, $show_time, $HOSTBASE, $menu_bg_color, $menu_fg_color, $bg_color;

   echo "&nbsp;<p>\n";
   echo '<table width="100%" border=0 cellspacing=0 cellpadding=4 bgcolor=' . $menu_bg_color . ">\n";

   if( $menu_array )
      make_menu($menu_array);


   if( count($menu_array) >= 3 )
      $span = ' colspan=' . (count($menu_array)-1);

   echo '
      <tr>
        <td' . $span . ' align="left" width="50%">
          <A href="' . $HOSTBASE . '/index.php"><font color=' . $menu_fg_color . '><B>Dragon Go Server</B></font></A></td>
        <td align="right" width="50%">';
   if( $show_time )
      echo '
        <font color=' . $menu_fg_color . '><B>' . T_('Page created in') .
        sprintf (' %0.2f', (getmicrotime() - $time)*1000) . '&nbsp;ms' .
        '</B></font></td>';
   else
      echo '<A href="' . $HOSTBASE . '/index.php?logout=t"><font color=' . $menu_fg_color . '><B>' . T_("Logout") . '</B></font></A></td>';

   echo '
      </tr>
    </table>
  </BODY>
</HTML>
';

   ob_end_flush();
}

function make_menu($menu_array)
{
   global $HOSTBASE, $bg_color;

   $w = 100/count($menu_array);

   echo '<tr bgcolor=' . $bg_color . ' align="center">';

   if( count($menu_array) == 1 )
     $span = " colspan=2";

   foreach( $menu_array as $text => $link )
      {
         $cumw += $w;
         $width = round($cumw - $cumwidth);
         echo "<td$span width=$width%><B><A href=\"$HOSTBASE/$link\">$text</A></B></td>\n";
         $cumwidth += $width;
      }

   echo "</tr>\n";
}


function error($err, $debugmsg=NULL)
{
   global $handle, $PHP_SELF, $REMOTE_ADDR;

   disable_cache();

   $uri = "error.php?err=" . urlencode($err);
   $errorlog_query = "INSERT INTO Errorlog SET Handle='$handle', " .
      "Message='$err', IP='$REMOTE_ADDR'" ;

   $mysql_error = mysql_error();


   if( !empty($mysql_error) )
   {
      $uri .= "&mysqlerror=" . urlencode(mysql_error());
      $errorlog_query .= ", MysqlError='" . mysql_error() . "'";
   }

   if( !empty($debugmsg) )
   {
      $errorlog_query .= ", Debug='$debugmsg'";
   }

   @mysql_query( $errorlog_query );

   jump_to( $uri );
}

function jump_to($uri, $absolute=false)
{
   global $HOSTBASE;

   if( $absolute )
      header( "Location: " . $uri );
   else
      header( "Location: " . $HOSTBASE . '/' . $uri );

   exit;
}

function make_session_code()
{
   mt_srand((double)microtime()*1000000);
   return sprintf("%06X%06X%04X",mt_rand(0,16777215), mt_rand(0,16777215), mt_rand(0,65535));
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
   global $session_duration, $SUB_PATH, $NOW;

   if( $delete )
   {
      $time_diff=-3600;
      $uid = "";
      $code = "";
   }
   else
      $time_diff = $session_duration;

   setcookie ("handle", $uid, $NOW+$time_diff, "$SUB_PATH" );

   setcookie ("sessioncode", $code, $NOW+$time_diff, "$SUB_PATH" );
}


function make_html_safe(&$msg, $some_html=false)
{

//   $msg = str_replace('&', '&amp;', $msg);
//   $msg = str_replace('"', '&quot;', $msg);


   if( $some_html )
   {
      if( $some_html == 'game' )
      {
         // mark sgf comments
         $msg = eregi_replace("<c(omment)?>", "<font color=blue>\\0", $msg);
         $msg = eregi_replace("</c(omment)?>", "\\0</font>", $msg);
         $msg = preg_replace("'<h(idden)?>(.*?)</h(idden)?>'mis", "", $msg);
      }

      // make sure the <, > replacements: {anglstart}, {anglend} are removed from the string
      $msg = str_replace("{anglstart}", "<", $msg);
      $msg = str_replace("{anglend}", ">", $msg);


      // replace <, > with {anglstart}, {anglend} for legal html code

      $msg=eregi_replace("<(mailto:)([^ >\n\t]+)>",
                         "{anglstart}a href=\"\\1\\2\"{anglend}\\2{anglstart}/a{anglend}", $msg);
      $msg=eregi_replace("<((http|news|ftp)+://[^ >\n\t]+)>",
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
      return '-';

   $days = (int)($hours/15);
   if( $days > 0 )
   {
      if( $days == 1 )
         $str = '1&nbsp;' . T_('day');
      else
         $str = $days .'&nbsp;' . T_('days');
   }

   $h = $hours % 15;
   if( $h > 0 )
   {
      if( $days > 0 )
         $str .='&nbsp;' . T_('and') . '&nbsp;';

      if( $h == 1 )
         $str .= '1&nbsp;' . T_('hour');
      else
         $str .= $h . '&nbsp;' . T_('hours');
   }

   return $str;
}

function echo_time_limit($Maintime, $Byotype, $Byotime, $Byoperiods)
{
   $str = '';
   if ( $Maintime > 0 )
      $str = echo_time( $Maintime );

   if( $Byotime <= 0 )
         $str .= ' ' . T_('without byoyomi');
      else if( $Byotype == 'FIS' )
      {
         $str .= ' ' . sprintf( 'with %s extra per move', echo_time($Byotime) );
      }
      else
      {
         if ( $Maintime > 0 )
            $str .= ' + ';
         $str .= echo_time($Byotime);
         $str .= '/' . $Byoperiods . ' ';

         if( $Byotype == 'JAP' )
            $str .= T_('periods') . ' ' . T_('Japanese byoyomi');
         else
            $str .= T_('stones') . ' ' . T_('Canadian byoyomi');
      }

      return $str;
}

function time_convert_to_longer_unit(&$time, &$unit)
{
   if( $unit == 'hours' and $time % 15 == 0 )
   {
      $unit = 'days';
      $time /= 15;
   }

   if( $unit == 'days' and $time % 30 == 0 )
   {
      $unit = 'months';
      $time /= 30;
   }
}

// Makes url from a base page and some variable/value pairs
// if $sep is true a '?' or '&' is added
// Example:
// make_url('test.php', false, 'a', 1, 'b, 'foo')  gives
// 'test.php?a=1&b=foo'
function make_url($page, $sep)
{
   $url = $page;

   $args = func_num_args();

   if( $args % 2 == 1 )
      error("internal problem");

   $separator = '?';
   for( $i=2; $i<$args; $i+=2 )
   {
      $var = func_get_arg($i);
      $value = func_get_arg($i+1);

      if( $value )
      {
         $url .= $separator . $var . '=' . urlencode($value);
         $separator = '&';
      }
   }

   if( $sep )
      $url .= $separator;

   return $url;
}

function is_logged_in($hdl, $scode, &$row)
{
   global $time, $show_time, $HOSTBASE, $PHP_SELF, $HOSTNAME, $HTTP_HOST,
      $ActivityHalvingTime, $ActivityForHit, $NOW, $the_translator;

   $time = getmicrotime();
   $show_time = false;

   if( $hostname_jump and eregi_replace(":.*$","", $HTTP_HOST) != $HOSTNAME )
   {
      jump_to( "http://" . $HOSTNAME . $PHP_SELF, true );
   }

   if( !$hdl )
      return false;

   $result = @mysql_query( "SELECT *, UNIX_TIMESTAMP(Sessionexpire) AS Expire " .
                          "FROM Players WHERE Handle='$hdl'" );


   if( @mysql_num_rows($result) != 1 )
      return false;

   $row = mysql_fetch_array($result);


   if( $row["Sessioncode"] != $scode or $row["Expire"] < $NOW )
      return false;

   $query = "UPDATE Players SET " .
       "Hits=Hits+1, " .
       "Activity=Activity + $ActivityForHit, " .
       "Lastaccess=FROM_UNIXTIME($NOW), " .
       "Notify='NONE' " .
       "WHERE Handle='$hdl' LIMIT 1";

   $result = @mysql_query( $query );

   if( @mysql_affected_rows() != 1 )
      return false;



   if( $row["Adminlevel"] >= 3 )
      $show_time = true;

   if( !empty( $row["Timezone"] ) )
      putenv('TZ='.$row["Timezone"] );

   if( !is_null($row['Lang']) and
       strcmp($row['Handle'],'guest') != 0 )
     {
       $the_translator->change_language( $row['Lang'] );
     }

   return true;
}

function check_password( $password, $new_password, $given_password )
{
  global $handle;

  $given_password_encrypted = mysql_fetch_row( mysql_query( "SELECT PASSWORD ('$given_password')" ) );
  if( $password != $given_password_encrypted[0] )
    {
      // Check if there is a new password

      if( empty($new_password) or $new_password != $given_password_encrypted[0] )
        error("wrong_password");
    }

  if( !empty( $new_password ) )
    {
      mysql_query( 'UPDATE Players ' .
                   "SET Password='" . $given_password_encrypted[0] . "', " .
                   'Newpassword=NULL ' .
                   "WHERE Handle='$handle' LIMIT 1" );
    }
}

function write_to_file( $filename, $string_to_write )
{
  $fp = fopen( $filename, 'w' )
    or error( "couldnt_open_file" );

  fwrite( $fp, $string_to_write );
  fclose( $fp );

  @chmod( $filename, 0666 );
}

function array_value_to_key_and_value( $array )
{
  $new_array = array();
  foreach( $array as $value )
    $new_array[$value] = $value;

  return $new_array;
}

function add_link_page_link($link, $linkdesc, $extra = '')
{
  echo "<p><a href=\"$link\">$linkdesc</a>";
  if( !empty($extra) )
    echo " --- $extra";
  echo "\n";
}

function nsq_addslashes( $str )
{
  return str_replace( array( "\\", "\"", "\$" ), array( "\\\\", "\\\"", "\\\$" ), $str );
}

?>
