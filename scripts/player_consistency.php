<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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


// Checks and fixes errors in Running, Finished, Won and Lost fields in the database.

chdir('..');
require_once 'include/std_functions.php';
require_once 'include/classlib_userconfig.php';
require_once 'include/classlib_userquota.php';
require_once 'include/game_functions.php';
require_once 'include/table_columns.php';
require_once 'include/form_functions.php';

define('DEBUG',0);

$GLOBALS['ThePage'] = new Page('Script', PAGEFLAG_IMPLICIT_FLUSH );


// ---------- MAIN --------------------------------------------------

{
   $beginall = getmicrotime();
   disable_cache();
   connect2mysql();
   set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries

   $logged_in = who_is_logged( $player_row, LOGIN_DEFAULT_OPTS_ADM_OPS );
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.player_consistency');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.player_consistency');
   if ( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'scripts.player_consistency');

/*
   URL-args:
      uid    = '' | 123 | 123,456      ; x,y = range x..y
      limit  = '' | 10
      buffer = '' | no
*/
   //uid could be '12,27' meaning from player=12 to player=27
   @list( $uid1, $uid2) = explode( ',', @$_REQUEST['uid']);

   //limit could be '55,10'
   $limit = ( ($lim=@$_REQUEST['limit']) > '' ) ? " LIMIT $lim" : '';
   $sqlbuf = ( @$_REQUEST['buffer'] == 'no' ) ? '' : 'SQL_BUFFER_RESULT';


   $page = $_SERVER['PHP_SELF'];
   $page_args = array();
   if ( $uid1 > '' )
      $page_args['uid'] = ( $uid2 > '' ) ? $uid1.','.$uid2 : $uid1;
   if ( $lim > '' )
      $page_args['limit'] = $lim;
   if ( $sqlbuf == '' )
      $page_args['buffer'] = 'no';

   start_html( 'player_consistency', 0, '',
      "  table.Table { border:0; background: #c0c0c0; }\n" .
      "  tr.Row1 { background: #ffffff; }\n" .
      "  tr.Row2 { background: #dddddd; }\n" .
      "  tr.hil { background: #ffb010; }" );

//echo ">>>> One shot fix. Do not run it again."; end_html(); exit;
   if ( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) {
        if ( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
        echo " --- fixed. ";
      }
      echo "<p>*** Fixes errors ***</p>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; "; }
   }


   $page = "player_consistency.php";
   $form = new Form( 'plconsistency', $page, FORM_GET );

   $form->add_row( array(
         'DESCRIPTION', 'uid',
         'TEXTINPUT',   'uid', 10, 30, @$_REQUEST['uid'],
         'TEXT',        '"" (=all) | num | num1,num2 (=range)', ));
   $form->add_row( array(
         'DESCRIPTION', 'Limit',
         'TEXTINPUT',   'limit', 10, 10, @$_REQUEST['limit'],
         'TEXT',        '"" (=all) | num', ));
   $form->add_row( array(
         'DESCRIPTION', 'Buffer',
         'CHECKBOX',    'buffer', 1, 'disable SQL_BUFFER_RESULT', @$_REQUEST['buffer'], ));
   $form->add_empty_row();
   $form->add_row( array(
         'SUBMITBUTTON', 'check_it', 'Check Only',
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'do_it', 'Check and Fix it!', ));

   echo "<p><h3 class=center>Player Consistency:</h3>\n";
   $form->echo_string();

   if ( !@$_REQUEST['check_it'] && !@$_REQUEST['do_it'] )
   {
      end_html();
      exit;
   }


   $is_rated = " AND Games.Rated IN ('Y','Done')" ;
   //$is_rated = " AND Games.Rated!='N'" ;
   //$is_rated.= " AND !(Games.Moves < ".DELETE_LIMIT."+Games.Handicap)";


   echo "<br>On ", date(DATE_FMT, $NOW), ' GMT<br>';


//-----------------

   $C = 'red';
   echo span($C, "\n<br>Check for bad players (bad White_ID and/or Black_ID) ...\n<br>");

   $begin = getmicrotime();
   //First search for games with bad player ID
   $query = "SELECT $sqlbuf ID,White_ID,Black_ID"
          . " FROM Games"
          . " WHERE Status ".not_in_clause( $ENUM_GAMES_STATUS, GAME_STATUS_SETUP, GAME_STATUS_INVITED )
            . " AND (White_ID<=0 OR Black_ID<=0 OR White_ID=Black_ID)"
          . " ORDER BY ID DESC"
          ;
   $result = explain_query( "pID.find_bad_players1", $query)
      or die("pID.find_bad_players2: " . mysql_error());

   $err = 0;
   while ( $row = mysql_fetch_assoc($result) )
   {
      extract($row);
      $msg = ( $row['White_ID'] == $row['Black_ID'] ) ? ' (same ID)' : '';
      echo "\n<br>Game: $ID  White_ID: $White_ID  Black_ID: $Black_ID $msg";
      $err++;
   }
   mysql_free_result($result);
   if ( $err )
      echo "\n<br>--- $err error(s). Must be fixed by hand.";

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin));
   echo "\n<br>PlayerID Done.";


//-----------------

   echo span($C, "\n<br><br>Check game-counts of players ...\n<br>");

   $diff = cnt_diff( 'Run', 'Running', 'Status'.IS_STARTED_GAME);
   foreach ( $diff as $ID => $ary )
   {
      list( $cnt, $sum) = $ary;
      echo "\n<br>ID: $ID  Running: $cnt  Should be: $sum";

      dbg_query("UPDATE Players SET Running=$sum WHERE ID=$ID LIMIT 1");
   }
   echo "\n<br>Running Done.<br>\n";


   $diff = cnt_diff( 'Fin', 'Finished', "Status='".GAME_STATUS_FINISHED."'");
   foreach ( $diff as $ID => $ary )
   {
      list( $cnt, $sum) = $ary;
      echo "\n<br>ID: $ID  Finished: $cnt  Should be: $sum";

      dbg_query("UPDATE Players SET Finished=$sum WHERE ID=$ID LIMIT 1");
   }
   echo "\n<br>Finished Done.<br>\n";


   $diff = cnt_diff( 'Rat', 'RatedGames', "Status='".GAME_STATUS_FINISHED."' $is_rated");
   foreach ( $diff as $ID => $ary )
   {
      list( $cnt, $sum) = $ary;
      echo "\n<br>ID: $ID  RatedGames: $cnt  Should be: $sum";

      dbg_query("UPDATE Players SET RatedGames=$sum WHERE ID=$ID LIMIT 1");
   }
   echo "\n<br>RatedGames Done.<br>\n";


   $diff = cnt_diff( 'Won', 'Won', "Status='".GAME_STATUS_FINISHED."' $is_rated", " AND Score<0", " AND Score>0");
   foreach ( $diff as $ID => $ary )
   {
      list( $cnt, $sum) = $ary;
      echo "\n<br>ID: $ID  Won: $cnt  Should be: $sum";

      dbg_query("UPDATE Players SET Won=$sum WHERE ID=$ID LIMIT 1");
   }
   echo "\n<br>Won Done.<br>\n";


   $diff = cnt_diff( 'Los', 'Lost', "Status='".GAME_STATUS_FINISHED."'$is_rated", " AND Score>0", " AND Score<0");
   foreach ( $diff as $ID => $ary )
   {
      list( $cnt, $sum) = $ary;
      echo "\n<br>ID: $ID  Lost: $cnt  Should be: $sum";

      dbg_query("UPDATE Players SET Lost=$sum WHERE ID=$ID LIMIT 1");
   }
   echo "\n<br>Lost Done.<br>\n";


   //RatedGames = Won + Lost + Jigo consistency
   $diff = cnt_diff( 'Jig', 'RatedGames-Won-Lost', "Status='".GAME_STATUS_FINISHED."'$is_rated", " AND Score=0", " AND Score=0");
   $err = 0;
   foreach ( $diff as $ID => $ary )
   {
      list( $cnt, $sum) = $ary;
      echo "\n<br>ID: $ID  Jigo: $cnt  Should be: $sum";

      $err++;
   }
   if ( $err )
      echo "\n<br>--- $err error(s). MAYBE fixed with: scripts/recalculate_ratings2.php";
   echo "\n<br>Jigo Done.<br>\n";


//-----------------

   echo span($C, "\n<br><br>Check Ratinglog vs RatedGames of players ...\n<br>");

   $begin = getmicrotime();
   //check consistency: RatedGames && Ratinglog
   $query = "SELECT $sqlbuf Players.ID, COUNT(Ratinglog.ID) AS Log, RatedGames " .
            "FROM Players LEFT JOIN Ratinglog ON Ratinglog.uid=Players.ID " .
            "GROUP BY Players.ID HAVING Log!=RatedGames"
            .uid_clause( 'Players.ID', 'AND')
            ." $limit";
   $result = explain_query( "Ratinglog1", $query)
      or die("Ratinglog2: " . mysql_error());

   $err = 0;
   while ( $row = mysql_fetch_assoc($result) )
   {
      extract($row);
      echo "\n<br>ID: $ID  Ratinglog: $Log  Should be: $RatedGames";
      $err++;
   }
   mysql_free_result($result);
   if ( $err )
      echo "\n<br>--- $err error(s). MAYBE fixed with: scripts/recalculate_ratings2.php";

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin));
   echo "\n<br>RatingLog Done.";


//-----------------

   echo span($C, "\n<br><br>Check Rating & ClockUsed of players ...\n<br>");

   $begin = getmicrotime();
   //Various checks: Rating2 within boundaries of RatingMin/Max (if user RATED), valid ClockUsed
   $query = "SELECT $sqlbuf " .
            "Players.ID, ClockUsed, RatingStatus, Rating2, RatingMin, RatingMax " .
            "FROM Players " .
            "WHERE (" .
              "(RatingStatus='RATED' AND (Rating2>RatingMax OR Rating2<RatingMin) ) " .
              "OR NOT((ClockUsed>=0 AND ClockUsed<24) " .
              // no WEEKEND_CLOCK in Players table
              //       "OR (ClockUsed>=".WEEKEND_CLOCK_OFFSET.
              //          " AND ClockUsed<".(24+WEEKEND_CLOCK_OFFSET).")" .
              // no VACATION_CLOCK in Players table
              ")" .
            ")"
            .uid_clause( 'Players.ID', 'AND')
            ." ORDER BY Players.ID$limit";
   //echo "\n<br>MiscQry=".$query;
   $result = explain_query( "Misc1", $query)
      or die("Misc2: " . mysql_error());

   $err = 0;
   while ( $row = mysql_fetch_assoc($result) )
   {
      extract($row);
      echo "\n<br>ID: $ID  Misc: ClockUsed=$ClockUsed, $RatingMin &lt; $Rating2 &lt; $RatingMax.";
      $err++;
   }
   mysql_free_result($result);
   if ( $err )
      echo "\n<br>--- $err error(s). Must be fixed by hand.";

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin));
   echo "\n<br>Misc Done.";


//-----------------

   echo span($C, "\n<br><br>Check multi-player-game count of players ...\n<br>");

   $begin = getmicrotime();
   echo "\n<br>";

   // check Players.GamesMPG
   $query = "SELECT $sqlbuf GP.uid, COUNT(*) AS X_Count, P.GamesMPG "
      . "FROM GamePlayers AS GP "
         . "INNER JOIN Games AS G ON G.ID=GP.gid "
         . "INNER JOIN Players AS P ON P.ID=GP.uid "
      . "WHERE G.Status='".GAME_STATUS_SETUP."' "
         . "AND ((GP.Flags & ".GPFLAG_JOINED.") OR (GP.Flags & ".GPFLAGS_RESERVED_INVITATION.")=".GPFLAGS_RESERVED_INVITATION.") "
         . uid_clause( 'GP.uid', 'AND' )
      . "GROUP BY GP.uid $limit";
   $result = explain_query( "Players.GamesMPG1", $query)
      or die("Players.GamesMPG2: " . mysql_error());
   $err = 0;
   while ( $row = mysql_fetch_assoc($result) )
   {
      $uid = $row['uid'];
      $cntG = $row['X_Count'];
      $cntP = $row['GamesMPG'];
      if ( $cntG != $cntP )
      {
         $err++;
         echo "\n<br>ID: $uid  GamesMPG: $cntP  Should be: $cntG";
         dbg_query("UPDATE Players SET GamesMPG=$cntG WHERE ID=$uid LIMIT 1");
      }
   }
   if ( $err )
      echo "\n<br>--- $err error(s) found.";

   echo "\n<br>Players.GamesMPG Done.";


//----------------- Player-related tables (ConfigBoard, ConfigPages, UserQuota)

   echo span($C, "\n<br><br>Check foreign-keys of players-data (ConfigBoard, ConfigPages, UserQuota) ...\n<br>");

   $begin = getmicrotime();

   // check missing ConfigBoard/ConfigPages/UserQuota
   $query = "SELECT $sqlbuf P.ID, " .
               "IFNULL(CB.User_ID,0) AS XCB_uid, " .
               "IFNULL(CP.User_ID,0) AS XCP_uid, " .
               "IFNULL(UQ.uid,0) AS XUQ_uid " .
            "FROM Players AS P " .
               "LEFT JOIN ConfigBoard AS CB ON CB.User_ID=P.ID " .
               "LEFT JOIN ConfigPages AS CP ON CP.User_ID=P.ID " .
               "LEFT JOIN UserQuota AS UQ ON UQ.uid=P.ID " .
            uid_clause( 'P.ID', 'WHERE' ) .
            "HAVING (XCB_uid=0 OR XCP_uid=0 OR XUQ_uid=0) " .
            "ORDER BY P.ID $limit";
   $result = explain_query( "PlayersFK1", $query)
      or die("PlayersFK2: " . mysql_error());
   $err = 0;
   while ( $row = mysql_fetch_assoc($result) )
   {
      $uid = $row['ID'];
      if ( $row['XCB_uid'] == 0 )
      {
         $err++;
         echo "Inserting ConfigBoard for user-id [$uid] ...\n<br>";
         if ( $do_it )
            ConfigBoard::insert_default( $uid );
      }
      if ( $row['XCP_uid'] == 0 )
      {
         $err++;
         echo "Inserting ConfigPages for user-id [$uid] ...\n<br>";
         if ( $do_it )
            ConfigPages::insert_default( $uid );
      }
      if ( $row['XUQ_uid'] == 0 )
      {
         $err++;
         echo "Inserting UserQuota for user-id [$uid] ...\n<br>";
         if ( $do_it )
            UserQuota::insert_default( $uid );
      }
   }
   if ( $err )
      echo "\n<br>--- $err error(s) found.";

   echo "\n<br>Player-related tables Done.";

   echo "\n<br>";

//----------------- counters

   echo span($C, "\n<br><br>Check message/feature/bulletin/tournament new-counters of players ...\n<br>");

   $begin = getmicrotime();

   // fix Players.CountMsgNew
   $query = "SELECT ID, CountMsgNew FROM Players WHERE CountMsgNew>=0 " .
            uid_clause( 'ID', 'AND' ) .
            "ORDER BY ID $limit";
   $result = explain_query( "CountMsgNew1", $query)
      or die("CountMsgNew2: " . mysql_error());
   $err = 0;
   while ( $row = mysql_fetch_assoc($result) )
   {
      $uid = $row['ID'];
      $CountMsgNew = $row['CountMsgNew'];

      $count_msg_new = count_messages_new( $uid ); // force-recalc
      if ( $count_msg_new >= 0 && $count_msg_new != $CountMsgNew )
      {
         echo "\n<br>ID: $uid fix CountMsgNew [$CountMsgNew] -> [$count_msg_new].";
         dbg_query("UPDATE Players SET CountMsgNew=$count_msg_new WHERE ID=$uid LIMIT 1");
         $err++;
      }
   }
   mysql_free_result($result);
   if ( $err )
      echo "\n<br>--- $err error(s) found.";

   echo "\n<br>MessageNew count Done.";
   echo "\n<br>";


   // fix Players.CountFeatNew
   $query = "SELECT ID, CountFeatNew FROM Players WHERE CountFeatNew>=0 " .
            uid_clause( 'ID', 'AND' ) .
            "ORDER BY ID $limit";
   $result = explain_query( "CountFeatNew1", $query)
      or die("CountFeatNew2: " . mysql_error());
   $err = 0;
   while ( $row = mysql_fetch_assoc($result) )
   {
      $uid = $row['ID'];
      $CountFeatNew = $row['CountFeatNew'];

      $count_feat_new = count_feature_new( $uid ); // force-recalc
      if ( $count_feat_new >= 0 && $count_feat_new != $CountFeatNew )
      {
         echo "\n<br>ID: $uid fix CountFeatNew [$CountFeatNew] -> [$count_feat_new].";
         $err++;
      }
   }
   mysql_free_result($result);
   if ( $err )
   {
      // reset all to recalc on user-reloading
      dbg_query("UPDATE Players SET CountFeatNew=-1 WHERE CountFeatNew>=0 LIMIT $err");
      echo "\n<br>--- $err error(s) found.";
   }

   echo "\n<br>FeatureNew count Done.";
   echo "\n<br>";


   // fix Players.CountBulletinNew
   $query = "SELECT ID, CountBulletinNew FROM Players WHERE CountBulletinNew>=0 " .
            uid_clause( 'ID', 'AND' ) .
            "ORDER BY ID $limit";
   $result = explain_query("CountBulletinNew1", $query)
      or die("CountBulletinNew2: " . mysql_error());
   $err = 0;
   while ( $row = mysql_fetch_assoc($result) )
   {
      $uid = $row['ID'];
      $CountBulletinNew = $row['CountBulletinNew'];

      $count_bulletin_new = Bulletin::count_bulletin_new( $uid ); // force-recalc
      if ( $count_bulletin_new >= 0 && $count_bulletin_new != $CountBulletinNew )
      {
         echo "\n<br>ID: $uid fix CountBulletinNew [$CountBulletinNew] -> [$count_bulletin_new].";
         $err++;
      }
   }
   mysql_free_result($result);
   if ( $err )
   {
      // reset all to recalc on user-reloading
      dbg_query("UPDATE Players SET CountBulletinNew=-1 WHERE CountBulletinNew>=0 LIMIT $err");
      echo "\n<br>--- $err error(s) found.";
   }

   echo "\n<br>BulletinNew count Done.";


   // fix Players.CountTourneyNew
   $query = "SELECT ID, UNIX_TIMESTAMP(Lastaccess) AS X_Lastaccess, CountTourneyNew " .
            "FROM Players WHERE CountTourneyNew>=0 " .
            uid_clause( 'ID', 'AND' ) .
            "ORDER BY ID $limit";
   $result = explain_query("CountTourneyNew1", $query)
      or die("CountTourneyNew2: " . mysql_error());
   $err = 0;
   while ( $row = mysql_fetch_assoc($result) )
   {
      $uid = $row['ID'];
      $CountTourneyNew = $row['CountTourneyNew'];
      $X_Lastaccess = $row['X_Lastaccess'];

      $count_tourney_new = count_tourney_new( $uid, $X_Lastaccess ); // force-recalc
      if ( $count_tourney_new >= 0 && $count_tourney_new != $CountTourneyNew )
      {
         echo "\n<br>ID: $uid fix CountTourneyNew [$CountTourneyNew] -> [$count_tourney_new].";
         $err++;
      }
   }
   mysql_free_result($result);
   if ( $err )
   {
      // reset all to recalc on user-reloading
      dbg_query("UPDATE Players SET CountTourneyNew=-1 WHERE CountTourneyNew>=0 LIMIT $err");
      echo "\n<br>--- $err error(s) found.";
   }

   echo "\n<br>TourneyNew count Done.";


   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin));
   echo "\n<br>Players main-menu counts Done.";

//-----------------


   echo span( "\n<br><br>Needed (all): " . sprintf("%1.3fs", (getmicrotime() - $beginall)) );
   echo "<hr>Done!!!\n";
   end_html();
}//main



function echo_query( $dbgmsg, $query, $rowhdr=20, $colsize=80, $colwrap='cut' )
{
   $result = db_query( "player_consistency.echo_query.$dbgmsg", $query );

   $mysqlerror = @mysql_error();
   if ( $mysqlerror )
   {
      echo "Error: $mysqlerror<p></p>";
      return -1;
   }

   if ( !$result  )
      return 0;
   $numrows = 0+@mysql_num_rows($result);
   if ( $numrows<=0 )
   {
      mysql_free_result($result);
      return 0;
   }

   $c=0;
   $i=0;
   echo "\n<table title='$numrows rows' class=Table cellpadding=4 cellspacing=1>\n";
   while ( $row = mysql_fetch_assoc( $result ) )
   {
      $c=($c % LIST_ROWS_MODULO)+1;
      $i++;
      if ( $i==1 || ($rowhdr>1 && ($i%$rowhdr)==1) )
      {
         echo "<tr>\n";
         foreach ( $row as $key => $val )
         {
            echo "<th>$key</th>";
         }
         echo "\n</tr>";
      }
      echo "<tr class=\"Row$c\" ondblclick=\"toggle_class(this,'Row$c','HilRow$c')\">\n";
      foreach ( $row as $key => $val )
      {
         //remove sensible fields from a query like "SELECT * FROM Players"
         switch ( (string)$key )
         {
            case 'Password':
            case 'Sessioncode':
            case 'Email':
               if ( $val ) $val= '***';
               break;

            case 'Debug':
               if ( $val )
                  $val= preg_replace( "%(passwd=)[^&]*%is", "\\1***", $val);
               break;
         }
         $val= textarea_safe($val);
         if ( $colsize>0 )
         {
            if ( $colwrap==='wrap' )
               $val= wordwrap( $val, $colsize, '<br>', 1);
            elseif ( $colwrap==='cut' )
               $val= substr( $val, 0, $colsize);
         }
         echo "<td title='$key#$i' nowrap>$val</td>";
      }
      echo "\n</tr>";
   }
   mysql_free_result($result);
   echo "\n</table><br>\n";

   return $numrows;
}//echo_query

function explain_query( $dbgmsg, $s ) {
   if (DEBUG)
   {
     echo "<BR>EXPLAIN $s;<BR>";
     echo_query( $dbgmsg, "EXPLAIN $s" );
     echo_query( $dbgmsg, $s); // show contents
   }
   return db_query( "player_consistency.explain_query.$dbgmsg", $s); // return query-results
}


function uid_clause( $fld, $oper='' )
{
   global $uid1, $uid2;
   if ( $uid1>'' && $uid2>'' )
      return " $oper ($fld>=$uid1 AND $fld<=$uid2) ";
   elseif ( $uid1>'' )
      return " $oper ($fld=$uid1) ";
   else
      return '';
}


/*!
 * \brief Counts and returns player-ids with incorrect counts for given query-restrictions.
 * \param $pfld Players-table field to check count for
 * \param $gwhr game-WHERE clause restricting query (e.g. on Status/Rated or other field)
 * \param $gwhrB game-WHERE clause restricting query for Black-player
 * \param $gwhrW game-WHERE clause restricting query for White-player
 * \note multi-player-games are counted in automatically for Players-field $pfld 'Running' and 'Finished' only,
 *       because MPG is not a rated games and 'RatedGames/Won/Lost' are only counted for rated games!
 * \return entries only for players with incorrect counts found:
 *       arr( Players.ID => arry( Players.<$pfld> count, real games-count )
 */
function cnt_diff( $name, $pfld, $gwhr, $gwhrB='', $gwhrW='')
{
   $tstart = getmicrotime();
   $diff = array();

   global $limit, $sqlbuf;

   $query = "SELECT $sqlbuf Black_ID AS idB, COUNT(*) AS cntB"
          . " FROM Games"
          . " WHERE ".$gwhr.$gwhrB.uid_clause( 'Black_ID', 'AND')
            . " AND GameType='".GAMETYPE_GO."'" // no MPG
          . " GROUP BY Black_ID"
          ;
   $resB = explain_query( "$name.B1", $query)
      or die( "$name.B2: " . mysql_error());

   $plB = array(); // Players.ID => games-count
   while ( $rowB = mysql_fetch_assoc($resB) )
      $plB[$rowB['idB']] = $rowB['cntB'];
   mysql_free_result($resB);


   $query = "SELECT $sqlbuf White_ID AS idW, COUNT(*) AS cntW"
          . " FROM Games"
          . " WHERE ".$gwhr.$gwhrW.uid_clause( 'White_ID', 'AND')
            . " AND GameType='".GAMETYPE_GO."'" // no MPG
          . " GROUP BY White_ID"
          ;
   $resW = explain_query( "$name.W1", $query)
      or die( "$name.W2: " . mysql_error());

   $plW = array(); // Players.ID => games-count
   while ( $rowW = mysql_fetch_assoc($resW) )
      $plW[$rowW['idW']] = $rowW['cntW'];
   mysql_free_result($resW);


   $plMPG = array(); // Players.ID => games-count
   if ( $pfld == 'Running' || $pfld == 'Finished' ) // count MPGs
   {
      $query = "SELECT $sqlbuf GP.uid, COUNT(*) AS cntMPG"
             . " FROM GamePlayers AS GP  INNER JOIN Games AS G ON G.ID=GP.gid"
             . " WHERE ".$gwhr.uid_clause( 'GP.uid', 'AND')
             . " GROUP BY GP.uid"
             ;
      $resMPG = explain_query( "$name.MPG1", $query)
         or die( "$name.MPG2: " . mysql_error());

      while ( $rowMPG = mysql_fetch_assoc($resMPG) )
         $plMPG[$rowMPG['uid']] = $rowMPG['cntMPG'];
      mysql_free_result($resMPG);
   }


   $query = "SELECT $sqlbuf ID AS idP, $pfld AS cntP FROM Players".uid_clause( 'ID', 'WHERE');
   $resP = explain_query( "$name.P1", $query)
      or die( "$name.P2: " . mysql_error());

   while ( $rowP = mysql_fetch_assoc($resP) )
   {
      extract($rowP);
      $sum = @$plB[$idP] + @$plW[$idP] + @$plMPG[$idP];
      if (DEBUG)
         echo "\n<br>P:$idP/$cntP/$sum  B:".@$plB[$idP]." W:".@$plW[$idP]." MPG:".@$plMPG[$idP];
      if ( $cntP != $sum )
         $diff[$idP] = array( $cntP, $sum );
   }
   mysql_free_result($resP);

   krsort($diff, SORT_NUMERIC);

   echo "\n<br>Needed ($name): " . sprintf("%1.3fs", (getmicrotime() - $tstart));
   return $diff;
}//cnt_diff

?>
