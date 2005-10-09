<?php

$quick_errors = 1;
require_once( "include/quick_common.php" );
$date_fmt = 'Y-m-d H:i';
require_once( "include/connect2mysql.php" );


function rss_safe( $str)
{
   return (string)@htmlentities( (string)$str, ENT_QUOTES);
}


function rss_open( $title, $description='', $html_clone='', $cache_minutes=10)
{
   global $encoding_used, $HOSTBASE, $NOW, $date_fmt; //$base_path

   ob_start("ob_gzhandler");

   $last_modified_stamp= $NOW;

   //if( empty($encoding_used) )
      $encoding_used = 'iso-8859-1';

   if( empty($html_clone) )
      $html_clone = $HOSTBASE . '/';

   if( empty($description) )
      $description = $title;

   header('Content-Type: text/xml; charset='.$encoding_used); // Character-encoding

   echo "<?xml version=\"1.0\" encoding=\"$encoding_used\"?>\n"
      . "<rss version=\"2.0\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\""
        . " xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">\n"
      . " <channel>\n"
      . "  <title>Dragon Go Server - $title</title>\n"
      . "  <link>$html_clone</link>\n"
      . "  <pubDate>" . date($date_fmt, $last_modified_stamp) . "</pubDate>"
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


function rss_item( $title, $link, $description='', $category='', $pubDate='', $guid='')
{
   if( empty($description) )
      $description = $title;
   if( empty($guid) )
      $guid = $link;

   $str = "  <item>\n"
        . "   <title>$title</title>\n";
   //if( $link )
      $str.= "   <link>$link</link>\n"
           . "   <guid>$guid</guid>\n";
   if( $category )
      $str.= "   <category>$category</category>\n";
   if( $pubDate )
      $str.= "   <pubDate>$pubDate</pubDate>\n";
   $str.= "   <description>$description</description>\n"
        . "  </item>\n";

   echo $str;
}


function rss_error( $str) //+error report
{
   rss_item( 'ERROR', '', 'Error: '.rss_safe( $str));
}


function rss_warning( $str)
{
   rss_item( 'WARNING', '', 'Warning: '.rss_safe( $str));
}


/*
   header('WWW-Authenticate: Digest realm="'.$realm.'", qop="auth", nonce="'
   .uniqid("55").'", opaque="'.md5($realm).'"');
*/
function http_auth( $cancel_str, $uhandle='')
{
   //if( $uhandle ) $uhandle= ' - '.$uhandle; else
      $uhandle= '';

   header("WWW-Authenticate: Basic realm=\"Dragon Go Server$userid\"");
   header('HTTP/1.0 401 Unauthorized');

   //echo "$cancel_str\n";
   rss_open( 'ERROR');
   rss_error($cancel_str);
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
   rss_warning($is_down_message);
   rss_close();
}
else
{
   disable_cache();

   connect2mysql();

   $html_clone = $HOSTBASE . '/status.php';

   $logged_in = false;
   $allow_auth = true;


   $uhandle = get_request_arg('userid');
   $passwd = get_request_arg('passwd');
   if( $allow_auth && !$uhandle )
   {
      $uhandle = arg_stripslashes((string)@$_SERVER['PHP_AUTH_USER']);
      $passwd = arg_stripslashes((string)@$_SERVER['PHP_AUTH_PW']);
      $authid = get_request_arg('authid');
      if( $authid && $authid !== $uhandle )
      {
         $uhandle = $authid;
         $passwd = '';
      }
   }

   if( !$logged_in && $uhandle && $passwd )
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
         //else error("wrong_password"); //+error report
      }
      //else error("wrong_userid"); //+error report
   }

   if( !$logged_in && !$uhandle )
   {
      // logged in?

      $uhandle= @$_COOKIE[COOKIE_PREFIX.'handle'];

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
      if( $allow_auth ) //+error report
         http_auth( 'Unauthorized access forbidden!', $uhandle);
      error("not_logged_in",'rss1');
   }


   if( !empty( $player_row["Timezone"] ) )
      putenv('TZ='.$player_row["Timezone"] );

   //+logging stat adjustments

   $my_id = (int)$player_row['ID'];
   $my_name = rss_safe( $player_row['Handle']);

   $rss_nl = "\n<br />";

   $tit= "Status of $my_name";
   $lnk= $HOSTBASE.'/status.php';
   $dsc= "Messages and Games for $my_name";
   rss_open( $tit, $dsc, $lnk);


   $nothing_found = true;

   // New messages?

   $query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS date, me.mid, " .
      "Messages.Subject, Players.Name AS sender " .
      "FROM Messages, MessageCorrespondents AS me " .
      "LEFT JOIN MessageCorrespondents AS other " .
        "ON other.mid=me.mid AND other.Sender!=me.Sender " .
      "LEFT JOIN Players ON Players.ID=other.uid " .
      "WHERE me.uid=$my_id AND me.Folder_nr=".FOLDER_NEW." " .
              "AND Messages.ID=me.mid " .
              "AND me.Sender='N' " . //exclude message to myself
      "ORDER BY date DESC";

   $result = mysql_query( $query ) or error('mysql_query_failed','rss3');

   $cat= 'Status/Message';
   while( $row = mysql_fetch_assoc($result) )
   {
      $nothing_found = false;
      $safename = @$row['sender'];
      if( !$safename )
         $safename = '[Server message]';
      $safename = rss_safe( $safename);

      $safeid = (int)@$row['mid'];

      $tit= "Message from $safename";
      $lnk= $HOSTBASE.'/message.php?mid='.$safeid;
      $dat= date('Y-m-d H:i', (int)@$row['date']);
      $dsc= "Message: $safeid" . $rss_nl .
            //"Folder: ".FOLDER_NEW . $rss_nl .
            "From: $safename" . $rss_nl .
            "Subject: ".rss_safe( @$row['Subject']);
      rss_item( $tit, $lnk, $dsc, $cat, $dat);
   }


   // Games to play?

   $query = "SELECT Black_ID,White_ID,Games.ID, (White_ID=$my_id)+0 AS Color, " .
       "UNIX_TIMESTAMP(LastChanged) as date,Games.Moves, " .
       "opponent.Name, opponent.Handle, opponent.ID AS pid " .
       "FROM Games,Players AS opponent " .
       "WHERE ToMove_ID=$my_id AND Status!='INVITED' AND Status!='FINISHED' " .
         "AND (opponent.ID=Black_ID OR opponent.ID=White_ID) AND opponent.ID!=$my_id " .
       "ORDER BY date DESC, Games.ID";

   $result = mysql_query( $query ) or error('mysql_query_failed','rss4');

   $cat= 'Status/Game';
   $clrs="BW"; //player's color... so color to play.
   while( $row = mysql_fetch_assoc($result) )
   {
      $nothing_found = false;
      $safename = @$row['Name'];
      $safename = rss_safe( $safename);

      $safeid = (int)@$row['ID'];
      $move = (int)@$row['Moves'];

      $tit= "Game with $safename";
      $lnk= $HOSTBASE.'/game.php?gid='.$safeid;
      $mov= $lnk.URI_AMP.'move='.$move;
      $dat= date('Y-m-d H:i', (int)@$row['date']);
      $dsc= "Game: $safeid" . $rss_nl .
            "Opponent: $safename" . $rss_nl .
            "Color: ".$clrs{@$row['Color']} . $rss_nl .
            "Move: ".$move;
      rss_item( $tit, $lnk, $dsc, $cat, $dat, $mov);
   }

    
   if( $nothing_found )
   {
      rss_warning("nothing found");
   }

   rss_close();
}
?>