#!/bin/bash
# php scripts/admin-sgf-bulk.php exec 200 5

year=$1

mkdir -p sgf-archive/$year

tar cjf sgf-archive/$year/dgs-$year-01.tar.bz2 $year/01/
tar cjf sgf-archive/$year/dgs-$year-02.tar.bz2 $year/02/
tar cjf sgf-archive/$year/dgs-$year-03.tar.bz2 $year/03/
tar cjf sgf-archive/$year/dgs-$year-04.tar.bz2 $year/04/
tar cjf sgf-archive/$year/dgs-$year-05.tar.bz2 $year/05/
tar cjf sgf-archive/$year/dgs-$year-06.tar.bz2 $year/06/
tar cjf sgf-archive/$year/dgs-$year-07.tar.bz2 $year/07/
tar cjf sgf-archive/$year/dgs-$year-08.tar.bz2 $year/08/
tar cjf sgf-archive/$year/dgs-$year-09.tar.bz2 $year/09/
tar cjf sgf-archive/$year/dgs-$year-10.tar.bz2 $year/10/
tar cjf sgf-archive/$year/dgs-$year-11.tar.bz2 $year/11/
tar cjf sgf-archive/$year/dgs-$year-12.tar.bz2 $year/12/

