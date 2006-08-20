<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

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

   $numrows = 0+@mysql_num_rows($result);
   if( !$result or $numrows<=0 )
      return 0;

   $c=2;
   $i=0;
   echo "\n<table title='$numrows rows' class=tbl cellpadding=4 cellspacing=1>\n";
   while( $row = mysql_fetch_assoc( $result ) )
   {
      $c=3-$c;
      $i++;
      if( $i==1 or ($rowhdr>1 && ($i%$rowhdr)==1) )
      {
         echo "<tr>\n";
         foreach( $row as $key => $val )
         {
            echo "<th>$key</th>";
         }
         echo "\n</tr>";
      }
      echo "<tr class=row$c ondblclick=\"row_click(this,'row$c')\">\n";
      foreach( $row as $key => $val )
      {
         //remove sensible fields from a query like "SELECT * FROM Players"
         switch( $key )
         {
            case 'Password':
            case 'Sessioncode':                  
            case 'Email':
               if ($val) $val= '***';
               break;
            case 'Debug':
               if ($val)
                  $val= preg_replace( "%(passwd=)[^&]*%is", "\\1***", $val);
               break;
         }
         $val= textarea_safe($val);
         if( $colsize>0 )
         {
            if( $colwrap==='wrap' )
               $val= wordwrap( $val, $colsize, '<br>', 1);
            else if( $colwrap==='cut' )
               $val= substr( $val, 0, $colsize);
         }
         echo "<td title='$key#$i' nowrap>$val</td>";
      }
      echo "\n</tr>";
   }
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
   else if( $uid1>'' )
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
   $diff = array();

   global $limit;
   $query = "SELECT ID as idP, $pfld as cntP"
          . " FROM Players".uid_clause( 'ID', 'WHERE')
          . " ORDER BY ID DESC$limit"
          ;
   $resP = explain_query( $query)
      or die( $nam.".P: " . mysql_error());

   $query = "SELECT Black_ID as idB, COUNT(*) as cntB"
          . " FROM Games"
          . " WHERE ".$gwhr.$gwhrB.uid_clause( 'Black_ID', 'AND')
          . " GROUP BY Black_ID"
          . " ORDER BY Black_ID DESC"
          ;
   $resB = explain_query( $query)
      or die( $nam.".B: " . mysql_error());

   $query = "SELECT White_ID as idW, COUNT(*) as cntW"
          . " FROM Games"
          . " WHERE ".$gwhr.$gwhrW.uid_clause( 'White_ID', 'AND')
          . " GROUP BY White_ID"
          . " ORDER BY White_ID DESC"
          ;
   $resW = explain_query( $query)
      or die( $nam.".W: " . mysql_error());


   $rowB = mysql_fetch_assoc($resB);
   if( $rowB )
      extract($rowB);
   else
      $idB = -1;

   $rowW = mysql_fetch_assoc($resW);
   if( $rowW )
      extract($rowW);
   else
      $idW = -1;

   while( $rowP = mysql_fetch_assoc($resP) )
   {
      extract($rowP);

      while( $idB > $idP )
      {
         $rowB = mysql_fetch_assoc($resB);
         if( $rowB )
            extract($rowB);
         else
            $idB = -1;
      }

      while( $idW > $idP )
      {
         $rowW = mysql_fetch_assoc($resW);
         if( $rowW )
            extract($rowW);
         else
            $idW = -1;
      }

      $sum = ( $idB == $idP ? $cntB : 0 ) + ( $idW == $idP ? $cntW : 0 );

      if(DEBUG)
         echo "\n<br>P:$idP/$cntP/$sum  B:$idB/".@$cntB." W:$idW/".@$cntW;
      if( $cntP != $sum )
      {
         $diff[$idP] = array( $cntP, $sum);
      }
   }
   mysql_free_result($resB);
   mysql_free_result($resW);
   mysql_free_result($resP);
   return $diff;
}


{
   disable_cache();

   connect2mysql();

  $logged_in = who_is_logged( $player_row);

  if( !$logged_in )
    error("not_logged_in");

  $player_level = (int)$player_row['admin_level'];
  if( !($player_level & ADMIN_DATABASE) )
    error("adminlevel_too_low");


   start_html( 'player_consistency', 0, 
      "  table.tbl { border:0; background: #c0c0c0; }\n" .
      "  tr.row1 { background: #ffffff; }\n" .
      "  tr.row2 { background: #dddddd; }\n" .
      "  tr.hil { background: #ffb010; }" );


   if( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) { 
        if( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
        echo " --- fixed. ";
      }
      echo "<p></p>*** Fixes errors:<br>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; ";}
      echo "<p></p>(just show queries needed):<br>";
   }


   //uid could be '12,27' meaning from player=12 to player=27
   @list( $uid1, $uid2) = explode( ',', @$_REQUEST['uid']);

   //limit could be '55,10'
   if( ($lim=@$_REQUEST['limit']) > '' )
      $limit = " LIMIT $lim";
   else
      $limit = "";

   $is_rated = " AND Games.Rated!='N'" ;
   //$is_rated.= " AND !(Games.Moves < ".DELETE_LIMIT."+Games.Handicap)";


//-----------------

   $diff = cnt_diff( 'Run', 'Running'
                   , "Status!='INVITED' AND Status!='FINISHED'"
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


   //RatedGames && Ratinglog consistency
   $query = "SELECT Players.ID, count(Ratinglog.ID) AS Log, RatedGames FROM Players " .
            "LEFT JOIN Ratinglog ON uid=Players.ID " .
            "GROUP BY Players.ID HAVING Log!=RatedGames"
            .uid_clause( 'Players.ID', 'AND')
            ." ORDER BY Players.ID$limit";
   $result = explain_query( $query)
      or die("Log.A: " . mysql_error());

   $err = 0;
   while( $row = mysql_fetch_assoc($result) )
   {
      extract($row);
      echo "\n<br>ID: $ID  Ratinglog: $Log  Should be: $RatedGames";
      $err++;
   }
   if( $err )
      echo "\n<br>--- $err error(s). MAYBE fixed with: scripts/recalculate_ratings2.php";

   mysql_free_result($result);
   echo "\n<br>RatinLog Done.";


   //Various checks
   $query = "SELECT Players.ID, ClockUsed, " .
            "RatingStatus, Rating2, RatingMin, RatingMax " .
            "FROM Players " .
            "WHERE (" .
              "(RatingStatus='RATED' AND (Rating2>=RatingMax OR Rating2<=RatingMin) ) " .
              "OR NOT((ClockUsed>=0 AND ClockUsed<24) " .
                     "OR (ClockUsed>=".WEEKEND_CLOCK_OFFSET.
                        " AND ClockUsed<".(24+WEEKEND_CLOCK_OFFSET)."))" .
              // no VACATION_CLOCK in Players table
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
   if( $err )
      echo "\n<br>--- $err error(s). Must be fixed by hand.";

   mysql_free_result($result);
   echo "\n<br>Misc Done.";


   end_html();
}
?>