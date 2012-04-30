<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Common";

define('ALLOW_AUTH', true);

// Allowed characters are not obvious with our RSS Feeder
// - because XML restrictions are strong
// - because our RSS can be read over the world
// - because it could have to send any original DGS charsets (within user names)
// There are two ways left (to be tested). Shortly:
// - 'iso': iso-8859-1 charsset + chars [^\x20-\x7f] escaped
// - 'utf': UTF-8 charsset + chars [\x00-\x1f] escaped
define('CHARSET_MODE', 'utf'); //'iso' or 'utf'

define('CACHE_MIN', 10);

function error($err, $debugmsg=NULL)
{
   global $uhandle;

   $title= str_replace('_',' ',$err);
   list( $xerr, $uri)= err_log( $uhandle, $err, $debugmsg);

   global $rss_opened;
   if( !$rss_opened )
      rss_open( 'ERROR');
   rss_error( $xerr, $title);
   rss_close();
   exit;
}

chdir('..');
require_once( "include/quick_common.php" );
require_once( "include/connect2mysql.php" );
require_once( "include/game_functions.php" );
require_once( "include/gui_bulletin.php" );

$TheErrors->set_mode(ERROR_MODE_PRINT);


/* For a default layout:

Beginning to Style Your RSS Feed
- http://mondaybynoon.com/2006/08/14/beginning-to-style-your-rss-feed/

Adding a CSS StyleSheet to your RSS Feed
- http://www.petefreitag.com/item/208.cfm

Some examples:
- http://mondaybynoon.com/feed
- http://www.tlc.ac.uk/xml/news-atom.xml

Basis:
- http://purl.org/rss/1.0/
- http://www.mnot.net/rss/tutorial/
*/



/*
   Special characters and different charset are a worry with xml+rss.
   We have not yet found the good solution.
*/

/*
$xmltrans = get_html_translation_table(HTML_ENTITIES, ENT_QUOTES); //ENT_COMPAT
foreach( $xmltrans as $key => $value )
   $xmltrans[$key] = '&#'.ord($key).';';
*/
$xmltrans = array();
for( $i=1; $i<0x20; $i++ )
   $xmltrans[chr($i)] = ''; //"&#$i;";
unset( $xmltrans["\t"]);
unset( $xmltrans["\n"]);

// see also <![CDATA[#]]> for particular cases.
// CDATA-section does not need escaping of: & > " $ '$ $' ]
// - no escape of ']' at end needed, just write ']]]>' as CDATA only recognizes ']]>' as end
$cdata_trans = array() + $xmltrans;
$cdata_trans['<'] = '&lt;';

/* FIXME needed (see CDATA-note above) ?
// XML only supports these entities: &amp; &lt; &gt; &quot;
//    but they must be used in text fields
$xmltrans['&'] = '&amp;';
$xmltrans['<'] = '&lt;';
$xmltrans['>'] = '&gt;';
$xmltrans['"'] = '&quot;';

//XML seems to not like some chars sequences (like "'$$'")
$xmltrans['\'$'] = '&quot;$';
$xmltrans['$\''] = '$&quot;';
//$xmltrans['\''] = '\\\'';
//$xmltrans['$'] = '\$';
//$xmltrans['\''] = '&#'.ord('\'').';';

switch( (string)CHARSET_MODE )
{
   case 'iso':
      $encoding_used = 'iso-8859-1';

      for( $i=0x80; $i<0x100 ; $i++ )
         $xmltrans[chr($i)] = "&#$i;";
      break;

   case 'utf':
      $encoding_used = 'utf-8';

      for( $i=0x80; $i<0x100 ; $i++ )
         $xmltrans[chr($i)] = "&#$i;";
      break;

   default:
      error('internal_error', 'rss.no_charset');
}
*/


// could not be called twice on the same string
function rss_safe( $str)
{
   global $cdata_trans;
   $str = reverse_htmlentities( $str);
   // XML seems to not like some chars sequences (like "'$$'"), so put in CDATA
   return '<![CDATA[' . strtr($str, $cdata_trans) . ']]>';
}

// could not be called twice on the same string
function rss_link( $lnk)
{
   // XML does not support some URL chars (like '#')
   return '<![CDATA[' . $lnk . ']]>';
}


function rss_date( $dat=0 )
{
   return gmdate( 'D, d M Y H:i:s \G\M\T', ( $dat ? $dat : $GLOBALS['NOW'] ) );
}

// $tag must be valid and $str must be safe
function rss_tag( $tag, $str)
{
   return "<$tag>$str</$tag>\n";
}


$rss_opened= false;
function rss_open( $title, $description='', $html_clone='', $cache_minutes= CACHE_MIN)
{
   global $encoding_used, $NOW, $rss_opened;

   ob_start("ob_gzhandler");
   $rss_opened= true;

   $last_modified_stamp= $NOW;

   if( empty($encoding_used) )
   {
      $encoding_used = 'UTF-8'; //really better for RSS feeders
      //$encoding_used = 'iso-8859-1';
   }

   if( empty($html_clone) )
      $html_clone = HOSTBASE;

   if( empty($description) )
      $description = $title;

   header('Content-Type: text/xml; charset='.$encoding_used);

   echo "<?xml version=\"1.0\" encoding=\"$encoding_used\"?>\n";
   echo "<rss version=\"2.0\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\""
        . " xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">\n";
   echo "<!--

If you're seeing this, you've clicked on the link
for ".FRIENDLY_LONG_NAME." Quick RSS feed.
This file is not meant to be read by a web
browser directly. Instead you're meant to copy
the URL for the file, which is:

  {".HOSTBASE."}rss/status.php

and paste it into your RSS reader or podcast program.

-->\n";

   $title = FRIENDLY_LONG_NAME.' - '.$title;
   echo " <channel>\n"
      . '  '.rss_tag( 'title', rss_safe( $title))
      . '  '.rss_tag( 'link', rss_link( $html_clone))
      . '  '.rss_tag( 'pubDate', rss_date( $last_modified_stamp))
      . ( is_numeric( $cache_minutes)
            ? '  '.rss_tag( 'ttl', $cache_minutes) : '')
      . '  '.rss_tag( 'language', 'en-us')
      . '  '.rss_tag( 'description', rss_safe( $description))
      ;
}


function rss_close( )
{
   echo "\n </channel>\n</rss>";
   ob_end_flush();
}


function rss_item( $title, $link, $description='', $pubDate='', $category='', $guid='')
{
   if( empty($description) )
      $description = $title;
   if( empty($guid) )
      $guid = $link;

   $str = "  <item>\n";
   $str.= '   '.rss_tag( 'title', rss_safe( $title));
   if( $link )
      $str.= '   '.rss_tag( 'link', rss_link( $link));
   if( $guid )
      $str.= '   '.rss_tag( 'guid', rss_link( $guid));
   if( $category )
      $str.= '   '.rss_tag( 'category', rss_safe( $category));
   //if( $pubDate )
      $str.= '   '.rss_tag( 'pubDate', rss_date( $pubDate));
   $str.= '   '.rss_tag( 'description', rss_safe( $description));
   $str.= "  </item>\n";

   echo $str;
}


function rss_error( $str, $title='', $link='')
{
   if( !$link )
      $link= HOSTBASE;
   if( !$title )
      $title= 'ERROR';
   else
      $title= 'ERROR: '.$title;

   rss_item( $title, $link, 'ERROR: '.$str);
}


function rss_warning( $str, $title='', $link='')
{
   if( !$link )
      $link= HOSTBASE;
   if( !$title )
      $title= 'Warning';
   else
      $title= 'Warning: '.$title;

   rss_item( $title, $link, 'Warning: '.$str);
}


/*
   header('WWW-Authenticate: Digest realm="'.$realm.'", qop="auth", nonce="'
   .uniqid("55").'", opaque="'.md5($realm).'"');
*/
function rss_auth( $cancel_str, $uhandle='')
{
   //if( $uhandle ) $uhandle= ' - '.$uhandle; else
      $uhandle= '';
   $uhandle= FRIENDLY_LONG_NAME . $uhandle;

   header("WWW-Authenticate: Basic realm=\"$uhandle\"");
   header('HTTP/1.0 401 Unauthorized');

   //echo "$cancel_str\n";
   rss_open( 'ERROR');
   rss_error( $cancel_str);
   rss_close();
   exit;
}



if( $is_down )
{
   rss_open( 'WARNING');
   rss_warning($is_down_message, 'The server is down');
   rss_close();
}
else
{
   $logged_in = false;
   $loggin_mode = '';
   $uhandle = get_request_arg('userid');
   $passwd = get_request_arg('passwd');
   if( $uhandle && $passwd )
      $loggin_mode = 'password';
   else if( ALLOW_AUTH )
   {
      $uhandle = arg_stripslashes((string)@$_SERVER['PHP_AUTH_USER']);
      $passwd = arg_stripslashes((string)@$_SERVER['PHP_AUTH_PW']);
      $authid = get_request_arg('authid');
      if( $authid && strcasecmp($authid,$uhandle) != 0 )
      {
         $uhandle = $authid;
         $passwd = '';
         $loggin_mode = 'authenticate';
      }
      else if( $uhandle && $passwd )
         $loggin_mode = 'password';
   }
   if( !$loggin_mode )
   {
      $uhandle= safe_getcookie('handle');
      $loggin_mode = 'cookie';
   }


   //disabling caches make some RSS feeders to instantaneously refresh.
   disable_cache( $NOW, $NOW + CACHE_MIN*60);

   connect2mysql();

   if( $loggin_mode=='password' )
   {
      // temp password?

      $result = @db_query( "rss_status.find_user.password($uhandle)",
         "SELECT *, UNIX_TIMESTAMP(Sessionexpire) AS Expire ".
         "FROM Players WHERE Handle='".mysql_addslashes($uhandle)."' LIMIT 1" );

      if( @mysql_num_rows($result) == 1 )
      {
         $player_row = mysql_fetch_array($result);

         if( check_password( $uhandle, $player_row["Password"], $player_row["Newpassword"], $passwd ) )
            $logged_in = true;
         else
            error('wrong_password', "rss_status.check_password($uhandle)");

         if( (@$player_row['AdminOptions'] & ADMOPT_DENY_LOGIN) )
            error('login_denied', "rss_status.check.user($uhandle)");
      }
      //else error('wrong_userid', "rss_status.check_user($uhandle)");
   }

   if( $loggin_mode=='cookie' )
   {
      // logged in?

      $result = @db_query( "rss_status.find_user.login($uhandle)",
         "SELECT *, UNIX_TIMESTAMP(Sessionexpire) AS Expire ".
         "FROM Players WHERE Handle='".mysql_addslashes($uhandle)."' LIMIT 1" );

      if( @mysql_num_rows($result) == 1 )
      {
         $player_row = mysql_fetch_assoc($result);

         if( $player_row['Sessioncode'] === safe_getcookie('sessioncode') && $player_row["Expire"] >= $NOW )
            $logged_in = true;

         if( (@$player_row['AdminOptions'] & ADMOPT_DENY_LOGIN) )
            error('login_denied', "wap_status.check.cookie.user($uhandle)");
      }
   }

   if( !$logged_in )
   {
      if( ALLOW_AUTH ) //or $loggin_mode=='authenticate'
         rss_auth( 'Unauthorized access forbidden!', $uhandle);
      error('not_logged_in', "rss_status.check_login($uhandle)");
   }


   setTZ( $player_row['Timezone']);

   $my_id = (int)$player_row['ID'];
   $my_name = (string)$player_row['Handle'];

   //$rss_sep = "\n<br />";
   $rss_sep = "\n - ";

   $tit= "Status of $my_name";
   $lnk= HOSTBASE.'status.php';
   $dsc= "Messages and Games for $my_name";
   rss_open( $tit, $dsc, $lnk);


   $nothing_found = true;

   // Unread Bulletins

   if( $player_row['CountBulletinNew'] < 0 )
      Bulletin::update_count_bulletin_new( 'rss_status', $my_id, COUNTNEW_RECALC );

   if( $player_row['CountBulletinNew'] > 0 )
   {
      $iterator = new ListIterator( 'rss_status.bulletins.unread',
         new QuerySQL( SQLP_WHERE,
               "BR.bid IS NULL", // only unread
               "B.Status='".BULLETIN_STATUS_SHOW."'" ),
         'ORDER BY B.PublishTime DESC' );
      $iterator->addQuerySQLMerge( Bulletin::build_view_query_sql( /*adm*/false, /*count*/false ) );
      $iterator = Bulletin::load_bulletins( $iterator );

      if( $iterator->ResultRows > 0 )
         $nothing_found = false;

      $cat = 'Status/Bulletin';
      while( list(,$arr_item) = $iterator->getListIterator() )
      {
         list( $bulletin, $orow ) = $arr_item;

         $tit= sprintf( T_('Bulletin to %s with "%s"#rss'),
                        GuiBulletin::getTargetTypeText($bulletin->TargetType),
                        GuiBulletin::getCategoryText($bulletin->Category) );
         $lnk= HOSTBASE.'view_bulletin.php?bid='.$bulletin->ID;
         $dsc= sprintf( "%s: %s $rss_sep", T_('From#rss_bullauthor'),
                        user_reference( 0, 1, '', null, $bulletin->User->Name, $bulletin->User->Handle ) ) .
               T_('Subject#bulletin') . ": {$bulletin->Subject}";
         rss_item( $tit, $lnk, $dsc, $bulletin->PublishTime, $cat);
      }
   } // bulletins


   // New messages?

   $query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS date, me.mid, " .
      "Messages.Subject, Players.Name AS sender, Players.Handle AS sendhndl " .
      "FROM (Messages, MessageCorrespondents AS me) " .
         "LEFT JOIN MessageCorrespondents AS other ON other.mid=me.mid AND other.Sender!=me.Sender " .
         "LEFT JOIN Players ON Players.ID=other.uid " .
      "WHERE me.uid=$my_id AND me.Folder_nr=".FOLDER_NEW." " .
              "AND Messages.ID=me.mid " .
              "AND me.Sender IN('N','S') " . //exclude message to myself
      "ORDER BY Messages.Time, me.mid";

   $result = db_query( 'rss3', $query );

   $cat= 'Status/Message';
   while( $row = mysql_fetch_assoc($result) )
   {
      $nothing_found = false;
      $sendname = @$row['sender'];
      if( !$sendname )
         $sendname = '[Server message]';
      else
         $sendname.= " (".@$row['sendhndl'].")";

      $mid = (int)@$row['mid'];

      $tit= sprintf( T_('Message from %s#rss'), $sendname );
      $lnk= HOSTBASE.'message.php?mid='.$mid;
      $dat= @$row['date'];
      $dsc= sprintf( "%s: $mid $rss_sep", T_('Message#rss') ) .
            T_('From#rss_message') . ": $sendname $rss_sep" .
            T_('Subject#rss_message') . ": ".@$row['Subject'];
      rss_item( $tit, $lnk, $dsc, $dat, $cat);
   }


   // Games to play?
   $sql_order = NextGameOrder::get_next_game_order( @$player_row['NextGameOrder'], 'Games' ); // enum -> order

   $query = "SELECT Games.Status, UNIX_TIMESTAMP(Lastchanged) as date,Games.ID, " .
       "Games.Moves,(White_ID=$my_id)+0 AS Color, " .
       "Games.GameType, Games.GamePlayers, " .
       "opponent.Name, opponent.Handle " .
       "FROM (Games,Players AS opponent) " .
       "WHERE ToMove_ID=$my_id AND Status" . IS_STARTED_GAME .
         "AND opponent.ID=(Black_ID+White_ID-$my_id) " .
       $sql_order;

   $result = db_query( 'rss4', $query );
   $cat= 'Status/Game';
   $clrs="BW"; //player's color... so color to play.
   while( $row = mysql_fetch_assoc($result) )
   {
      $nothing_found = false;
      $opponame = @$row['Name'];
      $opponame.= " (".@$row['Handle'].")";

      $gid = (int)@$row['ID'];
      $move = (int)@$row['Moves'];
      $game_status = @$row['Status'];
      $game_type = GameTexts::format_game_type($row['GameType'], $row['GamePlayers']);
      if( $game_status == GAME_STATUS_KOMI )
          $game_type .= GameTexts::build_fairkomi_gametype($game_status, /*raw*/true);

      $tit= sprintf( T_('Game of %s with %s#rss'), $game_type, $opponame );
      $lnk= HOSTBASE.'game.php?gid='.$gid;
      $mov= $lnk.URI_AMP.'move='.$move;
      $dat= @$row['date'];
      $dsc= T_('Game#rss') . ": $gid $rss_sep" .
            T_('Opponent') . ": $opponame $rss_sep" .
            T_('Color') . ": ".$clrs{@$row['Color']} . $rss_sep .
            T_('Move') . ": ".$move;
      rss_item( $tit, $lnk, $dsc, $dat, $cat, $mov);
   }


   if( $nothing_found )
   {
      //rss_warning('empty lists', 'empty lists', HOSTBASE.'status.php');
      $tit= T_('Empty lists#rss');
      $lnk= HOSTBASE.'status.php';
      $dsc= T_('No new bulletins') . $rss_sep .
            T_('No awaiting games') . $rss_sep .
            T_('No awaiting messages');
      rss_item( $tit, $lnk, $dsc);
   }

   rss_close();
}
?>
