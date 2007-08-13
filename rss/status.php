<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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


chdir("../");

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

require_once( "include/quick_common.php" );
require_once( "include/connect2mysql.php" );

$TheErrors->set_mode(ERROR_MODE_PRINT);


/* For a default layout:

Beginning to Style Your RSS Feed
- http://mondaybynoon.com/2006/08/14/beginning-to-style-your-rss-feed/

Adding a CSS StyleSheet to your RSS Feed
- http://www.petefreitag.com/item/208.cfm

Some examples:
- http://mondaybynoon.com/feed
- http://www.tlc.ac.uk/xml/news-atom.xml
*/



/*
   Special characters and different charset are a worry with xml+rss.
   We have not yet found the good solution.
*/

/*
$xmltrans = get_html_translation_table(HTML_ENTITIES, ENT_QUOTES); //ENT_COMPAT
foreach ($xmltrans as $key => $value)
   $xmltrans[$key] = '&#'.ord($key).';';
*/
$xmltrans = array();
for ( $i=1; $i<0x20 ; $i++ )
   $xmltrans[chr($i)] = ''; //"&#$i;";
unset( $xmltrans["\t"]);
unset( $xmltrans["\n"]);

// XML only supports these entities: &amp; &lt; &gt; &quot;
//  but they must be used in text fields 
// see also <![CDATA[#]]> for particular cases. 
$xmltrans['&'] = '&amp;';
$xmltrans['<'] = '&lt;';
$xmltrans['>'] = '&gt;';
$xmltrans['"'] = '&quot;';

$xmltrans[']'] = '&#'.ord(']').';';
//XML seems to not like some chars sequences (like "'$$'")
$xmltrans['\'$'] = '&quot;$';
$xmltrans['$\''] = '$&quot;';
/*
$xmltrans['\''] = '\\\'';
$xmltrans['$'] = '\$';
*/
//$xmltrans['\''] = '&#'.ord('\'').';';
      
switch( CHARSET_MODE )
{
   case 'iso':
   {
      $encoding_used = 'iso-8859-1';

      for ( $i=0x80; $i<0x100 ; $i++ )
         $xmltrans[chr($i)] = "&#$i;";
   }
   break;
   case 'utf':
   {
      $encoding_used = 'UTF-8';

      for ( $i=0x80; $i<0x100 ; $i++ )
         $xmltrans[chr($i)] = "&#$i;";
   }
   break;
   default:
      error('internal_error', 'rss.no_charset');
}


// can't use html_entity_decode() because of the '&nbsp;' below:
//HTML_SPECIALCHARS or HTML_ENTITIES, ENT_COMPAT or ENT_QUOTES or ENT_NOQUOTES 
$reverse_htmlentities_table= get_html_translation_table(HTML_ENTITIES, ENT_QUOTES);
$reverse_htmlentities_table= array_flip($reverse_htmlentities_table);
$reverse_htmlentities_table['&nbsp;'] = ' '; //else may be '\xa0' as with html_entity_decode()
function reverse_htmlentities( $str)
{
   //return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
 global $reverse_htmlentities_table;
   return strtr($str, $reverse_htmlentities_table);
}


// could not be called twice on the same string
function rss_safe( $str)
{
   $str = reverse_htmlentities( $str);
 global $xmltrans;
   //XML seems to not like some chars sequances (like "'$$'")
   return '<![CDATA[' . strtr($str, $xmltrans) . ']]>'; 
   //return strtr($str, $xmltrans);
/*
   //XML only supports these entities: &amp; &lt; &gt; &quot;
   return str_replace(
      array( '&', '"', '<', '>'),
      array( '&amp;', '&quot;', '&lt;', '&gt;'),
      $str );
*/
}

// could not be called twice on the same string
function rss_link( $lnk)
{
   //XML does not support some URL chars (like '#')
   return '<![CDATA[' . $lnk . ']]>'; 
}


function rss_date( $dat=0)
{
   if( !$dat )
   {
      global $NOW;
      $dat= $NOW;
   }
   return gmdate( 'D, d M Y H:i:s \G\M\T', $dat);
}

// $tag must be valid and $str must be safe
function rss_tag( $tag, $str)
{
   return "<$tag>$str</$tag>\n";
}


$rss_opened= false;
function rss_open( $title, $description='', $html_clone='', $cache_minutes= CACHE_MIN)
{
   global $encoding_used, $HOSTBASE, $FRIENDLY_LONG_NAME, $NOW;

   ob_start("ob_gzhandler");
   global $rss_opened;
   $rss_opened= true;

   $last_modified_stamp= $NOW;

   if( empty($encoding_used) )
      $encoding_used = 'UTF-8'; //really better for RSS feeders
      //$encoding_used = 'iso-8859-1';

   if( empty($html_clone) )
      $html_clone = $HOSTBASE;

   if( empty($description) )
      $description = $title;

   header('Content-Type: text/xml; charset='.$encoding_used);

   echo "<?xml version=\"1.0\" encoding=\"$encoding_used\"?>\n";
   echo "<rss version=\"2.0\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\""
        . " xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">\n";
   echo "<!--

If you're seeing this, you've clicked on the link 
for $FRIENDLY_LONG_NAME Quick RSS feed.  
This file is not meant to be read by a web 
browser directly.  Instead you're meant to copy 
the URL for the file, which is:

  {$HOSTBASE}rss/status.php

and paste it into your RSS reader or podcast program.

-->\n";

/*
If you need to know more about how to do this, 
please go to the following web pages to learn 
about RSS services.
*/

   $title = $FRIENDLY_LONG_NAME.' - '.$title;
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
   {
      global $HOSTBASE;
      $link= $HOSTBASE;
   }
   if( !$title )
      $title= 'ERROR';
   else
      $title= 'ERROR: '.$title;

   rss_item( $title, $link, 'ERROR: '.$str);
}


function rss_warning( $str, $title='', $link='')
{
   if( !$link )
   {
      global $HOSTBASE;
      $link= $HOSTBASE;
   }
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
   global $FRIENDLY_LONG_NAME;
   //if( $uhandle ) $uhandle= ' - '.$uhandle; else
      $uhandle= '';
   $uhandle= $FRIENDLY_LONG_NAME . $uhandle;

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
   {
      $loggin_mode = 'password';
   }
   else if( ALLOW_AUTH )
   {
      $uhandle = arg_stripslashes((string)@$_SERVER['PHP_AUTH_USER']);
      $passwd = arg_stripslashes((string)@$_SERVER['PHP_AUTH_PW']);
      $authid = get_request_arg('authid');
      if( $authid && $authid !== $uhandle )
      {
         $uhandle = $authid;
         $passwd = '';
         $loggin_mode = 'authenticate';
      }
      else if( $uhandle && $passwd )
      {
         $loggin_mode = 'password';
      }
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

      $result = @mysql_query( "SELECT *, UNIX_TIMESTAMP(Sessionexpire) AS Expire ".
                "FROM Players WHERE Handle='".mysql_addslashes($uhandle)."'" );

      if( @mysql_num_rows($result) == 1 )
      {
         $player_row = mysql_fetch_array($result);

         if( check_password( $uhandle, $player_row["Password"],
                              $player_row["Newpassword"], $passwd ) )
         {
            $logged_in = true;
         }
         else error("wrong_password");
      }
      //else error("wrong_userid");
   }

   if( $loggin_mode=='cookie' )
   {
      // logged in?

      $result = @mysql_query( "SELECT *, UNIX_TIMESTAMP(Sessionexpire) AS Expire ".
                          "FROM Players WHERE Handle='".mysql_addslashes($uhandle)."'" );

      if( @mysql_num_rows($result) == 1 )
      {
         $player_row = mysql_fetch_assoc($result);

         if( $player_row['Sessioncode'] === safe_getcookie('sessioncode')
             && $player_row["Expire"] >= $NOW )
         {
            $logged_in = true;
         }
      }
   }

   if( !$logged_in )
   {
      if( ALLOW_AUTH ) //or $loggin_mode=='authenticate'
         rss_auth( 'Unauthorized access forbidden!', $uhandle);
      error("not_logged_in",'rss1');
   }


   setTZ( $player_row['Timezone']);

   //+logging stat adjustments

   $my_id = (int)$player_row['ID'];
   $my_name = (string)$player_row['Handle'];

   //$rss_sep = "\n<br />";
   $rss_sep = "\n - ";

   $tit= "Status of $my_name";
   $lnk= $HOSTBASE.'status.php';
   $dsc= "Messages and Games for $my_name";
   rss_open( $tit, $dsc, $lnk);


   $nothing_found = true;

   // New messages?

   $query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS date, me.mid, " .
      "Messages.Subject, Players.Name AS sender, Players.Handle AS sendhndl " .
      "FROM (Messages, MessageCorrespondents AS me) " .
      "LEFT JOIN MessageCorrespondents AS other " .
        "ON other.mid=me.mid AND other.Sender!=me.Sender " .
      "LEFT JOIN Players ON Players.ID=other.uid " .
      "WHERE me.uid=$my_id AND me.Folder_nr=".FOLDER_NEW." " .
              "AND Messages.ID=me.mid " .
              "AND me.Sender IN('N','S') " . //exclude message to myself
      "ORDER BY date, me.mid";

   $result = mysql_query( $query )
      or error('mysql_query_failed','rss3');

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

      $tit= "Message from $sendname";
      $lnk= $HOSTBASE.'message.php?mid='.$mid;
      $dat= @$row['date'];
      $dsc= "Message: $mid" . $rss_sep .
            //"Folder: ".FOLDER_NEW . $rss_sep .
            "From: $sendname" . $rss_sep .
            "Subject: ".@$row['Subject'];
      rss_item( $tit, $lnk, $dsc, $dat, $cat);
   }


   // Games to play?

   $query = "SELECT UNIX_TIMESTAMP(LastChanged) as date,Games.ID, " .
       "Games.Moves,(White_ID=$my_id)+0 AS Color, " .
       "opponent.Name, opponent.Handle " .
       "FROM Games,Players AS opponent " .
       "WHERE ToMove_ID=$my_id AND Status" . IS_RUNNING_GAME .
         "AND opponent.ID=(Black_ID+White_ID-$my_id) " .
       "ORDER BY date, Games.ID";

   $result = mysql_query( $query ) or error('mysql_query_failed','rss4');

   $cat= 'Status/Game';
   $clrs="BW"; //player's color... so color to play.
   while( $row = mysql_fetch_assoc($result) )
   {
      $nothing_found = false;
      $opponame = @$row['Name'];
         $opponame.= " (".@$row['Handle'].")";

      $gid = (int)@$row['ID'];
      $move = (int)@$row['Moves'];

      $tit= "Game with $opponame";
      $lnk= $HOSTBASE.'game.php?gid='.$gid;
      $mov= $lnk.URI_AMP.'move='.$move;
      $dat= @$row['date'];
      $dsc= "Game: $gid" . $rss_sep .
            "Opponent: $opponame" . $rss_sep .
            "Color: ".$clrs{@$row['Color']} . $rss_sep .
            "Move: ".$move;
      rss_item( $tit, $lnk, $dsc, $dat, $cat, $mov);
   }

    
   if( $nothing_found )
   {
      //rss_warning('empty lists', 'empty lists', $HOSTBASE.'status.php');
      $tit= "Empty lists";
      $lnk= $HOSTBASE.'status.php';
      $dsc= "No awaiting games" . $rss_sep .
            "No awaiting messages";
      rss_item( $tit, $lnk, $dsc);
   }

   rss_close();
}
?>
