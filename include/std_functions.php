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

$hostname_jump = true;  // ensure $HTTP_HOST is same as $HOSTNAME

$ActivityHalvingTime = 4 * 24 * 60; // [minutes] four days halving time;
$ActivityForHit = 1.0;
$ActivityForMove = 10.0;

$ActiveLevel1 = 10.0;
$ActiveLevel2 = 150.0;

$RowsPerPage = 50;
$MaxRowsPerPage = 70;

$has_sgf_alias = false;
// If using apache add this row to your virtual host to make this work:
// AliasMatch game([0-9]+)\.sgf /path/to/sgf.php

$gid_color='"#d50047"';
$bg_color='"#F7F5E3"';  // change in dragon.css too!

$menu_bg_color='"#0C41C9"';
$menu_fg_color='"#FFFC70"';

$max_links_in_main_menu=8;

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

define("ADMIN_TRANSLATORS",1);
define("ADMIN_FAQ",2);
define("ADMIN_FORUM",4);
define("ADMIN_ADMINS",8);
define("ADMIN_TIME",16);


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
   global $base_path, $is_down, $bg_color, $menu_bg_color, $menu_fg_color,
      $the_translator, $CHARACTER_ENCODINGS, $max_links_in_main_menu, $vertical, $base_path;

   if( $no_cache )
      disable_cache($last_modified_stamp);


   $base_path = ( is_base_dir() ? '' : '../' );

   ob_start("ob_gzhandler");

   $charenc = $the_translator->current_language->charset;
   header ('Content-Type: text/html; charset='.$charenc); // Character-encoding

   echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
  <HEAD>';

  echo "
  <meta http-equiv=\"Content-Type\" content=\"text/html; charset=$charenc\">\n";

//   echo '<script language="JavaScript">
// function popup(page)
// {
// w2=window.open(page, "w2", "");
// }
// </script>';


   echo '
    <TITLE> Dragon Go Server - ' . $title . '</TITLE>
    <LINK REL="shortcut icon" HREF="' . $base_path . 'images/favicon.ico" TYPE="image/x-icon">
    <LINK rel="stylesheet" type="text/css" media="screen" href="' . $base_path . 'dragon.css">';

   if( $style_string )
      echo "<STYLE TYPE=\"text/css\">\n" .$style_string . "\n</STYLE>";

   echo '
  </HEAD>
  <BODY bgcolor=' . $bg_color . '>

    <table width="100%" border=0 cellspacing=0 cellpadding=4 bgcolor=' . $menu_bg_color . '>
        <tr>
        <td width="50%"><A href="' . $base_path . "index.php\">" .
      "<B><font color=$menu_fg_color>Dragon Go Server</font></B></A></td>\n" .
      '<td align=right width="50%"><font color=' . $menu_fg_color .'><B>' .
      ( ($logged_in and !$is_down) ? T_("Logged in as") . ': ' . $player_row["Handle"]
        : T_("Not logged in") ) .
      " </B></font></td></tr></table>\n";

   $menu_array = array(
      '<b><font size="+1">' . T_('Status') . '</font></b>' => array('status.php',1,1),
      T_('Waiting room') => array('waiting_room.php',1,2),
      T_('User info') => array('userinfo.php',1,3),
      T_('Messages') => array('list_messages.php',2,1),
      T_('Send a message') => array('message.php?mode=NewMessage',2,2),
      T_('Invite') => array('message.php?mode=Invite',2,3),

      T_('Users') => array('users.php',3,1),
      T_('Games') => array('show_games.php?uid=all&finished=1',3,2),
      T_('Translate') => array('translate.php',3,3),
      T_('Forums') => array('forum/index.php',4,1),
      T_('Docs') => array('docs.php',4,2),
      T_('Site map') => array('site_map.php',4,3) );

   if( $player_row['MenuDirection'] == 'HORIZONTAL' )
      make_menu_horizontal($menu_array);
   else
   {
      make_menu_vertical($menu_array);
      $vertical = true;
   }

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
   global $time, $admin_level, $base_path, $vertical,
      $menu_bg_color, $menu_fg_color, $bg_color;

   echo "&nbsp;<p>\n";

   if( $vertical )
      echo '</td></tr><tr><td valign=bottom>';

   if( $menu_array )
      make_menu($menu_array);

   if( $vertical )
      echo "</td></tr></table>\n";


   if( count($menu_array) >= 3 )
      $span = ' colspan=' . (count($menu_array)-1);


   echo '<table width="100%" border=0 cellspacing=0 cellpadding=4 bgcolor=' . $menu_bg_color . '>
   <tr>
     <td' . $span . ' align="left" width="50%">
       <A href="' . $base_path . 'index.php"><font color=' . $menu_fg_color . '><B>Dragon Go Server</B></font></A></td>
        <td align="right" width="50%">';

   if( $admin_level & ADMIN_TIME )
      echo '
        <font size=-2 color=' . $menu_fg_color . '>' . T_('Page created in') .
        sprintf (' %0.2f', (getmicrotime() - $time)*1000) . '&nbsp;ms&nbsp;&nbsp;&nbsp;' .
         "</font>\n";

   if( $admin_level > 0 )
      echo '<B><a href="' . $base_path . 'admin.php"><font color=' . $menu_fg_color . '>' .
         T_('Admin') . '</a></B></font></td>';
   else
      echo '<A href="' . $base_path . 'index.php?logout=t"><font color=' . $menu_fg_color . '><B>' . T_("Logout") . '</B></font></A></td>';

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
   global $base_path, $bg_color,$max_links_in_main_menu,$menu_bg_color;

   $new_row= '<tr bgcolor=' . $bg_color . ' align="center">' . "\n";

   echo "<table width=\"100%\" border=0 cellspacing=0 cellpadding=4 bgcolor=$menu_bg_color>\n" . $new_row;

   if( count($menu_array) == 1 )
     $span = " colspan=2";

   $break_point_array = array();
   $nr_menu_links = count($menu_array);
   $menu_levels = ceil($nr_menu_links/$max_links_in_main_menu);
   $menu_width = round($nr_menu_links/$menu_levels+0.01);
   $even = ((count($menu_array) % $menu_width) == 0);
   $w = 100/$menu_width;

   $i = 1;
   while( $i < $menu_levels )
     {
       $break_point_array[] = $menu_width*$i;
       $i++;
     }

   $i = 0;
   foreach( $menu_array as $text => $link )
      {
        $i++;
         $cumw += $w;
         $width = round($cumw - $cumwidth);
         if( $i == count($menu_array) && !$even )
           $span = " colspan=2";
         echo "<td$span width=\"$width%\"><B><A href=\"$base_path$link\">$text</A></B></td>\n";
         $cumwidth += $width;
         if( in_array($i, $break_point_array) )
           {
             echo "</tr>" . $new_row;
             $cumw = 0;
             $cumwidth = 0;
           }
      }

   echo "</tr></table>\n";
}

function cmp1($a, $b)
{
   list($d,$a1,$a2) = $a;
   list($d,$b1,$b2) = $b;

   if ($a1 != $b1)
      return ( $a1 > $b1 ? 1 : -1 );

   if( $a2 == $b2 )
      return 0;
   else
      return ( $a2 > $b2 ? 1 : -1 );
}

function cmp2($a, $b)
{
   list($d,$a1,$a2) = $a;
   list($d,$b1,$b2) = $b;

   if ($a2 != $b2)
      return ( $a2 > $b2 ? 1 : -1 );

   if( $a1 == $b1 )
      return 0;
   else
      return ( $a1 > $b1 ? 1 : -1 );
}


function make_menu_horizontal($menu_array)
{
   global $base_path, $menu_bg_color, $bg_color;

   echo '<table width="100%" border=0 cellspacing=0 cellpadding=0 bgcolor="#F7F5FF"><tr>' . "\n";
   $cols = 4;
   $w = 100/($cols+1);

   echo '<td width="' .round($w). '%" rowspan=3>' .
      '<img src="' . $base_path . 'images/dragonlogo_bl.jpg" alt="Dragon"></td>' . "\n";

   $cumwidth = round($w);
   $cumw=$w;
   $i = 0;

   uasort($menu_array, "cmp2");

   foreach( $menu_array as $text => $tmp )
      {
         list($link,$t1,$t2) = $tmp;
         $cumw += $w;
         $width = round($cumw - $cumwidth);
         $cumwidth += $width;
         if( $i % $cols == 0 and $i > 0 )
         {
            if( $i==$cols )
               echo '<td width=100 align=right rowspan=3> ' .
                  '<img src="' . $base_path . 'images/dragonlogo_br.jpg" alt="Dragon"></td>' . "\n";
            echo '</tr><tr>' . "\n";
            $cumwidth = round($w);
            $cumw=$w;

         }
         $i++;


         echo "<td width=\"$width%\"><A href=\"$base_path$link\"><font color=black>$text</font></A></td>\n";
      }

   echo '</tr></table>' . "\n";

   echo '<table width="100%" cellpadding=0 cellspacing=0><tr><td height=1 bgcolor=' . $menu_bg_color .
      "><img src=\"images/dot.gif\" width=1 height=1 alt=\"\"></td></table>\n" . "
    <BR>\n";

}

function make_menu_vertical($menu_array)
{
   global $base_path, $menu_bg_color, $bg_color;

   echo '<table width="100%" border=0 cellspacing=0 cellpadding=5><tr><td valign=top rowspan=2>' . "\n";
   echo '<table border=0 cellspacing=0 cellpadding=1 bgcolor='.$menu_bg_color.'><tr><td>' . "\n";
   echo '<table border=0 cellspacing=0 cellpadding=5 bgcolor="#F7F5FF">' . "\n";
   echo '<tr><td align=center> <img src="' . $base_path . 'images/dragonlogo_bl.jpg" alt="Dragon">' . "\n";

   $i = 0;

   //  uasort($menu_array, "cmp1");
   echo '<tr><td align=left nowrap>' . "\n";

   foreach( $menu_array as $text => $tmp )
      {
         list($link,$t1,$t2) = $tmp;

         if( $i % 3 == 0 and $i > 0 )
             echo '<tr><td height=1><img src="' . $base_path . 'images/dot.gif" alt=""></td></tr><tr><td align=left nowrap>' . "\n";

         $i++;

         echo "<A href=\"$base_path$link\"><font color=black>$text</font></A><br>\n";
      }

   echo '<tr><td height=5><img height=1 src="' . $base_path . 'images/dot.gif" alt=""></td></tr></table>' . "\n";
   echo '</table></td><td width="100%" align=center valign=top><BR>' . "\n";
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

function help($topic)
{
   global $base_path;

   return '<a href="javascript:popup(\'' . $base_path . 'help.php?topic=' . $topic . '\')"><img border=0 align=top src="' . $base_path . 'images/help.png"></a>';
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

      $msg=eregi_replace("<(/?($html_code)( +[^>]*)?)>", "{anglstart}\\1{anglend}", $msg);
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

function is_base_dir()
{
   global $SUB_PATH, $PHP_SELF;

   return dirname($PHP_SELF) == $SUB_PATH;
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
         $str .= ' ' . sprintf( T_('with %s extra per move'), echo_time($Byotime) );
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
   global $time, $admin_level, $PHP_SELF, $HOSTNAME, $HTTP_HOST, $hostname_jump,
      $ActivityHalvingTime, $ActivityForHit, $NOW, $the_translator, $known_languages;

   $time = getmicrotime();
   $admin_level = 0;

   if( $hostname_jump and eregi_replace(":.*$","", $HTTP_HOST) != $HOSTNAME )
   {
      jump_to( "http://" . $HOSTNAME . $PHP_SELF, true );
   }

   if( !$hdl )
      return false;

   $result = @mysql_query( "SELECT *, UNIX_TIMESTAMP(Sessionexpire) AS Expire, " .
                           "Adminlevel+0 as admin_level " .
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



   if( $row["admin_level"] >= 1 )
      $admin_level = $row["admin_level"];

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

function add_link_page_link($link, $linkdesc, $extra = '', $active = true)
{
   if( $active )
      echo "<p><a href=\"$link\">$linkdesc</a>";
   else
      echo "<p><font color=gray>$linkdesc";

   if( !empty($extra) )
      echo " --- $extra";

   if( !$active )
      echo "</font>";

   echo "\n";
}

function nsq_addslashes( $str )
{
  return str_replace( array( "\\", "\"", "\$" ), array( "\\\\", "\\\"", "\\\$" ), $str );
}

function is_on_observe_list( $gid, $uid )
{
   $result = mysql_query("SELECT ID FROM Observers WHERE gid=$gid AND uid=$uid");
   return( mysql_num_rows($result) > 0 );
}

function toggle_observe_list( $gid, $uid )
{
   if( is_on_observe_list( $gid, $uid ) )
      mysql_query("DELETE FROM Observers WHERE gid=$gid AND uid=$uid LIMIT 1");
   else
      mysql_query("INSERT INTO Observers SET gid=$gid, uid=$uid");
}

function delete_all_observers( $gid, $notify, $Text='' )
{
   global $NOW;

   if( $notify )
   {
      $result = mysql_query("SELECT Players.ID AS pid " .
                            "FROM Observers,Players WHERE gid=$gid AND uid=Players.ID");

      $Subject = 'An observed game has finished';

      while( $row = mysql_fetch_array( $result ) )
      {
         mysql_query( 'INSERT INTO Messages SET ' .
                      'From_ID=' . $row['pid'] . ', ' .
                      'To_ID=' . $row['pid'] . ', ' .
                      "Time=FROM_UNIXTIME($NOW), " .
                      "Game_ID=$gid, Subject='$Subject', Text='$Text'" );
      }
   }

   mysql_query("DELETE FROM Observers WHERE gid=$gid");
}

?>
