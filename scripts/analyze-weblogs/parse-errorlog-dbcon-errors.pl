#!/usr/bin/perl
# grep DB_CONNECT_ERROR apache-error-log | $0 >dbcon-err.csv
#
# CSV-headers:
#   YYYY-MM-DD;hh:mm:ss;hh;hh:mm;client_ip;errorcode;dberror;request;referer

use strict;

my $hd = {
   'Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4, 'May' => 5, 'Jun' => 6,
   'Jul' => 7, 'Aug' => 8, 'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12,
};

my ($mon, $day, $time, $hour_min, $hour, $year, $ip, $err, $dberr, $req, $ref );

print "Date;Time;Hour;HourMin;Client_IP;ErrorCode;DbErr;Request;Referer\n";

my $lcnt = 0;
while (<>) {
   $lcnt++;
   chomp;
   if ( ($mon, $day, $time, $hour_min, $hour, $year, $ip, $err, $dberr, $req, $ref ) 
         = (/^\[\w+ (\w+) (\d+) (((\d+):\d+):\d+) (\d+)\] \[\w+\] \[client ([0-9\.]+)\] DB_CONNECT_ERROR: err \[([^\]]+)\], mysql-err \[([^\]]+)\] on request \[(.*?)\](?:, referer: (.*?))?\s*$/)) {
      printf("%04d-%02d-%02d;%s;%s;%s;%s;%s;%s;%s;%s\n", 
         $year, $hd->{$mon}, $day, $time, $hour, $hour_min, $ip, $err, $dberr, $req, $ref );
   } else {
      print STDERR "Can't parse line #$lcnt [$_]\n";
   }
}

exit 0;

__END__
[Wed Nov 26 04:59:23 2014] [error] [client 98.195.87.209] DB_CONNECT_ERROR: err [mysql_connect_failed], mysql-err [Too many connections] on request [index.php?logout=t], referer: http://www.dragongoserver.net/error.php?err=mysql_connect_failed&mysqlerror=Too+many+connections&req_uri=message.php%3Fmode%3DShowMessage%26mid%3D2851835
[Wed Nov 26 09:36:32 2014] [error] [client 78.46.100.166] DB_CONNECT_ERROR: err [mysql_connect_failed], mysql-err [Too many connections] on request [index.php?err=not_logged_in&eid=0&page=gameinfo.php%3Fgid=175280]

