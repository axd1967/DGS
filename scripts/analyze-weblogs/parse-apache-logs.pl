#!/usr/bin/perl
# usage: parse-apache-logs.pl <log
# expecting lines of format: see "LogFormat" below
# filter: modify as wanted at #FILTER
# output: modify as wanted at #OUTPUT
# standard-output: "YYYY-MM-DD hh:mm:ss url status size response_time_micros"

use strict;

my $h_mon = { 'Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4, 'May' => 5, 'Jun' => 6, 'Jul' => 7, 'Aug' => 8, 'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12 };
my ($remote_ip, $remote_logname, $remote_user, $timestamp, $http_action, $url, $protocol, $status, $size, $referer, $user_agent, $response_time);

my $lcnt = 0;
while (<>) {
   $lcnt++;
   chomp;
   s/\\"//g; # remove escaped quote
   if (($remote_ip, $remote_logname, $remote_user, $timestamp, $http_action, $url, $protocol, $status, $size, $referer, $user_agent, $response_time) =
         /^(\S+)\s+(\S+)\s+(\S+)\s+\[([^\]]+)\]\s+"(\S+)\s+([^"]+)\s+(\S+)"\s+(\d+)\s+(\S+)\s+"([^"]*)"\s+"([^"]*)"\s+(\S+)$/)
   {
      $timestamp =~ s/^(\d+)\/(\w+)\/(\d+):(\S+)\s+(\S+)$/sprintf('%04d-%02d-%02d %s', $3, $h_mon->{$2}, $1, $4)/e; # "DD/MMM/YYYY:HH:MM:SS NZZZZ" -> "YYYY-MM-DD HH:MM:SS"
      #$url =~ s/^\///; # strip away leading '/'
      $url =~ s/\?.*$//; # strip away query
      $size = 0 if $size eq '-'; # replace '-' as size -> 0
      $response_time =~ s/^"(\d+)"$/$1/; # strip optional quotes "%D" -> %D
      #$response_time = int( $response_time / 1000 ); # macro-secs -> ms

      #FILTER
      #next if $timestamp =~ /^(14|14)-(05)-(2012)/;
      #next unless $status =~ /^(200)$/;
      #next if $response_time <= 10_000_000; # skip <10s

      #OUTPUT
      printf("$timestamp $url $status $size $response_time\n");
   }
   else {
      print STDERR "Parse error of line #$lcnt [$_]\n";
   }
}

exit 0;

__END__

LogFormat:
   %a %l %u %t \"%r\" %>s %b "%{Referer}i" "%{User-agent}i" %D|"%D"

%a = remote ip, or %h instead = remote host
%l = remote logname | "-"
%u = remote user (from auth)
%t = time
%r = request 1st line
%>s = last request status
%b = response-size [bytes] w/o header, "-" = no bytes sent
%{Referer}i = header-line "Referer"
%{User-agent}i = header-line "User-agent"
%D = response-time [micro-secs]

Example:
   89.31.40.122 - - [14/May/2012:20:41:58 +0200] "GET /game.php?gid=729285 HTTP/1.1" 200 6550 "http://www.dragongoserver.net/status.php" "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.19 (KHTML, like Gecko) Chrome/18.0.1025.168 Safari/535.19" 3410302

