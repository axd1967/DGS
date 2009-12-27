<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

chdir("../../");
//require_once( "include/quick_common.php" );
//require_once( "include/connect2mysql.php" );
require_once( "include/std_functions.php" );

$TheErrors->set_mode(ERROR_MODE_PRINT);

{
   $tid= get_request_arg( 'test', '1' );

   connect2mysql();

   $title= str_replace('\\', '/', @$_SERVER['PHP_SELF']);
   list($title)= explode( '.', substr(strrchr ( $title, '/'),1) );
   $title= "test - $title";
   start_html($title, true, 'dragon',
         "pre { background: #dddddd; padding: 8px;}" );

   echo "<br>$title - Hello.";

   // NOTE: avoid DROP'ing existing Dragon-table -> use prefix 'test_' for table-name
   $tname= get_request_arg( 'table', 'temp');

   $tmode= min(2,max(0,(int)get_request_arg( 'temporary', 1)));
   $noidx= (int)(bool)get_request_arg( 'no_index');
   $repeat= max(1,(int)get_request_arg( 'repeat', 200));
   $precision= min(8,max(0,(int)get_request_arg( 'precision', 3)));
   $sample= max(0,(int)get_request_arg( 'sample', 1000));
   if( $tmode ) $tname= 'temp';
   $tname = 'test_'.$tname; // see note above

   $halflife= 100;
   $factor = exp( -M_LN2 / $halflife );

   echo "<p>-<i>params:</i>"
      ,"<br>&nbsp;?table= name of the table used (prefix 'test_' added for safety)"
      ,"<br>&nbsp;?temporary= the table used is created 0=in the database, 1=temporary, 2=in memory"
      ,"<br>&nbsp;?no_index= the columns tested are not indexed"
      ,"<br>&nbsp;?repeat= number of tests made (halflife=$halflife)"
      ,"<br>&nbsp;?sample= sample size (number of rows)"
      ,"<br>&nbsp;?precision= precision of the decimal tests (2 =&gt; 1/100)"
      ,"<br>-<i>current:</i>"
      ,"<br>&nbsp;?table=$tname&amp;temporary=$tmode&amp;no_index=$noidx&amp;repeat=$repeat&amp;sample=$sample&amp;precision=$precision"
      ,"</p>";

   $prec10= pow(10,$precision);

   set_time_limit( 300); //seconds

   switch( (int)$tmode )
   {
      case 0:
         $engine= '';
         break;

      default:
      case 1:
         $engine= '';
         break;
      case 2:
         $engine= " ENGINE=MEMORY";
         break;
   }

   db_query( 'test.drop',
      "DROP".($tmode?" TEMPORARY":'')." TABLE IF EXISTS $tname")
      or $TheErrors->dump_exit('test');

   $i= 15; $k= $i*$prec10;
   db_query( 'test.create',
      "CREATE".($tmode?" TEMPORARY":'')." TABLE IF NOT EXISTS $tname (ID INT NOT NULL"
      .",Activity double NOT NULL default '$i'"
      .",F_Activity double NOT NULL default '$i'"
      .",T_Activity double NOT NULL default '$i'"
      .",D_Activity int NOT NULL default '$k'"
      .",N_Activity decimal(8,3) NOT NULL default '$i'"
      .",PRIMARY KEY (ID)"
      .($noidx?'':",INDEX F_Activity (F_Activity),INDEX T_Activity (T_Activity),INDEX D_Activity (D_Activity), INDEX N_Activity (N_Activity)")
      .")$engine"
      )
      or $TheErrors->dump_exit('test');

   $str= implode('),(',range(1,$sample));
   db_query( 'test.init.ID',
      "INSERT INTO $tname (ID) VALUES ($str)")
      or $TheErrors->dump_exit('test');

   $i= 10; $k= $i*$prec10;
   db_query( 'test.init.acts',
      "UPDATE $tname SET Activity=ID*$i,F_Activity=ID*$i,T_Activity=ID*$i,D_Activity=ID*$k,N_Activity=ID*$i")
      or $TheErrors->dump_exit('test');

   $rfrshtim= $starttim= getmicrotime();
   echo "<pre># Activity factor=$factor ";
   flush(); #ob_flush();

   $tsts= array(
      'F' => array( 'nam' => 'float',
         'qry' => "UPDATE $tname SET F_Activity=$factor*F_Activity WHERE F_Activity>0",
      ),
      'T' => array( 'nam' => 'truncated',
         'qry' => "UPDATE $tname SET T_Activity=TRUNCATE($factor*T_Activity,$precision) WHERE T_Activity>0",
      ),
      'D' => array( 'nam' => 'decimal',
         'qry' => "UPDATE $tname SET D_Activity=FLOOR($factor*D_Activity) WHERE D_Activity>0",
         //'qry' => "UPDATE $tname SET D_Activity=TRUNCATE($factor*D_Activity,0) WHERE D_Activity>0",
         //slower:
         //'qry' => "UPDATE $tname SET D_Activity=$factor*D_Activity-0.5 WHERE D_Activity>0",
      ),
      'N' => array( 'nam' => 'decimal(8,3)',
         'qry' => "UPDATE $tname SET N_Activity=$factor*N_Activity WHERE N_Activity>0",
      ),
   );

   foreach( $tsts as $tst => $dummy )
   {
      $sub= &$tsts[$tst];
      $sub['tim']= 0;
   }
   for( $i=1; $i<=$repeat; $i++ )
   {
      foreach( $tsts as $tst => $dummy )
      {
         $sub= &$tsts[$tst];
         $t= getmicrotime();
         //db_query( 'test.activity_factor.'.$tst,
         mysql_query( $sub['qry']);
         $sub['tim']+= getmicrotime()-$t;
      }

      $t= getmicrotime();
      if( $t-$rfrshtim > 5 )
      {
         echo '.'; flush(); #ob_flush(); //the browser won't freeze... hoping so
         $rfrshtim= $t;
      }
   }

   if( $tmode )
   {
      db_query( 'test.drop', "DROP TEMPORARY TABLE IF EXISTS $tname");
   }

   echo "<br># Tests times:";
   $sub= &$tsts['F'];
   $rn= $sub['nam']; $rt= $sub['tim'];
   echo sprintf("<br>%10s:%7.3fs avg=%7.3fs (used as reference)",$rn,$rt,$rt/$repeat);
   if( $rt > 0 )
   {
      foreach( $tsts as $tst => $dummy )
      {
         if( $tst == 'F' ) continue;
         $sub= &$tsts[$tst];
         $n= $sub['nam']; $t= $sub['tim'];
         $d= $t-$rt; $r= 100*$d/$rt;
         echo sprintf("<br>%10s:%7.3fs avg=%7.3fs diff=%+7.3fs ratio=%+7.2f%%",$n,$t,$t/$repeat,$d,$r);
      }
   }
   $t= getmicrotime()-$starttim;
   echo "<br># Total time: {$t}s</pre>";

   echo "<br>Done";
   end_html();
   exit;
}
?>
