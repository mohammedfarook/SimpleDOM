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
 
class nodeValueTest extends \PHPUnit_Framework_TestCase
{
	public function testGet()
	{
		$node = new SimpleDOM('<node>value</node>');

		$this->assertSame('value', $node->nodeValue());
	}

	public function testSet()
	{
		$node = new SimpleDOM('<node>value</node>');

		$this->assertSame('new', $node->nodeValue('new'));
		$this->assertXmlStringEqualsXmlString('<node>new</node>', $node->asXML());
	}
}