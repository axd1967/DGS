<?php

require_once( "include/quick_common.php" );
require_once( "include/std_functions.php" );
require_once( "include/rating.php" );

{
   $v = $argv[1];

   echo "\n";

   for ( $d=-100; $d <= 100; $d += 10 )
   {
      $v2 = $v + $d;
      $r = echo_rating($v2, true);
      $r = preg_replace( "/\&nbsp;/", ' ', $r );
      echo sprintf("  %-6.2f -> %s\n", $v2, $r );
   }
   echo "\n";
   $m = $v - MIN_RATING;
   $m -= 100*round($m/100.0);
   echo "  $v MOD 100 = ".($m)."\n";
   echo "  $v mod 100 = ".($v % 100)."\n";
   echo "  $v mod  50 = ".($v %  50)."\n";
   echo "  round($v/100) = ".round($v/100.0)."\n";
}
?>
