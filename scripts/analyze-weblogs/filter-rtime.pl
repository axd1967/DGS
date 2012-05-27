#!/usr/bin/perl
# usage: $0 min-rtime max-rtime|- <IN
# input: "YYYY-MM-DD hh:mm:ss url status size response_time_micros"

#use strict;

my ($date, $time, $url, $status, $size, $rtime);

my $min = shift or die "Missing min-response-time\n";
my $max = shift || '-';
$max = 0 if $max eq '-';

while (<>) {
   chomp;
   if (@fields = split /\s/)
   {
      # ($date, $time, $url, $status, $size, $rtime) = @fields;
      $rtime = $fields[5];

      print "$_\n" if $rtime >= $min && ( $max <=0 || ( $rtime < $max ) );
   }
}

exit 0;

