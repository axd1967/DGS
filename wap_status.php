<?php

define('ALLOW_AUTH',false);

if( ALLOW_AUTH )
{
   session_name('DGSwap');
   session_save_path('/tmp/persistent/dragongoserver/session');
   session_start();
   header ("Cache-control: private");
}

$quick_errors = 1;
function error($err, $debugmsg=NULL)
{
   global $uhandle;

   list( $err, $uri)= err_log( $uhandle, $err, $debugmsg);

   global $wap_opened;
   if( !$wap_opened )
      wap_open( 'ERROR');
   wap_error( $err);
   wap_close();
   exit;
}

putenv('TZ=GMT');
require_once( "include/quick_common.php" );

//require_once( "include/connect2mysql.php" );
//else ...
{//standalone version ==================
require_once( "include/config.php" );

function err_log( $handle, $err, $debugmsg=NULL)
{

   $uri = "error.php?err=" . urlencode($err);
   $errorlog_query = "INSERT INTO Errorlog SET Handle='".addslashes($handle)."', " .
      "Message='$err', IP='{$_SERVER['REMOTE_ADDR']}'" ;

   $mysqlerror = @mysql_error();

   if( !empty($mysqlerror) )
   {
      $uri .= "&amp;mysqlerror=" . urlencode($mysqlerror);
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
   //if( !empty($debugmsg) )
   {
      $errorlog_query .= ", Debug='" . addslashes( $debugmsg) . "'";
      //$err.= ' / '. $debugmsg; //Do not display this info!
   }

 global $dbcnx;
   if( !isset($dbcnx) )
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
      if( $no_errors ) return;
      error("mysql_connect_failed");
   }

   if (! @mysql_select_db($DB_NAME) )
   {
      if( $no_errors ) return;
      error("mysql_select_db_failed");
   }
}
}//standalone version ==================



function wap_safe( $str)
{
   return (string)@htmlentities( (string)$str, ENT_QUOTES);
}


function wap_date( $dat=0)
{
   if( !$dat )
   {
      global $NOW;
      $dat= $NOW;
   }
   return gmdate( 'D, d M Y H:i:s \G\M\T', $dat);
}


$wapid= 0;
function wap_id()
{
  global $wapid;
  $wapid++;
  return $wapid;
}


$wap_opened= false;
function wap_open( $title, $description='', $html_clone='', $cache_minutes=10)
{
   global $encoding_used, $HOSTBASE, $NOW; //$base_path

   if( !ALLOW_AUTH )
      ob_start("ob_gzhandler");
   global $wap_opened;
   $wap_opened= true;

   $last_modified_stamp= $NOW;

   //if( empty($encoding_used) )
      $encoding_used = 'iso-8859-1';

   if( empty($html_clone) )
      $html_clone = $HOSTBASE . '/';

   if( empty($description) )
      $description = $title;

   header('Content-Type: text/vnd.wap.wml; charset='.$encoding_used);

   echo "<?xml version=\"1.0\" encoding=\"$encoding_used\"?>\n";
   echo "<!DOCTYPE wml PUBLIC '-//WAPFORUM//DTD WML 1.1//EN' 'http://www.wapforum.org/DTD/wml_1.1.xml'>\n";

   echo "<wml>\n";
}


function wap_close( )
{
   echo "</wml>";
   if( !ALLOW_AUTH )
      ob_end_flush();
}


function wap_item( $id, $title, $link, $description='', $pubDate='')
{
   if( empty($description) )
      $description = $title;

   $str = "<card id=\"$id\" title=\"$title\">";
   if( $link )
   {
      $str.= "<p><a href=\"$link\">$title</a></p>";
   }
   else
   {
      $str.= "<p><b>$title</b></p>";
   }

   //if( $pubDate )
      $str.= "<p>" . wap_date($pubDate) . "</p>";

   $str.= "<p>$description</p>";

/*
   $str.= "<p><do type=\"prev\" label=\"back\"><prev/></do></p>";
*/
   $str.= "</card>\n";

   echo $str;
}


function wap_error( $str, $title='', $link='')
{
   if( !$link )
   {
      global $HOSTBASE;
      $link= $HOSTBASE.'/';
   }
   if( !$title )
      $title= 'ERROR';
   $str= wap_safe( $str);
   wap_item( 'E'.wap_id(), $title, $link, 'Error: '.$str);
}


function wap_warning( $str, $title='', $link='')
{
   if( !$link )
   {
      global $HOSTBASE;
      $link= $HOSTBASE.'/';
   }
   if( !$title )
      $title= 'WARNING';
   $str= wap_safe( $str);
   wap_item( 'W'.wap_id(), $title, $link, 'Warning: '.$str);
}


function wap_auth( $title, $uhandle='')
{
   //if( $uhandle ) $uhandle= ' - '.$uhandle; else
      $uhandle= '';

   global $wap_opened;
   if( !$wap_opened )
      wap_open( 'LOGIN');

   echo "<card id=\"login\" title=\"$title\">"
      ."<p>"
      ."user: <input name=\"userid\" size=\"10\" maxlength=\"16\" type=\"text\"/><br/>"
      ."pass: <input name=\"passwd\" size=\"10\" maxlength=\"16\" type=\"password\"/><br/>"
      ."</p>"
      ."<do type=\"accept\" label=\"login!\">"
      ."<go href=\"".@$_SERVER['PHP_SELF']."\" method=\"post\">"
      ."<postfield name=\"userid\" value=\"$(userid)\"/>"
      ."<postfield name=\"passwd\" value=\"$(passwd)\"/>"
      ."</go>"
      ."</do>"
      ."<do type=\"accept\" label=\"logout!\">"
      ."<go href=\"".@$_SERVER['PHP_SELF']."\" method=\"post\">"
      ."<postfield name=\"logout\" value=\"1\"/>"
      ."</go>"
      ."</do>"
      ."</card>";

   wap_close();
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
   wap_open( 'WARNING');
   wap_warning($is_down_message);
   wap_close();
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
      $uhandle = arg_stripslashes((string)@$_SESSION['AUTH_USER']);
      $passwd = arg_stripslashes((string)@$_SESSION['AUTH_PW']);
      $authid = get_request_arg('authid');
      if( @$_REQUEST['logout'] )
      {
         $_SESSION= array();
         session_destroy();
         $uhandle = '';
         $passwd = '';
         $loggin_mode = 'authenticate';
      }
      else if( $authid && $authid !== $uhandle )
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
            if( ALLOW_AUTH )
            {
               $_SESSION['AUTH_USER']= $uhandle;
               $_SESSION['AUTH_PW']= $passwd;
            }
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
         wap_auth( 'Register!', $uhandle);
      error("not_logged_in",'wap1');
   }


   if( !empty( $player_row["Timezone"] ) )
      putenv('TZ='.$player_row["Timezone"] );

   //+logging stat adjustments

   $my_id = (int)$player_row['ID'];
   $my_name = wap_safe( $player_row['Handle']);

   $wap_sep = "\n<br/>";

   $tit= "Status of $my_name";
   $lnk= $HOSTBASE.'/status.php';
   $dsc= "Messages and Games for $my_name";
   wap_open( $tit, $dsc, $lnk);


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

   $result = mysql_query( $query ) or error('mysql_query_failed','wap3');

   while( $row = mysql_fetch_assoc($result) )
   {
      $nothing_found = false;
      $safename = @$row['sender'];
      if( !$safename )
         $safename = '[Server message]';
      else
         $safename.= " (".@$row['sendhndl'].")";
      $safename = wap_safe( $safename);

      $safeid = (int)@$row['mid'];

      $card= 'M'.$safeid;
      $tit= "Message from $safename";
      $lnk= $HOSTBASE.'/message.php?mid='.$safeid;
      $dat= @$row['date'];
      $dsc= "Message: $safeid" . $wap_sep .
            //"Folder: ".FOLDER_NEW . $wap_sep .
            "From: $safename" . $wap_sep .
            "Subject: ".wap_safe( @$row['Subject']);
      wap_item( $card, $tit, $lnk, $dsc, $dat);
   }


   // Games to play?

   $query = "SELECT UNIX_TIMESTAMP(LastChanged) as date,Games.ID, " .
       "Games.Moves,(White_ID=$my_id)+0 AS Color, " .
       "opponent.Name, opponent.Handle " .
       "FROM Games,Players AS opponent " .
       "WHERE ToMove_ID=$my_id AND Status!='INVITED' AND Status!='FINISHED' " .
         "AND (opponent.ID=Black_ID OR opponent.ID=White_ID) AND opponent.ID!=$my_id " .
       "ORDER BY date, Games.ID";

   $result = mysql_query( $query ) or error('mysql_query_failed','wap4');

   $clrs="BW"; //player's color... so color to play.
   while( $row = mysql_fetch_assoc($result) )
   {
      $nothing_found = false;
      $safename = @$row['Name'];
         $safename.= " (".@$row['Handle'].")";
      $safename = wap_safe( $safename);

      $safeid = (int)@$row['ID'];
      $move = (int)@$row['Moves'];

      $card= 'G'.$safeid;
      $tit= "Game with $safename";
      $lnk= $HOSTBASE.'/game.php?gid='.$safeid;
      $dat= @$row['date'];
      $dsc= "Game: $safeid" . $wap_sep .
            "Opponent: $safename" . $wap_sep .
            "Color: ".$clrs{@$row['Color']} . $wap_sep .
            "Move: ".$move;
      wap_item( $card, $tit, $lnk, $dsc, $dat);
   }

    
   if( $nothing_found )
   {
      wap_warning('nothing found', 'nothing found', $HOSTBASE.'/status.php');
   }

   wap_close();
}
?>