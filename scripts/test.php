<?php
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
   echo "Hello. ";

   $arg_ary = array(
      'v0' => 'Score DESC, Time DESC',
      'v1' => 'Score DESC,Time DESC',
      'v2' => 'Score DESC,Time',
      'v3' => 'Score, Time DESC',
      'v4' => 'Score,Time DESC',
      'v5' => 'Score,Time',
      );

   echo '<FORM action="test.php" method="post" name="ftest">';
   foreach( $arg_ary as $key => $str )
   {
      echo '<input name="'.$key.'" value="'.$str.'" type="hidden">';
   }
   echo '<br>First, ';
   echo '<input name="stest" value="Hit me" type="submit">';
   echo '</form>';

   $arg= get_request_arg( 'stest', '' );
   if( $arg )
   {
      echo 'Then cut & paste:<pre>&lt;code>';
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
   }
?>
  </BODY>

</HTML>

