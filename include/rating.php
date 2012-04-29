<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

// rated-status for update_rating2()
define('RATEDSTATUS_RATED',     0);
define('RATEDSTATUS_DELETABLE', 1);
define('RATEDSTATUS_UNRATED',   2);

define('RCADM_RESET_CONFIDENCE', 0x01);
define('RCADM_CHANGE_RATING',    0x02);


// table for interpolating rating
global $IGS_TABLE; //PHP5
$IGS_TABLE = array(
   array( 'KEY' =>  200, 'VAL' =>  100 ), // 19k +1
   array( 'KEY' =>  700, 'VAL' =>  600 ), // 14k +1
   array( 'KEY' =>  800, 'VAL' =>  800 ), // 13k =
   array( 'KEY' => 1300, 'VAL' => 1300 ), // 8k =
   array( 'KEY' => 1400, 'VAL' => 1300 ), // 7k +1
   array( 'KEY' => 2400, 'VAL' => 2300 ), // 4d -1 DGS
);

// table for interpolating rating
global $KGS_TABLE; //PHP5
$KGS_TABLE = array(
   array( 'KEY' =>  100, 'VAL' => -700 ), // 20k +8
   array( 'KEY' =>  600, 'VAL' =>    0 ), // 15k +6
   array( 'KEY' => 1400, 'VAL' => 1000 ), // 7k +4
   array( 'KEY' => 1500, 'VAL' => 1200 ), // 6k +3
   array( 'KEY' => 2400, 'VAL' => 2200 ), // 4d -2
   array( 'KEY' => 2700, 'VAL' => 2600 ), // 7d -1 DGS
);

global $A_TABLE; //PHP5
$A_TABLE = array(
   array( 'KEY' =>   100, 'VAL' =>  200 ),
   array( 'KEY' =>  2700, 'VAL' =>   70 ),
   array( 'KEY' => 10000, 'VAL' =>   70 ),   //higher, should be constant
);

global $CON_TABLE; //PHP5
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

   $D = $old_D = $rating_W - $rating_B;

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

   $old_rating_W = $rating_W;
   $old_rating_B = $rating_B;
   $rating_W += $conW * ($result - $SEW);
   $rating_B += $conB * (1-$result - $SEB);

   if( DBG_RATING )
   {
      static $fmt = "   %s: SE=%1.6f, con=%3.6f, rating=[%4.6f -> %4.6f], diff = %3.6f\n";
      echo sprintf("change_rating: factor=%1.6f, sizefactor=%1.6f, result=%s, H=%1.6f, old_D=%3.6f, D=%3.6f\n",
                   $factor, $sizefactor, $result, $H, $old_D, $D),
         sprintf($fmt, 'White', $SEW, $conW, $old_rating_W, $rating_W, $rating_W - $old_rating_W),
         sprintf($fmt, 'Black', $SEB, $conB, $old_rating_B, $rating_B, $rating_B - $old_rating_B),
         "\n";
   }
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


//
// EGF rating, see above URIs for documentation
//
// param simul: if true, don't write into db (used for rating-calcs only)
//
// param game_row: only if simul=true for overwriting loaded fields from Games-table:
//    Moves, Handicap, Rated=Y|N, Score, Size, Komi, (Lastchanged),
//    b/wRatingStatus, b/wRating, b/wRatingMin/Max, Black/White_ID
//
// return: -1=error, or: RATEDSTATUS_...: 0=..RATED=rated-game, 1=..DELETABLE=not-rated, 2=..UNRATED=not-rated
function update_rating2($gid, $check_done=true, $simul=false, $game_row=null)
{
   global $NOW;

   $WithinPercent = 1/4;

   $query = "SELECT Games.*, ".
      "white.Rating2 as wRating, white.RatingStatus as wRatingStatus, " .
      "white.RatingMax as wRatingMax, white.RatingMin as wRatingMin, " .
      "black.Rating2 as bRating, black.RatingStatus as bRatingStatus, " .
      "black.RatingMax as bRatingMax, black.RatingMin as bRatingMin " .
      "FROM (Games, Players as white, Players as black) " .
      "WHERE Games.ID=$gid AND white.ID=White_ID AND black.ID=Black_ID " .
         ( $simul ? '' : "AND Status='".GAME_STATUS_FINISHED."' " ) .
         ( $check_done ? "AND Rated!='Done' " : '' );

   $result = db_query( 'update_rating2.find_game', $query );
   if( @mysql_num_rows($result) != 1 )
      return -1; //error or game not found or rate already done
   $row = mysql_fetch_assoc( $result );
   extract($row);

   if( !is_null($game_row) ) // overwrite (for simul-mode & MP-game)
      extract($game_row);

   if( DBG_RATING )
   {
      static $fmt2 = "   uid [%6d] %s-Rating: Game-Current [%1.6f], Min [%1.6f], Max [%1.6f], Start(unused) [%1.6f]\n";
      echo "Rating Init ...\n",
         sprintf("   Size %s, Handicap %s, Komi %s, Score [%s]\n", $Size, $Handicap, $Komi,
                 ($Score < 0 ? 'Black won' : ($Score > 0 ? 'White won' : 'Jigo'))),
         sprintf($fmt2, $White_ID, 'White', $wRating, $wRatingMin, $wRatingMax, $White_Start_Rating),
         sprintf($fmt2, $Black_ID, 'Black', $bRating, $bRatingMin, $bRatingMax, $Black_Start_Rating);
   }

   $too_few_moves = ( $tid == 0 && !$simul ) ? ( $Moves < DELETE_LIMIT + $Handicap ) : false;
   if( $too_few_moves || $Rated == 'N' || $wRatingStatus != RATING_RATED || $bRatingStatus != RATING_RATED
         || $GameType != GAMETYPE_GO )
   {
      if( !$simul )
      {
         db_query( 'update_rating2.set_rated_N',
            "UPDATE Games SET Rated='N'" .
                  ( is_numeric($bRating) ? ", Black_End_Rating=$bRating" : '' ) .
                  ( is_numeric($wRating) ? ", White_End_Rating=$wRating" : '' ) .
                  " WHERE ID=$gid LIMIT 1" );
      }

      if( $too_few_moves )
         return RATEDSTATUS_DELETABLE; //not rated game, deletable
      else
         return RATEDSTATUS_UNRATED; //not rated game
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

   if( DBG_RATING ) echo sprintf("   White-Rating-Factor [%1.6f]\n   Black-Rating-Factor [%1.6f]\n", $wFactor, $bFactor );

   // Update ratings

   if( DBG_RATING ) echo "\nWhite-Rating ...\n";
   $bTmp = $bOld;
   change_rating($wRating, $bTmp, $game_result, $Size, $Komi, $Handicap, $wFactor);
   if( $wRating < MIN_RATING )
      $wRating = MIN_RATING;

   if( DBG_RATING ) echo "White-RatingMax...\n";
   $wFactor *= $maxminFactor;
   $bTmp = $bOld;
   change_rating($wRatingMax, $bTmp, $game_result, $Size, $Komi, $Handicap, $wFactor);

   if( DBG_RATING ) echo "White-RatingMin...\n";
   $bTmp = $bOld;
   change_rating($wRatingMin, $bTmp, $game_result, $Size, $Komi, $Handicap, $wFactor);


   if( DBG_RATING ) echo "\nBlack-Rating ...\n";
   $wTmp = $wOld;
   change_rating($wTmp, $bRating, $game_result, $Size, $Komi, $Handicap, $bFactor);
   if( $bRating < MIN_RATING )
      $bRating = MIN_RATING;

   if( DBG_RATING ) echo "Black-Rating-Max ...\n";
   $bFactor *= $maxminFactor;
   $wTmp = $wOld;
   change_rating($wTmp, $bRatingMax, $game_result, $Size, $Komi, $Handicap, $bFactor);

   if( DBG_RATING ) echo "Black-Rating-Min ...\n";
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


   if( !$simul )
   {
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
   }

   if( DBG_RATING )
   {
      static $fmt = "   Result %s-Rating: Start [%1.6f] => <b>End [%1.6f], Diff [%3.6f]</b>,  Min [%1.6f], Max [%1.6f]\n";
      echo "<b>Rating Results of update_rating2():</b>\n",
         sprintf($fmt, 'White', $wOld, $wRating, $wRating - $wOld, $wRatingMin, $wRatingMax),
         sprintf($fmt, 'Black', $bOld, $bRating, $bRating - $bOld, $bRatingMin, $bRatingMax),
         "\n";
   }

   return RATEDSTATUS_RATED; //rated game
} //update_rating2


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

// input: show_percent = true|1|false (1 = like true but no HTML-space)
// input: short = true|1|false (1 = like true, but use abbreviations 'k/d' for kyu/dan and no translation)
//        needed for quick-suite
// return '' for invalid rating
function echo_rating($rating, $show_percent=true, $graph_uid=0, $keep_english=false, $short=false)
{
   if( !is_valid_rating($rating) )
      return '';

   $T_= ( $keep_english ? 'fnop' : 'T_' );
   $spc = ( $show_percent === true ? '&nbsp;' : ' ' );

   $rank_val = round($rating/100.0);

   $string = '';
   if( $rank_val > 20.5 )
      $string .= ( $rank_val - 20 ) . ( $short === true ? $T_('dan#short') : ($short ? 'd' : $spc . $T_('dan')) );
   else
      $string .= ( 21 - $rank_val ) . ( $short === true ? $T_('kyu#short') : ($short ? 'k' : $spc . $T_('kyu')) );

   if( $show_percent )
   {
      $percent = $rating - $rank_val*100.0;
      $string .= $spc . '('. ($percent > 0 ? '+' :'') . round($percent) . '%)';
   }

   if( $graph_uid > 0 )
   {
      global $base_path;
      $elo_str = T_('ELO#rating') . sprintf( ' %1.2f', $rating );
      $string = anchor( $base_path."ratinggraph.php?uid=$graph_uid", $string, $elo_str, 'class="Rating"' );
   }
   return $string;
}

// used for quick-suite
function echo_rating_elo( $rating )
{
   return (is_valid_rating($rating)) ? $rating : '';
}


// decode "21k", "1KYU", "1 dan", "1 kyu ( +15% )", "1gup-15%", ...
define('RATING_PATTERN', '/^\s*([1-9][0-9]*)\s*(k|d|kyu|dan|gup)\s*(\(?\s*([-+]?[0-9]+\s*)%\s*\)?\s*)?$/');

// Must not rise an error because used in filter.php
// check RATING_PATTERN for syntax, this func must be kept synchron with convert_to_rating-func
function read_rating($string)
{
   $string = strtolower($string);

   if( !preg_match(RATING_PATTERN, $string, $matches) )
      return NO_RATING;

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
      return NO_RATING;
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
   return NO_RATING; //not ranked
}

// for converting ranks, see convert_to_rating()-func
function getRatingTypes()
{
   return array(
      'dragonrank'   => T_('Dragon rank#ratingtype'),
      'dragonrating' => T_('Dragon rating#ratingtype'),
      // Rating-lists:
      'eurorank'     => T_('Euro rank (EGF)#ratingtype'),
      'eurorating'   => T_('Euro rating (EGF)#ratingtype'),
      'aga'          => T_('AGA rank#ratingtype'),
      'agarating'    => T_('AGA rating#ratingtype'),
      'japan'        => T_('Japanese rank#ratingtype'),
      'china'        => T_('Chinese rank#ratingtype'),
      'korea'        => T_('Korean rank#ratingtype'),
      // Servers:
      'kgs'          => T_('KGS rank#ratingtype'),
      'igs'          => T_('IGS rank#ratingtype'),
      //'igsrating' => T_('IGS rating#ratingtype'),
      'ogs'          => T_('OGS rank#ratingtype'),
      'ogsrating'    => T_('OGS rating#ratingtype'),
      'ficgs'        => T_('FICGS rank#ratingtype'),
      'iytgg'        => T_('IYT rank#ratingtype'),
      'tygem'        => T_('Tygem rank#ratingtype'),
      'wbaduk'       => T_('WBaduk rank#ratingtype'),
   );
}


// May rise an error if not said otherwise (except for bad ranktype) returning an out-of-range-rating then
// check RATING_PATTERN for syntax, this func must be kept synchron with read_rating-func
function convert_to_rating($string, $type, $no_error=false)
{
   $rating = NO_RATING;
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
         if( $rating != NO_RATING )
         {
            if( $rating >= -400 && $rating <= 300 ) // 25k..18k
               $rating -= 100.0;
            elseif( $rating >=800 && $rating <= 1200 ) // 13k-9k
               $rating += 100.0;
         }
         break;

      case 'eurorank':
         if( $kyu > 0 )
         {
            $rating = rank_to_rating($val, $kyu);
            if( $rating != NO_RATING )
            {
               if( $rating >= -400 && $rating <= 300 ) // 25k..18k
                  $rating -= 100.0;
               elseif( $rating >=800 && $rating <= 1200 ) // 13k-9k
                  $rating += 100.0;
            }
         }
         break;

      case 'aga':
         if( $kyu > 0 )
         {
            $rating = rank_to_rating($val, $kyu);
            if( $rating != NO_RATING )
               $rating -= 300.0;  // aga three stones weaker ?
         }
         break;

      case 'agarating':
         $needrank = false;
         if( $kyu <= 0 )
         {
            $rating = $val*100 + ( $val > 0 ? 1950 : 2150 ); // 1k : 2d
            $rating -= 300.0;  // aga three stones weaker ?
         }
         break;

      case 'igs':
         if( $kyu > 0 )
         {
            $rating = rank_to_rating($val, $kyu);
            if( $rating != NO_RATING )
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
            if( $rating != NO_RATING )
               $rating += 100;  // one stone stronger
         }
         break;

      case 'ficgs':
         if( $kyu > 0 )
            $rating = rank_to_rating($val, $kyu);
         break;

      case 'kgs': // rank
         if( $kyu > 0 )
         {
            $rating = rank_to_rating($val, $kyu);
            if( $rating != NO_RATING )
            {
               global $KGS_TABLE;
               $rating = table_interpolate($rating, $KGS_TABLE, true);
            }
         }
         break;

      case 'ogs':
         if( $kyu > 0 )
         {
            $rating = rank_to_rating($val, $kyu);
            if( $rating != NO_RATING )
               $rating -= 100;
         }
         break;

      case 'ogsrating':
         $needrank = false;
         if( $kyu <= 0 )
            $rating = $val - 100.0;
         break;

      case 'tygem':
         if( $kyu > 0 )
         {
            $rating = rank_to_rating($val, $kyu);
            if( $rating != NO_RATING )
               $rating -= 100;
         }
         break;

      case 'wbaduk':
         if( $kyu > 0 )
         {
            $rating = rank_to_rating($val, $kyu);
            if( $rating != NO_RATING )
               $rating -= 200;
         }
         break;

      case 'japan':
         if( $kyu > 0 )
         {
            $rating = rank_to_rating($val, $kyu);
            if( $rating != NO_RATING )
               $rating -= 300;  // three stones weaker
         }
         break;

      case 'china':
         if( $kyu > 0 )
         {
            $rating = rank_to_rating($val, $kyu);
            if( $rating != NO_RATING && ($rating <= 400 || $rating >= 1400) ) // <=17k, >=7k
               $rating -= 100;  // one stone weaker <=17k | >=7k
         }
         break;

      case 'korea':
         if( $kyu > 0 )
         {
            $rating = rank_to_rating($val, $kyu);
            if( $rating != NO_RATING )
            {
               if( $rating > 1100 )
                  $rating += 100; // one stone stronger >10k
               else
                  $rating += 200; // two stones stronger <=10k
            }
         }
         break;

      default:
         error('wrong_rank_type', "convert_to_rating.check.type($type)");
         break;
   }

   if( $rating == NO_RATING )
   {
      if( $no_error )
         return $rating;
      error($needrank ? 'rank_not_rating' : 'rating_not_rank',
         "convert_to_rating.check.rating($type,$string,$val,$kyu,$needrank)");
   }

   //valid rating, so ends with a limited bound corrections, else error
   if( $rating-50 > MAX_START_RATING )
      $rating = MAX_START_RATING;
   if( $rating+50 < MIN_RATING )
      $rating = MIN_RATING;

   if( $rating >= MIN_RATING && $rating <= MAX_START_RATING )
      return $rating;

   if( $no_error )
      return $rating;
   error('rating_out_of_range', "convert_to_rating.error($type,$string,$val,$kyu,$needrank)");
   return NO_RATING;
}//convert_to_rating

/*!
 * \brief Updates players Ratings and insert entries to allow rating-recalculation.
 * \param $changes RCADM_RESET_CONFIDENCE and/or RCADM_CHANGE_RATING
 *
 * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
 */
function change_user_rating( $uid, $changes, $rating, $rating_min, $rating_max, $with_rca=true )
{
   global $NOW;
   if( !is_numeric($changes) || !($changes & (RCADM_RESET_CONFIDENCE|RCADM_CHANGE_RATING)) )
      error('invalid_args', "change_user_rating.check.changes($uid,$changes,$rating)");
   if( !is_valid_rating($rating) )
      error('invalid_args', "change_user_rating.check.rating($uid,$changes,$rating)");

   db_query( "change_user_rating.update_players($uid,$rating)",
      "UPDATE Players SET " .
      "Rating2=$rating, RatingMin=$rating_min, RatingMax=$rating_max " .
      "WHERE ID=$uid LIMIT 1" );

   if( $with_rca )
   {
      $new_rating = ( $changes & RCADM_CHANGE_RATING ) ? $rating : NO_RATING;
      db_query( "change_user_rating.insert_rating_chg($uid,$rating,$reset_confidence)",
         "INSERT RatingChangeAdmin (uid,Created,Changes,Rating) " .
         "VALUES ($uid,FROM_UNIXTIME($NOW),$changes,$new_rating)" );
   }
}//change_user_rating

// format RatingChangeAdmin.Changes as string
function format_ratingchangeadmin_changes( $changes, $sep=', ' )
{
   $out = array();
   if( $changes & RCADM_RESET_CONFIDENCE )
      $out[] = 'RESET_CONFIDENCE';
   if( $changes & RCADM_CHANGE_RATING )
      $out[] = 'CHANGE_RATING';
   return implode($sep, $out);
}

// updates Players-rating-fields and more if given in optional $upd_players UpdateQuery
// param $new_rating if null -> only update rank
// IMPORTANT NOTE: no check, if RatingStatus != RATING_RATED !! (must be done before)
function update_player_rating( $uid, $new_rating=null, $upd_players=null )
{
   if( !is_numeric($uid) || $uid <= 0 )
      error('invalid_args', "update_player_rating.check.uid($uid,$new_rating)");

   $upd = ( is_null($upd_players) ) ? new UpdateQuery('Players') : $upd_players;
   if( !is_null($new_rating) && is_numeric($new_rating) && $new_rating >= MIN_RATING )
   {
      $upd->upd_num('Rating', $new_rating);
      $upd->upd_num('InitialRating', $new_rating);
      $upd->upd_num('Rating2', $new_rating);
      $upd->upd_raw('RatingMin', "$new_rating-200-GREATEST(1600-($new_rating),0)*2/15");
      $upd->upd_raw('RatingMax', "$new_rating+200+GREATEST(1600-($new_rating),0)*2/15");
      $upd->upd_txt('RatingStatus', RATING_INIT);
   }

   $upd_query = $upd->get_query();
   db_query( "update_player_rating.update($uid,$new_rating)",
      "UPDATE Players SET $upd_query WHERE ID=$uid LIMIT 1" );
}//update_player_rating

?>
