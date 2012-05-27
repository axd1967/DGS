#!/usr/bin/perl
# usage: $0 field-colnum value rx <IN
# input: "YYYY-MM-DD hh:mm:ss url status size response_time_micros"

#use strict;

my ($date, $time, $url, $status, $size, $rtime);

$cnt = $avg = $min = $max = $sum = 0;

my $col = shift or die "Missing field-colnum\n";
my $rx = shift or die "Missing regex\n";
$col--;

while (<>) {
   chomp;
   if (@fields = split /\s/)
   {
      # ($date, $time, $url, $status, $size, $rtime) = @fields;
      print "$_\n" if $fields[$col] =~ /$rx/i;
   }
}

exit 0;

