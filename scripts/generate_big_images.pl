#!/usr/bin/perl -w

use Gimp qw(:auto);

Gimp::init;

foreach $color ('b', 'w')
{
    if( $color eq 'b' )
    {
        $file='BigBlack.png';
        $markfile='BigBlackMark.png';
        $fg_color=[255, 255, 255];
    }
    else
    {
        $file='BigWhite.png';
        $markfile='BigWhiteMark.png';
        $fg_color=[0, 0, 0];
    }

    $theImage = gimp_file_load($file, $file);
    $theLayer = gimp_image_active_drawable( $theImage );
    plug_in_autocrop( $theImage, $theLayer );
    gimp_palette_set_foreground( $fg_color );
    print $file . "\n";
    file_png_save( $theImage, $theLayer, $file, $file, 0, 9, 0, 0, 0, 0, 0);

    gimp_ellipse_select( $theImage, 100, 100, 300, 300, REPLACE, 1, 0, 0);
    gimp_ellipse_select( $theImage, 150, 150, 200, 200, SUB, 1, 0, 0);
    gimp_bucket_fill( $theLayer, FG_BUCKET_FILL, NORMAL_MODE, 80, 15, 1, 125, 125);
    gimp_selection_none( $theImage);
    print $markfile . "\n";
    file_png_save( $theImage, $theLayer, $markfile, $markfile, 0, 9, 0, 0, 0, 0, 0);
}



@files = ('YinYang.png', 'BigPlayBlack.png', 'BigPlayWhite.png');

foreach $file (@files)
{
    $theImage = gimp_file_load($file, $file);
    $theLayer = gimp_image_active_drawable( $theImage );
    plug_in_autocrop( $theImage, $theLayer );
    gimp_palette_set_foreground( $fg_color );
    print $file . "\n";
    file_png_save( $theImage, $theLayer, $file, $file, 0, 9, 0, 0, 0, 0, 0);
}

Gimp::end;

