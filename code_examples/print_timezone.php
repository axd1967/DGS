<?php

chdir("../");
require_once( "include/quick_common.php" );
require_once( "include/filter_parser.php" );

{

$h = time() + date('Z');

putenv( 'TZ=GMT+1'); //so plus one
echo "\ngmdt=".gmdate('r',$h);
echo "\n  dt=".  date('r',$h);
putenv( 'TZ=GMT');
echo "\ngmdt=".gmdate('r',$h);
echo "\n  dt=".  date('r',$h);
putenv( 'TZ=GMT-1'); //so minus one
echo "\ngmdt=".gmdate('r',$h);
echo "\n  dt=".  date('r',$h);
echo "\n";
echo "\n";

putenv( 'TZ=UTC+1'); //so plus one
echo "\ngmdt=".gmdate('r \G\M\T',$h);
echo "\n  dt=".  date('r \G\M\T',$h);
echo "\ngmdt=".gmdate('r \U\T\C',$h);
echo "\n  dt=".  date('r \U\T\C',$h);
putenv( 'TZ=UTC');
echo "\ngmdt=".gmdate('r \G\M\T',$h);
echo "\n  dt=".  date('r \G\M\T',$h);
echo "\ngmdt=".gmdate('r \U\T\C',$h);
echo "\n  dt=".  date('r \U\T\C',$h);
putenv( 'TZ=UTC-1'); //so minus one
echo "\ngmdt=".gmdate('r \G\M\T',$h);
echo "\n  dt=".  date('r \G\M\T',$h);
echo "\ngmdt=".gmdate('r \U\T\C',$h);
echo "\n  dt=".  date('r \U\T\C',$h);
echo "\n";
}
?>
