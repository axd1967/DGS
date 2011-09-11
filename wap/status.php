<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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


chdir("../");

function error($err, $debugmsg=NULL)
{
   global $uhandle;

   list( $xerr, $uri)= err_log( $uhandle, $err, $debugmsg);

   global $wap_opened;
   if( !$wap_opened )
      wap_open( 'ERROR');
   wap_error( $xerr);
   wap_close();
   exit;
}

require_once( "include/quick_common.php" );
require_once( "include/connect2mysql.php" );
require_once( "include/classlib_game.php" );

$TheErrors->set_mode(ERROR_MODE_PRINT);


$xmltrans = array();
for( $i=1; $i<0x20 ; $i++ )
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

for( $i=0x80; $i<0x100 ; $i++ )
   $xmltrans[chr($i)] = "&#$i;";


// could not be called twice on the same string
function wap_safe( $str)
{
   $str = reverse_htmlentities( $str);
   global $xmltrans;
   return strtr($str, $xmltrans);
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
   global $encoding_used, $NOW;

   ob_start("ob_gzhandler");
   global $wap_opened;
   $wap_opened= true;

   if( empty($encoding_used) )
   {
      $encoding_used = 'UTF-8';
      //$encoding_used = 'iso-8859-1';
   }

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


function card_open( $cardid, $head)
{
   $head = addslashes(FRIENDLY_SHORT_NAME).' - '.addslashes($head);
   return "<card id=\"$cardid\" title=\"$head\">";
}


function card_close()
{
   return "</card>\n";
}


function wap_item( $cardid, $head, $title, $link='', $description='', $pubDate='', $nextid='', $previd='')
{
   $str = card_open( $cardid, $head);

   if( $previd )
      $str.= " <a accesskey=\"p\" href=\"#$previd\">[&lt;Prev]</a>";

   if( $link )
      $str.= " <a accesskey=\"g\" href=\"$link\">[Go]</a>";
   //$str.= "<p><do type=\"prev\" label=\"back\"><prev/></do></p>";
   //$str.= "<do type=\"prev\" label=\"back\"><prev/></do>";

   if( $nextid )
      $str.= " <a accesskey=\"n\" href=\"#$nextid\">[Next&gt;]</a>";

   $str.= "<br/>";


   $title = wap_safe( $title);
   $str.= "<p><b>$title</b></p>";

   //if( $pubDate )
      $str.= "<p>" . wap_date($pubDate) . "</p>";

   if( !empty($description) )
   {
      $description = wap_safe( $description);
      $str.= "<p>$description</p>";
   }

   $str.= card_close();

   echo $str;
}


function wap_error( $str, $title='', $link='')
{
   if( !$link )
      $link= HOSTBASE;
   if( !$title )
      $title= 'ERROR';

   wap_item( 'E'.wap_id(), 'Error', $title, $link, 'Error: '.$str);
}


function wap_warning( $str, $title='', $link='')
{
   if( !$link )
      $link= HOSTBASE;
   if( !$title )
      $title= 'WARNING';

   wap_item( 'W'.wap_id(), 'Warning', $title, $link, 'Warning: '.$str);
}


function wap_auth( $defid='', $defpw='')
{
/*
   //if( $uhandle ) $uhandle= ' - '.$uhandle; else
      $uhandle= '';

   global $wap_opened;
   if( !$wap_opened )
      wap_open( 'LOGIN');
*/

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
         $loggin_mode = 'password';
      else if( !$uhandle && !$passwd )
      {
         $uhandle= safe_getcookie('handle');
         $loggin_mode = 'cookie';
      }
   }


   disable_cache();

   connect2mysql();

   if( $loggin_mode )
   {
      $result = @db_query( "wap_status.find_user($uhandle)",
         "SELECT *, UNIX_TIMESTAMP(Sessionexpire) AS Expire ".
         "FROM Players WHERE Handle='".mysql_addslashes($uhandle)."' LIMIT 1" );

      if( @mysql_num_rows($result) == 1 )
      {
         $player_row = mysql_fetch_assoc($result);

         setTZ( $player_row['Timezone']);

         if( $loggin_mode=='password' )
         {
            if( check_password( $uhandle, $player_row["Password"], $player_row["Newpassword"], $passwd ) )
               $logged_in = true;
            else
               error('wrong_password', "wap_status.check_password($uhandle)");
         }
         else //$loggin_mode=='cookie'
         {
            if( $player_row['Sessioncode'] === safe_getcookie('sessioncode') && $player_row['Expire'] >= $NOW )
               $logged_in = true;
         }

         if( (@$player_row['AdminOptions'] & ADMOPT_DENY_LOGIN) )
            error('login_denied', "wap_status.check.user($uhandle)");
      }
      //else error('wrong_userid', "wap_status.check.user($uhandle)");
   }

   if( !$logged_in )
   {
      if( !$wap_opened )
         wap_open( 'LOGIN');
      $card = card_open( "login", "Register!");
      $card.= wap_auth( $uhandle);
      $card.= card_close();
      echo $card;
      wap_close();
      exit;
      //error("not_logged_in", "wap_status.check_login($uhandle)");
   }


   //+logging stat adjustments

   $my_id = (int)$player_row['ID'];
   $my_name = $player_row['Handle'];


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
      "ORDER BY Messages.Time, me.mid";

   $resultM = db_query( 'wap3', $query );
   $countM = @mysql_num_rows($resultM);


   // Games to play?
   $sql_order = NextGameOrder::get_next_game_order( @$player_row['NextGameOrder'], 'Games' ); // enum -> order

   $query = "SELECT UNIX_TIMESTAMP(Lastchanged) as date,Games.ID, " .
       "Games.Moves,(White_ID=$my_id)+0 AS Color, " .
       "opponent.Name, opponent.Handle " .
       "FROM (Games,Players AS opponent) " .
       "WHERE ToMove_ID=$my_id AND Status" . IS_STARTED_GAME .
         "AND opponent.ID=(Black_ID+White_ID-$my_id) " .
       $sql_order;

   $resultG = db_query( 'wap4', $query );
   $countG = @mysql_num_rows($resultG);


   // Display results

   $wap_sep = "\n<br/>";

   $tit= "Status of $my_name";
   $lnk= HOSTBASE.'status.php';
   wap_open( $tit);

   $cardid= 'login';
   $card = card_open( $cardid, "Status");

   // NOTE: keep static access-keys, no const-usage
   $card.= "<p><a accesskey=\"s\" href=\"$lnk\">Status of</a>: " .wap_safe($my_name). "</p>";
   if( $countM>0 )
      $card.= "<a accesskey=\"m\" href=\"#M1\">Messages</a>: $countM<br/>";
   else
      $card.= "Messages: 0<br/>";

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
   $card.= card_close();
   echo $card;


   $i= 1;
   while( $row = mysql_fetch_assoc($resultM) )
   {
      $sendname = @$row['sender'];
      if( !$sendname )
         $sendname = '[Server message]';
      else
         $sendname.= " (".@$row['sendhndl'].")";

      $mid = (int)@$row['mid'];

      $hdr= "Message $i";
      $tit= "From: $sendname";
      $lnk= HOSTBASE.'message.php?mid='.$mid;
      $dat= @$row['date'];
      $dsc= //"Message: $mid" . $wap_sep .
            //"Folder: ".FOLDER_NEW . $wap_sep .
            "Subject: ".@$row['Subject'];

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
      $opponame = @$row['Name'];
         $opponame.= " (".@$row['Handle'].")";

      $gid = (int)@$row['ID'];
      $move = (int)@$row['Moves'];

      $hdr= "Game $i";
      $tit= "Opponent: $opponame";
      $lnk= HOSTBASE.'game.php?gid='.$gid;
      $dat= @$row['date'];
      $dsc= //"Game: $gid" . $wap_sep .
            //"Opponent: $opponame" . $wap_sep .
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
