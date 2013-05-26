<HTML>
<HEAD>
<TITLE>Test</TITLE>
</HEAD>
<BODY>
<P>
<?php
 function date_format2($datestamp){
    $pattern = "/(19|20)(\d{2})-(\d{1,2})-(\d{1,2}) (\d{1,2}):(\d{1,2}):(\d{1,2})/";
    preg_match ($pattern, "$datestamp", $matches);
       
    if (empty($datestamp) or $datestamp == "0000-00-00") 
      {
        $datestamp = "0000-00-00 00:00:00";
      }

     list($whole, $y1, $y2, $month, $day, $hour, $minute, $second) = $matches;
     $year = $y1 . $y2;

     $hour+=-6;

     $tstamp = gmmktime($hour,$minute,$second,$month,$day,$year);


     $sDate = date('Y-m-d&\n\b\s\p\;H:i',$tstamp);
     return $sDate;
  }



putenv('TZ=GMT' );
echo date('Y-m-d H:i Z') . '<p>';
echo gmdate('Y-m-d H:i Z') . '<p>';

echo date_format2( date('Y-m-d H:i:s') ) . '<p>';
echo date_format2( gmdate('Y-m-d H:i:s') );


?>
</BODY>
</HTML>
