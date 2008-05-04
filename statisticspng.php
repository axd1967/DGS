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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( "include/graph.php" );


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

//   if( !$logged_in )
//      error("not_logged_in");


   //disable translations in graph if not latin
   if( eregi( '^iso-8859-', $encoding_used) )
   {
      $keep_english= false;
      $T_= 'T_';
      $datelabel = create_function('$x',
         'return T_(date("M",$x)).date("\\nY",$x);' );
   }
   else
   {
      $keep_english= true;
      $T_= 'fnop';
      $datelabel = create_function('$x',
         'return date("M\\nY",$x);' );
   }


   //prepare the graph

   $SizeX = max( 200, @$_GET['size'] > 0 ? $_GET['size'] : 640 );
   $SizeY = $SizeX * 3 / 4;

   $gr = new Graph($SizeX, $SizeY, substr($bg_color, 1, -1));

   $black = $gr->getcolor(0, 0, 0);
   $red = $gr->getcolor(205, 159, 156);


   //fetch and prepare datas

   get_stat_data();
   db_close();
   $nr_points = count($tTime);

   $graphs[]= array(
      'name' => $T_('Running games'),
      'x' => &$tTime,
      'y' => &$tGameR,
      'max' => $maxGameR,
      'min' => $minGameR,
      'c' => $gr->getcolor( 255,   0,   0),
   );
   $graphs[]= array(
      'name' => $T_('Games'),
      'x' => &$tTime,
      'y' => &$tGames,
      'max' => $maxGames,
      'min' => $minGames,
      'c' => $gr->getcolor( 255,   0, 200),
   );
   $graphs[]= array(
      'name' => $T_('Moves'),
      'x' => &$tTime,
      'y' => &$tMoves,
      'max' => $maxMoves,
      'min' => $minMoves,
      'c' => $gr->getcolor(   0, 180, 200),
   );
   $graphs[]= array(
      'name' => $T_('Users'),
      'x' => &$tTime,
      'y' => &$tUsers,
      'max' => $maxUsers,
      'min' => $minUsers,
      'c' => $gr->getcolor(   0, 200,   0),
   );
   if( @$_REQUEST['activity'] )
   $graphs[]= array(
      'name' => $T_('Activity'),
      'x' => &$tTime,
      'y' => &$tActiv,
      'max' => $maxActiv,
      'min' => $minActiv,
      'c' => $gr->getcolor(   0,   0, 200),
   );


   //start by drawing the headers to find the graph position

   $title_fmt= '%s / %d';
   $title_sep= 4*max($gr->border, $gr->labelMetrics['WIDTH']);

   // $curves_min only works if $graph['max'] > 0
   $curves_min= 1;
   $y = $gr->border;
   $x = 0;
   $a = $gr->width-2*$title_sep;
   $m = 0;
   for( $i=0 ; $i<count($graphs) ; $i++ )
   {
      $graph= &$graphs[$i];
      
      $max = $graph['max'];
      $min = $graph['min'];
      if( $max )
         $curves_min = min($curves_min,$min/$max);

      $v= sprintf($title_fmt, $graph['name'], $graph['max']);

      $b= $gr->labelbox($v);
      $b= $x+$b['x'];
      if( $b > $a )
      {
         $b-= $x;
         $x = 0;
         $y+= $gr->labelMetrics['LINEH'];
      }

      $graph['label']= $v;
      $graph['labelX']= $x;
      $graph['labelY']= $y;

      $m= max($m,$b);
      $x= $b+$title_sep;
   }
   $title_align= $title_sep+($a-$m)/2;
   $title_bottom= $y+$gr->labelMetrics['LINEH'];


   //just a string sample to evaluate $marge_left
   $b= $gr->labelbox('100%');
   $x= $b['x'];
   $y= $title_bottom + $gr->labelMetrics['HEIGHT'];
   $marge_left  = $gr->border+10 +$x;
   $marge_right = max(10,DASH_MODULO+2); //better if > DASH_MODULO
   $marge_top   = max($y,DASH_MODULO+2); //better if > DASH_MODULO
   $marge_bottom= $gr->border+ 2*$gr->labelMetrics['LINEH'];

   $gr->setgraphbox(
      $marge_left,
      $marge_top,
      $gr->width-$marge_right,
      $gr->height-$marge_bottom
      );


   //scale datas

   $gr->setgraphviewX($minTime, $maxTime);

   $curves_min-= 0.01; //add a little spacing
   for( $i=0 ; $i<count($graphs) ; $i++ )
   {
      $graph= &$graphs[$i];
      
      $max = $graph['max'];
      //$min = $graph['min'];
      $gr->setgraphviewY($max, $max*$curves_min);
      $graph['y'] = $gr->mapscaleY($graph['y']);
   }
   $ymax = 100.;
   $ymin = 100*$curves_min;
   $gr->setgraphviewY($ymax, $ymin);

   $tTime = $gr->mapscaleX($tTime);


   //vertical scaling

   $step = 10; //10%
   $start = ceil($ymin/$step)*$step;
   $gr->gridY( $start, $step, $gr->border
      , create_function('$x', 'return $x."%";' ), $black
      , '', $black);


   //horizontal scaling

      $step = 20.; //min grid distance in pixels
      $step/= $gr->sizeX; //graph width
      $step/= 3600*24*30; //one month
      $step = ceil(($maxTime - $minTime) * $step);

      $month = date('n',$minTime)+1;
      $year = date('Y',$minTime);
      $dategrid = create_function('$x',
         "return mktime(0,0,0,\$x,1,$year,0);" );
      $gr->gridX( $month, $step, $gr->boxbottom+3
         , $datelabel, $black
         , $dategrid, $red);


   //draw the curves

   for( $i=0 ; $i<count($graphs) ; $i++ )
   {
      $graph= &$graphs[$i];

      $gr->curve($graph['x'], $graph['y'], $nr_points, $graph['c']);
      $gr->label($title_align+$graph['labelX'], $graph['labelY']
               , $graph['label'], $graph['c']);
   }


   //misc drawings

   if( @$_REQUEST['show_time'] )
      $gr->label( 0, 0,
                 sprintf('%0.2f ms', (getmicrotime()-$page_microtime)*1000), $black);

   $gr->imagesend();
}


function get_stat_data()
{
 global $tTime, $minTime, $maxTime;
 global $tUsers, $minUsers, $maxUsers;
 global $tMoves, $minMoves, $maxMoves;
 global $tGames, $minGames, $maxGames;
 global $tGameR, $minGameR, $maxGameR;
 global $tActiv, $minActiv, $maxActiv;

   $tTime = array();
   $tUsers = array();
   $tMoves = array();
   $tGames = array();
   $tGameR = array();
   $tActiv = array();

   $result = mysql_query(
               "SELECT MAX(UNIX_TIMESTAMP(Time)) AS maxTime" .
               ",MIN(UNIX_TIMESTAMP(Time)) AS minTime" .
               ",MIN(Users) AS minUsers,MAX(Users) AS maxUsers" .
               ",MIN(Moves) AS minMoves,MAX(Moves) AS maxMoves" .
               ",MIN(Games) AS minGames,MAX(Games) AS maxGames" .
               ",MIN(GamesRunning) AS minGameR,MAX(GamesRunning) AS maxGameR" .
               ",MIN(Activity) AS minActiv,MAX(Activity) AS maxActiv" .
               " FROM Statistics")
      or error('mysql_query_failed', 'statisticspng.min_max');

   if( @mysql_num_rows( $result ) != 1 )
      exit;

   $max_row = mysql_fetch_assoc($result);
   extract($max_row);
   mysql_free_result($result);

   $result = mysql_query("SELECT *,UNIX_TIMESTAMP(Time) as times" .
                         " FROM Statistics ORDER BY Time")
      or error('mysql_query_failed', 'statisticspng.load_data');

   if( @mysql_num_rows( $result ) < 1 )
      exit;


   while( $row = mysql_fetch_assoc($result) )
   {
      array_push($tTime, $row['times']);
      array_push($tUsers, $row['Users']);
      array_push($tMoves, $row['Moves']);
      array_push($tGames, $row['Games']);
      array_push($tGameR, $row['GamesRunning']);
      array_push($tActiv, $row['Activity']);
   }
   mysql_free_result($result);
}

?>
