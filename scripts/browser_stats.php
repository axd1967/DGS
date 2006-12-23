<?php

chdir('..');
require_once( "include/std_functions.php" );


function search_browsers($result, &$Browsers)
{
   global $total;

   $total = 0;

   while( $row = mysql_fetch_array($result) )
   {
      extract($row);
//      echo "\n$Browser: ";

      foreach( $Browsers as $key => $browserfam )
         {
            $nr = 0;
            foreach( $browserfam as $name => $b )
               {
                  $res = preg_match("/{$b[0]}/i", $Browser);

                  if( $res == 0 )
                     if( $nr == 0 )
                        continue 2;
                     else
                        continue;

                  $c = ($Browsers[$key][$name][1] += $Count);
//                  echo $name . ": {$c}  ";

                  if( $nr > 0 )
                     continue 3;

                  $nr++;
                  $total+=$Count;
               }

            if( $nr == 1 )
               continue 2;

            echo "No Browser Found: {$Browser} <br>\n";
         }

      echo "No Family Found:{$Browser}<br>\n";
   }
}


function echo_browsers(&$Browsers)
{
   global $total;

   foreach( $Browsers as $key => $browserfam )
      {
         $nr = 0;
         foreach( $browserfam as $name => $b )
            {
               if( $nr == 1 )
                  echo "\n--------------";
               echo "\n$name: {$b[1]}   " . sprintf("%.2f",100*$b[1]/$total) . "%";
               $nr++;
            }
         echo "\n\n";
      }
}





{
   connect2mysql();

   echo '<pre>';

   $Browsers = array(array('Opera' => array('opera',0),
                           'Opera 5' => array('opera.5',0),
                           'Opera 6' => array('opera.6',0),
                           'Opera 7' => array('opera.7',0),
                           'Opera 8' => array('opera.8',0),
                           'Opera 9' => array('opera.9',0)),

                     array('Konqueror' => array('konqueror|safari',0),
                           'Konqueror 2' => array('konqueror.?2',0),
                           'Konqueror 3' => array('konqueror.?3',0),
                           'Safari' => array('safari',0)),

                     array('AntFresco' => array('antfresco',0)),
                     array('OmniWeb' => array('omniweb',0)),
                     array('Links' => array('links',0)),
                     array('Lynx' => array('lynx',0)),
                     array('curl' => array('url',0)),
                     array('Jakarta' => array('jakarta',0)),
                     array('w3m' => array('w3m',0)),
                     array('R1ED Browser' => array('r1ed',0)),
                     array('libwww-perl' => array('libwww-perl',0)),
                     array('GameWatch' => array('gamewatch',0)),
                     array('RTGO Client' => array('rtgo client',0)),
                     array('Indy Library' => array('indy library',0)),

                     array('MSIE' => array('msie',0),
                           'MSIE <4' => array('msie [23]',0),
                           'MSIE 4' => array('msie 4',0),
                           'MSIE 5' => array('msie 5',0),
                           'MSIE 6' => array('msie 6',0),
                           'MSIE 7' => array('msie 7',0),
                           'MSIE Other' => array('msie',0)),

                     array('Netscape 4' => array('mozilla.?4',0)),

                     array('Gecko' => array('gecko|firefox|mozilla.?[5678]',0),
                           'Firefox' => array('firefox',0),
                           'Galeon' => array('galeon',0),
                           'Netscape 6' => array('netscape.?6',0),
                           'Netscape 7' => array('netscape.?7',0),
                           'Netscape 8' => array('netscape.?8',0),
                           'Netscape Other' => array('netscape',0),
                           'Phoenix' => array('phoenix',0),
                           'Camino' => array('chimera|camino',0),
                           'Gecko Other' => array('gecko|mozilla',0)));


//                     array('Other' => array('.',0)));





   $result = mysql_query("SELECT count(*) AS Count, Browser FROM Players " .
                         "WHERE Browser IS NOT NULL AND Browser!='' AND Activity > 0.1 " .
                         "GROUP BY Browser")
      or die(mysql_error());

   search_browsers($result, $Browsers);

   echo_browsers($Browsers);

   echo "\n\nTotal: $total";


   $OSes = array(array('Linux' => array('linux',0),
                       'Linux 2.2' => array('2.2',0),
                       'Linux 2.4' => array('2.4',0),
                       'Linux 2.5' => array('2.5',0),
                       'Linux 2.6' => array('2.6',0)),

                 array('FreeBSD' => array('freebsd',0)),

                 array('SunOS' => array('sunos',0)),

                 array('HP-UX' => array('hp.?ux',0)),
                 array('IRIX' => array('irix',0)),

                 array('WebTV' => array('webtv',0)),
                 array('PSP' => array('psp',0)),
                 array('Palm' => array('palm',0)),
                 array('Nintendo Wii' => array('wii',0)),

                 array('Unix' => array('unix',0)),

                 array('Mobile phone' => array('nokia|ericsson',0),
                       'Nokia' => array('nokia',0),
                       'SonyEricsson' => array('ericsson',0)),

                 array('Mac' => array('mac',0),
                       'OS X' => array('os x',0)),

                 array('Windows' => array('win',0),
                       'XP' => array('(win|windows).?(xp|nt 5.1)',0),
                       'NT' => array('(win|windows).?nt',0),
                       '95' => array('(win|windows).?95',0),
                       '98' => array('(win|windows).?98',0),
                       '2000' => array('(win|windows).?2000',0)),


                 array('Other' => array('.',0)));


   mysql_data_seek($result, 0);

   echo "\n\n\n========================================================\n\n";

   search_browsers($result, $OSes);

   echo_browsers($OSes);



   echo '</pre>';
}
?>
