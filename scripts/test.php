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

chdir("../");
require_once( "include/quick_common.php" );
require_once( "include/connect2mysql.php" );
chdir("scripts");
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<HTML>

  <HEAD>

  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">


    <TITLE>DGS - Test page</TITLE>

    <LINK REL="shortcut icon" HREF="images/favicon.ico" TYPE="image/x-icon">

    <LINK rel="stylesheet" type="text/css" media="screen" href="dragon.css"><STYLE TYPE="text/css">
a.button { color : white;  font : bold 100% sans-serif;  text-decoration : none;  width : 94px; }

td.button { background-image : url(../images/button0.gif);background-repeat : no-repeat;  background-position : center; }
pre { background: #dddddd; padding: 8px;}
</STYLE>

  </HEAD>

  <BODY bgcolor="#F7F5E3">
<?php
   echo "Hello.<br>";

   $tid= get_request_arg( 'test', '1' );

   $arg_ary = array(
      'v0' => 'Score DESC, Time DESC',
      'v1' => 'Score DESC,Time DESC',
      'v2' => 'Score DESC,Time',
      'v3' => 'Score, Time DESC',
      'v4' => 'Score,Time DESC',
      'v5' => 'Score,Time',
      );

   $res= get_request_arg( 'stest', '' );
   if( !$res )
   {
      echo '<FORM action="test.php" method="post" name="ftest">';
      echo '<input name="test" value="'.$tid.'" type="hidden">';
      switch( $tid )
      {
      case 2:
         echo '<input name="term" value="FAQ" type="hidden">';
         break;
      default:
         echo '<input name="term" value="FAQ|topics" type="hidden">';
         break;
      }
      foreach( $arg_ary as $key => $str )
      {
         echo '<input name="'.$key.'" value="'.$str.'" type="hidden">';
      }
      echo 'First, ';
      echo '<input name="stest" value="Hit me" type="submit">';
      echo '</form>';
   }
   else
   {
      echo 'Then cut & paste:<pre>&lt;code>';
      $arg= get_request_arg( 'term', '' );
      echo sprintf('<br>t%s: term => "%s"'
            ,$tid
            ,htmlentities( $arg, ENT_QUOTES)
            );
      foreach( $arg_ary as $key => $str )
      {
         $arg= get_request_arg( $key, '' );
         echo sprintf('<br>%s: "%s" => "%s"'
            ,$key
            ,htmlentities( $str, ENT_QUOTES)
            ,htmlentities( $arg, ENT_QUOTES)
            );
      }
      echo '<br>&lt;/code></pre>';
      echo '<a href="test.php?test='.$tid.'">Redo</a>';
   }
?>
  </BODY>

</HTML>

