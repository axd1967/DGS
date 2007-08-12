// Dragon Go Server
// Copyright (C) 2001-2007  Erik Ouchterlony

// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software Foundation,
// Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.

global_settings { assumed_gamma 2.2 }
//global_settings { ambient_light 1.2 }

#include "stone.inc"

#ifndef( YINYANG )
#declare YINYANG=0;
#end

#declare Ratio = 1.1;
#declare SideLength = 0.45;
#declare Thickness = 0.1;
#declare Margin = 0.004;
#declare LineThickness = 0.0002;
#declare LineWidth = 0.0012;
#declare StoneSize = 0.0212;
#declare ExtraSizeBlack = 0.0006;
#declare StoneThickness = 0.0092;

camera
{
  location <-0.0, 1.0, -0.0>
  look_at <0, 0, 0>
  angle 2.5
}


light_source {  <-1.2, 3, 1.3>  color 1.31*<1.0,1.0,0.99>  }
light_source {  <-10, -0.5, -10.3>  colour 0.8  shadowless }

// light_source {  <-0.9, 5, 2.4>  color 0.7*<1.0, 1.0, 1.03> }
// light_source {  <-3.9, 1, 0.4>  color 0.4*<1.0, 1.0, 0.95> }
// light_source {  <2.9, 4, -2.7>  color 0.4*<1.0, 1.0, 0.95> }
// light_source {  10*<-0.9, -0.1, 2.4>  colour 0.5  shadowless }

#declare StoneSize = 0.0212;
#declare ExtraSizeBlack = 0.0006;
#declare StoneThickness = 0.0092;


#if( YINYANG )
  WhiteStone ( <0, 0, 0>, StoneSize, StoneThickness )
  BlackStone ( <0, 0, 0>, StoneSize, StoneThickness )
#end

#ifdef( PLAY_W )
  WhiteStone ( <0.003, 0.005, 0>, StoneSize, StoneThickness )
  BlackStone ( <-0.003, 0, 0>, StoneSize, StoneThickness )
#end

#ifdef( PLAY_B )
  WhiteStone ( <0.003, 0, 0>, StoneSize, StoneThickness )
  BlackStone ( <-0.003, 0.005, 0>, StoneSize, StoneThickness )
#end

// Rodival: not well implanted:
#ifdef( W_B_PLAY )
  BlackStone ( <0.003, 0, 0>, StoneSize, StoneThickness )
  WhiteStone ( <-0.003, 0.005, 0>, StoneSize, StoneThickness )
#end

#ifdef( B_W_PLAY )
  WhiteStone ( <0.003, 0, 0>, StoneSize, StoneThickness )
  BlackStone ( <-0.003, 0.005, 0>, StoneSize, StoneThickness )
#end

#ifdef( W_W_PLAY )
  WhiteStone ( <0.003, 0, 0>, StoneSize, StoneThickness )
  WhiteStone ( <-0.003, 0.005, 0>, StoneSize, StoneThickness )
#end

#ifdef( B_B_PLAY )
  BlackStone ( <0.003, 0, 0>, StoneSize, StoneThickness )
  BlackStone ( <-0.003, 0.005, 0>, StoneSize, StoneThickness )
#end

#ifdef( WHITE )
  WhiteStone ( <0, 0, 0>, StoneSize, StoneThickness )
#end

#ifdef( BLACK )
  BlackStone ( <0, 0, 0>, StoneSize, StoneThickness )
#end
