<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

// apd_set_pprof_trace();  for profiling

$TranslateGroups[] = "Common";

require_once( 'include/globals.php' );
require_once( "include/quick_common.php" );
require_once( 'include/utilities.php' );

require_once( "include/time_functions.php" );
if( !isset($page_microtime) )
{
   $page_microtime = getmicrotime();
   //std_functions.php must be called from the main dir
   $main_path = str_replace('\\', '/', getcwd()).'/';
   //$base_path is relative to the URL, not to the current dir
   $base_path = rel_base_dir();
   $printable = (bool)@$_REQUEST['printable'];
}

require_once( "include/page_functions.php" );

require_once( "include/translation_functions.php" );
require_once( "include/classlib_matrix.php" );
require_once( "forum/class_forum_read.php" );


// Server birth date:
define('BEGINYEAR', 2001);
define('BEGINMONTH', 8);


// because of the cookies host, $hostname_jump = true is nearly mandatory
$hostname_jump = true;  // ensure $HTTP_HOST is same as HOSTNAME

// If using apache add this row to your virtual host to make this work:
// AliasMatch game([0-9]+)\.sgf /path/to/sgf.php
$has_sgf_alias = false;


/* when $GUESTPASS is modified,
 * run HOSTBASE."change_password.php?guestpass=".GUEST_ID
 * with ADMIN_PASSWORD privileges
 */
if( FRIENDLY_SHORT_NAME == 'DGS' )
   $GUESTPASS = 'guest'.'pass';
else
   $GUESTPASS = 'guest';
define('GUESTS_ID_MAX', 1); //minimum 1 because hard-coded in init.mysql

// for debugging various variables (used for development)
$DEBUG = false;
$DEBUG_SQL = false; // for debugging filter showing where-clause on page

define('LAYOUT_FILTER_IN_TABLEHEAD', true); // default is to show filters within tablehead (not below rows)
define('LAYOUT_FILTER_EXTFORM_HEAD', true); // default is to show external-filter-form above filter-table

define('SPAN_ONLINE_MINS', 10); // being "online" = if last-accessed during last X minutes

//----- { layout : change in dragon.css too!
$bg_color='"#f7f5e3"';

//$menu_fg_color='"#FFFC70"';
if( FRIENDLY_SHORT_NAME == 'DGS' )
   $menu_bg_color='"#0C41C9"'; //live server
else
   $menu_bg_color='"#C9410C"'; //devel server

//{ N.B.: only used for folder transparency but CSS incompatible
$table_row_color1='"#FFFFFF"';
$table_row_color2='"#E0E8ED"';
//}
// obsolete since CSS
//$table_head_color='"#CCCCCC"';
//$table_row_color_del1='"#FFCFCF"';
//$table_row_color_del2='"#F0B8BD"';
//$h3_color='"#800000"';
//$sgf_color='"#d50047"';

//----- } layout : change in dragon.css too!


$max_links_in_main_menu=5;

//-----
//Tables - lists
define('LIST_ROWS_MODULO', 4); //at least 1

define('MAXROWS_PER_PAGE_DEFAULT', 20);
define('MAXROWS_PER_PAGE_PROFILE', 50);
define('MAXROWS_PER_PAGE_FORUM',   50); // max for forum-search
define('MAXROWS_PER_PAGE', 100);
//-----

$ActivityHalvingTime = 4 * 24 * 60; // [minutes] four days halving time
$ActivityForHit = 1000.0; //it is the base unit for all the Activity calculus
$ActivityForMove = 10*$ActivityForHit;

$ActiveLevel1 = $ActivityForMove + 2*$ActivityForHit; //a "move sequence" value
$ActiveLevel2 = 15*$ActiveLevel1;
$ActivityMax = 0x7FFF0000-$ActivityForMove;

define('NEWGAME_MAX_GAMES', 10);

define('MAX_START_RATING', 2600); //6 dan
define('MIN_RATING', -900); //30 kyu
define('OUT_OF_RATING', 9999); //ominous rating bounds: [-OUT_OF_RATING,OUT_OF_RATING]
define('RATING_9DAN', 2900); //9 dan (selectable max-rating)

// Players.RatingStatus
define('RATING_NONE',  'NONE'); // no rating set
define('RATING_INIT',  'INIT'); // rating set, but can be changed (no rated games yet)
define('RATING_RATED', 'RATED'); // rating established (rated game exists)

//Allow the "by number of games" graphic (as well as "by date of games").
define('GRAPH_RATING_BY_NUM_ENABLED', true);
define('GRAPH_RATING_MIN_INTERVAL', 2*31*24*3600);
// see also CACHE_FOLDER in config.php
define('CACHE_EXPIRE_GRAPH', 24*3600); //1 day

define('MENU_MULTI_SEP', ' / ');

define('BUTTON_WIDTH', 96);
$button_max = 11;
$buttonfiles = array('button0.gif','button1.gif','button2.gif','button3.gif',
                     'button4.gif','button5.gif','button6.gif','button7.gif',
                     'button8.png','button9.png','button10.png','button10.png');
$buttoncolors = array('white','white','white','white',
                      '#990000','white','white','white',
                      'white','white','white','black');

$woodbgcolors = array(1=>'#e8c878','#e8b878','#e8a858', '#d8b878', '#b88848');

$cookie_pref_rows = array(
       // global config (from Players-table):
       'UserFlags',
       'Button',
       'TableMaxRows',
       'MenuDirection',
       'SkinName',
       // board config (from ConfigBoard-table):
       // NOTE: place also all prefs from ConfigBoard-table in players_row,
       //       but manage with ConfigBoard-class; see is_logged_in()
       // NOTE: also add in ConfigBoard::load_config_board()
       'Stonesize', 'Woodcolor', 'Boardcoords',
       'MoveNumbers', 'MoveModulo',
       'NotesSmallHeight', 'NotesSmallWidth', 'NotesSmallMode',
       'NotesLargeHeight', 'NotesLargeWidth', 'NotesLargeMode',
       'NotesCutoff',
   );

$vacation_min_days = 2;

define('INFO_HTML', 'cell'); //HTML parsing for texts like 'Rank info'
define('SUBJECT_HTML', false); //HTML parsing for subjects of posts and messages

define('DELETE_LIMIT', 10);

define('MAX_SEKI_MARK', 2);

//-----
//Board array
define("NONE", 0); //i.e. DAME, Moves(Stone=NONE,PosX/PosY=coord,Hours=0)
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

//-- Database values --

//Moves table: particular Stone values
//normal moves are BLACK or WHITE, prisoners are NONE
define("MARKED_BY_WHITE", 7); // for scoring: Moves(Stone=MARKED_BY_BLACK|WHITE, PosX/PosY=coord, Hours=0)
define("MARKED_BY_BLACK", 8);

//Moves table: particular PosX values
//regular PosX and PosY are from 0 to size-1
// game steps
define('POSX_PASS', -1);   // Pass-move: Stone=BLACK|WHITE, PosY=0, Hours=passed-time
define('POSX_SCORE', -2);  // scoring step by Stone=BLACK|WHITE, PosY=0, Hours=passed-time
define('POSX_RESIGN', -3); // resigned by Stone=BLACK|WHITE: PosY=0, Hours=passed-time
define('POSX_TIME', -4);   // timeout for Stone=BLACK|WHITE: PosY=0, Hours=passed-time
// game commands
define('POSX_ADDTIME', -50); // Add-Hours: Stone=BLACK|WHITE (time-adder), PosY=0|1 (1=byoyomi-reset), Hours=add_hours

//Games table: particular Score values
define('SCORE_RESIGN', 1000);
define('SCORE_TIME', 2000);
define('SCORE_MAX', min(SCORE_RESIGN,SCORE_TIME) - 1); // =min(SCORE_...) - 1


define('DEFAULT_KOMI', 6.5); // change with care only, keep separate from STONE_VALUE
define('STONE_VALUE',13); // 2 * conventional komi (=DEFAULT_KOMIT), change with care

define('MIN_BOARD_SIZE',5);
define('MAX_BOARD_SIZE',25);
define('MAX_KOMI_RANGE',200);
define('MAX_HANDICAP',21);
// b0=standard placement, b1=with black validation skip, b2=all placements
// both b1 and b2 set is not fully handled (error if incomplete pattern)
define('ENA_STDHANDICAP',0x3);
define('ENA_MOVENUMBERS',1);
define('MAX_MOVENUMBERS', 500);


//keep next constants powers of 2
define('GAMEFLAGS_KO', 0x01);
define('GAMEFLAGS_HIDDEN_MSG', 0x02);

//-----
// UserFlags (also stored in cookie)
define('USERFLAG_JAVASCRIPT_ENABLED', 0x001);
//-----


//-----
// admin-roles
// NOTE: also adjust admin_show_users.php on adding new roles
define("ADMIN_TRANSLATORS",0x01);
define("ADMIN_FAQ",0x02);
define("ADMIN_FORUM",0x04);
define("ADMIN_SUPERADMIN",0x08); // manage admins (add, edit, delete)
define('ADMIN_TOURNAMENT',0x10);
define('ADMIN_VOTE',0x20);
define("ADMIN_PASSWORD",0x40);
define('ADMIN_DATABASE',0x80);
define('ADMIN_DEVELOPER',0x100);
define('ADMIN_SKINNER',0x200);
// admin groups
define('ADMINGROUP_EXECUTIVE', (ADMIN_FAQ|ADMIN_FORUM|ADMIN_VOTE|ADMIN_TOURNAMENT|ADMIN_PASSWORD|ADMIN_DEVELOPER));
//-----


//-----
// user-type characteristics
define('USERTYPE_UNSET',   0x0000); // default
define('USERTYPE_PRO',     0x0001); // professional
define('USERTYPE_TEACHER', 0x0002); // offers lessons (free or paid)
define('USERTYPE_ROBOT',   0x0004);
define('USERTYPE_TEAM',    0x0008); // unused so far
//-----
define('ARG_USERTYPE_NO_TEXT', 'none');


// param $short: true, false, ARG_USERTYPE_NO_TEXT (no text)
function build_usertype_text( $usertype, $short=false, $img=true, $sep=', ' )
{
   global $base_path;
   $out = array();
   if( $usertype & USERTYPE_PRO )
   {
      $text = T_('Professional');
      $tmp = ($img) ? image( "{$base_path}images/professional.gif", $text, null ) : '';
      if( $short !== ARG_USERTYPE_NO_TEXT )
         $tmp .= ($img ? ' ' : '') . ($short ? T_('Pro#utype_short') : $text);
      $out[] = $tmp;
   }
   if( $usertype & USERTYPE_TEACHER )
   {
      $text = T_('Teacher');
      $tmp = ($img) ? image( "{$base_path}images/teacher.gif", $text, null ) : '';
      if( $short !== ARG_USERTYPE_NO_TEXT )
         $tmp .= ($img ? ' ' : '') . ($short ? T_('Teacher#utype_short') : $text);
      $out[] = $tmp;
   }
   if( $usertype & USERTYPE_ROBOT )
   {
      $text = T_('Robot');
      $tmp = ($img) ? image( "{$base_path}images/robot.gif", $text, null ) : '';
      if( $short !== ARG_USERTYPE_NO_TEXT )
         $tmp .= ($img ? ' ' : '') . ($short ? T_('Bot#utype_short') : $text);
      $out[] = $tmp;
   }
   if( $usertype & USERTYPE_TEAM )
   {
      $text = T_('Team');
      $tmp = ($img) ? image( "{$base_path}images/team.gif", $text, null ) : '';
      if( $short !== ARG_USERTYPE_NO_TEXT )
         $tmp .= ($img ? ' ' : '') . ($short ? T_('Team#utype_short') : $text);
      $out[] = $tmp;
   }
   return implode( $sep, $out );
}

// returns true if JavaScript allowed and current Player has it enabled
function is_javascript_enabled()
{
   global $player_row;
   return ( ALLOW_JAVASCRIPT && (@$player_row['UserFlags'] & USERFLAG_JAVASCRIPT_ENABLED) );
}

function start_html( $title, $no_cache, $skinname=NULL, $style_string=NULL, $last_modified_stamp=NULL )
{
   global $base_path, $encoding_used, $printable;

   if( $no_cache )
      disable_cache($last_modified_stamp);

   global $ThePage;
   if( !is_a($ThePage, 'HTMLPage') )
      ob_start('ob_gzhandler');

   if( empty($encoding_used) )
      $encoding_used = LANG_DEF_CHARSET;

   header('Content-Type: text/html;charset='.$encoding_used); // Character-encoding

   //This full DOCTYPE make most of the browsers to leave the "quirks" mode.
   //This may be a disavantage with IE5-mac because its "conform" mode is worst.
   echo '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"'
         .' "http://www.w3.org/TR/html4/loose.dtd">';

   echo "\n<HTML>\n<HEAD>";

   echo "\n <META http-equiv=\"content-type\" content=\"text/html;charset=$encoding_used\">";

   echo "\n <META NAME=\"DESCRIPTION\" CONTENT=\"To play go on a turn by turn basis.\">";

   echo "\n <TITLE>".basic_safe(FRIENDLY_SHORT_NAME." - $title")."</TITLE>";

   //because of old browsers favicon.ico should always stay in the root folder
   echo "\n <LINK REL=\"shortcut icon\" TYPE=\"image/x-icon\" HREF=\"".HOSTBASE."favicon.ico\">";

   global $main_path;
   if( !isset($skinname) || !$skinname )
      $skinname = 'dragon';
   if( !file_exists("{$main_path}skins/$skinname/screen.css") )
      $skinname = 'dragon';
   echo "\n <link rel=\"stylesheet\" type=\"text/css\" media=\"screen\" href=\"{$base_path}skins/$skinname/screen.css\">";
   if( !file_exists("{$main_path}skins/$skinname/print.css") )
      $skinname = 'dragon';
   echo "\n <link rel=\"stylesheet\" type=\"text/css\" media=\"print\" href=\"{$base_path}skins/$skinname/print.css\">";

   switch( (string)substr( @$_SERVER['PHP_SELF'], strlen(SUB_PATH)) )
   {
      case 'status.php':
         // RSS Autodiscovery:
         echo "\n <link rel=\"alternate\" type=\"application/rss+xml\""
             ," title=\"".FRIENDLY_SHORT_NAME." Status RSS Feed\" href=\"/rss/status.php\">";
         break;
   }

   if( $style_string )
      echo "\n <STYLE TYPE=\"text/css\">\n",$style_string,"\n </STYLE>";

   if( is_javascript_enabled() )
   {
      echo "\n<script language=\"JavaScript\" type=\"text/javascript\" src=\"{$base_path}js/common.js\"></script>";

      if( ALLOW_GOBAN_EDITOR )
         echo "\n<script language=\"JavaScript\" type=\"text/javascript\" src=\"{$base_path}js/goban_editor.js\"></script>";

      if( ALLOW_GO_DIAGRAMS )
      {
         echo "\n<script language=\"JavaScript\" type=\"text/javascript\" src=\"{$base_path}js/goeditor.js\"></script>";
         $version = 1;
         //echo "\n<script language=\"JavaScript\" type=\"text/javascript\"> version=$version; </script>"; //TODO
      }
   }

   if( is_a($ThePage, 'HTMLPage') )
   {
      $tmp = $ThePage->getClassCSS(); //may be multiple, i.e. 'Games Running'
      $tmp = ' class='.attb_quote($tmp);
   }
   else
      $tmp='';
   echo "\n</HEAD>\n<BODY id=\"".FRIENDLY_SHORT_NAME."\"$tmp>\n";
} //start_html

function start_page( $title, $no_cache, $logged_in, &$player_row,
                     $style_string=NULL, $last_modified_stamp=NULL )
{
   global $base_path, $is_down, $is_down_message, $is_maintenance, $ARR_USERS_MAINTENANCE, $printable;

   $user_handle = @$player_row['Handle'];
   if( $is_down && $logged_in )
      check_maintenance( $user_handle );

   start_html( $title, $no_cache, @$player_row['SkinName'], $style_string, $last_modified_stamp);

   echo_dragon_top_bar( $logged_in, $user_handle );

   if( !$printable ) // main-menu
   {
      if( $logged_in )
         $menu = make_dragon_main_menu( $player_row );
      else
      {
         $menu = make_dragon_main_menu_logged_out();
         $player_row['MenuDirection'] = 'VERTICAL'; //layout like menu_vertical without menu
      }

      $tools_array = make_dragon_tools();
   }

   if( $is_down || $printable )
   {
      //layout like menu_horizontal without menu
      $player_row['MenuDirection'] = 'HORIZONTAL';
      echo "\n<table id='pageLayout'>" //layout table
         . "\n <tr class=LayoutHorizontal>";
   }
   elseif( $player_row['MenuDirection'] == 'HORIZONTAL' )
   {
      //echo "\n  <td class=Menu>\n";
      make_menu_horizontal($menu); //outside layout table
      echo_menu_tools( $tools_array, 0);
      //echo "\n  </td></tr><tr>";
      echo "\n<table id='pageLayout'>" //layout table
         . "\n <tr class=LayoutHorizontal>";
   }
   else
   { // vertical
      echo "\n<table id='pageLayout'>" //layout table
         . "\n <tr class=LayoutVertical>"
         . "\n  <td class=Menu rowspan=2>\n";
      make_menu_vertical($menu); //inside layout table
      echo_menu_tools( $tools_array, 4);
      echo "\n  </td>";
   }
   //this <table><tr><td> is left open until page end
   echo "\n  <td id=\"pageBody\">\n\n";

   sysmsg(get_request_arg('sysmsg'));

   if( $is_down )
   {
      echo "<br><br>\n", $is_down_message, "<br><br><br>\n";
      end_page();
      exit;
   }
} //start_page

function echo_dragon_top_bar( $logged_in, $user_handle )
{
   global $base_path, $is_down, $printable, $is_maintenance;

   // forum for bookmark (around table for formatting)
   if( !$printable )
      echo '<form name="bookmarkForm" action="'.$base_path.'bookmark.php" method="GET">';

   echo "\n\n<table id=\"pageHead\">",
      "\n <tr>",
      "\n  <td class=\"ServerHome\"><A id=\"homeId\" href=\"".HOSTBASE."index.php\">",
      FRIENDLY_LONG_NAME."</A>";

   // show bookmarks
   if( !$printable && $logged_in && !$is_down )
   {
      echo '&nbsp;&nbsp;|&nbsp;&nbsp;',
         '<select name="jumpto" size="1"',
               ( is_javascript_enabled() ? " onchange=\"javascript:this.form['show'].click();\"" : '' ) . '>',
            '<option value="">&lt;' . T_('Bookmarks#bookmark') . '&gt;</option>',
            '<option value="S1">' . T_('Latest forum posts#bookmark') . '</option>',
            '<option value="S2">' . T_('Opponents online#bookmark') . '</option>',
            '<option value="S3">' . T_('Users online#bookmark') . '</option>',
            '<option value="S4">' . T_('Edit profile#bookmark') . '</option>',
         '</select>',
         '<input type="submit" name="show" value="' . T_('Show#bookmark') . '">'
         ;
   }

   if( $is_maintenance ) // mark also for maintainers
      echo '&nbsp;&nbsp;|&nbsp;&nbsp;<b>[ MAINTENANCE ]</b>';

   echo "</td>";
   echo "\n  <td class='LoginBox'>";

   if( $logged_in && !$is_down )
      echo T_("Logged in as"),
         ": <A id=\"loggedId\" href=\"{$base_path}status.php\">", $user_handle, '</A>';
   else
      echo T_("Not logged in");

   echo "</td>",
      "\n </tr>\n</table>\n";

   if( !$printable )
      echo '</form>';
} //echo_dragon_top_bar

function make_dragon_main_menu_logged_out()
{
   $menu = new Matrix(); // see make_dragon_main_menu()
   // object = arr( itemtext, itemlink [, arr( accesskey/class => value ) ]

   $menu->add( 1,1, array( T_('Login'),        'index.php?logout=t', array()));
   $menu->add( 1,2, array( T_('Register'),     'register.php',       array()));

   $menu->add( 2,1, array( T_('Introduction'), 'introduction.php', array()));
   $menu->add( 2,2, array( T_('Policy'),       'policy.php',       array()));
   $menu->add( 2,3, array( T_('FAQ'),          'faq.php',          array( 'accesskey' => ACCKEY_MENU_FAQ )));

   $menu->add( 3,1, array( T_('Docs'),         'docs.php',         array( 'accesskey' => ACCKEY_MENU_DOCS )));
   $menu->add( 3,2, array( T_('Site map'),     'site_map.php',     array()));
   $menu->add( 3,3, array( T_('Statistics'),   'statistics.php',   array()));

   return $menu;
} //make_dragon_main_menu_logged_out

function make_dragon_main_menu( $player_row )
{
   $cnt_msg_new = (isset($player_row['CountMsgNew'])) ? (int)$player_row['CountMsgNew'] : -1;
   $cnt_feat_new = (isset($player_row['CountFeatNew'])) ? (int)$player_row['CountFeatNew'] : -1;
   $has_forum_new = load_global_forum_new();

   $menu = new Matrix(); // keep x/y sorted (then no need to sort in make_menu_horizontal/vertical)
   // object = arr( itemtext, itemlink [, arr( accesskey/class => value ) ]
   // NOTE: row-number can be skipped, only for ordering
   // NOTE: multi-text per matrix-entry possible: use list of arrays with arr( itemtext ..) or sep-str
   $menu->add( 1,1, array( T_('Status'),       'status.php',       array( 'accesskey' => ACCKEY_MENU_STATUS, 'class' => 'strong' )));
   $menu->add( 1,2, array( T_('Waiting room'), 'waiting_room.php', array( 'accesskey' => ACCKEY_MENU_WAITROOM )));
   if( ALLOW_TOURNAMENTS )
      $menu->add( 1,3, array( T_('Tournaments'), 'tournaments/list_tournaments.php', array( 'accesskey' => ACCKEY_MENU_TOURNAMENT )));
   $menu->add( 1,4, array( T_('User info'),    'userinfo.php',     array( 'accesskey' => ACCKEY_MENU_USERINFO )));

   $arr_msgs = array( array( T_('Messages'), 'list_messages.php', array( 'accesskey' => ACCKEY_MENU_MESSAGES ) ));
   if( $cnt_msg_new > 0 )
   {
      $cnt_msg_new_str = sprintf( '<span class="MainMenuCount">(%s)</span>', $cnt_msg_new );
      $arr_msgs[] = '&nbsp;';
      $arr_msgs[] = array( $cnt_msg_new_str, 'list_messages.php?folder='.FOLDER_NEW, array( 'class' => 'MainMenuCount' ) );
   }
   $menu->add( 2,1, $arr_msgs );
   $menu->add( 2,2, array( T_('Send message'), 'message.php?mode=NewMessage', array( 'accesskey' => ACCKEY_MENU_SENDMSG )));
   $menu->add( 2,3, array( T_('Invite'),       'message.php?mode=Invite',     array( 'accesskey' => ACCKEY_MENU_INVITE )));
   $menu->add( 2,4, array( T_('New Game'),     'new_game.php',                array( 'accesskey' => ACCKEY_MENU_NEWGAME )));

   $menu->add( 3,1, array( T_('Users'),    'users.php',              array( 'accesskey' => ACCKEY_MENU_USERS )));
   $menu->add( 3,2, array( T_('Contacts'), 'list_contacts.php',      array( 'accesskey' => ACCKEY_MENU_CONTACTS )));
   $menu->add( 3,3, array( T_('Games'),    'show_games.php?uid=all', array( 'accesskey' => ACCKEY_MENU_GAMES )));

   $arr_forums = array( array( T_('Forums'), 'forum/index.php', array( 'accesskey' => ACCKEY_MENU_FORUMS )) );
   if( $has_forum_new )
   {
      $arr_forums[] = '&nbsp;';
      $arr_forums[] = array( '<span class="MainMenuCount">(*)</span>', 'bookmark.php?jumpto=S1', array( 'class' => 'MainMenuCount' ) );
   }
   $menu->add( 4,1, $arr_forums );
   $menu->add( 4,2, array( T_('FAQ'),      'faq.php',         array( 'accesskey' => ACCKEY_MENU_FAQ )));
   $menu->add( 4,3, array( T_('Site map'), 'site_map.php',    array()));
   $menu->add( 4,4, array( T_('Docs'),     'docs.php',        array( 'accesskey' => ACCKEY_MENU_DOCS )));

   if( ALLOW_FEATURE_VOTE )
   {
      $arr_feats = array( array( T_('Features'), 'features/list_votes.php', array( 'accesskey' => ACCKEY_MENU_VOTE )) );
      if( $cnt_feat_new > 0 )
      {
         $cnt_feat_new_str = sprintf( '<span class="MainMenuCount">(%s)</span>', $cnt_feat_new );
         $arr_feats[] = '&nbsp;';
         $arr_feats[] = array( $cnt_feat_new_str, 'features/list_features.php', array( 'class' => 'MainMenuCount' ) );
      }
      $menu->add( 5,1, $arr_feats );
   }
   if( ALLOW_GOBAN_EDITOR )
      $menu->add( 5,2, array( T_('Goban Editor'), 'goban_editor.php', array()));

   return $menu;
} //make_dragon_main_menu

function make_dragon_tools()
{
   global $base_path;

   $tools_array = array(); //$url => array($img,$alt,$title)
   $page = substr( @$_SERVER['PHP_SELF'], strlen(SUB_PATH));
   switch( (string)$page )
   {
      case 'status.php':
      {
         if( ENABLE_DONATIONS )
         {
            $tools_array['donation.php'] = array(
               $base_path.'images/donate.gif',
               T_('Donate'), T_('Support DGS with a donation') );
         }

         $tools_array['rss/status.php'] =
            array( $base_path.'images/rss-icon.png',
                   'RSS',
                   FRIENDLY_SHORT_NAME . ' ' . T_("Status RSS Feed") );
         break;
      }
   }

   return $tools_array;
} //make_dragon_tools

function end_page( $menu_array=NULL )
{
   global $page_microtime, $player_row, $base_path, $printable;

   section(); //close if any

   echo "\n  </td>"; //close the pageBody

   if( $menu_array && !$printable )
   {
      echo "\n </tr><tr class=Links>"
         . "\n  <td class=Links>";
      make_menu($menu_array);
      echo "\n  </td>";
   }

   //close the <table><tr><td> left open since page start
   echo "\n </tr>\n</table>\n";

   { //hostlink build
      if( HOSTNAME == "dragongoserver.sourceforge.net" ) //for devel server
         $hostlink= '<A href="http://sourceforge.net" target="_blank"><IMG src="http://sourceforge.net/sflogo.php?group_id=29933&amp;type=1" alt="SourceForge.net Logo" width=88 height=31 border=0 align=middle></A>';
      else //for live server
         $hostlink= '<a href="http://www.samurajdata.se" target="_blank"><img src="'.$base_path.'images/samurajlogo.gif" alt="Samuraj Logo" width=160 height=20 border=0 align=middle></a>';
   } //hostlink build


   global $NOW;
   echo "\n<table id='pageFoot'>"
      . "\n <tr>"
      . "\n  <td class=ServerHome><A href=\"{$base_path}index.php\">"
        . FRIENDLY_LONG_NAME."</A></td>";

   echo "\n  <td class=PageTime>"
        . T_("Page time") . ' <span id="pageTime">' . date(DATE_FMT, $NOW)
        . "</span>";

   if( !$printable && (@$player_row['AdminOptions'] & ADMOPT_SHOW_TIME) )
   {
      echo "<br><span class=PageLapse>"
        . T_('Page created in') . ' <span id="pageLapse">'
        . sprintf (' %0.2f ms', (getmicrotime() - $page_microtime)*1000)
        . "</span></span>";
   }

   echo "</td>";

   if( !$printable )
   {
      echo "\n  <td class=LoginBox>";

      if( @$player_row['admin_level'] && !$printable )
         echo "<a href=\"{$base_path}admin.php\">", T_('Admin'), "</a>&nbsp;&nbsp;&nbsp;";

      if( @$player_row['Translator'] && !$printable )
         echo anchor( $base_path.'translate.php',
                      T_('Translate'), '', array( 'accesskey' => ACCKEY_MENU_TRANSLATE ))
            , "&nbsp;&nbsp;&nbsp;";

      echo anchor( $base_path."index.php?logout=t",
                   ( $player_row['ID'] > 0 ) ? T_('Logout') : T_('Login'),
                   '', array( 'accesskey' => ACCKEY_MENU_LOGOUT ));

      echo "</td>",
           "\n </tr>",
           "\n</table>";

      // Start of a new host line
      echo "\n<table class=HostedBy>",
           "\n <tr>";
   }

   //continuation of host line
   echo "\n  <td id='hostedBy'>Hosted by&nbsp;&nbsp;$hostlink</td>";

   echo "\n </tr>",
        "\n</table>";

   end_html();
} //end_page

function end_html()
{
   if( isset($TheErrors) )
   {
      if( $TheErrors->error_count() )
         echo $TheErrors->list_string('garbage', 1);
   }
   echo "\n</BODY>\n</HTML>";
   ob_end_flush();
} //end_html

//push a level in the output stack
function grab_output_start( $compressed=0)
{
   if( $compressed )
      return ob_start('ob_gzhandler');
   else
      return ob_start();
}

//grab the output buffer into a file
//also copy it in the previous level of the output stack
function grab_output_end( $filename='')
{
   if( !$filename )
   {
      ob_end_flush(); //also copy it
      return false;
   }
   $tmp= ob_get_contents();//grab it
   ob_end_flush(); //also copy it
   return write_to_file( $filename, $tmp);
}

/*!
 * \brief Sets $is_down if given user-handle is a maintenance-allowed user (see 'include/config-local.php'.
 * \return true, if given user is a maintenance-allowed user
 */
function check_maintenance( $user_handle )
{
   global $is_down, $ARR_USERS_MAINTENANCE;

   $is_maint_user = ( is_array($ARR_USERS_MAINTENANCE) && in_array( $user_handle, $ARR_USERS_MAINTENANCE ) );
   if( $is_down && $is_maint_user )
      $is_down = false;
   return $is_maint_user;
}


// make bottom page-links
//   supported formats in $menu_array:
//    linktext  => URL
//    linktext  => array( 'url' => URL, attb1 => val1, ... )
//    dummytext => Form-object
function make_menu($menu_array, $with_accesskeys=true)
{
   global $base_path, $max_links_in_main_menu;

   echo "\n\n<table id=\"pageLinks\" class=Links>\n <tr>";

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

      echo "\n  <td width=\"$width%\">";
      if( is_a($link, 'Form') )
         echo $link->echo_string();
      else
         echo make_menu_link( $text, $link, $i % 10 );
      echo '</td>';

      $cumwidth += $width;
   }

   echo "\n </tr>\n</table>\n";
}

// array( 'url' => URL, attb1 => val1, ... ) => <a href=...>text</a>
// accesskey will be overwritten by optional accesskey in link-array
function make_menu_link( $text, $link, $accesskey='' )
{
   global $base_path;

   $attbs = array();
   if( (string)$accesskey != '' )
      $attbs['accesskey'] = $accesskey;
   if( is_array($link) )
   {
      $url = $link['url'];
      unset( $link['url'] );
      $attbs += $link;
   }
   else
      $url = $link;

   return anchor( $base_path . $url, $text, '', $attbs );
}

function make_menu_horizontal($menu)
{
   global $base_path; //, $menu_bg_color;

/*
   //table for top line
   echo "\n<table class=NotPrintable width=\"100%\" border=0 cellspacing=0 cellpadding=0 bgcolor=$menu_bg_color>"
      . "\n <tr>"
      . "\n  <td>";
*/


   echo "\n<table id=\"pageMenu\" class=MenuHorizontal>"
      . "\n <tr>";

   $cols = $menu->get_info(MATRIX_MAX_X);
   $rows = $menu->get_info(MATRIX_MAX_Y);
   $b = $w = 100/($cols+2); //two icons + col-num = 100% width
   $w = floor($w); // width% for col #2..n
   $b = 100-$w*($cols+1); // width% for first col

   // left logo
   $logo_line =
      "\n  <td width=\"%d%%\" class=\"%s\" rowspan=\"%d\"><img src=\"{$base_path}images/%s\" alt=\"Dragon\"></td>";
   echo sprintf( $logo_line, $b, 'Logo1', $rows, 'dragonlogo_bl.jpg');

   for( $row=1; $row <= $rows; $row++ )
   {
      for( $col=1; $col <= $cols; $col++ )
      {
         $menuitem = $menu->get_entry( $col, $row );
         if( !is_null($menuitem) ) // matrix-point unset
         {
            if( is_array($menuitem[0]) )
            {
               // object = arr( sep-string | arr( itemtext, itemlink [, arr( accesskey/class => value ) ] ), ...) for multi-items
               $content = '';
               foreach( $menuitem as $mitem )
               {
                  if( is_array($mitem) )
                  {//item-arr
                     @list( $text, $link, $attbs ) = $mitem;
                     $content .= anchor( $base_path.$link, $text, '', $attbs);
                  }
                  else //separator
                     $content .= $mitem;
               }
            }
            else
            {
               // object = arr( itemtext, itemlink, arr( accesskey/class => value ))
               @list( $text, $link, $attbs ) = $menuitem;
               $content = anchor( $base_path.$link, $text, '', $attbs);
            }
         }
         else
            $content = '';
         echo "\n  <td width=\"$w%\">$content</td>";
      }

      // right logo
      if( $row == 1 )
         echo sprintf( $logo_line, $w, 'Logo2', $rows, 'dragonlogo_br.jpg');
      if( $row < $rows )
         echo "\n </tr><tr>";
   }

   echo "\n </tr>\n</table>\n";

/*
   //table for bottom line
   echo "\n  </td>"
      . "\n </tr><tr>"
      . "\n  <td height=1><img src=\"{$base_path}images/dot.gif\" width=1 height=1 alt=\"\"></td>"
      . "\n </tr>\n</table>\n";
*/
}

function make_menu_vertical($menu)
{
   global $base_path; //, $menu_bg_color;

/*
   //table for border line
   echo "\n<table class=NotPrintable border=0 cellspacing=0 cellpadding=1 bgcolor=$menu_bg_color>"
      . "\n <tr>"
      . "\n  <td>";
*/


   echo "\n<table id=\"pageMenu\" class=MenuVertical>"
      . "\n <tr>";

   echo "\n  <td class=Logo1><img src=\"{$base_path}images/dragonlogo_bl.jpg\" alt=\"Dragon\"></td>"
      . "\n </tr><tr>"
      . "\n  <td align=left nowrap>";

   $cntX = $menu->get_info(MATRIX_MAX_X);
   for( $x=1; $x <= $cntX; $x++ )
   {
      if( $x > 1 )
          echo '</td>'
             . "\n </tr><tr>"
             . "\n  <td height=1><img height=1 src=\"{$base_path}images/dot.gif\" alt=\"\"></td>"
             . "\n </tr><tr>"
             . "\n  <td align=left nowrap>";

      $menuitems = $menu->get_y_entries( $x );
      foreach( $menuitems as $menuitem )
      {
         if( is_array($menuitem[0]) )
         {
            // object = arr( sep-string | arr( itemtext, itemlink [, arr( accesskey/class => value ) ] ), ...) for multi-items
            foreach( $menuitem as $mitem )
            {
               if( is_array($mitem) )
               {//item-arr
                  @list( $text, $link, $attbs ) = $mitem;
                  echo anchor( $base_path.$link, $text, '', $attbs);
               }
               else //separator
                  echo $mitem;
            }
         }
         else
         {
            // object = arr( itemtext, itemlink, arr( accesskey/class => value ))
            @list( $text, $link, $attbs ) = $menuitem;
            echo anchor( $base_path.$link, $text, '', $attbs);
         }
         echo '<br>';
      }
   }

   echo '</td>'
      . "\n </tr><tr>"
      . "\n  <td class=Logo2><img src=\"{$base_path}images/dragonlogo_br.jpg\" alt=\"Dragon\"></td>"
      . "\n </tr>"
      . "\n</table>\n";

/*
   //table for border line
   echo "\n  </td>"
      . "\n </tr>\n</table>\n";
*/
}

function echo_menu_tools( $array, $width=0)
{
   if( !is_array($array) || count($array)==0 )
      return;
   echo "<table class=NotPrintable id='pageTools'>\n<tr>\n";
   $c= 0;
   $r= 1;
   foreach( $array as $lnk => $sub )
   {
      list( $src, $alt, $tit) = $sub;
      if( $width>0 && $c>=$width )
      {
         echo "</tr><tr>\n";
         $c= 1;
         $r++;
      }
      else
         $c++;
      echo '<td>'.anchor( $lnk, image( $src, $alt, $tit))."</td>\n";
   }
   $c= $width-$c;
   if( $r>1 && $c>0 )
   {
      if( $c>1 )
         echo "<td colspan=$c></td>\n";
      else
         echo "<td></td>\n";
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
   if( isset($msg) && ($msg=trim(make_html_safe($msg,'msg'))) )
      echo "\n<p class=SysMsg>$msg</p><hr class=SysMsg>\n";
}



//must never allow quotes, ampersand, < , > and URI reserved chars
//to be used in a preg_exp enclosed by [] and possibly by ()
define('HANDLE_LEGAL_REGS', '\\-_a-zA-Z0-9'); // '+' allowed in old ages, but not anymore for new handles
define('HANDLE_TAG_CHAR', '='); //not in: HANDLE_LEGAL_REGS or < or >
define('PASSWORD_LEGAL_REGS', HANDLE_LEGAL_REGS.'\\.\\?\\*\\+,;:!%');

function illegal_chars( $string, $punctuation=false )
{
   if( $punctuation )
      $regs = PASSWORD_LEGAL_REGS;
   else
      $regs = 'a-zA-Z]['.HANDLE_LEGAL_REGS; //begins with a letter

   return !preg_match( "/^[$regs]+\$/", $string);
}

function make_session_code()
{
   mt_srand((double)microtime()*1000000);
   $n = 41; //size of the MySQL 4.1 PASSWORD() result.
   $s = '';
   for( $i=$n; $i>0; $i-=6 )
      $s.= sprintf("%06X",mt_rand(0,0xffffff));
   return substr($s, 0, $n);
}

function random_letter()
{
   $c = mt_rand(0,61);
   if( $c < 10 )
      return chr( $c + ord('0'));
   elseif( $c < 36 )
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

/*!
 * \brief Checks if given email is valid.
 *
 * \note An email address validity function should never be treated as definitive.
 *       In more simple terms, if a user-supplied email address fails a validity check,
 *       don't tell the users the email address they entered is invalid
 *       but just force them to enter something different.
 *
 * \return true if email is valid; otherwise an error('bad_mail_address')
 *       is called (if debugmsg given) or else error-code is returned instead
 */
function verify_email( $debugmsg, $email, $die_on_error=true )
{
   //RFC 2822 - 3.4.1. Addr-spec specification
   //See http: //www.faqs.org/rfcs/rfc2822
   //$regexp = "^[a-z0-9]+([_.-][a-z0-9]+)*@([a-z0-9]+([.-][a-z0-9]+)*)+\\.[a-z]{2,4}$";
   $regexp = "/^([-_a-z0-9]+)(\\.[-_a-z0-9]+)*@([-a-z0-9]+)(\\.[-a-z0-9]+)*(\\.[a-z]{2,4})\$/i";
   $res= preg_match($regexp, $email);
   if( !$res ) // invalid email
   {
      if( $die_on_error )
      {
         if( $is_string($debugmsg) )
            error('bad_mail_address', "$debugmsg=$email");
      }
      else
         return 'bad_mail_address';
   }
   return true;
}


// format-option for send_email()
define('EMAILFMT_SKIP_WORDWRAP', 0x01); // skipping word-wrapping

/**
 * \brief Sends email to one or multiple recipients.
 * \param $email may be:
 *     - user@example.com
 *     - user.com, anotheruser@example.com
 *     - User <user@example.com>
 *     - User <user@example.com>, Another User <anotheruser@example.com>
 *   or an array of those.
 * \param $formatopts format-options for email: 0=none, EMAILFMT_SKIP_WORDWRAP
 * \param $subject default => FRIENDLY_LONG_NAME.' notification';
 * \param $headers default => 'From: '.EMAIL_FROM;
 * \param $params optional command-line paramenters for mail-command (may differ);
 *     none per default
 **/
function send_email( $debugmsg, $email, $formatopts, $text, $subject='', $headers='', $params='')
{
   if( !$subject )
      $subject = FRIENDLY_LONG_NAME.' notification';
   $subject= preg_replace("/[\\x01-\\x20]+/", ' ', $subject);

   $rgx= array("/\r\n/","/\r/");
   $rpl= array("\n","\n");
   $text= preg_replace( $rgx, $rpl, $text);
   if( !($formatopts & EMAILFMT_SKIP_WORDWRAP) )
      $text= wordwrap( $text, 70, "\n", 1);

   /**
    * How to break the lines of an email ? CRLF.
    * http://cr.yp.to/docs/smtplf.html
    * http://www.ietf.org/rfc/rfc0822.txt
    * Any problems may be platform dependent. Maybe:
    * switch( (string)strtoupper(substr(PHP_OS, 0, 3)) ) {
    *   case 'WIN': $eol= "\r\n"; break;
    *   case 'MAC': $eol= "\r"; break;
    *   default: $eol= "\n"; break;
    * }
    * $text= str_replace( nl2br("\n"), $eol, nl2br($text) );
    **/
   $eol= "\r\n"; //desired one for emails

   switch( (string)$eol )
   {
      default:
         $eol = "\r\n";
      case "\r":
         $text = preg_replace( "/\n/", $eol, $text);
      case "\n":
         break;
   }
   $text= trim($text).$eol;

   $rgx= array("/[\r\n]+/");
   $rpl= array($eol);

   $headers= trim($headers);
   if( !$headers )
   {
      $headers = 'From: '.EMAIL_FROM;
      //if HTML in mail allowed:
      //$headers.= "\nMIME-Version: 1.0";
      //$headers.= "\nContent-type: text/html; charset=iso-8859-1";
   }
   $headers= preg_replace( $rgx, $rpl, trim($headers)); //.$eol;

   $params= trim($params);
   if( $params )
      $params= preg_replace( $rgx, $rpl, trim($params)); //.$eol;

   if( is_array($email) )
      $email = implode( ',', $email);

   if( function_exists('mail') )
      $res= @mail( $email, $subject, $text, $headers, $params);
   else
      $res= false;

   if( is_string($debugmsg) && !$res )
      error('mail_failure', "$debugmsg=$email - $subject");
   return $res;
}

/**
 * $text and $subject must NOT be escaped by mysql_escape_string()
 * $to_ids and $to_handles have been splitted because, historically, some handles
 *  may seems to be numeric (e.g. '00000') as their first char may be a digit.
 *  In fact, both are treated like strings or arrays here.
 * if $from_id <= 0, the message will be a system message
 * if $from_id is in the $to_ids list, the message will be a message to myself
 * if $prev_mid is >0, the previous (answered) message will be flaged as Replied,
 *  set to the type $prev_type and moved to the folder $prev_folder.
 **/
function send_message( $debugmsg, $text='', $subject=''
            , $to_ids='', $to_handles='', $notify=true
            , $from_id=0, $type='NORMAL', $gid=0
            , $prev_mid=0, $prev_type='', $prev_folder=FOLDER_NONE
            )
{
   global $NOW;

   if( is_string($debugmsg) )
      $debugmsg.= '.send_message';

   $text = mysql_addslashes(trim($text));
   $subject = mysql_addslashes(trim($subject));
   if( (string)$subject == '' )
      $subject = T_('(no subject)');

   if( !isset($type) || !is_string($type) || !$type )
      $type = 'NORMAL';
   if( !isset($gid) || !is_numeric($gid) || $gid<0 )
      $gid = 0;
   if( !isset($from_id) || !is_numeric($from_id) || $from_id <= GUESTS_ID_MAX ) //exclude guest
      $from_id = 0; //i.e. server message
   if( !isset($prev_mid) || !is_numeric($prev_mid) || $prev_mid < 0 )
      $prev_mid = 0;

   $to_myself= false;
   $receivers= array();
   //if( eregi( 'mysql', get_resource_type($to_ids)) )
   foreach( array( 'ID' => &$to_ids, 'Handle' => &$to_handles ) as $field => $var )
   {
      if( is_array($var) )
         $var= implode(',', $var);
      $var= preg_split('/[\s,]+/', $var);
      $varcnt= count($var);
      if( $varcnt <= 0 )
         continue;

      $var= implode("','", ( $field == 'ID' ? $var : array_map('mysql_addslashes', $var) ));
      if( !$var )
         continue;

      $query= "SELECT ID,Notify,SendEmail FROM Players WHERE $field IN ('$var') LIMIT $varcnt";
      $result = db_query( "$debugmsg.get$field($var)", $query);
      while( ($row=mysql_fetch_assoc($result)) )
      {
         $uid= $row['ID'];
         if( $from_id > 0 && $uid == $from_id )
            $to_myself= true;
         elseif( $uid > GUESTS_ID_MAX ) //exclude guest
            $receivers[$uid]= $row;
      }
      mysql_free_result($result);
   }
   $reccnt= count($receivers);
   //TODO: handle $reccnt != $varcnt ???
   if( !$to_myself && $reccnt <= 0 )
      error('receiver_not_found', "$debugmsg.rec0($from_id,$subject)");

   /**
    * Actually, only the messages from server can have multiple
    * receivers because they are NOT read BY the server.
    * The code to display a message can't manage more than one
    * correspondent.
    * See also: message.php
    **/
   if( $from_id > 0 && $reccnt+($to_myself?1:0) > 1 )
      error('receiver_not_found', "$debugmsg.rec1($from_id,$subject)");

   //actually not supported: sending a message to myself and other in the same pack
   if( $to_myself && $reccnt > 0 )
      error('internal_error', "$debugmsg.rec2($from_id,$subject)");

   // determine message-thread info
   $thread = 0;
   $thread_level = 0;
   if( $prev_mid > 0 )
   {
      $prev_msgrow = mysql_single_fetch( "$debugmsg.find_prev($from_id,$prev_mid)",
         "SELECT Thread, Level FROM Messages WHERE ID=$prev_mid LIMIT 1" );
      if( is_array($prev_msgrow) )
      {
         $thread = $prev_msgrow['Thread'];
         $thread_level = $prev_msgrow['Level'] + 1;
      }
   }

   $query= "INSERT INTO Messages SET Time=FROM_UNIXTIME($NOW)"
          .", Type='$type', Thread='$thread', Level='$thread_level'"
          .", ReplyTo=$prev_mid, Game_ID=$gid"
          .", Subject='$subject', Text='$text'" ;
   db_query( "$debugmsg.message", $query);

   if( mysql_affected_rows() != 1 )
      error('mysql_insert_message', "$debugmsg.message");

   $mid = mysql_insert_id();
   ksort($receivers);

   $query= array();
   $receivers_folder_new = array();
   if( $from_id > 0 ) //exclude system messages (no sender)
   {
      if( $to_myself )
      {
         $query[]= "$mid,$from_id,'M','N',".FOLDER_NEW;
         $receivers_folder_new[] = $from_id;
      }
      else
         $query[]= "$mid,$from_id,'Y','N',".FOLDER_SENT;
   }

   if( $from_id > 0 && $thread == 0 )
   {
      db_query( "$debugmsg.upd_thread($mid,$from_id)",
         "UPDATE Messages SET Thread='$mid', Level=0 WHERE ID='$mid' LIMIT 1" );
   }

   $need_reply= ( ($from_id > 0 && $type == 'INVITATION') ?'M' :'N' );

   foreach( $receivers as $uid => $row ) //exclude to myself
   {
      if( $from_id > 0 )
         $query[]= "$mid,$uid,'N','$need_reply',".FOLDER_NEW;
      else //system messages
         $query[]= "$mid,$uid,'S','N',".FOLDER_NEW;
      $receivers_folder_new[] = $uid;
   }

   $cnt= count($query);
   if( $cnt > 0 )
   {
      $query= "INSERT INTO MessageCorrespondents"
             ." (mid,uid,Sender,Replied,Folder_nr) VALUES"
             .' ('.implode('),(', $query).")";
      db_query( "$debugmsg.correspondent", $query );

      if( mysql_affected_rows() != $cnt )
         error('mysql_insert_message', "$debugmsg.correspondent");
   }

   // update receivers new-message counter
   if( $cnt_fnew = count($receivers_folder_new) )
   {
      $ids = implode( ',', $receivers_folder_new );
      db_query( "$debugmsg.count_msg_new([$ids])",
         "UPDATE Players SET CountMsgNew=CountMsgNew+1 WHERE ID IN ($ids) LIMIT $cnt_fnew" );
   }

   //records the last message of the invitation/dispute sequence
   //the type of the previous messages will be changed to 'DISPUTED'
   if( $gid > 0 && ( $type == 'INVITATION' ) )
   {
      db_query( "$debugmsg.game_message",
         "UPDATE Games SET mid='$mid' WHERE ID='$gid' LIMIT 1" );
   }

   if( $from_id > 0 && $prev_mid > 0 ) //is this an answer?
   {
      $query = "UPDATE MessageCorrespondents SET Replied='Y'";
      if( $prev_folder > FOLDER_ALL_RECEIVED )
         $query .= ", Folder_nr=$prev_folder";
      $query.= " WHERE mid=$prev_mid AND uid=$from_id AND Sender!='Y' LIMIT 1";
      db_query( "$debugmsg.reply_correspondent", $query );

      if( $prev_type )
      {
         db_query( "$debugmsg.reply_message",
            "UPDATE Messages SET Type='$prev_type' WHERE ID=$prev_mid LIMIT 1" );
      }
   }

   if( $notify ) //about message!
   {
      $ids= array();
      foreach( $receivers as $uid => $row )
      {
         if( $row['Notify'] == 'NONE'
               && is_numeric(strpos($row['SendEmail'], 'ON'))
               //&& is_numeric(strpos($row['SendEmail'], 'MESSAGE'))
               )
            $ids[]= $uid;
      }
      $cnt= count($ids);
      if( $cnt > 0 )
      {
         $ids= implode(',', $ids);
         db_query( "$debugmsg.mess_notify",
            "UPDATE Players SET Notify='NEXT'"
            ." WHERE ID IN ($ids) AND Notify='NONE'"
            ." AND FIND_IN_SET('ON',SendEmail)"
            //." AND FIND_IN_SET('MESSAGE',SendEmail)"
            //." AND SendEmail LIKE '%ON%'"
            // LIMIT $cnt
            );
      }
   }

   return $mid; //>0: no error
} //send_message

//$type is one element of the SET of SendEmail ('MOVE','MESSAGE' ...)
//start the notification process for thoses from $ids having $type set.
function notify( $debugmsg, $ids, $type='')
{
   if( !is_array($ids) )
      $ids= array( $ids);
   $query = array();
   foreach( $ids as $cnt )
      $query = array_merge( $query, explode(',', $cnt));
   $ids = array();
   foreach( $query as $cnt )
      if( ($cnt=(int)$cnt) > GUESTS_ID_MAX ) //exclude guest
         $ids[$cnt] = $cnt;

   $cnt= count($ids);
   if( $cnt <= 0 )
      return 'no IDs';

   $ids= implode(',', $ids);
   db_query( "$debugmsg.notify",
      "UPDATE Players SET Notify='NEXT'"
      ." WHERE ID IN ($ids) AND Notify='NONE'"
      ." AND FIND_IN_SET('ON',SendEmail)"
      .($type ? " AND FIND_IN_SET('$type',SendEmail)" : '')
      //." AND SendEmail LIKE '%ON%'"
      // LIMIT $cnt
      );

   return ''; //no error
} //notify


function safe_setcookie($name, $value='', $rel_expire=-3600)
//should be: ($name, $value, $expire, $path, $domain, $secure)
{
   global $NOW;

/*
   if( COOKIE_OLD_COMPATIBILITY )
      setcookie( $name, '', $NOW-3600, SUB_PATH);
*/

   $name= COOKIE_PREFIX.$name;

   //remove duplicated cookies sometime occuring with some browsers
   if( $tmp= @$_SERVER['HTTP_COOKIE'] )
      $n= preg_match_all(';'.$name.'[\\x01-\\x20]*=;i', $tmp, $dummy);
   else
      $n= 0;

   while( $n>1 ) {
      setcookie( $name, '', $NOW-3600, SUB_PATH);
      $n--;
   }
   setcookie( $name, $value, $NOW+$rel_expire, SUB_PATH );
   //for current session:
   $_COOKIE[$name] = $value; //??? add magic_quotes_gpc like slashes?
} //safe_setcookie

function set_login_cookie($handl, $code, $delete=false)
{
   if( $delete || !$handl || !$code)
   {
      safe_setcookie('handle');
      safe_setcookie('sessioncode');
   }
   else
   {
      safe_setcookie('handle', $handl, 5 * SESSION_DURATION);
      safe_setcookie('sessioncode', $code, SESSION_DURATION);
   }
} //set_login_cookie

function set_cookie_prefs(&$player_row, $delete=false)
{
   $uid = (int)@$player_row['ID'];
   //assert('$uid>0');
   if( $uid <= 0 ) return;

   global $cookie_prefs;

   if( $delete )
      safe_setcookie("prefs$uid");
   else
      safe_setcookie("prefs$uid", serialize($cookie_prefs), 3600*12*61*12*5); //5 years
} //set_cookie_prefs

function get_cookie_prefs(&$player_row)
{
   $uid = (int)@$player_row['ID'];
   //assert('$uid>0');
   if( $uid <= 0 ) return;

   global $cookie_prefs, $cookie_pref_rows;

   $cookie_prefs = unserialize( safe_getcookie("prefs$uid") );
   if( !is_array( $cookie_prefs ) )
      $cookie_prefs = array();

   foreach( $cookie_prefs as $key => $value )
   {
      if( in_array($key, $cookie_pref_rows) )
         $player_row[$key] = $value;
   }
} //get_cookie_prefs

/*!
 * \brief Changes admin-status for user and specified admin-mask: set/unset/toggle status.
 * \param $cmd command to set/unset/toggle status: y/Y/+ (=sets status), n/N/- (=unsets status), x/X/* (=toggle status)
 * \return  1 (=admin-status granted and active),
 *          0 (=admin-status granted, but inactive),
 *         -1 (=status not granted, admin-level not sufficient)
 *         -2 (=get admin-status from cookies, but at most grant admin-level of user)
 *         -3 (=invalid user specified)
 */
function switch_admin_status(&$player_row, $mask=0, $cmd='')
{
   $uid = (int)@$player_row['ID'];
   //assert('$uid>0');
   if( $uid <= 0 ) return -3;

   $cookie = "status$uid";
   $level = (int)@$player_row['admin_level'];
   if( !$mask )
   {
      $status = $level & (int)safe_getcookie($cookie);
      $player_row['admin_status'] = $status;
      return -2;
   }
   if( ($level & $mask) == 0 )
      return -1; //not granted

   $status = $level & (int)@$player_row['admin_status'];
   $old = $status;
   switch( (string)strtolower($cmd) )
   {
      case 'y': case '+': $status |= $mask; break; //set
      case 'n': case '-': $status &=~$mask; break; //unset
      case 'x': case '*': $status ^= $mask; break; //toggle
      //default: break; //just return status
   }
   if( $old != $status )
   {
      if( $status )
         safe_setcookie( $cookie, $status, 3600);
      else
         safe_setcookie( $cookie);
      $player_row['admin_status'] = $status;
   }
   if( ($status & $mask) == $mask )
      return 1; //active
   return 0; //granted but inactive
} //switch_admin_status


function add_line_breaks( $str)
{
   $str = trim($str);

   // Strip out carriage returns
   $str=preg_replace('/[\\x01-\\x09\\x0B-\\x20]*\\x0A/','<BR>', $str);

   // Handle collapsed vertical white spaces
   for( $i=0; $i<2; $i++)
      $str = preg_replace('%[\\x01-\\x20]*<(BR|P)[\\x01-\\x20]*/?\>[\\x01-\\x20]*<(BR|P)[\\x01-\\x20]*/?\>%i','<\\1>&nbsp;<\\2>', $str);

   return $str;
}



// Some regular allowed html tags. Keep them lower case.
// 'cell': tags that does not disturb a table cell. Mostly decorations.
// 'line': tags that does not disturb a line layout. Mostly 'cell' + <a> tag.
// 'msg': tags allowed in messages
// 'game': tags allowed in game messages
// 'faq': tags allowed in the FAQ pages
//Warning: </br> was historically used in end game messages. It remains in database.

// ** keep them lowercase and do not use parenthesis **
  // ** keep a '|' at both ends (or empty):
$html_code_closed['cell'] = '|note|b|i|u|strong|em|tt|color|';
$html_code_closed['line'] = '|home_|home|a'.$html_code_closed['cell'];
$html_code_closed['msg'] = '|center|ul|ol|font|pre|code|quote|igoban'.$html_code_closed['line'];
$html_code_closed['game'] = '|h|hidden|c|comment'.$html_code_closed['msg'];
//$html_code_closed['faq'] = ''; //no closed check
$html_code_closed['faq'] = $html_code_closed['msg']; //minimum closed check
  // more? '|/li|/p|/br|/ *br';

  // ** no '|' at ends:
$html_code['cell'] = 'note|b|i|u|strong|em|tt|color';
$html_code['line'] = 'home|a|'.$html_code['cell'];
$html_code['msg'] = 'br|/br|p|/p|li'.$html_code_closed['msg']
   .'goban|mailto|https?|news|game_?|tourney_?|user_?|send_?|image';
$html_code['game'] = 'br|/br|p|/p|li'.$html_code_closed['game']
   .'goban|mailto|https?|news|ftp|game_?|tourney_?|user_?|send_?|image';
$html_code['faq'] = '\w+|/\w+'; //all not empty words


//** no reg_exp chars nor ampersand nor '%' (see also $html_safe_preg):
define( 'ALLOWED_LT', "``a`n`g`l``");
define( 'ALLOWED_GT', "``a`n`g`g``");
define( 'ALLOWED_QUOT', "``q`u`o`t``");
define( 'ALLOWED_APOS', "``a`p`o`s``");

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

   while( !$bad )
   {
      $i = strcspn($trail, $seps[$quote]);
      $c = substr($trail,$i,1);
      if( $c=='' || $c=='<' )
      {
         $head.= substr($trail,0,$i);
         $trail = substr($trail,$i);
         $bad = 1;
         break;
      }
      elseif( $c=='>' )
      {
         $head.= substr($trail,0,$i);
         $trail = substr($trail,$i+1);
         break;
      }
      elseif( $quote )
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
   if( $quote )
   {
      $head.= $quote;
      $bad = 1;
   }
   if( !$bad && $head )
   {
/* TODO check for newer/more attributes!
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
      if( /*$quote &&*/  preg_match( "%($quote)%i", preg_replace( "/[\\x01-\\x1f]+/", '', $head)) )
         $bad = 2;
   }
   if( $bad )
   {
      $head = str_replace(ALLOWED_QUOT, '"', $head);
      $head = str_replace(ALLOWED_APOS, "'", $head);
   }
   return $head;
}


/**
 * Simple check of elements' attributes and inner text. Recursive.
 * >>> Don't call it: use parse_html_safe()
 * If an element is allowed and correctly closed,
 *  validate it by subtituing its '<' and '>' with ALLOWED_LT and ALLOWED_GT.
 * Check up to the <$stop > tag (supposed to be the closing tag).
 * If $stop=='', check up to the end of string $trail.
 * The global $parse_mark_regex must be the well-formed preg-exp of the marked terms.
 *
 * \param $html_code, $html_code_closed seems to be refs because of speed !?
 **/
$parse_mark_regex = ''; //global because parse_tags_safe() is recursive
define('PARSE_MARK_TERM',
      ALLOWED_LT.'span class=MarkTerm'.ALLOWED_GT.'\\1'.ALLOWED_LT.'/span'.ALLOWED_GT);
define('PARSE_MARK_TAGTERM',
      ALLOWED_LT.'span class=MarkTagTerm'.ALLOWED_GT.'&lt;\\1&gt;'.ALLOWED_LT.'/span'.ALLOWED_GT);
function parse_tags_safe( &$trail, &$bad, &$html_code, &$html_code_closed, $stop)
{
   if( !$trail )
      return '';

   global $parse_mark_regex;
   $before = '';
   //$stop = preg_quote($stop, '%');
   $reg = ( $html_code ) ? ( $stop ? $stop.'|' : '' ) . $html_code : $stop;
   if( !$reg )
      return '';
   //enclosed by '%' because $html_code may contain '/'
   //FIXME(?) ... and $html_code can not contain '%' too ?
   $reg = "%^(.*?)<($reg)\\b(.*)$%is";

   while( preg_match($reg, $trail, $matches) )
   {
      $marks = $matches[1] ;
      if( $parse_mark_regex && PARSE_MARK_TERM && $marks )
         $marks = preg_replace( $parse_mark_regex, PARSE_MARK_TERM, $marks);
      $before.= $marks;
      $tag = strtolower($matches[2]) ; //Warning: same case as $html_code
      if( $tag == '/br' ) $tag = 'br' ; //historically used in end game messages.
      $endtag = ( substr($tag,-1,1) == '_' ) ? substr($tag,0,-1) : $tag;
      $trail = $matches[3] ;
      unset($matches);

      $head = $tag . parse_atbs_safe( $trail, $bad) ;
      $marks = '';
      if( $parse_mark_regex && PARSE_MARK_TAGTERM && $head )
      {
         if( preg_match_all( $parse_mark_regex, $head, $tmp) )
         {
            $marks = textarea_safe( implode('|', $tmp[1]), 'iso-8859-1'); //LANG_DEF_CHARSET);
            $marks = str_replace( '\\1', $marks, PARSE_MARK_TAGTERM);
         }
      }
      if( $bad)
         return $before .$marks .'<'. $head .'>' ;

      $head = preg_replace('/[\\x01-\\x20]+/', ' ', $head);
      if( in_array($tag, array(
            //as a first set/choice of <ul>-like tags
            'quote','code','pre','center',
            'dl','/dt','/dd','ul','ol','/li',
         )) )
      { //remove all the following newlines (to avoid inserted <br>)
         $trail= preg_replace( "/^[\\r\\n]+/", '', $trail);
      }
      elseif( in_array($tag, array(
            //as a first set/choice of </ul>-like tags
            '/quote','/code','/pre','/center',
            '/dl','/ul','/ol','/note','/div',
         )) )
      { //remove the first following newline
         $trail= preg_replace( "/^(\\r\\n|\\r|\\n)/", '', $trail);
      }

      if( $stop == $tag )
         return $before .ALLOWED_LT. $head .ALLOWED_GT .$marks; //mark after

      $before.= $marks; //mark before
      $to_be_closed = is_numeric(strpos($html_code_closed,'|'.$tag.'|')) ;
      if( $tag == 'code' )
      {
         // does not allow inside HTML
         $tmp= '';
         $inside = parse_tags_safe( $trail, $bad, $tmp, $tmp, '/'.$tag);
         if( $bad)
            return $before .'<'. $head .'>'. $inside ;
         $inside = str_replace('&', '&amp;', $inside);
         //TODO: fix possible corrupted marks... to be reviewed
         //TODO: can't use nested <code>-tags
         $inside = preg_replace(
            '%(class=Mark[^`]*'.ALLOWED_GT.')&amp;(lt;[^&]*)&amp;(gt;)%',
            '\\1&\\2&\\3',
            $inside);
      }
      elseif( $tag == 'tt' )
      {
         // TT is mainly designed to be used when $some_html=='cell'
         // does not allow inside HTML and remove line breaks
         $tmp= '';
         $inside = parse_tags_safe( $trail, $bad, $tmp, $tmp, '/'.$tag);
         if( $bad)
            return $before .'<'. $head .'>'. $inside ;
         //$inside = str_replace('&', '&amp;', $inside);
         $inside = preg_replace('/[\\x09\\x20]/', '&nbsp;', $inside);
         $inside = preg_replace('/[\\x01-\\x1F]*/', '', $inside);
         //TODO: fix possible corrupted marks... to be reviewed
         $inside = preg_replace('/&nbsp;class=Mark/', ' class=Mark', $inside);
      }
      elseif( $to_be_closed )
      {
         $inside = parse_tags_safe( $trail, $bad, $html_code, $html_code_closed, '/'.$endtag);
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

// returns true, if given text contains some marked-terms
// (originated from regex-matching added in parse_tags_safe)
function contains_mark_terms( $text )
{
   return preg_match( "/class=Mark(Tag)?Term/", $text );
}

/**
 * Simple check of elements' attributes and inner text.
 * If an element is allowed and correctly closed,
 *  validate it by subtituing its '<' and '>' with ALLOWED_LT and ALLOWED_GT.
 * $mark_terms: search rx_terms.
 *  replace case-insensitive regex-terms in text with tags used to highlight found-texts.
 *  must be a valid regex (escaped for the '/' delimiter), but be cautious with .* (!)
 *  e.g. terms separated by '|' like word1|word2|word3;
 **/
function parse_html_safe( $msg, $some_html, $mark_terms='')
{
   global $html_code, $html_code_closed, $parse_mark_regex;

   //set the regexp (escaped for the '/' delimiter) to the first match level (parenthesis)
   $parse_mark_regex = !$mark_terms ? '' : "/($mark_terms)/is";
   $bad = 0;
   if( !$some_html )
      $str = '';
   else
      $str = parse_tags_safe( $msg, $bad,
                  $html_code[$some_html],
                  $html_code_closed[$some_html],
                  '') ;
   if( $parse_mark_regex && PARSE_MARK_TERM && $msg )
      $msg = preg_replace( $parse_mark_regex, PARSE_MARK_TERM, $msg);
   $str.= $msg;
   $parse_mark_regex = '';
   return $str;
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

//<note>...</note> =>removed from entry, seen only by editors
 '%'.ALLOWED_LT."note([^`\\n\\t]*)".ALLOWED_GT.".*?"
    .ALLOWED_LT."/note([^`\\n\\t]*)".ALLOWED_GT.'%is'
  => '', // =>removed from entry

//<mailto:...>
 '/'.ALLOWED_LT."(mailto:)([^`\\n\\s]+)".ALLOWED_GT.'/is'
  => ALLOWED_LT."a href=".ALLOWED_QUOT."\\1\\2".ALLOWED_QUOT.ALLOWED_GT
                        ."\\2".ALLOWED_LT."/a".ALLOWED_GT,

//<http://...>, <https://...>, <news://...>, <ftp://...>
 '%'.ALLOWED_LT."((http:|https:|news:|ftp:)//[^`'\\r\\n\\s]+?)(?:\s+\|([^'\\r\\n]+?))?".ALLOWED_GT.'%ise'
  => '"'.ALLOWED_LT.'a class='.ALLOWED_QUOT.'linkmarkup'.ALLOWED_QUOT
         . ' href='.ALLOWED_QUOT."\\1".ALLOWED_QUOT.ALLOWED_GT.'"'
         . ".( strlen(trim('\\3')) ? '\\3' : '\\1' )."
         . '"'.ALLOWED_LT."/a".ALLOWED_GT.'"',

//<game gid[,move]> =>show game
 '/'.ALLOWED_LT."game(_)? +([0-9]+)( *, *([0-9]+))? *".ALLOWED_GT.'/ise'
  => "game_reference(('\\1'?".REF_LINK_BLANK.":0)+"
                        .REF_LINK_ALLOWED.",1,'',\\2,\\4+0)",

//<tourney tid> => show tournament
 '/'.ALLOWED_LT."tourney(_)? +([0-9]+) *".ALLOWED_GT.'/ise'
  => "tournament_reference(('\\1'?".REF_LINK_BLANK.":0)+"
                        .REF_LINK_ALLOWED.",1,'',\\2)",

//<user uid> or <user =uhandle> =>show user info
//<send uid> or <send =uhandle> =>send a message to user
 '/'.ALLOWED_LT."(user|send)(_)? +(".HANDLE_TAG_CHAR
                        ."?[+".HANDLE_LEGAL_REGS."]+) *".ALLOWED_GT.'/ise'
  => "\\1_reference(('\\2'?".REF_LINK_BLANK.":0)+"
                        .REF_LINK_ALLOWED.",1,'','\\3')",
//adding '+' to HANDLE_LEGAL_REGS because of old DGS users having it in their Handle
//because of HANDLE_LEGAL_REGS, no need of ...,str_replace('\"','"','\\3')...

//<color col>...</color> =>translated to <font color="col">...</font>
 '%'.ALLOWED_LT."color +([#0-9a-zA-Z]+) *".ALLOWED_GT.'%is'
  => ALLOWED_LT."font color=".ALLOWED_QUOT."\\1".ALLOWED_QUOT.ALLOWED_GT,
 '%'.ALLOWED_LT."/color *".ALLOWED_GT.'%is'
  => ALLOWED_LT."/font".ALLOWED_GT,

//<code>...</code> =>translated to <pre class=code>...</pre>
// see also parse_tags_safe() for the suppression of inner html codes
 '%'.ALLOWED_LT."code([^`\\n\\t]*)".ALLOWED_GT.'%is'
  => ALLOWED_LT."pre class=code \\1".ALLOWED_GT,
 '%'.ALLOWED_LT."/code *".ALLOWED_GT.'%is'
  => ALLOWED_LT."/pre".ALLOWED_GT,

//<quote>...</quote> =>translated to <div class=quote>...</div>
 '%'.ALLOWED_LT."quote([^`\\n\\t]*)".ALLOWED_GT.'%is'
  => ALLOWED_LT."div class=quote \\1".ALLOWED_GT,
 '%'.ALLOWED_LT."/quote *".ALLOWED_GT.'%is'
  => ALLOWED_LT."/div".ALLOWED_GT,

//<home page>...</home> =>translated to <a href="{HOSTBASE}$page">...</a>
 '%'.ALLOWED_LT."home(_)?[\\n\\s]+((\.?[^\.\\\\:\"`\\n\\s])+)".ALLOWED_GT.'%ise'
  => '"'.ALLOWED_LT."a href=".ALLOWED_QUOT.HOSTBASE."\\2".ALLOWED_QUOT
      ."\".('\\1'?' target=".ALLOWED_QUOT.'_blank'.ALLOWED_QUOT."':'').\""
      .ALLOWED_GT.'"',
 '%'.ALLOWED_LT."/home *".ALLOWED_GT.'%is'
  => ALLOWED_LT."/a".ALLOWED_GT,

//<image pict> =>translated to <img src="{HOSTBASE}images/$pict">
//<image board/pict> =>translated to <img src="{HOSTBASE}17/$pict">
 '%'.ALLOWED_LT."image[\\n\\s]+(board/)?((\.?[^\.\\\\:\"`\\n\\s])+)".ALLOWED_GT.'%ise'
  => '"'.ALLOWED_LT."img class=InTextImage"
      ." alt=".ALLOWED_QUOT."(img)".ALLOWED_QUOT
      ." src=".ALLOWED_QUOT.HOSTBASE
      ."\".('\\1'?'17':'images').\"/\\2".ALLOWED_QUOT.ALLOWED_GT.'"',

//<tt>...</tt> =>translated to <pre>...</pre>
// see also parse_tags_safe() for the suppression of inner html code
/*
 "%".ALLOWED_LT."tt([^`\\n\\t]*)".ALLOWED_GT
  => ALLOWED_LT."pre\\1".ALLOWED_GT,
 "%".ALLOWED_LT."/tt *".ALLOWED_GT
  => ALLOWED_LT."/pre".ALLOWED_GT,
*/

//reverse to bad the skiped (faulty) ones
 '%'.ALLOWED_LT."(/?(image|home|quote|code|note|color|" //|tt if <tt> above
      ."user|send|game|mailto|news|ftp|http)[^`]*)"
    .ALLOWED_GT.'%is'
  => "&lt;\\1&gt;",
); //$html_safe_preg


/**
 * Caution: can't be called twice on the same string. For instance:
 *  first pass: <quote> become <div ...>
 *  second pass: <div ...> will be disabled
 *
 * $some_html may be:
 *  false: no tags at all, except the marked terms
 *  true:  same as 'msg'
 *  'cell', 'line', 'msg', 'game' or 'faq': see $html_code[]
 *  'gameh': 'game' + show hidden sgf comments
 * $mark_terms: see parse_html_safe().
 **/
function make_html_safe( $msg, $some_html=false, $mark_terms='')
{
   if( $some_html )
   {
      // make sure the <, > replacements: ALLOWED_LT, ALLOWED_GT are removed from the string
      $msg= reverse_allowed( $msg);

      switch( (string)$some_html )
      {
         case 'gameh':
            $gameh = 1 ;
            $some_html = 'game';
            break;

         default:
            $some_html = 'msg'; //historical default for $some_html == true
         case 'msg':
         case 'cell':
         case 'game':
         case 'faq':
            $gameh = 0 ;
            break;
      }

      // regular (and extended) allowed html tags check
      $msg = parse_html_safe( $msg, $some_html, $mark_terms) ;


      // formats legal html code
      if( $some_html == 'game' )
      {
         $tmp = "<\\1>"; // "<\\1>"=show tag, ""=hide tag, "\\0"=html error
         if( $gameh ) // show hidden sgf comments
         {
            $msg = preg_replace('%'.ALLOWED_LT.'(h(idden)?) *'.ALLOWED_GT.'%i',
                     ALLOWED_LT."span class=GameTagH".ALLOWED_GT.$tmp, $msg);
            $msg = preg_replace('%'.ALLOWED_LT.'(/h(idden)?) *'.ALLOWED_GT.'%i',
                     $tmp.ALLOWED_LT."/span".ALLOWED_GT, $msg);
         }
         else // hide hidden sgf comments
            $msg = trim(preg_replace(
                  '%'.ALLOWED_LT.'h(idden)? *'.ALLOWED_GT.'(.*?)'.ALLOWED_LT.'/h(idden)? *'.ALLOWED_GT.'%is',
                  '', $msg));


         $msg = preg_replace('%'.ALLOWED_LT.'(c(omment)?) *'.ALLOWED_GT.'%i',
                  ALLOWED_LT.'span class=GameTagC'.ALLOWED_GT.$tmp, $msg);
         $msg = preg_replace('%'.ALLOWED_LT.'(/c(omment)?) *'.ALLOWED_GT.'%i',
                  $tmp.ALLOWED_LT.'/span'.ALLOWED_GT, $msg);

         $some_html = 'msg';
      }

      global $html_safe_preg;
      $msg= preg_replace( array_keys($html_safe_preg), $html_safe_preg, $msg);

   }
   elseif( $mark_terms )
   {
      $msg = parse_html_safe( $msg, '', $mark_terms) ;
   }


   // Filter out HTML code

   /*
   $msg = str_replace('&', '&amp;', $msg);
   $msg = eregi_replace('&amp;((#[0-9]+|[A-Z][0-9A-Z]*);)', '&\\1', $msg);
   */
   $msg = preg_replace('/&(?!(#[0-9]+|[A-Z][0-9A-Z]*);)/is', '&amp;', $msg);

   $msg = basic_safe( $msg);

   if( $some_html || $mark_terms )
   {
      // change back to <, > from ALLOWED_LT, ALLOWED_GT
      $msg= reverse_allowed( $msg);

      if( $some_html && $some_html != 'cell' && $some_html != 'line' )
         $msg = add_line_breaks($msg);
   }

   return $msg;
}

function textarea_safe( $msg, $charenc=false)
{
   global $encoding_used;
   if( !$charenc)
      $charenc = $encoding_used;
   //else 'iso-8859-1' LANG_DEF_CHARSET

   $msg = @htmlspecialchars($msg, ENT_QUOTES, $charenc);
   //No: $msg = @htmlentities($msg, ENT_QUOTES, $charenc); //Too much entities for not iso-8859-1 languages
   return $msg;
}

//keep the parts readable by an observer
function game_tag_filter( $msg)
{
   $nr_matches = preg_match_all(
         "%(<c(omment)? *>(.*?)</c(omment)? *>)".
         "|(<h(idden)? *>(.*?)</h(idden)? *>)%is"
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


function yesno( $yes)
{
   return ( $yes && strtolower(substr($yes,0,1))!='n' ) ? T_('Yes') : T_('No');
}

function score2text($score, $verbose, $keep_english=false)
{
   $T_= ( $keep_english ? 'fnop' : 'T_' );

   if( !isset($score) )
      return "?";

   if( $score == 0 )
      return ( $keep_english ? 'Draw' : ( $verbose ? T_('Jigo') : 'Jigo' ));

   $color = ($verbose
             ? ( $score > 0 ? $T_('White') : $T_('Black') )
             : ( $score > 0 ? 'W' : 'B' ));

   if( abs($score) == SCORE_TIME )
      return ( $verbose ? sprintf( $T_("%s wins on time"), $color) : $color . "+Time" );
   elseif( abs($score) == SCORE_RESIGN )
      return ( $verbose ? sprintf( $T_("%s wins by resign"), $color) : $color . "+Resign" );
   else
      return ( $verbose ? sprintf( $T_("%s wins by %.1f"), $color, abs($score))
               : $color . '+' . abs($score) );
}

// returns rows checked against min/max-limits; return default-rows if unset or exceeding limits
function get_maxrows( $rows, $maxrows, $defrows = MAXROWS_PER_PAGE_DEFAULT )
{
   return ( is_numeric($rows) && $rows > 0 && $rows <= $maxrows ) ? $rows : $defrows;
}

// returns array with standard rows and with customized maxrows (added to standard list at the right place)
// RETURN: array ( row_count => row_count, ...); ready to be used for selectbox
// param $maxrows current value to add unorthodox value; 0 to get default
function build_maxrows_array( $maxrows, $rows_limit = MAXROWS_PER_PAGE )
{
   $maxrows = get_maxrows( $maxrows, $rows_limit );
   $arr_maxrows = array();
   foreach( array( 5, 10, 15, 20, 25, 30, 40, 50, 75, 100 ) as $k)
   {
      if( $k <= $rows_limit )
         $arr_maxrows[$k] = $k;
   }
   $arr_maxrows[$maxrows] = $maxrows; // add manually added value
   ksort( $arr_maxrows, SORT_NUMERIC );
   return $arr_maxrows;
}


// Makes URL from a base URL and an array of variable/value pairs
// if $sep is true, a '?' or '&' is added at the end
// this is somehow the split_url() mirror
// NOTE: Since PHP5, there is http_build_query() that do nearly the same thing
// Warning: These chars must not be a part of a URI query. From RFC 2396
//      unwise = "{" | "}" | "|" | "\" | "^" | "[" | "]" | "`"
//    Instead of 'varname[]' use 'varname%5b%5d'
//
// Example:
//    make_url('test.php', array('a'=> 1, 'b' => 'foo'), false)  gives  'test.php?a=1&b=foo'
//    make_url('test.php?a=1', array('b' => 'foo'), false)  gives  'test.php?a=1&b=foo'
// Also handle value-arrays:
//    make_url('arr.php', array('a' => array( 44, 55 ))  gives  'arr.php?a[]=44&a[]=55'
// TODO: next step could be to handle the '#' part of the url:
//    make_url('test.php?a=1#id', array('b' => 'foo'), false)  gives  'test.php?a=1&b=foo#id'
function make_url( $url, $args, $end_sep=false)
{
   $url= clean_url($url);
   $sep= ( is_numeric( strpos( $url, '?')) ? URI_AMP : '?' );
   $args= build_url( $args, $end_sep);
   if( $args || $end_sep )
      return $url . $sep . $args;
   return $url;
} //make_url

function build_url( $args, $end_sep=false, $sep=URI_AMP )
{
   if( !is_array( $args) )
      return '';
   $arr_str = array();
   foreach( $args as $key => $value )
   {
      // some clients need ''<>0, so don't use empty(val)
      if( (string)$value == '' || !is_string($key) || empty($key) )
         continue;
      if( !is_array($value) )
      {
         $arr_str[]= $key . '=' . urlencode($value);
         continue;
      }
      $key.= '%5b%5d='; //encoded []
      foreach( $value as $val )
      {
         // some clients need ''<>0, so don't use empty(val)
         if( (string)$val != '' )
            $arr_str[]= $key . urlencode($val);
      }
   }
   if( count($arr_str) )
      return implode( $sep, $arr_str) . ( $end_sep ? $sep : '' );
   return '';
} //build_url

function build_hidden( $args)
{
   if( !is_array( $args) )
      return '';
   $arr_str = array();
   foreach( $args as $key => $value )
   {
      // some clients need ''<>0, so don't use empty(val)
      if( (string)$value == '' || !is_string($key) || empty($key) )
         continue;
      if( !is_array($value) )
      {
         $arr_str[]= "name=\"$key\" value=" . attb_quote($value);
         continue;
      }
      $key.= '[]'; //%5b%5d encoded []
      foreach( $value as $val )
      {
         // some clients need ''<>0, so don't use empty(val)
         if( (string)$val != '' )
            $arr_str[]=  "name=\"$key\" value=" . attb_quote($val);
      }
   }
   if( count($arr_str) )
      return "\n<input type=\"hidden\" ".implode( ">\n<input type=\"hidden\" ", $arr_str) .">";
   return '';
} //build_hidden

//see also the PHP parse_str() and parse_url()
//this one use URI_AMP by default to be the make_url() mirror
function split_url($url, &$page, &$args, $sep='')
{
   if( !$sep ) $sep = URI_AMP;
   $url = split( '([?#]|'.$sep.')', $url );
   list( , $page ) = each( $url );
   $args = array();
   while( list( , $query ) = each( $url ) )
   {
      if( !empty( $query ) )
      {
         @list( $var, $value ) = explode( '=', $query );
         if( (string)@$value != '' ) // can be '0' (which is <> unset/false)
         {
            $var = urldecode($var);
            if( substr($var,-2) != '[]' ) //'%5B%5D'
            {
               $args[$var] = urldecode($value);
            }
            else
            {
               $var = substr($var,0,-2);
               $tmp = @$args[$var]; // append to array (if existing)
               $tmp[] = urldecode($value);
               $args[$var] = $tmp;
            }
         }
      }
   }
}

// chop off all trailing URI_AMP and '?' from passed url/query
function clean_url( $url, $sep='' )
{
   if( !$sep ) $sep = URI_AMP;
   $l = -strlen($sep);
   do
   {
      $stop=1;
      while( substr( $url, $l ) == $sep ) // strip '&'
         $url = substr( $url, $stop=0, $l );
      while( substr( $url, -1 ) == '?' ) // strip '?'
         $url = substr( $url, $stop=0, -1);
   } while( !$stop );
   return $url;
}

// relative to the calling URL, not to the current dir
// returns empty or some rel-dir with trailing '/'
function rel_base_dir()
{
   $dir = str_replace('\\','/',$_SERVER['PHP_SELF']);
   $rel = '';
   while( $i=strrpos($dir,'/') )
   {
      $dir= substr($dir,0,$i);
      if( !strcasecmp( $dir.'/' , SUB_PATH ) )
         break;
      $rel.= '../';
   }
   return $rel;
}

function get_request_url( $absolute=false)
{
//CAUTION: sometime, REQUEST_URI != PHP_SELF+args
//if there is a redirection, _URI==requested, while _SELF==reached (running one)
   $url = str_replace('\\','/',@$_SERVER['REQUEST_URI']); //contains URI_AMP_IN and still urlencoded
   $len = strlen(SUB_PATH);
   if(!strcasecmp( SUB_PATH, substr($url,0,$len) ))
      $url = substr($url,$len);
   $url = str_replace( URI_AMP_IN, URI_AMP, $url);
   if( $absolute )
      $url = HOSTBASE . $url;
   return $url;
}


//Priorities: URI(id) > URI(handle) > REFERER(id) > REFERER(handle)
//returns bitmask of what is found: bit0=uid, bit1=uhandle, bit2=...from referer
//Warning: the '+' (an URI reserved char) must be substitued with %2B in 'handle'.
function get_request_user( &$uid, &$uhandle, $from_referer=false)
{
   $uid_name = 'uid';
   $uid = (int)@$_REQUEST[$uid_name];
   $uhandle = '';
   if( $uid > 0 )
      return 1; //bit0
   $uid = 0;
   $uhandle = (string)@$_REQUEST[UHANDLE_NAME];
   if( $uhandle )
      return 2; //bit1
   if( $from_referer && ($refer=@$_SERVER['HTTP_REFERER']) )
   {
      //default user = last referenced user
      //(ex: message.php from userinfo.php by menu link)
      if( preg_match('/[?'.URI_AMP_IN.']'.$uid_name.'=([0-9]+)/i', $refer, $eres) )
      {
         $uid = (int)$eres[1];
         if( $uid > 0 )
            return 5; //bit0,2
      }
      $uid = 0;
      //adding '+' to HANDLE_LEGAL_REGS because of old DGS users having it in their Handle
      if( preg_match('/[?'.URI_AMP_IN.']'.UHANDLE_NAME.'=([+'.HANDLE_LEGAL_REGS.']+)/i', $refer, $eres) )
      {
         $uhandle = (string)$eres[1];
         if( $uhandle )
            return 6; //bit1,2
      }
   }
   return 0; //not found
} //get_request_user

function who_is_logged( &$player_row)
{
   $handle = safe_getcookie('handle');
   $sessioncode = safe_getcookie('sessioncode');
   $curdir = getcwd();
   global $main_path;

   // because of include_all_translate_groups() must be called from main dir
   chdir( $main_path);
   $player_id = is_logged_in($handle, $sessioncode, $player_row);
   chdir( $curdir);
   return $player_id;
}


/**
 * fever-vault parameters:
 * set VAULT_DELAY to 0 to disable the whole process.
 * setting VAULT_CNT or VAULT_CNT_X to 0 will nearly always let the accounts in the vault.
 *   (one page allowed each VAULT_TIME* seconds)
 * setting VAULT_CNT or VAULT_CNT_X big will nearly never let the accounts entering the vault.
 *   (nearly no way to hit VAULT_CNT* pages during VAULT_DELAY seconds)
 * Caution: VaultCnt is a SMALLINT in the database
 **/
define('VAULT_CNT', 1000); //an account with more than x hits...
define('VAULT_DELAY', 3600); //... during y seconds ...
define('VAULT_TIME', 24*3600); //... is vaulted for z seconds
//two specific parameters for multi-users accounts, e.g. 'guest':
define('VAULT_CNT_X', VAULT_CNT*10); //activity count (larger)
define('VAULT_TIME_X', 2*3600); //vault duration (smaller)

/**
 * Check if the player $handle can be logged in
 * returns 0 if $handle can't be logged in
 * else fill the $player_row array with its characteristics,
 * load the player language definitions, set his timezone
 * and finally returns his ID (i.e. >0)
 **/
function is_logged_in($handle, $scode, &$player_row) //must be called from main dir
{
   global $hostname_jump, $NOW, $dbcnx;
   global $ActivityForHit, $ActivityMax;

   $player_row = array( 'ID' => 0 );

   if( $hostname_jump && preg_replace("/:.*\$/",'', @$_SERVER['HTTP_HOST']) != HOSTNAME )
   {
      list($protocol) = explode(HOSTNAME, HOSTBASE);
      jump_to( $protocol . HOSTNAME . $_SERVER['PHP_SELF'], true );
   }

   if( empty($handle) || empty($dbcnx) )
   {
      include_all_translate_groups(); //must be called from main dir
      return 0;
   }

   $query= "SELECT *,UNIX_TIMESTAMP(Sessionexpire) AS Expire"
          .",Adminlevel+0 AS admin_level"
          .(VAULT_DELAY>0 ?",UNIX_TIMESTAMP(VaultTime) AS VaultTime" :'')
          .',UNIX_TIMESTAMP(LastMove) AS X_LastMove'
          .',UNIX_TIMESTAMP(Lastaccess) AS X_Lastaccess'
          ." FROM Players WHERE Handle='".mysql_addslashes($handle)."' LIMIT 1";

   $result = db_query( 'is_logged_in.find_player', $query );

   if( $result && @mysql_num_rows($result) == 1 )
   {
      $player_row = mysql_fetch_assoc($result);
      unset($player_row['Adminlevel']);
   }
   if( $result )
      mysql_free_result($result);

   $uid = (int)@$player_row['ID'];
   if( $uid <= 0 )
   {
      include_all_translate_groups(); //must be called from main dir
      $player_row['ID'] = 0;
      return 0;
   }
   include_all_translate_groups($player_row); //must be called from main dir

   if( (@$player_row['AdminOptions'] & ADMOPT_DENY_LOGIN) )
      error('login_denied');

   setTZ( $player_row['Timezone']);


   $session_expired= ( $player_row['Sessioncode'] != $scode || $player_row['Expire'] < $NOW );

   $query = "UPDATE Players SET Hits=Hits+1";

   $ip = (string)@$_SERVER['REMOTE_ADDR'];
   if( $ip && $player_row['IP'] !== $ip )
   {
      $query.= ",IP='$ip'";
      $player_row['IP'] = $ip;
   }

   if( !$session_expired )
   {
      $query.= ",Activity=LEAST($ActivityMax,$ActivityForHit+Activity)"
              .",Lastaccess=FROM_UNIXTIME($NOW)"
              .",Notify='NONE'";

      $browser = substr(@$_SERVER['HTTP_USER_AGENT'], 0, 150);
      if( $player_row['Browser'] !== $browser )
      {
         $query.= ",Browser='".mysql_addslashes($browser)."'";
         $player_row['Browser'] = $browser;
      }
   }

   $vaultcnt= true; //no vault for anonymous or if disabled
   if( VAULT_DELAY>0 && !$session_expired ) //exclude access deny from an other user
   {
      $vaultcnt= (int)@$player_row['VaultCnt'];
      $vaulttime= @$player_row['VaultTime'];
      //can be translated if desired (translations have just been set):
      $vault_fmt= T_("The activity of the account '%s' grew to high"
                 ." and swallowed up our bandwidth and resources."
                 ."<br>Please, correct this behaviour."
                 ."<br>This account is blocked until %s.");
      if( $vaultcnt <= 0 ) //inside fever vault
      {
         if( $NOW > $vaulttime ) //time to quit the vault?
         {
            $vaultcnt= 1; //will be reseted next time
            $query.= ",VaultCnt=$vaultcnt";
         }
         else
            $vaultcnt= 0; //stay in fever vault...
      }
      elseif( $vaultcnt > 1 ) //measuring fever
      {
         $vaultcnt--;
         $query.= ",VaultCnt=$vaultcnt";
      }
      //TODO: maybe exclude the multi-users accounts
      elseif( $NOW < $vaulttime ) //fever too high
      //to exclude guest, add: && $uid > GUESTS_ID_MAX
      {
         $vaultcnt= 0; //enter fever vault...
         if( $uid <= GUESTS_ID_MAX ) //multi-users accounts
            $vaulttime= $NOW+VAULT_TIME_X;
         else
            $vaulttime= $NOW+VAULT_TIME; // vault exit date
         $query.= ",VaultCnt=$vaultcnt"
                 .",VaultTime=FROM_UNIXTIME($vaulttime)";

         err_log( $handle, 'fever_vault');

         //send notifications to owner
         $subject= T_('Temporary access restriction');
         $text= 'On '.date(DATE_FMT, $NOW).":\n"
               .sprintf($vault_fmt, $handle, date(DATE_FMT,$vaulttime));

         if( $uid > GUESTS_ID_MAX )
            $handles[]= $handle;
         if( count($handles) > 0 )
            send_message("fever_vault.msg($uid,$ip)", $text, $subject, '', $handles, /*notify*/false, 0);

         $email= $player_row['Email'];
         if( $uid > GUESTS_ID_MAX && verify_email( false, $email) )
            send_email("fever_vault.email($handle)", $email, 0, $text
                      , FRIENDLY_LONG_NAME.' - '.$subject);
      }
      else //cool enough: reset counters for one period
      {
         if( $uid <= GUESTS_ID_MAX ) //multi-users accounts
            $vaultcnt= VAULT_CNT_X;
         else
            $vaultcnt= VAULT_CNT; //less than x hits...
         $vaulttime= $NOW+VAULT_DELAY; //... during y seconds
         $query.= ",VaultCnt=$vaultcnt"
                 .",VaultTime=FROM_UNIXTIME($vaulttime)";
      }
   }//fever-fault check

   // DST-check if the player's clock need an adjustment from/to summertime
   if( $player_row['ClockChanged'] != 'Y'
         && $player_row['ClockUsed'] != get_clock_used($player_row['Nightstart']) )
   {
      $query .= ",ClockChanged='Y'"; // ClockUsed is updated once a day...
   }

   // check aggregated counts
   $count_msg_new = count_messages_new( $uid, $player_row['CountMsgNew'] );
   if( $count_msg_new >= 0 )
   {
      $player_row['CountMsgNew'] = $count_msg_new;
      $query .= ",CountMsgNew=$count_msg_new";
   }

   if( ALLOW_FEATURE_VOTE )
   {
      $count_feat_new = count_feature_new( $uid, $player_row['CountFeatNew'] );
      if( $count_msg_new >= 0 )
      {
         $player_row['CountFeatNew'] = $count_feat_new;
         $query .= ",CountFeatNew=$count_feat_new";
      }
   }


   $query.= " WHERE ID=$uid LIMIT 1";
   //$updok will be false if an error occurs and error() is set to 'no exit'
   $updok = db_query( 'is_logged_in.update_player', $query );

   if( !$vaultcnt ) //vault entered
   {
      switch( (string)substr( @$_SERVER['PHP_SELF'], strlen(SUB_PATH)) )
      {
         case 'index.php':
            $text = sprintf($vault_fmt, $handle, date(DATE_FMT,$vaulttime));
            $_REQUEST['sysmsg'] = $text;
            $session_expired = true; //fake disconnection
            break;

         default:
            jump_to("index.php");
            break;
      }
/* options:
      set_login_cookie("","", true);
      error('fever_vault'); //log record
      jump_to("error.php?err=fever_vault"); //no log record
*/
   }

   if( $session_expired || !$updok || @mysql_affected_rows() != 1 )
   {
      $player_row['ID'] = 0;
      return 0;
   }

   get_cookie_prefs($player_row);
   switch_admin_status($player_row); //get default admin_status

   setTZ( $player_row['Timezone']);

   return $uid;
} //is_logged_in

/*!
 * \brief Counts new messages for given user-id if current count < 0 (=needs-update).
 * \param $curr_count force counting if <0 or omitted
 * \return new new-message count (>=0) for given user-id; or -1 on error
 */
function count_messages_new( $uid, $curr_count=-1 )
{
   if( $curr_count >= 0 )
      return $curr_count;
   if( !is_numeric($uid) || $uid <= 0 )
      error( 'invalid_args', "count_messages_new.check.uid($uid)" );

   $row = mysql_single_fetch( "count_messages_new($uid)",
      "SELECT COUNT(*) AS X_Count FROM MessageCorrespondents WHERE uid='$uid' AND Folder_nr=".FOLDER_NEW );
   return ($row) ? $row['X_Count'] : -1;
}

/*!
 * \brief Updates or resets Players.CountMsgNew.
 * \param $diff null|omit to reset to -1 (=recalc later); COUNTNEW_RECALC to recalc now;
 *        otherwise increase or decrease counter
 */
define('COUNTNEW_RECALC', 'recalc');
function update_count_message_new( $dbgmsg, $uid, $diff=null )
{
   if( !is_numeric($uid) )
      error( 'invalid_args', $dbgmsg.'.check.uid' );

   if( is_null($diff) )
   {
      db_query( "$dbgmsg.reset",
         "UPDATE Players SET CountMsgNew=-1 WHERE ID='$uid' LIMIT 1" );
   }
   elseif( is_numeric($diff) && $diff != 0 )
   {
      $diffstr = (($diff < 0) ? '-' : '+') . abs($diff);
      db_query( "$dbgmsg.upd($diff)",
         "UPDATE Players SET CountMsgNew=CountMsgNew$diffstr WHERE CountMsgNew>=0 AND ID='$uid' LIMIT 1" );
   }
   elseif( (string)$diff == COUNTNEW_RECALC )
   {
      global $player_row;
      $count_new = count_messages_new( $uid );
      if( @$player_row['ID'] == $uid )
         $player_row['CountMsgNew'] = $count_new;
      db_query( "$dbgmsg.recalc",
         "UPDATE Players SET CountMsgNew=$count_new WHERE ID='$uid' LIMIT 1" );
   }
}

/*!
 * \brief Counts new features for given user-id if current count < 0 (=needs-update).
 * \param $curr_count force counting if <0 or omitted
 * \return new features count (>=0) for given user-id; or -1 on error
 */
function count_feature_new( $uid, $curr_count=-1 )
{
   if( !ALLOW_FEATURE_VOTE )
      return -1;
   if( $curr_count >= 0 )
      return $curr_count;
   if( !is_numeric($uid) || $uid <= 0 )
      error( 'invalid_args', "count_features_new.check.uid($uid)" );

   $row = mysql_single_fetch( "count_features_new($uid)",
      "SELECT COUNT(*) AS X_Count " .
      "FROM FeatureList AS FL " .
         "LEFT JOIN FeatureVote AS FV ON FL.ID=FV.fid AND FV.Voter_ID='$uid' " .
      "WHERE FL.Status='NEW' AND ISNULL(FV.fid)" );
   return ($row) ? $row['X_Count'] : -1;
}

/*!
 * \brief Updates or resets Players.CountFeatNew.
 * \param $uid 0 update all users
 * \param $diff null|omit to reset to -1 (=recalc later);
 *        COUNTNEW_RECALC to recalc now (for specific user-id only);
 *        otherwise increase or decrease counter
 */
function update_count_feature_new( $dbgmsg, $uid=0, $diff=null )
{
   if( !ALLOW_FEATURE_VOTE )
      return;
   if( !is_numeric($uid) )
      error( 'invalid_args', $dbgmsg.'.check.uid' );

   if( is_null($diff) )
   {
      $query = "UPDATE Players SET CountFeatNew=-1 WHERE CountFeatNew>=0";
      if( $uid > 0 )
         $query .= " AND ID='$uid' LIMIT 1";
      db_query( "$dbgmsg.reset", $query );
   }
   elseif( is_numeric($diff) && $diff != 0 )
   {
      $diffstr = (($diff < 0) ? '-' : '+') . abs($diff);
      $query = "UPDATE Players SET CountFeatNew=CountFeatNew$diffstr WHERE CountFeatNew>=0";
      if( $uid > 0 )
         $query .= " AND ID='$uid' LIMIT 1";
      db_query( "$dbgmsg.upd($diff)", $query );
   }
   elseif( (string)$diff == COUNTNEW_RECALC && $uid > 0 )
   {
      global $player_row;
      $count_new = count_features_new( $uid );
      if( @$player_row['ID'] == $uid )
         $player_row['CountFeatNew'] = $count_new;
      db_query( "$dbgmsg.recalc",
         "UPDATE Players SET CountFeatNew=$count_new WHERE ID='$uid' LIMIT 1" );
   }
}

/*!
 * \brief Loads (and updates if needed) global-forum NEW-flag-state for current player.
 * \return true, if there are NEW posts in forum for user to read; false otherwise
 */
function load_global_forum_new()
{
   global $player_row;

   $f_opts = new ForumOptions( $player_row );
   $has_new = ForumRead::load_global_new( $f_opts );
   return $has_new;
}

// Caution: can cause concurrency problems
if( function_exists('file_put_contents') )
{
   function write_to_file($filename, $data, $quit_on_error=true )
   {
      $num= @file_put_contents($filename, $data);
      if( is_int($num) )
      {
         @chmod( $filename, 0666);
         return $num;
      }
      if( $quit_on_error )
         error( 'couldnt_open_file', 'write_to_file:'.$filename);
      trigger_error("write_to_file($filename) failed to write stream", E_USER_WARNING);
      return false;
   }
}
else
{
   function write_to_file( $filename, $data, $quit_on_error=true )
   {
      $fh = @fopen($filename, 'wb');
      if( $fh )
      {
         flock($fh, LOCK_EX);
         $num= @fwrite($fh, $data);
         fclose( $fh );
         if( is_int($num) )
         {
            @chmod( $filename, 0666);
            return $num;
         }
      }
      if( $quit_on_error )
         error( 'couldnt_open_file', 'write_to_file:'.$filename);
      trigger_error("write_to_file($filename) failed to write stream", E_USER_WARNING);
      return false;
   }
} //write_to_file

if( function_exists('file_get_contents') )
{
   function read_from_file($filename, $quit_on_error=true, $incpath=false)
   {
      if( defined('FILE_USE_INCLUDE_PATH') ) //since PHP5
         $data = @file_get_contents( $filename, ($incpath ? FILE_USE_INCLUDE_PATH : 0) );
      else
         $data = @file_get_contents( $filename, $incpath ); //PHP4
      if( is_string($data) )
         return $data;
      if( $quit_on_error )
         error( 'couldnt_open_file', 'read_from_file:'.$filename);
      trigger_error("read_from_file($filename) failed to open stream", E_USER_WARNING);
      return false;
   }
}
else
{
   function read_from_file($filename, $quit_on_error=true, $incpath=false)
   {
      $fh = @fopen($filename, 'rb', $incpath);
      if( $fh )
      {
         flock($fh, LOCK_SH);
         clearstatcache();
         if( ($fsize=@filesize($filename)) )
         {
            $data = @fread($fh, $fsize);
         }
         else
         {
            $data = '';
            while( !feof($fh) )
               $data.= @fread($fh, 8192);
         }
         fclose($fh);
         if( is_string($data) )
            return $data;
      }
      if( $quit_on_error )
         error( 'couldnt_open_file', 'read_from_file:'.$filename);
      trigger_error("read_from_file($filename) failed to open stream", E_USER_WARNING);
      return false;
   }
} //read_from_file

function centered_container( $open=true)
{
//the container must be a centered one which can be left or right aligned
   static $opened = false;
   if( $opened )
   { //opened, close it
      echo "</td></tr></table>\n";
      $opened = false;
   }
   if( $open )
   { //open a new one
      echo "\n<table class=Container><tr><td class=Container>";
      $opened = true;
   }
}

function section( $id='', $header='', $anchorName='' )
{
   static $section = '';

   centered_container( false);
   if( $section )
   { //section opened, close it
      echo "</div>\n";
      $section = '';
      if( $id )
         echo "<hr class=Section>\n";
   }
   if( $id )
   { //section request, open it
      if( $anchorName )
         echo "<a name=\"$anchorName\">";
      $section = attb_quote('sect'.$id);
      echo "\n<div id=$section class=Section>";
      if( $header )
         echo "<h3 class=Header>$header</h3>";
      else
         echo '<br class=Section>';
   }
}

function add_link_page_link( $link=false, $linkdesc='', $extra='', $active=true)
{
   static $started = false;

   if( $link === false )
   {
      if( $started )
         echo "</dl>\n";
      $started = false;
      return 0;
   }

   if( !$started )
   {
      echo "<dl class=DocLink>\n";
      $started = true;
   }

   if( $active )
      echo "<dd><a href=\"$link\">$linkdesc</a>";
   else
      echo "<dd class=Inactive><span>$linkdesc</span>";
   if( !empty($extra) )
      echo " --- $extra";
   echo "</dd>\n";
}

function activity_string( $act_lvl)
{
   switch( (int)$act_lvl )
   {
      case 1: $img = 'star2.gif'; break; // orange
      case 2: $img = 'star.gif'; break;  // green
      default: return '&nbsp;';
   }
   global $base_path;
   $img= "<img class=InTextImage alt='*' src=\"{$base_path}images/$img\">";
   return str_repeat( $img, $act_lvl);
}


// @deprecated: not used -> TODO remove
function nsq_addslashes( $str )
{
  return str_replace( array( "\\", "\"", "\$" ), array( "\\\\", "\\\"", "\\\$" ), $str );
}

function game_reference( $link, $safe_it, $class, $gid, $move=0, $whitename=false, $blackname=false)
{
   global $base_path;

   $gid = (int)$gid;
   $legal = ( $gid > 0 );
   if( $legal && ($whitename===false || $blackname===false) )
   {
     $query = 'SELECT black.Name as blackname, white.Name as whitename ' .
              'FROM (Games, Players as white, Players as black) ' .
              "WHERE Games.ID=$gid " .
              ' AND white.ID=Games.White_ID ' .
              ' AND black.ID=Games.Black_ID ' .
              'LIMIT 1' ;
     if( $row=mysql_single_fetch( 'game_reference', $query ) )
     {
       if( $whitename===false )
         $whitename = $row['whitename'];
       if( $blackname===false )
         $blackname = $row['blackname'];
       $safe_it = true;
     }
     else
       $legal = false;
   }
   $whitename = trim($whitename);
   $blackname = trim($blackname);
   if( $whitename )
      $whitename = "$whitename (W)";
   if( $blackname )
      $blackname = "$blackname (B)";
   if( !$whitename && !$blackname )
      $whitename = "Game#$gid" ;
   elseif( $whitename && $blackname )
      $whitename = "$whitename vs. $blackname";
   else
      $whitename = "$whitename$blackname";
   if( $safe_it )
      $whitename = make_html_safe($whitename);
   if( $move>0 )
      $whitename.= " ,$move";
   if( $link && $legal )
   {
      $url = "game.php?gid=$gid" . ($move>0 ? URI_AMP."move=$move" : "");
      $url = 'A href="' . $base_path. $url . '"';
      if( $link & REF_LINK_BLANK )
        $url .= ' target="_blank"';
      $class = 'Game'.$class;
      if( $class )
        $url .= " class=$class";
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

// format: Tournament #n [title]
function tournament_reference( $link, $safe_it, $class, $tid )
{
   global $base_path;

   $tid = (int)$tid;
   $legal = ( $tid > 0 );
   if( $legal )
   {
      $query = "SELECT Title FROM Tournament WHERE ID='$tid' LIMIT 1";
      if( $row = mysql_single_fetch( "tournament_reference.find_tournament($tid)", $query ) )
      {
         $title = trim(@$row['Title']);
         $safe_it = true;
      }
      else
         $legal = false;
   }

   $tourney = sprintf( T_('Tournament #%s [%s]'),
                       $tid, ( $legal ? $title : T_('???#tournament') ));
   if( $safe_it )
      $tourney = make_html_safe($tourney);

   if( $link && $legal )
   {
      $url = $base_path."tournaments/view_tournament.php?tid=$tid";
      $url = 'A href="' . $url . '"';
      if( $link & REF_LINK_BLANK )
         $url .= ' target="_blank"';
      $class = 'Tournament'.$class;
      if( $class )
        $url .= " class=$class";
      if( $link & REF_LINK_ALLOWED )
      {
         $url = str_replace('"', ALLOWED_QUOT, $url);
         $tourney = ALLOWED_LT.$url.ALLOWED_GT.$tourney.ALLOWED_LT."/A".ALLOWED_GT ;
      }
      else
         $tourney = "<$url>$tourney</A>";
   }

   return $tourney;
}

function send_reference( $link, $safe_it, $class, $player_ref, $player_name=false, $player_handle=false)
{
   if( is_numeric($link) ) //not owned reference
      $link = -$link; //make it a send_reference
   return user_reference( $link, $safe_it, $class, $player_ref, $player_name, $player_handle);
}

/**
 * the reference generated depends of $link:
 * : 0 or '' => no <A></A> tag added
 * : >0 => a user-info reference
 * : <0 => a send-message reference
 * : string => $link will be the reference (must end with '?' or URI_AMP)
 * if numeric, abs($link) may be a combination of:
 * : REF_LINK to add a <A></A> tag
 * : REF_LINK_BLANK to add a <A></A> tag with a target="_blank"
 * : REF_LINK_ALLOWED the <A></A> tag added will pass the make_html_safe filter
 *
 * $player_ref may be:
 * : an integer => the reference will use it as a user ID
 * : a string => the reference will use it as a user Handle
 * :   (if the first char is HANDLE_TAG_CHAR, it is removed)
 * : an array => the reference will use its 'ID' field, and if possible, the
 * :   'Handle' and 'Name' fields if $player_name or $player_handle are absent
 * then the missing arguments will be retrieved from the database if needed.
 **/
function user_reference( $link, $safe_it, $class, $player_ref, $player_name=false, $player_handle=false)
{
   global $base_path;
   if( is_array($player_ref) ) //i.e. $player_row
   {
      if( !$player_name )
         $player_name = $player_ref['Name'];
      if( !$player_handle )
         $player_handle = $player_ref['Handle'];
      $player_ref = (int)$player_ref['ID'];
      $byid = true;
      $legal = false; //temporary
   }
   else
   {
      $byid = is_numeric($player_ref);
      $legal = ( substr($player_ref,0,1) == HANDLE_TAG_CHAR );
   }
   if( !$byid || $legal )
   {
      $byid = false;
      if( $legal )
         $player_ref = substr($player_ref,1);

      //because of old DGS users having a pure numeric Handle
      //illegal_chars( $player_ref) had been replaced by this reg_exp here
      //adding '+' to HANDLE_LEGAL_REGS because of old DGS users having it in their Handle
      $legal = ( $player_ref && preg_match( '/^[+'.HANDLE_LEGAL_REGS."]+\$/", $player_ref) );
   }
   else
   {
      $byid = true;
      $player_ref = (int)$player_ref;
      $legal = ( $player_ref > 0 );
   }
   if( $legal && ($player_name===false || $player_handle===false) )
   {
     $query = 'SELECT Name, Handle ' .
              'FROM Players ' .
              "WHERE " . ( $byid ? 'ID' : 'Handle' ) . "='$player_ref' " .
              'LIMIT 1' ;
     if( $row=mysql_single_fetch( 'user_reference', $query ) )
     {
         if( $player_name===false )
            $player_name = $row['Name'];
         if( $player_handle===false )
            $player_handle = $row['Handle'];
         $safe_it = true;
     }
     else
       $legal = false;
   }
   $player_name = trim($player_name);
   $player_handle = trim($player_handle);
   if( !$player_name )
      $player_name = "User#$player_ref";
   if( $player_handle )
      $player_name.= " ($player_handle)" ;
   if( $safe_it )
      $player_name = make_html_safe($player_name) ;
   if( $link && $legal )
   {
      if( is_string($link) ) //owned reference. Must end with '?' or URI_AMP
      {
         $url = $link;
         $link = 0;
         $class = 'Ref'.$class;
      }
      elseif( $link<0 ) //send_reference
      {
         $url = "message.php?mode=NewMessage".URI_AMP;
         $link = -$link;
         $class = 'Send'.$class;
      }
      else //user_reference
      {
         $url = "userinfo.php?";
         $class = 'User'.$class;
      }

      //encoding '+' to %2B because of old DGS users having it in their Handle
      $url .= ( $byid ? "uid=$player_ref" : UHANDLE_NAME."=".str_replace('+','%2B',$player_ref) );
      $url = 'A href="' . $base_path. $url . '"';
      if( $class )
         $url .= " class=\"$class\"";
      if( $link & REF_LINK_BLANK )
         $url .= ' target="_blank"';
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

// returns true, if there are observers for specified game
function has_observers( $gid )
{
   $result = db_query( 'has_observers',
      "SELECT ID FROM Observers WHERE gid=$gid LIMIT 1");
   if( !$result )
      return false;
   $res = ( @mysql_num_rows($result) > 0 );
   mysql_free_result($result);
   return $res;
}

function is_on_observe_list( $gid, $uid )
{
   $result = db_query( 'is_on_observe_list',
      "SELECT ID FROM Observers WHERE gid=$gid AND uid=$uid");
   if( !$result )
      return false;
   $res = ( @mysql_num_rows($result) > 0 );
   mysql_free_result($result);
   return $res;
}

function toggle_observe_list( $gid, $uid )
{
   $res = is_on_observe_list( $gid, $uid );
   if( $res )
      db_query( 'toggle_observe_list.delete',
         "DELETE FROM Observers WHERE gid=$gid AND uid=$uid LIMIT 1");
   else
      db_query( 'toggle_observe_list.insert',
         "INSERT INTO Observers SET gid=$gid, uid=$uid");
   return !$res;
}

//$Text must NOT be escaped by mysql_addslashes()
function delete_all_observers( $gid, $notify, $Text='' )
{
   global $NOW;

   if( $notify )
   {
      $result = db_query( 'delete_all_observers.find',
         "SELECT Observers.uid AS pid " .
         "FROM Observers " .
         "WHERE gid=$gid AND Observers.uid>".GUESTS_ID_MAX ); //exclude guest

      if( @mysql_num_rows($result) > 0 )
      {
         $Subject = 'An observed game has finished';

         $to_ids = array();
         while( $row = mysql_fetch_array( $result ) )
            $to_ids[] = $row['pid'];

         send_message( "delete_all_observers($gid)", $Text, $Subject
            ,$to_ids, '', /*notify*/false
            , 0, 'NORMAL', $gid);
      }
      mysql_free_result($result);
   }

   db_query( 'delete_all_observers.delete',
      "DELETE FROM Observers WHERE gid=$gid" );
} //delete_all_observers



// definitions and functions to help avoid '!=' or 'NOT IN' in SQL-where-clauses:

$ENUM_GAMES_STATUS = array( 'INVITED','PLAY','PASS','SCORE','SCORE2','FINISHED' );

/*!
 * Builds IN-SQL-part for some enum-array containing all possible values
 * for a table-column.
 * \param $arr_enum non-empty array with all possible values for a table-column
 * var-args params enum-values that shouldn't match on table-column;
 *        must not contain all elements of enum(!)
 * Example: 'Games.Status' . not_in_clause( $ENUM_GAMES_STATUS, 'FINISHED', ... )
 */
function not_in_clause( $arr_enum )
{
   $arr_not = func_get_args();
   $arr = array_diff( $arr_enum, array_slice( $arr_not, 1 ) );
   return " IN ('" . implode("','", $arr) . "') ";
}


function RGBA($r, $g, $b, $a=NULL)
{
   if( is_null($a) )
      return sprintf("%02x%02x%02x", $r, $g, $b);
   else
      return sprintf("%02x%02x%02x%02x", $r, $g, $b, $a);
}

function blend_alpha($red, $green, $blue, $alpha,
      $bgred=0xf7, $bggreen=0xf5, $bgblue=0xe3) //$bg_color values
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

// param bgcolor: if null, use default
function blend_alpha_hex($color, $bgcolor=null)
{
   if( is_null($bgcolor) )
      $bgcolor = "f7f5e3"; //$bg_color value (#f7f5e3)
   list($r,$g,$b,$a)= split_RGBA($color, 0);
   list($br,$bg,$bb,$ba)= split_RGBA($bgcolor);
   return blend_alpha($r,$g,$b,$a,$br,$bg,$bb);
}

function blend_warning_cell_attb( $title='', $bgcolor='f7f5e3', $col='ff000033')
{
   $str= ' bgcolor="#' . blend_alpha_hex( $col, $bgcolor) . '"';
   if( $title )
      $str.= ' title="' . $title . '"';
   return $str;
}

function attb_quote( $str)
{
   return '"'.basic_safe(trim($str)).'"';
}

//return an array of the attributs $attbs
function attb_parse( $attbs)
{
   if( is_array($attbs) )
      return array_change_key_case( $attbs, CASE_LOWER);
   if( !is_string($attbs) )
      return array();

   $nr_matches = preg_match_all(
      "%\\b([a-z][a-z0-9]*)\\s*=\\s*(((['\"])(.*?)\\4)|([a-z0-9]+\\b))%is"
      , $attbs, $matches );

   $attbs = array();
   for($i=0; $i<$nr_matches; $i++)
   {
      $key = $matches[1][$i];
      if( !$key )
         continue;
      $val = $matches[6][$i];
      if( !$val )
         $val = $matches[5][$i];
      $attbs[strtolower($key)]= $val;
   }
   return $attbs;
}

//return a string of the attributs $attbs
function attb_build( $attbs)
{
   $attbs= attb_parse( $attbs);
   if( is_array($attbs) )
   {
      $str= '';
      foreach( $attbs as $key => $val )
      {
         if( $key == 'colspan' && $val < 2 )
            continue;
         $str .= ' '.$key.'=';

         // don't quote values of JavaScript-attributes
         //if( preg_match( "/^(on((dbl)?click|mouse(down|up|over|move)|key(press|down|up)))$/i", $key ) )
         if( strncasecmp($key,'on',2) == 0 ) // begins with 'on'
            $str .= "\"$val\"";
         else
            $str .= attb_quote($val);
      }
      return $str;
   }
   return '';
}

//merge two arrays of attributs
//$attb2 overwrite $attb1, special behaviour for the classes
function attb_merge( $attb1, $attb2, $class_sep='')
{
/* must be done before call:
   $attb1 = attb_parse( $attb1);
   $attb2 = attb_parse( $attb2);
*/
   if( isset($attb1['class']) && isset($attb2['class']) )
   {
      if( is_string($class_sep) )
         $attb2['class'] = $attb1['class'].$class_sep.$attb2['class'];
      //as other CSS items, the priority is for the last in the CSS file.
      unset($attb1['class']);
   }
   return array_merge($attb1, $attb2);
}

// if $title=null use same as $alt
function image( $src, $alt, $title='', $attbs='', $height=-1, $width=-1)
{
   $str = "<img src=\"$src\" alt=".attb_quote($alt);
   if( $title )
      $str.= ' title='.attb_quote($title);
   elseif( is_null($title) )
      $str.= ' title='.attb_quote($alt);
   if( $height>=0 )
      $str.= " height=\"$height\"";
   if( $width>=0 )
      $str.= " width=\"$width\"";
   $str.= attb_build($attbs);
   return $str.'>';
}

function anchor( $href, $text=null, $title='', $attbs='')
{
   if( is_null($text) ) $text = $href;
   $str = "<a href=\"$href\"";
   if( is_array($attbs) )
   {
      if( isset($attbs['accesskey']) )
      {
         $xkey = trim($attbs['accesskey']);
         unset($attbs['accesskey']);
         if( (string)$xkey != '' ) // can be '0'
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

/*!
 * \brief Return a stratagem to force a minimal column width.
 * param $height default=0 is useful for Avant browser, others are managed with CSS
 *
 * NOTE: must be changed when, a day, the CSS min-width command
 *       will work fine with every browser.
 *       dot.gif is a 1x1 transparent image.
 */
function insert_width( $width=1, $height=0, $use_minwid=false )
{
   global $base_path;
   if( !is_numeric($width) )
      $width = 1;
   if( !is_numeric($height) )
      $height = 0;
   $img_class = ($use_minwid) ? ' class="MinWidth"' : '';
   return "<img{$img_class} src=\"{$base_path}images/dot.gif\" width=\"$width\" height=\"$height\" alt=''>";
}

/*! \brief Returns trimmed and stripped game-notes (used for games-lists). */
function strip_gamenotes( $note )
{
   // strip special-chars (including tabs/LF and following text)
   return trim( substr( preg_replace('/[\x00-\x1f].*$/s','',$note) , 0, LIST_GAMENOTE_LEN) );
}

?>
