<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/* Author: Jens-Uwe Gaspar */

// Call EntityTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
   define("PHPUnit_MAIN_METHOD", "EntityTest::main");
}

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once 'include/db_classes.php';


/**
 * Test class for Entity.
 * Generated by PHPUnit_Util_Skeleton on 2010-01-06 at 16:03:39.
 */
class EntityTest extends PHPUnit_Framework_TestCase {

   private $entity;

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once 'PHPUnit/TextUI/TestRunner.php';

      $suite  = new PHPUnit_Framework_TestSuite("EntityTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }

   /** Sets up the fixture. */
   protected function setUp() {
      $this->entity = new Entity( 'Table',
            FTYPE_PKEY, 'ID',
            FTYPE_AUTO, 'ID',
            FTYPE_INT,  'ID', 'i1', 'i2',
            FTYPE_FLOAT, 'f1',
            FTYPE_TEXT, 't1', 't2',
            FTYPE_DATE, 'd1', 'd2',
            FTYPE_ENUM, 'e1', 'e2'
         );
   }

   /** Test constructor. */
   public function test_Entity() {
      $this->assertNotNull( $this->entity );

      $this->assertEquals( 'Table', $this->entity->table );

      $this->assertEquals( 1, count($this->entity->pkeys) );
      $this->assertEquals( 1, $this->entity->pkeys['ID'] );

      $this->assertEquals( 'ID', $this->entity->field_autoinc );

      $this->assertEquals( 10, count($this->entity->fields) );
      $this->assertEquals( FTYPE_INT, $this->entity->fields['ID'] );
      $this->assertEquals( FTYPE_INT, $this->entity->fields['i1'] );
      $this->assertEquals( FTYPE_INT, $this->entity->fields['i2'] );
      $this->assertEquals( FTYPE_TEXT, $this->entity->fields['t1'] );
      $this->assertEquals( FTYPE_TEXT, $this->entity->fields['t2'] );
      $this->assertEquals( FTYPE_DATE, $this->entity->fields['d1'] );
      $this->assertEquals( FTYPE_DATE, $this->entity->fields['d2'] );
      $this->assertEquals( FTYPE_ENUM, $this->entity->fields['e1'] );
      $this->assertEquals( FTYPE_ENUM, $this->entity->fields['e2'] );
      $this->assertEquals( FTYPE_FLOAT, $this->entity->fields['f1'] );

      $this->assertEquals( 2, count($this->entity->date_fields) );
      $arr = array_merge( array(), $this->entity->date_fields );
      sort($arr);
      $this->assertEquals( 'd1,d2', implode(',', $arr) );
   }

   public function test_is_field() {
      $this->assertTrue( $this->entity->is_field('ID') );
      $this->assertTrue( $this->entity->is_field('t1') );
      $this->assertFalse( $this->entity->is_field('unknown') );
   }

   public function test_is_primary_key() {
      $this->assertTrue( $this->entity->is_primary_key('ID') );
      $this->assertFalse( $this->entity->is_primary_key('t1') );
      $this->assertFalse( $this->entity->is_primary_key('unknown') );
   }

   public function test_is_auto_increment() {
      $this->assertTrue( $this->entity->is_auto_increment('ID') );
      $this->assertFalse( $this->entity->is_auto_increment('t1') );
   }

   public function test_newQuerySQL() {
      $qsql = $this->entity->newQuerySQL('T');
      $this->assertTrue( ($qsql instanceof QuerySQL) );
      $this->assertEquals(
         "SELECT T.*, UNIX_TIMESTAMP(T.d1) AS X_d1, UNIX_TIMESTAMP(T.d2) AS X_d2 FROM Table AS T",
         $qsql->get_select(), $qsql->to_string() );
   }

   public function test_newEntityData() {
      $data = $this->entity->newEntityData();
      $this->assertNotNull( $data );
      $this->assertSame( $this->entity, $data->entity );
   }
}

// Call EntityTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "EntityTest::main") {
   EntityTest::main();
}
?>
