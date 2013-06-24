<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

chdir('..');
require_once 'include/std_functions.php';


function search_browsers($result, &$Browsers)
{
   global $total;

   $total = 0;

   while ( $row = mysql_fetch_array($result) )
   {
      extract($row);
//      echo "\n$Browser: ";

      foreach ( $Browsers as $key => $browserfam )
      {
         $nr = 0;
         foreach ( $browserfam as $name => $b )
         {
            $res = preg_match("/{$b[0]}/i", $Browser);

            if ( $res == 0 )
            {
               if ( $nr == 0 )
                  continue 2;
               else
                  continue;
            }

            $c = ($Browsers[$key][$name][1] += $Count);
//            echo $name . ": {$c}  ";

            if ( $nr > 0 )
               continue 3;

            $nr++;
            $total+=$Count;
         }

         if ( $nr == 1 )
            continue 2;

         echo "No Browser Found ($Count): {$Browser}<br>\n";
      }

      echo "No Family Found ($Count): {$Browser}<br>\n";
   }
}//search_browsers


function echo_browsers(&$Browsers)
{
   global $total;

   foreach ( $Browsers as $key => $browserfam )
   {
      $nr = 0;
      foreach ( $browserfam as $name => $b )
      {
         if ( $nr == 1 )
            echo "\n--------------";
         $s = "$name: {$b[1]}   " . sprintf("%.2f",100*$b[1]/$total) . "%";
         if ( $nr == 0 ) $s = "<b>$s</b>";
         echo "\n", $s;
         $nr++;
      }
      echo "\n\n";
   }
}





{
   connect2mysql();

   echo '<pre>';

   $result = mysql_query("SELECT SQL_SMALL_RESULT COUNT(*) AS Count, Browser FROM Players " .
                         "WHERE Browser IS NOT NULL AND Browser!='' AND Activity>$ActivityForHit/10 " .
                         "GROUP BY Browser")
      or die(mysql_error());


   $Browsers = array(array('Opera' => array('opera',0),
                           'Opera 5' => array('opera.5',0),
                           'Opera 6' => array('opera.6',0),
                           'Opera 7' => array('opera.7',0),
                           'Opera 8' => array('opera.8',0),
                           'Opera 9' => array('opera.9',0),
                           'Opera 10' => array('opera.10',0)),

                     array('Konqueror' => array('konqueror|safari',0),
                           'Konqueror 2' => array('konqueror.?2',0),
                           'Konqueror 3' => array('konqueror.?3',0),
                           'Konqueror 4' => array('konqueror.?4',0),
                           'Safari' => array('safari',0)),

                     array('MSIE' => array('msie',0),
                           'MSIE <4' => array('msie [23]',0),
                           'MSIE 4' => array('msie 4',0),
                           'MSIE 5' => array('msie 5',0),
                           'MSIE 6' => array('msie 6',0),
                           'MSIE 7' => array('msie 7',0),
                           'MSIE 8' => array('msie 8',0),
                           'MSIE 9' => array('msie 9',0),
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
                           'Gecko Other' => array('gecko|mozilla',0)),

                     array('Apple' => array('(apple|iphone|ipad|mac)',0),
                           'iPhone' => array('iPhone;',0),
                           'iPad' => array('iPad;',0),
                           'iPod' => array('iPod(.touch)?;',0),
                           'Mac' => array('(MacBookPro|iMac)',0)),

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

                     array('BlackBerry' => array('blackberry',0)),
                     array('Java' => array('java',0)), //Java/1.6.0_03
                     array('Picsel' => array('picsel',0)), //Picsel/1.0 (Windows NT 5.1; U)

//                     array('Other' => array('.',0)),
         );


   search_browsers($result, $Browsers);

   echo_browsers($Browsers);

   echo "\n\nTotal: $total";


   echo "\n\n\n========================================================\n\n";

   mysql_data_seek($result, 0);


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
                       'iPhone' => array('iPhone',0),
                       'Nokia' => array('nokia',0),
                       'Samsung' => array('samsung',0),
                       'SonyEricsson' => array('ericsson',0)),

                 array('Mac' => array('mac',0),
                       'OS X' => array('os x',0)),

                 array('Windows' => array('win',0),
                       'XP' => array('(win|windows).?(xp|nt 5.1)',0),
                       'NT' => array('(win|windows).?nt',0),
                       '95' => array('(win|windows).?95',0),
                       '98' => array('(win|windows).?98',0),
                       '2000' => array('(win|windows).?2000',0)),


                 array('Other' => array('.',0)),
         );


   search_browsers($result, $OSes);

   echo_browsers($OSes);


   mysql_free_result($result);

   echo '</pre>';
}
?>
