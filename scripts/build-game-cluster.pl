#!/usr/bin/perl
#
# Script to detect, if there are clusters of users only playing users within
# the cluster therefore having their "own" encapsulated rating-system.
#
# From DGS-wishlist:
# - algorithm, see http://www.dragongoserver.net/forum/read.php?forum=4&thread=4690#4692
# - script-result, see http://www.dragongoserver.net/forum/read.php?forum=4&thread=4690#25424
#
# Usage: build-game-cluster.pl <input-file
# NOTE: Expecting input file of format: lines with "Black_ID  White_ID"

use strict;

my $include_users = shift;

my $cluster = {}; # cluster-ID -> { user => cnt, ... }
my $users = {};   # user -> cluster-ID

my @games = (); # [ [ id1, id2 ], ... ]

print STDERR "Reading games ...\n";
while (<>) {
   chomp;
   if (/^\s*(\d+)\D+(\d+)\D*$/) {
      my ($id1,$id2) = ($1,$2);
      next if ($id1 == 1 || $id2 == 1); # skip guest-ID
      $users->{$id1} = $id1;
      $users->{$id2} = $id2;
      $cluster->{$id1} = { $id1 => 0 };
      $cluster->{$id2} = { $id2 => 0 };
      push @games, [ $id1, $id2 ];
   } else {
      die "Unknown syntax [$_]\n";
   }
}

my $cnt = 0;
my $cnt_games = scalar(@games);
foreach my $arr (@games) {
   my ($id1,$id2) = @$arr;
   my $cl1 = $users->{$id1};
   my $cl2 = $users->{$id2};
   print STDERR sprintf( "Handling %d of %d ...\n", $cnt, $cnt_games ) unless ($cnt++ % 1000);

   # determine min/max cluster-id
   my ($cluster_id,$mod_clid);
   if( $cl1 < $cl2 ) {
      $cluster_id = $cl1;
      $mod_clid = $cl2;
   } else {
      $cluster_id = $cl2;
      $mod_clid = $cl1;
   }

   # build cluster
   my $cl_map = $cluster->{$cluster_id};
   $cl_map->{$id1}++;
   $cl_map->{$id2}++;

   # update all users with max-cluster-IDs to min-cluster-id
   if( $cluster_id != $mod_clid ) {
      my $cl_map2 = $cluster->{$mod_clid};
      foreach my $uid (keys %{$cl_map2}) {
         $users->{$uid} = $cluster_id;
         $cl_map->{$uid} += $cl_map2->{$uid};
      }
      $cluster->{$mod_clid} = {};
   }
}


# output clusters
my $cnt_cl = 0;
my $h_out = {}; # $cl_id => [ cnt, out ]
foreach my $clid (sort keys %{$cluster}) {
   my $map = $cluster->{$clid};
   my $cnt_map = scalar(keys %{$map});
   next if $cnt_map <= 1;
   $cnt_cl++;
   my $cnt_c = 0; # double contact between 2 players
   my @arr_map = keys %{$map};
   foreach my $uid (keys %{$map}) {
      $cnt_c += $map->{$uid};
   }
   $cnt_c >>= 1;
   $h_out->{$clid} = [ $cnt_map, $cnt_c, sprintf( "Cluster[%06d]: #users = %d, #games = %d\n", $clid, $cnt_map, $cnt_c ), \@arr_map ];
}

print "\n";
printf( "There are %d clusters:\n", $cnt_cl );
printf( "There are %d games:\n", scalar(@games) );
foreach my $clid (sort { $h_out->{$b}->[1] <=> $h_out->{$a}->[1] } keys %{$h_out}) { # sort by #games
   my $h = $h_out->{$clid};
   print $h->[2];
   print "    " . join( ", ", @{$h->[3]} )."\n" if $include_users && $h->[1] < 100;
}

exit 0;

__END__
