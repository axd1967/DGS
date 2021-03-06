// Dragon Go Server
// Copyright (C) 2001-  Erik Ouchterlony

// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as
// published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.

// You should have received a copy of the GNU Affero General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

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
        ambient 1
//        ambient 1.3
        phong 0.25
        phong_size 5
//        phong_size 6
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

      finish
      {
        ambient 0.25
        diffuse 0.55
        //     reflection 0.1
        specular 0.8
        roughness 0.003

//         ambient 0.42
//         diffuse 0.45
//         reflection { 0.1, 1.0 fresnel on }
//         specular 0.2
//         roughness 0.0015
//         brilliance 1.2
      }
//     interior { ior 1.5 }
    }
  }

#end
