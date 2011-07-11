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
 
class isSameNodeTest extends \PHPUnit_Framework_TestCase
{
	public function testSameNode()
	{
		$root = new SimpleDOM('<root><child1 /><child2 /><child3 /></root>');
		$this->assertTrue($root->child1->isSameNode($root->child1));
	}

	public function testDifferentNodes()
	{
		$root = new SimpleDOM('<root><child1 /><child2 /><child3 /></root>');
		$this->assertFalse($root->child1->isSameNode($root->child2));
	}

	public function testClonedNodesAreNotTheSame()
	{
		$root = new SimpleDOM('<root><child1 /><child2 /><child3 /></root>');
		$this->assertFalse($root->child1->isSameNode($root->child1->cloneNode()));
	}
}