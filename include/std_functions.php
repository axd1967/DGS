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

// apd_set_pprof_trace();  for profiling

$TranslateGroups[] = "Common";

require_once( "include/quick_common.php" );
require_once( "include/connect2mysql.php" );

require_once( "include/time_functions.php" );
if (!isset($page_microtime))
{
   $page_microtime = getmicrotime();
   $admin_level = 0;
   //$base_path is relative to the URL, not to the current dir
   $base_path = ( is_base_dir() ? '' : '../' );
   //force to call std_functions.php from main dir
   $main_path = getcwd().'/';
}

require_once( "include/translation_functions.php" );

$hostname_jump = true;  // ensure $HTTP_HOST is same as $HOSTNAME

$ActivityHalvingTime = 4 * 24 * 60; // [minutes] four days halving time;
$ActivityForHit = 1.0;
$ActivityForMove = 10.0;

$ActiveLevel1 = 10.0;
$ActiveLevel2 = 150.0;

$RowsPerPage = 50;
$MaxRowsPerPage = $RowsPerPage+1;


$has_sgf_alias = false;
// If using apache add this row to your virtual host to make this work:
// AliasMatch game([0-9]+)\.sgf /path/to/sgf.php

$sgf_color='"#d50047"';
$bg_color='"#F7F5E3"';  // change in dragon.css too!

$menu_bg_color='"#0C41C9"';
$menu_fg_color='"#FFFC70"';

$max_links_in_main_menu=8;


//This car will be a part of a URI query. From RFC 2396 unreserved
// marks are "-" | "_" | "." | "!" | "~" | "*" | "'" | "(" | ")"
define('URI_ORDER_CHAR','-');

$ratingpng_min_interval = 2*31*24*3600;
$BEGINYEAR = 2001;
$BEGINMONTH = 8;


$table_head_color='"#CCCCCC"';
$table_row_color1='"#FFFFFF"';
$table_row_color2='"#E0E8ED"';
//$table_row_color_del1='"#FFCFCF"';
//$table_row_color_del2='"#F0B8BD"';

$h3_color='"#800000"';

$button_max = 10;
$buttonfiles = array('button0.gif','button1.gif','button2.gif','button3.gif',
                     'button4.gif','button5.gif','button6.gif','button7.gif',
                     'button8.png','button9.png','button10.png');
$buttoncolors = array('white','white','white','white',
                      '#990000','white','white','white',
                      'white','white','white');

$woodbgcolors = array(1=>'#e8c878','#e8b878','#e8a858', '#d8b878', '#b88848');


define('INFO_HTML', 'cell');
$cookie_pref_rows = array(
       'Stonesize', 'MenuDirection', 'Woodcolor', 'Boardcoords', 'Button',
       'NotesSmallHeight', 'NotesSmallWidth', 'NotesSmallMode',
       'NotesLargeHeight', 'NotesLargeWidth', 'NotesLargeMode', 'NotesCutoff',
       );

$vacation_min_days = 5;

define('DELETE_LIMIT', 10);

define('MAX_SEKI_MARK', 2);

define("NONE", 0); //i.e. DAME
define("BLACK", 1);
define("WHITE", 2);

define("OFFSET_TERRITORY", 0x04); //keep it a power of 2
define("DAME", OFFSET_TERRITORY+NONE);
define("BLACK_TERRITORY", OFFSET_TERRITORY+BLACK);
define("WHITE_TERRITORY", OFFSET_TERRITORY+WHITE);

define("OFFSET_MARKED", 0x08); //keep it a power of 2
define("MARKED_DAME", OFFSET_MARKED+NONE);
define("BLACK_DEAD", OFFSET_MARKED+BLACK);
define("WHITE_DEAD", OFFSET_MARKED+WHITE);

define("FLAG_NOCLICK", 0x10); //keep it a power of 2


/* Not yet used:
define("PASS_BLACK", 3);
define("PASS_WHITE", 4);
define("DONE_BLACK", 5);
define("DONE_WHITE", 6);
*/

define('SCORE_RESIGN', 1000);
define('SCORE_TIME', 2000);

//Database values:
define("MARKED_BY_WHITE", 7);
define("MARKED_BY_BLACK", 8);

//keep next constants powers of 2
define("KO", 0x01);

define("LEFT",0x01);
define("UP",0x02);
define("RIGHT",0x04);
define("DOWN",0x08);
define("SMOOTH_EDGE",0x10);
define("OVER",0x20);

define('MIN_BOARD_SIZE',5);
define('MAX_BOARD_SIZE',25);
define('MAX_KOMI_RANGE',200);
define('MAX_HANDICAP',20);


define("ADMIN_TRANSLATORS",0x01);
define("ADMIN_FAQ",0x02);
define("ADMIN_FORUM",0x04);
define("ADMIN_ADMINS",0x08);
define("ADMIN_TIME",0x10);
define("ADMIN_ADD_ADMIN",0x20);
define("ADMIN_PASSWORD",0x40);
define('ADMIN_DATABASE',0x80);


define("FOLDER_NONE", -1);
define("FOLDER_ALL_RECEIVED", 0);
//Valid folders must be > FOLDER_ALL_RECEIVED
define("FOLDER_MAIN", 1);
//define("FOLDER_NEW", 2); //moved in quick_common.php
define("FOLDER_REPLY", 3);
define("FOLDER_DELETED", 4);
define("FOLDER_SENT", 5);
define("USER_FOLDERS", 6);



function fnop( $a)
{
   return $a;
}

function start_page( $title, $no_cache, $logged_in, &$player_row,
                     $style_string=NULL, $last_modified_stamp=NULL )
{
   global $base_path, $is_down, $is_down_message, $bg_color, $menu_bg_color, $menu_fg_color,
      $encoding_used, $vertical;

   if( $no_cache )
      disable_cache($last_modified_stamp);

   ob_start("ob_gzhandler");

   if( empty($encoding_used) )
      $encoding_used = 'iso-8859-1';

   header('Content-Type: text/html; charset='.$encoding_used); // Character-encoding

   echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
  <HEAD>';

  echo "
  <meta http-equiv=\"Content-Type\" content=\"text/html; charset=$encoding_used\">\n";


   echo '
    <TITLE> Dragon Go Server - ' . $title . '</TITLE>
    <LINK REL="shortcut icon" HREF="' . $base_path . 'images/favicon.ico" TYPE="image/x-icon">
    <LINK rel="stylesheet" type="text/css" media="screen" href="' . $base_path . 'dragon.css">';

   if( $style_string )
      echo "<STYLE TYPE=\"text/css\">\n" .$style_string . "\n</STYLE>";

   echo '
  </HEAD>
  <BODY bgcolor=' . $bg_color . '>

    <script language="JavaScript" src="' . $base_path . 'js/goeditor.js" type="text/javascript"></script>
    <script language="JavaScript1.4" type="text/javascript"> version=1; </script>

    <table width="100%" border=0 cellspacing=0 cellpadding=4 bgcolor=' . $menu_bg_color . '>
        <tr>
        <td width="50%"><A href="' . $base_path . "index.php\">" .
      "<B><font color=$menu_fg_color>Dragon Go Server</font></B></A></td>\n" .
      '<td align=right width="50%"><font color=' . $menu_fg_color .'><B>' .
      ( ($logged_in and !$is_down) ? T_("Logged in as") . ': ' . $player_row["Handle"]
        : T_("Not logged in") ) .
      " </B></font></td></tr>\n</table>\n";

   $menu_array = array(
      '<b><font size="+1">' . T_('Status') . '</font></b>' => array('status.php" accesskey="s',1,1),
      T_('Waiting room') => array('waiting_room.php" accesskey="w',1,2),
      T_('User info') => array('userinfo.php" accesskey="p',1,3),

      T_('Messages') => array('list_messages.php" accesskey="b',2,1),
      T_('Send a message') => array('message.php?mode=NewMessage" accesskey="m',2,2),
      T_('Invite') => array('message.php?mode=Invite" accesskey="i',2,3),

      T_('Users') => array('users.php" accesskey="u',3,1),
      T_('Games') => array('show_games.php?uid=all&finished=1" accesskey="g',3,2),
      T_('Translate') => array('translate.php" accesskey="t',3,3),

      T_('Forums') => array('forum/index.php" accesskey="f',4,1),
      T_('FAQ') => array('faq.php" accesskey="q',4,2),
//      T_('Site map') => array('site_map.php',4,3),
      T_('Docs') => array('docs.php" accesskey="d',4,3)
      );

   if( $player_row['MenuDirection'] == 'HORIZONTAL' )
      make_menu_horizontal($menu_array);
   else
   {
      make_menu_vertical($menu_array);
      $vertical = true;
   }

   if( $is_down )
      {
         echo $is_down_message . '<p>';
         end_page();
         exit;
      }
}

function end_page( $menu_array=NULL )
{
   global $page_microtime, $admin_level, $base_path, $vertical,
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
   else
      $span = "";


   global $NOW, $date_fmt;
   echo '<table width="100%" border=0 cellspacing=0 cellpadding=4 bgcolor=' . $menu_bg_color . '>
   <tr>
     <td' . $span . ' align="left" width="30%">
       <A href="' . $base_path . 'index.php"><font color=' . $menu_fg_color . '><B>Dragon Go Server</B></font></A></td>
        <td align="center" width="40%"><font size=-1 color=' . $menu_fg_color . '>'
        . T_("Page time") . ' ' . date($date_fmt, $NOW)
        . '</font></td>
        <td align="right" width="30%">';

   if( $admin_level & ADMIN_TIME )
      echo '
        <font size=-2 color=' . $menu_fg_color . '>' . T_('Page created in') .
        sprintf (' %0.2f ms', (getmicrotime() - $page_microtime)*1000) .
         "</font>&nbsp;<br>\n";

   if( $admin_level & ~ADMIN_TIME )
      echo '<b><a href="' . $base_path . 'admin.php"><font color=' . $menu_fg_color . '>' .
         T_('Admin') . '</font></a></b>&nbsp;&nbsp;&nbsp;';

   echo '<A href="' . $base_path . 'index.php?logout=t" accesskey="x"><font color=' . $menu_fg_color . '><B>' . T_("Logout") . '</B></font></A></td>';

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
   global $base_path, $bg_color, $max_links_in_main_menu;

   $new_row= '<tr bgcolor=' . $bg_color . ' align="center">' . "\n";

   echo "<table width=\"100%\" border=0 cellspacing=0 cellpadding=4>\n" . $new_row;


   $nr_menu_links = count($menu_array);
   $menu_levels = ceil($nr_menu_links/$max_links_in_main_menu);
   $menu_width = ceil($nr_menu_links/$menu_levels);
   $remain = ($menu_levels*$menu_width) - $nr_menu_links +1;
   $w = 100/$menu_width;

   $cumwidth = $cumw = 0;
   $i = 0;
   foreach( $menu_array as $text => $link )
      {
         if( ($i % $menu_width)==0 && $i>0 )
           {
             echo "</tr>" . $new_row;
             $cumw = 0;
             $cumwidth = 0;
           }
         $i++;
         $cumw += $w;
         $width = round($cumw - $cumwidth);
/*
         if( $i == $nr_menu_links && $remain>1 )
           $span = " colspan=$remain";
         else
*/
           $span = "";

         $j = $i % 10;
         echo "<td$span width=\"$width%\"><B><A href=\"$base_path$link\" accesskey=\"$j\">$text</A></B></td>\n";
         $cumwidth += $width;
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

   echo "</tr>\n</table>\n";

   echo '<table width="100%" cellpadding=0 cellspacing=0><tr><td height=1 bgcolor=' . $menu_bg_color .
      "><img src=\"{$base_path}images/dot.gif\" width=1 height=1 alt=\"\"></td></tr></table>\n" . "
    <BR>\n";

}

function make_menu_vertical($menu_array)
{
   global $base_path, $menu_bg_color, $bg_color;

   //this <table><tr> is left open until page end
   echo '<table width="100%" border=0 cellspacing=0 cellpadding=5><tr><td valign=top rowspan=2>' . "\n";

   echo '<table border=0 cellspacing=0 cellpadding=1 bgcolor='.$menu_bg_color.'><tr><td>' . "\n";
   echo '<table border=0 cellspacing=0 cellpadding=5 bgcolor="#F7F5FF">' . "\n";
   echo '<tr><td align=center> <img src="' . $base_path . 'images/dragonlogo_bl.jpg" alt="Dragon">' . "\n";

   $i = 0;

   //  uasort($menu_array, "cmp1");
   echo '</td></tr><tr><td align=left nowrap>' . "\n";

   foreach( $menu_array as $text => $tmp )
      {
         list($link,$t1,$t2) = $tmp;

         if( $i % 3 == 0 and $i > 0 )
             echo '</td></tr><tr><td height=1><img src="' . $base_path . 'images/dot.gif" alt=""></td></tr><tr><td align=left nowrap>' . "\n";

         $i++;

         echo "<A href=\"$base_path$link\"><font color=black>$text</font></A><br>\n";
      }

   echo '</td></tr><tr><td height=5><img height=1 src="' . $base_path . 'images/dot.gif" alt=""></td></tr>'
         . "\n</table>\n</td></tr></table>\n";

   //this <td> is left open until page end
   echo '</td><td width="100%" align=center valign=top><BR>' . "\n";
}

/* Not used
function warn($debugmsg)
{
   $errorlog_query = "INSERT INTO Errorlog SET Handle='$handle', " .
      "Message='WARN:$debugmsg', IP='{$_SERVER['REMOTE_ADDR']}'" ;

   if( !empty($mysql_error) )
      $errorlog_query .= ", MysqlError='" . mysql_error() . "'";

   @mysql_query( $errorlog_query );
}
*/

/* Not used
function help($topic)
{
   global $base_path;

   return '<a href="javascript:popup(\'' . $base_path . 'help.php?topic=' . $topic . '\')"><img border=0 align=top src="' . $base_path . 'images/help.png"></a>';
}
*/

function sysmsg($msg)
{
   if( isset($msg) && trim($msg) )
      echo "\n<p><b><font color=\"green\">".make_html_safe($msg)."</font></b><hr>\n";
}



//must never allow quotes, ampersand, < and >
define('HANDLE_LEGAL_REGS', '-_+a-zA-Z0-9');
define('HANDLE_TAG_CHAR', '='); //not in HANDLE_LEGAL_REGS or in "<>"
define('PASSWORD_LEGAL_REGS', HANDLE_LEGAL_REGS.'.,:;?!%*');

function illegal_chars( $string, $punctuation=false )
{
   if( $punctuation )
      $regs = PASSWORD_LEGAL_REGS;
   else
      $regs = HANDLE_LEGAL_REGS;

   return !ereg( "^[$regs]+\$", $string);
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

function set_cookies($handl, $code, $delete=false)
{
   global $session_duration, $SUB_PATH, $NOW;

   if( $delete )
   {
      setcookie (COOKIE_PREFIX."handle", '', $NOW-3600, "$SUB_PATH" );
      setcookie (COOKIE_PREFIX."sessioncode", '', $NOW-3600, "$SUB_PATH" );
   }
   else
   {
      setcookie (COOKIE_PREFIX."handle", $handl, $NOW+$session_duration*5, "$SUB_PATH" );
      setcookie (COOKIE_PREFIX."sessioncode", $code, $NOW+$session_duration, "$SUB_PATH" );
   }
}

function get_cookie_prefs(&$player_row)
{
   global $cookie_prefs, $cookie_pref_rows;

   $cookie_prefs = unserialize(arg_stripslashes((string)
         @$_COOKIE[COOKIE_PREFIX."prefs{$player_row['ID']}"] ));
   if( !is_array( $cookie_prefs ) )
      $cookie_prefs = array();

   foreach( $cookie_prefs as $key => $value )
      {
         if( in_array($key, $cookie_pref_rows) )
            $player_row[$key] = $value;
      }
}

function set_cookie_prefs($id, $delete=false)
{
   global $cookie_prefs, $NOW, $SUB_PATH, $session_duration;

   if( $delete )
      setcookie(COOKIE_PREFIX."prefs$id", '', $NOW-3600, $SUB_PATH );
   else
      setcookie(COOKIE_PREFIX."prefs$id", serialize($cookie_prefs), $NOW+$session_duration*36, $SUB_PATH );
}

function add_line_breaks( $str)
{
   $str = trim($str);

   // Strip out carriage returns
  $str=preg_replace('%[\\x01-\\x09\\x0B-\\x20]*\\x0A%','<BR>', $str);

   // Handle collapsed vertical white spaces
  for( $i=0; $i<2; $i++)
  $str=preg_replace('%[\\x01-\\x20]*<(BR|P)[\\x01-\\x20]*/?\>[\\x01-\\x20]*<(BR|P)[\\x01-\\x20]*/?\>%i','<\\1>&nbsp;<\\2>', $str);

   return $str;
}

// Some regular allowed html tags. Keep them lower case.
// 'cell': tags that does not disturb a table cell.
// 'msg': tags allowed in messages
//Warning: </br> was historically used in end game messages. It remains in database.
 //keep a '|' at both end:
$html_code_closed['cell'] = '|b|i|u|';
$html_code_closed['msg'] = '|a|b|i|u|center|ul|ol|font|tt|pre|';
 //more? '|/li|/p|/br|/ *br';
$html_code['cell'] = 'b|i|u';
$html_code['msg'] = 'br'.$html_code_closed['msg'].'p|goban|li|/br';

define( 'ALLOWED_LT', '{anglstart}');
define( 'ALLOWED_GT', '{anglend}');
define( 'ALLOWED_QUOT', '{allowedquot}');
define( 'ALLOWED_APOS', '{allowedapos}');

/* Simple syntax check of element's attributes up to the next '>'.
   Check for quote mismatches.
   If so, simply add the missing quote at the (supposed?) end of tag.
*/
//attribut not string - allowed characters (HTML4.01): [-_:.a-zA-Z0-9] 
function parse_atbs_safe( &$trail, &$bad)
{
   $head = '';
   $quote = '';
   $seps = array(
         ""    => "\"'<>",
         "\""  => "\"",
         "'"   => "'",
      );

   while ( !$bad )
   {
      $i = strcspn($trail, $seps[$quote]);
      $c = substr($trail,$i,1);
      if ( $c=='' || $c=='<' )
      {
         $head.= substr($trail,0,$i);
         $trail = substr($trail,$i);
         $bad = 1;
         break;
      }
      else if ( $c=='>' )
      {
         $head.= substr($trail,0,$i);
         $trail = substr($trail,$i+1);
         break;
      }
      else if( $quote )
      {
         $quote.= substr($trail,0,$i+1);
         $quote = str_replace('"', ALLOWED_QUOT, $quote);
         $quote = str_replace("'", ALLOWED_APOS, $quote);
         $head = substr($head,0,-1) . $quote;
         $trail = substr($trail,$i+1);
         $quote = '';
      }
      else
      {
         $head.= substr($trail,0,$i+1);
         $trail = substr($trail,$i+1);
         $quote = $c;
      }
   }
   if ( $quote )
   {
      $head.= $quote;
      $bad = 1;
   }
   if ( $bad )
   {
      $head = str_replace(ALLOWED_QUOT, '"', $head);
      $head = str_replace(ALLOWED_APOS, "'", $head);
   }
   return $head;
}

/* Simple check of elements' attributes and inner text. Recursive.
   If an element is allowed and correctly closed,
    validate it by subtituing its '<' and '>' with ALLOWED_LT and ALLOWED_GT.
   Check up to the <$stop > tag (supposed to be the closing tag).
   If $stop=='', check up to the end of string $trail.
*/
function parse_tags_safe( &$trail, &$bad, &$html_code, &$html_code_closed, $stop)
{

   $before = '';
   $reg = "%^(.*?)<(" . ( $stop ? "$stop|" : "" ) . "$html_code)([\\x01-\\x20>].*)$%is";

   while ( preg_match($reg, $trail, $matches) )
   {
      $before.= $matches[1] ;
      $tag = strtolower($matches[2]) ; //Warning: same case as $html_code
         if( $tag == '/br' ) $tag = 'br' ; //historically used in end game messages.
      $trail = $matches[3] ;
      unset($matches);
      $head = $tag . parse_atbs_safe( $trail, $bad) ;
      if( $bad)
         return $before .'<'. $head .'>' ;
      $head = preg_replace('%[\\x01-\\x20]+%', ' ', $head);

      if( $stop == $tag )
         return $before .ALLOWED_LT. $head .ALLOWED_GT ;

      $to_be_closed = is_numeric(strpos($html_code_closed,'|'.$tag.'|')) ;
      if( $to_be_closed )
      {
         $inside = parse_tags_safe( $trail, $bad, $html_code, $html_code_closed, '/'.$tag) ;
         if( $bad)
            return $before .'<'. $head .'>'. $inside ;
      }
      else
      {
         $inside = '' ;
      }

      $before.= ALLOWED_LT. $head .ALLOWED_GT. $inside ;
   }
   if( $stop )
      $bad = 1;
   return $before ;
}

function parse_html_safe( $msg, $some_html)
{
 global $html_code, $html_code_closed;
   $bad = 0;
   $str = parse_tags_safe( $msg, $bad, 
               $html_code[$some_html], 
               $html_code_closed[$some_html], 
               '') ;
   $str.= $msg;
   return $str;
}

define('REF_LINK', 0x1);
define('REF_LINK_ALLOWED', 0x2);
define('REF_LINK_BLANK', 0x4);
//$some_html could be false, 'cell', 'game' or 'msg'==default
function make_html_safe( $msg, $some_html=false)
{

//   $msg = str_replace('&', '&amp;', $msg);
//   $msg = str_replace('"', '&quot;', $msg);


   if( $some_html )
   {
      // make sure the <, > replacements: ALLOWED_LT, ALLOWED_GT are removed from the string
      $msg = str_replace(ALLOWED_LT, '<', $msg);
      $msg = str_replace(ALLOWED_GT, '>', $msg);
      $msg = str_replace(ALLOWED_QUOT, '"', $msg);
      $msg = str_replace(ALLOWED_APOS, "'", $msg);

      // replace <, > with ALLOWED_LT, ALLOWED_GT for legal html code
      if( $some_html === 'game' )
      {
         // mark sgf comments
         $msg = trim(preg_replace("'<h(idden)? *>(.*?)</h(idden)? *>'is", "", $msg));

         $msg = eregi_replace("<c(omment)? *>",
                              ALLOWED_LT."font color=blue".ALLOWED_GT."\\0", $msg);
         $msg = eregi_replace("</c(omment)? *>",
                              "\\0".ALLOWED_LT."/font".ALLOWED_GT, $msg);
         $some_html = 'msg';
      }
      else if( $some_html !== 'cell' )
         $some_html = 'msg';

      $msg=eregi_replace("<(mailto:)([^ >\n\t]+)>",
                         ALLOWED_LT."a href=".ALLOWED_QUOT."\\1\\2".ALLOWED_QUOT.ALLOWED_GT.
                         "\\2".ALLOWED_LT."/a".ALLOWED_GT, $msg);
      $msg=eregi_replace("<((http:|https:|news:|ftp:)//[^ >\n\t]+)>",
                         ALLOWED_LT."a href=".ALLOWED_QUOT."\\1".ALLOWED_QUOT.ALLOWED_GT.
                         "\\1".ALLOWED_LT."/a".ALLOWED_GT, $msg);

      //link: <game gid[,move]> =>show game
      $msg=preg_replace("%<game(_)? +([0-9]+)( *, *([0-9]+))? *>%ise",
                        "game_reference(('\\1'=='_'?".REF_LINK_BLANK.":0)+".
                           REF_LINK_ALLOWED.",1,\\2,\\4+0)", $msg);
      //link: <user uid> or <user =uhandle> =>show user info
      //link: <send uid> or <send =uhandle> =>send a message to user
      $msg=preg_replace("%<(user|send)(_)? +(".HANDLE_TAG_CHAR."?[".HANDLE_LEGAL_REGS."]+) *>%ise",
                        "\\1_reference(('\\2'=='_'?".REF_LINK_BLANK.":0)+".
                           REF_LINK_ALLOWED.",1,0,'\\3')", $msg);

      //tag: <color col>...</color> =>translated to <font color="col">...</font>
      $msg=eregi_replace("<color +([#0-9a-zA-Z]+) *>",
                           ALLOWED_LT."font color=".ALLOWED_QUOT."\\1".ALLOWED_QUOT.ALLOWED_GT, $msg);
      $msg=eregi_replace("</color *>",
                           ALLOWED_LT."/font".ALLOWED_GT, $msg);

      // Regular allowed html tags
      $msg = parse_html_safe( $msg, $some_html) ;
   }

   // Filter out HTML code

   /*
      $msg = str_replace('&', '&amp;', $msg);
   $msg = eregi_replace('&amp;((#[0-9]+|[A-Z][0-9A-Z]*);)', '&\\1', $msg);
   */
   $msg = preg_replace('%&(?!(#[0-9]+|[A-Z][0-9A-Z]*);)%si', '&amp;', $msg);

   $msg = str_replace('<', '&lt;', $msg);
   $msg = str_replace('>', '&gt;', $msg);
   $msg = str_replace('"', '&quot;', $msg);
   $msg = str_replace("'", '&#039;', $msg);

   if( $some_html )
   {
      // change back to <, > from ALLOWED_LT, ALLOWED_GT
      $msg = str_replace(ALLOWED_LT, '<', $msg);
      $msg = str_replace(ALLOWED_GT, '>', $msg);
      $msg = str_replace(ALLOWED_QUOT, '"', $msg);
      $msg = str_replace(ALLOWED_APOS, "'", $msg);
   }

   $msg = add_line_breaks($msg);

   return $msg;
}

function textarea_safe( $msg, $charenc=false)
{
 global $encoding_used;
   if( !$charenc) $charenc = $encoding_used; //else 'iso-8859-1'
   $msg = @htmlspecialchars($msg, ENT_QUOTES, $charenc);
//No:   $msg = @htmlentities($msg, ENT_QUOTES, $charenc); //Too much entities for not iso-8859-1 languages
   return $msg;
}

function score2text($score, $verbose, $keep_english=false)
{
   $T_= ( $keep_english ? 'fnop' : 'T_' );

   if( !isset($score) )
      return "?";

   if( $score == 0 )
   {
      return ( $keep_english ? 'Draw' : ( $verbose ? T_('Jigo') : 'Jigo' ));
   }

   $color = ($verbose
             ? ( $score > 0 ? $T_('White') : $T_('Black') )
             : ( $score > 0 ? 'W' : 'B' ));

   if( abs($score) == SCORE_TIME )
   {
      return ( $verbose ? sprintf( $T_("%s wins on time"), $color) : $color . "+Time" );
   }
   else if( abs($score) == SCORE_RESIGN )
   {
      return ( $verbose ? sprintf( $T_("%s wins by resign"), $color) : $color . "+Resign" );
   }
   else
      return ( $verbose ? sprintf( $T_("%s wins by %.1f"), $color, abs($score))
               : $color . '+' . abs($score) );
}

// relative to the calling URL, not to the current dir
function is_base_dir()
{
   global $SUB_PATH;

   //return dirname($_SERVER['PHP_SELF']) == $SUB_PATH;
/* In case of a local server under Windows,
         dirname('/foo/bar') return '/foo'
     but dirname('/foo')     return '\\'
     and dirname('/')        return '\\'
   replace the previous line by this one:
 */
   return str_replace('\\','/',dirname($_SERVER['PHP_SELF'])) == $SUB_PATH;
   //relative to current directory (allow chdir())
   //return file_exists("include/std_functions.php");
}

function mod($a,$b)
{
   if ($a <= 0)
      return (int) ($b*(int)(-$a/$b+1)+$a) % $b;
   else
      return (int) $a % $b;
}

function swap(&$a, &$b)
{
   $tmp = $a;
   $a = $b;
   $b = $tmp;
}

// Makes url from a base page and an array of variable/value pairs
// if $sep is true a '?' or '&' is added
// Example:
// make_url('test.php', false, array('a'=> 1, 'b => 'foo')  gives
// 'test.php?a=1&b=foo'
function make_url($page, $sep, $array)
{
   $url = $page;

   $separator = '?';
   foreach( $array as $var=>$value )
   {
      if( !empty($value) )
      {
         $url .= $separator . $var . '=' . urlencode($value);
         $separator = '&';
      }
   }

   if( $sep )
      $url .= $separator;

   return $url;
}

function get_request_url()
{
 global $SUB_PATH;

   $url = @$_SERVER['REQUEST_URI'];
   $len = strlen($SUB_PATH);
   if ($len == 1)
      $url = substr($url,1);
   else if (!strcasecmp( $SUB_PATH, substr($url,0,$len) ))
      $url = substr($url,$len+1);
   return $url;
}

define('UHANDLE_NAM', 'user');
function get_request_user( &$uid, &$uhandle, $from_referer=false)
{
//Priorities: URI(id) > URI(handle) > REFERER(id) > REFERER(handle)
//Warning: + (an URI reserved char) must be substitued with %2B in 'handle'.
   $uid_nam = 'uid';
   $uid = @$_REQUEST[$uid_nam];
   $uhandle = '';  
   if( !($uid > 0) )
   {
      $uid = 0;
      $uhandle = @$_REQUEST[UHANDLE_NAM];
      if( !$uhandle && $from_referer && ($refer=@$_SERVER['HTTP_REFERER']) )
      {
//default user = last referenced user
//(ex: message.php from userinfo.php by menu link)
         if( eregi("[?&]$uid_nam=([0-9]+)", $refer, $result) )
           $uid = $result[1];
         if( !($uid > 0) )
         {
            $uid = 0;
            if( eregi("[?&]".UHANDLE_NAM."=([".HANDLE_LEGAL_REGS."]+)", $refer, $result) )
              $uhandle = $result[1];
         }
      }
   }
}

function who_is_logged( &$row)
{
   $handle = @$_COOKIE[COOKIE_PREFIX.'handle'];
   $sessioncode = @$_COOKIE[COOKIE_PREFIX.'sessioncode'];
   $curdir = getcwd();
   global $main_path;
   chdir( $main_path);
   $res = is_logged_in($handle, $sessioncode, $row);
   chdir( $curdir);
   return $res;
}

function is_logged_in($hdl, $scode, &$row) //must be called from main dir
{
   global $HOSTNAME, $hostname_jump, $page_microtime, $admin_level,
      $ActivityHalvingTime, $ActivityForHit, $NOW;


   if( $hostname_jump and eregi_replace(":.*$","", @$_SERVER['HTTP_HOST']) != $HOSTNAME )
   {
      jump_to( "http://" . $HOSTNAME . $_SERVER['PHP_SELF'], true );
   }

   if( !$hdl )
   {
      include_all_translate_groups(); //must be called from main dir
      return false;
   }

   $result = @mysql_query( "SELECT *, UNIX_TIMESTAMP(Sessionexpire) AS Expire, " .
                           "Adminlevel+0 as admin_level " .
                           "FROM Players WHERE Handle='$hdl'" );


   if( @mysql_num_rows($result) != 1 )
   {
      include_all_translate_groups(); //must be called from main dir
      return false;
   }

   $row = mysql_fetch_assoc($result);

   include_all_translate_groups($row); //must be called from main dir

   if( $row["Sessioncode"] != $scode or $row["Expire"] < $NOW )
      return false;

   $query = "UPDATE Players SET " .
      "Hits=Hits+1, " .
      "Activity=Activity + $ActivityForHit, " .
      "Lastaccess=FROM_UNIXTIME($NOW), " .
      "Notify='NONE'";

   $browser = substr($_SERVER['HTTP_USER_AGENT'], 0, 100);
   if( $row['Browser'] !== $browser )
      $query .= ", Browser='$browser'";

   if( $row['IP'] !== $_SERVER['REMOTE_ADDR'] )
      $query .= ", IP='{$_SERVER['REMOTE_ADDR']}'";

   $query .= " WHERE Handle='$hdl' LIMIT 1";

   $result = @mysql_query( $query );

   if( @mysql_affected_rows() != 1 )
      return false;


   if( $row["admin_level"] != 0 )
      $admin_level = $row["admin_level"];

   get_cookie_prefs($row);

   if( !empty( $row["Timezone"] ) )
      putenv('TZ='.$row["Timezone"] );

   return true;
}

function check_password( $userid, $password, $new_password, $given_password )
{
  $given_password_encrypted =
     mysql_fetch_row( mysql_query( "SELECT PASSWORD ('$given_password')" ) );

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
                   "WHERE Handle='$userid' LIMIT 1" );
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

function game_reference( $link, $safe, $gid, $move=0, $whitename=false, $blackname=false)
{
 global $base_path;

   $gid = (int)$gid;
   $legal = ( $gid<=0 ? 0 : 1 );
   if( ($whitename===false or $blackname===false) && $legal )
   {
     $tmp = 'SELECT black.Name as blackname, white.Name as whitename ' .
            'FROM Games, Players as white, Players as black ' .
            "WHERE Games.ID=$gid " .
            ' AND white.ID=Games.White_ID ' .
            ' AND black.ID=Games.Black_ID ' .
            'LIMIT 1' ;
     $result = mysql_query( $tmp );
     if( @mysql_num_rows($result) == 1 )
     {
       $tmp = mysql_fetch_assoc($result);
       if( $whitename===false )
         $whitename = $tmp['whitename'];
       if( $blackname===false )
         $blackname = $tmp['blackname'];
       $safe = true;
     }
     else
       $legal = 0;
   }
   $whitename = trim($whitename);
   $blackname = trim($blackname);
   if( $whitename )
      $whitename = "$whitename (W)" ;
   if( $blackname )
      $blackname = "$blackname (B)" ;
   if( !$whitename && !$blackname )
      $whitename = "Game#$gid" ;
   else if( $whitename && $blackname )
      $whitename = "$whitename vs. $blackname" ;
   else
      $whitename = "$whitename$blackname" ;
   if( $safe )
      $whitename = make_html_safe($whitename) ;
   if( $link && $legal )
   {
      $tmp = 'A href="'.$base_path."game.php?gid=$gid" .
                   ($move>0 ? "&move=$move" : "") . '"';
      if( $link & REF_LINK_BLANK )
        $tmp.= ' target="_blank"';
      if( $link & REF_LINK_ALLOWED )
      {
        $tmp = str_replace('"', ALLOWED_QUOT, $tmp);
        $whitename = ALLOWED_LT.$tmp.ALLOWED_GT.$whitename.ALLOWED_LT."/A".ALLOWED_GT ;
      }
      else
        $whitename = "<$tmp>$whitename</A>" ;
   }
   return $whitename ;
}

function send_reference( $link, $safe, $color, $player_id, $player_name=false, $player_handle=false)
{
// global $base_path;
   return user_reference( -$link
      , $safe, $color, $player_id, $player_name, $player_handle);
}

function user_reference( $link, $safe, $color, $player_id, $player_name=false, $player_handle=false)
{
 global $base_path;
   if( is_array($player_id) ) //i.e. $player_row
   {
      if( !$player_name )
         $player_name = $player_id['Name'];
      if( !$player_handle )
         $player_handle = $player_id['Handle'];
      $player_id = $player_id['ID'];
   }
   $legal = 1;
   if( is_string($player_id) && $player_id{0}==HANDLE_TAG_CHAR )
   {
      $player_id = substr($player_id,1);
      if( illegal_chars( $player_id) )
         $legal = 0;
      $byid = 0;
   }
   else
   {
      $player_id = (int)$player_id;
      if( $player_id<=0)
         $legal = 0;
      $byid = 1;
   }
   if( ($player_name===false or $player_handle===false) && $legal )
   {
     $tmp = 'SELECT Name, Handle ' .
            'FROM Players ' .
            "WHERE " . ( $byid ? 'ID' : 'Handle' ) . "='$player_id' " .
            'LIMIT 1' ;
     $result = mysql_query( $tmp );
     if( @mysql_num_rows($result) == 1 )
     {
       $tmp = mysql_fetch_assoc($result);
       if( $player_name===false )
         $player_name = $tmp['Name'];
       if( $player_handle===false )
         $player_handle = $tmp['Handle'];
       $safe = true;
     }
     else
       $legal = 0;
   }
   $player_name = trim($player_name);
   $player_handle = trim($player_handle);
   if( !$player_name )
      $player_name = "User#$player_id";
   if( $player_handle )
      $player_name.= " ($player_handle)" ;
   if( $safe )
      $player_name = make_html_safe($player_name) ;
   if( $color )
      $player_name = "<FONT color=\"$color\">$player_name</FONT>" ;
   if( $link && $legal )
   {
      if( $link<0 )
      {
         $link = -$link;
         $tmp = 'A href="'.$base_path."message.php?mode=NewMessage&";
      }
      else
      {
         $tmp = 'A href="'.$base_path."userinfo.php?";
      }
      $tmp.= ( $byid ? "uid=$player_id" 
                 : UHANDLE_NAM."=".str_replace('+','%2B',$player_id) ) . '"';
      if( $link & REF_LINK_BLANK )
        $tmp.= ' target="_blank"';
      if( $link & REF_LINK_ALLOWED )
      {
        $tmp = str_replace('"', ALLOWED_QUOT, $tmp);
        $player_name = ALLOWED_LT.$tmp.ALLOWED_GT.$player_name.ALLOWED_LT."/A".ALLOWED_GT ;
      }
      else
        $player_name = "<$tmp>$player_name</A>" ;
   }
   return $player_name ;
}

function is_on_observe_list( $gid, $uid )
{
   $result = mysql_query("SELECT ID FROM Observers WHERE gid=$gid AND uid=$uid");
   return( @mysql_num_rows($result) > 0 );
}

function toggle_observe_list( $gid, $uid )
{
   if( is_on_observe_list( $gid, $uid ) )
      mysql_query("DELETE FROM Observers WHERE gid=$gid AND uid=$uid LIMIT 1");
   else
      mysql_query("INSERT INTO Observers SET gid=$gid, uid=$uid");
}

//Text must be escaped by addslashes()
function delete_all_observers( $gid, $notify, $Text='' )
{
   global $NOW;

   if( $notify )
   {
      $result = mysql_query("SELECT Observers.uid AS pid " .
                            "FROM Observers WHERE gid=$gid");

      if( @mysql_num_rows($result) > 0 )
      {

         $Subject = 'An observed game has finished';

         mysql_query( "INSERT INTO Messages SET Time=FROM_UNIXTIME($NOW), " .
                      "Game_ID=$gid, Subject='$Subject', Text='$Text'" );

         if( mysql_affected_rows() == 1)
         {
            $mid = mysql_insert_id();

            while( $row = mysql_fetch_array( $result ) )
            {
               mysql_query("INSERT INTO MessageCorrespondents (uid,mid,Sender,Folder_nr) VALUES " .
                           "(" . $row['pid'] . ", $mid, 'N', ".FOLDER_NEW.")");
            }
         }

      }
   }

   mysql_query("DELETE FROM Observers WHERE gid=$gid");
}

function RGBA($r, $g, $b, $a=NULL)
{
   if ( $a === NULL )
      return sprintf("%02x%02x%02x", $r, $g, $b);
   else
      return sprintf("%02x%02x%02x%02x", $r, $g, $b, $a);
}

function blend_alpha($red, $green, $blue, $alpha, $bgred=0xf7, $bggreen=0xf5, $bgblue=0xe3)
{
   $a = $alpha/255;
   $r = $a*$red + (1-$a)*$bgred;
   $g = $a*$green + (1-$a)*$bggreen;
   $b = $a*$blue + (1-$a)*$bgblue;
   return RGBA( $r, $g, $b);
}

function split_RGBA($color, $alpha=NULL)
{
   return array(base_convert(substr($color, 0, 2), 16, 10),
                base_convert(substr($color, 2, 2), 16, 10),
                base_convert(substr($color, 4, 2), 16, 10),
                strlen($color)<7 ? $alpha :
                base_convert(substr($color, 6, 2), 16, 10),
         );
}

function blend_alpha_hex($color, $bgcolor="f7f5e3")
{
   list($r,$g,$b,$a)= split_RGBA($color, 0);
   list($br,$bg,$bb,$ba)= split_RGBA($bgcolor);
   return blend_alpha($r,$g,$b,$a,$br,$bg,$bb);
}

function limit($val, $minimum, $maximum, $default)
{
   if( is_string( $val) && is_numeric(strpos('hHxX#$',$val{0})) && substr($val,1)!='' )
      $val = base_convert( substr($val,1), 16, 10);

   if( !is_numeric($val) )
      return (isset($default) ? $default : $val );
   else if( is_numeric($minimum) and $val < $minimum )
      return $minimum;
   else if( is_numeric($maximum) and $val > $maximum )
      return $maximum;

   return $val;
}

function str_TD_class_button( &$browser)
{
   return "<td class=button width=94 align=center>";
}

function button_style()
{
   global $player_row, $buttoncolors, $buttonfiles, $button_max;

   $button_nr = $player_row["Button"];

   if ( !is_numeric($button_nr) or $button_nr < 0 or $button_nr > $button_max  )
      $button_nr = 0;

   return 'a.button { color : ' . $buttoncolors[$button_nr] .
      ';  font : bold 100% sans-serif;  text-decoration : none;  width : 94px; }
td.button { background-image : url(images/' . $buttonfiles[$button_nr] . ');' .
      'background-repeat : no-repeat;  background-position : center; }';

}

?>
