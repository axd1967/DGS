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
                {
                  header("Location: error.php?err=value_out_of_range");
                  exit;
                }              
            }
          else
            return $prev['VAL'] + ($value-$prev['KEY'])/($x['KEY']-$prev['KEY']) * 
              ($x['VAL']-$prev['VAL']);
        }

      $tmpprev = $x;
    }
  
  if( !$extrapolate )
    {
      header("Location: error.php?err=value_out_of_range");
      exit;
    }
  
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


function update_rating(&$rating_A, &$rating_B, $result)
{
  $e = 0.014;

  $D = abs($rating_B - $rating_A);

  if( $rating_A > $rating_B )
    {
      $SEB = 1.0/(1.0+exp($D/a($rating_B)));
      $SEA = 1.0-$SEB;
    }
  else
    {
      $SEA = 1.0/(1.0+exp($D/a($rating_A)));
      $SEB = 1.0-$SEA;
    }

  $SEA *= 1-$e;
  $SEB *= 1-$e;

  $conA = con($rating_A); 
  $conB = con($rating_B); 

  $rating_A += $conA * ($result - $SEA);
  $rating_B += $conB * (1-$result - $SEB);
}

function echo_rating($rating, $show_percent)
{
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
      $percent = round($rating*2 - $rank_val*200);
      echo ' ('. ( $percent > 0 ? '+' : '') . $percent . '%)';
    }
}


function rank_to_rating($val, $kyu)
{
  if( empty($kyu) )
    {
      header("Location: error.php?err=rank_not_rating");
      exit;
    } 

  $rating = $val*100;
  
  if( $kyu == 1 )
    $rating = 2100 - $rating;
  else 
    $rating += 2000; 

  return $rating;
}

function convert_to_rating($string, $type)
{
  $string = strtolower($string);
  
  $val = doubleval($string);

  if( strpos($string, 'kyu') > 0 or strpos($string, 'gup') > 0 )
      $kyu = 1;

  if( strpos($string, 'dan') > 0 )
      $kyu = 2;

  switch( $type )
    {
    case 'eurorating':
      {
        if( $kyu > 0 )
          {
            header("Location: error.php?err=rating_not_rank");
            exit;
          } 
        
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
          {
            header("Location: error.php?err=rating_not_rank");
            exit;
          } 
        
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
        
        $igs_table[0]['KEY'] = -300;
        $igs_table[0]['VAL'] = 500;
        
        $igs_table[1]['KEY'] = 500;
        $igs_table[1]['VAL'] = 1200;

        $igs_table[2]['KEY'] = 1100;
        $igs_table[2]['VAL'] = 1600;

        $igs_table[3]['KEY'] = 1700;
        $igs_table[3]['VAL'] = 2000;

        $igs_table[4]['KEY'] = 2200;
        $igs_table[4]['VAL'] = 2400;
        

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

      header("Location: error.php?err=wrong_rank_type");
      exit;
    }

  return $rating;
}

?>