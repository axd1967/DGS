#!/usr/bin/perl -w
#
# Perl script to find all translatable sentences.
#

while( <*.php include/*.php> )
{
    $filename = $_;

    select STDOUT;

    open(INFILE, "<$filename") or die "Can't open input file: $!\n";

    $/ = ""; # take all input
    while (<INFILE>)
    {
        if( /T_\(([^)]*)\)/g )
        {
            $a = $1;
            $a =~ s/['"]\s*\.\s*["']//g;
            $a =~ s/\\n/\n/g;
            print $filename . ': ' . $a . "\n";
        }
    }

    close(INFILE);
}

