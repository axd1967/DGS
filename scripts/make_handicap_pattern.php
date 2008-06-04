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

chdir( '../' );
require_once('include/std_functions.php' );
require_once('include/coords.php');
chdir( 'pattern/' );


define('MAX_PATTERN_SIZE',51);


{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $player_level = (int)$player_row['admin_level'];
   if( !($player_level & ADMIN_DATABASE) )
      error("adminlevel_too_low");


   start_html( 'handicap_pattern', 0 );


//================================================================

$comment= "Standard handicap patterns don't take care about colors.
They just use the moves coordinates and their succession.
If the current variation does not held enought moves, the next variation is used (so, keep the variations in the order from shortest to longest).
For instance, on a 19x19 board:
- a 5 stones handicap will stay in the main branch,
- but a 6 stones handicap will switch to the variation of move #5 to find the 5th and 6th stones coordinates.
";

  $step_odd=array( //{
  1 => '
;B[{$ds-$dl,$dl}]
C[$comment]',
  2 => '
;W[{$dl,$ds-$dl}]',
  3 => '
;B[{$ds-$dl,$ds-$dl}]',
  4 => '
;W[{$dl,$dl}]',
  5 => '
(
;B[{$dm,$dm}]
)
(
;B[{$ds-$dl,$dm}]',
  6 => '
;W[{$dl,$dm}]',
  7 => '
(
;B[{$dm,$dm}]
)
(
;B[{$dm,$ds-$dl}]',
  8 => '
;W[{$dm,$dl}]',
  9 => '
;B[{$dm,$dm}]',
  10 => '
;W[{$ds-$dn,$dn}]',
  11 => '
;B[{$dn,$ds-$dn}]',
  12 => '
;W[{$ds-$dn,$ds-$dn}]',
  13 => '
;B[{$dn,$dn}]',

  14 => '
;W[{$ds-$db,$db}]',
  15 => '
;B[{$db,$ds-$db}]',
  16 => '
;W[{$ds-$db,$ds-$db}]',
  17 => '
;B[{$db,$db}]',

  18 => '
;W[{$ds-$db,$dm}]',
  19 => '
;B[{$db,$dm}]',
  20 => '
;W[{$dm,$ds-$db}]',
  21 => '
;B[{$dm,$db}]',

  22 => '
;W[{$ds-$dl,$dn}]',
  23 => '
;B[{$dl,$ds-$dn}]',
  24 => '
;W[{$ds-$dn,$ds-$dl}]',
  25 => '
;B[{$dn,$dl}]',

  26 => '
;W[{$ds-$dn,$dl}]',
  27 => '
;B[{$dn,$ds-$dl}]',
  28 => '
;W[{$ds-$dl,$ds-$dn}]',
  29 => '
;B[{$dl,$dn}]',

  30 => '
;W[{$ds-$dn,$dm}]',
  31 => '
;B[{$dn,$dm}]',
  32 => '
;W[{$dm,$ds-$dn}]',
  33 => '
;B[{$dm,$dn}]',

  34 => '
;W[{$ds-$db,$dn}]',
  35 => '
;B[{$db,$ds-$dn}]',
  36 => '
;W[{$ds-$dn,$ds-$db}]',
  37 => '
;B[{$dn,$db}]',

  38 => '
;W[{$ds-$dn,$db}]',
  39 => '
;B[{$dn,$ds-$db}]',
  40 => '
;W[{$ds-$db,$ds-$dn}]',
  41 => '
;B[{$db,$dn}]',

  );//}$step_odd


  $step_even=array( //{
  1 => '
;B[{$ds-$dl,$dl}]
C[$comment]',
  2 => '
;W[{$dl,$ds-$dl}]',
  3 => '
;B[{$ds-$dl,$ds-$dl}]',
  4 => '
;W[{$dl,$dl}]',
  5 => '
(
;B[{$dm,$ds-$dm}]
)
(
;B[{$ds-$dl,$ds-$dm}]',
  6 => '
;W[{$dl,$ds-$dm}]',
  7 => '
(
;B[{$dm,$ds-$dm}]
)
(
;B[{$dm,$ds-$dl}]',
  8 => '
;W[{$dm,$dl}]',
  9 => '
;B[{$dm,$ds-$dm}]',
  10 => '
;W[{$ds-$dn,$dn}]',
  11 => '
;B[{$dn,$ds-$dn}]',
  12 => '
;W[{$ds-$dn,$ds-$dn}]',
  13 => '
;B[{$dn,$dn}]',

  14 => '
;W[{$ds-$db,$db}]',
  15 => '
;B[{$db,$ds-$db}]',
  16 => '
;W[{$ds-$db,$ds-$db}]',
  17 => '
;B[{$db,$db}]',

  18 => '
;W[{$ds-$db,$ds-$dm}]',
  19 => '
;B[{$db,$ds-$dm}]',
  20 => '
;W[{$dm,$ds-$db}]',
  21 => '
;B[{$dm,$db}]',

  22 => '
;W[{$ds-$dl,$dn}]',
  23 => '
;B[{$dl,$ds-$dn}]',
  24 => '
;W[{$ds-$dn,$ds-$dl}]',
  25 => '
;B[{$dn,$dl}]',

  26 => '
;W[{$ds-$dn,$dl}]',
  27 => '
;B[{$dn,$ds-$dl}]',
  28 => '
;W[{$ds-$dl,$ds-$dn}]',
  29 => '
;B[{$dl,$dn}]',

  30 => '
;W[{$ds-$dn,$ds-$dm}]',
  31 => '
;B[{$dn,$ds-$dm}]',
  32 => '
;W[{$dm,$ds-$dn}]',
  33 => '
;B[{$dm,$dn}]',

  34 => '
;W[{$ds-$db,$dn}]',
  35 => '
;B[{$db,$ds-$dn}]',
  36 => '
;W[{$ds-$dn,$ds-$db}]',
  37 => '
;B[{$dn,$db}]',

  38 => '
;W[{$ds-$dn,$db}]',
  39 => '
;B[{$dn,$ds-$db}]',
  40 => '
;W[{$ds-$db,$ds-$dn}]',
  41 => '
;B[{$db,$dn}]',

  );//}$step_even


  $step_s7=array( //{
  1 => '
;B[{4,2}]
C[$comment]',
  2 => '
;W[{2,4}]',
  3 => '
;B[{4,4}]',
  4 => '
;W[{2,2}]',
  5 => '
(
;B[{3,3}]
)
(
;B[{5,3}]',
  6 => '
;W[{1,3}]',
  7 => '
(
;B[{3,3}]
)
(
;B[{3,5}]',
  8 => '
;W[{3,1}]',
  9 => '
;B[{3,3}]',
  10 => '
;W[{5,1}]',
  11 => '
;B[{1,5}]',
  12 => '
;W[{5,5}]',
  13 => '
;B[{1,1}]',

  );//}$step_s7


  $ok= 1;
  for( $size=5 ; $size<=MAX_BOARD_SIZE ; $size++ ) {
    $dst = "standard_handicap_$size";
    echo "<br>\n".$dst;

    $sgf= "(;FF[4]GM[1]
PC[$FRIENDLY_LONG_NAME: $HOSTBASE]
SZ[$size]
GC[Standard handicap patterns]
GN[$dst]";

    $npar=1;
    
    if( $size == 7 )
      $step=&$step_s7;
    else if( $size & 1 )
      $step=&$step_odd;
    else
      $step=&$step_even;

    $ds= $size-1;
    $dl= ($size>11 ? 3 : ($size>7 ? 2 : 1 ));
    $dm= ceil($ds/2);
    $dn= floor(($dl+$dm)/2);
    $db= min(ceil($dl/2),$dl-1);
    
    $cnt=1;
    for( $ha=1 ; $ha<=MAX_PATTERN_SIZE ; $ha++ ) {    
      if( $size<=6 ) {
        if( $cnt>8 ) break;
/*
      } else if( $size<=7 ) {
        if( $cnt>13 ) break;
*/
      } else if( $size<=13 ) {
        if( $cnt>17 ) break;
        if( $ha>=10 && $ha<=13 && $dn==$dl+1 ) continue;
      } else if( $size<=16 ) {
        if( $size<=14 && $cnt>21 ) break;
        //if( $cnt>29 ) break;
        if( $ha>=10 && $ha<=13 && $dn==$dl+1 ) continue;
      }
    
      $cnt++;  
      $str=@$step[$ha];
      if( !$str ) continue;
      $npar+= count(explode('(',$str))-count(explode(')',$str));
      
      $str=str_replace('{','".number2sgf_coords(',$str);
      $str=str_replace('}',',$size)."',$str);
      $str='$str="'.$str.'";';
      //echo "<br>\n".$str;
      eval($str);
      $sgf.= $str;
    }
    
    while( $npar-- >0 )
      $sgf.= '
)';
    $ok=write_to_file($dst.".sgf", $sgf, 0);
    if( !$ok )
      break;
  }
   if( !$ok )
      echo "\n<br>Can't write ",$dst;
   else
      echo "\n<br>Done.";

   end_html();
}
?>
