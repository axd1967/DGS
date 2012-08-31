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


chdir("../");

// NOTE: must appear before includes
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
require_once( "include/std_functions.php" );
require_once( "include/game_functions.php" );


$TheErrors->set_mode(ERROR_MODE_PRINT);

define('WAP_CHECK_MIN', 5*60 - 5); //secs
define('WAP_CHECK_MAX', 1*3600);   //secs


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
   return str_replace( "&lt;br/&gt;", "<br/>", strtr($str, $xmltrans) ); // restore <br>
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


function wap_item( $cardid, $head, $title, $link='', $description='', $pubDate='', $nextid='', $previd='', $link_text='' )
{
   $str = card_open( $cardid, $head);

   if( $previd )
      $str.= " <a accesskey=\"p\" href=\"#$previd\">[&lt;Prev]</a>";

   if( $link )
   {
      if( !$link_text )
         $link_text = '[Go]';
      $str.= " <a accesskey=\"g\" href=\"$link\">$link_text</a>";
   }
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

   $wap_sep = "<br/>\n";


   // check for excessive usage
   // NOTE: Without checking DB, this can be abused to block other users. But that should be punished by an admin as abuse.
   //       Advantage like this is to avoid DB-requests.
   if( !is_legal_handle($uhandle) ) // check userid to avoid exploits as it's used in filename
      error('invalid_user', "wap_status.check.handle(".substr($uhandle,0,50).")");
   list( $allow_exec, $last_call_time ) =
      enforce_min_timeinterval( 'wap', 'wap_status-'.strtolower($uhandle), WAP_CHECK_MIN, WAP_CHECK_MAX );

   disable_cache();

   if( !$allow_exec )
   {
      $wap_head = "Status of [$uhandle]";
      $tit = "[DISABLED] $wap_head";
      $lnk = HOSTBASE.'status.php';
      wap_open( $tit );

      $tit = sprintf( T_('%s-Status of [%s] temporarily disabled due to excessive usage!#alt'), 'WAP', $uhandle );
      $lnk = "http://www.dragongoserver.net/faq.php?read=t&cat=15#Entry302"; // FAQ-entry on quota/responsible-user
      $dsc = sprintf( T_('Please follow the link, read the FAQ entry and re-configure your %s configuration accordingly.#alt'), 'WAP') . $wap_sep .
         T_('Please do it as soon as possible to reduce the stress on the server.#alt') . $wap_sep .
         T_('Thanks in advance for your cooperation.#alt');
      $dat = $NOW;
      $cardid = 'U_'.$uhandle;
      wap_item( $cardid, $wap_head, $tit, $lnk, $dsc, $dat, '', '', '[Go FAQ]');

      echo card_close();
      wap_close();
      exit;
   }


   connect2mysql();

   if( $loggin_mode )
   {
      $result = @db_query( "wap_status.find_user($uhandle)",
         "SELECT *, UNIX_TIMESTAMP(Sessionexpire) AS Expire ".
         "FROM Players WHERE Handle='".mysql_addslashes($uhandle)."' LIMIT 1" );

      if( @mysql_num_rows($result) == 1 )
      {
         $player_row = mysql_fetch_assoc($result);
         mysql_free_result($result);

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
      else
      {
         //error('wrong_userid', "wap_status.check.user($uhandle)");
         mysql_free_result($result);
      }
   }

   writeIpStats('WAP');

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
   }


   //+logging stat adjustments

   $my_id = (int)$player_row['ID'];
   $my_name = $player_row['Handle'];


   // New messages?

   $query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS date, me.mid, " .
      "Messages.Subject, Players.Name AS sender, Players.Handle AS sendhndl " .
      "FROM Messages " .
         "INNER JOIN MessageCorrespondents AS me ON me.mid=Messages.ID " .
         "LEFT JOIN MessageCorrespondents AS other ON other.mid=me.mid AND other.Sender!=me.Sender " .
         "LEFT JOIN Players ON Players.ID=other.uid " .
      "WHERE me.uid=$my_id AND me.Folder_nr=".FOLDER_NEW." " .
         "AND me.Sender IN('N','S') " . //exclude message to myself
      "ORDER BY Messages.Time, me.mid";

   $resultM = db_query( 'wap3', $query );
   $countM = @mysql_num_rows($resultM);


   // Games to play? -> build status-query (including next-game-order)
   $next_game_order = @$player_row['NextGameOrder'];
   $qsql = NextGameOrder::build_status_games_query( $my_id, IS_STARTED_GAME, $next_game_order );

   $resultG = db_query( 'wap4', $qsql->get_select() );
   $countG = @mysql_num_rows($resultG);


   // Display results

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
   mysql_free_result($resultM);


   $clrs="BW"; //player's color... so color to play.
   $i= 1;
   while( $row = mysql_fetch_assoc($resultG) )
   {
      $opponame = @$row['opp_Name'];
      $opponame.= " (".@$row['opp_Handle'].")";

      $gid = (int)@$row['ID'];
      $move = (int)@$row['Moves'];

      $hdr= "Game $i";
      $tit= "Opponent: $opponame";
      $lnk= HOSTBASE.'game.php?gid='.$gid;
      $dat= @$row['X_Lastchanged'];
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
   mysql_free_result($resultG);

   wap_close();
}
?>
