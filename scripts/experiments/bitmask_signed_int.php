<?php
/*
Dragon Go Server
Copyright (C) 2007  Erik Ouchterlony, Rod Ival

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

/* Author: Rod Ival */

/*!
 * \file bitmask_signed_int.php
 *
 * \brief Script for testing handling of 32-bit bitmasks with PHPs SIGNED(!) integers.
 *
 * \note DB-user needs access-right "CREATE TEMPORARY TABLE".
 */

chdir("../../");
require_once( "include/quick_common.php" );
require_once( "include/connect2mysql.php" );
require_once( "include/std_functions.php" );



{
   echo <<<HEREDOC
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<HTML>

  <HEAD>

  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">


    <TITLE>DGS - Test page</TITLE>

    <LINK REL="shortcut icon" HREF="{$base_path}images/favicon.ico" TYPE="image/x-icon">

    <LINK rel="stylesheet" type="text/css" media="screen" href="{$base_path}skins/dragon/screen.css"><STYLE TYPE="text/css">
a.button { color : white;  font : bold 100% sans-serif;  text-decoration : none;  width : 94px; }

td.button { background-image : url({$base_path}images/button0.gif);background-repeat : no-repeat;  background-position : center; }
pre { background: #dddddd;
 padding: 8px;}
</STYLE>

  </HEAD>

  <BODY bgcolor="#F7F5E3">
HEREDOC;

   echo "Hello.<br>";
   $TheErrors->set_mode(ERROR_MODE_PRINT);

   $tid= get_request_arg( 'test', '1' );

   connect2mysql();

   echo "\n<br>Notice: the 0x100000000 hex number is too big by itself (parser error)";
   echo "<table border=1 cellpadding=4>";
   rout();
   $v= 0x7ffffffe;
   rout("x7ffffffe",$v);
   $v= 0x7ffffffe + 1;
   rout("x7ffffffe + 1",$v);
   $v= 0x7fffffff;
   rout("x7fffffff",$v);
   $v= 0x7fffffff + 1;
   rout("x7fffffff + 1",$v);
   $v= 0x80000000 ;
   rout("x80000000",$v);
   $v= 2147483648;
   rout("2147483648",$v);
   $v= -2147483648;
   rout("-2147483648",$v);
   $v= 0x40000000 * 2 ;
   rout("0x40000000 * 2",$v);
   $v= 0x80000000 + 1 ;
   rout("0x80000000 + 1",$v);
   $v= 0x80000000 + 0 ;
   rout("0x80000000 + 0",$v);
   $v= 0x40000000 << 1 ;
   rout("0x40000000 << 1",$v, 1);
   $v= 0x80000000 ^ 1 ;
   rout("0x80000000 ^ 1",$v, 1);
   $v= 0x80000000 ^ 0 ;
   rout("0x80000000 ^ 0",$v, 1);
   $v= (0x80000000 ^ 0) + 0 ;
   rout("(0x80000000 ^ 0) + 0",$v, 1);
   $v= 0x80000000 - 1 ;
   rout("0x80000000 - 1",$v);
   $v= 2147483648 - 1 ;
   rout("2147483648 - 1",$v);
   $v= -2147483648 - 1 ;
   rout("-2147483648 - 1",$v);
   $v= 2147483648 ^ 0x80000000 ;
   rout("2147483648 ^ 0x80000000",$v);
   $v= -2147483648 ^ 0x80000000 ;
   rout("-2147483648 ^ 0x80000000",$v);
   $v= 0xffffffff + 1 ;
   rout("0xffffffff + 1",$v);
   $v= 0xffffffff + 0 ;
   rout("0xffffffff + 0",$v);
   $v= 0xffffffff - 1 ;
   rout("0xffffffff - 1",$v);
   echo "</table>";

   echo "<pre>INSERT VALUES:";
   $query= '';
   $id= 0;
   for( $k=-2147483648; $k<=4294967296; $k+=0x40000000 )
   {
      for( $j=-1; $j<1; $j++ )
      {
         $m= $k + $j;
         $x= dechex($m);
         //$str= ",(".($id++).",'$x',IF($m<0,4294967296$m,$m),$m)";
         $str= ",(".($id++).",'$x','$m'" //,Hex,Raw
            .",".unsigned($m).",".signed($m) //,U2U,S2S
            .",".signed($m).",".unsigned($m) //,S2U,U2S
            .",($m & 4294967295)" //,xpU
            .",IF(($m & 4294967295)>2147483647,($m & 4294967295)-4294967296,($m & 4294967295))"//,xqU
            //.",($m & 4294967295)" //,xpS
            .")";
         echo "\n", substr("              $m => ",-16),$str;
         $query.=$str;
      }
      $id+=3;
   }

   echo "\n";
   //>>> CAUTION: enabling this part will DROP and CREATE a table!
   if( 1 && $query )
   {
      $query[0]= ' ';
/*
      db_query( 'test.TmpRaz', 'DROP TABLE IF EXISTS tmp_rodival');
      db_query( 'test.TmpCreate',
         "CREATE TABLE tmp_rodival (id INT"
         .", Hex CHAR(16) not null, Raw CHAR(16) not null"
         .", U2U INT(11) UNSIGNED not null, S2S INT(11) not null"
         .", S2U INT(11) UNSIGNED not null, U2S INT(11) not null"
         .", xpU INT(11) UNSIGNED not null, xpS INT(11) not null"
         .")")
         or $TheErrors->dump_exit('test');
      db_query( 'test.TmpRaz', 'TRUNCATE TABLE tmp_rodival');
*/
      db_query( 'test.TmpCreate',
         "CREATE TEMPORARY TABLE IF NOT EXISTS tmp_rodival (id INT"
         .", Hex CHAR(16) not null, Raw CHAR(16) not null"
         .", U2U INT(11) UNSIGNED not null, S2S INT(11) not null"
         .", S2U INT(11) UNSIGNED not null, U2S INT(11) not null"
         .", xpU INT(11) UNSIGNED not null, xpS INT(11) not null"
         .") ENGINE=MEMORY")
         or $TheErrors->dump_exit('test');
      db_query( 'test.TmpInsert',
         'INSERT INTO tmp_rodival (id,Hex,Raw,U2U,S2S,S2U,U2S,xpU,xpS) VALUES'.$query)
         or $TheErrors->dump_exit('test');
      $result= db_query( 'test.read',
         "SELECT * FROM tmp_rodival ORDER BY id");

      echo "\nResult:\n";
      echo MysqlTableStr( $result);
/*
      @mysql_free_result($result);
      db_query( 'test.TmpDrop', 'DROP TEMPORARY TABLE tmp_rodival');
*/
   }

   echo "</pre>";
   echo "<br>Done";
   exit;
}


function type($v) {
   return "(".gettype($v).") ".$v;
}

function signed($v) {
   return $v & 0xffffffff;
}

function unsigned($v) {
   $v= ($v & 0xffffffff);
   if( $v < 0 ) $v+= 4294967296;
   return $v;
}

function rout($s='',$v=0,$w=0)
{
   if( !$s )
   {
      echo "\n<tr><th>Expression</th><th>hex</th><th>raw</th><th>!!!</th><th>(unsigned)</th><th>(signed)</th></tr>";
      return;
   }
   $w= ($w ?'&lt;&lt;' :'&nbsp;');
   echo "\n<tr><td>$s</td><td>x",dechex($v),"</td><td>",type($v),"</td><td>",$w,"</td><td>",type(unsigned($v)),"</td><td>",type(signed($v)),"</td></tr>";
}

function MysqlTableStr( &$result, $free=true) {
   if( $result == false )
      return '';
   $rows = array();
   if( mysql_num_rows($result) > 0 )
   {
      while( $row=mysql_fetch_assoc( $result) ) {
         $rows[] = $row;
      }
      mysql_data_seek($result, 0);
   }
   if( $free ) mysql_free_result($result);
   return TableStr( $rows);
} //MysqlTableStr

function TableStr(&$rows) {
   if( !is_array( $rows) || count( $rows)<1 ) return '';
   $str = '';
   $lens = array();
   foreach( $rows as $row ) {
      if( !is_array( $row) || count( $row)<1 ) continue;
      foreach( $row as $key => $val ) {
         $lens[$key] = max( @$lens[$key], strlen((string)$key), strlen((string)$val));
      }
   }
   if( count( $lens)<1 ) return '';

      foreach( $lens as $key => $len ) {
         $str.= '+-' . str_pad( '', $len, '-', STR_PAD_RIGHT) . '-';
      }  $str.= "+\n";
      foreach( $lens as $key => $len ) { //STR_PAD_LEFT
         $str.= '| ' . str_pad( $key, $len, ' ', STR_PAD_RIGHT) . ' ';
      }  $str.= "| keys\n";
      foreach( $lens as $key => $len ) {
         $str.= '+-' . str_pad( '', $len, '-', STR_PAD_RIGHT) . '-';
      }  $str.= "+\n";
   foreach( $rows as $val => $row ) {
      foreach( $lens as $key => $len ) {
         $str.= '| ' . str_pad( (string)@$row[$key], $len, ' ', STR_PAD_RIGHT) . ' ';
      }  $str.= "| $val\n";
   }
      foreach( $lens as $key => $len ) {
         $str.= '+-' . str_pad( '', $len, '-', STR_PAD_RIGHT) . '-';
      }  $str.= "+\n";
   //reset($rows);
   return $str;
} //TableStr

?>
  </BODY>

</HTML>

