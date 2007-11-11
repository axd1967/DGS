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
  echo "Hello";

   $str= '!"$%()*+,-.:;<=>?@[\]^_{|}~';
   $url= urlencode($str);
   $arg= get_request_arg( 'arg', '' );
   echo '<pre>Results:';
   echo '<br>str='.htmlentities( $str, ENT_QUOTES);
   echo '<br>url='.htmlentities( $url, ENT_QUOTES);
   echo '<br>arg='.htmlentities( $arg, ENT_QUOTES);
   echo '<br>htm='.htmlentities( htmlentities( $arg, ENT_QUOTES), ENT_QUOTES);
   echo '</pre>';
   echo '<a href="test.php?arg='.$url.'">Test it</a>';
?>
  </BODY>

</HTML>

