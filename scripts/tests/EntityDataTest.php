<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

// Call EntityDataTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
   define("PHPUnit_MAIN_METHOD", "EntityDataTest::main");
}

require_once "PHPUnit/Framework/TestCase.php";
require_once "PHPUnit/Framework/TestSuite.php";

require_once 'scripts/tests/UnitTestHelper.php';
require_once 'include/db_classes.php';


/**
 * Test class for EntityData.
 * Generated by PHPUnit_Util_Skeleton on 2010-01-06 at 16:03:45.
 */
class EntityDataTest extends PHPUnit_Framework_TestCase {

   private $entity;
   private $data;

   /**
    * Runs the test methods of this class.
    *
    * @access public
    * @static
    */
   public static function main() {
      require_once "PHPUnit/TextUI/TestRunner.php";

      $suite  = new PHPUnit_Framework_TestSuite("EntityDataTest");
      $result = PHPUnit_TextUI_TestRunner::run($suite);
   }

   /** Sets up the fixture. */
   protected function setUp() {
      UnitTestHelper::clearErrors(ERROR_MODE_PRINT);

      $this->entity = new Entity( 'Table',
            FTYPE_PKEY, 'ID',
            FTYPE_AUTO, 'ID',
            FTYPE_INT,  'ID', 'i1', 'i2',
            FTYPE_TEXT, 't1', 't2',
            FTYPE_DATE, 'd1', 'd2',
            FTYPE_ENUM, 'e1', 'e2'
         );

      $this->data = $this->entity->newEntityData();
   }

   public function test_set_value() {
      $this->data->set_value('ID', 4711);
      $this->assertEquals( 4711, $this->data->values['ID'] );
      $this->assertEquals( "ID[4711]", $this->data->get_pkey_string() );

      UnitTestHelper::clearErrors(ERROR_MODE_TEST);
      $this->data->set_value('unknown', 7);
      $this->assertEquals( 1, UnitTestHelper::countErrors() );
   }

   public function test_get_value() {
      $this->data->set_value('ID', 4711);
      $this->data->set_value('t2', 15);
      $this->assertEquals( 4711, $this->data->get_value('ID') );
      $this->assertNull( $this->data->get_value('t1') );
      $this->assertEquals( 'abc', $this->data->get_value('t1', 'abc') );
      $this->assertEquals( 15, $this->data->get_value('t2', 'abc') );
      $this->assertNull( $this->data->get_value('unknown') );
   }

   public function test_remove_value() {
      $this->data->set_value('ID', 4711);
      $this->assertEquals( 4711, $this->data->get_value('ID') );
      $this->data->remove_value('ID');
      $this->assertNull( $this->data->get_value('ID') );
   }

   public function test_get_sql_value() {
      $this->data->set_value('ID', 4711);
      $this->assertEquals( "4711", $this->data->get_sql_value('ID') );
      $this->data->set_value('i1', 0);
      $this->assertEquals( "0", $this->data->get_sql_value('i1') );
      $this->data->set_value('i2', '');
      $this->assertEquals( "0", $this->data->get_sql_value('i2') );
      $this->data->set_value('t1', 12);
      $this->assertEquals( "'12'", $this->data->get_sql_value('t1') );
      $this->data->set_value('e2', 'e-val');
      $this->assertEquals( "'e-val'", $this->data->get_sql_value('e2') );
      $this->data->set_value('d1', 12345678);
      $this->assertEquals( "FROM_UNIXTIME(12345678)", $this->data->get_sql_value('d1') );
   }

   public function test_build_sql_insert() {
      $this->data->set_value('ID', 4711);
      $this->data->set_value('i1', 0);
      $this->data->set_value('i2', '');
      $this->data->set_value('t1', 12);
      $this->data->set_value('e2', 'e-val');
      $this->data->set_value('d1', 12345678);
      $this->assertEquals(
         "INSERT INTO Table SET i1=0, i2=0, t1='12', e2='e-val', d1=FROM_UNIXTIME(12345678)",
         $this->data->build_sql_insert() );
   }

   public function test_build_sql_insert_NoAutoKey() {
      $e = new Entity( 'NTable',
            FTYPE_PKEY, 'id',
            FTYPE_INT,  'id', 'f1'
         );
      $d = $e->newEntityData();
      $d->set_value('id', 1);
      $d->set_value('f1', 2);
      $this->assertEquals( "INSERT INTO NTable SET id=1, f1=2", $d->build_sql_insert() );
   }

   public function test_build_sql_insert_values() {
      $this->data->set_value('ID', 4711);
      $this->data->set_value('d1', 12345678);
      $this->data->set_value('e2', 'e-val');
      $this->data->set_value('i1', 0);
      $this->data->set_value('i2', '');
      $this->data->set_value('t1', 12);
      $this->assertEquals(
         "INSERT INTO Table (i1,i2,t1,t2,d1,d2,e1,e2) VALUES ",
         $this->data->build_sql_insert_values(true) );
      $this->assertEquals(
         "(0,0,'12',DEFAULT(t2),FROM_UNIXTIME(12345678),DEFAULT(d2),DEFAULT(e1),'e-val')",
         $this->data->build_sql_insert_values() );
   }

   public function test_build_sql_update() {
      $this->data->set_value('ID', 4711);
      $this->data->set_value('i1', 0);
      $this->data->set_value('i2', '');
      $this->data->set_value('t1', 12);
      $this->data->set_value('e2', 'e-val');
      $this->data->set_value('d1', 12345678);
      $this->assertEquals(
         "UPDATE Table SET i1=0, i2=0, t1='12', e2='e-val', d1=FROM_UNIXTIME(12345678) WHERE ID=4711",
         $this->data->build_sql_update(0) );
      $this->assertEquals(
         "UPDATE Table SET i1=0, i2=0, t1='12', e2='e-val', d1=FROM_UNIXTIME(12345678) WHERE ID=4711 LIMIT 1",
         $this->data->build_sql_update() );
   }

   public function test_build_sql_delete() {
      $this->data->set_value('ID', 4711);
      $this->data->set_value('i1', 0);
      $this->data->set_value('t1', 12);
      $this->assertEquals( "DELETE FROM Table WHERE ID=4711", $this->data->build_sql_delete(0) );
      $this->assertEquals( "DELETE FROM Table WHERE ID=4711 LIMIT 1", $this->data->build_sql_delete() );
      $this->assertEquals( "DELETE FROM Table WHERE ID=4711 LIMIT 2", $this->data->build_sql_delete(2) );
   }

   public function test_multi_field_pkey() {
      $e = new Entity( 'MTable',
            FTYPE_PKEY, 'id1', 'id2',
            FTYPE_INT,  'id1', 'id2', 'f1'
         );
      $d = $e->newEntityData();
      $d->set_value('id1', 1);
      $d->set_value('id2', 2);
      $d->set_value('f1', 3);

      $this->assertEquals( "id1[1],id2[2]", $d->get_pkey_string() );
      $this->assertEquals( "INSERT INTO MTable SET id1=1, id2=2, f1=3", $d->build_sql_insert() );
      $this->assertEquals( "INSERT INTO MTable (id1,id2,f1) VALUES ", $d->build_sql_insert_values(true) );
      $this->assertEquals( "(1,2,3)", $d->build_sql_insert_values() );
      $this->assertEquals( "UPDATE MTable SET f1=3 WHERE id1=1 AND id2=2", $d->build_sql_update(0) );
      $this->assertEquals( "DELETE FROM MTable WHERE id1=1 AND id2=2", $d->build_sql_delete(0) );
   }

}

// Call EntityDataTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "EntityDataTest::main") {
   EntityDataTest::main();
}
?>
