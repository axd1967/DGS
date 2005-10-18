<?php

define('ALLOW_AUTH',true);

$quick_errors = 1;
function error($err, $debugmsg=NULL)
{
   global $uhandle;

   $title= str_replace('_',' ',$err);
   list( $err, $uri)= err_log( $uhandle, $err, $debugmsg);

   global $rss_opened;
   if( !$rss_opened )
      rss_open( 'ERROR');
   rss_error( $err, $title);
   rss_close();
   exit;
}

putenv('TZ=GMT');
require_once( "include/quick_common.php" );

//require_once( "include/connect2mysql.php" );
//else ...
{//standalone version ==================
require_once( "include/config.php" );
if( @URI_AMP=='URI_AMP' ) define('URI_AMP','&amp;');

function err_log( $handle, $err, $debugmsg=NULL)
{

   $uri = "error.php?err=" . urlencode($err);
   $errorlog_query = "INSERT INTO Errorlog SET Handle='".addslashes($handle)."', " .
      "Message='$err', IP='{$_SERVER['REMOTE_ADDR']}'" ;

   $mysqlerror = @mysql_error();

   if( !empty($mysqlerror) )
   {
      $uri .= URI_AMP."mysqlerror=" . urlencode($mysqlerror);
      $errorlog_query .= ", MysqlError='".addslashes( $mysqlerror)."'";
      $err.= ' / '. $mysqlerror;
   }

   
   if( empty($debugmsg) )
   {
    global $SUB_PATH;
      $debugmsg = @$_SERVER['REQUEST_URI']; //@$_SERVER['PHP_SELF'];
      //$debugmsg = str_replace( $SUB_PATH, '', $debugmsg);
      $debugmsg = substr( $debugmsg, strlen($SUB_PATH));
   }
   if( !empty($debugmsg) )
   {
      $errorlog_query .= ", Debug='" . addslashes( $debugmsg) . "'";
      //$err.= ' / '. $debugmsg; //Do not display this info!
   }

   global $dbcnx;
   if( !@$dbcnx )
      connect2mysql( true);

   @mysql_query( $errorlog_query );

   return array( $err, $uri);
}

function disable_cache($stamp=NULL)
{
   global $NOW;
  // Force revalidation
   header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
   header('Cache-Control: no-store, no-cache, must-revalidate, max_age=0'); // HTTP/1.1
   header('Pragma: no-cache');                                              // HTTP/1.0
   if( !$stamp )
      header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $NOW) . ' GMT');  // Always modified
   else
      header('Last-Modified: ' . gmdate('D, d M Y H:i:s',$stamp) . ' GMT');
}

function connect2mysql($no_errors=false)
{
   global $dbcnx, $MYSQLUSER, $MYSQLHOST, $MYSQLPASSWORD, $DB_NAME;

   $dbcnx = @mysql_connect( $MYSQLHOST, $MYSQLUSER, $MYSQLPASSWORD);

   if (!$dbcnx)
   {
      if( $no_errors ) return false;
      error("mysql_connect_failed");
   }

   if (! @mysql_select_db($DB_NAME) )
   {
      mysql_close( $dbcnx);
      $dbcnx= 0;
      if( $no_errors ) return false;
      error("mysql_select_db_failed");
   }

   return true;
}
}//standalone version ==================



function rss_safe( $str)
{
   return (string)@htmlentities( (string)$str, ENT_QUOTES);
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


$rss_opened= false;
function rss_open( $title, $description='', $html_clone='', $cache_minutes=10)
{
   global $encoding_used, $HOSTBASE, $NOW;

   ob_start("ob_gzhandler");
   global $rss_opened;
   $rss_opened= true;

   $last_modified_stamp= $NOW;

   //if( empty($encoding_used) )
      $encoding_used = 'iso-8859-1';

   if( empty($html_clone) )
      $html_clone = $HOSTBASE . '/';

   if( empty($description) )
      $description = $title;

   header('Content-Type: text/xml; charset='.$encoding_used);

   echo "<?xml version=\"1.0\" encoding=\"$encoding_used\"?>\n";
   echo "<rss version=\"2.0\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\""
        . " xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">\n";
   echo " <channel>\n"
      . "  <title>Dragon Go Server - $title</title>\n"
      . "  <link>$html_clone</link>\n"
      . "  <pubDate>" . rss_date($last_modified_stamp) . "</pubDate>"
      . ( is_numeric( $cache_minutes) ? "  <ttl>$cache_minutes</ttl>\n" : '' )
      . "  <language>en-us</language>"
      . "  <description>$description</description>\n"
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

   $str = "  <item>\n"
        . "   <title>$title</title>\n";
   if( $link )
      $str.= "   <link>$link</link>\n";
   if( $guid )
      $str.= "   <guid>$guid</guid>\n";
   if( $category )
      $str.= "   <category>$category</category>\n";
   //if( $pubDate )
      $str.= "   <pubDate>" . rss_date($pubDate) . "</pubDate>\n";
   $str.= "   <description>$description</description>\n"
        . "  </item>\n";

   echo $str;
}


function rss_error( $str, $title='', $link='')
{
   if( !$link )
   {
      global $HOSTBASE;
      $link= $HOSTBASE.'/';
   }
   if( !$title )
      $title= 'ERROR';
   $str= rss_safe( $str);
   rss_item( $title, $link, 'Error: '.$str);
}


function rss_warning( $str, $title='', $link='')
{
   if( !$link )
   {
      global $HOSTBASE;
      $link= $HOSTBASE.'/';
   }
   if( !$title )
      $title= 'WARNING';
   $str= rss_safe( $str);
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

   header("WWW-Authenticate: Basic realm=\"Dragon Go Server$uhandle\"");
   header('HTTP/1.0 401 Unauthorized');

   //echo "$cancel_str\n";
   rss_open( 'ERROR');
   rss_error( $cancel_str);
   rss_close();
   exit;
}


function check_password( $uhandle, $passwd, $new_passwd, $given_passwd )
{
   $given_passwd_encrypted =
     mysql_fetch_row( mysql_query( "SELECT PASSWORD ('".addslashes($given_passwd)."')" ) );
   $given_passwd_encrypted = $given_passwd_encrypted[0];

   if( $passwd != $given_passwd_encrypted )
   {
      // Check if there is a new password

      if( empty($new_passwd) or $new_passwd != $given_passwd_encrypted )
         return false;
   }

   if( !empty( $new_passwd ) )
   {
      mysql_query( 'UPDATE Players ' .
                   "SET Password='" . $given_passwd_encrypted . "', " .
                   'Newpassword=NULL ' .
                   "WHERE Handle='".addslashes($uhandle)."' LIMIT 1" );
   }

   return true;
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
      $uhandle= @$_COOKIE[COOKIE_PREFIX.'handle'];
      $loggin_mode = 'cookie';
   }


   disable_cache();

   connect2mysql();

   if( $loggin_mode=='password' )
   {
      // temp password?

      $result = @mysql_query( "SELECT *, UNIX_TIMESTAMP(Sessionexpire) AS Expire ".
                "FROM Players WHERE Handle='".addslashes($uhandle)."'" );

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
                          "FROM Players WHERE Handle='".addslashes($uhandle)."'" );

      if( @mysql_num_rows($result) == 1 )
      {
         $player_row = mysql_fetch_assoc($result);

         if( $player_row['Sessioncode'] === @$_COOKIE[COOKIE_PREFIX.'sessioncode']
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


   if( !empty( $player_row["Timezone"] ) )
      putenv('TZ='.$player_row["Timezone"] );

   //+logging stat adjustments

   $my_id = (int)$player_row['ID'];
   $my_name = rss_safe( $player_row['Handle']);

   $rss_sep = "\n - ";

   $tit= "Status of $my_name";
   $lnk= $HOSTBASE.'/status.php';
   $dsc= "Messages and Games for $my_name";
   rss_open( $tit, $dsc, $lnk);


   $nothing_found = true;

   // New messages?

   $query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS date, me.mid, " .
      "Messages.Subject, Players.Name AS sender, Players.Handle AS sendhndl " .
      "FROM Messages, MessageCorrespondents AS me " .
      "LEFT JOIN MessageCorrespondents AS other " .
        "ON other.mid=me.mid AND other.Sender!=me.Sender " .
      "LEFT JOIN Players ON Players.ID=other.uid " .
      "WHERE me.uid=$my_id AND me.Folder_nr=".FOLDER_NEW." " .
              "AND Messages.ID=me.mid " .
              "AND me.Sender='N' " . //exclude message to myself
      "ORDER BY date, me.mid";

   $result = mysql_query( $query ) or error('mysql_query_failed','rss3');

   $cat= 'Status/Message';
   while( $row = mysql_fetch_assoc($result) )
   {
      $nothing_found = false;
      $safename = @$row['sender'];
      if( !$safename )
         $safename = '[Server message]';
      else
         $safename.= " (".@$row['sendhndl'].")";
      $safename = rss_safe( $safename);

      $safeid = (int)@$row['mid'];

      $tit= "Message from $safename";
      $lnk= $HOSTBASE.'/message.php?mid='.$safeid;
      $dat= @$row['date'];
      $dsc= "Message: $safeid" . $rss_sep .
            //"Folder: ".FOLDER_NEW . $rss_sep .
            "From: $safename" . $rss_sep .
            "Subject: ".rss_safe( @$row['Subject']);
      rss_item( $tit, $lnk, $dsc, $dat, $cat);
   }


   // Games to play?

   $query = "SELECT UNIX_TIMESTAMP(LastChanged) as date,Games.ID, " .
       "Games.Moves,(White_ID=$my_id)+0 AS Color, " .
       "opponent.Name, opponent.Handle " .
       "FROM Games,Players AS opponent " .
       "WHERE ToMove_ID=$my_id AND Status!='INVITED' AND Status!='FINISHED' " .
         "AND (opponent.ID=Black_ID OR opponent.ID=White_ID) AND opponent.ID!=$my_id " .
       "ORDER BY date, Games.ID";

   $result = mysql_query( $query ) or error('mysql_query_failed','rss4');

   $cat= 'Status/Game';
   $clrs="BW"; //player's color... so color to play.
   while( $row = mysql_fetch_assoc($result) )
   {
      $nothing_found = false;
      $safename = @$row['Name'];
         $safename.= " (".@$row['Handle'].")";
      $safename = rss_safe( $safename);

      $safeid = (int)@$row['ID'];
      $move = (int)@$row['Moves'];

      $tit= "Game with $safename";
      $lnk= $HOSTBASE.'/game.php?gid='.$safeid;
      $mov= $lnk.URI_AMP.'move='.$move;
      $dat= @$row['date'];
      $dsc= "Game: $safeid" . $rss_sep .
            "Opponent: $safename" . $rss_sep .
            "Color: ".$clrs{@$row['Color']} . $rss_sep .
            "Move: ".$move;
      rss_item( $tit, $lnk, $dsc, $dat, $cat, $mov);
   }

    
   if( $nothing_found )
   {
      rss_warning('nothing found', 'nothing found', $HOSTBASE.'/status.php');
   }

   rss_close();
}
?>