#!/usr/bin/perl -w
#
# Perl script to find all translatable sentences.
#

select STDOUT;

print "<?php\n\$all_translations=array(\n";

while( <*.php include/*.php> )
{
    $filename = $_;

    open(INFILE, "<$filename") or die "Can't open input file: $!\n";

    $/ = ""; # take all input
    while (<INFILE>)
    {
        while( /T_\((['"].*?['"])\)[^'"]/gs )
        {
            $a = $1;
            $a =~ s/['"]\s+\.\s+["']//g;
            $a =~ s/\\n/\n/g;
            print "array( 'CString' => " . $a . ", 'File' => '" . $filename . "' ),\n";
        }
    }

    close(INFILE);
}

print "'' );\n?>\n";
