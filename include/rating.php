<?php
/*
Dragon Go Server
Copyright (C) 2001  Erik Ouchterlony

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

function interpolate($value, $table, $extrapolate)
{
   foreach ( $table as $x )
      {
         $prev=$tmpprev;
      
         if( $value <= $x['KEY'] )
         {
            if( empty($prev) )
            {
               if( !$extrapolate )
                  error("value_out_of_range");
            }
            else
               return $prev['VAL'] + ($value-$prev['KEY'])/($x['KEY']-$prev['KEY']) * 
                  ($x['VAL']-$prev['VAL']);
         }

         $tmpprev = $x;
      }
  
   if( !$extrapolate )
      error("value_out_of_range");
  
   // extrapolate
  
   return $prev['VAL'] + ($value-$prev['KEY'])/($x['KEY']-$prev['KEY']) * 
      ($x['VAL']-$prev['VAL']);

}


function con($rating)
{
   $con_table[0]['KEY'] = 100;
   $con_table[0]['VAL'] = 116;
     
   $con_table[1]['KEY'] = 200;
   $con_table[1]['VAL'] = 110;

   $con_table[2]['KEY'] = 1300;
   $con_table[2]['VAL'] = 55;

   $con_table[3]['KEY'] = 2000;
   $con_table[3]['VAL'] = 27;

   $con_table[4]['KEY'] = 2400;
   $con_table[4]['VAL'] = 15;

   $con_table[5]['KEY'] = 2600;
   $con_table[5]['VAL'] = 11;

   $con_table[6]['KEY'] = 2700;
   $con_table[6]['VAL'] = 10;

   return interpolate($rating, $con_table, true);
}

function a($rating)
{
   $a_table[0]['KEY'] = 100;
   $a_table[0]['VAL'] = 200;
     
   $a_table[1]['KEY'] = 2700;
   $a_table[1]['VAL'] = 70;

   return interpolate($rating, $a_table, true);
}


// Using rating the EGF system: 
// http://www.european-go.org/rating/gor.html
//
// result: 0 - black win, 1 - white win

function change_rating(&$rating_W, &$rating_B, $result, $size, $komi, $handicap)
{

   $e = 0.014;

   $D = $rating_W - $rating_B;

   if( $handicap <= 0 )
      $handicap = 1;

   $H = ( $handicap - 0.5 - $komi / 13.0 );

   // Handicap value is about proportional to number of moves
   $H *= ( 256.0 / (($size-3.0)*($size-3.0)));

   $D -= 100.0 * $H;

   if( $D > 0 )
   {
      $SEB = 1.0/(1.0+exp($D/a($rating_B)));
      $SEW = 1.0-$SEB;
   }
   else
   {
      $SEW = 1.0/(1.0+exp(-$D/a($rating_W)));
      $SEB = 1.0-$SEW;
   }

   $SEW *= 1-$e;
   $SEB *= 1-$e;

   $sizefactor = (19 - abs($size-19))*(19 - abs($size-19)) / (19*19);

   $conW = con($rating_W) * $sizefactor;
   $conB = con($rating_B) * $sizefactor;

   $rating_W += $conW * ($result - $SEW);
   $rating_B += $conB * (1-$result - $SEB);

}

function suggest($rating_W, $rating_B, $size, $pos_komi=false)
{
   $H = ($rating_W - $rating_B) / 100.0;

   $H *= (($size-3.0)*($size-3.0)) / 256.0;  // adjust handicap to board size
   
   $H += 0.5; // advantage for playing first;

   $handicap = ( $pos_komi ? $handicap = ceil($H) : round($H) ); 

   if( $handicap <=1 ) $handicap = 1;

   $komi = round( 26.0 * ( $handicap - $H ) ) / 2;

   if( $handicap == 1 ) $handicap = 0;

   return array($handicap, $komi);
}

function update_rating(&$wRating, &$bRating, $score, $size, $komi, $handicap, 
$gid, $Black_ID, $White_ID)
{

   $result = 0.5;
   if( $score > 0 ) $result = 1.0;
   if( $score < 0 ) $result = 0.0;

   $bOld = $bRating;
   $wOld = $wRating;

   change_rating($wRating, $bRating, $result, $size, $komi, $handicap);

   mysql_query( "UPDATE Games SET Lastchanged=Lastchanged, Rated='DONE' WHERE ID=$gid" );

   mysql_query( "UPDATE Players SET Lastaccess=Lastaccess, Rating=$bRating, " .
                "RatingStatus='RATED' WHERE ID=$Black_ID" );

   mysql_query( "UPDATE Players SET Lastaccess=Lastaccess, Rating=$wRating, " .
                "RatingStatus='RATED' WHERE ID=$White_ID" );

   mysql_query("INSERT INTO RatingChange (uid,gid,diff) VALUES " . 
               "($Black_ID, $gid, " . ($bRating - $bOld) . "), " .
               "($White_ID, $gid, " . ($wRating - $wOld) . ")");
}

function echo_rating($rating, $show_percent=true)
{
   if( !isset($rating) ) return '';

   $rank_val = round($rating/100);

   if( $rank_val > 20.5 )
   {
      echo ( $rank_val - 20 ) . " dan";
   }
   else
   {
      echo ( 21 - $rank_val ) . " kyu";
   }

   if( $show_percent ) 
   {
      $percent = round($rating - $rank_val*100);
      echo ' ('. ( $percent > 0 ? '+' : '') . $percent . '%)';
   }
}

function read_rating($string)
{
   $string = strtolower($string);
   $pattern = "/^\s*([1-9][0-9]*)\s*(k|d|kyu|dan|gup)\s*(\(?\s*([+-]?[0-9]+\s*)%\s*\)?\s*)?$/";

   if( !preg_match($pattern, $string, $matches) )
      return null;

   $kyu = ( $matches[2] == 'dan' || $matches[2] == 'd' ) ? 2 : 1;
   return rank_to_rating($matches[1], $kyu) + $matches[4];
}

function rank_to_rating($val, $kyu)
{
   if( empty($kyu) )
      error("rank_not_rating");

   $rating = $val*100;
  
   if( $kyu == 1 )
      $rating = 2100 - $rating;
   else 
      $rating += 2000; 

   return $rating;
}

function convert_to_rating($string, $type)
{
   $max_start_rating = 2600;
   $min_start_rating = -900;
   
   if( empty($string) )
      return null;

   $string = strtolower($string);
   $val = doubleval($string);

   if( strpos($string, 'k') > 0 or strpos($string, 'gup') > 0 )
      $kyu = 1;
   if( strpos($string, 'd') > 0 )
      $kyu = 2;

   $igs_table[0]['KEY'] = -100;
   $igs_table[0]['VAL'] = 500;
    
   $igs_table[1]['KEY'] = 600;
   $igs_table[1]['VAL'] = 1000;
  
   $igs_table[2]['KEY'] = 1200;
   $igs_table[2]['VAL'] = 1500;
  
   $igs_table[3]['KEY'] = 1900;
   $igs_table[3]['VAL'] = 2100;
  
   $igs_table[4]['KEY'] = 2200;
   $igs_table[4]['VAL'] = 2400;


   switch( $type )
   {
      case 'dragonrating':
      {
         $rating = read_rating($string);
          
         if( !$rating )
            error("rank_not_rating");
          
      }
      break;
       
      case 'eurorating':
      {
         if( $kyu > 0 )
            error("rating_not_rank");
        
         $rating = $val;
      }
      break;

      case 'eurorank':
      {
         $rating = rank_to_rating($val, $kyu);
      }
      break;

      case 'aga':
      {
         $rating = rank_to_rating($val, $kyu);
        
         $rating -= 200.0;  // aga two stones weaker ?
      }
      break;

      
      case 'agarating':
      {
         if( $kyu > 0 )
            error("rating_not_rank");
        
         if( $val > 0 )
            $rating = $val*100 + 1950;
         else
            $rating = $val*100 + 2150;
        
         $rating -= 200.0;  // aga two stones weaker ?
      }
      break;


      case 'igs':
      {
         $rating = rank_to_rating($val, $kyu);
         $rating = interpolate($rating, $igs_table, true);
      }
      break;

      case 'igsrating':
      {
         if( $kyu > 0 )
            error("rating_not_rank");
             
         $rating = $val*100 - 1130 ;
         $rating = interpolate($rating, $igs_table, true);
      }
      break;

      case 'iytgg':
      case 'nngs':
      {
         $rating = rank_to_rating($val, $kyu);
    
         $rating += 100;  // one stone stronger
      }
      break;
      
      case 'nngsrating':
      {
         if( $kyu > 0 )
            error("rating_not_rank");
        
         $rating = $val - 900;
        
      }
      break;

      case 'japan':
      {
         $rating = rank_to_rating($val, $kyu);
    
         $rating -= 300;  // three stones weaker
      }
      break;
      

      case 'china':
      {
         $rating = rank_to_rating($val, $kyu);
    
         $rating += 100;  // one stone stronger
      }
      break;
      

      case 'korea':
      {
         $rating = rank_to_rating($val, $kyu);
    
         $rating += 400;  // four stones stronger
      }
      break;

      default:
      {
         error("wrong_rank_type");
      }
   }

   if( $rating > $max_start_rating or $rating < $min_start_rating ) 
      error("rating_out_of_range");

   return $rating;
}

?>