<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

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
   //std_functions.php must be called from the main dir
   $main_path = str_replace('\\', '/', getcwd()).'/';
   //$base_path is relative to the URL, not to the current dir
   $base_path = rel_base_dir();
   $printable = (bool)@$_REQUEST['printable'];
}

require_once( "include/translation_functions.php" );


// because of the cookies host, $hostname_jump = true is nearly mandatory
$hostname_jump = true;  // ensure $HTTP_HOST is same as $HOSTNAME

// If using apache add this row to your virtual host to make this work:
// AliasMatch game([0-9]+)\.sgf /path/to/sgf.php
$has_sgf_alias = false;


// when modified, run $HOSTBASE."change_password.php?guestpass=1"
// with ADMIN_PASSWORD privileges
if( $FRIENDLY_SHORT_NAME == 'DGS' )
   $GUESTPASS = 'guest'.'pass';
else
   $GUESTPASS = 'guest';


//----- { layout : change in dragon.css too!
$bg_color='"#F7F5E3"';

//$menu_fg_color='"#FFFC70"';
if( $FRIENDLY_SHORT_NAME == 'DGS' )
   $menu_bg_color='"#0C41C9"'; //live server
else
   $menu_bg_color='"#C9410C"'; //devel server

//{ N.B.: only used for folder transparency but CSS incompatible
$table_row_color1='"#FFFFFF"';
$table_row_color2='"#E0E8ED"';
//}
//$table_head_color='"#CCCCCC"';
//$table_row_color_del1='"#FFCFCF"';
//$table_row_color_del2='"#F0B8BD"';

$h3_color='"#800000"';

$sgf_color='"#d50047"';

//----- } layout : change in dragon.css too!


$max_links_in_main_menu=5;

$RowsPerPage = 50;
$MaxRowsPerPage = $RowsPerPage+1;

$SearchPostsPerPage = 20;
$MaxSearchPostsPerPage = $SearchPostsPerPage+1;

$ActivityHalvingTime = 4 * 24 * 60; // [minutes] four days halving time;
$ActivityForHit = 1.0;
$ActivityForMove = 10.0;

$ActiveLevel1 = 10.0;
$ActiveLevel2 = 150.0;


//This car will be a part of a URI query. From RFC 2396 unreserved
// marks are "-" | "_" | "." | "!" | "~" | "*" | "'" | "(" | ")"
define('URI_ORDER_CHAR','-');


define('MAX_START_RATING', 2600); //6 dan
define('MIN_RATING', -900); //30 kyu

$ratingpng_min_interval = 2*31*24*3600;
$BEGINYEAR = 2001;
$BEGINMONTH = 8;


$button_max = 11;
$button_width = 96;
$buttonfiles = array('button0.gif','button1.gif','button2.gif','button3.gif',
                     'button4.gif','button5.gif','button6.gif','button7.gif',
                     'button8.png','button9.png','button10.png','button10.png');
$buttoncolors = array('white','white','white','white',
                      '#990000','white','white','white',
                      'white','white','white','black');

$woodbgcolors = array(1=>'#e8c878','#e8b878','#e8a858', '#d8b878', '#b88848');

$cookie_pref_rows = array(
       'SkinName',
       'MenuDirection', 'Button',
       'Stonesize', 'Woodcolor', 'Boardcoords',
       'NotesLargeHeight', 'NotesLargeWidth', 'NotesLargeMode', 'NotesCutoff',
       'NotesSmallHeight', 'NotesSmallWidth', 'NotesSmallMode',
       'MoveNumbers', 'MoveModulo',
       );

$vacation_min_days = 2;

define('INFO_HTML', 'cell');

define('DELETE_LIMIT', 10);

define('MAX_SEKI_MARK', 2);

//-----
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
//-----

//Database values:
define("MARKED_BY_WHITE", 7);
define("MARKED_BY_BLACK", 8);

define('POSX_PASS', -1);
define('POSX_SCORE', -2);
define('POSX_RESIGN', -3);
define('POSX_TIME', -4);

define('SCORE_RESIGN', 1000);
define('SCORE_TIME', 2000);

define('STONE_VALUE',13); // 2 * conventional komi
define('MIN_BOARD_SIZE',5);
define('MAX_BOARD_SIZE',25);
define('MAX_KOMI_RANGE',200);
define('MAX_HANDICAP',21);
// b0=standard placement, b1=with black validation skip, b2=all placements
// both b1 and b2 set is not fully checked (error if unfinished pattern)
define('ENA_STDHANDICAP',0x3);
define('ENA_MOVENUMBERS',1);
define('MAX_MOVENUMBERS', 500);


//keep next constants powers of 2
define("KO", 0x01);

//-----
define('COORD_LEFT',0x01);
define('COORD_UP',0x02);
define('COORD_RIGHT',0x04);
define('COORD_DOWN',0x08);
define('SMOOTH_EDGE',0x10);
define('COORD_OVER',0x20);
define('COORD_SGFOVER',0x40);
define('NUMBER_OVER',0x80);
//-----


//-----
define("ADMIN_TRANSLATORS",0x01);
define("ADMIN_FAQ",0x02);
define("ADMIN_FORUM",0x04);
define("ADMIN_ADMINS",0x08);
define("ADMIN_TIME",0x10);
define("ADMIN_ADD_ADMIN",0x20);
define("ADMIN_PASSWORD",0x40);
define('ADMIN_DATABASE',0x80);
//-----


//-----
define("FOLDER_NONE", -1);
define("FOLDER_ALL_RECEIVED", 0);
//Valid folders must be > FOLDER_ALL_RECEIVED
define("FOLDER_MAIN", 1);
//define("FOLDER_NEW", 2); //moved in quick_common.php
define("FOLDER_REPLY", 3);
define("FOLDER_DELETED", 4);
define("FOLDER_SENT", 5);
define("USER_FOLDERS", 6);
//-----


function fnop( $a)
{
   return $a;
}

function start_html( $title, $no_cache, $skinname=NULL, $style_string=NULL, $last_modified_stamp=NULL )
{
   global $base_path, $encoding_used, $printable, $FRIENDLY_SHORT_NAME;

   if( $no_cache )
      disable_cache($last_modified_stamp);

   ob_start("ob_gzhandler");

   if( empty($encoding_used) )
      $encoding_used = LANG_DEF_CHARSET;

   header('Content-Type: text/html; charset='.$encoding_used); // Character-encoding

   echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">'
      . "\n<HTML>\n<HEAD>";

  echo "\n <meta http-equiv=\"Content-Type\" content=\"text/html; charset=$encoding_used\">";

   echo "\n <TITLE>$FRIENDLY_SHORT_NAME - $title </TITLE>"
      . "\n <LINK REL=\"shortcut icon\" HREF=\"{$base_path}images/favicon.ico\" TYPE=\"image/x-icon\">";

   if( !isset($skinname) or !$skinname )
      $skinname = 'dragon';
   if( !file_exists("{$base_path}skins/$skinname/screen.css") )
      $skinname = 'dragon';
   echo "\n <link rel=\"stylesheet\" type=\"text/css\" media=\"screen\" href=\"{$base_path}skins/$skinname/screen.css\">";
   if( !file_exists("{$base_path}skins/$skinname/print.css") )
      $skinname = 'dragon';
   echo "\n <link rel=\"stylesheet\" type=\"text/css\" media=\"print\" href=\"{$base_path}skins/$skinname/print.css\">";

   global $SUB_PATH;
   switch( substr( @$_SERVER['PHP_SELF'], strlen($SUB_PATH)) )
   {
      case 'status.php':
         // RSS Autodiscovery:
         echo "\n <link rel=\"alternate\" type=\"application/rss+xml\"" 
             ." title=\"$FRIENDLY_SHORT_NAME Status RSS Feed\" href=\"/rss/status.php\">";
      break;
   }

   if( $style_string )
      echo "\n <STYLE TYPE=\"text/css\">\n" .$style_string . "\n </STYLE>";

   echo "\n</HEAD>\n<BODY id=\"$FRIENDLY_SHORT_NAME\">\n";
}

function start_page( $title, $no_cache, $logged_in, &$player_row,
                     $style_string=NULL, $last_modified_stamp=NULL )
{
   global $base_path, $is_down, $is_down_message, $printable,
      $bg_color, $FRIENDLY_LONG_NAME, $HOSTBASE;

   if( $is_down && $logged_in )
   {
      //$is_down_allowed = array('ejlo','rodival');
      if( isset($is_down_allowed) && is_array($is_down_allowed)
         && in_array( $player_row['Handle'], $is_down_allowed) )
      {
         $is_down = false;
      }
      unset( $is_down_allowed);
   }

   start_html( $title, $no_cache, @$player_row['SkinName'], $style_string, $last_modified_stamp);

//    echo "\n<script language=\"JavaScript\" type=\"text/javascript\" src=\"{$base_path}js/goeditor.js\"></script>";
//    echo "\n<script language=\"JavaScript1.4\" type=\"text/javascript\"> version=1; </script>";

   if( !$printable )
   {
   echo "\n\n<table id=\"page_head\" width=\"100%\" border=0 cellspacing=0 cellpadding=4>"
      . "\n <tr>"
      . "\n  <td align=left><A id=\"home_id\" href=\"{$HOSTBASE}index.php\">"
        . "$FRIENDLY_LONG_NAME</A></td>";
   echo "\n  <td align=right>";

   if ($logged_in and !$is_down)
      echo T_("Logged in as") . ": <A id=\"logged_id\" href=\"{$base_path}status.php\">"
           . $player_row["Handle"] . "</A>";
   else
      echo T_("Not logged in");

   echo "</td>"
      . "\n </tr>\n</table>\n";
   }

   if( !$printable )
   {
      $menu_array = array(
         T_('Status') => array(1,1, 'status.php',array('accesskey'=>'s','class'=>'strong')),
         T_('Waiting room') => array(1,2, 'waiting_room.php',array('accesskey'=>'r')),
         T_('User info') => array(1,3, 'userinfo.php',array('accesskey'=>'p')),

         T_('Messages') => array(2,1, 'list_messages.php',array('accesskey'=>'b')),
         T_('Send a message') => array(2,2, 'message.php?mode=NewMessage',array('accesskey'=>'m')),
         T_('Invite') => array(2,3, 'message.php?mode=Invite',array('accesskey'=>'i')),

         T_('Users') => array(3,1, 'users.php',array('accesskey'=>'u')),
         T_('Games') => array(3,2, 'show_games.php?uid=all',array('accesskey'=>'g')),
         T_('Translate') => array(3,3, 'translate.php',array('accesskey'=>'t')),

         T_('Forums') => array(4,1, 'forum/index.php',array('accesskey'=>'f')),
         T_('FAQ') => array(4,2, 'faq.php',array('accesskey'=>'q')),
         //T_('Site map') => array(4,3, 'site_map.php'),
         T_('Docs') => array(4,3, 'docs.php',array('accesskey'=>'d')),
      );

      $tools_array = array();
      global $SUB_PATH, $FRIENDLY_SHORT_NAME;
      switch( substr( @$_SERVER['PHP_SELF'], strlen($SUB_PATH)) )
      {
         case 'status.php':
            $tools_array['rss/status.php'] = 
               array( $base_path.'images/rss-icon.png', 
                      'RSS',
                      $FRIENDLY_SHORT_NAME . ' ' . T_("Status RSS Feed")
                     );
         break;
      }
   }


   if( !$logged_in or $is_down or $printable )
   {
      echo "\n<table width=\"100%\" border=0 cellspacing=0 cellpadding=5>"
         . "\n <tr>";
      $br = ( $printable ? '' : '<br>' );
   }
   else if( $player_row['MenuDirection'] == 'HORIZONTAL' )
   {
      make_menu_horizontal($menu_array);
      make_tools( $tools_array, 0);
      echo "\n<table width=\"100%\" border=0 cellspacing=0 cellpadding=5>"
         . "\n <tr>";
      $br = '';
   }
   else
   {
      echo "\n<table width=\"100%\" border=0 cellspacing=0 cellpadding=5>"
         . "\n <tr>"
         . "\n  <td valign=top rowspan=2>\n";
      make_menu_vertical($menu_array);
      make_tools( $tools_array, 4);
      echo "\n  </td>";
      $br = '<br>';
   }
   //this <table><tr><td> is left open until page end
   echo "\n  <td id=\"page_body\" width=\"100%\" align=center valign=top>$br\n\n";

   sysmsg(get_request_arg('sysmsg'));

   if( $is_down )
   {
      echo $is_down_message . '<p></p>';
      end_page();
      exit;
   }
}

function end_page( $menu_array=NULL )
{
   global $page_microtime, $admin_level, $base_path, $printable,
      $bg_color;

   echo "\n\n&nbsp;<br>";

   echo "\n  </td>";

   if( $menu_array && !$printable )
   {
      echo "\n </tr><tr>"
         . "\n  <td valign=bottom>";
      make_menu($menu_array);
      echo "\n  </td>";
   }

   //close the <table><tr><td> left open since page start
   echo "\n </tr>\n</table>\n";

   global $NOW, $date_fmt, $FRIENDLY_LONG_NAME;
   echo "\n<table id=\"page_foot\" width=\"100%\" border=0 cellspacing=0 cellpadding=4>"
      . "\n <tr>"
      . "\n  <td align=left><A href=\"{$base_path}index.php\">"
        . "$FRIENDLY_LONG_NAME</A></td>";

   echo "\n  <td class=\"page_time\" align=center>"
        . T_("Page time") . ' <span id="page_time">' . date($date_fmt, $NOW)
        . "</span>";

   if( $admin_level & ADMIN_TIME && !$printable )
      echo "<br><span style=\"font-size:84%;\">"
        . T_('Page created in') . ' <span id="page_lapse">'
        . sprintf (' %0.2f ms', (getmicrotime() - $page_microtime)*1000)
        . "</span></span>";

   echo "</td>";

 if( !$printable )
 {

   echo "\n  <td align=right>";

   if( $admin_level & ~(ADMIN_TIME) && !$printable )
      echo "<a href=\"{$base_path}admin.php\">"
        . T_('Admin') . "</a>&nbsp;&nbsp;&nbsp;";

   echo anchor( $base_path."index.php?logout=t"
              , T_("Logout")
              , ''
              , array( 'accesskey' => 'o' )
              );

   echo "</td>"
      . "\n </tr>"
      . "\n</table>";

   // Start of host line
   echo "\n<table width=\"100%\" border=0 cellspacing=0 cellpadding=4 bgcolor=$bg_color>"
      . "\n <tr>";
 }

global $HOSTNAME;

   if( $HOSTNAME == "dragongoserver.sourceforge.net" ) //for devel server
      $hostlink= '<A href="http://sourceforge.net" target="_blank"><IMG src="http://sourceforge.net/sflogo.php?group_id=29933&amp;type=1" alt="SourceForge.net Logo" width=88 height=31 border=0 align=middle></A>';
   else //for devel server
      $hostlink= '<a href="http://www.samurajdata.se" target="_blank"><img src="'.$base_path.'images/samurajlogo.gif" alt="Samuraj Logo" width=160 height=20 border=0 align=middle></a>';

   echo "\n  <td valign=top align=right><font size=-1>Hosted by&nbsp;&nbsp;</font>$hostlink</td>";

   echo "\n </tr>"
      . "\n</table>";

   end_html();
}

function end_html()
{
   echo "\n</BODY>\n</HTML>";
   ob_end_flush();
}

function make_menu($menu_array)
{
   global $base_path, $max_links_in_main_menu;

   echo "\n\n<table id=\"page_links\" class=links>\n <tr>";

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
         echo "\n </tr>\n <tr>";
         $cumw = 0;
         $cumwidth = 0;
      }
      $i++;
      $cumw += $w;
      $width = round($cumw - $cumwidth);

      echo "\n  <td width=\"$width%\">"
         . anchor( "$base_path$link"
                 , $text
                 , ''
                 , array( 'accesskey' => $i % 10 )
                 )
         . '</td>';

      $cumwidth += $width;
   }

   echo "\n </tr>\n</table>\n";
}

function cmp1($a, $b)
{
   list($a1,$a2,$d) = $a;
   list($b1,$b2,$d) = $b;

   if ($a1 != $b1)
      return ( $a1 > $b1 ? 1 : -1 );

   if( $a2 == $b2 )
      return 0;
   else
      return ( $a2 > $b2 ? 1 : -1 );
}

function cmp2($a, $b)
{
   list($a1,$a2,$d) = $a;
   list($b1,$b2,$d) = $b;

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

   //table for bottom line
   echo "\n<table class=notprintable width=\"100%\" border=0 cellspacing=0 cellpadding=0 bgcolor=$menu_bg_color>"
      . "\n <tr>"
      . "\n  <td>";

   echo "\n<table id=\"page_menu\" width=\"100%\" border=0 cellspacing=0 cellpadding=4 bgcolor=\"#F7F5FF\">"
      . "\n <tr>";

   $cols = 4;
   $b = $w = 100/($cols+2); //two icons
   $w = floor($w); $b = 100-$w*($cols+1);

   $i = 0;
   uasort($menu_array, "cmp2");
   foreach( $menu_array as $text => $tmp )
   {
      if( $i % $cols == 0 )
      {
         if( $i<=$cols )
         {
            if( $i==0 )
            {
               $t1 = round($b);
               $t1 = " width=\"$t1%\"";
               $t2 = 'dragonlogo_bl.jpg';
               $width = "*";
            }
            else 
            {
               $t1 = 100-$cumwidth;
               $t1 = " width=\"$t1%\" align=right";
               $t2 = 'dragonlogo_br.jpg';
               $width = "";
            }
            echo "\n  <td$t1 rowspan=3>"
               . "<img src=\"{$base_path}images/$t2\" alt=\"Dragon\"></td>";
         }
         if( $i>0 )
            echo "\n </tr><tr>";
         $cumw = $b;
         $cumwidth = round($cumw);
      }
      $i++;

      $attbs= '';
      @list($t1,$t2,$link,$attbs) = $tmp;
      if( $width )
      {
         $cumw += $w;
         $width = round($cumw - $cumwidth);
         $cumwidth += $width;
         $width = " width=\"$width%\"";
      }

      echo "\n  <td$width>";
      echo anchor( $base_path.$link, $text, '', $attbs);
      echo "</td>";
   }

   echo "\n </tr>\n</table>\n";

   //table for bottom line
   echo "\n  </td>"
      . "\n </tr><tr>"
      . "\n  <td height=1><img src=\"{$base_path}images/dot.gif\" width=1 height=1 alt=\"\"></td>"
      . "\n </tr>\n</table>\n";
}

function make_menu_vertical($menu_array)
{
   global $base_path, $menu_bg_color, $bg_color;

   //table for border line
   echo "\n<table class=notprintable border=0 cellspacing=0 cellpadding=1 bgcolor=$menu_bg_color>"
      . "\n <tr>"
      . "\n  <td>";

   echo "\n<table id=\"page_menu\" border=0 cellspacing=0 cellpadding=4 bgcolor=\"#F7F5FF\">"
      . "\n <tr>";

   echo "\n  <td align=center><img src=\"{$base_path}images/dragonlogo_bl.jpg\" alt=\"Dragon\"></td>"
      . "\n </tr><tr>"
      . "\n  <td align=left nowrap>";

   $i = 0;
   //  uasort($menu_array, "cmp1");
   foreach( $menu_array as $text => $tmp )
   {
      if( $i % 3 == 0 and $i > 0 )
          echo "</td>"
             . "\n </tr><tr>"
             . "\n  <td height=1><img height=1 src=\"{$base_path}images/dot.gif\" alt=\"\"></td>"
             . "\n </tr><tr>"
             . "\n  <td align=left nowrap>";
      $i++;

      $attbs= '';
      @list($t1,$t2,$link,$attbs) = $tmp;
      echo anchor( $base_path.$link, $text, '', $attbs);
      echo "<br>";
   }

   echo "</td>"
      . "\n </tr><tr>"
      . "\n  <td height=1><img height=1 src=\"{$base_path}images/dot.gif\" alt=\"\"></td>"
      . "\n </tr>"
      . "\n</table>";

   //table for border line
   echo "\n  </td>"
      . "\n </tr>\n</table>\n";
}

function make_tools( $array, $width=0)
{
   if( !is_array($array) or count($array)==0 )
      return;
   echo "<table class=notprintable id=\"page_tools\" border=0 cellspacing=0 cellpadding=6>\n<tr>\n";
   $i= 0;
   foreach( $array as $lnk => $sub )
   {
      list( $src, $alt, $tit) = $sub;
      if( $width>0 && $i==$width )
      {
         echo "</tr><tr>\n";
         $i= 1;
      }
      else
         $i++;
      echo '<td>'.anchor( $lnk, image( $src, $alt, $tit))."</td>\n";
   }
   echo "</tr>\n</table>\n";
}


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
      echo "\n<p class=sysmsg>".make_html_safe($msg)."</p><hr>\n";
}



//must never allow quotes, ampersand, < , > and URI reserved chars
define('HANDLE_LEGAL_REGS', '-_a-zA-Z0-9');
define('HANDLE_TAG_CHAR', '='); //not in: HANDLE_LEGAL_REGS or < or >
define('PASSWORD_LEGAL_REGS', HANDLE_LEGAL_REGS.'+.,:;?!%*');

function illegal_chars( $string, $punctuation=false )
{
   if( $punctuation )
      $regs = PASSWORD_LEGAL_REGS;
   else
      $regs = 'a-zA-Z]['.HANDLE_LEGAL_REGS; //begins with a letter

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
   $return = '';
   mt_srand((double)microtime()*1000000);
   for( $i=0; $i<8; $i++ )
      $return .= random_letter();

   return $return;
}

function verify_email( $email, $debugmsg='')
{
   $regexp = "^([-_a-z0-9]+)(\.[-_a-z0-9]+)*@([-a-z0-9]+)(\.[-a-z0-9]+)*(\.[a-z]{2,4})$";
   $res= eregi($regexp, $email);
   if( $debugmsg && !$res )
      error('bad_mail_address', "$debugmsg=$email");
   return $res;
}


function safe_setcookie($name, $value='', $rel_expire=-3600)
//should be: ($name, $value, $expire, $path, $domain, $secure)
{
   global $SUB_PATH, $NOW;

   if( COOKIE_OLD_COMPATIBILITY )
   {
      setcookie( $name, '', $NOW-3600, $SUB_PATH);
   }

   $name= COOKIE_PREFIX.$name;

   //remove duplicated cookies sometime occuring with some browsers
   //global $HTTP_SERVER_VARS; old == new $_SERVER
   if( $tmp= @$_SERVER['HTTP_COOKIE'] )
      $n= preg_match_all(';'.$name.'[\\x01-\\x20]*=;i', $tmp, $dummy);
   else
      $n= 0;

   while ($n>1) {
      setcookie( $name, '', $NOW-3600, $SUB_PATH);
      $n--;
   }
   setcookie( $name, $value, $NOW+$rel_expire, $SUB_PATH );
   //for current session:
   $_COOKIE[$name] = $value; //??? add magic_quotes_gpc like slashes?
}

function set_login_cookie($handl, $code, $delete=false)
{
 global $session_duration;

   if( $delete or !$handl or !$code)
   {
      safe_setcookie('handle');
      safe_setcookie('sessioncode');
   }
   else
   {
      safe_setcookie('handle', $handl, $session_duration*5);
      safe_setcookie('sessioncode', $code, $session_duration);
   }
}

function set_cookie_prefs($id, $delete=false)
{
 global $cookie_prefs, $session_duration;

   if( $delete )
      safe_setcookie("prefs$id");
   else
      safe_setcookie("prefs$id", serialize($cookie_prefs), 3600*12*61*12*5); //5 years
}

function get_cookie_prefs(&$player_row)
{
   global $cookie_prefs, $cookie_pref_rows;

   $cookie_prefs = unserialize( safe_getcookie("prefs{$player_row['ID']}") );
   if( !is_array( $cookie_prefs ) )
      $cookie_prefs = array();

   foreach( $cookie_prefs as $key => $value )
      {
         if( in_array($key, $cookie_pref_rows) )
            $player_row[$key] = $value;
      }
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
// 'game': tags allowed in game messages
// 'faq': tags allowed in the FAQ pages
//Warning: </br> was historically used in end game messages. It remains in database.

// ** keep them lowercase and do not use parenthesis **
  // ** keep a '|' at both ends:
$html_code_closed['cell'] = '|b|i|u|strong|em|tt|color|';
$html_code_closed['msg'] = '|a|b|i|u|strong|em|color|center|ul|ol|font|tt|pre|code|quote|home|';
$html_code_closed['game'] = $html_code_closed['msg'].'h|hidden|c|comment|';
//$html_code_closed['faq'] = '|'; //no closed check
$html_code_closed['faq'] = $html_code_closed['msg']; //minimum closed check
  // more? '|/li|/p|/br|/ *br';

  // ** no '|' at ends:
$html_code['cell'] = 'b|i|u|strong|em|tt|color';
$html_code['msg'] = 'br|/br|p|/p|li'.$html_code_closed['msg']
   .'goban|mailto|https?|news|game_?|user_?|send_?';
$html_code['game'] = 'br|/br|p|/p|li'.$html_code_closed['game']
   .'goban|mailto|https?|news|game_?|user_?|send_?';
$html_code['faq'] = '\w+|/\w+'; //all not empty words


  //** no reg exp chars nor ampersand nor '%' (see also $html_safe_preg):
define( 'ALLOWED_LT', "`anglstart`");
define( 'ALLOWED_GT', "`anglend`");
define( 'ALLOWED_QUOT', "`allowedquot`");
define( 'ALLOWED_APOS', "`allowedapos`");

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
   if ( !$bad && $head )
   {
/*
This part fix a security hole. One was able to execute a javascript code
(if read by some browsers: IExplorer, for instance) with something like:
<b style="background:url('javascript:eval(document.all.mycode.xcode)')"
   id="mycode" xcode="alert('Hello!!!')">Hello!</b>
*/
      $quote =
          '\\bjavascript\\s*:'   //main reject
         .'|\\.inner'            //like .innerHTML
         .'|(\\bon\\w+\\s*=)'    //like onevent=
         .'|\\beval\\s*\\('      //eval() can split most of the keywords
         .'|\\bstyle\\s*='       //disabling style= is not bad too
         ;
      if ( /*$quote &&*/  preg_match( "%($quote)%i",
            preg_replace( "%[\\x01-\\x1f]+%", '', $head)) ) {
         $bad = 2;
      }
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
   //$stop = preg_quote($stop, '%');
   //$reg = "%^(.*?)<(" . ( $stop ? "$stop|" : '' ) . "$html_code)([\\x01-\\x20>:].*)$%is";
   $reg = "%^(.*?)<(" . ( $stop ? "$stop|" : '' ) . $html_code . ")\b(.*)$%is";

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
      if( $tag == 'code' )
      {
         // does not allow inside HTML
         $tmp= '/'.$tag;
         $inside = parse_tags_safe( $trail, $bad, $tmp, $tmp, $tmp) ;
         if( $bad)
            return $before .'<'. $head .'>'. $inside ;
         $inside = str_replace('&', '&amp;', $inside);
      }
      else
      if( $tag == 'tt' )
      {
         // TT is mainly designed to be used when $some_html=='cell'
         // does not allow inside HTML and remove line breaks
         $tmp= '/'.$tag;
         $inside = parse_tags_safe( $trail, $bad, $tmp, $tmp, $tmp) ;
         if( $bad)
            return $before .'<'. $head .'>'. $inside ;
         //$inside = str_replace('&', '&amp;', $inside);
         $inside = preg_replace('%[\\x09\\x20]%', '&nbsp;', $inside);
         $inside = preg_replace('%[\\x01-\\x1F]*%', '', $inside);
      }
      else
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

function basic_safe( $str)
{
   return str_replace(
              array( '<', '>', '"', "'")
            , array( '&lt;', '&gt;', '&quot;', '&#039;')
            , $str);
}

function reverse_allowed( $msg)
{
   return str_replace(
      array( ALLOWED_LT, ALLOWED_GT, ALLOWED_QUOT, ALLOWED_APOS)
      , array( '<', '>', '"', "'")
      , $msg);
}

define('REF_LINK', 0x1);
define('REF_LINK_ALLOWED', 0x2);
define('REF_LINK_BLANK', 0x4);


//Note: some of those check for the '`' i.e. the first char of ALLOWED_* vars
$html_safe_preg = array(

//<mailto:...>
 "%".ALLOWED_LT."(mailto:)([^ `\n\t]+)".ALLOWED_GT."%is"
  => ALLOWED_LT."a href=".ALLOWED_QUOT."\\1\\2".ALLOWED_QUOT.ALLOWED_GT
                        ."\\2".ALLOWED_LT."/a".ALLOWED_GT,

//<http://...>, <https://...>, <news://...>, <ftp://...>
 "%".ALLOWED_LT."((http:|https:|news:|ftp:)//[^ `\n\t]+)".ALLOWED_GT."%is"
  => ALLOWED_LT."a href=".ALLOWED_QUOT."\\1".ALLOWED_QUOT.ALLOWED_GT
                        ."\\1".ALLOWED_LT."/a".ALLOWED_GT,

//<game gid[,move]> =>show game
 "%".ALLOWED_LT."game(_)? +([0-9]+)( *, *([0-9]+))? *".ALLOWED_GT."%ise"
  => "game_reference(('\\1'=='_'?".REF_LINK_BLANK.":0)+"
                        .REF_LINK_ALLOWED.",1,'',\\2,\\4+0)",

//<user uid> or <user =uhandle> =>show user info
//<send uid> or <send =uhandle> =>send a message to user
 "%".ALLOWED_LT."(user|send)(_)? +(".HANDLE_TAG_CHAR
                        ."?[".HANDLE_LEGAL_REGS."]+) *".ALLOWED_GT."%ise"
  => "\\1_reference(('\\2'=='_'?".REF_LINK_BLANK.":0)+"
                        .REF_LINK_ALLOWED.",1,'','\\3')",

//<color col>...</color> =>translated to <font color="col">...</font>
 "%".ALLOWED_LT."color +([#0-9a-zA-Z]+) *".ALLOWED_GT."%is"
  => ALLOWED_LT."font color=".ALLOWED_QUOT."\\1".ALLOWED_QUOT.ALLOWED_GT,
 "%".ALLOWED_LT."/color *".ALLOWED_GT."%is"
  => ALLOWED_LT."/font".ALLOWED_GT,

//<tt>...</tt> =>translated to <pre>...</pre>
// see also parse_tags_safe() for the suppression of inner html code
/*
 "%".ALLOWED_LT."tt([^`\n\t]*)".ALLOWED_GT
  => ALLOWED_LT."pre\\1".ALLOWED_GT,
 "%".ALLOWED_LT."/tt *".ALLOWED_GT
  => ALLOWED_LT."/pre".ALLOWED_GT,
*/

//<code>...</code> =>translated to <pre class="code">...</pre>
// see also parse_tags_safe() for the suppression of inner html codes
 "%".ALLOWED_LT."code([^`\n\t]*)".ALLOWED_GT."%is"
  => ALLOWED_LT."pre class=".ALLOWED_QUOT."code".ALLOWED_QUOT
                        ."\\1".ALLOWED_GT,
 "%".ALLOWED_LT."/code *".ALLOWED_GT."%is"
  => ALLOWED_LT."/pre".ALLOWED_GT,

//<quote>...</quote> =>translated to <div class="quote">...</div>
 "%".ALLOWED_LT."quote([^`\n\t]*)".ALLOWED_GT."%is"
  => ALLOWED_LT."div class=".ALLOWED_QUOT."quote".ALLOWED_QUOT
                        ."\\1".ALLOWED_GT,
 "%".ALLOWED_LT."/quote *".ALLOWED_GT."%is"
  => ALLOWED_LT."/div".ALLOWED_GT,

//<home page>...</home> =>translated to <a href="$HOSTBASE$page">...</a>
 "%".ALLOWED_LT."home[\n\s]+([^`\n\s]*)".ALLOWED_GT."%is"
  => ALLOWED_LT."a href=".ALLOWED_QUOT.$HOSTBASE."\\1".ALLOWED_QUOT.ALLOWED_GT,
 "%".ALLOWED_LT."/home *".ALLOWED_GT."%is"
  => ALLOWED_LT."/a".ALLOWED_GT,

); //$html_safe_preg


//$some_html may be false, 'cell', 'faq', 'game', 'gameh' or 'msg'
function make_html_safe( $msg, $some_html=false)
{

   if( $some_html )
   {
      // make sure the <, > replacements: ALLOWED_LT, ALLOWED_GT are removed from the string
      $msg= reverse_allowed( $msg);

      switch( $some_html )
      {
      case 'gameh':
         $gameh = 1 ;
         $some_html = 'game';
         break;
      default:
         $some_html = 'msg';
      case 'msg':
      case 'cell':
      case 'game':
      case 'faq':
         $gameh = 0 ;
         break;
      }

      // regular (and extended) allowed html tags check
      $msg = parse_html_safe( $msg, $some_html) ;


      // formats legal html code
      if( $some_html == 'game' )
      {
         if( $gameh ) // show hidden sgf comments
         {
            $msg = eregi_replace(ALLOWED_LT."h(idden)? *".ALLOWED_GT,
                                 ALLOWED_LT."font color=red".ALLOWED_GT."\\0", $msg);
            $msg = eregi_replace(ALLOWED_LT."/h(idden)? *".ALLOWED_GT,
                                 "\\0".ALLOWED_LT."/font".ALLOWED_GT, $msg);
         }
         else // hide hidden sgf comments
            $msg = trim(preg_replace("%".ALLOWED_LT."h(idden)? *".ALLOWED_GT
                                          ."(.*?)".ALLOWED_LT."/h(idden)? *"
                                          .ALLOWED_GT."%is", "", $msg));


         $msg = eregi_replace(ALLOWED_LT."c(omment)? *".ALLOWED_GT,
                              ALLOWED_LT."font color=blue".ALLOWED_GT."\\0", $msg);
         $msg = eregi_replace(ALLOWED_LT."/c(omment)? *".ALLOWED_GT,
                              "\\0".ALLOWED_LT."/font".ALLOWED_GT, $msg);

         $some_html = 'msg';
      }

      global $html_safe_preg;
      $msg= preg_replace( array_keys($html_safe_preg), $html_safe_preg, $msg);

   }


   // Filter out HTML code

   /*
      $msg = str_replace('&', '&amp;', $msg);
   $msg = eregi_replace('&amp;((#[0-9]+|[A-Z][0-9A-Z]*);)', '&\\1', $msg);
   */
   $msg = preg_replace('%&(?!(#[0-9]+|[A-Z][0-9A-Z]*);)%si', '&amp;', $msg);

   $msg = basic_safe( $msg);

   if( $some_html )
   {
      // change back to <, > from ALLOWED_LT, ALLOWED_GT
      $msg= reverse_allowed( $msg);
   }

   if( $some_html && $some_html != 'cell' )
   {
      $msg = add_line_breaks($msg);
   }

   return $msg;
}

function textarea_safe( $msg, $charenc=false)
{
 global $encoding_used;
   if( !$charenc) $charenc = $encoding_used; //else 'iso-8859-1' LANG_DEF_CHARSET
   $msg = @htmlspecialchars($msg, ENT_QUOTES, $charenc);
//No:   $msg = @htmlentities($msg, ENT_QUOTES, $charenc); //Too much entities for not iso-8859-1 languages
   return $msg;
}

//keep the parts readable by an observer
function game_tag_filter( $msg)
{
   $nr_matches = preg_match_all(
         "'(<c(omment)? *>(.*?)</c(omment)? *>)".
         "|(<h(idden)? *>(.*?)</h(idden)? *>)'is"
         , $msg, $matches );
   $str = '';
   for($i=0; $i<$nr_matches; $i++)
   {
      $msg = trim($matches[1][$i]);
      if( !$msg )
         $msg = trim($matches[5][$i]);
      if(  $msg )
         $str .= "\n" . $msg ;
   }
   return trim($str);
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
// if $sep is true, a '?' or '&' is added
// Example:
// make_url('test.php', array('a'=> 1, 'b => 'foo'), false)  gives
// 'test.php?a=1&b=foo'
function make_url($page, $array, $sep=false)
{
   $url = $page;

   $separator = ( is_numeric( strpos( $url, '?')) ? URI_AMP : '?' );
   if( is_array( $array) )
   foreach( $array as $var=>$value )
   {
      if( !empty($value) && !is_numeric($var) )
      {
         $url .= $separator . $var . '=' . urlencode($value);
         $separator = URI_AMP;
      }
   }

   if( $sep )
      $url .= $separator;

   return $url;
}

// relative to the calling URL, not to the current dir
function rel_base_dir()
{
   global $SUB_PATH;

   $dir = str_replace('\\','/',$_SERVER['PHP_SELF']);
   $rel = '';
   while( $i=strrpos($dir,'/') )
   {
      $dir= substr($dir,0,$i);
      if( !strcasecmp( $dir.'/' , $SUB_PATH ) )
         break;
      $rel.= '../';
   }
   return $rel;
}

function get_request_url( $absolute=false)
{
 global $SUB_PATH, $HOSTBASE;

//CAUTION: sometime, REQUEST_URI != PHP_SELF+args
//if there is a redirection, _URI==requested, while _SELF==reached (running one)
   $url = str_replace('\\','/',@$_SERVER['REQUEST_URI']); //contains URI_AMP_IN and still urlencoded
   $len = strlen($SUB_PATH);
   if (!strcasecmp( $SUB_PATH, substr($url,0,$len) ))
      $url = substr($url,$len);
   $url = str_replace( URI_AMP_IN, URI_AMP, $url);
   if( $absolute )
      $url = $HOSTBASE . $url;
   return $url;
}


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
      $uhandle = @$_REQUEST[UHANDLE_NAME];
      if( !$uhandle && $from_referer && ($refer=@$_SERVER['HTTP_REFERER']) )
      {
//default user = last referenced user
//(ex: message.php from userinfo.php by menu link)
         if( eregi("[?".URI_AMP_IN."]$uid_nam=([0-9]+)", $refer, $result) )
           $uid = $result[1];
         if( !($uid > 0) )
         {
            $uid = 0;
            if( eregi("[?".URI_AMP_IN."]".UHANDLE_NAME."=([".HANDLE_LEGAL_REGS."]+)", $refer, $result) )
              $uhandle = $result[1];
         }
      }
   }
}

function who_is_logged( &$row)
{
   $handle = safe_getcookie('handle');
   $sessioncode = safe_getcookie('sessioncode');
   $curdir = getcwd();
   global $main_path;
// because include_all_translate_groups() must be called from main dir
   chdir( $main_path);
   $res = is_logged_in($handle, $sessioncode, $row);
   chdir( $curdir);
   return $res;
}

function is_logged_in($hdl, $scode, &$row) //must be called from main dir
{
   global $HOSTNAME, $hostname_jump, $admin_level,
      $ActivityForHit, $NOW;

   $row = array();

   if( $hostname_jump and eregi_replace(":.*$","", @$_SERVER['HTTP_HOST']) != $HOSTNAME )
   {
      jump_to( "http://" . $HOSTNAME . $_SERVER['PHP_SELF'], true );
   }

   if( !$hdl )
   {
      include_all_translate_groups(); //must be called from main dir
      return false;
   }

   $result = mysql_query( "SELECT *, UNIX_TIMESTAMP(Sessionexpire) AS Expire, " .
                          "Adminlevel+0 as admin_level " .
                          "FROM Players WHERE Handle='".addslashes($hdl)."'" )
      or error('mysql_query_failed','std_functions.is_logged_in.find_player');


   if( @mysql_num_rows($result) != 1 )
   {
      include_all_translate_groups(); //must be called from main dir
      return false;
   }

   $row = mysql_fetch_assoc($result);

   include_all_translate_groups($row); //must be called from main dir

   $query = "UPDATE Players SET " .
      "Hits=Hits+1, " .
      "Activity=Activity + $ActivityForHit, " .
      "Lastaccess=FROM_UNIXTIME($NOW), " .
      "Notify='NONE'";

   $browser = substr(@$_SERVER['HTTP_USER_AGENT'], 0, 100);
   if( $row['Browser'] !== $browser )
   {
      $query .= ", Browser='".addslashes($browser)."'";
      $row['Browser'] = $browser;
   }

   $ip = (string)@$_SERVER['REMOTE_ADDR'];
   if( $ip && $row['IP'] !== $ip )
   {
      $query .= ", IP='$ip'";
      $row['IP'] = $ip;
   }

   if( $row["Sessioncode"] != $scode or $row["Expire"] < $NOW )
      return false;

   $query .= " WHERE Handle='".addslashes($hdl)."' LIMIT 1";

   $result = mysql_query( $query )
      or error('mysql_query_failed','std_functions.is_logged_in.update_player');

   if( @mysql_affected_rows() != 1 )
      return false;


   if( @$row["admin_level"] != 0 )
      $admin_level = $row["admin_level"];

   get_cookie_prefs($row);

   setTZ( $row['Timezone']);

   return true;
}

function write_to_file( $filename, $string_to_write )
{
  $fp = fopen( $filename, 'w' )
    or error( "couldnt_open_file", $filename );

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
      echo "<p><a class=blue href=\"$link\">$linkdesc</a>";
   else
      echo "<p class=gray>$linkdesc";

   if( !empty($extra) )
      echo " --- $extra";

   echo "</p>\n";
}

function activity_string( $act_lvl)
{
 global $base_path;
   return ( $act_lvl == 0 ? '&nbsp;' :
           ( $act_lvl == 1
             ? '<img align=middle alt="*" src="'.$base_path.'images/star2.gif">'
             : '<img align=middle alt="*" src="'.$base_path.'images/star.gif">'
              .'<img align=middle alt="*" src="'.$base_path.'images/star.gif">' 
             ) );
}


function nsq_addslashes( $str )
{
  return str_replace( array( "\\", "\"", "\$" ), array( "\\\\", "\\\"", "\\\$" ), $str );
}

function game_reference( $link, $safe, $class, $gid, $move=0, $whitename=false, $blackname=false)
{
 global $base_path;

   $gid = (int)$gid;
   $legal = ( $gid<=0 ? 0 : 1 );
   if( ($whitename===false or $blackname===false) && $legal )
   {
     $query = 'SELECT black.Name as blackname, white.Name as whitename ' .
              'FROM Games, Players as white, Players as black ' .
              "WHERE Games.ID=$gid " .
              ' AND white.ID=Games.White_ID ' .
              ' AND black.ID=Games.Black_ID ' .
              'LIMIT 1' ;
     if( $row=mysql_single_fetch( $query, 'assoc', 'std_functions.game_reference' ) )
     {
       if( $whitename===false )
         $whitename = $row['whitename'];
       if( $blackname===false )
         $blackname = $row['blackname'];
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
   if( $move>0 )
      $whitename.= " #$move";
   if( $link && $legal )
   {
      $url = "game.php?gid=$gid" . ($move>0 ? URI_AMP."move=$move" : "");
      $url = 'A href="' . $base_path. $url . '"';
      if( $link & REF_LINK_BLANK )
        $url.= ' target="_blank"';
      if( $class )
        $url.= " class=$class";
      if( $link & REF_LINK_ALLOWED )
      {
        $url = str_replace('"', ALLOWED_QUOT, $url);
        $whitename = ALLOWED_LT.$url.ALLOWED_GT.$whitename.ALLOWED_LT."/A".ALLOWED_GT ;
      }
      else
        $whitename = "<$url>$whitename</A>" ;
   }
   return $whitename;
}

function send_reference( $link, $safe, $class, $player_ref, $player_name=false, $player_handle=false)
{
   if( is_numeric($link) ) //not owned reference
      $link= -$link; //make it a send_reference
   return user_reference( $link, $safe, $class, $player_ref, $player_name, $player_handle);
}

function user_reference( $link, $safe, $class, $player_ref, $player_name=false, $player_handle=false)
{
 global $base_path;
   if( is_array($player_ref) ) //i.e. $player_row
   {
      if( !$player_name )
         $player_name = $player_ref['Name'];
      if( !$player_handle )
         $player_handle = $player_ref['Handle'];
      $player_ref = $player_ref['ID'];
   }
   $legal = 1;
   if( !is_numeric($player_ref) || $player_ref{0}==HANDLE_TAG_CHAR )
   {
      $byid = 0;
      if( $player_ref{0}==HANDLE_TAG_CHAR )
         $player_ref = substr($player_ref,1);
      if( !$player_ref or illegal_chars( $player_ref) )
         $legal = 0;
   }
   else
   {
      $byid = 1;
      $player_ref = (int)$player_ref;
      if( $player_ref<=0)
         $legal = 0;
   }
   if( ($player_name===false or $player_handle===false) && $legal )
   {
     $query = 'SELECT Name, Handle ' .
              'FROM Players ' .
              "WHERE " . ( $byid ? 'ID' : 'Handle' ) . "='$player_ref' " .
              'LIMIT 1' ;
     if( $row=mysql_single_fetch( $query, 'assoc', 'std_functions.user_reference' ) )
     {
       if( $player_name===false )
         $player_name = $row['Name'];
       if( $player_handle===false )
         $player_handle = $row['Handle'];
       $safe = true;
     }
     else
       $legal = 0;
   }
   $player_name = trim($player_name);
   $player_handle = trim($player_handle);
   if( !$player_name )
      $player_name = "User#$player_ref";
   if( $player_handle )
      $player_name.= " ($player_handle)" ;
   if( $safe )
      $player_name = make_html_safe($player_name) ;
   if( $link && $legal )
   {
      if( is_string($link) ) //owned reference. Must end with '?' or URI_AMP
      {
         $url = $link;
         $link = 0;
      }
      else if( $link<0 ) //send_reference
      {
         $url = "message.php?mode=NewMessage".URI_AMP;
         $link = -$link;
      }
      else //user_reference
      {
         $url = "userinfo.php?";
      }
      $url.= ( $byid ? "uid=$player_ref" 
                 : UHANDLE_NAME."=".str_replace('+','%2B',$player_ref) );
      $url = 'A href="' . $base_path. $url . '"';
      if( $class )
        $url.= " class=$class";
      if( $link & REF_LINK_BLANK )
        $url.= ' target="_blank"';
      if( $link & REF_LINK_ALLOWED )
      {
        $url = str_replace('"', ALLOWED_QUOT, $url);
        $player_name = ALLOWED_LT.$url.ALLOWED_GT.$player_name.ALLOWED_LT."/A".ALLOWED_GT ;
      }
      else
        $player_name = "<$url>$player_name</A>" ;
   }
   return $player_name ;
}

function is_on_observe_list( $gid, $uid )
{
   $result = mysql_query("SELECT ID FROM Observers WHERE gid=$gid AND uid=$uid")
      or error('mysql_query_failed','std_functions.is_on_observe_list');
   return( @mysql_num_rows($result) > 0 );
}

function toggle_observe_list( $gid, $uid )
{
   if( is_on_observe_list( $gid, $uid ) )
      mysql_query("DELETE FROM Observers WHERE gid=$gid AND uid=$uid LIMIT 1")
         or error('mysql_query_failed','std_functions.toggle_observe_list.delete');
   else
      mysql_query("INSERT INTO Observers SET gid=$gid, uid=$uid")
         or error('mysql_query_failed','std_functions.toggle_observe_list.insert');
}

//Text must be escaped by addslashes()
function delete_all_observers( $gid, $notify, $Text='' )
{
   global $NOW;

   if( $notify )
   {
      $result = mysql_query("SELECT Observers.uid AS pid " .
                            "FROM Observers WHERE gid=$gid")
         or error('mysql_query_failed','std_functions.delete_all_observers.find');

      if( @mysql_num_rows($result) > 0 )
      {

         $Subject = 'An observed game has finished';

         mysql_query( "INSERT INTO Messages SET Time=FROM_UNIXTIME($NOW), " .
                      "Game_ID=$gid, Subject='$Subject', Text='$Text'" )
            or error('mysql_query_failed','std_functions.delete_all_observers.message');

         if( mysql_affected_rows() == 1)
         {
            $mid = mysql_insert_id();

            while( $row = mysql_fetch_array( $result ) )
            {
               mysql_query("INSERT INTO MessageCorrespondents (uid,mid,Sender,Folder_nr) VALUES " .
                           "(" . $row['pid'] . ", $mid, 'N', ".FOLDER_NEW.")")
                  or error('mysql_query_failed','std_functions.delete_all_observers.message');
            }
         }

      }
   }

   mysql_query("DELETE FROM Observers WHERE gid=$gid")
      or error('mysql_query_failed','std_functions.delete_all_observers.delete');
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

function blend_warning_cell_attb( $title='', $bgcolor='f7f5e3', $col='ff000033')
{
   $str= ' bgcolor="#' . blend_alpha_hex( $col, $bgcolor) . '"';
   if ($title) $str.= ' title="' . $title . '"';
   return $str;
}

function limit($val, $minimum, $maximum, $default)
{
   if( is_string( $val) )
   {
      $val = trim( $val);
      if( strlen( $val) > 1 )
      {
         if( substr($val,-1) == '%'
               && is_numeric($minimum) 
               && is_numeric($maximum) 
               )
            $val = ($maximum-$minimum)*(substr($val,0,-1)/100.) + $minimum;
         elseif( is_numeric(strpos('hHxX#$',$val{0})) )
            $val = base_convert( substr($val,1), 16, 10);
      } 
   }

   if( !is_numeric($val) )
      return (isset($default) ? $default : $val );
   else if( is_numeric($minimum) and $val < $minimum )
      return $minimum;
   else if( is_numeric($maximum) and $val > $maximum )
      return $maximum;

   return $val;
}

function attb_quote( $str)
{
   return '"'.basic_safe(trim($str)).'"';
}

function attb_build( $attbs)
{
   if( is_array( $attbs) )
   {
      $str= '';
      foreach( $attbs as $key => $val )
      {
         $str.= ' '.$key.'='.attb_quote($val);
      }
      return $str;
   }
   if( is_string( $attbs) )
   {
      $str= trim($attbs);
      if( $str )
         return ' '.$str;
   }
   return '';
}

function image( $src, $alt, $title='', $attbs='', $height=-1, $width=-1)
{
   $str = "<img border=0 src=\"$src\" alt=".attb_quote($alt);
   if( $title )
     $str.= ' title='.attb_quote($title);
   if( $height>=0 )
     $str.= " height=\"$height\"";
   if( $width>=0 )
     $str.= " width=\"$width\"";
   $str.= attb_build($attbs);
   return $str.'>';
}

function anchor( $href, $text, $title='', $attbs='')
{
   $str = "<a href=\"$href\"";
   if( is_array($attbs) )
   {
      if( isset($attbs['accesskey']) )
      {
         $xkey = trim($attbs['accesskey']);
         unset($attbs['accesskey']);
         if( $xkey )
         {
            $xkey = substr($xkey,0,1);
            $title.= " [&amp;$xkey]";
            $str.= ' accesskey='.attb_quote($xkey);
         }
      }
   }
   if( $title )
      $str.= ' title='.attb_quote($title);
   $str.= attb_build($attbs);
   return $str.">$text</a>";
}

function str_TD_class_button( $href, $text='')
{
   $text= (string)$text;
   return "<td class=button>" .
      "<a class=button href=\"$href\">" .
      "&nbsp;&nbsp;$text&nbsp;&nbsp;" .
      "</a>" .
      "</td>";
}

function button_style( $button_nr=0)
{
   global $buttoncolors, $buttonfiles, $button_max, $button_width;

   if ( !is_numeric($button_nr) or $button_nr < 0 or $button_nr > $button_max  )
      $button_nr = 0;

   return 
     "a.button {" .
       " display: block;" .
       " min-width: ".($button_width-4)."px;" .
//       " width: ".($button_width-4)."px;" .
       " color: {$buttoncolors[$button_nr]};" .
       " font: bold 100% sans-serif;" .
       " text-decoration: none;" .
     "}\n" .
     "td.button {" .
       " background-image: url(images/{$buttonfiles[$button_nr]});" .
       " background-repeat: no-repeat;" .
       " background-position: center;" .
       " padding: 0px 2px 0px 2px;" .
       " min-width: {$button_width}px;" .
       " width: {$button_width}px;" .
       " text-align: center;" .
     "}";
}

?>