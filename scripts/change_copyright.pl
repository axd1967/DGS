#!/usr/bin/perl

## Dragon Go Server
## Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar
##
## This program is free software: you can redistribute it and/or modify
## it under the terms of the GNU Affero General Public License as
## published by the Free Software Foundation, either version 3 of the
## License, or (at your option) any later version.
##
## This program is distributed in the hope that it will be useful,
## but WITHOUT ANY WARRANTY; without even the implied warranty of
## MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
## GNU Affero General Public License for more details.
##
## You should have received a copy of the GNU Affero General Public License
## along with this program.  If not, see <http://www.gnu.org/licenses/>.


# Usage: change_copyrigh.pl YEAR
#
# Scans for all files configured in this script, applying changes on
# copyright-line adjusting date and save back changes.

use strict;

die "Usage: $0 YEAR\nERROR: Missing year\n" unless ( @ARGV == 1 );
my $year = shift;
die "Bad year [$year]\n" unless $year =~ /^\d{4}$/;
die "Bad year [$year]\n" if( $year < 2000 || $year > 2999);

# ---------- process all files -----------------------------

my @scan_cmds = (
   # [ dir, file-pattern ]
   [ '.', '*.php' ],
   [ '.', '*.pl' ],
   [ '.', '*.js' ],
   [ '.', '*.txt' ],
   [ '.', '*.css' ],
   [ '.', '*.inc' ],
   [ '.', '*.pov' ],
   [ '.', '*akefile' ],
   [ '.', 'NEWS' ],
);

# scan for filenames to adjust
my @files = ();
foreach my $arrpath (@scan_cmds) {
   my ($dir, $filepatt) = @$arrpath;
   my @arr = `find $dir -type f -name "$filepatt" |xargs grep -l Copyright`;
   foreach (@arr) {
      chomp;
      push @files, $_;
   }
}

foreach my $file (@files) {
   # read file into buffer
   my $buf = '';
   open(IN, "<$file") or die "Can't open file [$file] for reading\n";
   while (<IN>) {
      $buf .= $_;
   }
   close IN;

   # apply change for file on $buf
   print STDERR "Processing file [$file] ...\n";
   #--------------------------------------------------------
   # update copyright
   $buf =~ s/Copyright .C. \d+(-\d+)? /Copyright (C) 2001-$year /m;
   #--------------------------------------------------------

   # write file back
   open(OUT, ">$file") or die "Can't open file [$file] for writing\n";
   print OUT $buf;
   close OUT;
}

exit 0;

__END__

