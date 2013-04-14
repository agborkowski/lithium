<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2013, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\fixture\model\gallery;

class TagsFixture extends \li3_fixtures\test\Fixture {

	protected $_model = 'lithium\tests\fixture\model\gallery\Tags';

	protected $_fields = array(
		'id' => array('type' => 'id'),
		'name' => array('type' => 'string', 'length' => 50)
	);

	protected $_records = array(
		array('id' => 1, 'name' => 'High Tech'),
		array('id' => 2, 'name' => 'Sport'),
		array('id' => 3, 'name' => 'Computer'),
		array('id' => 4, 'name' => 'Art'),
		array('id' => 5, 'name' => 'Science'),
		array('id' => 6, 'name' => 'City')
	);
}

?>