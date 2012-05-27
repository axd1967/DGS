#!/usr/bin/perl
# usage: $0 <IN
# input: "YYYY-MM-DD hh:mm:ss url status size response_time_micros"

#use strict;

my ($date, $time, $url, $status, $size, $rtime);

$cnt = $avg = $min = $max = $sum = 0;

my $rx = shift or die "Missing regex\n";

while (<>) {
   chomp;
   if (($date, $time, $url, $status, $size, $rtime) = split /\s/ )
   {
      #next unless $url =~ /$rx/;
      next unless $status == $rx;

      $rtime /= 1000;
      $min = $rtime if $rtime < $min || !$min;
      $max = $rtime if $rtime > $max;
      $sum += $rtime;
      $cnt++;
   }
}

$avg = $sum / $cnt;
printf("For regex [%s]: count #%s, min [%s], max [%s], avg [%s] ms\n", $rx, $cnt, $min, $max, $avg);

exit 0;

