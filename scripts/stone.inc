// Dragon Go Server
// Copyright (C) 2002-2003  Erik Ouchterlony

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

#macro StoneShape( pos, Size, Thickness, yin, yang )

  #if( yang )
  union
  {
    intersection
    {
      sphere { 0, 1 }
      cylinder { <0,-1,-0.5>, <0,1,-0.5>, 0.5 inverse }
      box { <-1,-1,-1>, <0,1,1> }
    }
    intersection
    {
      sphere { 0, 1 }
      cylinder { <0,-1,0.5>, <0,1,0.5>, 0.5 }
    }
  }
  #else
  #if( yin )
  union
  {
    intersection
    {
      sphere { 0, 1 }
      cylinder { <0,-1,0.5>, <0,1,0.5>, 0.5 inverse }
      box { <0,-1,-1>, <1,1,1> }
    }
    intersection
    {
      sphere { 0, 1 }
      cylinder { <0,-1,-0.5>, <0,1,-0.5>, 0.5 }
    }
  }

  #else
  sphere { 0, 1 }
  #end
  #end

  scale 0.5*<-Size, Thickness, Size>
  translate pos + 0.5*Thickness*y
#end




#macro BlackStone( pos, Size, Thickness )

  object
  {
    StoneShape(pos, Size, Thickness, YINYANG, 0)

    texture
    {
      pigment { colour 0.14*<1,1,1> }
      finish
      {
        ambient 1.3
//         diffuse 0.1
//         brilliance 0.2
//         reflection 0.2
//         specular 0.15
//         roughness 0.3
        phong 0.25
        phong_size 6
      }
    }
  }

#end

#macro WhiteStone( pos, Size, Thickness )

  object
  {
    StoneShape(pos, Size, Thickness, 0, YINYANG)

    texture
    {
      pigment { colour rgb 1 }
      finish  {
        ambient 0.26
        diffuse 0.55
        //     reflection 0.1
        specular 0.8
        roughness 0.003
//        phong 1
//        phong_size 40
      }
    }
  }

#end