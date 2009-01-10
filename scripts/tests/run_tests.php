<?php

require_once 'PHPUnit/Framework.php';

require_once 'AllTests.php';

// Create a test suite that contains all the DGS-tests
$suite = new PHPUnit_Framework_TestSuite('AllTests');

// Run the tests.
$suite->run();

?>
