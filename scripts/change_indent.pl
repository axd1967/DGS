#!/usr/bin/perl

## Dragon Go Server
## Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar
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


# Usage: change_indent.pl
#
# Scans for all files configured in this script, changing indent from 3 to 4 spaces.

use strict;

# ---------- process all files -----------------------------

my @scan_cmds = (
   # [ dir, file-pattern ]

   [ '.', '*.php' ],
   [ '.', '*.pl' ],
   [ '.', '*.js' ],
   [ '.', '*.txt' ],
   [ '.', '*.css' ],
   [ '.', '*.xml' ],
   [ '.', 'INSTALL' ],
   [ '.', 'NEWS' ],
   [ '.', 'ChangeLog*' ],
   [ '.', 'database_changes_*.mysql' ],
   [ '.', 'README.*' ],
   #[ '.', 'TEST.file' ],
);

# scan for filenames to adjust
my @files = ();
foreach my $arrpath (@scan_cmds) {
   my ($dir, $filepatt) = @$arrpath;
   my @arr = `find $dir -type f -name "$filepatt" `;
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
   # update indentation
   $buf =~ s/^((   )+)/replace_indent("$1")/meg;
   #--------------------------------------------------------

   # write file back
   open(OUT, ">$file") or die "Can't open file [$file] for writing\n";
   print OUT $buf;
   close OUT;
}

exit 0;

sub replace_indent {
   my $s = shift;
   my $c = length($s) / 3;
   return $s . ( ' ' x $c );
}

__END__

