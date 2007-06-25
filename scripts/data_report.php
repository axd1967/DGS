<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

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

chdir( '../' );
require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );

define('UNBUF_TIMOUT', 5); //x seconds limit

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   $player_level = (int)$player_row['admin_level'];
   if( !($player_level & ADMIN_DATABASE) )
      error('adminlevel_too_low');


   $encoding_used= get_request_arg( 'charset', 'UTF-8'); //LANG_DEF_CHARSET iso-8859-1 UTF-8

   $rowhdr= get_request_arg( 'rowhdr', 20);
   $colsize= get_request_arg( 'colsize', 40);
   $colwrap= get_request_arg( 'colwrap', 'cut');

   $oldquery= get_request_arg( 'oldquery', '');
   $unbuffered= (int)(bool)get_request_arg( 'unbuffered', '');

   $apply= (int)(bool)@$_REQUEST['apply'];


   $arg_array = array(
      'select' => array('word'=>'SELECT', 'size'=>4),
      'from'   => array('word'=>'FROM'),
      'join'   => array('word'=>'LEFT JOIN', 'size'=>4),
      'where'  => array('word'=>'WHERE', 'size'=>4),
      'group'  => array('word'=>'GROUP BY'),
      'having' => array('word'=>'HAVING'),
      'order'  => array('word'=>'ORDER BY'),
      'limit'  => array('word'=>'LIMIT'),
      );
   foreach( $arg_array as $arg => $ary)
   {
      $$arg= trim(get_request_arg($arg));
   }


   start_html( 'data_report', 0, '', //@$player_row['SkinName'],
      "  table.Table { border:0; background:#c0c0c0; text-align:left;\n" .
      "   border-spacing:1px; border-collapse:separate; margin:0.5em 2px; }\n" .
      "  table.Table td, table.Table th { padding:4px; }\n" .
      "  tr.Row1 { background: #ffffff; }\n" .
      "  tr.Row2 { background: #dddddd; }\n" .
      "  tr.hil { background: #ffb010; }\n" .
      "  td.Null { background: #ffc0c0; }" );

   echo " <SCRIPT language=\"JavaScript\" type=\"text/javascript\"><!-- \n";
   echo "   function row_click(row,rcl) {
     row.className=((row.className=='hil')?rcl:'hil');
   }\n";
   echo "\n//-->\n</SCRIPT>\n";

   /*
      The mysql_unbuffered_query() implementation was a test. It is useless
      to control the MySQL queries resulting in a too long run time.
      The code just reminds here for memory.
      MySQL < 5.* seems unable to abort a query before the first result row.
      Waiting for better, a query result is now displayed in two steps:
       1) enter (or modify) some fields in the query form.
       2) validate the form: only the "EXPLAIN" of the query is displayed.
       3) check the "EXPLAIN" and re-validate: the query result is displayed.
      The "EXPLAIN" result can be used to avoid some of the too long queries.
   */
   $dbtimout= UNBUF_TIMOUT;
   $dbthread= false;
   $dbcnxctl= false;
   if( $unbuffered )
   {
      //$dbthread = mysql_thread_id( $dbcnx); //PHP4 >= 4.3.0
      for(;;) {
         $result= mysql_query('SHOW PROCESSLIST', $dbcnx);
         $mysqlerror = @mysql_error();
         $numrows = @mysql_num_rows($result);
         if( $mysqlerror || !$result || $numrows != 1 )
         {
            echo "<p>Error: find thread $numrows=".textarea_safe($mysqlerror)."</p>";
            break;
         }
         $row = mysql_fetch_assoc( $result);
         $dbthread= @$row['Id'];
         mysql_free_result( $result);

         $result= mysql_query("SHOW VARIABLES LIKE 'long_query_time'", $dbcnx);
         $mysqlerror = @mysql_error();
         $numrows = @mysql_num_rows($result);
         if( $mysqlerror || !$result || $numrows != 1 )
         {
            echo "<p>Error: find timeout $numrows=".textarea_safe($mysqlerror)."</p>";
            break;
         }
         $row = mysql_fetch_assoc( $result);
         $dbtimout= (float)@$row['Value']-1;
         mysql_free_result( $result);

         //if( echo_query( 'SHOW VARIABLES', 'query_svar', 0, 0, 0, 0) < 0 ) break;
         //long_query_time is in second

         break;
      }
      if( !$dbthread )
      {
         mysql_close( $dbcnx);
         error('mysql_connect_failed', "data_report.dbthread($dbthread)");
      }

      //add a controller link
      $dbcnxctl = mysql_connect( $MYSQLHOST, $MYSQLUSER, $MYSQLPASSWORD, true );
      if( !$dbcnxctl )
      {
         mysql_close( $dbcnx);
         error('mysql_connect_failed', 'data_report.dbcnxctl');
      }
   }
   //echo_query('SHOW PROCESSLIST', 'query_spro', 0, 0, 0, 0) < 0 );


   $dform = new Form('dform', 'data_report.php#result', FORM_POST, true );
   $query= '';
   $uri= '';
   foreach( $arg_array as $arg => $ary )
   {
      $word= $ary['word'];
      $tmp= ($word == 'LEFT JOIN' ?$word.'<BR>(including ON)' :$word);
      $n= max(1,@$ary['size']);
      $dform->add_row( array( 'DESCRIPTION', $tmp,
                              'TEXTAREA', $arg, 120, $n, $$arg ) );
      if( $select && $$arg )
      {
         $query.= $word . ' ' . $$arg . ' ';
         $uri.= $arg . '=' . urlencode($$arg) . URI_AMP;
      }
   }
   $formcol= 2;

   $query= trim($query);
   if( $select && $query )
   {
      $uri= 'http://' . $HOSTNAME . @$_SERVER['PHP_SELF'] . '?' . $uri;
      $ary= array();
      $dform->get_hiddens( $ary);
      $uri= make_url($uri, $ary, 1);
      $uri.= 'apply=1#result';
   }

   if( $apply )
   {
      if( $query && $query === $oldquery )
         $execute = 1;
      else
         $execute = 0;
   }
   else
      $execute = 0;

   $dform->add_row( array(
      'HIDDEN', 'charset', $encoding_used,
      'HIDDEN', 'oldquery', $query,
      'CELL', $formcol, 'align="center"',
      'CHECKBOX', 'unbuffered', 1, 'Unbuffered&nbsp;&nbsp;&nbsp;', $unbuffered,
      'OWNHTML', '<INPUT type="submit" name="apply" accesskey="x" value="Apply [&x]">',
      'TEXT', '&nbsp;&nbsp;col size:&nbsp;',
      'TEXTINPUT', 'colsize', 3 , 3, $colsize,
      'RADIOBUTTONS', 'colwrap'
         , array('cut'=>'cut','wrap'=>'wrap',''=>'none',), $colwrap,
      ) );

   $dform->echo_string(1);
   echo "<p>&nbsp;</p>\n";


   if( $query )
   {
      echo 'Query&gt; ' . anchor( $uri, textarea_safe($query).';') . "&nbsp;<br>\n";
      //echo "&nbsp;<br>\n";
   }

   echo "<div id='result'>\n";
   while( $apply && $query )
   {
      $apply=0;
      if( $execute )
      {
         //maybe, test the LIMIT params and force $qryunbuf:
         $qryunbuf = $unbuffered;

         $n= echo_query( $query, 'query_result'
                        , $qryunbuf, $rowhdr, $colsize, $colwrap);

         if( $n < 0 )
            break;

         if( is_array($n) )
            list( $n,$qrytime) = $n;
         else
            $qrytime=-1;

         $s= $dbtimout*1e3;
         $s= "SELECT '$n' as 'Rows'"
            . ($qrytime<0 ? '' :",'${qrytime}ms' as 'Duration'")
            . ",'${s}ms' as 'Time out'"
            . ",NOW() as 'Mysql time'"
            . ",FROM_UNIXTIME($NOW) as 'Server time'"
            . ",'" . date('Y-m-d H:i:s', $NOW) . "' as 'Local time'"
            . ",'" . gmdate('Y-m-d H:i:s', $NOW) . "' as 'GMT time'"
            //. ",'".mysql_info()."' as 'Infos'"
            ;
         if( echo_query( $s, 'query_info', 0, 0, 0, 0) < 0 ) break;

      }
      else
      {
         echo "<span>Just EXPLAIN</span>";
      }

      if( echo_query( 'EXPLAIN '.$query, 'query_explain', 0, 0, 0, 0) < 0 ) break;

      echo 'Query&gt; ' . anchor( $uri, textarea_safe($query).';') . "&nbsp;<br>\n";
   }
   echo "</div>\n";

   end_html();
}


function echo_query( $query, $qid='', $unbuffered=false, $rowhdr=20, $colsize=40, $colwrap='cut' )
{
   global $dbcnx, $dbcnxctl, $dbthread, $dbtimout;

   $info= '';

   if( !$dbcnxctl || !$dbthread )
      $unbuffered= false;

   //kill sensible fields from a query like "SELECT Password as pwd FROM Players"
   if( !@$GLOBALS['Super_admin'] )
      $query= preg_replace("%(Password|Sessioncode|Email)%is", "***", $query);

   $table = array();
   $numrows = 0;
   $qrytime = getmicrotime();
   if( $unbuffered && $dbtimout<=0 )
   {
      $info = "#Time > ${dbtimout}s";
      $qrytime = 0;
   }
   else if( $unbuffered )
   {
      $qrytout = $qrytime + $dbtimout; //in second
      $rowtime = getmicrotime();
      $result = mysql_unbuffered_query( $query, $dbcnx); //PHP4 >= 4.0.6

/*
      $mysqlerror = @mysql_error( $dbcnx);
      if( $mysqlerror )
      {
         echo "<p>Error: ".textarea_safe($mysqlerror)."</p>";
         return -1;
      }
*/

      if( !$result )
         return 0;

      while( $row = mysql_fetch_assoc( $result) )
      {
         $tmp= $rowtime;
         $rowtime= getmicrotime();
         $row['#row ms']= round(($rowtime - $tmp) * 1e3, 2);
         $table[] = $row;
         $numrows++;

         if ( $rowtime > $qrytout )
         {
            // this is taking too long
            mysql_query("KILL $dbthread", $dbcnxctl);
            $info= "#Time > ${dbtimout}s";
            break;
         }
      }
      $qrytime = round((getmicrotime() - $qrytime) * 1e3, 2);
      mysql_free_result( $result);

      if( $numrows<=0 )
         return array(0,$qrytime);
   }
   else
   {
      $result = mysql_query( $query, $dbcnx);
      $qrytime = round((getmicrotime() - $qrytime) * 1e3, 2);

      $mysqlerror = @mysql_error();
      if( $mysqlerror )
      {
         echo "<p id=query_error>Error: ".textarea_safe($mysqlerror)."</p>";
         return -1;
      }

      if( !$result )
         return 0;

      $numrows = @mysql_num_rows($result);
      if( $numrows<=0 )
      {
         @mysql_free_result( $result);
         return array(0,$qrytime);
      }

      while( $row = mysql_fetch_assoc( $result) )
         $table[] = $row;

      mysql_free_result( $result);
   }


   $c=0;
   $i=0;
   $ncol= 0;
   if( $qid>'' )
      $qid = " id='$qid'";
   else
      $qid = '';
   echo "\n<table$qid class=Table title='$numrows rows'>\n";
   foreach( $table as $row )
   {
      $c=($c % LIST_ROWS_MODULO)+1;
      $i++;
      if( $i==1 or ($rowhdr>1 && ($i%$rowhdr)==1) )
      {
         $ncol= 0;
         echo "<tr class=Head>\n";
         foreach( $row as $key => $val )
         {
            echo "<th>$key</th>";
            $ncol++;
         }
         echo "\n</tr>";
      }
      echo "<tr class=Row$c ondblclick=\"row_click(this,'Row$c')\">\n";
      foreach( $row as $key => $val )
      {
         //remove sensible fields from a query like "SELECT * FROM Players"
         if( !@$GLOBALS['Super_admin'] )
         switch( $key )
         {
            case 'Newpassword':
            case 'Password':
            case 'Sessioncode':
            case 'Email':
               if ($val) $val= '***';
               break;
            case 'Debug':
               if ($val) if( !@$GLOBALS['Super_admin'] )
                  $val= preg_replace("%(passwd=)[^&]*%is", "\\1***", $val);
               break;
         }

         if( !isset($val) )
         {
            $val= '';
            $class= ' class="Null"';
         }
         else
         {
            $val= textarea_safe($val);
            $class= '';
         }

         if( $colsize>0 )
         {
            if( $colwrap==='wrap' )
               $val= wordwrap( $val, $colsize, '<br>', 1);
            else if( $colwrap==='cut' )
               $val= substr( $val, 0, $colsize);
         }
         echo "<td$class title='$key#$i' nowrap>$val</td>";
      }
      echo "\n</tr>";
   }
   if( $info )
   {
      $tmp= $ncol > 1 ?" colspan=$ncol" :'';
      echo "<tr><td$tmp>$info</td></tr>\n";
   }
   echo "\n</table>";
   //echo "<font size=-1>Query time: $qrytime&nbsp;ms</font><br>\n";

   return array($numrows,$qrytime);
}

?>