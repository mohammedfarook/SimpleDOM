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
 
class setAttributeTest extends \PHPUnit_Framework_TestCase
{
	public function test()
	{
		$node = new SimpleDOM('<node />');

		$node->setAttribute('a', 'aval');

		$this->assertXmlStringEqualsXmlString(
			'<node a="aval" />',
			$node->asXML()
		);
	}

	public function testIsChainable()
	{
		$node = new SimpleDOM('<node />');

		$return = $node->setAttribute('a', 'aval');

		$this->assertEquals($node, $return);
		$this->assertTrue(dom_import_simplexml($node)->isSameNode(dom_import_simplexml($return)));
	}
}