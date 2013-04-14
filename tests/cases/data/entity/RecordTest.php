<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2013, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\cases\data\entity;

use lithium\data\Connections;
use lithium\data\entity\Record;
use lithium\data\Schema;
use lithium\tests\mocks\data\model\MockDatabase;
use lithium\tests\mocks\data\MockPost;
use lithium\tests\mocks\data\MockComment;
use lithium\tests\mocks\data\MockSource;

class RecordTest extends \lithium\test\Unit {

	protected $_record = null;
	protected $_schema = null;

	public function setUp() {
		Connections::add('mockconn', array('object' => new MockDatabase()));

		$this->_schema = new Schema(array(
			'fields' => array(
				'id' => 'int', 'title' => 'string', 'body' => 'text'
			)
		));
		MockPost::config(array(
			'meta' => array('connection' => 'mockconn', 'key' => 'id', 'locked' => true),
			'schema' => $this->_schema
		));
		$this->_record = new Record(array('model' => 'lithium\tests\mocks\data\MockPost'));
	}

	public function tearDown() {
		Connections::remove('mockconn');
		MockPost::reset();
		MockComment::reset();
	}

	/**
	 * Tests that a record's fields are accessible as object properties.
	 *
	 * @return void
	 */
	public function testDataPropertyAccess() {
		$data = array('title' => 'Test record', 'body' => 'Some test record data');
		$this->_record = new Record(compact('data'));

		$this->assertEqual('Test record', $this->_record->title);
		$this->assertTrue(isset($this->_record->title));

		$this->assertEqual('Some test record data', $this->_record->body);
		$this->assertTrue(isset($this->_record->body));

		$this->assertNull($this->_record->foo);
		$this->assertFalse(isset($this->_record->foo));
	}

	/**
	 * Tests that a record can be exported to a given series of formats.
	 *
	 * @return void
	 */
	public function testRecordFormatExport() {
		$data = array('foo' => 'bar');
		$this->_record = new Record(compact('data'));

		$this->assertEqual($data, $this->_record->to('array'));
		$this->assertEqual($this->_record, $this->_record->to('foo'));
	}

	public function testErrorsPropertyAccess() {
		$errors = array(
			'title' => 'please enter a title',
			'email' => array('email is empty', 'email is not valid')
		);

		$record = new Record();
		$result = $record->errors($errors);
		$this->assertEqual($errors, $result);

		$result = $record->errors();
		$this->assertEqual($errors, $result);

		$expected = 'please enter a title';
		$result = $record->errors('title');
		$this->assertEqual($expected, $result);

		$expected = array('email is empty', 'email is not valid');
		$result = $record->errors('email');
		$this->assertEqual($expected, $result);

		$result = $record->errors('not_a_field');
		$this->assertNull($result);

		$result = $record->errors('not_a_field', 'badness');
		$this->assertEqual('badness', $result);
	}

	/**
	 * Test the ability to set multiple field's values, and that they can be read back.
	 */
	public function testSetData() {
		$this->assertEmpty($this->_record->data());
		$expected = array('id' => 1, 'name' => 'Joe Bloggs', 'address' => 'The Park');
		$this->_record->set($expected);
		$this->assertEqual($expected, $this->_record->data());
		$this->assertEqual($expected, $this->_record->to('array'));
		$this->assertEqual($expected['name'], $this->_record->data('name'));
	}

	public function testRecordExists() {
		$this->assertFalse($this->_record->exists());
		$this->_record->sync(313);
		$this->assertIdentical(313, $this->_record->id);
		$this->assertTrue($this->_record->exists());

		$this->_record = new Record(array('exists' => true));
		$this->assertTrue($this->_record->exists());
	}

	public function testMethodDispatch() {
		Connections::add('mocksource', array('object' => new MockSource()));
		MockPost::config(array(
			'meta' => array('connection' => 'mocksource', 'key' => 'id', 'locked' => true),
			'schema' => $this->_schema
		));
		$result = $this->_record->save(array('title' => 'foo'));
		$this->assertEqual('create', $result['query']->type());
		$this->assertEqual(array('title' => 'foo'), $result['query']->data());

		$this->expectException("Unhandled method call `invalid`.");
		$this->assertNull($this->_record->invalid());
		Connections::remove('mocksource');
	}

	public function testSetOnMany() {
		$this->_schema->append(array('published' => array('type' => 'string', 'default' => 'N')));
		MockComment::config(array(
			'meta' => array('connection' => 'mockconn', 'key' => 'id', 'locked' => true),
			'schema' => $this->_schema
		));
		$this->_record->mock_comments = array('5', '6', '7');

		$expected = array(array('id' => 5), array('id' => 6), array('id' => 7));
		$result = $this->_record->mock_comments->to('array', array('indexed' => false));
		$this->assertEqual($expected, $result);
	}

	public function testSetOnSingle() {
		MockComment::config(array(
			'meta' => array('connection' => 'mockconn', 'key' => 'id', 'locked' => true),
			'schema' => $this->_schema
		));
		$this->_schema->append(array('published' => array('type' => 'string', 'default' => 'N')));
		MockPost::config(array(
			'meta' => array('connection' => 'mockconn', 'key' => 'id', 'locked' => true),
			'schema' => $this->_schema
		));
		$this->_record = new Record(array('model' => 'lithium\tests\mocks\data\MockComment'));
		$this->_record->mock_post = 5;

		$expected = array('id' => 5);
		$result = $this->_record->mock_post->to('array', array('indexed' => false));
		$this->assertEqual($expected, $result);
	}
}

?>