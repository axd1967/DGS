<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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

require_once( "include/std_functions.php" );
require_once( "include/graph.php" );

define('IMAGE_BORDER',6);

function get_stat_data( )
{
 global $tTime, $tUsers, $tMoves, $tGames, $tGameR;
 global $minTime, $minUsers, $minMoves, $minGames, $minGameR;
 global $maxTime, $maxUsers, $maxMoves, $maxGames, $maxGameR;

   $tTime = array();
   $tUsers = array();
   $tMoves = array();
   $tGames = array();
   $tGameR = array();
 
   $result = mysql_query(
               "SELECT MAX(UNIX_TIMESTAMP(Time)) AS maxTime" .
               ",MIN(UNIX_TIMESTAMP(Time)) AS minTime" .
               ",MIN(Users) AS minUsers,MAX(Users) AS maxUsers" .
               ",MIN(Moves) AS minMoves,MAX(Moves) AS maxMoves" .
               ",MIN(Games) AS minGames,MAX(Games) AS maxGames" .
               ",MIN(GamesRunning) AS minGameR,MAX(GamesRunning) AS maxGameR" .
               " FROM Statistics") or die(mysql_error());
   if( @mysql_num_rows( $result ) != 1 )
      exit;

   $max_row = mysql_fetch_assoc($result);
   extract($max_row);

   $result = mysql_query("SELECT *,UNIX_TIMESTAMP(Time) as times" .
                         " FROM Statistics ORDER BY Time")
          or die(mysql_error());      
   if( @mysql_num_rows( $result ) < 1 )
      exit;

/*
   mysql_query( "INSERT INTO Statistics SET " .
                "Time=FROM_UNIXTIME($NOW), " .
                "Hits=$Hits, " .
                "Users=$Users, " .
                "Moves=" . ($MovesFinished+$MovesRunning) . ", " .
                "MovesFinished=$MovesFinished, " .
                "MovesRunning=$MovesRunning, " .
                "Games=" . ($GamesRunning+$GamesFinished) . ", " .
                "GamesFinished=$GamesFinished, " .
                "GamesRunning=$GamesRunning, " .
                "Activity=$Activity" )
               or error('mysql_query_failed','daily_cron7');
 global $tTime, $tUsers, $tMoves, $tGames, $tGameR;
*/

   while( $row = mysql_fetch_assoc($result) )
   {
      array_push($tTime, $row['times']);
      array_push($tUsers, $row['Users']);
      array_push($tMoves, $row['Moves']);
      array_push($tGames, $row['Games']);
      array_push($tGameR, $row['GamesRunning']);
   } 
}

function scale($x)
{
   global $MAX, $MIN, $SIZE, $OFFSET;

   return round( $OFFSET + (($x-$MIN)/($MAX-$MIN))*$SIZE);
}


{

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

//   if( !$logged_in )
//      error("not_logged_in");


   //Disable translations in graph if not latin
   if( eregi( '^iso-8859-', $encoding_used) )
   {
      $keep_english= false;
      $T_= 'T_';
   }
   else
   {
      $keep_english= true;
      $T_= 'fnop';
   }



//Then draw the graph

   $SizeX = ( @$_GET['size'] > 0 ? $_GET['size'] : $defaultsize );
   $SizeY = $SizeX * 3 / 4;

   $im = imagecreate( $SizeX, $SizeY );
   list($r,$g,$b,$a)= split_RGBA(substr($bg_color, 2, 6), 0);
   $bg = imagecolorallocate ($im, $r,$g,$b); //first=background color

   $black = imagecolorallocate ($im, 0, 0, 0);
   $red = imagecolorallocate ($im, 205, 159, 156);


   get_stat_data();

   $nr_points = count($tTime);

   $graphs[]= array(
      'name' => $T_('Running Games'), 
      'x' => &$tTime,
      'y' => &$tGameR,
      'max' => $maxGameR,
      'min' => $minGameR,
      'c' => imagecolorallocate ($im, 255,   0,   0),
   );
   $graphs[]= array(
      'name' => $T_('Games'), 
      'x' => &$tTime,
      'y' => &$tGames,
      'max' => $maxGames,
      'min' => $minGames,
      'c' => imagecolorallocate ($im, 255,   0, 200),
   );
   $graphs[]= array(
      'name' => $T_('Moves'), 
      'x' => &$tTime,
      'y' => &$tMoves,
      'max' => $maxMoves,
      'min' => $minMoves,
      'c' => imagecolorallocate ($im,   0, 180, 200),
   );
   $graphs[]= array(
      'name' => $T_('Users'), 
      'x' => &$tTime,
      'y' => &$tUsers,
      'max' => $maxUsers,
      'min' => $minUsers,
      'c' => imagecolorallocate ($im,   0, 200,   0),
   );


   $b= imagelabelbox($im, '100%');
   define('MARGE_LEFT'  ,$b['x'] +IMAGE_BORDER+10);
   define('MARGE_RIGHT' ,max(10,DASH_MODULO+2)); //Better if > DASH_MODULO

   $title_fmt= '%s / %d';
   $title_sep= 4*max(IMAGE_BORDER,LABEL_WIDTH);

   $curve_bottom= 1;
   $y = IMAGE_BORDER;
   $x = 0;
   $a = $SizeX-2*$title_sep;
   $m = 0;
   for( $i=0 ; $i<count($graphs) ; $i++ )
   {
      $graph= &$graphs[$i];

      $v= sprintf($title_fmt, $graph['name'], $graph['max']);

      $b= imagelabelbox($im, $v);
      $b= $x+$b['x'];
      if( $b > $a )
      {
         $b-= $x;
         $x = 0;
         $y+= LABEL_HEIGHT+LABEL_SEPARATION;
      }

      $graph['label']= $v;
      $graph['labelX']= $x;
      $graph['labelY']= $y;
      if( $graph['max'] )
         $curve_bottom = min($curve_bottom,$graph['min']/$graph['max']);

      $m= max($m,$b);
      $x= $b+$title_sep;
   }
   $title_left= $title_sep+($a-$m)/2;

   $y+= 2*LABEL_HEIGHT+LABEL_SEPARATION;
   define('MARGE_TOP'   ,max($y,DASH_MODULO+2)); //Better if > DASH_MODULO
   define('MARGE_BOTTOM',IMAGE_BORDER+2*(LABEL_HEIGHT+LABEL_SEPARATION));


   //vertical scaling

   $SIZE = $SizeY-MARGE_BOTTOM-MARGE_TOP;
   $OFFSET = MARGE_TOP;

   $curve_bottom-= 0.01;
   for( $i=0 ; $i<count($graphs) ; $i++ )
   {
      $graph= &$graphs[$i];

      $MIN = $graph['max'];
      $MAX = $MIN*$curve_bottom;
      $graph['y'] = array_map('scale', $graph['y']);
   }


   $MIN = 100;
   $MAX = $MIN*$curve_bottom;

   imagesetdash($im, $black);

   $v = ceil($MAX/10)*10;
   if( abs($v)<1 ) $v=0;
   $a = MARGE_LEFT-4 ;
   $b = $SizeX ;
   $b = $b - ((($b-$a) % DASH_MODULO)+1) ; //so all lines start in the same way
   $y = $SizeY ;
   while( $v <= $MIN )
   {
      $sc = scale($v);
      imageline($im, $a, $sc, $b, $sc, IMG_COLOR_STYLED);
      if ( $y > $sc )
      {
         imagelabel($im, IMAGE_BORDER, $sc-LABEL_ALIGN
                  , $v.'%', $black);
         $y = $sc - LABEL_HEIGHT ;
      }
      $v += 10;
   }


   //horizontal scaling

   $SIZE = $SizeX-MARGE_LEFT-MARGE_RIGHT;
   $OFFSET = MARGE_LEFT;
   $MIN = $minTime;
   $MAX = $maxTime;
   $tTime = array_map('scale', $tTime);

   imagesetdash($im, $red);

   $year = date('Y',$minTime);
   $month = date('n',$minTime)+1;

   $step = ceil(($maxTime - $minTime)/(3600*24*30) * 20 / $SIZE);
   $no_text = true;
   $b = $SizeY-MARGE_BOTTOM+3 ;
   $a = MARGE_TOP ;
   $a = $a - (DASH_MODULO-((($b-$a) % DASH_MODULO)+1)) ;
   $x = MARGE_LEFT-1 ;
   $y = $SizeY-MARGE_BOTTOM+3;
   for(;;$month+=$step)
   {
      $dt = mktime(0,0,0,$month,1,$year);
      if( $dt > $maxTime )
      {
         if( !$no_text ) break;
         $dt = $minTime;
         $sc = scale($dt);
      }
      else
      {
         $sc = scale($dt);
         imageline($im, $sc, $a, $sc, $b, IMG_COLOR_STYLED);
      }

      $no_text = false;
      if ($x >= $sc)
         continue;

      $x= max($x,LABEL_WIDTH+
            imagelabel($im, $sc, $y,
                        $T_(date('M', $dt)), $black));
      $x= max($x,LABEL_WIDTH+
            imagelabel($im, $sc, $y+LABEL_HEIGHT+LABEL_SEPARATION,
                        date('Y', $dt), $black));
   }


   //drawings

   for( $i=0 ; $i<count($graphs) ; $i++ )
   {
      $graph= &$graphs[$i];

      imagecurve($im, $graph['x'], $graph['y'], $nr_points, $graph['c']);
      imagelabel($im, $title_left+$graph['labelX'], $graph['labelY']
               , $graph['label'], $graph['c']);
   }


   if( @$_GET['show_time'] == 'y')
      imagelabel($im, 0, 0,
                 sprintf('%0.2f ms', (getmicrotime()-$page_microtime)*1000), $black);


   imagesend($im);
}
?>
