<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Game";

require_once( "include/std_functions.php" ); // consts


// table for interpolating rating
$IGS_TABLE = array(
   array( 'KEY' =>  200, 'VAL' =>  500 ),
   array( 'KEY' =>  900, 'VAL' => 1000 ),
   array( 'KEY' => 1500, 'VAL' => 1500 ),
   array( 'KEY' => 2400, 'VAL' => 2400 ), // 4d
);

// table for interpolating rating
$KGS_TABLE = array(
   array( 'KEY' =>  400, 'VAL' =>    0 ),
   array( 'KEY' => 1300, 'VAL' => 1100 ), // 8k
   array( 'KEY' => 2000, 'VAL' => 1900 ), // 1k
   array( 'KEY' => 2700, 'VAL' => 2600 ), // 7d -> 6d
);

$A_TABLE = array(
   array( 'KEY' =>   100, 'VAL' =>  200 ),
   array( 'KEY' =>  2700, 'VAL' =>   70 ),
   array( 'KEY' => 10000, 'VAL' =>   70 ),   //higher, should be constant
);

$CON_TABLE = array(
   array( 'KEY' =>   100, 'VAL' => 116 ),
   array( 'KEY' =>   200, 'VAL' => 110 ),
   array( 'KEY' =>  1300, 'VAL' =>  55 ),
   array( 'KEY' =>  2000, 'VAL' =>  27 ),
   array( 'KEY' =>  2400, 'VAL' =>  15 ),
   array( 'KEY' =>  2600, 'VAL' =>  11 ),
   array( 'KEY' =>  2700, 'VAL' =>  10 ),
   array( 'KEY' => 10000, 'VAL' =>  10 ),   //higher, should be constant
);


function table_interpolate($value, $table, $extrapolate=false)
{
   foreach( $table as $x )
   {
      if( !empty($tmpprev) )
         $prev=$tmpprev;

      if( $value <= $x['KEY'] )
      {
         if( empty($prev) )
         {
            if( !$extrapolate )
               error('value_out_of_range','table_interpolate1');
         }
         else
         {
            //i.e.: $extrapolate = true; break;
            return $prev['VAL'] + ($value-$prev['KEY'])/($x['KEY']-$prev['KEY']) *
               ($x['VAL']-$prev['VAL']);
         }
      }

      $tmpprev = $x;
   }

   if( !$extrapolate )
      error('value_out_of_range','table_interpolate2');

   // extrapolate

   return $prev['VAL'] + ($value-$prev['KEY'])/($x['KEY']-$prev['KEY']) *
      ($x['VAL']-$prev['VAL']);
}


function con_value($rating)
{
   global $CON_TABLE;
   return table_interpolate($rating, $CON_TABLE, true);
}

function a_value($rating)
{
   global $A_TABLE;
   return table_interpolate($rating, $A_TABLE, true);
}


// Handicap value is about inversely proportional to number of moves
// must be 1.0 for a 19x19 board
function handicapfactor( $size)
{
   $size-= 3.0;
   return ($size*$size) / 256.0;
}

// Using rating the EGF system:
// http://www.european-go.org/rating/gor.html
// http://gemma.ujf.cas.cz/~cieply/GO/gor.html
//
// $result: 0 = black win, 1 = white win, 0.5 = jigo
function change_rating(&$rating_W, &$rating_B, $result, $size, $komi, $handicap, $factor=1)
{
   $e = 0.014;

   $D = $rating_W - $rating_B;

   if( $handicap < 1 )
      $handicap = 1;

   $H = ( $handicap - 0.5 - $komi / STONE_VALUE );

   // Restore true rating diff from handicap
   $H /= handicapfactor( $size);

   $D -= 100.0 * $H;

   if( $D >= 0 ) //ratW-ratB
   {
      $SEB = 1.0/(1.0+exp($D/a_value($rating_B)));
      $SEW = 1.0-$SEB;
      if( $result != 0.5 )
         $SEW-= $e;
   }
   else
   {
      $SEW = 1.0/(1.0+exp(-$D/a_value($rating_W)));
      $SEB = 1.0-$SEW;
      if( $result != 0.5 )
         $SEB-= $e;
   }

   $sizefactor = (19 - abs($size-19))*(19 - abs($size-19)) / (19*19);

   $conW = con_value($rating_W) * $sizefactor * $factor;
   $conB = con_value($rating_B) * $sizefactor * $factor;

   $rating_W += $conW * ($result - $SEW);
   $rating_B += $conB * (1-$result - $SEB);
}

// (handi,komi,iamblack,is_nigiri) = suggest_proper(my_rating, $opp_rating, size)
// NOTE: iamblack/is_nigiri is <>''
function suggest_proper($rating_W, $rating_B, $size, $positive_komi=false)
{
   $H = abs($rating_W - $rating_B) / 100.0;

   // Handicap value is about proportional to number of moves
   $H *= handicapfactor( $size);

   $H += 0.5; // advantage for playing first;

   $handicap = ( $positive_komi ? ceil($H) : round($H) );
   // temporary, there is no 0 handicap stone game in this calculus. An equal
   // game is a 1 stone game where black play his handicap stone where he want.
   if( $handicap < 1 ) $handicap = 1;

   $is_nigiri = ( $rating_B == $rating_W );
   if( $is_nigiri )
      $iamblack = mt_rand(0,1); // nigiri on same rating
   else
      $iamblack = ( $rating_B > $rating_W );

   $komi = round( 2.0 * STONE_VALUE * ( $handicap - $H ) ) / 2.0;

   if( $handicap == 1 ) $handicap = 0; //back to the 0 handicap habit

   return array( $handicap, $komi, ($iamblack ? 1:0), ($is_nigiri ? 1:0) );
}

// (handi,komi,iamblack,is_nigiri) = suggest_conventional(my_rating, $opp_rating, size)
// NOTE: iamblack/is_nigiri is <>''
function suggest_conventional($rating_W, $rating_B, $size, $positive_komi=false)
{
   $H = abs($rating_W - $rating_B) / 100.0;

   // Handicap value is about proportional to number of moves
   $H *= handicapfactor( $size);

   $handicap = round($H);
   if( $handicap == 1 ) $handicap = 0;

   $komi = ( $handicap == 0 ) ? STONE_VALUE / 2.0 : 0.5;

   // NOTE: nigiri was used on handicap==0, but now manual-setup should be used for that
   $is_nigiri = ( $rating_B == $rating_W );
   if( $is_nigiri )
      $iamblack = mt_rand(0,1); // nigiri on same rating
   else
      $iamblack = ( $rating_B > $rating_W );

   return array( $handicap, $komi, ($iamblack ? 1:0), ($is_nigiri ? 1:0) );
}

/* obsolete
function update_rating($gid)
{
   $query = "SELECT Games.*, ".
       "white.Rating as wRating, white.RatingStatus as wRatingStatus, " .
       "black.Rating as bRating, black.RatingStatus as bRatingStatus " .
       "FROM (Games, Players as white, Players as black) " .
       "WHERE Status='FINISHED' AND Rated='Y' AND Games.ID=$gid " .
       //"AND white.RatingStatus='RATED' " .
       //"AND black.RatingStatus='RATED' " .
       "AND white.ID=White_ID AND black.ID=Black_ID ";

   $result = db_query( 'update_rating.find_game', $query );

   if( @mysql_num_rows($result) != 1 )
      return -1; //error or game not found (?or rate already done)

   $row = mysql_fetch_array( $result );
   extract($row);

   $too_few_moves = ($Moves < DELETE_LIMIT+$Handicap) ;
   // here $Rated=='N' is always false. See rating2 to update
   if( $too_few_moves || $Rated == 'N' || $wRatingStatus != RATING_RATED || $bRatingStatus != RATING_RATED )
   {
      db_query( 'update_rating.set_rated_N',
         "UPDATE Games SET Rated='N' WHERE ID=$gid" );

      if( $too_few_moves )
         return 1; //not rated game, deletable
      else
         return 2; //not rated game
   }

   $game_result = 0.5;
   if( $Score > 0 ) $game_result = 1.0;
   if( $Score < 0 ) $game_result = 0.0;

   $bOld = $bRating;
   $wOld = $wRating;

   change_rating($wRating, $bRating, $game_result, $Size, $Komi, $Handicap);

   db_query( 'update_rating.set_rated_Done',
      "UPDATE Games SET Rated='Done' WHERE ID=$gid" );

   db_query( 'update_rating.set_black_rating',
      "UPDATE Players SET Rating=$bRating WHERE ID=$Black_ID LIMIT 1" );

   db_query( 'update_rating.set_white_rating',
      "UPDATE Players SET Rating=$wRating WHERE ID=$White_ID LIMIT 1" );

   db_query( 'update_rating.ratingchange',
      "INSERT INTO RatingChange (uid,gid,diff) VALUES " .
               "($Black_ID, $gid, " . ($bRating - $bOld) . "), " .
               "($White_ID, $gid, " . ($wRating - $wOld) . ")" );

   return 0; //rated game
} //update_rating
obsolete */


//
// EGF rating, see above URIs for documentation
//
// return: 0=rated-game, 1=not-rated (deletable), 2=not-rated
function update_rating2($gid, $check_done=true)
{
   global $NOW;

   $WithinPercent = 1/4;

   $query = "SELECT Games.*, ".
      "white.Rating2 as wRating, white.RatingStatus as wRatingStatus, " .
      "white.RatingMax as wRatingMax, white.RatingMin as wRatingMin, " .
      "black.Rating2 as bRating, black.RatingStatus as bRatingStatus, " .
      "black.RatingMax as bRatingMax, black.RatingMin as bRatingMin " .
      "FROM (Games, Players as white, Players as black) " .
      "WHERE Status='FINISHED' AND Games.ID=$gid " .
      ( $check_done ? "AND Rated!='Done' " : '' ) .
      "AND white.ID=White_ID AND black.ID=Black_ID";

   $result = db_query( 'update_rating2.find_game', $query );

   if( @mysql_num_rows($result) != 1 )
      return -1; //error or game not found or rate already done

   $row = mysql_fetch_assoc( $result );
   extract($row);

   $too_few_moves = ($Moves < DELETE_LIMIT+$Handicap) ;
   if( $too_few_moves || $Rated == 'N' || $wRatingStatus != RATING_RATED || $bRatingStatus != RATING_RATED )
   {
      db_query( 'update_rating2.set_rated_N',
         "UPDATE Games SET Rated='N'" .
                  ( is_numeric($bRating) ? ", Black_End_Rating=$bRating" : '' ) .
                  ( is_numeric($wRating) ? ", White_End_Rating=$wRating" : '' ) .
                  " WHERE ID=$gid LIMIT 1" );

      if( $too_few_moves )
         return 1; //not rated game, deletable
      else
         return 2; //not rated game
   }

   $game_result = 0.5;
   if( $Score > 0 ) $game_result = 1.0;
   if( $Score < 0 ) $game_result = 0.0;

   $bOld = $bRating;
   $wOld = $wRating;

   // Calculate factor used to alter how much the ratings are to be changed
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
   if( $wRating < MIN_RATING )
      $wRating = MIN_RATING;

   $wFactor *= $maxminFactor;
   $bTmp = $bOld;
   change_rating($wRatingMax, $bTmp, $game_result, $Size, $Komi, $Handicap, $wFactor);

   $bTmp = $bOld;
   change_rating($wRatingMin, $bTmp, $game_result, $Size, $Komi, $Handicap, $wFactor);


   $wTmp = $wOld;
   change_rating($wTmp, $bRating, $game_result, $Size, $Komi, $Handicap, $bFactor);
   if( $bRating < MIN_RATING )
      $bRating = MIN_RATING;

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


   db_query( 'update_rating2.set_rated_Done',
      "UPDATE Games SET Rated='Done', " .
                "Black_End_Rating=$bRating, White_End_Rating=$wRating " .
                "WHERE ID=$gid LIMIT 1" );

   db_query( 'update_rating2.set_black_rating',
      "UPDATE Players SET Rating2=$bRating, " .
                "RatingMin=$bRatingMin, RatingMax=$bRatingMax " .
                "WHERE ID=$Black_ID LIMIT 1" );

   db_query( 'update_rating2.set_white_rating',
      "UPDATE Players SET Rating2=$wRating, " .
                "RatingMin=$wRatingMin, RatingMax=$wRatingMax " .
                "WHERE ID=$White_ID LIMIT 1" );

   db_query( 'update_rating2.ratinglog',
      'INSERT INTO Ratinglog' .
               '(uid,gid,Rating,RatingMin,RatingMax,RatingDiff,Time) VALUES ' .
               "($Black_ID, $gid, $bRating, $bRatingMin, $bRatingMax, " .
               ($bRating - $bOld) . ", '$Lastchanged'), " .
               "($White_ID, $gid, $wRating, $wRatingMin, $wRatingMax, " .
               ($wRating - $wOld) . ", '$Lastchanged') " );

   return 0; //rated game
} //update_rating2


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
      "FROM (Games, Players as white, Players as black) " .
      "WHERE Status='FINISHED' AND Games.ID=$gid " .
      ( $check_done ? "AND Rated!='Done' " : '' ) .
      "AND white.ID=White_ID AND black.ID=Black_ID ";


   $result = db_query( 'update_rating_glicko.find_game', $query );

   if( @mysql_num_rows($result) != 1 )
      return -1; //error or game not found or rate already done

   $row = mysql_fetch_assoc( $result );
   extract($row);

   $too_few_moves = ($Moves < DELETE_LIMIT+$Handicap) ;
   if( $too_few_moves || $Rated == 'N' || $wRatingStatus != RATING_RATED || $bRatingStatus != RATING_RATED )
   {
      db_query( 0 /* "update_rating_glicko.update_rated_endrating($gid)" */,
         "UPDATE Games SET Rated='N'" .
                  ( is_numeric($bRating) ? ", Black_End_Rating=$bRating" : '' ) .
                  ( is_numeric($wRating) ? ", White_End_Rating=$wRating" : '' ) .
                  " WHERE ID=$gid LIMIT 1" );
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

   $H = ( $Handicap - 0.5 - $Komi / STONE_VALUE );

   // Restore true rating diff from handicap
   $H /= handicapfactor( $size);

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

   db_query( 'update_rating_glicko.update_white',
      "UPDATE Players SET RatingGlicko=$w_mu, " .
                "RatingGlicko_Deviation=$w_phi, RatingGlicko_Volatility=$w_sigma " .
                "WHERE ID=$White_ID LIMIT 1" );

   db_query( 'update_rating_glicko.update_black',
      "UPDATE Players SET RatingGlicko=$b_mu, " .
                "RatingGlicko_Deviation=$b_phi, RatingGlicko_Volatility=$b_sigma " .
                "WHERE ID=$Black_ID LIMIT 1" );

   db_query( 'update_rating_glicko.RatinglogGlicko',
      'INSERT INTO RatinglogGlicko' .
               '(uid,gid,RatingGlicko,RatingGlicko_Deviation,RatingGlicko_Volatility,RatingDiff,Time) VALUES ' .
               "($Black_ID, $gid, $b_mu, $b_phi, $b_sigma, " .
               ($b_mu - $bRating) . ",'$Lastchanged'), " .
               "($White_ID, $gid, $w_mu, $w_phi, $w_sigma, " .
               ($w_mu - $wRating) . ", '$Lastchanged') " );

   echo "<br>$gid: $White_ID - $Black_ID    $w_mu, $w_phi, $w_sigma - $b_mu, $b_phi, $b_sigma\n";

   return 0; //rated game
} //update_rating_glicko

// returns true, if given DGS-rating is valid
function is_valid_rating( $dgs_rating, $check_min=true )
{
   if( isset($dgs_rating) && is_numeric($dgs_rating) && abs($dgs_rating) < OUT_OF_RATING )
      return ( $check_min ) ? ( $dgs_rating >= MIN_RATING ) : true;
   else
      return false;
}

// 30k .. 9d, used for selectboxes
function getRatingArray()
{
   $rating_array = array();
   $s = ' ' . T_('dan');
   for($i=9; $i>0; $i--)
      $rating_array["$i dan"] = $i . $s;
   $s = ' ' . T_('kyu');
   for($i=1; $i<=30; $i++) //30 = (2100-MIN_RATING)/100
      $rating_array["$i kyu"] = $i . $s;
   return $rating_array;
}

function echo_rating($rating, $show_percent=true, $graph_uid=0, $keep_english=false, $short=false)
{
   if( !is_valid_rating($rating) )
      return '';

   $T_= ( $keep_english ? 'fnop' : 'T_' );
   $spc = ( $show_percent === true ? '&nbsp;' : ' ' );

   $rank_val = round($rating/100.0);

   $string = '';
   if( $rank_val > 20.5 )
      $string .= ( $rank_val - 20 ) . ($short ? $T_('dan#short') : $spc . $T_('dan'));
   else
      $string .= ( 21 - $rank_val ) . ($short ? $T_('kyu#short') : $spc . $T_('kyu'));

   if( $show_percent )
   {
      $percent = $rating - $rank_val*100.0;
      $string .= $spc . '('. ($percent > 0 ? '+' :'') . round($percent) . '%)';
   }

   if( $graph_uid > 0 )
   {
      global $base_path;
      $string = "<a class=Rating href=\"{$base_path}ratinggraph.php?uid=$graph_uid\">$string</a>";
   }
   return $string;
}


// decode "21k", "1KYU", "1 dan", "1 kyu ( +15% )", "1gup-15%", ...
define('RATING_PATTERN', '/^\s*([1-9][0-9]*)\s*(k|d|kyu|dan|gup)\s*(\(?\s*([-+]?[0-9]+\s*)%\s*\)?\s*)?$/');

// Must not rise an error because used in filter.php
// check RATING_PATTERN for syntax, this func must be kept synchron with convert_to_rating-func
function read_rating($string)
{
   $string = strtolower($string);

   if( !preg_match(RATING_PATTERN, $string, $matches) )
      return -OUT_OF_RATING;

   $kyu = ( $matches[2] == 'dan' || $matches[2] == 'd' ) ? 2 : 1;
   return rank_to_rating($matches[1], $kyu) + ((int)@$matches[4]);
}

//Must not rise an error, see read_rating()
//need $kyu=1 (kyu) or $kyu=2 (dan)
function rank_to_rating($val, $kyu)
{
   if( $kyu == 1 )
      return 2100 - $val*100;
   else if( $kyu == 2 )
      return $val*100 + 2000;
   else
      return -OUT_OF_RATING;
}


//May rise an error
function get_rating_at_date($uid, $date)
{
   $row = mysql_single_fetch( "get_rating_at.find_data($uid,$date)",
               "SELECT Rating FROM Ratinglog " .
               "WHERE uid='$uid' AND Time<='$date' " .
               "ORDER BY Time DESC LIMIT 1" );

   if( !$row )
      $row = mysql_single_fetch( "get_rating_at.initial_rating($uid)",
         "SELECT InitialRating AS Rating FROM Players WHERE ID='$uid' LIMIT 1" );

   if( isset($row['Rating']) )
      return $row['Rating'];
   return -OUT_OF_RATING; //not ranked
}

// for converting ranks, see convert_to_rating()-func
function getRatingTypes()
{
   return array(
      'dragonrank'   => T_('Dragon rank#ratingtype'),
      'dragonrating' => T_('Dragon rating#ratingtype'),
      'eurorank'     => T_('Euro rank (EGF)#ratingtype'),
      'eurorating'   => T_('Euro rating (EGF)#ratingtype'),
      'aga'          => T_('AGA rank#ratingtype'),
      'agarating'    => T_('AGA rating#ratingtype'),
      'kgs'          => T_('KGS rank#ratingtype'),
      'igs'          => T_('IGS rank#ratingtype'),
      //'igsrating' => T_('IGS rating#ratingtype'),
      'iytgg'        => T_('IYT rank#ratingtype'),
      'japan'        => T_('Japanese rank#ratingtype'),
      'china'        => T_('Chinese rank#ratingtype'),
      'korea'        => T_('Korean rank#ratingtype'),
   );
}


// May rise an error if not said otherwise (except for bad ranktype) returning an out-of-range-rating then
// check RATING_PATTERN for syntax, this func must be kept synchron with read_rating-func
function convert_to_rating($string, $type, $no_error=false)
{
   $rating = -OUT_OF_RATING;
   if( (string)$string == '' )
      return $rating;

   $string = strtolower($string);
   $string = str_replace(chr(160), ' ', $string); // change to normal space char.

   $val = doubleval($string);

   // check, if input is rank (with some dan/kyu/gup-grade) or rating (nums only)
   if( strpos($string, 'k') > 0 || strpos($string, 'gup') > 0 )
      $kyu = 1; // kyu rank
   else if( strpos($string, 'd') > 0 )
      $kyu = 2; // dan rank
   else
      $kyu = 0; // no grad found => rating assumed

   // determine rating from input
   // also see http://senseis.xmp.net/?RankWorldwideComparison
   $needrank = true; // true if rating-type needs rank; false=need-rating
   switch( (string)$type )
   {
      case 'dragonrank':
         if( $kyu > 0 )
            $rating = read_rating($string);
         break;

      case 'dragonrating':
         $needrank = false;
         if( $kyu <= 0 )
            $rating = $val;
         break;

      case 'eurorating':
         $needrank = false;
         if( $kyu <= 0 )
            $rating = $val;
         break;

      case 'eurorank':
         if( $kyu > 0 )
            $rating = rank_to_rating($val, $kyu);
         break;

      case 'aga':
         if( $kyu > 0 )
         {
            $rating = rank_to_rating($val, $kyu);
            if( $rating != -OUT_OF_RATING )
               $rating -= 200.0;  // aga two stones weaker ?
         }
         break;

      case 'agarating':
         $needrank = false;
         if( $kyu <= 0 )
         {
            $rating = $val*100 + ( $val > 0 ? 1950 : 2150 );
            $rating -= 200.0;  // aga two stones weaker ?
         }
         break;

      case 'igs':
         if( $kyu > 0 )
         {
            $rating = rank_to_rating($val, $kyu);
            if( $rating != -OUT_OF_RATING )
            {
               global $IGS_TABLE;
               $rating = table_interpolate($rating, $IGS_TABLE, true);
            }
         }
         break;

      /*
      case 'igsrating':
         $needrank = 0;
         if( $kyu <= 0 )
         {
             global $IGS_TABLE;
             $rating = $val*100 - 1130 ;
             $rating = table_interpolate($rating, $IGS_TABLE, true);
         }
         break;
      */

      case 'iytgg':
         if( $kyu > 0 )
         {
            $rating = rank_to_rating($val, $kyu);
            if( $rating != -OUT_OF_RATING )
               $rating += 100;  // one stone stronger
         }
         break;

      case 'kgs': // rank
         if( $kyu > 0 )
         {
            $rating = rank_to_rating($val, $kyu);
            if( $rating != -OUT_OF_RATING )
            {
               global $KGS_TABLE;
               $rating = table_interpolate($rating, $KGS_TABLE, true);
            }
         }
         break;

      case 'japan':
         if( $kyu > 0 )
         {
            $rating = rank_to_rating($val, $kyu);
            if( $rating != -OUT_OF_RATING )
               $rating -= 300;  // three stones weaker
         }
         break;

      case 'china':
         if( $kyu > 0 )
         {
            $rating = rank_to_rating($val, $kyu);
            if( $rating != -OUT_OF_RATING )
               $rating += 100;  // one stone stronger
         }
         break;

      case 'korea':
         if( $kyu > 0 )
         {
            $rating = rank_to_rating($val, $kyu);
            if( $rating != -OUT_OF_RATING )
               $rating += 400;  // four stones stronger
         }
         break;

      default:
         error('wrong_rank_type');
         break;
   }

   if( $rating == -OUT_OF_RATING )
   {
      if( $no_error )
         return $rating;
      error($needrank ? 'rank_not_rating' : 'rating_not_rank'
         , "type:$type str:$string val:$val kyu:$kyu");
   }

   //valid rating, so ends with a limited bound corrections, else error
   if( $rating > MAX_START_RATING && $rating-50 <= MAX_START_RATING )
      $rating = MAX_START_RATING;

   if( $rating < MIN_RATING && $rating+50 >= MIN_RATING )
      $rating = MIN_RATING;

   if( $rating >= MIN_RATING && $rating <= MAX_START_RATING )
      return $rating;

   if( $no_error )
      return $rating;
   error('rating_out_of_range');
   return -OUT_OF_RATING;
}

?>
