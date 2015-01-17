<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/globals.php';
require_once 'include/quick_common.php';
require_once 'include/utilities.php';
require_once 'include/dgs_cache.php';

require_once 'include/time_functions.php';

global $page_microtime, $main_path, $base_path, $printable; //PHP5
if ( !isset($page_microtime) )
{
   $page_microtime = getmicrotime();
   //std_functions.php must be called from the main dir
   $main_path = str_replace('\\', '/', getcwd()).'/';
   //$base_path is relative to the URL, not to the current dir
   $base_path = rel_base_dir();
   $printable = (bool)@$_REQUEST['printable'];
}

require_once 'include/page_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/game_texts.php';

require_once 'include/translation_functions.php';
require_once 'include/classlib_matrix.php';
require_once 'forum/class_forum_read.php';
require_once 'include/db/bulletin.php';
require_once 'include/survey_control.php';


// Server birth date:
define('BEGINYEAR', 2001);
define('BEGINMONTH', 8);

define('DGS_DESCRIPTION',
   "The Dragon Go Server (DGS) is a place where you can play turn-based Go (aka Baduk or Weichi) with other players from around the world in different time zones. " .
   "It functions more or less the same way as playing Go via email but just using your web browser. DGS also provides discussion forums about Go." );

// because of the cookies host, $hostname_jump = true is nearly mandatory
global $hostname_jump; //PHP5
$hostname_jump = true;  // ensure $HTTP_HOST is same as HOSTNAME

/* when $GUESTPASS is modified,
 * run HOSTBASE."change_password.php?guestpass=".GUEST_ID
 * with ADMIN_PASSWORD privileges
 */
global $GUESTPASS; //PHP5
$GUESTPASS = ( FRIENDLY_SHORT_NAME == 'DGS' ) ? 'guestpass' : 'guest';

define('GUESTS_ID_MAX', 1); //minimum 1 because hard-coded in init.mysql

define('LAYOUT_FILTER_IN_TABLEHEAD', true); // default is to show filters within tablehead (not below rows)
define('LAYOUT_FILTER_EXTFORM_HEAD', true); // default is to show external-filter-form above filter-table

define('SPAN_ONLINE_MINS', 10); // being "online" = if last-accessed during last X minutes

//----- { layout : change in dragon.css too!
global $bg_color; //PHP5
$bg_color='"#f7f5e3"';

//$menu_fg_color='"#FFFC70"';
global $menu_bg_color; //PHP5
if ( FRIENDLY_SHORT_NAME == 'DGS' )
   $menu_bg_color='"#0C41C9"'; //live server
else
   $menu_bg_color='"#C9410C"'; //devel server

// NOTE: only used for folder transparency, but CSS incompatible
global $table_row_color1, $table_row_color2; //PHP5
$table_row_color1='"#FFFFFF"';
$table_row_color2='"#E0E8ED"';


//----- } layout : change in dragon.css too!


global $max_links_in_main_menu; //PHP5
$max_links_in_main_menu=5;

//-----
//Tables - lists
define('LIST_ROWS_MODULO', 4); //at least 1

define('MAXROWS_PER_PAGE_DEFAULT', 20);
define('MAXROWS_PER_PAGE_PROFILE', 50);
define('MAXROWS_PER_PAGE_FORUM',   50); // max for forum-search
define('MAXROWS_PER_PAGE', 100);
//-----

global $ActivityHalvingTime, $ActivityForHit, $ActivityForMove; //PHP5
$ActivityHalvingTime = 4 * 24 * 60; // [minutes] four days halving time
$ActivityForHit = 1000.0; //it is the base unit for all the Activity calculus
$ActivityForMove = 10*$ActivityForHit;

global $ActiveLevel1, $ActiveLevel2, $ActivityMax; //PHP5
$ActiveLevel1 = $ActivityForMove + 2*$ActivityForHit; //a "move sequence" value
$ActiveLevel2 = 15*$ActiveLevel1;
$ActivityMax = 0x7FFF0000-$ActivityForMove;

define('NEWGAME_MAX_GAMES', 10);

define('GRAPH_RATING_MIN_INTERVAL', 2*31 * SECS_PER_DAY);

// see also CACHE_FOLDER in config.php
define('CACHE_EXPIRE_GRAPH', SECS_PER_DAY); //1 day

define('MENU_MULTI_SEP', ' / ');

define('BUTTON_WIDTH', 96);

global $buttonfiles, $buttoncolors, $woodbgcolors; //PHP5
$buttonfiles = array('button0.gif','button1.gif','button2.gif','button3.gif',
                     'button4.gif','button5.gif','button6.gif','button7.gif',
                     'button8.png','button9.png','button10.png','button10.png');
$buttoncolors = array('white','white','white','white',
                      '#990000','white','white','white',
                      'white','white','white','black');
define('BUTTON_MAX', count($buttonfiles));

$woodbgcolors = array(1=>'#e8c878','#e8b878','#e8a858', '#d8b878', '#b88848', '#ffffff');

global $cookie_pref_rows; //PHP5
$cookie_pref_rows = array(
       // global config (from Players-table):
       'UserFlags',
       'Button',
       'TableMaxRows',
       'ThumbnailSize',
       'MenuDirection',
       'SkinName',
       // board config (from ConfigBoard-table):
       // NOTE: place also all prefs from ConfigBoard-table in players_row,
       //       but manage with ConfigBoard-class; see is_logged_in()
       // NOTE: also add in ConfigBoard::load_config_board()
       'Stonesize', 'Woodcolor', 'BoardFlags', 'Boardcoords',
       'MoveNumbers', 'MoveModulo',
       'NotesSmallHeight', 'NotesSmallWidth', 'NotesSmallMode',
       'NotesLargeHeight', 'NotesLargeWidth', 'NotesLargeMode',
       'NotesCutoff',
   );

global $vacation_min_days; //PHP5
$vacation_min_days = 2;

define('INFO_HTML', 'cell'); //HTML parsing for texts like 'Rank info'
define('SUBJECT_HTML', false); //HTML parsing for subjects of posts and messages

define('DELETE_LIMIT', 10);

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
define('POSX_SETUP', -5);  // setup for shape-game for Stone=BLACK: PosY=0, Hours=0
define('POSX_FORFEIT', -6); // forfeit game (for Stone=BLACK|WHITE), set by game-admin (only for Games.Last_X, not stored in Moves-table)
define('POSX_NO_RESULT', -7); // no-result game, set by game-admin (only for Games.Last_X, not stored in Moves-table)
// game commands
define('POSX_ADDTIME', -50); // Add-Hours: Stone=BLACK|WHITE (time-adder), PosY=bitmask (bit #1(0|1)=byoyomi-reset, bit #2(0|2)=added-by-TD), Hours=add_hours

define('MOVE_SETUP', 'S'); // setup-move #0 for shape-game; NOTE: '0' already has special meaning (go to last-move)


define('DEFAULT_KOMI', 6.5); // change with care only, keep separate from STONE_VALUE
define('STONE_VALUE',13); // 2 * conventional komi (=DEFAULT_KOMIT), change with care

define('MIN_BOARD_SIZE',5);
define('MAX_BOARD_SIZE',25);
define('MAX_KOMI_RANGE',200);
define('MAX_HANDICAP',21); // should not be larger than biggest default-max-handicap, see DefaultMaxHandicap::calc_def_max_handicap()
define('DEFAULT_MAX_HANDICAP', -1);

// admin text-object-type, used in admin_faq.php
define('TXTOBJTYPE_FAQ',   0); // edit FAQ-table
define('TXTOBJTYPE_INTRO', 1); // edit Intro-table
define('TXTOBJTYPE_LINKS', 2); // edit Links-table


// b0=0x1 (standard placement), b1=0x2 (with black validation skip), b2=0x4 (all placements)
// both b1 and b2 set is not fully handled (error if incomplete pattern)
// NOTE: value != 0x3 may not be supported by quick-suite
define('ENABLE_STDHANDICAP', 0x3);

define('ENA_MOVENUMBERS', 1);
define('MAX_MOVENUMBERS', 500);

define('MAX_REJECT_TIMEOUT', 120); // days


//-----
// Players.UserFlags (also stored in cookie);  adjust also admin_users.php showing user-flags
define('USERFLAG_JAVASCRIPT_ENABLED', 0x0001);
define('USERFLAG_NFY_BUT_NO_OR_INVALID_EMAIL', 0x0002); // user has SendEmail>'' but missing or invalid Email
define('USERFLAG_VERIFY_EMAIL', 0x0004); // if set, Players.Email will be replaced with verified email
define('USERFLAG_ACTIVATE_REGISTRATION', 0x0008); // if set, account will be activated (accessible) after email-verification
define('USERFLAG_EMAIL_VERIFIED', 0x0010); // marks email as verified by email-verification
define('USERFLAG_RATINGGRAPH_BY_GAMES', 0x0020); // show rating-graph 'byGames' if set, else 'byTime' (=default)
//-----


//-----
// admin-roles
// NOTE: when adding new roles also adjust: admin_show_users.php, people.php#get_executives()
define("ADMIN_TRANSLATORS",0x01);
define("ADMIN_FAQ",0x02);
define("ADMIN_FORUM",0x04);
define("ADMIN_SUPERADMIN",0x08); // manage admins (add, edit, delete)
define('ADMIN_TOURNAMENT',0x10);
define('ADMIN_FEATURE',0x20); // feature-voting
define("ADMIN_PASSWORD",0x40);
define('ADMIN_DATABASE',0x80);
define('ADMIN_DEVELOPER',0x100);
define('ADMIN_SKINNER',0x200);
define('ADMIN_GAME',0x400); // game, player-rating
define('ADMIN_SURVEY',0x800); // survey-voting
// admin groups
define('ADMINGROUP_EXECUTIVE', (ADMIN_FAQ|ADMIN_FORUM|ADMIN_FEATURE|ADMIN_SURVEY|ADMIN_TOURNAMENT|ADMIN_PASSWORD|ADMIN_DEVELOPER|ADMIN_GAME));
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
   if ( $usertype & USERTYPE_PRO )
   {
      $text = T_('Professional');
      $tmp = ($img) ? image( "{$base_path}images/professional.gif", $text, null ) : '';
      if ( $short !== ARG_USERTYPE_NO_TEXT )
         $tmp .= ($img ? ' ' : '') . ($short ? T_('Pro#utype_short') : $text);
      $out[] = $tmp;
   }
   if ( $usertype & USERTYPE_TEACHER )
   {
      $text = T_('Teacher');
      $tmp = ($img) ? image( "{$base_path}images/teacher.gif", $text, null ) : '';
      if ( $short !== ARG_USERTYPE_NO_TEXT )
         $tmp .= ($img ? ' ' : '') . ($short ? T_('Teacher#utype_short') : $text);
      $out[] = $tmp;
   }
   if ( $usertype & USERTYPE_ROBOT )
   {
      $text = T_('Robot');
      $tmp = ($img) ? image( "{$base_path}images/robot.gif", $text, null ) : '';
      if ( $short !== ARG_USERTYPE_NO_TEXT )
         $tmp .= ($img ? ' ' : '') . ($short ? T_('Bot#utype_short') : $text);
      $out[] = $tmp;
   }
   if ( $usertype & USERTYPE_TEAM )
   {
      $text = T_('Team');
      $tmp = ($img) ? image( "{$base_path}images/team.gif", $text, null ) : '';
      if ( $short !== ARG_USERTYPE_NO_TEXT )
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

function start_html( $title, $no_cache, $skinname=NULL, $style_string=NULL, $last_modified_stamp=NULL,
                     $javascript=null )
{
   global $base_path, $encoding_used, $printable, $main_path;

   if ( $no_cache )
      disable_cache($last_modified_stamp);

   global $ThePage;
   $has_thepage = ( $ThePage instanceof HTMLPage );
   if ( !$has_thepage )
      ob_start('ob_gzhandler');

   if ( empty($encoding_used) )
      $encoding_used = LANG_DEF_CHARSET;

   header('Content-Type: text/html;charset='.$encoding_used); // Character-encoding

   //This full DOCTYPE make most of the browsers to leave the "quirks" mode.
   //This may be a disavantage with IE5-mac because its "conform" mode is worst.
   echo '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">';

   echo "\n<HTML>\n<HEAD>";

   echo "\n <meta http-equiv=\"content-type\" content=\"text/html;charset=$encoding_used\">";

   $meta_description = ( $has_thepage && $ThePage->meta_description )
      ? $ThePage->meta_description
      : 'Play the board game Go on a turn by turn basis.'; // keep in English
   echo "\n <meta name=\"description\" content=\"$meta_description\">";

   $meta_robots = ( $has_thepage && $ThePage->meta_robots ) ? $ThePage->meta_robots : ROBOTS_NO_INDEX_NOR_FOLLOW;
   echo "\n <meta name=\"robots\" content=\"$meta_robots\">";

   $meta_keywords = 'dgs, dragon, dragon go server, go server, go, igo, weiqi, weichi, baduk, board game, boardgame, turn-based, correspondence';
   if ( $has_thepage && $ThePage->meta_keywords )
      $meta_keywords .= ', ' . $ThePage->meta_keywords;
   echo "\n <meta name=\"keywords\" content=\"$meta_keywords\">";

   echo "\n <title>".basic_safe(FRIENDLY_SHORT_NAME." - $title")."</title>";

   //because of old browsers favicon.ico should always stay in the root folder
   echo "\n <link rel=\"shortcut icon\" type=\"image/x-icon\" href=\"".HOSTBASE."favicon.ico\">";

   if ( !isset($skinname) || !$skinname )
      $skinname = 'dragon';
   $skin_screen = ( file_exists("{$main_path}skins/$skinname/screen.css") ) ? $skinname : 'dragon';
   echo "\n <link rel=\"stylesheet\" type=\"text/css\" media=\"screen\" href=\"{$base_path}skins/$skin_screen/screen.css?t=".CSS_VERSION."\">";
   $skin_print = ( file_exists("{$main_path}skins/$skinname/print.css") ) ? $skinname : 'dragon';
   echo "\n <link rel=\"stylesheet\" type=\"text/css\" media=\"print\" href=\"{$base_path}skins/$skin_print/print.css?t=".CSS_VERSION."\">";

   $enable_js_game = false;
   $page = get_base_page();
   switch ( (string)$page )
   {
      case 'status.php':
         // RSS Autodiscovery:
         echo "\n <link rel=\"alternate\" type=\"application/rss+xml\""
             ," title=\"".FRIENDLY_SHORT_NAME." Status RSS Feed\" href=\"/rss/status.php\">";
         break;
      case 'game.php':
      case 'game_editor.php':
      case 'forum/read.php':
      case 'old_goban_editor.php':
         $enable_js_game = true;
         break;
   }

   if ( $style_string )
      echo "\n <STYLE TYPE=\"text/css\">\n",$style_string,"\n </STYLE>";

   if ( is_javascript_enabled() )
   {
      // for development reload everytime (on LIVE-server use JS_VERSION to use latest committed version)
      $ts = ( FRIENDLY_SHORT_NAME == 'DGS' ) ? JS_VERSION : date(DATE_FMT4, $GLOBALS['NOW']);

      $js_code = array(); // global declarations first (before including JS-libs)
      $js_code[] = add_js_var( 'base_path', $base_path );
      $js_code[] = add_js_var( 'T_js', dgs_json_encode(
         array(
            'quota_low'     => T_('Your access quota is running low!#js'),
            'save_success'  => T_('Save operation successful!#js'),
            'error_occured' => T_('Error occured#js'),
         )), true );
      echo "\n<script language=\"JavaScript\" type=\"text/javascript\">\n", implode("\n", $js_code), "</script>";
      echo "\n<script language=\"JavaScript\" type=\"text/javascript\" src=\"{$base_path}js/common.js?t=$ts\"></script>";

      if ( $enable_js_game )
      {
         //TODO later compress jquery + other-js into 2 separate compressed JS with dojo-toolkit and updated JS_VERSION
         if ( ALLOW_GAME_EDITOR || ENABLE_GAME_VIEWER )
         {
            echo "\n<script language=\"JavaScript\" type=\"text/javascript\" src=\"{$base_path}js/jquery-1.9.1.min.js\"></script>";
            echo "\n<script language=\"JavaScript\" type=\"text/javascript\" src=\"{$base_path}js/jquery-ui-1.10.3.custom.min.js\"></script>";
            echo "\n<script language=\"JavaScript\" type=\"text/javascript\" src=\"{$base_path}js/jquery.scrollTo-1.4.3.1-min.js\"></script>";
            echo "\n<script language=\"JavaScript\" type=\"text/javascript\" src=\"{$base_path}js/lang-ext.js?t=$ts\"></script>";
            echo "\n<script language=\"JavaScript\" type=\"text/javascript\" src=\"{$base_path}js/gametree.js?t=$ts\"></script>";
            echo "\n<script language=\"JavaScript\" type=\"text/javascript\" src=\"{$base_path}js/game-editor.js?t=$ts\"></script>";
            if ( ALLOW_GAME_EDITOR )
               echo "\n<script language=\"JavaScript\" type=\"text/javascript\" src=\"{$base_path}js/goban-editor.js?t=$ts\"></script>"; //TODO refactor later
         }
         if ( ALLOW_GO_DIAGRAMS )
            echo "\n<script language=\"JavaScript\" type=\"text/javascript\" src=\"{$base_path}js/goeditor.js\"></script>";
      }

      // additional JS-code (after including JS-libs)
      if ( !is_null($javascript) && is_string($javascript) )
         echo "\n<script language=\"JavaScript\" type=\"text/javascript\">\n$javascript\n</script>";
   }

   $tmp_class = ( $has_thepage )
      ? ' class='.attb_quote( $ThePage->getClassCSS() ) //may be multiple, i.e. 'Games Running'
      : '';
   echo "\n</HEAD>\n<BODY id=\"".FRIENDLY_SHORT_NAME."\"$tmp_class>\n";
   echo "<div id=\"InfoBox\"></div>\n";
   if ( is_javascript_enabled() )
      echo "<div id=\"JSInfoBox\"></div>\n";
} //start_html

function start_page( $title, $no_cache, $logged_in, &$player_row,
                     $style_string=NULL, $last_modified_stamp=NULL, $javascript=null )
{
   global $base_path, $is_down, $is_down_message, $ARR_USERS_MAINTENANCE, $printable;

   $user_handle = @$player_row['Handle'];
   if ( $is_down && $logged_in )
      check_maintenance( $user_handle );

   /* NOTE: prep for UTF-8 migration:
   if ( $logged_in )
      error_log(sprintf("START_PAGE: U[%s] L[%s] AL[%s] ACH[%s]", @$player_row['Handle'], @$player_row['Lang'], @$_SERVER['HTTP_ACCEPT_LANGUAGE'], @$_SERVER['HTTP_ACCEPT_CHARSET']));
    */

   start_html( $title, $no_cache, @$player_row['SkinName'], $style_string, $last_modified_stamp, $javascript );

   echo_dragon_top_bar( $logged_in, $user_handle );

   if ( !$printable ) // main-menu
   {
      if ( $logged_in )
         $menu = make_dragon_main_menu( $player_row );
      else
      {
         $menu = make_dragon_main_menu_logged_out();
         $player_row['MenuDirection'] = 'VERTICAL'; //layout like menu_vertical without menu
      }

      $tools_array = make_dragon_tools();
   }

   if ( $is_down || $printable )
   {
      //layout like menu_horizontal without menu
      $player_row['MenuDirection'] = 'HORIZONTAL';
      echo "\n<table id='pageLayout'>" //layout table
         . "\n <tr class=LayoutHorizontal>";
   }
   elseif ( $player_row['MenuDirection'] == 'HORIZONTAL' )
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

   if ( is_javascript_enabled() )
      echo "<div id=\"QuotaWarning\"></div>"; // for JS-quota-warning
   sysmsg(get_request_arg('sysmsg'));
   if ( isset($player_row['VaultCnt']) && $player_row['VaultCnt'] <= 11 )
   {
      if ( $player_row['VaultCnt'] > 0 )
      {
         $block_hours = ( (int)@$player_row['ID'] > GUESTS_ID_MAX ? VAULT_TIME : VAULT_TIME_X ) / SECS_PER_HOUR;
         sysmsg( sprintf( T_('Your access quota is running low.<br>You only got %s hits left before you get blocked for %s !!'),
                 @$player_row['VaultCnt'] - 1, TimeFormat::echo_hour($block_hours) ),
                 'WarnQuota');
      }
      else
      {
         sysmsg( T_('Your access quota is exceeded.'), 'WarnQuota');
      }
   }

   if ( $is_down )
   {
      echo "<br><br>\n", $is_down_message, "<br><br><br>\n";
      end_page();
      exit;
   }
} //start_page

function echo_dragon_top_bar( $logged_in, $user_handle )
{
   global $base_path, $is_down, $printable, $is_maintenance, $player_row;

   // forum for bookmark (around table for formatting)
   if ( !$printable )
      echo '<form name="bookmarkForm" action="'.$base_path.'bookmark.php" method="GET">';

   echo "\n\n<table id=\"pageHead\">",
      "\n <tr>",
      "\n  <td class=\"ServerHome\"><A id=\"homeId\" href=\"".HOSTBASE."index.php\">", FRIENDLY_LONG_NAME, "</A>";

   // show bookmarks
   if ( !$printable && $logged_in && !$is_down )
   {
      echo SEP_SPACING,
         '<select name="jumpto" size="1"',
               ( is_javascript_enabled() ? " onchange=\"this.form['show'].click();\"" : '' ), ">\n",
            '<option value="">&lt;', T_('Bookmarks#bookmark'), "&gt;</option>\n",
            '<option value="S6">', T_('My running MP-games#bookmark'), "</option>\n",
            '<option value="S1">', T_('Latest forum posts#bookmark'), "</option>\n",
            '<option value="S2">', T_('Opponents online#bookmark'), "</option>\n",
            '<option value="S3">', T_('Users online#bookmark'), "</option>\n",
            '<option value="S4">', T_('Edit vacation#bookmark'), "</option>\n",
            '<option value="S5">', T_('Edit profile#bookmark'), "</option>\n",
         '</select>',
         '<input type="submit" name="show" value="', T_('Show#bookmark'), '">'
         ;
   }

   if ( $is_maintenance ) // mark also for maintainers
      echo SEP_MEDSPACING . span('Maintenance', '[MAINTENANCE]');
   if ( @$player_row['admin_level'] & ADMIN_TOURNAMENT ) // remind of mighty T-Admin
      echo SEP_MEDSPACING . span('AdminTournament', '[Tournament-Admin]');

   echo "</td>";
   echo "\n  <td class=\"LoginBox\">";

   if ( $logged_in && !$is_down )
      echo T_("Logged in as"), ': <a id="loggedId" title="' . $player_row['ID'] . '">', $user_handle, '</a>';
   else
      echo T_("Not logged in");

   echo "</td>",
      "\n </tr>\n</table>\n";

   if ( !$printable )
      echo '</form>';
} //echo_dragon_top_bar

function make_dragon_main_menu_logged_out()
{
   $menu = new Matrix(); // see make_dragon_main_menu()
   // object = arr( itemtext, itemlink, itemtitle [, arr( accesskey/class => value ) ]

   $menu->add( 1,1, array( T_('Login'),        'index.php?logout=t', '', array()));
   $menu->add( 1,2, array( T_('Register'),     'register.php',       '', array()));

   $menu->add( 2,1, array( T_('Introduction'), 'introduction.php', '', array()));
   $menu->add( 2,2, array( T_('Policy'),       'policy.php',       '', array()));
   $menu->add( 2,3, array( T_('Help / FAQ'),   'faq.php',
      T_('search site documentation#menu'), array( 'accesskey' => ACCKEY_MENU_FAQ )));

   $menu->add( 3,1, array( T_('Docs'),         'docs.php',
      T_('show policy, release notes, links, contributors, tools, sources, license#menu'),
      array( 'accesskey' => ACCKEY_MENU_DOCS )));
   $menu->add( 3,2, array( T_('Site map'),     'site_map.php',     T_('show all pages of this site#menu'), array()));
   $menu->add( 3,3, array( T_('Statistics'),   'statistics.php',   '', array()));

   return $menu;
} //make_dragon_main_menu_logged_out

function make_dragon_main_menu( $player_row )
{
   $cnt_msg_new = (isset($player_row['CountMsgNew'])) ? (int)$player_row['CountMsgNew'] : -1;
   $cnt_feat_new = (isset($player_row['CountFeatNew'])) ? (int)$player_row['CountFeatNew'] : -1;
   $cnt_bulletin_new = (isset($player_row['CountBulletinNew'])) ? (int)$player_row['CountBulletinNew'] : -1;
   $cnt_tourney_new = (isset($player_row['CountTourneyNew'])) ? (int)$player_row['CountTourneyNew'] : -1;
   $has_forum_new = load_global_forum_new();

   $menu = new Matrix(); // keep x/y sorted (then no need to sort in make_menu_horizontal/vertical)
   // object = arr( itemtext, itemlink, itemtitle [, arr( accesskey/class => value ) ]
   // NOTE: row-number can be skipped, only for ordering
   // NOTE: multi-text per matrix-entry possible: use list of arrays with arr( itemtext ..) or sep-str
   $menu->add( 1,1, array( T_('Status'),       'status.php',       '', array( 'accesskey' => ACCKEY_MENU_STATUS, 'class' => 'strong' )));
   $menu->add( 1,2, array( T_('Waiting room'), 'waiting_room.php',
      T_('show new game offers from other players#menu'), array( 'accesskey' => ACCKEY_MENU_WAITROOM )));
   if ( ALLOW_TOURNAMENTS )
   {
      $arr_tourney = array( array( T_('Tournaments'), 'tournaments/list_tournaments.php', '', array( 'accesskey' => ACCKEY_MENU_TOURNAMENT )) );
      if ( $cnt_tourney_new > 0 )
      {
         $arr_tourney[] = MINI_SPACING;
         $arr_tourney[] = span('MainMenuCount', $cnt_tourney_new, '(%s)', T_('New tournaments') );
      }
      $menu->add( 1,3, $arr_tourney );
   }
   $menu->add( 1,4, array( T_('User info'),    'userinfo.php',     '', array( 'accesskey' => ACCKEY_MENU_USERINFO )));

   $arr_msgs = array( array( T_('Messages'), 'list_messages.php',
      T_('show your messages and folders#menu'), array( 'accesskey' => ACCKEY_MENU_MESSAGES ) ));
   if ( $cnt_msg_new > 0 )
   {
      $arr_msgs[] = MINI_SPACING;
      $arr_msgs[] = array( span('MainMenuCount', $cnt_msg_new, '(%s)' ),
         'list_messages.php?folder='.FOLDER_NEW,
         T_('show new messages#menu'),
         array( 'class' => 'MainMenuCount' ) );
   }
   $menu->add( 2,1, $arr_msgs );
   $menu->add( 2,2, array( T_('Send message'), 'message.php?mode=NewMessage', '', array( 'accesskey' => ACCKEY_MENU_SENDMSG )));
   $menu->add( 2,3, array( T_('Invite'),       'message.php?mode=Invite',
      T_('send game invitation to another player#menu'), array( 'accesskey' => ACCKEY_MENU_INVITE )));
   $menu->add( 2,4, array( T_('New Game'),     'new_game.php',
      T_('offer new games in waiting room for other players#menu'), array( 'accesskey' => ACCKEY_MENU_NEWGAME )));

   $menu->add( 3,1, array( T_('Users'),    'users.php',              '', array( 'accesskey' => ACCKEY_MENU_USERS )));
   $menu->add( 3,2, array( T_('Contacts'), 'list_contacts.php',      '', array( 'accesskey' => ACCKEY_MENU_CONTACTS )));
   $menu->add( 3,3, array( T_('Games'),    'show_games.php?uid=all', '', array( 'accesskey' => ACCKEY_MENU_GAMES )));

   $menu->add( 4,1, array( T_('Introduction'), 'introduction.php', '', array()));
   $menu->add( 4,2, array( T_('Help / FAQ'), 'faq.php',
      T_('search site documentation#menu'), array( 'accesskey' => ACCKEY_MENU_FAQ )));
   $menu->add( 4,3, array( T_('Site map'), 'site_map.php',
      T_('show all pages of this site#menu'), array()));
   $menu->add( 4,4, array( T_('Docs'),     'docs.php',
      T_('show policy, release notes, links, contributors, tools, sources, license#menu'),
      array( 'accesskey' => ACCKEY_MENU_DOCS )));

   $arr_forums = array( array( T_('Forums'), 'forum/index.php',
      T_('show support and discussion forums#menu'), array( 'accesskey' => ACCKEY_MENU_FORUMS )) );
   if ( $has_forum_new )
   {
      $arr_forums[] = MINI_SPACING;
      $arr_forums[] = array( span('MainMenuCount', '(*)'), 'bookmark.php?jumpto=S1',
         T_('show forums with new entries#menu'), array( 'class' => 'MainMenuCount' ) );
   }
   $menu->add( 5,1, $arr_forums );
   $arr_bulletins = array( array( T_('Bulletins'), 'list_bulletins.php?read=2'.URI_AMP.'no_adm=1', '', array()) );
   if ( $cnt_bulletin_new > 0 )
   {
      $arr_bulletins[] = MINI_SPACING;
      $arr_bulletins[] = array( span('MainMenuCount', $cnt_bulletin_new, '(%s)' ),
         'list_bulletins.php?text=1'.URI_AMP.'view=1'.URI_AMP.'no_adm=1',
         '',
         array( 'class' => 'MainMenuCount' ) );
   }
   $menu->add( 5,2, $arr_bulletins );
   if ( ALLOW_FEATURE_VOTE )
   {
      $arr_feats = array( array( T_('Features'), 'features/list_votes.php',
         T_('show feature votes#menu'), array( 'accesskey' => ACCKEY_MENU_VOTE )) );
      if ( $cnt_feat_new > 0 )
      {
         $arr_feats[] = MINI_SPACING;
         $arr_feats[] = array( span('MainMenuCount', $cnt_feat_new, '(%s)' ),
            'features/list_features.php?status=2'.URI_AMP.'my_vote=1',
            T_('vote on new features#menu'),
            array( 'class' => 'MainMenuCount' ) );
      }
      $menu->add( 5,3, $arr_feats );
   }
   if ( ALLOW_GAME_EDITOR )
      $menu->add( 5,4, array( T_('Game Editor'), 'game_editor.php', '', array()));
   $menu->add( 5,5, array( T_('Goban Editor'), 'goban_editor.php', '', array()));

   return $menu;
}//make_dragon_main_menu

function make_dragon_tools()
{
   global $base_path;

   $tools_array = array(); //$url => array($img,$alt,$title)
   $page = get_base_page();
   switch ( (string)$page )
   {
      case 'status.php':
      {
         if ( ENABLE_DONATIONS )
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

function end_page( $menu_array=NULL, $links_per_line=0 )
{
   global $page_microtime, $player_row, $base_path, $printable, $NOW;

   section(); //close if any

   echo "\n  </td>"; //close the pageBody

   if ( $menu_array && !$printable )
   {
      echo "\n </tr><tr class=Links>"
         . "\n  <td class=Links>";
      make_menu( $menu_array, true, $links_per_line );
      echo "\n  </td>";
   }

   //close the <table><tr><td> left open since page start
   echo "\n </tr>\n</table>\n";

   { //hostlink build
      if ( HOSTNAME == "dragongoserver.sourceforge.net" ) //for devel server
         $hostlink= '<A href="http://sourceforge.net" target="_blank"><IMG src="http://sourceforge.net/sflogo.php?group_id=29933&amp;type=1" alt="SourceForge.net Logo" width=88 height=31 border=0 align=middle></A>';
      else //for live server
         $hostlink= '<a href="http://www.samurajdata.se" target="_blank"><img src="'.$base_path.'images/samurajlogo.gif" alt="Samuraj Logo" width=160 height=20 border=0 align=middle></a>';
   } //hostlink build


   global $NOW;
   echo "\n<table id=pageFoot>",
      "\n <tr>",
      "\n  <td class=\"ServerHome NoPrint\"><A href=\"{$base_path}index.php\">", FRIENDLY_LONG_NAME, "</A> ",
      span('Version', '[' . anchor( $base_path.'news.php', T_('Version') . ' ' . DGS_VERSION ) . ']'),
      "</td>";

   echo "\n  <td class=PageTime>", T_('Page time'),
      ': <span id="pageTime">', format_translated_date(DATE_FMT5, $NOW), "</span>";

   if ( !$printable )
   {
      echo "<span class=NoPrint>";
      if ( isset($player_row['VaultCnt']) && isset($player_row['X_VaultTime']) )
      {
         echo '<br>', span('PageQuota',
            sprintf( "%s: %s / %s", T_('Quota#user'), span('QuotaCount', $player_row['VaultCnt']),
               format_translated_date(DATE_FMT5, $player_row['X_VaultTime']) ));
      }

      echo '<br>', span('PageLapse',
            T_('Page created in') . sprintf(' %0.2f ms', (getmicrotime() - $page_microtime)*1000));
      echo "</span>";
   }

   echo "</td>";

   if ( !$printable )
   {
      echo "\n  <td class=LoginBox>";

      if ( @$player_row['admin_level'] && !$printable )
         echo "<a href=\"{$base_path}admin.php\">", T_('Admin'), "</a>", SMALL_SPACING;

      if ( @$player_row['Translator'] && !$printable )
      {
         $curr_page = get_base_page();
         echo anchor( $base_path.'translate.php', T_('Translate'), '', array( 'accesskey' => ACCKEY_MENU_TRANSLATE )),
            MINI_SPACING,
            span('smaller',
               anchor( $base_path.'translate.php?tpage='.urlencode($curr_page),
                  sprintf('(%s)', T_('page#translate'))) ),
            SMALL_SPACING;
      }

      echo anchor( $base_path."index.php?logout=t",
                   ( @$player_row['ID'] > 0 ) ? T_('Logout') : T_('Login'),
                   '', array( 'accesskey' => ACCKEY_MENU_LOGOUT ));

      echo "</td>",
           "\n </tr>",
           "\n</table>";

      // Start of a new host line
      echo "\n<table class=\"HostedBy NoPrint\">",
           "\n <tr>";
   }

   //continuation of host line
   echo "\n  <td id='hostedBy'>", T_('Hosted by'), "&nbsp;&nbsp;$hostlink</td>";

   echo "\n </tr>",
        "\n</table>";

   end_html();
} //end_page

function end_html()
{
   if ( isset($TheErrors) )
   {
      if ( $TheErrors->error_count() )
         echo $TheErrors->list_string('garbage', 1);
   }
   echo "\n</BODY>\n</HTML>";

   global $ThePage;
   if ( !($ThePage instanceof HTMLPage) )
      ob_end_flush();
} //end_html

//push a level in the output stack
function grab_output_start( $compressed=0)
{
   if ( $compressed )
      return ob_start('ob_gzhandler');
   else
      return ob_start();
}

//grab the output buffer into a file
//also copy it in the previous level of the output stack
function grab_output_end( $filename='')
{
   if ( !$filename )
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
 *
 * \note IMPORTANT: must only be called if user has been authenticated, otherwise exploit possible!
 */
function check_maintenance( $user_handle )
{
   global $is_down, $ARR_USERS_MAINTENANCE;

   $is_maint_user = ( is_array($ARR_USERS_MAINTENANCE) && in_array( $user_handle, $ARR_USERS_MAINTENANCE ) );
   if ( $is_down && $is_maint_user )
      $is_down = false;
   return $is_maint_user;
}

// shows maintenance page if server down
function show_maintenance_page()
{
   if ( @$GLOBALS['is_down'] )
   {
      global $player_row;
      start_page('Server down', true, false, $player_row);
      exit;
   }
}


// make bottom page-links
//   supported formats in $menu_array:
//    linktext  => URL
//    linktext  => array( 'url' => URL, attb1 => val1, ... )
//    dummytext => Form-object
// $links_per_line : <0 (absolute), >0 (balanced)
function make_menu($menu_array, $with_accesskeys=true, $links_per_line=0 )
{
   global $base_path, $max_links_in_main_menu;

   $balanced = ( $links_per_line >= 0 );
   if ( $links_per_line == 0 )
      $links_per_line = $max_links_in_main_menu;
   elseif ( !$balanced )
      $links_per_line = -$links_per_line;

   $nr_menu_links = count($menu_array);
   if ( $nr_menu_links == 0 )
      return;

   $menu_levels = ceil( $nr_menu_links / $links_per_line );
   $menu_width = ( $balanced ) ? ceil($nr_menu_links / $menu_levels) : $links_per_line;
   $w = 100/$menu_width;

   echo "\n\n<table id=\"pageLinks\" class=Links>\n <tr>";

   $cumwidth = $cumw = 0;
   $i = 0;
   foreach ( $menu_array as $text => $link )
   {
      if ( ($i % $menu_width)==0 && $i>0 )
      {
         echo "\n </tr>\n <tr>";
         $cumw = 0;
         $cumwidth = 0;
      }
      $i++;
      $cumw += $w;
      $width = round($cumw - $cumwidth);

      echo "\n  <td width=\"$width%\">";
      if ( $link instanceof Form )
         echo $link->echo_string();
      else
         echo make_menu_link( $text, $link, ($with_accesskeys ? $i % 10 : '') );
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
   if ( (string)$accesskey != '' )
      $attbs['accesskey'] = $accesskey;
   if ( is_array($link) )
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

   for ( $row=1; $row <= $rows; $row++ )
   {
      for ( $col=1; $col <= $cols; $col++ )
      {
         $menuitem = $menu->get_entry( $col, $row );
         if ( !is_null($menuitem) ) // matrix-point unset
         {
            if ( is_array($menuitem[0]) )
            {
               // object = arr( sep-string | arr( itemtext, itemlink, itemtitle [, arr( accesskey/class => value ) ] ), ...) for multi-items
               $content = '';
               foreach ( $menuitem as $mitem )
               {
                  if ( is_array($mitem) )
                  {//item-arr
                     @list( $text, $link, $title, $attbs ) = $mitem;
                     $content .= anchor( $base_path.$link, $text, $title, $attbs);
                  }
                  else //separator
                     $content .= $mitem;
               }
            }
            else
            {
               // object = arr( itemtext, itemlink, arr( accesskey/class => value ))
               @list( $text, $link, $title, $attbs ) = $menuitem;
               $content = anchor( $base_path.$link, $text, $title, $attbs);
            }
         }
         else
            $content = '';
         echo "\n  <td width=\"$w%\">$content</td>";
      }

      // right logo
      if ( $row == 1 )
         echo sprintf( $logo_line, $w, 'Logo2', $rows, 'dragonlogo_br.jpg');
      if ( $row < $rows )
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
}//make_menu_horizontal

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
   for ( $x=1; $x <= $cntX; $x++ )
   {
      if ( $x > 1 )
          echo '</td>'
             . "\n </tr><tr>"
             . "\n  <td height=1><img height=1 src=\"{$base_path}images/dot.gif\" alt=\"\"></td>"
             . "\n </tr><tr>"
             . "\n  <td align=left nowrap>";

      $menuitems = $menu->get_y_entries( $x );
      foreach ( $menuitems as $menuitem )
      {
         if ( is_array($menuitem[0]) )
         {
            // object = arr( sep-string | arr( itemtext, itemlink, itemtitle [, arr( accesskey/class => value ) ] ), ...) for multi-items
            foreach ( $menuitem as $mitem )
            {
               if ( is_array($mitem) )
               {//item-arr
                  @list( $text, $link, $title, $attbs ) = $mitem;
                  echo anchor( $base_path.$link, $text, $title, $attbs);
               }
               else //separator
                  echo $mitem;
            }
         }
         else
         {
            // object = arr( itemtext, itemlink, arr( accesskey/class => value ))
            @list( $text, $link, $title, $attbs ) = $menuitem;
            echo anchor( $base_path.$link, $text, $title, $attbs);
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
}//make_menu_vertical

function echo_menu_tools( $array, $width=0)
{
   if ( !is_array($array) || count($array)==0 )
      return;
   echo "<table class=NotPrintable id='pageTools'>\n<tr>\n";
   $c= 0;
   $r= 1;
   foreach ( $array as $lnk => $sub )
   {
      list( $src, $alt, $tit) = $sub;
      if ( $width>0 && $c>=$width )
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
   if ( $r>1 && $c>0 )
   {
      if ( $c>1 )
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

function sysmsg($msg, $class='SysMsg')
{
   if ( isset($msg) && ($msg=trim(make_html_safe($msg,'msg'))) )
      echo "\n<p class=\"$class\">$msg</p><hr class=SysMsg>\n";
}



//must never allow quotes, ampersand, < , > and URI reserved chars
//to be used in a preg_exp enclosed by [] and possibly by ()
define('HANDLE_LEGAL_REGS', '\\-_a-zA-Z0-9'); // '+' allowed in old ages, but not anymore for new handles
define('HANDLE_TAG_CHAR', '='); //not in: HANDLE_LEGAL_REGS or < or >
define('PASSWORD_LEGAL_REGS', HANDLE_LEGAL_REGS.'\\.\\?\\*\\+,;:!%');

function illegal_chars( $string, $punctuation=false )
{
   if ( $punctuation )
      $regs = PASSWORD_LEGAL_REGS;
   else
      $regs = 'a-zA-Z]['.HANDLE_LEGAL_REGS; //begins with a letter

   return !preg_match( "/^[$regs]+\$/", $string);
}

// NOTE: all chars allowed for handle must also be usable for a filename !!!
function is_legal_handle( $handle )
{
   $len = strlen($handle);
   if ( !preg_match( "/^[a-zA-Z][\\+".HANDLE_LEGAL_REGS."]+\$/", $handle) )
      return false;
   elseif ( $len == 0 || $len > 16 )
      return false;
   else
      return true;
}

function make_session_code()
{
   mt_srand((double)microtime()*1000000);
   $n = 41; //size of the MySQL 4.1 PASSWORD() result.
   $s = '';
   for ( $i=$n; $i>0; $i-=6 )
      $s.= sprintf("%06X",mt_rand(0,0xffffff));
   return substr($s, 0, $n);
}

function random_letter()
{
   $c = mt_rand(0,61);
   if ( $c < 10 )
      return chr( $c + ord('0'));
   elseif ( $c < 36 )
      return chr( $c - 10 + ord('a'));
   else
      return chr( $c - 36 + ord('A'));
}

function generate_random_password()
{
   $return = '';
   mt_srand((double)microtime()*1000000);
   for ( $i=0; $i<8; $i++ )
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
 * \return false if email is valid; otherwise an error('bad_mail_address') is thrown (if debugmsg given),
 *       or else an error-code is returned instead.
 */
function verify_invalid_email( $debugmsg, $email, $die_on_error=true )
{
   // Syntax email-address:
   // - RFC 5322 - 3.4.1 : see http://tools.ietf.org/html/rfc5322#section-3.4.1 ("local-part"),
   //   RFC 5322 - 3.2.3 : see http://tools.ietf.org/html/rfc5322#section-3.2.3 ("atom")
   // - see http://en.wikipedia.org/wiki/Email_address#Local_part
   //   normally also the following chars are allowed: ! # $ % & ' * + - / = ? ^ _ ` { | } ~
   //   though some systems/organizations do not support all of these.
   // - RFC 2822 - 3.4.1 : Addr-spec specification, see http: //www.faqs.org/rfcs/rfc2822
   static $atext = "[-+_a-z0-9]"; // allowed chars for DGS' email-localpart
   $regexp = "/^($atext+)(\\.$atext+)*@([-a-z0-9]+)(\\.[-a-z0-9]+)*(\\.[-a-z0-9]{2,63})\$/i";
   $res= preg_match($regexp, $email);
   if ( !$res ) // invalid email
   {
      if ( $die_on_error && is_string($debugmsg) )
         error('bad_mail_address', "verify_invalid_email.$debugmsg($email)"); // can fall-through if errors collected
      return 'bad_mail_address';
   }
   return false; // no-error
}//verify_invalid_email


// format-option for send_email()
define('EMAILFMT_SKIP_WORDWRAP', 0x01); // skipping word-wrapping

/**
 * \brief Sends email to one or multiple recipients.
 * \param $debugmsg if false, method will not die-on-error but return false instead;
 *        otherwise error('mail_failure') is called on mail-error
 * \param $email may be:
 *     - user@example.com
 *     - user.com, anotheruser@example.com
 *     - User <user@example.com>
 *     - User <user@example.com>, Another User <anotheruser@example.com>
 *   or an array of those.
 * \param $formatopts format-options for email: 0=none, EMAILFMT_SKIP_WORDWRAP
 * \param $subject default => FRIENDLY_LONG_NAME.' notification';
 * \param $headers default => 'From: '.EMAIL_FROM;
 * \param $params optional command-line paramenters for mail-command (may differ); none per default;
 *     always included are params from SENDMAIL_PARAMETERS from local config
 * \return true on success sending mail, false otherwise
 **/
function send_email( $debugmsg, $email, $formatopts, $text, $subject='', $headers='', $params='')
{
   // NOTE: mail-format of header and text see http://de3.php.net/manual/en/function.mail.php

   if ( !$subject )
      $subject = FRIENDLY_LONG_NAME.' notification';
   $subject= preg_replace("/[\\x01-\\x20]+/", ' ', $subject);

   // EOL for message-body should be LF
   $text = str_replace( array("\r\n", "\r"), "\n", $text );
   if ( !($formatopts & EMAILFMT_SKIP_WORDWRAP) )
      $text= wordwrap( $text, 70, "\n", 1);
   $text = trim($text) . "\n";

   $headers= trim($headers);
   if ( !$headers )
   {
      $headers = 'From: '.EMAIL_FROM;
      //if HTML in mail allowed:
      //$headers.= "\nMIME-Version: 1.0";
      //$headers.= "\nContent-type: text/html; charset=iso-8859-1";
   }

   // EOL of message-headers should be CRLF
   // NOTE: How to break the lines of an email ? CRLF -> http://cr.yp.to/docs/smtplf.html
   $rgx = "/[\r\n]+/";
   $eol = "\r\n"; // desired one for emails
   $headers = preg_replace( $rgx, $eol, trim($headers)); //.$eol;

   $params= trim(SENDMAIL_PARAMETERS . ' ' . $params);
   if ( $params )
      $params = preg_replace( $rgx, $eol, trim($params)); //.$eol;

   if ( is_array($email) )
      $email = trim( implode( ',', $email) );

   if ( function_exists('mail') )
   {
      $begin_time = time();
      $res = @mail( $email, $subject, $text, $headers, $params);
      if ( !$res )
         error_log( sprintf("SEND_EMAIL($debugmsg,$email): subject [$subject] -> time %s, RESULT [%s]", time() - $begin_time, $res ) );
   }
   else
      $res = false;

   if ( is_string($debugmsg) && !$res )
      error('mail_failure', "send_email.$debugmsg($email,[$subject])");
   return $res;
} //send_email

/**
 * $text and $subject must NOT be escaped by mysql_real_escape_string()
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
            , $from_id=0, $type=MSGTYPE_NORMAL, $gid=0
            , $prev_mid=0, $prev_type='', $prev_folder=FOLDER_NONE
            )
{
   global $NOW;

   if ( is_string($debugmsg) )
      $debugmsg.= '.send_message';

   $text = mysql_addslashes(trim($text));
   $subject = mysql_addslashes(trim($subject));
   if ( (string)$subject == '' )
      $subject = T_('(no subject)');

   if ( !isset($type) || !is_string($type) || !$type )
      $type = MSGTYPE_NORMAL;
   if ( !isset($gid) || !is_numeric($gid) || $gid<0 )
      $gid = 0;
   if ( !isset($from_id) || !is_numeric($from_id) || $from_id <= GUESTS_ID_MAX ) //exclude guest
      $from_id = 0; //i.e. server message
   if ( !isset($prev_mid) || !is_numeric($prev_mid) || $prev_mid < 0 )
      $prev_mid = 0;

   $to_myself = false;
   $receivers = array();
   foreach ( array( 'ID' => &$to_ids, 'Handle' => &$to_handles ) as $field => $var )
   {
      if ( is_null($var) || (string)$var == '' )
         continue;
      if ( !is_array($var) )
         $var = preg_split('/[\s,]+/', $var);
      $varcnt = count($var);
      if ( $varcnt <= 0 )
         continue;

      $var = implode("','", ( $field == 'ID' ? $var : array_map('mysql_addslashes', $var) ));
      if ( !$var )
         continue;

      // make receivers unique, exclude guests
      $query = "SELECT ID,Notify,SendEmail FROM Players WHERE $field IN ('$var') LIMIT $varcnt";
      $result = db_query( "$debugmsg.get$field($var)", $query);
      while ( $row = mysql_fetch_assoc($result) )
      {
         $uid = $row['ID'];
         if ( $from_id > 0 && $uid == $from_id )
            $to_myself = true;
         elseif ( $uid > GUESTS_ID_MAX ) //exclude guest
            $receivers[$uid] = $row;
      }
      mysql_free_result($result);
   }
   $reccnt = count($receivers);
   if ( !$to_myself && $reccnt <= 0 )
      error('receiver_not_found', "$debugmsg.rec0($from_id,$subject)");

   // not supported: sending a bulk-message to myself and other in the same pack
   if ( $to_myself && $reccnt > 0 )
      error('bulkmessage_self', "$debugmsg.rec2($from_id,$subject)");

   // determine message-thread info
   $thread = 0;
   $thread_level = 0;
   if ( $prev_mid > 0 )
   {
      $prev_msgrow = mysql_single_fetch( "$debugmsg.find_prev($from_id,$prev_mid)",
         "SELECT Thread, Level FROM Messages WHERE ID=$prev_mid LIMIT 1" );
      if ( is_array($prev_msgrow) )
      {
         $thread = $prev_msgrow['Thread'];
         $thread_level = $prev_msgrow['Level'] + 1;
      }
   }

   $msg_flags = ($from_id > 0 && $reccnt > 1) ? MSGFLAG_BULK : 0;


   ta_begin();
   {//HOT-SECTION to save message-data
      $query= "INSERT INTO Messages SET Time=FROM_UNIXTIME($NOW)"
             .", Type='$type', Flags='$msg_flags'"
             .", Thread='$thread', Level='$thread_level'"
             .", ReplyTo=$prev_mid, Game_ID=$gid"
             .", Subject='$subject', Text='$text'" ;
      db_query( "$debugmsg.message", $query);
      if ( mysql_affected_rows() != 1 )
         error('mysql_insert_message', "$debugmsg.message");

      $mid = mysql_insert_id();
      ksort($receivers);

      $query= array();
      $receivers_folder_new = array();
      if ( $from_id > 0 ) //exclude system messages (no sender)
      {
         if ( $to_myself )
         {
            $query[]= "$mid,$from_id,'M','N',".FOLDER_NEW;
            $receivers_folder_new[] = $from_id;
         }
         else
            $query[]= "$mid,$from_id,'Y','N',".FOLDER_SENT;
      }

      if ( $from_id > 0 && $thread == 0 )
      {
         db_query( "$debugmsg.upd_thread($mid,$from_id)",
            "UPDATE Messages SET Thread='$mid', Level=0 WHERE ID='$mid' LIMIT 1" );
      }

      $need_reply_val = ( $from_id > 0 && $type == MSGTYPE_INVITATION ) ? 'M' : 'N';
      foreach ( $receivers as $uid => $row ) //exclude to myself
      {
         if ( $from_id > 0 )
            $query[]= "$mid,$uid,'N','$need_reply_val',".FOLDER_NEW;
         else //system messages
            $query[]= "$mid,$uid,'S','N',".FOLDER_NEW;
         $receivers_folder_new[] = $uid;
      }

      $cnt= count($query);
      if ( $cnt > 0 )
      {
         $query= "INSERT INTO MessageCorrespondents"
                ." (mid,uid,Sender,Replied,Folder_nr) VALUES"
                .' ('.implode('),(', $query).")";
         db_query( "$debugmsg.correspondent", $query );
         if ( mysql_affected_rows() != $cnt )
            error('mysql_insert_message', "$debugmsg.correspondent");
      }

      // update receivers new-message counter
      if ( $cnt_fnew = count($receivers_folder_new) )
      {
         $ids = implode( ',', $receivers_folder_new );
         db_query( "$debugmsg.count_msg_new([$ids])",
            "UPDATE Players SET CountMsgNew=CountMsgNew+1 WHERE ID IN ($ids) AND CountMsgNew>=0 LIMIT $cnt_fnew" );
      }

      //records the last message of the invitation/dispute sequence
      //the type of the previous messages will be changed to 'DISPUTED'
      if ( $gid > 0 && $type == MSGTYPE_INVITATION )
      {
         db_query( "$debugmsg.game_message($gid)",
            "UPDATE Games SET mid='$mid' WHERE ID='$gid' LIMIT 1" );
      }

      if ( $from_id > 0 && $prev_mid > 0 ) //is this an answer?
      {
         $query = "UPDATE MessageCorrespondents SET Replied='Y'";
         if ( $prev_folder > FOLDER_ALL_RECEIVED )
            $query .= ", Folder_nr=$prev_folder";
         elseif ( $prev_folder == MOVEMSG_REPLY_TO_MAIN_FOLDER )
            $query .= ", Folder_nr=IF(Folder_nr=".FOLDER_REPLY.",".FOLDER_MAIN.",Folder_nr)";
         $query.= " WHERE mid=$prev_mid AND uid=$from_id AND Sender!='Y' LIMIT 1";
         db_query( "$debugmsg.reply_correspondent", $query );

         if ( $prev_type )
         {
            db_query( "$debugmsg.reply_message",
               "UPDATE Messages SET Type='$prev_type' WHERE ID=$prev_mid LIMIT 1" );
         }
      }

      if ( $notify ) //about message!
      {
         $ids= array();
         foreach ( $receivers as $uid => $row )
         {
            if ( $row['Notify'] == 'NONE' && ( strpos($row['SendEmail'], 'ON') !== false ) )
               $ids[]= $uid; // optimize: notify only eligible
         }
         if ( count($ids) > 0 )
            notify( $debugmsg, $ids, '', NOTIFYFLAG_NEW_MSG );
      }

      // clear caches for sender & receivers
      $clear_uids = array_keys($receivers);
      if ( $from_id > GUESTS_ID_MAX )
         $clear_uids[] = $from_id;
      clear_cache_quick_status( $clear_uids, QST_CACHE_MSG );
      delete_cache_message_list( $debugmsg, $clear_uids );
   }
   ta_end();

   return $mid; //>0: no error
} //send_message


// see MessageListBuilder.load_cache_message_list()
// NOTE: can't put in 'include/message_functions.php' because needed in send_message() and circular dependency
function delete_cache_message_list( $dbgmsg, $uids )
{
   if ( !is_array($uids) )
      $uids = array( $uids );
   foreach ( $uids as $uid )
      DgsCache::delete( $dbgmsg, CACHE_GRP_MSGLIST, "Messages.$uid" );
}


define('NOTIFYFLAG_NEW_MSG', 0x01 ); // new-message awaiting for mail-notifications

/*!
 * \brief Sets Players.Notify-field (something to notify) for given uids.
 *        Starts the notification process for thoses from $ids having $type set.
 * \param $ids id(s), or array of id(s)
 * \param $type is one element of the SET of SendEmail ('MOVE','MESSAGE' ...), that need to be set in order to notify
 * \param $nfy_flags if >0 set additional flags in Players.NotifyFlags; NOTIFYFLAG_...
 * \return ''=no-error, else error-string, e.g. 'no IDs'
 */
function notify( $debugmsg, $ids, $type='', $nfy_flags=0 )
{
   if ( !is_array($ids) )
      $ids= array( $ids);
   $query = array();
   foreach ( $ids as $cnt )
      $query = array_merge( $query, explode(',', $cnt));
   $ids = array();
   foreach ( $query as $cnt )
   {
      if ( ($cnt=(int)$cnt) > GUESTS_ID_MAX ) //exclude guest
         $ids[$cnt] = $cnt; //unique
   }

   $cnt= count($ids);
   if ( $cnt <= 0 )
      return 'no IDs';

   $ids= implode(',', $ids);
   db_query( "$debugmsg.notify",
      "UPDATE Players SET Notify='NEXT'"
      .( $nfy_flags > 0 ? sprintf( ', NotifyFlags=NotifyFlags | %s ', (int)$nfy_flags ) : '' )
      ." WHERE ID IN ($ids) AND Notify='NONE'"
      ." AND FIND_IN_SET('ON',SendEmail)"
      ." AND (UserFlags & ".USERFLAG_NFY_BUT_NO_OR_INVALID_EMAIL.") = 0"
      .($type ? " AND FIND_IN_SET('$type',SendEmail)" : '')
      ." LIMIT $cnt" );

   return ''; //no error
} //notify


function safe_setcookie( $name, $value='', $rel_expire=-3600, $http_only=false ) //-SECS_PER_HOUR
//should be: ($name, $value, $expire, $path, $domain, $secure)
{
   global $NOW;

   $name= COOKIE_PREFIX.$name;

   //remove duplicated cookies sometime occuring with some browsers
   if ( $tmp= @$_SERVER['HTTP_COOKIE'] )
      $n= preg_match_all(';'.$name.'[\\x01-\\x20]*=;i', $tmp, $dummy);
   else
      $n= 0;

   while ( $n-- > 1 )
      setcookie( $name, '', $NOW-SECS_PER_HOUR, SUB_PATH);

   if ( $http_only )
      setcookie( $name, $value, $NOW+$rel_expire, SUB_PATH, '', /*secure*/false, (bool)$http_only );
   else
      setcookie( $name, $value, $NOW+$rel_expire, SUB_PATH );
   //for current session:
   $_COOKIE[$name] = $value; //??? add magic_quotes_gpc like slashes?
} //safe_setcookie

function set_login_cookie($handl, $code, $delete=false)
{
   if ( $delete || !$handl || !$code)
   {
      safe_setcookie('handle');
      safe_setcookie('sessioncode');
   }
   else
   {
      safe_setcookie('handle', $handl, 5 * SESSION_DURATION);
      safe_setcookie('sessioncode', $code, SESSION_DURATION, /*http_only*/true );
   }
} //set_login_cookie

function set_cookie_prefs(&$player_row, $delete=false)
{
   $uid = (int)@$player_row['ID'];
   //assert('$uid>0');
   if ( $uid <= 0 ) return;

   global $cookie_prefs;

   if ( $delete )
      safe_setcookie("prefs$uid");
   else
      safe_setcookie("prefs$uid", serialize($cookie_prefs), SECS_PER_HOUR*12*61 * 12*5); //5 years
} //set_cookie_prefs

function get_cookie_prefs(&$player_row)
{
   $uid = (int)@$player_row['ID'];
   //assert('$uid>0');
   if ( $uid <= 0 ) return;

   global $cookie_prefs, $cookie_pref_rows;

   $cookie_prefs = unserialize( safe_getcookie("prefs$uid") );
   if ( !is_array( $cookie_prefs ) )
      $cookie_prefs = array();

   foreach ( $cookie_prefs as $key => $value )
   {
      if ( in_array($key, $cookie_pref_rows) )
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
   if ( $uid <= 0 ) return -3;

   $cookie = "status$uid";
   $level = (int)@$player_row['admin_level'];
   if ( !$mask )
   {
      $status = $level & (int)safe_getcookie($cookie);
      $player_row['admin_status'] = $status;
      return -2;
   }
   if ( ($level & $mask) == 0 )
      return -1; //not granted

   $status = $level & (int)@$player_row['admin_status'];
   $old = $status;
   switch ( (string)strtolower($cmd) )
   {
      case 'y': case '+': $status |= $mask; break; //set
      case 'n': case '-': $status &=~$mask; break; //unset
      case 'x': case '*': $status ^= $mask; break; //toggle
      //default: break; //just return status
   }
   if ( $old != $status )
   {
      if ( $status )
         safe_setcookie( $cookie, $status, SECS_PER_HOUR);
      else
         safe_setcookie( $cookie);
      $player_row['admin_status'] = $status;
   }
   if ( ($status & $mask) == $mask )
      return 1; //active
   return 0; //granted but inactive
} //switch_admin_status


function add_line_breaks( $str)
{
   $str = trim($str);

   // Strip out carriage returns
   $str=preg_replace('/[\\x01-\\x09\\x0B-\\x20]*\\x0A/','<BR>', $str);

   // Handle collapsed vertical white spaces
   for ( $i=0; $i<2; $i++)
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
global $html_code_closed; //PHP5
$html_code_closed['cell'] = '|note|b|i|u|strong|em|tt|strike|color|bgcolor|';
$html_code_closed['line'] = '|home_|home|a'.$html_code_closed['cell'];
$html_code_closed['msg']  = '|center|ul|ol|font|pre|code|quote|igoban'.$html_code_closed['line'];
$html_code_closed['game'] = '|h|hidden|c|comment'.$html_code_closed['msg'];
$html_code_closed['faq']  = $html_code_closed['msg']; //minimum closed check

// ** no '|' at ends:
global $html_code; //PHP5
$html_code['cell'] = 'note|b|i|u|strong|em|tt|strike|color|bgcolor';
$html_code['line'] = 'home|a|feature|ticket|'.$html_code['cell'];
$html_code['msg']  = 'br|/br|p|/p|li|hr'.$html_code_closed['msg'] .'goban|mailto|_?https?|_?news|_?ftp|game_?|ticket|tourney_?|survey_?|user_?|send_?|image|feature';
$html_code['game'] = 'br|/br|p|/p|li|hr'.$html_code_closed['game'].'goban|mailto|_?https?|_?news|_?ftp|game_?|ticket|tourney_?|survey_?|user_?|send_?|image|feature';
$html_code['faq']  = '/?\w+'; //all non-empty words


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
      elseif ( $c=='>' )
      {
         $head.= substr($trail,0,$i);
         $trail = substr($trail,$i+1);
         break;
      }
      elseif ( $quote )
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
      if ( preg_match( "%($quote)%i", preg_replace( "/[\\x01-\\x1f]+/", '', $head)) )
         $bad = 2;
   }
   if ( $bad )
   {
      $head = str_replace(ALLOWED_QUOT, '"', $head);
      $head = str_replace(ALLOWED_APOS, "'", $head);
   }
   return $head;
}//parse_atbs_safe


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
global $parse_mark_regex; //PHP5
$parse_mark_regex = ''; //global because parse_tags_safe() is recursive -> FIXME better to refactor into class-instance-attribute
define('PARSE_MARK_TERM',
      ALLOWED_LT.'span class=MarkTerm'.ALLOWED_GT.'\\1'.ALLOWED_LT.'/span'.ALLOWED_GT);
define('PARSE_MARK_TAGTERM',
      ALLOWED_LT.'span class=MarkTagTerm'.ALLOWED_GT.'&lt;\\1&gt;'.ALLOWED_LT.'/span'.ALLOWED_GT);
function parse_tags_safe( &$trail, &$bad, &$html_code, &$html_code_closed, $stop)
{
   if ( !$trail )
      return '';

   global $parse_mark_regex;
   $before = '';
   //$stop = preg_quote($stop, '%');
   $reg = ( $html_code ) ? ( $stop ? $stop.'|' : '' ) . $html_code : $stop;
   if ( !$reg )
      return '';
   //enclosed by '%' because $html_code may contain '/'
   //FIXME(?) ... and $html_code can not contain '%' too ?
   $reg = "%^(.*?)<($reg)\\b(.*)$%is";

   while ( preg_match($reg, $trail, $matches) )
   {
      $marks = $matches[1] ;
      if ( $parse_mark_regex && PARSE_MARK_TERM && $marks )
         $marks = preg_replace( $parse_mark_regex, PARSE_MARK_TERM, $marks);
      $before.= $marks;
      $tag = strtolower($matches[2]) ; //Warning: same case as $html_code
      if ( $tag == '/br' ) $tag = 'br' ; //historically used in end game messages.
      $endtag = ( substr($tag,-1,1) == '_' ) ? substr($tag,0,-1) : $tag;
      $trail = $matches[3] ;
      unset($matches);

      $head = $tag . parse_atbs_safe( $trail, $bad) ;
      $marks = '';
      if ( $parse_mark_regex && PARSE_MARK_TAGTERM && $head )
      {
         if ( preg_match_all( $parse_mark_regex, $head, $tmp) )
         {
            $marks = textarea_safe( implode('|', $tmp[1]), 'iso-8859-1'); //LANG_DEF_CHARSET);
            $marks = str_replace( '\\1', $marks, PARSE_MARK_TAGTERM);
         }
      }
      if ( $bad)
         return $before .$marks .'<'. $head .'>' ;

      $head = preg_replace('/[\\x01-\\x20]+/', ' ', $head);
      if ( in_array($tag, array(
            //as a first set/choice of <ul>-like tags
            'quote','code','pre','center',
            'dl','/dt','/dd','ul','ol','/li',
         )) )
      { //remove all the following newlines (to avoid inserted <br>)
         $trail= preg_replace( "/^[\\r\\n]+/", '', $trail);
      }
      elseif ( in_array($tag, array(
            //as a first set/choice of </ul>-like tags
            '/quote','/code','/pre','/center',
            '/dl','/ul','/ol','/note','/div',
         )) )
      { //remove the first following newline
         $trail= preg_replace( "/^(\\r\\n|\\r|\\n)/", '', $trail);
      }

      if ( (string)$stop == $tag )
         return $before .ALLOWED_LT. $head .ALLOWED_GT .$marks; //mark after

      $before.= $marks; //mark before
      $to_be_closed = is_numeric(strpos($html_code_closed,'|'.$tag.'|')) ;
      if ( $tag == 'code' )
      {
         // does not allow inside HTML
         $tmp= '';
         $inside = parse_tags_safe( $trail, $bad, $tmp, $tmp, '/'.$tag);
         if ( $bad)
            return $before .'<'. $head .'>'. $inside ;
         $inside = str_replace('&', '&amp;', $inside);
         //TODO: fix possible corrupted marks... to be reviewed
         //TODO: can't use nested <code>-tags
         $inside = preg_replace(
            '%(class=Mark[^`]*'.ALLOWED_GT.')&amp;(lt;[^&]*)&amp;(gt;)%',
            '\\1&\\2&\\3',
            $inside);
      }
      elseif ( $tag == 'tt' )
      {
         // TT is mainly designed to be used when $some_html=='cell'
         // does not allow inside HTML and remove line breaks
         $tmp= '';
         $inside = parse_tags_safe( $trail, $bad, $tmp, $tmp, '/'.$tag);
         if ( $bad)
            return $before .'<'. $head .'>'. $inside ;
         //$inside = str_replace('&', '&amp;', $inside);
         $inside = preg_replace('/[\\x09\\x20]/', '&nbsp;', $inside);
         $inside = preg_replace('/[\\x01-\\x1F]*/', '', $inside);
         //TODO: fix possible corrupted marks... to be reviewed
         $inside = preg_replace('/&nbsp;class=Mark/', ' class=Mark', $inside);
      }
      elseif ( $to_be_closed )
      {
         $inside = parse_tags_safe( $trail, $bad, $html_code, $html_code_closed, '/'.$endtag);
         if ( $bad)
            return $before .'<'. $head .'>'. $inside ;
      }
      else
      {
         $inside = '' ;
      }

      $before.= ALLOWED_LT. $head .ALLOWED_GT. $inside ;
   }
   if ( $stop )
      $bad = 1;
   return $before ;
}//parse_tags_safe

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
   if ( !$some_html )
      $str = '';
   else
      $str = parse_tags_safe( $msg, $bad,
                  $html_code[$some_html],
                  $html_code_closed[$some_html],
                  '') ;
   if ( $parse_mark_regex && PARSE_MARK_TERM && $msg )
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




// see user_reference()-function for description
define('REF_LINK', 0x1);
define('REF_LINK_ALLOWED', 0x2);
define('REF_LINK_BLANK', 0x4);

//Note: some of those check for the '`' i.e. the first char of ALLOWED_* vars
global $html_safe_preg; //PHP5
$html_safe_preg = array(

//<note>...</note> =>removed from entry, seen only by editors
 '%'.ALLOWED_LT."note([^`\\n\\t]*)".ALLOWED_GT.".*?"
    .ALLOWED_LT."/note([^`\\n\\t]*)".ALLOWED_GT.'%is'
  => '', // =>removed from entry

//<mailto:...>
 '/'.ALLOWED_LT."(mailto:)([^`\\n\\s]+)".ALLOWED_GT.'/is'
  => ALLOWED_LT."a href=".ALLOWED_QUOT."\\1\\2".ALLOWED_QUOT.ALLOWED_GT."\\2".ALLOWED_LT."/a".ALLOWED_GT,

//<http://...>, <https://...>, <news://...>, <ftp://...>, <_(http|https|news|ftp)://...>
 '%'.ALLOWED_LT."(_)?((http:|https:|news:|ftp:)//[^`'\\r\\n\\s]+?)(?:\s+\|([^'\\r\\n]+?))?".ALLOWED_GT.'%ise'
  => '"'.ALLOWED_LT.'a class='.ALLOWED_QUOT.'linkmarkup'.ALLOWED_QUOT
         . ' href='.ALLOWED_QUOT."\\2".ALLOWED_QUOT
         . "\".('\\1'?' target=".ALLOWED_QUOT.'_blank'.ALLOWED_QUOT."':'').\""
         . ALLOWED_GT.'"'
         . ".( strlen(trim('\\4')) ? '\\4' : '\\2' )."
         . '"'.ALLOWED_LT."/a".ALLOWED_GT.'"',

//<game gid[,move]> =>show game
 '/'.ALLOWED_LT."game(_)? +([0-9]+)( *, *(".MOVE_SETUP."|[0-9]+))? *".ALLOWED_GT.'/ise'
  => "game_reference(('\\1'?".REF_LINK_BLANK.":0)+".REF_LINK_ALLOWED.",1,'',\\2,'\\4')",

//<ticket issue_id> => link to issue
 '/'.ALLOWED_LT."ticket +([\\-\\w]+) *".ALLOWED_GT.'/ise'
  => ( TICKET_REF ? "ticket_reference(".REF_LINK_ALLOWED.",1,'','\\1')" : "'\\0'" ),

//<tourney tid> => show tournament
 '/'.ALLOWED_LT."tourney(_)? +([0-9]+) *".ALLOWED_GT.'/ise'
  => "tournament_reference(('\\1'?".REF_LINK_BLANK.":0)+".REF_LINK_ALLOWED.",1,'',\\2)",

//<feature id> => link to feature
 '/'.ALLOWED_LT."feature +([0-9]+) *".ALLOWED_GT.'/ise'
  => "feature_reference(".REF_LINK_ALLOWED.",1,'',\\1)",

//<survey sid> => show survey
 '/'.ALLOWED_LT."survey(_)? +([0-9]+) *".ALLOWED_GT.'/ise'
  => "survey_reference(('\\1'?".REF_LINK_BLANK.":0)+".REF_LINK_ALLOWED.",1,'',\\2)",

//<user uid> or <user =uhandle> =>show user info
//<send uid> or <send =uhandle> =>send a message to user
 '/'.ALLOWED_LT."(user|send)(_)? +(".HANDLE_TAG_CHAR."?[+".HANDLE_LEGAL_REGS."]+) *".ALLOWED_GT.'/ise'
  => "\\1_reference(('\\2'?".REF_LINK_BLANK.":0)+".REF_LINK_ALLOWED.",1,'','\\3')",
//adding '+' to HANDLE_LEGAL_REGS because of old DGS users having it in their Handle
//because of HANDLE_LEGAL_REGS, no need of ...,str_replace('\"','"','\\3')...

//<color col>...</color> =>translated to <font color="col">...</font>
 '%'.ALLOWED_LT."color +([#0-9a-zA-Z]+) *".ALLOWED_GT.'%is'
  => ALLOWED_LT."font color=".ALLOWED_QUOT."\\1".ALLOWED_QUOT.ALLOWED_GT,
 '%'.ALLOWED_LT."/color *".ALLOWED_GT.'%is'
  => ALLOWED_LT."/font".ALLOWED_GT,

//<bgcolor col>...</bgcolor> =>translated to <span style="background-color: col">...</span>
 '%'.ALLOWED_LT."bgcolor +(#?\\w+) *".ALLOWED_GT.'%is'
  => ALLOWED_LT."span style=".ALLOWED_QUOT."background-color: \\1".ALLOWED_QUOT.ALLOWED_GT,
 '%'.ALLOWED_LT."/bgcolor *".ALLOWED_GT.'%is'
  => ALLOWED_LT."/span".ALLOWED_GT,

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

//<home page>...</home>, <home_ ...> =>translated to <a href="{HOSTBASE}$page">...</a>
 '%'.ALLOWED_LT."home(_)?[\\n\\s]+((\.?[^\.\\\\:\"`\\n\\s])+)".ALLOWED_GT.'%ise'
  => '"'.ALLOWED_LT."a href=".ALLOWED_QUOT.HOSTBASE."\\2".ALLOWED_QUOT
      ."\".('\\1'?' target=".ALLOWED_QUOT.'_blank'.ALLOWED_QUOT."':'').\""
      .ALLOWED_GT.'"',
 '%'.ALLOWED_LT."/home *".ALLOWED_GT.'%is'
  => ALLOWED_LT."/a".ALLOWED_GT,

//<image pict> =>translated to <img src="{HOSTBASE}images/$pict">
//<image board/pict> =>translated to <img src="{HOSTBASE}17/$pict">
 '%'.ALLOWED_LT."image[\\n\\s]+(board/)?([\\.\\-/a-zA-Z0-9_]+)".ALLOWED_GT.'%ise'
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

//reverse (=escape) bad skipped (faulty) tags; keep them alphabetic here
 '%'.ALLOWED_LT."(/?_?(bgcolor|code|color|feature|ftp|game|home|https?|image|mailto|news|note|quote|send|survey|ticket|tourney|user).*?)"
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
 *  'game': format public comment tags (<c> + <comment>): <c>..</c> -> <span class=GameTagC>..</span>,
 *          remove hidden comments
 *  'gameh': format public (like 'game' but keep hidden comments),
 *           format hidden comment tags (<h> + <hidden>): <h>..</h> -> <span class=GameTagH>..</span>
 * $mark_terms: see parse_html_safe().
 **/
function make_html_safe( $msg, $some_html=false, $mark_terms='')
{
   if ( $some_html )
   {
      // make sure the <, > replacements: ALLOWED_LT, ALLOWED_GT are removed from the string
      $msg= reverse_allowed( $msg);

      switch ( (string)$some_html )
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
      if ( $some_html == 'game' )
      {
         $tmp = "<\\1>"; // "<\\1>"=show tag, ""=hide tag, "\\0"=html error
         if ( $gameh ) // show hidden sgf comments
         {
            $msg = preg_replace('%'.ALLOWED_LT.'(h(idden)?) *'.ALLOWED_GT.'%i',
                     ALLOWED_LT."span class=GameTagH".ALLOWED_GT.$tmp, $msg);
            $msg = preg_replace('%'.ALLOWED_LT.'(/h(idden)?) *'.ALLOWED_GT.'%i',
                     $tmp.ALLOWED_LT."/span".ALLOWED_GT, $msg);
         }
         else // hide hidden sgf comments
         {
            $msg = trim(preg_replace(
                  '%'.ALLOWED_LT.'h(idden)? *'.ALLOWED_GT.'(.*?)'.ALLOWED_LT.'/h(idden)? *'.ALLOWED_GT.'%is',
                  '', $msg));
         }


         $msg = preg_replace('%'.ALLOWED_LT.'(c(omment)?) *'.ALLOWED_GT.'%i',
                  ALLOWED_LT.'span class=GameTagC'.ALLOWED_GT.$tmp, $msg);
         $msg = preg_replace('%'.ALLOWED_LT.'(/c(omment)?) *'.ALLOWED_GT.'%i',
                  $tmp.ALLOWED_LT.'/span'.ALLOWED_GT, $msg);

         $some_html = 'msg';
      }

      global $html_safe_preg;
      $msg= preg_replace( array_keys($html_safe_preg), $html_safe_preg, $msg);

   }
   elseif ( $mark_terms )
   {
      $msg = parse_html_safe( $msg, '', $mark_terms) ;
   }

   // Filter out HTML code

   $msg = preg_replace('/&(?!(#[0-9]+|[A-Z][0-9A-Z]*);)/is', '&amp;', $msg);
   $msg = basic_safe( $msg);

   if ( $some_html || $mark_terms )
   {
      // change back to <, > from ALLOWED_LT, ALLOWED_GT
      $msg= reverse_allowed( $msg);

      if ( $some_html && $some_html != 'cell' && $some_html != 'line' )
         $msg = add_line_breaks($msg);
   }

   return $msg;
}//make_html_safe

// replace "<move num|S>" with "<game $gid,N>"
function replace_move_tag( $msg, $gid )
{
   return preg_replace("/<move\\s+(\\d+|S)>/s", "<game $gid,\\1>", $msg);
}

function textarea_safe( $msg, $charenc=false)
{
   global $encoding_used;
   if ( !$charenc)
      $charenc = $encoding_used;
   //else 'iso-8859-1' LANG_DEF_CHARSET

   // NOTE: No: $msg = @htmlentities($msg, ENT_QUOTES, $charenc); //Too much entities for not iso-8859-1 languages
   $msg = @htmlspecialchars($msg, ENT_QUOTES, $charenc);

   // NOTE: with PHP 5.3 htmlspecialchars(.., $double_enc=false) has double_enc-arg which is true per default :-(
   //       Till then we have to decode the &amp; of already encoded original &#xx; entities.
   $msg = preg_replace( '/(&amp;)(?=#[0-9]+;)/is', '&', $msg);
   return $msg;
}

// removes hidden comment tags and included text
function remove_hidden_game_tags( $msg )
{
   return trim(preg_replace("'<h(idden)? *>(.*?)</h(idden)? *>'is", "", $msg));
}

// keeps and trims the parts readable by an observer, removing private comments (the text outside of <c> and <h> tags)
// param $includeTags if false also removes the <c>/<h>-tags itself keeping only the surrounded text
//
// NOTE: $includeTags==false MUST NOT be used to format HTML, because then style-applying is impossible with the tags gone
function game_tag_filter( $msg, $includeTags=true )
{
   if ( $includeTags )
   {
      $idx_c = 1;
      $idx_h = 5;
   }
   else
   {
      $idx_c = 3;
      $idx_h = 7;
   }

   $nr_matches = preg_match_all(
         "%(<c(omment)? *>(.*?)</c(omment)? *>)".
         "|(<h(idden)? *>(.*?)</h(idden)? *>)%is"
         , $msg, $matches );
   $str = '';
   for ($i=0; $i<$nr_matches; $i++)
   {
      $msg = trim($matches[$idx_c][$i]);
      if ( (string)$msg == '' )
         $msg = trim($matches[$idx_h][$i]);
      if ( (string)$msg != '' )
         $str .= "\n" . $msg ;
   }
   return trim($str);
}


function yesno( $yes)
{
   return ( $yes && strtolower(substr($yes,0,1))!='n' ) ? T_('Yes') : T_('No');
}

// \param $quick true = used by quick-suite for special quick-suite-format; 0 = used for SGF-building
// \note $verbose=false + $keep_english=true + $quick=0(!) used by SGF-download (except for Jigo) !!
function score2text( $score, $game_flags, $verbose, $keep_english=false, $quick=false )
{
   if ( $quick ) $verbose = false;
   $T_= ( $keep_english ? 'fnop' : 'T_' );

   if ( is_null($score) || !isset($score) || abs($score) > SCORE_FORFEIT )
      return ($quick) ? "" : "?";

   if ( $score == 0 )
   {
      if ( $game_flags & GAMEFLAGS_NO_RESULT )
      {
         return ($quick) ? 'VOID' : ( $quick === 0 ? 'Void' : ( $keep_english
            ? 'No-Result'
            : ( $verbose ? $T_('Game ends with No-Result') : $T_('No-Result#score') )));
      }
      else
         return ($quick || $quick === 0 ) ? '0' : ( $keep_english ? 'Draw' : $T_('Jigo') );
   }

   if ( $verbose )
      $color = ( $score > 0 ) ? $T_('White') : $T_('Black');
   else if ( $keep_english || $quick || $quick === 0 )
      $color = ( $score > 0 ) ? 'W' : 'B';
   else
      $color = ( $score > 0 ) ? $T_('W#white_short') : $T_('B#black_short');

   if ( abs($score) == SCORE_RESIGN )
   {
      return ( $verbose
         ? sprintf( $T_("%s wins by resignation"), $color)
         : $color . '+' . ($quick ? "R" : ($keep_english || $quick === 0 ? 'Resign' : $T_('Resign#score')) ) );
   }
   elseif ( abs($score) == SCORE_TIME )
   {
      return ( $verbose
         ? sprintf( $T_("%s wins by timeout"), $color)
         : $color . '+' . ($quick ? "T" : ($keep_english || $quick === 0 ? 'Time' : $T_('Time#score')) ) );
   }
   elseif ( abs($score) == SCORE_FORFEIT )
   {
      return ( $verbose
         ? sprintf( $T_("%s wins by forfeit"), $color)
         : $color . '+' . ($quick ? "F" : ($keep_english || $quick === 0 ? 'Forfeit' : $T_('Forfeit#score')) ) );
   }
   else
      return ( $verbose ? sprintf( $T_("%s wins by %.1f"), $color, abs($score)) : $color . '+' . abs($score) );
}//score2text

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
   foreach ( array( 5, 10, 15, 20, 25, 30, 40, 50, 75, 100 ) as $k)
   {
      if ( $k <= $rows_limit )
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
//
// NOTE: (if needed): next step could be to handle the '#' part of the url:
//    make_url('test.php?a=1#id', array('b' => 'foo'), false)  gives  'test.php?a=1&b=foo#id'
function make_url( $url, $args, $end_sep=false)
{
   $url= clean_url($url);
   $sep= ( is_numeric( strpos( $url, '?')) ? URI_AMP : '?' );
   $args= build_url( $args, $end_sep);
   if ( $args || $end_sep )
      return $url . $sep . $args;
   return $url;
} //make_url

function build_url( $args, $end_sep=false, $sep=URI_AMP )
{
   if ( !is_array( $args) )
      return '';
   $arr_str = array();
   foreach ( $args as $key => $value )
   {
      // some clients need ''<>0, so don't use empty(val)
      if ( (string)$value == '' || !is_string($key) || empty($key) )
         continue;
      if ( !is_array($value) )
         $arr_str[] = $key . '=' . urlencode($value);
      else
      {
         $key.= '%5b%5d='; //encoded []
         foreach ( $value as $val )
         {
            // some clients need ''<>0, so don't use empty(val)
            if ( (string)$val != '' )
               $arr_str[] = $key . urlencode($val);
         }
      }
   }
   if ( count($arr_str) )
      return implode( $sep, $arr_str) . ( $end_sep ? $sep : '' );
   return '';
}//build_url

function build_hidden( $args)
{
   static $hidden_fmt = "\n<input type=\"hidden\" name=\"%s\" value=%s>";
   if ( !is_array($args) )
      return '';

   $arr_str = array();
   foreach ( $args as $key => $value )
   {
      if ( (string)$value == '' || !is_string($key) || empty($key) )
         continue;
      if ( !is_array($value) )
         $arr_str[] = sprintf( $hidden_fmt, $key, attb_quote($value) );
      else
      {
         $key .= '[]'; //%5b%5d encoded []
         foreach ( $value as $val )
         {
            if ( (string)$val != '' )
               $arr_str[] = sprintf( $hidden_fmt, $key, attb_quote($val) );
         }
      }
   }
   return implode('', $arr_str);
}//build_hidden

//see also the PHP parse_str() and parse_url()
//this one use URI_AMP by default to be the make_url() mirror
function split_url($url, &$page, &$args, $sep='')
{
   if ( !$sep ) $sep = URI_AMP;
   $url = preg_split( "/([?#]|$sep)/", $url );
   list( , $page ) = each( $url );
   $args = array();
   while ( list( , $query ) = each( $url ) )
   {
      if ( !empty( $query ) )
      {
         @list( $var, $value ) = explode( '=', $query );
         if ( (string)@$value != '' ) // can be '0' (which is <> unset/false)
         {
            $var = urldecode($var);
            if ( substr($var,-2) != '[]' ) //'%5B%5D'
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
   if ( !$sep ) $sep = URI_AMP;
   $l = -strlen($sep);
   do
   {
      $stop=1;
      while ( substr( $url, $l ) == $sep ) // strip '&'
         $url = substr( $url, $stop=0, $l );
      while ( substr( $url, -1 ) == '?' ) // strip '?'
         $url = substr( $url, $stop=0, -1);
   } while ( !$stop );
   return $url;
}

// relative to the calling URL, not to the current dir
// returns empty or some rel-dir with trailing '/'
// see also get_base_page()
function rel_base_dir()
{
   $dir = str_replace('\\','/',$_SERVER['PHP_SELF']);
   $rel = '';
   while ( $i=strrpos($dir,'/') )
   {
      $dir= substr($dir,0,$i);
      if ( !strcasecmp( $dir.'/' , SUB_PATH ) )
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
   if (!strcasecmp( SUB_PATH, substr($url,0,$len) ))
      $url = substr($url,$len);
   $url = str_replace( URI_AMP_IN, URI_AMP, $url);
   if ( $absolute )
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
   if ( $uid > 0 )
      return 1; //bit0
   $uid = 0;
   $uhandle = (string)@$_REQUEST[UHANDLE_NAME];
   if ( (string)$uhandle != '' ) // '000' is valid handle
      return 2; //bit1
   if ( $from_referer && ($refer=@$_SERVER['HTTP_REFERER']) )
   {
      //default user = last referenced user
      //(ex: message.php from userinfo.php by menu link)
      if ( preg_match('/[?'.URI_AMP_IN.']'.$uid_name.'=([0-9]+)/i', $refer, $eres) )
      {
         $uid = (int)$eres[1];
         if ( $uid > 0 )
            return 5; //bit0,2
      }
      $uid = 0;
      //adding '+' to HANDLE_LEGAL_REGS because of old DGS users having it in their Handle
      if ( preg_match('/[?'.URI_AMP_IN.']'.UHANDLE_NAME.'=([+'.HANDLE_LEGAL_REGS.']+)/i', $refer, $eres) )
      {
         $uhandle = (string)$eres[1];
         if ( $uhandle )
            return 6; //bit1,2
      }
   }
   return 0; //not found
} //get_request_user

function who_is_logged( &$player_row, $login_opts=LOGIN_DEFAULT_OPTS )
{
   global $main_path;
   $handle = safe_getcookie('handle');
   $sessioncode = safe_getcookie('sessioncode');
   $curdir = getcwd();

   // because of include_all_translate_groups() must be called from main dir
   chdir( $main_path);
   $player_id = is_logged_in($handle, $sessioncode, $player_row, $login_opts );
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
define('VAULT_CNT', 500); //an account with more than x hits...
define('VAULT_DELAY', SECS_PER_HOUR); //... during y seconds ...
define('VAULT_TIME', 24*SECS_PER_HOUR); //... is vaulted for z seconds
//two specific parameters for multi-users accounts, e.g. 'guest':
define('VAULT_CNT_X', VAULT_CNT*10); //activity count (larger)
define('VAULT_TIME_X', 2*SECS_PER_HOUR); //vault duration (smaller)

// login-options
define('LOGIN_QUICK_SUITE',  0x01);
define('LOGIN_UPD_ACTIVITY', 0x02);
define('LOGIN_RESET_NOTIFY', 0x04); // set Players.Notify to NONE (nothing to notify, after user checked on GUI)
define('LOGIN_SKIP_UPDATE',  0x08); // skips update & other checks for Players-table or main-menu; for error-page (to not decrease quota)
define('LOGIN_QUICK_PLAY',   0x10);
define('LOGIN_NO_QUOTA_HIT', 0x20);
define('LOGIN_SKIP_VFY_CHK', 0x40); // skip verify-checks to avoid redirect-loop
define('LOGIN_DEFAULT_OPTS', (LOGIN_UPD_ACTIVITY|LOGIN_RESET_NOTIFY));

/*!
 * \brief Check if the player $handle can be logged in
 * \return returns 0 if $handle can't be logged in;
 *    or for quick_suite==true if jump_to other page would have been initiated
 * \note else fill the $player_row array with its characteristics,
 *    load the player language definitions, set his timezone
 *    and finally returns his ID (i.e. >0)
 */
function is_logged_in($handle, $scode, &$player_row, $login_opts=LOGIN_DEFAULT_OPTS ) //must be called from main dir
{
   global $hostname_jump, $NOW, $dbcnx, $ActivityForHit, $ActivityMax, $is_down;

   if ( $login_opts & LOGIN_QUICK_PLAY )
      $login_opts |= LOGIN_QUICK_SUITE;
   $is_quick_suite = ($login_opts & LOGIN_QUICK_SUITE);
   $skip_update = ($login_opts & LOGIN_SKIP_UPDATE);

   $player_row = array( 'ID' => 0 );

   if ( $hostname_jump && preg_replace("/:.*\$/",'', @$_SERVER['HTTP_HOST']) != HOSTNAME )
   {
      list($protocol) = explode(HOSTNAME, HOSTBASE);
      if ( $is_quick_suite )
      {
         error('bad_host', "is_logged_in($handle)");
         return 0; // should not be reached normally
      }
      else
         jump_to( $protocol . HOSTNAME . $_SERVER['PHP_SELF'], true );
   }

   if ( empty($handle) || empty($dbcnx) )
   {
      include_all_translate_groups(); //must be called from main dir
      return 0;
   }

   $query= "SELECT *,UNIX_TIMESTAMP(Sessionexpire) AS Expire"
          .",Adminlevel+0 AS admin_level"
          .(VAULT_DELAY>0 ? ",UNIX_TIMESTAMP(VaultTime) AS X_VaultTime" : '')
          .',UNIX_TIMESTAMP(LastMove) AS X_LastMove'
          .',UNIX_TIMESTAMP(Lastaccess) AS X_Lastaccess'
          .',UNIX_TIMESTAMP(LastQuickAccess) AS X_LastQuickAccess'
          .',UNIX_TIMESTAMP(ForumReadTime) AS X_ForumReadTime'
          ." FROM Players WHERE Handle='".mysql_addslashes($handle)."' LIMIT 1";

   $result = db_query( 'is_logged_in.find_player', $query );
   if ( $result )
   {
      if ( @mysql_num_rows($result) == 1 )
      {
         $player_row = mysql_fetch_assoc($result);
         unset($player_row['Adminlevel']);
      }
      mysql_free_result($result);
   }

   $user_flags = (int)@$player_row['UserFlags'];
   if ( !($login_opts & LOGIN_SKIP_VFY_CHK) && ($user_flags & USERFLAG_ACTIVATE_REGISTRATION) )
      error('need_activation');

   if ( !$skip_update && $is_quick_suite ) // NOTE: for now only for quick-suite
      writeIpStats( (($login_opts & LOGIN_QUICK_PLAY) ? 'QPL' : ($is_quick_suite ? 'QDO' : 'WEB')) );

   $uid = (int)@$player_row['ID'];
   if ( $uid <= 0 )
   {
      include_all_translate_groups(); //must be called from main dir
      $player_row['ID'] = 0;
      return 0;
   }
   include_all_translate_groups($player_row); //must be called from main dir

   if ( (@$player_row['AdminOptions'] & ADMOPT_DENY_LOGIN) )
      error('login_denied', "is_logged_in($uid)");

   setTZ( $player_row['Timezone']);


   $session_expired= ( $player_row['Sessioncode'] != $scode || $player_row['Expire'] < $NOW );

   $upd = new UpdateQuery('Players');
   $upd->upd_raw('Hits', 'Hits+1' );

   $ip = (string)@$_SERVER['REMOTE_ADDR'];
   if ( !$skip_update && !$is_quick_suite && $ip && $player_row['IP'] !== $ip )
   {
      $upd->upd_txt('IP', $ip );
      $player_row['IP'] = $ip;
   }

   if ( !$skip_update && !$session_expired )
   {
      if ( $login_opts & LOGIN_UPD_ACTIVITY )
         $upd->upd_raw('Activity', "LEAST($ActivityMax,$ActivityForHit+Activity)" );
      if ( $login_opts & LOGIN_RESET_NOTIFY )
         $upd->upd_txt('Notify', 'NONE' );

      $upd->upd_raw('Lastaccess', "GREATEST(Lastaccess,FROM_UNIXTIME($NOW))" ); // update for both (web-site + quick-suite)
      if ( $is_quick_suite )
         $upd->upd_time('LastQuickAccess', $NOW);
      else
      {
         $browser = substr(@$_SERVER['HTTP_USER_AGENT'], 0, 150);
         if ( $player_row['Browser'] !== $browser )
         {
            $upd->upd_txt('Browser', $browser );
            $player_row['Browser'] = $browser;
         }
      }
   }

   $vaultcnt= true; //no vault for anonymous or if disabled or if server-down
   $quota_hit = !( $login_opts & LOGIN_NO_QUOTA_HIT );
   if ( !$is_down && !$skip_update && VAULT_DELAY>0 && $quota_hit && !$session_expired ) //exclude access deny from an other user
   {
      $vaultcnt = (int)@$player_row['VaultCnt'];
      $vaulttime = @$player_row['X_VaultTime'];
      $vault_fmt = T_("The activity of the account '%s' grew too high and swallowed up our bandwidth and resources.\n" .
         "Please, correct this behaviour.\nThis account is blocked until %s.");
      if ( $NOW >= $vaulttime ) // fever cool enough (expire-date passed -> reset quota for another period)
      {
         $vaultcnt = ( $uid > GUESTS_ID_MAX ) ? VAULT_CNT : VAULT_CNT_X; //multi-user account?
         $vaulttime = $NOW + VAULT_DELAY;
         $upd->upd_num('VaultCnt', $vaultcnt );
         $upd->upd_time('VaultTime', $vaulttime );
      } //from here: $NOW < $vaulttime
      else if ( $vaultcnt <= 0 ) // inside fever vault?
         $vaultcnt = 0; // stay in fever vault... quota-block in place till expire-date
      elseif ( $vaultcnt > 1 ) //measuring fever
         $upd->upd_num('VaultCnt', (--$vaultcnt) );
      else //if ( $vaulttime == 1 ) // quota up, but not expired (fever too high)
      {
         // enter fever vault... set quota-expire on block-time
         $vaultcnt = 0;
         $vaulttime = $NOW + ( $uid > GUESTS_ID_MAX ? VAULT_TIME : VAULT_TIME_X ); //multi-user account?
         $upd->upd_num('VaultCnt', $vaultcnt );
         $upd->upd_time('VaultTime', $vaulttime );

         DgsErrors::err_log( $handle, 'fever_vault');

         // send notifications to owner
         $subject= T_('Temporary access restriction');
         $text= 'On '.date(DATE_FMT, $NOW).":\n" . sprintf($vault_fmt, $handle, date(DATE_FMT,$vaulttime));

         if ( $uid > GUESTS_ID_MAX )
            $handles[]= $handle;
         if ( count($handles) > 0 )
            send_message("fever_vault.msg($uid,$ip)", $text, $subject, '', $handles, /*notify*/false );

         $email = $player_row['Email'];
         if ( $uid > GUESTS_ID_MAX && !verify_invalid_email(false, $email) )
            send_email("fever_vault.email($handle)", $email, 0, $text, FRIENDLY_LONG_NAME.' - '.$subject);
      }

      $player_row['VaultCnt']  = $vaultcnt;
      $player_row['VaultTime'] = date(DATE_FMT_QUICK, $vaulttime);
      $player_row['X_VaultTime'] = $vaulttime;
   }//fever-fault check

   if ( !$skip_update )
   {
      // DST-check if the player's clock need an adjustment from/to summertime
      if ( $player_row['ClockChanged'] != 'Y' && $player_row['ClockUsed'] != get_clock_used($player_row['Nightstart']) )
         $upd->upd_bool('ClockChanged', true ); // ClockUsed is updated once a day...
   }

   if ( $is_down )
      check_maintenance( @$player_row['Handle'] ); // revoke is_down for maintenance-users

   // check aggregated counts
   if ( !$is_down && !$is_quick_suite && !$skip_update )
   {
      $count_msg_new = count_messages_new( $uid, $player_row['CountMsgNew'] );
      if ( $count_msg_new >= 0 )
      {
         $upd->upd_num('CountMsgNew', $count_msg_new );
         $player_row['CountMsgNew'] = $count_msg_new;
      }

      if ( ALLOW_FEATURE_VOTE )
      {
         $count_feat_new = count_feature_new( $uid, $player_row['CountFeatNew'] );
         if ( $count_msg_new >= 0 )
         {
            $upd->upd_num('CountFeatNew', $count_feat_new );
            $player_row['CountFeatNew'] = $count_feat_new;
         }
      }

      $count_bulletin_new = Bulletin::count_bulletin_new( $player_row['CountBulletinNew'] );
      if ( $count_bulletin_new >= 0 )
      {
         $upd->upd_num('CountBulletinNew', $count_bulletin_new );
         $player_row['CountBulletinNew'] = $count_bulletin_new;
      }

      if ( ALLOW_TOURNAMENTS )
      {
         $count_tourney_new = count_tourney_new( $uid, $player_row['X_Lastaccess'], $player_row['CountTourneyNew'] );
         if ( $count_tourney_new >= 0 )
         {
            $upd->upd_num('CountTourneyNew', $count_tourney_new );
            $player_row['CountTourneyNew'] = $count_tourney_new;
         }
      }
   }


   // $updok will be false if server is down, or skip-update-option is enabled, or an error occurs and error() is set to 'no exit'
   if ( $is_down || $skip_update )
      $updok = false;
   else
   {
      $updok = db_query( "is_logged_in.update_player($uid)",
         "UPDATE Players SET " . $upd->get_query() . " WHERE ID=$uid LIMIT 1" );
      if ( @mysql_affected_rows() != 1 )
         $updok = false;
   }

   if ( !$vaultcnt ) //vault entered
   {
      $base_page = get_base_page();
      switch ( (string)$base_page )
      {
         case 'index.php':
            $text = sprintf($vault_fmt, $handle, date(DATE_FMT,$vaulttime));
            $_REQUEST['sysmsg'] = $text;
            $session_expired = true; //fake disconnection
            break;

         default:
            error('fever_vault', "is_logged_in($uid,$handle)");
            if ( $is_quick_suite )
               return 0; // should not be reached normally
            break;
      }
   }

   if ( $session_expired || ( !$skip_update && !$updok ) )
   {
      $player_row['ID'] = 0;
      return 0;
   }

   get_cookie_prefs($player_row);
   switch_admin_status($player_row); //get default admin_status

   return $uid;
} //is_logged_in

/*!
 * \brief Counts new messages for given user-id if current count < 0 (=needs-update).
 * \param $curr_count force counting if <0 or omitted
 * \return new new-message count (>=0) for given user-id; or -1 on error
 */
function count_messages_new( $uid, $curr_count=-1 )
{
   if ( $curr_count >= 0 )
      return $curr_count;
   if ( !is_numeric($uid) || $uid <= 0 )
      error( 'invalid_args', "count_messages_new.check.uid($uid)" );

   $row = mysql_single_fetch( "count_messages_new($uid)",
      "SELECT COUNT(*) AS X_Count FROM MessageCorrespondents WHERE uid='$uid' AND Folder_nr=".FOLDER_NEW );
   return ($row) ? (int)@$row['X_Count'] : -1;
}//count_messages_new

/*!
 * \brief Counts new features for given user-id if current count < 0 (=needs-update).
 * \param $curr_count force counting if <0 or omitted
 * \return new features count (>=0) for given user-id; or -1 on error
 */
function count_feature_new( $uid, $curr_count=-1 )
{
   if ( !ALLOW_FEATURE_VOTE )
      return -1;
   if ( $curr_count >= 0 )
      return $curr_count;
   if ( !is_numeric($uid) || $uid <= 0 )
      error( 'invalid_args', "count_features_new.check.uid($uid)" );

   $row = mysql_single_fetch( "count_features_new($uid)",
      "SELECT COUNT(*) AS X_Count " .
      "FROM Feature AS F " .
         "LEFT JOIN FeatureVote AS FV ON F.ID=FV.fid AND FV.Voter_ID='$uid' " .
      "WHERE F.Status='VOTE' AND ISNULL(FV.fid)" );
   return ($row) ? (int)@$row['X_Count'] : -1;
}//count_features_new

/*!
 * \brief Counts new tournaments for given user-id if current count < 0 (=needs-update).
 * \param $last_access timestamp of last-access
 * \param $curr_count force counting if <0 or omitted
 * \return new tournament count (>=0) for given user-id; or -1 on error
 */
function count_tourney_new( $uid, $last_access, $curr_count=-1 )
{
   global $NOW;

   if ( !ALLOW_TOURNAMENTS )
      return -1;

   // auto-refresh if last-access too old
   if ( $curr_count >= 0 && $last_access >= $NOW - DAYS_RESET_COUNT_TOURNEY_NEW*SECS_PER_DAY )
      return $curr_count;

   if ( !is_numeric($uid) || $uid <= 0 )
      error( 'invalid_args', "count_tourney_new.check.uid($uid)" );

   $row = mysql_single_fetch( "count_tourney_new($uid)",
      "SELECT COUNT(*) AS X_Count " .
      "FROM Tournament AS T " .
         "LEFT JOIN TournamentVisit AS TV ON TV.uid=$uid AND TV.tid=T.ID " .
      "WHERE T.Status IN ('REG','PAIR','PLAY') AND TV.tid IS NULL" ); // see consts TOURNEY_STATUS_...
   return ($row) ? (int)@$row['X_Count'] : -1;
}//count_tourney_new

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
function write_to_file($filename, $data, $quit_on_error=true )
{
   $num= @file_put_contents($filename, $data);
   if ( is_int($num) )
   {
      @chmod( $filename, 0666);
      return $num;
   }
   if ( $quit_on_error )
      error( 'couldnt_open_file', "write_to_file($filename)");
   trigger_error("write_to_file($filename) failed to write stream", E_USER_WARNING);
   return false;
}//write_to_file

function read_from_file( $filename, $quit_on_error=true, $incpath=false )
{
   $data = @file_get_contents( $filename, ($incpath ? FILE_USE_INCLUDE_PATH : 0) );
   if ( is_string($data) )
      return $data;
   if ( $quit_on_error )
      error( 'couldnt_open_file', "read_from_file.err1($filename)");
   trigger_error("read_from_file($filename) failed to open stream", E_USER_WARNING);
   return false;
}

function centered_container( $open=true)
{
   //the container must be a centered one which can be left or right aligned
   static $opened = false;
   if ( $opened )
   { //opened, close it
      echo "</td></tr></table>\n";
      $opened = false;
   }
   if ( $open )
   { //open a new one
      echo "\n<table class=Container><tr><td class=Container>";
      $opened = true;
   }
}

function section( $id='', $header='', $anchorName='', $with_hr_sep=false )
{
   static $section = '';

   centered_container( false);
   if ( $section )
   { //section opened, close it
      echo "</div>\n";
      $section = '';
      if ( $id )
         echo "<hr class=Section>\n";
   }
   if ( $id )
   { //section request, open it
      $section = attb_quote('sect'.$id);
      echo "\n<div id=$section class=Section>";
      if ( $with_hr_sep )
         echo "<hr>\n";
      if ( $anchorName )
         echo name_anchor($anchorName);
      if ( $header )
         echo "<h3 class=Header>$header</h3>";
      else
         echo '<br class=Section>';
   }
}

// $link can be arr( url => desc, ... ); $linkdesc then is separator
function add_link_page_link( $link=false, $linkdesc='', $extra='', $active=true)
{
   global $link_class;
   static $started = false;

   if ( $link === false )
   {
      if ( $started )
         echo "</dl>\n";
      $started = false;
      return 0;
   }

   if ( !$started )
   {
      $class = (@$link_class) ? $link_class : 'DocLink';
      echo "<dl class=\"$class\">\n";
      $started = true;
   }

   if ( $active )
   {
      if ( is_array($link) )
      {
         $arr = array();
         foreach ( $link as $link_url => $link_desc )
            $arr[] = "<a href=\"$link_url\">$link_desc</a>";
         echo '<dd>', implode($linkdesc, $arr);
      }
      else
         echo "<dd><a href=\"$link\">$linkdesc</a>";
   }
   else
      echo "<dd class=Inactive><span>$linkdesc</span>";
   if ( !empty($extra) )
      echo " --- $extra";
   echo "</dd>\n";
}

function activity_string( $act_lvl)
{
   switch ( (int)$act_lvl )
   {
      case 1: $img = 'star2.gif'; break; // orange
      case 2: $img = 'star.gif'; break;  // green
      default: return '&nbsp;';
   }
   global $base_path;
   $img= "<img class=InTextImage alt='*' src=\"{$base_path}images/$img\">";
   return str_repeat( $img, $act_lvl);
}


/*!
 * \brief Returns web-link with reference to given game.
 * \param $move can be MOVE_SETUP 'S' for shape-game; otherwise number
 * \param $extra (optional but best to fully-provided) array with fields,
 *        that if not given are loaded for game (if game exists):
 *        Whitename, Blackname, GameType, GamePlayers, Status=game-status
 */
function game_reference( $link, $safe_it, $class, $gid, $move=0, $extra=null )
{
   global $base_path;

   $gid = (int)$gid;
   $move = ( strtoupper($move) === MOVE_SETUP )
      ? MOVE_SETUP
      : ( is_numeric($move) ? (int)$move : 0 );

   $legal = ( $gid > 0 );
   if ( !is_array($extra) )
      $extra = array();
   if ( $legal && count($extra) < 5 )
   {
      $game_row = mysql_single_fetch( "game_reference.find_game($gid)",
         'SELECT G.GameType, G.Status, G.GamePlayers, black.Name AS Blackname, white.Name AS Whitename ' .
         'FROM Games AS G ' .
            'INNER JOIN Players as black ON black.ID=G.Black_ID ' .
            'LEFT JOIN Players as white ON white.ID=G.White_ID ' . // LEFT-join for MP-game
         "WHERE G.ID=$gid LIMIT 1" );
      if ( $game_row )
      {
         foreach ( array( 'Blackname', 'Whitename', 'GameType', 'GamePlayers', 'Status' ) as $key )
         {
            if ( !@$extra[$key] )
               $extra[$key] = $game_row[$key];
         }
         $safe_it = true;
      }
      else
         $legal = false;
   }
   $blackname = trim(@$extra['Blackname']);
   $whitename = trim(@$extra['Whitename']);
   $game_type = @$extra['GameType'];
   $is_std_go = !$game_type || ($game_type == GAMETYPE_GO);

   if ( $blackname )
      $blackname = "$blackname (B)";
   if ( $whitename )
      $whitename = "$whitename (W)";

   $gtype_text = ($game_type)
      ? GameTexts::format_game_type( $game_type, @$extra['GamePlayers'] )
      : '';
   if ( !$whitename && !$blackname )
      $text = ($gtype_text ? "{$gtype_text}-" : '') . "game #$gid" ;
   elseif ( $whitename && $blackname )
      $text = ($gtype_text ? "{$gtype_text}: " : '') . "$whitename vs. $blackname";
   else
      $text = ($gtype_text ? "{$gtype_text}: " : '') . "$whitename$blackname";

   if ( $safe_it )
      $text = make_html_safe($text);
   if ( $move === MOVE_SETUP )
      $text .= ', ' . T_('Shape-Setup#gametag');
   elseif ( $move > 0 )
      $text .= sprintf( ', %s #%s', T_('Move#gametag'), $move );

   if ( $link && $legal )
   {
      $url = ( $is_std_go || @$extra['Status'] != GAME_STATUS_SETUP )
         ? "game.php?gid=$gid" . (( $move > 0 || $move === MOVE_SETUP) ? URI_AMP."move=$move" : "")
         : "game_players.php?gid=$gid";
      $url = "A href=\"$base_path$url\" class=Game$class";
      if ( $link & REF_LINK_BLANK )
         $url .= ' target="_blank"';
      if ( $link & REF_LINK_ALLOWED )
      {
         $url = str_replace('"', ALLOWED_QUOT, $url);
         $text = ALLOWED_LT.$url.ALLOWED_GT.$text.ALLOWED_LT."/A".ALLOWED_GT ;
      }
      else
         $text = "<$url>$text</A>" ;
   }
   return $text;
}//game_reference

// format: ticket:issue
// NOTE: expects TICKET_REF with '%s' to be replaced with issue-arg
function ticket_reference( $link, $safe_it, $class, $issue )
{
   if ( !TICKET_REF )
      return $issue;

   $ticket_str = sprintf('%s:%s', T_('Ticket'), $issue);
   if ( $safe_it )
      $ticket_str = make_html_safe($ticket_str);

   if ( $link )
   {
      $url = 'A href="' . str_replace('%s', urlencode($issue), TICKET_REF) . '"';
      if ( $class )
         $url .= " class=$class";
      if ( $link & REF_LINK_ALLOWED )
      {
         $url = str_replace('"', ALLOWED_QUOT, $url);
         $ticket_str = ALLOWED_LT.$url.ALLOWED_GT.$ticket_str.ALLOWED_LT."/A".ALLOWED_GT;
      }
      else
         $ticket_str = "<$url>$ticket_str</A>";
   }

   return $ticket_str;
}//ticket_reference

// format: Feature #n: title
function feature_reference( $link, $safe_it, $class, $fid )
{
   global $base_path;

   $fid = (int)$fid;
   $legal = ( $fid > 0 );
   if ( $legal )
   {
      $query = "SELECT Subject FROM Feature WHERE ID=$fid LIMIT 1";
      if ( $row = mysql_single_fetch( "feature_reference.find_feature($fid)", $query ) )
      {
         $title = trim(@$row['Subject']);
         $safe_it = true;
      }
      else
         $legal = false;
   }

   $feature = sprintf( T_('Feature #%s: %s'), $fid, ( $legal ? $title : T_('???#feature') ));
   if ( $safe_it )
      $feature = make_html_safe($feature);

   if ( $link && $legal )
   {
      $url = $base_path."features/vote_feature.php?fid=$fid";
      $url = 'A href="' . $url . '"';
      $class = 'Feature'.$class;
      if ( $class )
        $url .= " class=$class";
      if ( $link & REF_LINK_ALLOWED )
      {
         $url = str_replace('"', ALLOWED_QUOT, $url);
         $feature = ALLOWED_LT.$url.ALLOWED_GT.$feature.ALLOWED_LT."/A".ALLOWED_GT ;
      }
      else
         $feature = "<$url>$feature</A>";
   }

   return $feature;
}//feature_reference

// format: Tournament #n [title]
function tournament_reference( $link, $safe_it, $class, $tid )
{
   global $base_path;

   $tid = (int)$tid;
   $legal = ( $tid > 0 );
   if ( $legal )
   {
      $query = "SELECT Title FROM Tournament WHERE ID=$tid LIMIT 1";
      if ( $row = mysql_single_fetch( "tournament_reference.find_tournament($tid)", $query ) )
      {
         $title = trim(@$row['Title']);
         $safe_it = true;
      }
      else
         $legal = false;
   }

   $tourney = sprintf( T_('Tournament #%s [%s]'), $tid, ( $legal ? $title : T_('???#tournament') ));
   if ( $safe_it )
      $tourney = make_html_safe($tourney);

   if ( $link && $legal )
   {
      $url = $base_path."tournaments/view_tournament.php?tid=$tid";
      $url = 'A href="' . $url . '"';
      if ( $link & REF_LINK_BLANK )
         $url .= ' target="_blank"';
      $class = 'Tournament'.$class;
      if ( $class )
        $url .= " class=$class";
      if ( $link & REF_LINK_ALLOWED )
      {
         $url = str_replace('"', ALLOWED_QUOT, $url);
         $tourney = ALLOWED_LT.$url.ALLOWED_GT.$tourney.ALLOWED_LT."/A".ALLOWED_GT ;
      }
      else
         $tourney = "<$url>$tourney</A>";
   }

   return $tourney;
}//tournament_reference

// format: Survey #n [title]
function survey_reference( $link, $safe_it, $class, $sid )
{
   global $base_path;

   $sid = (int)$sid;
   $legal = ( $sid > 0 );
   if ( $legal )
   {
      $query = "SELECT Status, Title FROM Survey WHERE ID='$sid' LIMIT 1";
      if ( $row = mysql_single_fetch( "survey_reference.find_survey($sid)", $query ) )
      {
         $status = @$row['Status'];
         $title = trim(@$row['Title']);
         $safe_it = true;
      }
      else
         $legal = false;
   }

   $survey = ($legal)
      ? sprintf( T_('Survey #%s (%s) [%s]'), $sid, SurveyControl::getStatusText($status), $title )
      : sprintf( T_('Survey #%s [%s]'), $sid, T_('???#survey') );
   if ( $safe_it )
      $survey = make_html_safe($survey);

   if ( $link && $legal )
   {
      $url = $base_path."view_survey.php?sid=$sid";
      $url = 'A href="' . $url . '"';
      if ( $link & REF_LINK_BLANK )
         $url .= ' target="_blank"';
      $class = 'Survey'.$class;
      if ( $class )
        $url .= " class=$class";
      if ( $link & REF_LINK_ALLOWED )
      {
         $url = str_replace('"', ALLOWED_QUOT, $url);
         $survey = ALLOWED_LT.$url.ALLOWED_GT.$survey.ALLOWED_LT."/A".ALLOWED_GT ;
      }
      else
         $survey = "<$url>$survey</A>";
   }

   return $survey;
}

/*! \brief Sends user-reference with link to send new message to user. */
// IMPORTANT NOTE: do not rename this function without adjusting "<send ..>"-DGS-tag in $html_safe_preg !!
function send_reference( $link, $safe_it, $class, $player_ref, $player_name=false, $player_handle=false)
{
   if ( is_numeric($link) ) //not owned reference
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
 * : then the missing arguments will be retrieved from the database if needed.
 **/
function user_reference( $link, $safe_it, $class, $player_ref, $player_name=false, $player_handle=false)
{
   global $base_path;
   if ( is_array($player_ref) ) //i.e. $player_row
   {
      if ( !$player_name )
         $player_name = $player_ref['Name'];
      if ( !$player_handle )
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
   if ( !$byid || $legal )
   {
      $byid = false;
      if ( $legal )
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
   if ( $legal && ($player_name===false || $player_handle===false) )
   {
      $row = load_cache_user_reference( 'user_reference', $byid, $player_ref );
      if ( $row )
      {
         if ( $player_name === false )
            $player_name = $row['Name'];
         if ( $player_handle === false )
            $player_handle = $row['Handle'];
         $safe_it = true;
      }
      else
         $legal = false;
   }
   $player_name = trim($player_name);
   $player_handle = trim($player_handle);
   if ( !$player_name )
      $player_name = "User#$player_ref";
   if ( $player_handle )
      $player_name.= " ($player_handle)" ;
   if ( $safe_it )
      $player_name = make_html_safe($player_name) ;
   if ( $link && $legal )
   {
      if ( is_string($link) ) //owned reference. Must end with '?' or URI_AMP
      {
         $url = $link;
         $link = 0;
         $class = 'Ref'.$class;
      }
      elseif ( $link<0 ) //send_reference
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
      if ( $class )
         $url .= " class=\"$class\"";
      if ( $link & REF_LINK_BLANK )
         $url .= ' target="_blank"';
      if ( $link & REF_LINK_ALLOWED )
      {
         $url = str_replace('"', ALLOWED_QUOT, $url);
         $player_name = ALLOWED_LT.$url.ALLOWED_GT.$player_name.ALLOWED_LT."/A".ALLOWED_GT ;
      }
      else
         $player_name = "<$url>$player_name</A>" ;
   }
   return $player_name ;
}

function load_cache_user_reference( $dbgmsg, $by_id, $player_ref )
{
   $player_ref = strtolower($player_ref);
   $qfield = ( $by_id ) ? 'ID' : 'Handle';
   $qvalue = ( $by_id ) ? (int)$player_ref : sprintf("'%s'", mysql_addslashes($player_ref) );

   $dbgmsg = "load_user_reference($qfield,$player_ref).$dbgmsg";
   $key = 'user_ref.' . strtolower($qfield) . '.' . $player_ref;

   $row = DgsCache::fetch( $dbgmsg, CACHE_GRP_USER_REF, $key );
   if ( is_null($row) )
   {
      $row = mysql_single_fetch( $dbgmsg, "SELECT Name, Handle FROM Players WHERE $qfield = $qvalue LIMIT 1" );
      DgsCache::store( $dbgmsg, CACHE_GRP_USER_REF, $key, $row, SECS_PER_DAY );
   }
   return $row;
}

// clear cache for load_cache_user_reference() on potential Name/Handle-update
function delete_cache_user_reference( $dbgmsg, $uid, $uhandle, $uhandle_new=null )
{
   DgsCache::delete( $dbgmsg, CACHE_GRP_USER_REF, "user_ref.id.$uid" );
   DgsCache::delete( $dbgmsg, CACHE_GRP_USER_REF, "user_ref.handle." . strtolower($uhandle) );
   if ( !is_null($uhandle_new) && strcasecmp($uhandle, $uhandle_new) != 0 )
      DgsCache::delete( $dbgmsg, CACHE_GRP_USER_REF, "user_ref.handle." . strtolower($uhandle_new) );
}

/*!
 * \brief Checks if there are observers (has_observers) and if specific user is an observer.
 * \param $check_user null = check for is_on_observe_list(gid,uid);
 *        otherwise assume $check_user is pre-loaded is_on_observe_list()
 * \return arr( bool has_observers, bool uid-is-on-observe-list )
 *
 * \see is_on_observe_list()
 * \see has_observers()
 */
function check_for_observers( $gid, $uid, $check_user )
{
   $dbgmsg = "check_for_observers($gid,$uid)";
   $key = "Observers.$gid.$uid";

   $result = DgsCache::fetch( $dbgmsg, CACHE_GRP_GAME_OBSERVERS, $key );
   if ( is_null($result) )
   {
      $is_on_observe_list = ( is_null($check_user) ) ? is_on_observe_list( $gid, $uid ) : (bool)$check_user;
      $has_observers = ( $is_on_observe_list ) ? true : has_observers( $gid );

      $result = array( $has_observers, $is_on_observe_list );
      DgsCache::store( $dbgmsg, CACHE_GRP_GAME_OBSERVERS, $key, $result, 30*SECS_PER_MIN, "Observers.$gid" );
   }

   return $result;
}

// returns true, if there are observers for specified game
function has_observers( $gid )
{
   $result = db_query( 'has_observers',
      "SELECT ID FROM Observers WHERE gid=$gid LIMIT 1");
   if ( !$result )
      return false;
   $res = ( @mysql_num_rows($result) > 0 );
   mysql_free_result($result);
   return $res;
}

function is_on_observe_list( $gid, $uid )
{
   $result = db_query( 'is_on_observe_list',
      "SELECT ID FROM Observers WHERE gid=$gid AND uid=$uid LIMIT 1");
   if ( !$result )
      return false;
   $res = ( @mysql_num_rows($result) > 0 );
   mysql_free_result($result);
   return $res;
}

function toggle_observe_list( $gid, $uid, $toggle_yes )
{
   $dbgmsg = "toggle_observe_list($gid,$uid,$toggle_yes)";

   $my_observe = is_on_observe_list( $gid, $uid );
   if ( $toggle_yes == ($my_observe ? 'N' : 'Y') )
   {
      ta_begin();
      {//HOT-section to update observers
         if ( $my_observe )
            db_query( $dbgmsg.'.delete', "DELETE FROM Observers WHERE gid=$gid AND uid=$uid LIMIT 1");
         else
            db_query( $dbgmsg.'.insert', "INSERT INTO Observers SET gid=$gid, uid=$uid");
         $my_observe = !$my_observe;

         DgsCache::delete_group( $dbgmsg, CACHE_GRP_GAME_OBSERVERS, "Observers.$gid" );
      }
      ta_end();
   }
   return $my_observe;
}

//$Text must NOT be escaped by mysql_addslashes()
// IMPORTANT NOTE: caller needs to open TA with HOT-section!!
function delete_all_observers( $gid, $notify, $Text='' )
{
   global $NOW;
   $dbgmsg = "delete_all_observers($gid,$notify)";

   if ( $notify )
   {
      $result = db_query( $dbgmsg.'.find',
         "SELECT Observers.uid AS pid " .
         "FROM Observers " .
         "WHERE gid=$gid AND Observers.uid>".GUESTS_ID_MAX ); //exclude guest

      if ( @mysql_num_rows($result) > 0 )
      {
         $Subject = 'An observed game has finished';

         $to_ids = array();
         while ( $row = mysql_fetch_array( $result ) )
            $to_ids[] = $row['pid'];
         mysql_free_result($result);

         send_message( $dbgmsg.'send_msg', $Text, $Subject
            , $to_ids, '', /*notify*/true
            , /*sys-msg*/0, MSGTYPE_NORMAL, $gid);
      }
      else
         mysql_free_result($result);
   }

   db_query( $dbgmsg.'.del_obs', "DELETE FROM Observers WHERE gid=$gid" );
   DgsCache::delete_group( $dbgmsg, CACHE_GRP_GAME_OBSERVERS, "Observers.$gid" );
} //delete_all_observers



// definitions and functions to help avoid '!=' or 'NOT IN' in SQL-where-clauses:

global $ENUM_GAMES_STATUS; //PHP5
$ENUM_GAMES_STATUS = array( GAME_STATUS_KOMI, GAME_STATUS_SETUP, GAME_STATUS_INVITED,
   GAME_STATUS_PLAY, GAME_STATUS_PASS, GAME_STATUS_SCORE, GAME_STATUS_SCORE2, GAME_STATUS_FINISHED );

/*!
 * Builds IN-SQL-part for some enum-array containing all possible values for a table-column.
 * \param $arr_enum non-empty array with all possible values for a table-column
 * var-args params enum-values that shouldn't match on table-column;
 *        must not contain all elements of enum(!)
 * Example: 'Games.Status' . not_in_clause( $ENUM_GAMES_STATUS, GAME_STATUS_FINISHED, ... )
 */
function not_in_clause( $arr_enum )
{
   $arr_not = func_get_args();
   $arr = array_diff( $arr_enum, array_slice( $arr_not, 1 ) );
   return " IN ('" . implode("','", $arr) . "') ";
}


function RGBA($r, $g, $b, $a=NULL)
{
   if ( is_null($a) )
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
   if ( is_null($bgcolor) )
      $bgcolor = "f7f5e3"; //$bg_color value (#f7f5e3)
   list($r,$g,$b,$a)= split_RGBA($color, 0);
   list($br,$bg,$bb,$ba)= split_RGBA($bgcolor);
   return blend_alpha($r,$g,$b,$a,$br,$bg,$bb);
}

function blend_warning_cell_attb( $title='', $bgcolor='f7f5e3', $col='ff000033')
{
   $str= ' bgcolor="#' . blend_alpha_hex( $col, $bgcolor) . '"';
   if ( $title )
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
   if ( is_array($attbs) )
      return array_change_key_case( $attbs, CASE_LOWER);
   if ( !is_string($attbs) )
      return array();

   $nr_matches = preg_match_all(
      "%\\b([a-z][a-z0-9]*)\\s*=\\s*(((['\"])(.*?)\\4)|([a-z_0-9]+\\b))%is"
      , $attbs, $matches );

   $attbs = array();
   for ($i=0; $i<$nr_matches; $i++)
   {
      $key = $matches[1][$i];
      if ( !$key )
         continue;
      $val = $matches[6][$i];
      if ( !$val )
         $val = $matches[5][$i];
      $attbs[strtolower($key)]= $val;
   }
   return $attbs;
}

//return a string of the attributs $attbs
function attb_build( $attbs)
{
   $attbs= attb_parse( $attbs);
   if ( is_array($attbs) )
   {
      $str= '';
      foreach ( $attbs as $key => $val )
      {
         if ( $key == 'colspan' && $val < 2 )
            continue;
         $str .= ' '.$key.'=';

         // don't quote values of JavaScript-attributes
         //if ( preg_match( "/^(on((dbl)?click|mouse(down|up|over|move)|key(press|down|up)))$/i", $key ) )
         if ( strncasecmp($key,'on',2) == 0 ) // begins with 'on'
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
   if ( isset($attb1['class']) && isset($attb2['class']) )
   {
      if ( is_string($class_sep) )
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
   if ( $title )
      $str.= ' title='.attb_quote($title);
   elseif ( is_null($title) )
      $str.= ' title='.attb_quote($alt);
   if ( $height>=0 )
      $str.= " height=\"$height\"";
   if ( $width>=0 )
      $str.= " width=\"$width\"";
   $str.= attb_build($attbs);
   return $str.'>';
}

function anchor( $href, $text=null, $title='', $attbs='')
{
   if ( is_null($text) ) $text = $href;
   $str = "<a href=\"$href\"";
   if ( is_array($attbs) )
   {
      if ( isset($attbs['accesskey']) )
      {
         $xkey = trim($attbs['accesskey']);
         unset($attbs['accesskey']);
         if ( (string)$xkey != '' ) // can be '0'
         {
            $xkey = substr($xkey,0,1);
            $title.= " [&amp;$xkey]";
            $str.= ' accesskey='.attb_quote($xkey);
         }
      }
   }
   if ( $title )
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
   if ( !is_numeric($width) )
      $width = 1;
   if ( !is_numeric($height) )
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

function buildErrorListString( $errmsg, $errors, $colspan=0, $safe=true, $lineclass='TWarning', $errclass='ErrorMsg' )
{
   if ( count($errors) == 0 )
      return '';

   if ( $colspan <= 0 )
      return span($errclass, ( $errmsg ? "$errmsg:" : '') . "<br>\n* " . implode("<br>\n* ", $errors));
   else
   {
      $out = "\n<ul>";
      foreach ( $errors as $err )
         $out .= "<li>" . span($lineclass, ($safe ? make_html_safe($err, 'line') : $err)) . "\n";
      $out .= "</ul>\n";
      $out = span($errclass, ( $errmsg ? "$errmsg:<br>\n" : '' )) . $out;
      return "<td colspan=\"$colspan\">$out</td>";
   }
}//buildErrorListString

function buildWarnListString( $warnmsg, $warnings, $colspan=0 )
{
   return buildErrorListString( $warnmsg, $warnings, $colspan, true, 'TWarning', 'WarnMsg' );
}

/*!
 * \brief Parses given date-string using static date-formats into UNIX-timestamp.
 * \param $allow_secs true = seconds will be parsed too with format [YYYY-MM-DD hh:mm:ss];
 *       otherwise parse-format is [YYYY-MM-DD hh:mm] and seconds are set to 0
 * \return 0 if no date-string given; integer as UNIX-timestamp with date-value; otherwise error-string
 */
function parseDate( $msg, $date_str, $allow_secs=false )
{
   $result = 0;
   $date_str = trim($date_str);
   if ( $date_str != '' )
   {
      if ( preg_match( "/^(\d{4})-?(\d+)-?(\d+)(?:\s+(\d+)(?::(\d+)(?::(\d+))?))$/", $date_str, $matches ) )
      {// (Y)=1, (M)=2, (D)=3, (h)=4, (m)=5, [ (s)=6 ]
         list(, $year, $month, $day, $hour, $min ) = $matches;
         if ( count($matches) > 6 )
            $secs = $matches[6];
         if ( !$allow_secs || (string)$secs == '' )
            $secs = 0;
         $result = mktime( 0+$hour, 0+$min, 0+$secs, 0+$month, 0+$day, 0+$year );
      }
      else
         $result = sprintf( T_('Dateformat of [%s] is wrong, expected [%s] for [%s]'),
            $date_str, FMT_PARSE_DATE . ($allow_secs ? '[:ss]' : ''), $msg );
   }
   return $result;
}//parseDate

/*!
 * \brief Prevents excessive usage of scripts by writing and checking file into DATASTORE_FOLDER to keep track of last use.
 * \param $filename filename to be stored in DATASTORE_FOLDER
 * \param $min_interval minimum time-interval [in secs] that must lie between two calls of script;
 *        method does nothing if <1
 * \param $max_interval maximum time-interval [in secs] that a script should be used regardless of min-interval;
 *        if 0 no max-check is done
 * \param $read_content if set, return additional 3 results path_cache/header_content/body_content
 * \return general result-format: ( allow_exec, last_call_time [, path_cache, header_content, body_content ] );
 *         ( false, last-check-time ) if time-interval since last use was below $min_interval (last-check in secs);
 *         ( true, 0 ) if script-execution is following min/max-interval or if check disabled with $min_interval < 1
 * \note errors are created if DATASTORE_FOLDER is unset, directory not existing or missing permissions
 * \note currently DST (daylight saving time) is not handled
 */
function enforce_min_timeinterval( $subdir, $filename, $min_interval, $max_interval=0, $read_content=false )
{
   global $NOW;

   if ( $min_interval < 1 ) // check disabled
      return array( true, 0 );
   if ( (string)DATASTORE_FOLDER == '' )
      error('internal_error', "enforce_min_timeinterval.miss.datastore");

   $path = build_path_dir( $_SERVER['DOCUMENT_ROOT'], DATASTORE_FOLDER );
   if ( $subdir )
      $path .= '/' . $subdir;
   if ( !is_dir($path) )
   {
      if ( !mkdir($path, 0777, /*recursive*/true) )
         error('internal_error', "enforce_min_timeinterval.miss.datastore_dir");
   }

   $file_path = "$path/$filename";
   clearstatcache(); //FIXME since PHP5.3.0 with filename

   $last_check_time = 0;
   $header = $body = '';
   if ( file_exists($file_path) )
   {
      if ( $max_interval > 0 || $read_content )
      {
         $file_data = read_from_file( $file_path, /*err-quit*/false );
         $pos_lf = strpos($file_data, "\n");
         if ( $pos_lf === false )
            $header = $file_data;
         else
         {
            $header = substr($file_data, 0, $pos_lf );
            $body = substr($file_data, $pos_lf + 1 );
         }
      }

      if ( $max_interval > 0 )
      {
         $last_ok_time = (int)$header;
         $no_max_trigger = ( $last_ok_time <= 0 ) || ( $NOW - $last_ok_time < $max_interval );
      }
      else
         $no_max_trigger = true;

      if ( $no_max_trigger )
      {
         $mtime = (int)@filemtime( $file_path );
         if ( $NOW - $mtime <= $min_interval )
            $last_check_time = $mtime;
      }
   }

   if ( $last_check_time > 0 ) // not-ok
   {
      touch($file_path);
      $result = array( false, $last_check_time );
   }
   else // ok
   {
      $header = $NOW;
      $cnt = write_to_file( $file_path, "$header\n$body", /*err-quit*/false );
      $result = array( ($cnt > 0), 0 );
   }

   if ( $read_content )
      array_push( $result, $file_path, $header, $body );
   return $result;
}//enforce_min_timeinterval

// $cache_key : QST_CACHE_...
function clear_cache_quick_status( $arr_uids, $cache_key )
{
   if ( (string)DATASTORE_FOLDER == '' )
      return;

   if ( !is_array($arr_uids) )
      $arr_uids = array( $arr_uids );
   else
      $arr_uids = array_unique($arr_uids);

   $path = build_path_dir( $_SERVER['DOCUMENT_ROOT'], DATASTORE_FOLDER ) . '/qst/quick_status-';
   clearstatcache(); //FIXME since PHP5.3.0 with filename

   foreach ( $arr_uids as $uid )
   {
      if ( !is_numeric($uid) || $uid <= GUESTS_ID_MAX )
         continue;

      $file_path = $path . $uid;
      if ( file_exists($file_path) )
      {
         $mtime = (int)@filemtime( $file_path );
         if ( $GLOBALS['NOW'] - $mtime > SECS_PER_HOUR ) // delete if older than 1h
            @unlink($file_path);
         else
            @file_put_contents($file_path, "CLEAR $cache_key\n", FILE_APPEND); // block-specific-clearing
      }
   }
}//clear_cache_quick_status

?>
