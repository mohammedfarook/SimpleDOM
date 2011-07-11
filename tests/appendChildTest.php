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
 
class appendChildTest extends \PHPUnit_Framework_TestCase
{
	public function testChild()
	{
		$root = new SimpleDOM('<root><child /></root>');
		$new = new SimpleDOM('<new />');

		$root->appendChild($new);

		$this->assertXmlStringEqualsXmlString($root->asXML(), '<root><child /><new /></root>');
	}

	public function testGrandchild()
	{
		$root = new SimpleDOM('<root><child /></root>');
		$new = new SimpleDOM('<new />');

		$root->child->appendChild($new);

		$this->assertXmlStringEqualsXmlString($root->asXML(), '<root><child><new /></child></root>');
	}

	public function testReturn()
	{
		$root = new SimpleDOM('<root><child /></root>');
		$new = new SimpleDOM('<new />');

		$return = $root->child->appendChild($new);

		$this->assertEquals($new, $return);
		$this->assertSame(
			dom_import_simplexml($root->child->new),
			dom_import_simplexml($return)
		);
	}
}