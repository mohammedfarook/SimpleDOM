<?php

/**
* @package   s9e\SimpleDOM
* @copyright Copyright (c) 2009 The SimpleDOM authors
* @copyright Copyright (c) 2010-2011 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\SimpleDOM\Tests;
use s9e\SimpleDOM\SimpleDOM;

include_once __DIR__ . '/../src/SimpleDOM.php';
 
class firstOfTest extends \PHPUnit_Framework_TestCase
{
	public function testMatch()
	{
		$node     = new SimpleDOM('<node><ignore /><child /><child id="15" /></node>');
		$expected = new SimpleDOM('<child id="15" />');
		$actual   = $node->firstOf('//child[@id="15"]');

		$this->assertEquals($expected, $actual);
	}

	public function testNoMatch()
	{
		$node     = new SimpleDOM('<node><ignore /><child /><child id="15" /></node>');
		$actual   = $node->firstOf('//nomatch');

		$this->assertNull($actual);
	}
}