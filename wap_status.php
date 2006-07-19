<?php

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

require_once( "include/quick_common.php" );

//require_once( "include/connect2mysql.php" );
//else ...
{//standalone version ==================
require_once( "include/config.php" );
if( @URI_AMP=='URI_AMP' ) define('URI_AMP','&amp;');

function err_log( $handle, $err, $debugmsg=NULL)
{

   $uri = "error.php?err=" . urlencode($err);
   $ip = (string)@$_SERVER['REMOTE_ADDR'];
   $errorlog_query = "INSERT INTO Errorlog SET Handle='".addslashes($handle)."'"
      .", Message='".addslashes($err)."', IP='".addslashes($ip)."'" ;

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

function disable_cache($stamp=NULL, $expire=NULL)
{
   global $NOW;
   if( !$stamp )
      $stamp = $NOW;  // Always modified
   if( !$expire )
      $expire = $stamp-3600;  // Force revalidation

   header('Expires: ' . gmdate('D, d M Y H:i:s',$expire) . ' GMT');
   header('Last-Modified: ' . gmdate('D, d M Y H:i:s',$stamp) . ' GMT');
   header('Cache-Control: no-store, no-cache, must-revalidate, max_age=0'); // HTTP/1.1
   header('Pragma: no-cache');                                              // HTTP/1.0
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
   return date( 'Y-m-d H:i', $dat);
}


$wapid= 0;
function wap_id()
{
  global $wapid;
  $wapid++;
  return $wapid;
}


$wap_opened= false;
function wap_open( $title)
{
   global $encoding_used, $HOSTBASE, $NOW;

   ob_start("ob_gzhandler");
   global $wap_opened;
   $wap_opened= true;

   //if( empty($encoding_used) )
      $encoding_used = 'iso-8859-1';

   if( empty($html_clone) )
      $html_clone = $HOSTBASE . '/';

   if( empty($description) )
      $description = $title;

   header('Content-Type: text/vnd.wap.wml; charset='.$encoding_used);

   echo "<?xml version=\"1.0\" encoding=\"$encoding_used\"?>\n";
   echo "<!DOCTYPE wml PUBLIC '-//WAPFORUM//DTD WML 1.2//EN' 'http://www.wapforum.org/DTD/wml_1.2.xml'>\n";

   echo "<wml>\n";
}


function wap_close( )
{
   echo "</wml>";
   ob_end_flush();
}


function wap_item( $cardid, $head, $title, $link='', $description='', $pubDate='', $nextid='', $previd='')
{
   $str = "<card id=\"$cardid\" title=\"$head\">";

   if( $previd )
      $str.= " <a accesskey=\"p\" href=\"#$previd\">[&lt;Prev]</a>";

   if( $link )
      $str.= " <a accesskey=\"g\" href=\"$link\">[Go]</a>";
   //$str.= "<p><do type=\"prev\" label=\"back\"><prev/></do></p>";
   //$str.= "<do type=\"prev\" label=\"back\"><prev/></do>";

   if( $nextid )
      $str.= " <a accesskey=\"n\" href=\"#$nextid\">[Next&gt;]</a>";

   $str.= "<br/>";


   $str.= "<p><b>$title</b></p>";

   //if( $pubDate )
      $str.= "<p>" . wap_date($pubDate) . "</p>";

   if( !empty($description) )
      $str.= "<p>$description</p>";

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
   wap_item( 'E'.wap_id(), 'Error', $title, $link, 'Error: '.$str);
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
   wap_item( 'W'.wap_id(), 'Warning', $title, $link, 'Warning: '.$str);
}


function wap_auth( $defid='', $defpw='')
{
   $str= "<p>"
      ."user: <input name=\"userid\" size=\"10\" maxlength=\"16\" value=\"$defid\" type=\"text\"/><br/>"
      ."pass: <input name=\"passwd\" size=\"10\" maxlength=\"16\" value=\"$defpw\" type=\"password\"/><br/>"
      ."</p>"
      ."<do type=\"accept\" label=\"login\">"
      ."<go href=\"".@$_SERVER['PHP_SELF']."\" method=\"post\">"
      ."<postfield name=\"userid\" value=\"\$(userid)\"/>"
      ."<postfield name=\"passwd\" value=\"\$(passwd)\"/>"
      ."</go>"
      ."</do>"
      ."<do type=\"accept\" label=\"logout\">"
      ."<go href=\"".@$_SERVER['PHP_SELF']."\" method=\"post\">"
      ."<postfield name=\"logout\" value=\"1\"/>"
      ."</go>"
      ."</do>"
      ;
   return $str;

/*
   wap_close();
   exit;
*/
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
   wap_warning( $is_down_message);
   wap_close();
}
else
{

   $logged_in = false;
   $loggin_mode = '';
   if( @$_REQUEST['logout'] )
   {
      $uhandle = '';
      $passwd = '';
   }
   else
   {
      $uhandle = get_request_arg('userid');
      $passwd = get_request_arg('passwd');
      if( $uhandle && $passwd )
      {
         $loggin_mode = 'password';
      }
      else if( !$uhandle && !$passwd )
      {
         $uhandle= @$_COOKIE[COOKIE_PREFIX.'handle'];
         $loggin_mode = 'cookie';
      }
   }


   disable_cache();

   connect2mysql();

   if( $loggin_mode )
   {
      $result = @mysql_query( "SELECT *, UNIX_TIMESTAMP(Sessionexpire) AS Expire ".
                          "FROM Players WHERE Handle='".addslashes($uhandle)."'" );

      if( @mysql_num_rows($result) == 1 )
      {
         $player_row = mysql_fetch_assoc($result);

         setTZ( $player_row['Timezone']);

         if( $loggin_mode=='password' )
         {
            if( check_password( $uhandle, $player_row["Password"],
                                 $player_row["Newpassword"], $passwd ) )
            {
               $logged_in = true;
            }
            else error("wrong_password");
         }
         else //$loggin_mode=='cookie'
         {
            if( $player_row['Sessioncode'] === @$_COOKIE[COOKIE_PREFIX.'sessioncode']
                && $player_row['Expire'] >= $NOW )
            {
               $logged_in = true;
            }
         }
      }
      //else error("wrong_userid");
   }

   if( !$logged_in )
   {
      if( !$wap_opened )
         wap_open( 'LOGIN');
      $card = "<card id=\"login\" title=\"Register!\">";
      $card.= wap_auth( $uhandle);
      $card.= "</card>\n";
      echo $card;
      wap_close();
      exit;
      //error("not_logged_in",'wap1');
   }


   //+logging stat adjustments

   $my_id = (int)$player_row['ID'];
   $my_name = wap_safe( $player_row['Handle']);


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

   $resultM = mysql_query( $query ) or error('mysql_query_failed','wap3');
   $countM = @mysql_num_rows($resultM);


   // Games to play?

   $query = "SELECT UNIX_TIMESTAMP(LastChanged) as date,Games.ID, " .
       "Games.Moves,(White_ID=$my_id)+0 AS Color, " .
       "opponent.Name, opponent.Handle " .
       "FROM Games,Players AS opponent " .
       "WHERE ToMove_ID=$my_id AND Status!='INVITED' AND Status!='FINISHED' " .
         "AND (opponent.ID=Black_ID OR opponent.ID=White_ID) AND opponent.ID!=$my_id " .
       "ORDER BY date, Games.ID";

   $resultG = mysql_query( $query ) or error('mysql_query_failed','wap4');
   $countG = @mysql_num_rows($resultG);


   // Display results

   $wap_sep = "\n<br/>";

   $tit= "Status of $my_name";
   $lnk= $HOSTBASE.'/status.php';
   wap_open( $tit);

   $cardid= 'login';
   $card = "<card id=\"$cardid\" title=\"Status\">";

   $card.= "<p><a accesskey=\"s\" href=\"$lnk\">Status of</a>: $my_name</p>";
   if( $countM>0 )
   {
      $card.= "<a accesskey=\"m\" href=\"#M1\">Messages</a>: $countM<br/>";
   }
   else
   {
      $card.= "Messages: 0<br/>";
   }
   if( $countG>0 )
   {
      $nextMid= 'G1';
      $card.= "<a accesskey=\"g\" href=\"#G1\">Games</a>: $countG<br/>";
   }
   else
   {
      $nextMid= $cardid;
      $card.= "Games: 0<br/>";
   }
   $nextGid= $cardid;

   $card.= wap_auth( $uhandle, $passwd);
   $card.= "</card>\n";
   echo $card;


   $i= 1;
   while( $row = mysql_fetch_assoc($resultM) )
   {
      $safename = @$row['sender'];
      if( !$safename )
         $safename = '[Server message]';
      else
         $safename.= " (".@$row['sendhndl'].")";
      $safename = wap_safe( $safename);

      $safeid = (int)@$row['mid'];

      $hdr= "Message $i";
      $tit= "From: $safename";
      $lnk= $HOSTBASE.'/message.php?mid='.$safeid;
      $dat= @$row['date'];
      $dsc= //"Message: $safeid" . $wap_sep .
            //"Folder: ".FOLDER_NEW . $wap_sep .
            "Subject: ".wap_safe( @$row['Subject']);

      $previd= $cardid;
      $cardid= 'M'.$i;
      $i++;
      $nextid= ( $i > $countM ) ? $nextMid : 'M'.$i;

      wap_item( $cardid, $hdr, $tit, $lnk, $dsc, $dat, $nextid, $previd);
   }


   $clrs="BW"; //player's color... so color to play.
   $i= 1;
   while( $row = mysql_fetch_assoc($resultG) )
   {
      $safename = @$row['Name'];
         $safename.= " (".@$row['Handle'].")";
      $safename = wap_safe( $safename);

      $safeid = (int)@$row['ID'];
      $move = (int)@$row['Moves'];

      $hdr= "Game $i";
      $tit= "Opponent: $safename";
      $lnk= $HOSTBASE.'/game.php?gid='.$safeid;
      $dat= @$row['date'];
      $dsc= //"Game: $safeid" . $wap_sep .
            //"Opponent: $safename" . $wap_sep .
            "Color: ".$clrs{@$row['Color']} . $wap_sep .
            "Move: ".$move;

      $previd= $cardid;
      $cardid= 'G'.$i;
      $i++;
      $nextid= ( $i > $countG ) ? $nextGid : 'G'.$i;

      wap_item( $cardid, $hdr, $tit, $lnk, $dsc, $dat, $nextid, $previd);
   }
   
   wap_close();
}
?>