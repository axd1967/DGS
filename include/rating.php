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

$TranslateGroups[] = "Game";

$MAX_START_RATING = 2600;
$MIN_RATING = -900;

function interpolate($value, $table, $extrapolate)
{
   foreach ( $table as $x )
      {
         if( !empty($tmpprev) )
            $prev=$tmpprev;

         if( $value <= $x['KEY'] )
         {
            if( empty($prev) )
            {
               if( !$extrapolate )
                  error("value_out_of_range",'rat1');
            }
            else
               return $prev['VAL'] + ($value-$prev['KEY'])/($x['KEY']-$prev['KEY']) *
                  ($x['VAL']-$prev['VAL']);
         }

         $tmpprev = $x;
      }

   if( !$extrapolate )
      error("value_out_of_range",'rat2');

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

   $con_table[7]['KEY'] = 10000;
   $con_table[7]['VAL'] = 10;

   return interpolate($rating, $con_table, true);
}

function a($rating)
{
   $a_table[0]['KEY'] = 100;
   $a_table[0]['VAL'] = 200;

   $a_table[1]['KEY'] = 2700;
   $a_table[1]['VAL'] = 70;

   $a_table[1]['KEY'] = 10000;
   $a_table[1]['VAL'] = 70;

   return interpolate($rating, $a_table, true);
}


// Using rating the EGF system:
// http://www.european-go.org/rating/gor.html
// http://www.ujf.cas.cz/~cieply/GO/gor.html
//
// $result: 0 = black win, 1 = white win, 0.5 = jigo
function change_rating(&$rating_W, &$rating_B, $result, $size, $komi, $handicap, $factor=1)
{

   $e = 0.014;

   $D = $rating_W - $rating_B;

   if( $handicap < 1 )
      $handicap = 1;

   $H = ( $handicap - 0.5 - $komi / 13.0 );

   // Handicap value is about proportional to number of moves
   $H *= ( 256.0 / (($size-3.0)*($size-3.0)));

   $D -= 100.0 * $H;

   if( $D >= 0 ) //ratW-ratB
   {
      $SEB = 1.0/(1.0+exp($D/a($rating_B)));
      $SEW = 1.0-$SEB;
      if( $result != 0.5 )
         $SEW-= $e;
   }
   else
   {
      $SEW = 1.0/(1.0+exp(-$D/a($rating_W)));
      $SEB = 1.0-$SEW;
      if( $result != 0.5 )
         $SEB-= $e;
   }

   $sizefactor = (19 - abs($size-19))*(19 - abs($size-19)) / (19*19);

   $conW = con($rating_W) * $sizefactor * $factor;
   $conB = con($rating_B) * $sizefactor * $factor;

   $rating_W += $conW * ($result - $SEW);
   $rating_B += $conB * (1-$result - $SEB);

}

function suggest_proper($rating_W, $rating_B, $size, $pos_komi=false)
{
   $H = abs($rating_W - $rating_B) / 100.0;

   $H *= (($size-3.0)*($size-3.0)) / 256.0;  // adjust handicap to board size

   $H += 0.5; // advantage for playing first;

   $handicap = ( $pos_komi ? ceil($H) : round($H) );

   if( $rating_B == $rating_W )
      $swap = mt_rand(0,1);
   else
      $swap = ( $rating_B > $rating_W );

   if( $handicap < 1 ) $handicap = 1;

   $komi = round( 26.0 * ( $handicap - $H ) ) / 2;

   if( $handicap == 1 ) $handicap = 0;

   return array($handicap, $komi, $swap);
}

function suggest_conventional($rating_W, $rating_B, $size, $pos_komi=false)
{
   $handicap = abs($rating_W - $rating_B) / 100.0;

   $handicap = round($handicap * (($size-3.0)*($size-3.0)) / 256.0 );

   if( $handicap == 0 )
   {
      $komi = 6.5;
      $swap = mt_rand(0,1);
   }
   else
   {
      $komi = 0.5;
      $swap = ( $rating_B > $rating_W );
      if( $handicap == 1 )
         $handicap = 0;
   }

   return array($handicap, $komi, $swap);
}

function update_rating($gid)
{
   $query = "SELECT Games.*, ".
       "white.Rating as wRating, white.RatingStatus as wRatingStatus, " .
       "black.Rating as bRating, black.RatingStatus as bRatingStatus " .
       "FROM Games, Players as white, Players as black " .
       "WHERE Status='FINISHED' AND Rated='Y' AND Games.ID=$gid " .
       //"AND white.RatingStatus='RATED' " .
       //"AND black.RatingStatus='RATED' " .
       "AND white.ID=White_ID AND black.ID=Black_ID ";


   $result = mysql_query( $query );

   if( @mysql_num_rows($result) != 1 )
      return -1; //error or game not found (?or rate already done)

   $row = mysql_fetch_array( $result );
   extract($row);

   $too_few_moves = ($Moves < DELETE_LIMIT+$Handicap) ;
   // here $Rated=='N' is always false. See rating2 to update
   if( $too_few_moves or $Rated == 'N' or $wRatingStatus!='RATED' or $bRatingStatus!='RATED' )
   {
      mysql_query("UPDATE Games SET Rated='N' WHERE ID=$gid");
      if( $too_few_moves )
         return 1; //not rated game
      else
         return 2; //not rated game
   }

   $game_result = 0.5;
   if( $Score > 0 ) $game_result = 1.0;
   if( $Score < 0 ) $game_result = 0.0;

   $bOld = $bRating;
   $wOld = $wRating;

   change_rating($wRating, $bRating, $game_result, $Size, $Komi, $Handicap);

   mysql_query( "UPDATE Games SET Rated='Done' WHERE ID=$gid" );

   mysql_query( "UPDATE Players SET Rating=$bRating WHERE ID=$Black_ID" );

   mysql_query( "UPDATE Players SET Rating=$wRating WHERE ID=$White_ID" );

   mysql_query("INSERT INTO RatingChange (uid,gid,diff) VALUES " .
               "($Black_ID, $gid, " . ($bRating - $bOld) . "), " .
               "($White_ID, $gid, " . ($wRating - $wOld) . ")");

   return 0; //rated game
}

//
// EGF rating, see above URIs for documentation
//
function update_rating2($gid, $check_done=true)
{
   global $NOW, $MIN_RATING;

   $WithinPercent = 1/4;

   $query = "SELECT Games.*, ".
      "white.Rating2 as wRating, white.RatingStatus as wRatingStatus, " .
      "white.RatingMax as wRatingMax, white.RatingMin as wRatingMin, " .
      "black.Rating2 as bRating, black.RatingStatus as bRatingStatus, " .
      "black.RatingMax as bRatingMax, black.RatingMin as bRatingMin " .
      "FROM Games, Players as white, Players as black " .
      "WHERE Status='FINISHED' AND Games.ID=$gid " .
      ( $check_done ? "AND Rated!='Done' " : '' ) .
      "AND white.ID=White_ID AND black.ID=Black_ID";


   $result = mysql_query( $query ) or die(mysql_error());

   if( @mysql_num_rows($result) != 1 )
      return -1; //error or game not found or rate already done

   $row = mysql_fetch_assoc( $result );
   extract($row);

   $too_few_moves = ($Moves < DELETE_LIMIT+$Handicap) ;
   if( $too_few_moves or $Rated == 'N' or $wRatingStatus!='RATED' or $bRatingStatus!='RATED' )
   {
      mysql_query("UPDATE Games SET Rated='N'" .
                  ( is_numeric($bRating) ? ", Black_End_Rating=$bRating" : '' ) .
                  ( is_numeric($wRating) ? ", White_End_Rating=$wRating" : '' ) .
                  " WHERE ID=$gid LIMIT 1");
      if( $too_few_moves )
         return 1; //not rated game
      else
         return 2; //not rated game
   }

   $game_result = 0.5;
   if( $Score > 0 ) $game_result = 1.0;
   if( $Score < 0 ) $game_result = 0.0;

   $bOld = $bRating;
   $wOld = $wRating;

   // Calculate factor used to alter how much the the ratings are to be changed
   /*
     with R = ($bRatingMax - $bRatingMin)/($wRatingMax - $wRatingMin);
     and logMF(x), the MAX_FACTOR based logarithm of x:
       $bFactor = MAX_FACTOR ^ tanh( SLOPE_CONST * logMF( R ) );
       $wFactor = MAX_FACTOR ^ tanh( SLOPE_CONST * logMF(1/R) );
   */
   $Factor = log(($bRatingMax - $bRatingMin)/($wRatingMax - $wRatingMin));

   $MAX_FACTOR = 2.5;
   $MAX_LN_FACTOR = log($MAX_FACTOR);
   $SLOPE_CONST = 1;

   $tmp = exp(2*$SLOPE_CONST*$Factor/$MAX_LN_FACTOR);

   $bFactor = exp($MAX_LN_FACTOR * ($tmp - 1) / ($tmp + 1));
   $wFactor = 1/$bFactor;
   $maxminFactor = 0.5;

   // Update ratings

   $bTmp = $bOld;
   change_rating($wRating, $bTmp, $game_result, $Size, $Komi, $Handicap, $wFactor);
   if( $wRating < $MIN_RATING )
      $wRating = $MIN_RATING;

   $wFactor *= $maxminFactor;
   $bTmp = $bOld;
   change_rating($wRatingMax, $bTmp, $game_result, $Size, $Komi, $Handicap, $wFactor);

   $bTmp = $bOld;
   change_rating($wRatingMin, $bTmp, $game_result, $Size, $Komi, $Handicap, $wFactor);


   $wTmp = $wOld;
   change_rating($wTmp, $bRating, $game_result, $Size, $Komi, $Handicap, $bFactor);
   if( $bRating < $MIN_RATING )
      $bRating = $MIN_RATING;

   $bFactor *= $maxminFactor;
   $wTmp = $wOld;
   change_rating($wTmp, $bRatingMax, $game_result, $Size, $Komi, $Handicap, $bFactor);

   $wTmp = $wOld;
   change_rating($wTmp, $bRatingMin, $game_result, $Size, $Komi, $Handicap, $bFactor);


   // Check that $Rating is within the central $WithinPercent of [$RatingMin,$RatingMax]

   $k = (1-$WithinPercent)/2;
   $Dist = ($bRatingMax - $bRatingMin) * $k;

   if( $bRating > $bRatingMax - $Dist )
      $bRatingMax = ($bRating - $bRatingMin * $k) / (1-$k);

   if( $bRating < $bRatingMin + $Dist )
      $bRatingMin = ($bRating - $bRatingMax * $k) / (1-$k);

   $Dist = ($wRatingMax - $wRatingMin) * $k;

   if( $wRating > $wRatingMax - $Dist )
      $wRatingMax = ($wRating - $wRatingMin * $k) / (1-$k);

   if( $wRating < $wRatingMin + $Dist )
      $wRatingMin = ($wRating - $wRatingMax * $k) / (1-$k);


   mysql_query( "UPDATE Games SET Rated='Done', " .
                "Black_End_Rating=$bRating, White_End_Rating=$wRating " .
                "WHERE ID=$gid LIMIT 1" );

   mysql_query( "UPDATE Players SET Rating2=$bRating, " .
                "RatingMin=$bRatingMin, RatingMax=$bRatingMax " .
                "WHERE ID=$Black_ID LIMIT 1" );

   mysql_query( "UPDATE Players SET Rating2=$wRating, " .
                "RatingMin=$wRatingMin, RatingMax=$wRatingMax " .
                "WHERE ID=$White_ID LIMIT 1" );

   mysql_query('INSERT INTO Ratinglog' .
               '(uid,gid,Rating,RatingMin,RatingMax,RatingDiff,Time) VALUES ' .
               "($Black_ID, $gid, $bRating, $bRatingMin, $bRatingMax, " .
               ($bRating - $bOld) . ", '$Lastchanged'), " .
               "($White_ID, $gid, $wRating, $wRatingMin, $wRatingMax, " .
               ($wRating - $wOld) . ", '$Lastchanged') ")
      or die(mysql_error());

   return 0; //rated game
}


//
// Glicko-2, see http://www.glicko.com/glicko2.doc/example.html
//
function update_rating_glicko($gid, $check_done=true)
{
   global $NOW;

   // Step 1

   $tau = 1.2;
   $tau2 = 1/($tau*$tau);

   $query = "SELECT Games.*, ".
      "white.RatingGlicko as wRating, " .
      "white.RatingStatus as wRatingStatus, " .
      "white.RatingGlicko_Deviation as wRatingDeviation, " .
      "white.RatingGlicko_Volatility as wRatingVolatility, " .
      "black.RatingGlicko as bRating, " .
      "black.RatingStatus as bRatingStatus, " .
      "black.RatingGlicko_Deviation as bRatingDeviation, " .
      "black.RatingGlicko_Volatility as bRatingVolatility " .
      "FROM Games, Players as white, Players as black " .
      "WHERE Status='FINISHED' AND Games.ID=$gid " .
      ( $check_done ? "AND Rated!='Done' " : '' ) .
      "AND white.ID=White_ID AND black.ID=Black_ID ";


   $result = mysql_query( $query ) or die(mysql_error());

   if( @mysql_num_rows($result) != 1 )
      return -1; //error or game not found or rate already done

   $row = mysql_fetch_assoc( $result );
   extract($row);

   $too_few_moves = ($Moves < DELETE_LIMIT+$Handicap) ;
   if( $too_few_moves or $Rated == 'N' or $wRatingStatus!='RATED' or $bRatingStatus!='RATED' )
   {
      mysql_query("UPDATE Games SET Rated='N'" .
                  ( is_numeric($bRating) ? ", Black_End_Rating=$bRating" : '' ) .
                  ( is_numeric($wRating) ? ", White_End_Rating=$wRating" : '' ) .
                  " WHERE ID=$gid LIMIT 1");
      if( $too_few_moves )
         return 1; //not rated game
      else
         return 2; //not rated game
   }

   $game_result = 0.5;
   if( $Score > 0 ) $game_result = 1.0; // White won
   if( $Score < 0 ) $game_result = 0.0; // Black won

   $D = $wRating - $bRating;

   if( $Handicap <= 0 )
      $Handicap = 1;

   $H = ( $Handicap - 0.5 - $Komi / 13.0 );

   // Handicap value is about proportional to number of moves
   $H *= ( 256.0 / (($Size-3.0)*($Size-3.0)));

   $p = 400/M_LN10;

   $D -= 100.0 * $H;
   $D /= $p;

   // Step 2

   $w_mu = $wRating/$p;
   $b_mu = $bRating/$p;
   $w_phi = $wRatingDeviation/$p;
   $b_phi = $bRatingDeviation/$p;

   // Step 3
   $w_g = 1/sqrt(1+3*($w_phi/M_PI)*($w_phi/M_PI));
   $b_g = 1/sqrt(1+3*($b_phi/M_PI)*($b_phi/M_PI));

   $w_e = 1/(1+exp(-$b_g*($D)));
   $b_e = 1/(1+exp(-$w_g*(-$D)));
   $w_v = 1/($b_g*$b_g * $w_e * (1-$w_e));
   $b_v = 1/($w_g*$w_g * $b_e * (1-$b_e));

   // Step 4
   $w_delta = $w_v * $b_g * ($game_result - $w_e);
   $b_delta = $b_v * $w_g * (1 - $game_result - $b_e);

   // Step 5
//    $a = 2*log($wRatingVolatility);

//    $x = $a;
//    $x_last = $x+10;
//    while( abs($x_last - $x) > 1e-14 )
//    {
//       $exp_x = exp($x);
//       $d = $w_phi*$w_phi + $w_v + $exp_x;

//       $h1 = -($x - $a)*$tau2 + 0.5*$exp_x*(($w_delta*$w_delta-$d)/($d*$d));
//       $h2 = -$tau2 - 0.5*$exp_x*( ($w_phi*$w_phi + $w_v)/($d*$d) +
//                                   $delta*$delta*($w_phi*$w_phi + $w_v - $exp_x)/($d*$d*$d));
//       $x_last = $x;
//       $x = $x - $h1/$h2;
//    }
//    $w_sigma = exp($x/2);
//    echo "<br>w_sigma: $wRatingVolatility";
//    echo "<br>w_sigma: $w_sigma";

   $sigma = $wRatingVolatility;
   $d = $w_phi*$w_phi + $w_v + $sigma;
   $k = $tau*$sigma/(2*$d);

   $w_sigma = $sigma*exp($k*$k * ($w_delta*$w_delta - $d));

//    $a = 2*log($bRatingVolatility);

//    $x = $a;
//    $x_last = $x+10;
//    while( abs($x_last - $x) > 1e-14 )
//    {
//       $exp_x = exp($x);
//       $d = $b_phi*$b_phi + $b_v + $exp_x;
//       $h1 = -($x - $a)*$tau2 + 0.5*$exp_x*(($b_delta*$b_delta-$d)/($d*$d));
//       $h2 = -$tau2 - 0.5*$exp_x*( ($b_phi*$b_phi + $b_v)/($d*$d) +
//                                   $delta*$delta*($b_phi*$b_phi + $b_v - $exp_x)/($d*$d*$d));

//       $x_last = $x;
//       $x = $x - $h1/$h2;
//    }

//   $b_sigma = exp($x/2);
   $sigma = $bRatingVolatility;
   $d = $b_phi*$b_phi + $b_v + $sigma*$sigma;
   $k = $tau*$sigma/(2*$d);
   $b_sigma = $sigma*exp($k*$k * ($b_delta*$b_delta - $d));

   // Step 6
   $w_phi = sqrt($w_phi*$w_phi + $w_sigma*$w_sigma);
   $b_phi = sqrt($b_phi*$b_phi + $b_sigma*$b_sigma);

   // Step 7,8
   $w_phi = 1/sqrt(1/($w_phi*$w_phi) + 1/$w_v);
   $b_phi = 1/sqrt(1/($b_phi*$b_phi) + 1/$b_v);

   $w_mu = $p * ( $w_mu + $w_phi * $w_phi * $b_g * ($game_result - $w_e) );
   $b_mu = $p * ( $b_mu + $b_phi * $b_phi * $w_g * (1 - $game_result - $b_e) );

   $w_phi *= $p;
   $b_phi *= $p;

   mysql_query( "UPDATE Players SET RatingGlicko=$w_mu, " .
                "RatingGlicko_Deviation=$w_phi, RatingGlicko_Volatility=$w_sigma " .
                "WHERE ID=$White_ID LIMIT 1" ) or die(mysql_error());

   mysql_query( "UPDATE Players SET RatingGlicko=$b_mu, " .
                "RatingGlicko_Deviation=$b_phi, RatingGlicko_Volatility=$b_sigma " .
                "WHERE ID=$Black_ID LIMIT 1" ) or die(mysql_error());

   mysql_query('INSERT INTO RatinglogGlicko' .
               '(uid,gid,RatingGlicko,RatingGlicko_Deviation,RatingGlicko_Volatility,RatingDiff,Time) VALUES ' .
               "($Black_ID, $gid, $b_mu, $b_phi, $b_sigma, " .
               ($b_mu - $bRating) . ",'$Lastchanged'), " .
               "($White_ID, $gid, $w_mu, $w_phi, $w_sigma, " .
               ($w_mu - $wRating) . ", '$Lastchanged') ")
      or die(mysql_error());

   echo "<br>$gid: $White_ID - $Black_ID    $w_mu, $w_phi, $w_sigma - $b_mu, $b_phi, $b_sigma\n";

   return 0; //rated game
}

// To avoid too many translations
//WARNING: the translation database must be available when this file is included.
//$dan = T_('dan');
//$kyu = T_('kyu');
$dan = 'dan';
$kyu = 'kyu';

function echo_rating($rating, $show_percent=true, $graph_uid=0, $keep_english=false)
{
   $T_= ( $keep_english ? 'fnop' : 'T_' );
   //global $dan, $kyu;

   if( !isset($rating) ) return '';

   $spc = ( $show_percent === true ? '&nbsp;' : ' ' );

   $rank_val = round($rating/100.0);

   $string = ($graph_uid > 0 ?
              "<a href=\"ratinggraph.php?uid=$graph_uid\"><font color=black>" : '');

   if( $rank_val > 20.5 )
   {
      $string .= ( $rank_val - 20 ) . $spc . $T_('dan');
   }
   else
   {
      $string .= ( 21 - $rank_val ) . $spc . $T_('kyu');
   }

   if( $show_percent )
   {
      $percent = round($rating - $rank_val*100.0);
      $string .= $spc . '('. ( ($rating - $rank_val*100.0 > 0) ? '+' : '') . $percent . '%)';
   }

   if( $graph_uid > 0 )
      $string .= '</font></a>';
   return $string;
}

function read_rating($string)
{
   $string = strtolower($string);
   $pattern = "/^\s*([1-9][0-9]*)\s*(k|d|kyu|dan|gup)\s*(\(?\s*([+-]?[0-9]+\s*)%\s*\)?\s*)?$/";

   if( !preg_match($pattern, $string, $matches) )
      return null;

   $kyu = ( $matches[2] == 'dan' || $matches[2] == 'd' ) ? 2 : 1;

   return rank_to_rating($matches[1], $kyu) + @$matches[4];
}

function rank_to_rating($val, $kyu)
{
   if( empty($kyu) )
      error("rank_not_rating", "val: $val  kyu: $kyu");

   $rating = $val*100;

   if( $kyu == 1 )
      $rating = 2100 - $rating;
   else
      $rating += 2000;

   return $rating;
}


function get_rating_at($uid, $date)
{
   $result = mysql_query( "SELECT Rating FROM Ratinglog " .
                          "WHERE uid='$uid' AND Time<='$date' " .
                          "ORDER BY Time DESC LIMIT 1" )
      or die(mysql_error());

   if( @mysql_num_rows($result) != 1 )
      $result = mysql_query( "SELECT InitialRating AS Rating FROM Players WHERE ID='$uid'" );

   $row = mysql_fetch_array( $result );

   return $row['Rating'];
}


function convert_to_rating($string, $type)
{
   global $MAX_START_RATING, $MIN_RATING;

   if( empty($string) )
      return null;

   $string = strtolower($string);
   $string = str_replace(Chr(160), ' ', $string); // change to normal space char.

   $val = doubleval($string);

   if( strpos($string, 'k') > 0 or strpos($string, 'gup') > 0 )
      $kyu = 1;
   if( strpos($string, 'd') > 0 )
      $kyu = 2;

   $igs_table[0]['KEY'] = 200;
   $igs_table[0]['VAL'] = 500;

   $igs_table[1]['KEY'] = 900;
   $igs_table[1]['VAL'] = 1000;

   $igs_table[2]['KEY'] = 1500;
   $igs_table[2]['VAL'] = 1500;

   $igs_table[3]['KEY'] = 2400;
   $igs_table[3]['VAL'] = 2400;


   switch( $type )
   {
      case 'dragonrating':
      {
         $rating = read_rating($string);

         if( !is_numeric($rating) )
            error("rank_not_rating", "type: $type  Rating: $rating  string: $string");
      }
      break;

      case 'eurorating':
      {
         if( $kyu > 0 )
            error("rating_not_rank", "type: $type  val: $val  kyu: $kyu");

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
            error("rating_not_rank", "type: $type  val: $val  kyu: $kyu");

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

//       case 'igsrating':
//       {
//          if( $kyu > 0 )
//             error("rating_not_rank", "type: $type  val: $val  kyu: $kyu");

//          $rating = $val*100 - 1130 ;
//          $rating = interpolate($rating, $igs_table, true);
//       }
//       break;

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
            error("rating_not_rank", "type: $type  val: $val  kyu: $kyu");

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

   if( $rating > $MAX_START_RATING and $rating-50 <= $MAX_START_RATING )
      $rating = $MAX_START_RATING;

   if( $rating < $MIN_RATING and $rating+50 >= $MIN_RATING )
      $rating = $MIN_RATING;

   if( $rating > $MAX_START_RATING or $rating < $MIN_RATING )
      error("rating_out_of_range");

   return $rating;
}

?>