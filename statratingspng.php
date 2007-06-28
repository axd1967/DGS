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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( "include/rating.php" );
require_once( "include/graph.php" );


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

//   if( !$logged_in )
//      error("not_logged_in");


define('MIN_RANK', round(MIN_RATING/100.));

   //disable translations in graph if not latin
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
      $Xlabelfct = create_function('$x',
         'return $x<'.(21-(MIN_RANK))
            .'?(('.(21-(MIN_RANK)).'-$x).\'k\')'
            .':(('.((MIN_RANK)-20).'+$x).\'d\');' );
      $Ylabelfct = create_function('$x',
         'return (string)$x;' );


   //prepare the graph

   $SizeX = max( 200, @$_GET['size'] > 0 ? $_GET['size'] : 640 );
   $SizeY = $SizeX * 3 / 4;

   $gr = new Graph($SizeX, $SizeY, substr($bg_color, 1, -1));

   $black = $gr->getcolor(0, 0, 0);
   $red = $gr->getcolor(205, 159, 156);
   $light_blue = $gr->getcolor(220, 229, 255);
   $number_color = $gr->getcolor(250, 100, 98);


   //fetch and prepare datas

   get_ratings_data( $Xaxis, $graphs, $xlims, $ylims);
   $nr_points = count($Xaxis);

   $ymax = max( 1, $ylims['MAX']);
   $ymin = $ylims['MIN'];


   //start by drawing the headers to find the graph position

   $title_fmt= '%s';
   $title_sep= 4*max($gr->border, $gr->labelMetrics['WIDTH']);

   // $curves_min only works if $graph['max'] > 0
   $curves_min= 0;
   $y = $gr->border;
   $x = 0;
   $a = $gr->width-2*$title_sep;
   $m = 0;
   for( $i=0 ; $i<count($graphs) ; $i++ )
   {
      $graph= &$graphs[$i];

      $graph['c'] = $gr->getcolor($graph['c']);

      $v= sprintf($title_fmt, $graph['name']);
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
   $b= $gr->labelbox($Ylabelfct($ymax));
   $x= $b['x'];

   $y= $title_bottom + $gr->labelMetrics['HEIGHT'];
   $marge_left  = $gr->border+10 +$x;
   $marge_right = max(10,DASH_MODULO+2); //better if > DASH_MODULO
   $marge_top   = max($y,DASH_MODULO+2); //better if > DASH_MODULO
   $marge_bottom= $gr->border+ 1*$gr->labelMetrics['LINEH'];

   $gr->setgraphbox(
      $marge_left,
      $marge_top,
      $gr->width-$marge_right,
      $gr->height-$marge_bottom
      );


   //scale datas

   $gr->setgraphview(
      $xlims['MIN'],
      $ymax,
      $xlims['MAX'],
      $ymin
      );

   for( $i=0 ; $i<count($graphs) ; $i++ )
   {
      $graph= &$graphs[$i]['y'];
      $graph = $gr->mapscaleY($graph);
   }
   $Xaxis = $gr->mapscaleX($Xaxis);


   //vertical scaling

   $step = max( 1, pow(10, round( log10($ymax)-1.1 )));
   $start = ceil($ymin/$step)*$step;
   $gr->gridY( $start, $step, $gr->border
      , $Ylabelfct, $black
      , '', $black);


   //horizontal scaling

   $step = 1; //grads
   $y = $gr->boxbottom+3 ; //+1*$gr->labelMetrics['LINEH'];
   $gr->gridX( $xlims['MIN'], $step, $y
      , $Xlabelfct, $black
      , '', $red);


   //draw the curves

   for( $i=0 ; $i<count($graphs) ; $i++ )
   {
      $graph= &$graphs[$i];

      $gr->curve($Xaxis, $graph['y'], $nr_points, $graph['c']);
      $gr->label($title_align+$graph['labelX'], $graph['labelY']
               , $graph['label'], $graph['c']);
   }


   //misc drawings

   if( @$_GET['show_time'] == 'y')
      $gr->label($gr->offsetX, 0,
                 sprintf('%0.2f ms', (getmicrotime()-$page_microtime)*1000), $black);


   $gr->imagesend();
}


function get_ratings_data(&$Xaxis, &$graphs, &$xlims, &$ylims)
{
/*****
 * An idea (p_rank: -30=<29kyu, -29=29kyu, -1=1kyu, 0=1dan, 6=7dan):
 * SELECT ROUND(Rating/100)-21 as p_rank,COUNT(*) as cnt FROM Ratinglog GROUP BY p_rank ORDER BY p_rank desc;
 * i.e. based on number of finished games
 * More conventional:
 * SELECT ROUND(Rating2/100)-21 as p_rank,COUNT(*) as cnt FROM Players GROUP BY p_rank ORDER BY p_rank desc;
 * active players:
 * SELECT ROUND(Rating2/100)-21 as p_rank,COUNT(*) as cnt FROM Players WHERE Players.Activity>$ActiveLevel1 GROUP BY p_rank ORDER BY p_rank desc;
 *****/

   global $ActiveLevel1, $T_;

   $Xaxis = array();
   $Xmin = 0;
   $Ymin = 0;
   $Ymax = 0;
   $graphs = array();
   for( $i=0; $i<3 ;$i++ )
   {
      switch( $i )
      {
      case 0:
         $name = $T_('Active users');
         $query =
            "SELECT ROUND(Rating2/100)-(".MIN_RANK.") as rank,COUNT(*) as cnt"
            . " FROM Players WHERE Rating2>=".MIN_RATING
               . " AND Activity>$ActiveLevel1"
            . " GROUP BY rank ORDER BY rank;" ;
         $color = array( 255,   0,   0);
       break;
      case 1:
         $name = $T_('Users');
         $query =
            "SELECT ROUND(Rating2/100)-(".MIN_RANK.") as rank,COUNT(*) as cnt"
            . " FROM Players WHERE Rating2>=".MIN_RATING
            . " GROUP BY rank ORDER BY rank;" ;
         $color = array( 255,   0, 200);
         break;
      case 2:
         $name = $T_('Rated games');
         $query =
            "SELECT ROUND(Rating/100)-(".MIN_RANK.") as rank,COUNT(*) as cnt"
            . " FROM Ratinglog WHERE Rating>=".MIN_RATING
            . " GROUP BY rank ORDER BY rank;" ;
         $color = array(   0, 180, 200);
         break;
      default:
         $query = '';
         $name = '';
         $color = 0;
         break;
      }
      if( $query )
      {
         $result = mysql_query( $query)
            or error('mysql_query_failed', 'statratingspng.query'.$i);

         $tmp = count($graphs);
         $graphs[] = array();
         $graphs[$tmp]['name'] = $name;
         $graphs[$tmp]['c'] = $color;
         $graphs[$tmp]['y'] = array();
         $graph= &$graphs[$tmp]['y'];
         while( $row = mysql_fetch_assoc($result) )
         {
            $rank = (int)@$row['rank'];
            $cnt = (int)@$row['cnt'];
            if( $cnt > $Ymax )
               $Ymax = $cnt;
            $graph[$rank] = $cnt;
            $tmp = (int)@$Xaxis[$rank];
            $Xaxis[$rank] = $tmp + $cnt;
         }
         mysql_free_result($result);
      }
   }

   ksort( $Xaxis);
   $Xmax = $Xmin;
   foreach( $Xaxis as $rank => $cnt )
   {
      if( $cnt > 0 )
         $Xmax = $rank;
   }
   while( $rank > $Xmax )
   {
      for( $i=count($graphs)-1 ; $i>=0 ; $i-- )
         unset($graphs[$i]['y'][$rank]);
      unset($Xaxis[$rank]);
      $rank--;
   }
   for( $rank=$Xmin ; $rank<=$Xmax ; $rank++ )
   {
      for( $i=count($graphs)-1 ; $i>=0 ; $i-- )
      {
         $graph= &$graphs[$i]['y'];
         if( !isset($graph[$rank]) )
            $graph[$rank] = 0;
      }
      $Xaxis[$rank] = $rank;
   }

   ksort( $Xaxis);
   for( $i=count($graphs)-1 ; $i>=0 ; $i-- )
      ksort($graphs[$i]['y']);

   $xlims = array('MIN'=>$Xmin, 'MAX'=>$Xmax);
   $ylims = array('MIN'=>$Ymin, 'MAX'=>$Ymax);
}

?>