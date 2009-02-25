<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

chdir( '../' );
require_once( "include/std_functions.php" );

define('DEBUG',0);

//---------------
require_once( "include/table_columns.php" );
function echo_query( $query, $rowhdr=20, $colsize=80, $colwrap='cut' )
{
   //kill sensible fields from a query like "SELECT Password as pwd FROM Players"
   $query= preg_replace( "%(Password|Sessioncode|Email)%is", "***", $query);

   $result = mysql_query( $query );

   $mysqlerror = @mysql_error();
   if( $mysqlerror )
   {
      echo "Error: $mysqlerror<p></p>";
      return -1;
   }

   if( !$result  )
      return 0;
   $numrows = 0+@mysql_num_rows($result);
   if( $numrows<=0 )
   {
      mysql_free_result($result);
      return 0;
   }

   $c=0;
   $i=0;
   echo "\n<table title='$numrows rows' class=Table cellpadding=4 cellspacing=1>\n";
   while( $row = mysql_fetch_assoc( $result ) )
   {
      $c=($c % LIST_ROWS_MODULO)+1;
      $i++;
      if( $i==1 || ($rowhdr>1 && ($i%$rowhdr)==1) )
      {
         echo "<tr>\n";
         foreach( $row as $key => $val )
         {
            echo "<th>$key</th>";
         }
         echo "\n</tr>";
      }
      echo "<tr class=\"Row$c\" ondblclick=\"toggle_class(this,'Row$c','HilRow$c')\">\n";
      foreach( $row as $key => $val )
      {
         //remove sensible fields from a query like "SELECT * FROM Players"
         switch( (string)$key )
         {
            case 'Password':
            case 'Sessioncode':
            case 'Email':
               if( $val ) $val= '***';
               break;
            case 'Debug':
               if( $val )
                  $val= preg_replace( "%(passwd=)[^&]*%is", "\\1***", $val);
               break;
         }
         $val= textarea_safe($val);
         if( $colsize>0 )
         {
            if( $colwrap==='wrap' )
               $val= wordwrap( $val, $colsize, '<br>', 1);
            elseif( $colwrap==='cut' )
               $val= substr( $val, 0, $colsize);
         }
         echo "<td title='$key#$i' nowrap>$val</td>";
      }
      echo "\n</tr>";
   }
   mysql_free_result($result);
   echo "\n</table><br>\n";

   return $numrows;
}

function explain_query($s) {
   if(DEBUG)
   {
     echo "<BR>EXPLAIN $s;<BR>";
     echo_query( "EXPLAIN ".$s);

     echo_query( $s);
   }
   return mysql_query( $s);
}
//---------------


function uid_clause( $fld, $oper)
{
   global $uid1, $uid2;
   if( $uid1>'' && $uid2>'' )
   {
      return " $oper ($fld>=$uid1 AND $fld<=$uid2)";
   }
   elseif( $uid1>'' )
   {
      return " $oper ($fld=$uid1)";
   }
   else
   {
      return '';
   }
}


function cnt_diff( $nam, $pfld, $gwhr, $gwhrB='', $gwhrW='')
{
   $tstart = getmicrotime();
   $diff = array();

   global $limit, $sqlbuf;

   $query = "SELECT $sqlbuf Black_ID as idB, COUNT(*) as cntB"
          . " FROM Games"
          . " WHERE ".$gwhr.$gwhrB.uid_clause( 'Black_ID', 'AND')
          . " GROUP BY Black_ID"
          ;
   $resB = explain_query( $query)
      or die( $nam.".B: " . mysql_error());

   $plB = array();
   while( $rowB = mysql_fetch_assoc($resB) )
   {
      $plB[$rowB['idB']] = $rowB['cntB'];
   }
   mysql_free_result($resB);


   $query = "SELECT $sqlbuf White_ID as idW, COUNT(*) as cntW"
          . " FROM Games"
          . " WHERE ".$gwhr.$gwhrW.uid_clause( 'White_ID', 'AND')
          . " GROUP BY White_ID"
          ;
   $resW = explain_query( $query)
      or die( $nam.".W: " . mysql_error());

   $plW = array();
   while( $rowW = mysql_fetch_assoc($resW) )
   {
      $plW[$rowW['idW']] = $rowW['cntW'];
   }
   mysql_free_result($resW);


   $query = "SELECT $sqlbuf ID as idP, $pfld as cntP"
          . " FROM Players".uid_clause( 'ID', 'WHERE');
   $resP = explain_query( $query)
      or die( $nam.".P: " . mysql_error());

   while( $rowP = mysql_fetch_assoc($resP) )
   {
      extract($rowP);
      $sum = @$plB[$idP] + @$plW[$idP];
      if(DEBUG)
         echo "\n<br>P:$idP/$cntP/$sum  B:".@$plB[$idP]." W:".@$plW[$idP];
      if( $cntP != $sum )
         $diff[$idP] = array( $cntP, $sum);
   }
   mysql_free_result($resP);
   krsort($diff, SORT_NUMERIC);

   echo "\n<br>Needed ($nam): " . sprintf("%1.3fs", (getmicrotime() - $tstart));
   return $diff;
}


{
   $beginall = getmicrotime();
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low');

   //uid could be '12,27' meaning from player=12 to player=27
   @list( $uid1, $uid2) = explode( ',', @$_REQUEST['uid']);

   //limit could be '55,10'
   if( ($lim=@$_REQUEST['limit']) > '' )
      $limit = " LIMIT $lim";
   else
      $limit = "";


   if( @$_REQUEST['buffer'] == 'no' )
      $sqlbuf = "";
   else
      $sqlbuf = "SQL_BUFFER_RESULT";


   $page = $_SERVER['PHP_SELF'];
   $page_args = array();
   if( $uid1 > '' )
   {
      if( $uid2 > '' )
         $page_args['uid'] = $uid1.','.$uid2;
      else
         $page_args['uid'] = $uid1;
   }
   if( $lim > '' )
      $page_args['limit'] = $lim;
   if( $sqlbuf == '' )
      $page_args['buffer'] = 'no';

   start_html( 'player_consistency', 0, '',
      "  table.Table { border:0; background: #c0c0c0; }\n" .
      "  tr.Row1 { background: #ffffff; }\n" .
      "  tr.Row2 { background: #dddddd; }\n" .
      "  tr.hil { background: #ffb010; }" );

//echo ">>>> One shot fix. Do not run it again."; end_html(); exit;
   if( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) {
        if( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
        echo " --- fixed. ";
      }
      echo "<p>*** Fixes errors ***"
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; ";}
      $tmp = array_merge($page_args,array('do_it' => 1));
      echo "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."<br>use arg buffer=no to deactivate SQL_BUFFER_RESULT in selects"
         ."</p>";
   }

   $is_rated = " AND Games.Rated IN ('Y','Done')" ;
   //$is_rated = " AND Games.Rated!='N'" ;
   //$is_rated.= " AND !(Games.Moves < ".DELETE_LIMIT."+Games.Handicap)";


   echo "<br>On ", gmtdate(DATE_FMT, $NOW), ' GMT<br>';

//-----------------

   $begin = getmicrotime();
   //First search for games with bad player ID
   $query = "SELECT $sqlbuf ID,White_ID,Black_ID"
          . " FROM Games"
          . " WHERE Status!='INVITED'"
            . " AND (White_ID<=0 OR Black_ID<=0 OR White_ID=Black_ID)"
          . " ORDER BY ID DESC"
          ;
   $result = explain_query( $query)
      or die("pID.A: " . mysql_error());

   $err = 0;
   while( $row = mysql_fetch_assoc($result) )
   {
      extract($row);
      echo "\n<br>Game: $ID  White_ID: $White_ID  Black_ID: $Black_ID";
      $err++;
   }
   mysql_free_result($result);
   if( $err )
      echo "\n<br>--- $err error(s). Must be fixed by hand.";

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin));
   echo "\n<br>PlayerID Done.";


//-----------------

   $diff = cnt_diff( 'Run', 'Running'
                   , 'Status' . IS_RUNNING_GAME
                   , "", "");
   foreach( $diff as $ID => $ary )
   {
      list( $cnt, $sum) = $ary;
      echo "\n<br>ID: $ID  Running: $cnt  Should be: $sum";

      dbg_query("UPDATE Players SET Running=$sum WHERE ID=$ID LIMIT 1");
   }
   echo "\n<br>Running Done.";


   $diff = cnt_diff( 'Fin', 'Finished'
                   , "Status='FINISHED'"
                   , "", "");
   foreach( $diff as $ID => $ary )
   {
      list( $cnt, $sum) = $ary;
      echo "\n<br>ID: $ID  Finished: $cnt  Should be: $sum";

      dbg_query("UPDATE Players SET Finished=$sum WHERE ID=$ID LIMIT 1");
   }
   echo "\n<br>Finished Done.";


   $diff = cnt_diff( 'Rat', 'RatedGames'
                   , "Status='FINISHED'$is_rated"
                   , "", "");
   foreach( $diff as $ID => $ary )
   {
      list( $cnt, $sum) = $ary;
      echo "\n<br>ID: $ID  RatedGames: $cnt  Should be: $sum";

      dbg_query("UPDATE Players SET RatedGames=$sum WHERE ID=$ID LIMIT 1");
   }
   echo "\n<br>RatedGames Done.";


   $diff = cnt_diff( 'Won', 'Won'
                   , "Status='FINISHED'$is_rated"
                   , " AND Score<0", " AND Score>0");
   foreach( $diff as $ID => $ary )
   {
      list( $cnt, $sum) = $ary;
      echo "\n<br>ID: $ID  Won: $cnt  Should be: $sum";

      dbg_query("UPDATE Players SET Won=$sum WHERE ID=$ID LIMIT 1");
   }
   echo "\n<br>Won Done.";


   $diff = cnt_diff( 'Los', 'Lost'
                   , "Status='FINISHED'$is_rated"
                   , " AND Score>0", " AND Score<0");
   foreach( $diff as $ID => $ary )
   {
      list( $cnt, $sum) = $ary;
      echo "\n<br>ID: $ID  Lost: $cnt  Should be: $sum";

      dbg_query("UPDATE Players SET Lost=$sum WHERE ID=$ID LIMIT 1");
   }
   echo "\n<br>Lost Done.";


   //RatedGames = Won + Lost + Jigo consistency
   $diff = cnt_diff( 'Jig', 'RatedGames-Won-Lost'
                   , "Status='FINISHED'$is_rated"
                   , " AND Score=0", " AND Score=0");
   $err = 0;
   foreach( $diff as $ID => $ary )
   {
      list( $cnt, $sum) = $ary;
      echo "\n<br>ID: $ID  Jigo: $cnt  Should be: $sum";

      $err++;
   }
   if( $err )
      echo "\n<br>--- $err error(s). MAYBE fixed with: scripts/recalculate_ratings2.php";
   echo "\n<br>Jigo Done.";


//-----------------


   $begin = getmicrotime();
   //RatedGames && Ratinglog consistency
   $query = "SELECT $sqlbuf " .
            "Players.ID, count(Ratinglog.ID) AS Log, RatedGames FROM (Players) " .
            "LEFT JOIN Ratinglog ON uid=Players.ID " .
            "GROUP BY Players.ID HAVING Log!=RatedGames"
            .uid_clause( 'Players.ID', 'AND')
            ." $limit";
   $result = explain_query( $query)
      or die("Log.A: " . mysql_error());

   $err = 0;
   while( $row = mysql_fetch_assoc($result) )
   {
      extract($row);
      echo "\n<br>ID: $ID  Ratinglog: $Log  Should be: $RatedGames";
      $err++;
   }
   mysql_free_result($result);
   if( $err )
      echo "\n<br>--- $err error(s). MAYBE fixed with: scripts/recalculate_ratings2.php";

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin));
   echo "\n<br>RatingLog Done.";


   $begin = getmicrotime();
   //Various checks
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
   $result = explain_query( $query)
      or die("Mis.A: " . mysql_error());

   $err = 0;
   while( $row = mysql_fetch_assoc($result) )
   {
      extract($row);
      echo "\n<br>ID: $ID  Misc: ClockUsed=$ClockUsed, $RatingMin &lt; $Rating2 &lt; $RatingMax.";
      $err++;
   }
   mysql_free_result($result);
   if( $err )
      echo "\n<br>--- $err error(s). Must be fixed by hand.";

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin));
   echo "\n<br>Misc Done.";


   echo "\n<br>Needed (all): " . sprintf("%1.3fs", (getmicrotime() - $beginall));
   echo "<hr>Done!!!\n";
   end_html();
}
?>
