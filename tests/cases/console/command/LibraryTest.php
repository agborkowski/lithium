<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2013, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\cases\console\command;

use Phar;
use lithium\console\command\Library;
use lithium\core\Libraries;
use lithium\console\Request;
use lithium\test\Mocker;

class LibraryTest extends \lithium\test\Unit {

	public $request;

	protected $_backup = array();

	protected $_testPath = null;

	public function skip() {
		$this->_testPath = Libraries::get(true, 'resources') . '/tmp/tests';
		$this->skipIf(!is_writable($this->_testPath), "Path `{$this->_testPath}` is not writable.");
	}

	public function setUp() {
		Mocker::register();
		$this->_backup['cwd'] = getcwd();
		$this->_backup['_SERVER'] = $_SERVER;
		$_SERVER['argv'] = array();

		chdir($this->_testPath);

		Libraries::add('library_test', array(
			'path' => $this->_testPath . '/library_test', 'bootstrap' => false
		));

		Libraries::add('library_test_plugin', array(
			'path' => $this->_testPath . '/library_test_plugin'
		));

		$this->classes = array(
			'service' => 'lithium\tests\mocks\console\command\MockLibraryService',
			'response' => 'lithium\tests\mocks\console\MockResponse'
		);
		$this->request = new Request(array('input' => fopen('php://temp', 'w+')));
		$this->library = new Library(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$this->testConf = $this->library->conf = $this->_testPath . '/library.json';
	}

	public function tearDown() {
		Mocker::overwriteFunction(false);
		$_SERVER = $this->_backup['_SERVER'];
		chdir($this->_backup['cwd']);
		Libraries::remove('library_test');
		unset($this->library, $this->request);
	}

	public function testConfigServer() {
		$result = $this->library->config('server', 'lab.li3.me');
		$this->assertNotEmpty($result);

		$expected = array('servers' => array('lab.li3.me' => true));
		$result = json_decode(file_get_contents($this->testConf), true);
		$this->assertEqual($expected, $result);

		//create a new object to test initialiaztion
		$this->request->params += array('conf' => $this->testConf);
		$library = new Library(array(
			'request' => $this->request, 'classes' => $this->classes
		));

		$expected = array('servers' => array(
			'lab.li3.me' => true
		));
		$result = $this->library->config();
		$this->assertEqual($expected, $result);
	}

	public function testExtract() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->library->library = 'library_test';

		$result = $this->library->extract($this->_testPath . '/library_test');
		$this->assertTrue($result);

		$lithium = Libraries::get('lithium', 'path');
		$path = '/console/command/create/template/app.phar.gz';
		$expected = "library_test created in {$this->_testPath} from ";
		$expected .= realpath($lithium . $path) . "\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);
	}

	/**
	 * Tests the app extraction and replace functionality to ensure that all paths
	 * are set correctly after the extraction. It re-uses the test extraction
	 * generated in the last test (`LibraryTest::testExtract()`).
	 *
	 * Note that we can't properly test the case where the `LITHIUM_LIBRARY_PATH`
	 * is in a different location because it's a constant and can't
	 * be altered for testing.
	 */
	public function testExtractAndReplace() {
		$filepath = $this->_testPath . '/library_test/config/bootstrap/libraries.php';
		if (dirname(LITHIUM_APP_PATH) . '/libraries' !== LITHIUM_LIBRARY_PATH) {
			$expected = 'define(\'LITHIUM_LIBRARY_PATH\', \'';
			$expected .= realpath(LITHIUM_LIBRARY_PATH) . '\')';
		} else {
			$expected = 'define(\'LITHIUM_LIBRARY_PATH\', ';
			$expected .= 'dirname(LITHIUM_APP_PATH) . \'/libraries\')';
		}
		$this->_assertFileContents(realpath($filepath), $expected);

		$filepath = $this->_testPath . '/library_test/controllers/PagesController.php';
		$expected = "namespace library_test\\";
		$this->_assertFileContents($filepath, $expected);

		$this->library->library = 'replace_test';
		$this->library->lithiumLibraryPath = 'dirname(LITHIUM_APP_PATH)';
		$result = $this->library->extract(
			'test-app-replacements',
			$this->_testPath . '/replace_test'
		);
		$this->assertTrue($result);

		$lithium = Libraries::get('lithium', 'path');
		$path = '/console/command/create/template/test-app-replacements.phar.gz';
		$expected = "replace_test created in {$this->_testPath} from ";
		$expected .= realpath($lithium . $path) . "\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		$filepath = $this->_testPath . '/replace_test/config/bootstrap/libraries.php';
		$expected = 'define(\'LITHIUM_LIBRARY_PATH\', dirname(LITHIUM_APP_PATH))';
		$this->_assertFileContents($filepath, $expected);

		$filepath = $this->_testPath . '/replace_test/controllers/PagesController.php';
		$expected = "namespace replace_test\\";
		$this->_assertFileContents($filepath, $expected);

		$filepath = $this->_testPath . '/replace_test/.htaccess';
		$expected = "just a test";
		$this->_assertFileContents($filepath, $expected);

		$filepath = $this->_testPath . '/replace_test/webroot/css/lithium.css';
		$expected = "Carbon:";
		$this->_assertFileContents($filepath, $expected);
	}

	protected function _assertFileContents($filepath, $expected) {
		$content = file_get_contents($filepath);
		$this->assertContains($expected, $content);
	}

	public function testArchive() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->skipIf(ini_get('phar.readonly') === '1', 'INI setting phar.readonly = On');

		$this->library->library = 'library_test';

		$testPath = "{$this->_testPath}/library_test";
		$result = $this->library->archive($testPath, $testPath);
		$this->assertTrue($result);

		$expected = "library_test.phar.gz created in {$this->_testPath} from ";
		$expected .= "{$this->_testPath}/library_test\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		Phar::unlinkArchive("{$this->_testPath}/library_test.phar");
	}

	public function testExtractWithFullPaths() {
		$fileExists = file_exists("{$this->_testPath}/library_test.phar.gz");
		$this->skipIf(!$fileExists, 'Depends on ' . __CLASS__ . '::testArchive()');
		$this->library->library = 'library_test';

		$result = $this->library->extract(
			$this->_testPath . '/library_test.phar.gz', $this->_testPath . '/new'
		);
		$this->assertTrue($result);

		$this->assertFileExists($this->_testPath . '/new');

		$expected = "new created in {$this->_testPath} from ";
		$expected .= "{$this->_testPath}/library_test.phar.gz\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		$this->assertFileExists($this->_testPath . '/new/.htaccess');

		$this->assertFileNotExists($this->_testPath . '/new/.DS_Store');

		Phar::unlinkArchive($this->_testPath . '/library_test.phar.gz');
	}

	public function testArchiveNoLibrary() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->skipIf(
			ini_get('phar.readonly') === '1',
			'INI setting phar.readonly = On'
		);

		chdir('new');
		$app = new Library(array('request' => new Request(), 'classes' => $this->classes));
		$app->library = 'does_not_exist';

		$result = $app->archive();
		$this->assertTrue($result);

		$path = realpath($this->_testPath);
		$expected = "new.phar.gz created in {$path} from {$path}" . DIRECTORY_SEPARATOR . "new\n";
		$result = $app->response->output;
		$this->assertEqual($expected, $result);

		Phar::unlinkArchive($this->_testPath . '/new.phar');
		Phar::unlinkArchive($this->_testPath . '/new.phar.gz');

		$this->_cleanUp('tests/new');
	}

	public function testExtractWhenLibraryDoesNotExist() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		chdir($this->_testPath);
		$app = new Library(array('request' => new Request(), 'classes' => $this->classes));
		$app->library = 'does_not_exist';

		$result = $app->extract();
		$this->assertTrue($result);

		$this->assertFileExists($this->_testPath . '/new');

		$path = realpath($this->_testPath);
		$lithium = Libraries::get('lithium', 'path');
		$tplPath = realpath($lithium . '/console/command/create/template');
		$filePath = $tplPath . DIRECTORY_SEPARATOR . "app.phar.gz";
		$expected = "new created in {$path} from {$filePath}\n";
		$result = $app->response->output;
		$this->assertEqual($expected, $result);

		$this->_cleanUp();
	}

	public function testExtractPlugin() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->library->library = 'library_plugin_test';
		$path = $this->_testPath;

		$result = $this->library->extract('plugin', "{$path}/library_test_plugin");
		$this->assertTrue($result);

		$expected = "library_test_plugin created in {$path} from ";
		$lithium = Libraries::get('lithium', 'path');
		$target = '/console/command/create/template/plugin.phar.gz';
		$expected .= realpath($lithium . $target) . "\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		$this->_cleanup();
	}

	public function testFormulate() {
		$this->library->formulate();
		$expected = '/please supply a name/';
		$result = $this->library->response->output;
		$this->assertPattern($expected, $result);

		$path = $this->_testPath . '/library_test_plugin';
		mkdir($path);
		$result = $this->library->formulate($path);
		$this->assertTrue($result);

		$this->assertFileExists($path . '/config/library_test_plugin.json');

		$this->_cleanUp();
	}

	public function testFormulateWithFormula() {
		$path = $this->_testPath . '/library_test_plugin';
		mkdir($path);
		mkdir($path . '/config');
		file_put_contents(
			$path . '/config/library_test_plugin.json',
			json_encode(array(
				'name' => 'library_test_plugin',
				'version' => '1.0',
				'summary' => 'something',
				'sources' => array(
					'phar' => 'http://somewhere.com/download/library_test_plugin.phar.gz'
				)
			))
		);

		$result = $this->library->formulate($path);
		$this->assertTrue($result);

		$this->assertFileExists($path . '/config/library_test_plugin.json');
	}

	public function testNoFormulate() {
		$path = $this->_testPath . '/library_test_no_plugin';
		$result = $this->library->formulate($path);
		$this->assertFalse($result);

		$this->assertFileNotExists($path . '/config/library_test_no_plugin.json');

		$expected = '/Formula for library_test_no_plugin not created/';
		$result = $this->library->response->error;
		$this->assertPattern($expected, $result);
	}

	public function testFormulateNoPath() {
		$isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
		$this->skipIf($isWin, 'Permissions cannot be modified on Windows.');

		$path = $this->_testPath . '/library_test_no_plugin';
		umask(0);
		mkdir($path, 655);
		umask(100);
		$this->expectException('/Permission denied/');

		$result = $this->library->formulate($path);
		$this->assertFalse($result);

		$this->assertFileNotExists($path . '/config/library_test_plugin.json');

		$expected = '/Formula for library_test_no_plugin not created/';
		$result = $this->library->response->error;
		$this->assertPattern($expected, $result);

		umask(0);
		rmdir($path);
	}

	public function testPushNoName() {
		$this->library->push();
		$expected = 'please supply a name';
		$result = $this->library->response->output;
		$this->assertNotEmpty($result);
	}

	public function testPush() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->skipIf(
			ini_get('phar.readonly') === '1',
			'INI setting phar.readonly = On'
		);

		$result = file_put_contents(
			$this->_testPath . '/library_test_plugin/config/library_test_plugin.json',
			json_encode(array(
				'name' => 'library_test_plugin',
				'version' => '1.0',
				'summary' => 'something',
				'sources' => array(
					'phar' => 'http://somewhere.com/download/library_test_plugin.phar.gz'
				)
			))
		);
		$this->assertNotEmpty($result);

		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertTrue($result);

		$expected = "library_test_plugin.phar.gz created in {$this->_testPath} from ";
		$expected .= "{$this->_testPath}/library_test_plugin\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		$this->assertFileExists($this->_testPath . '/library_test_plugin.phar.gz');
		$this->library->response->output = null;

		$result = $this->library->push('library_test_plugin');
		$this->assertNotEmpty($result);

		$expected = "library_test_plugin added to {$this->library->server}.\n";
		$expected .= "See http://{$this->library->server}/lab/plugins/view/{$result->id}\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		$this->assertFileExists($this->_testPath . '/library_test_plugin');

		$this->_cleanUp('tests/library_test_plugin');
		rmdir($this->_testPath . '/library_test_plugin');
	}

	public function testInstall() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->skipIf(
			ini_get('phar.readonly') === '1',
			'Relies on ' . __CLASS__ . '::testPush()'
		);
		$this->library->path = $this->_testPath;
		$result = $this->library->install('library_test_plugin');
		$this->assertTrue($result);

		$this->assertFileExists($this->_testPath . '/library_test_plugin.phar.gz');

		$this->assertFileExists($this->_testPath . '/library_test_plugin');

		Phar::unlinkArchive($this->_testPath . '/library_test_plugin.phar');
		Phar::unlinkArchive($this->_testPath . '/library_test_plugin.phar.gz');
		$this->_cleanUp();
	}

	public function testNoInstall() {
		$result = $this->library->install('library_test_plugin');
		$expected = "library_test_plugin not installed.\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);
		$this->library->response->output = null;

		$this->request->params += array('server' => null);
		$library = new Library(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$library->conf = $this->testConf;
		$library->config('server', 'localhost');
		$result = $this->library->install('library_not_a_plugin');
		$expected = "library_not_a_plugin not found.\n";
		$result = $this->library->response->error;
		$this->assertEqual($expected, $result);
	}

	public function testNoInstalLab() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->skipIf(ini_get('phar.readonly') === '1', 'Relies on ' . __CLASS__ . '::testPush()');
		$this->library->path = $this->_testPath;
		$result = $this->library->install('li3_lab');

		$expected = "li3_lab not installed.\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		$this->assertFileNotExists($this->_testPath . '/li3_lab');
		$this->_cleanUp();
	}

	public function testInstallDocsWithGit() {
		$base = 'lithium\console\command\\';
		Mocker::overwriteFunction("{$base}shell_exec", function($cmd) {
			if ($cmd === 'git --version') {
				return 'git version 1.7.9.5';
			}
			return true;
		});
		Mocker::overwriteFunction("{$base}is_dir", function($dir) {
			return true;
		});

		$this->library->path = $this->_testPath;
		$result = $this->library->install('li3_docs');
		$this->assertTrue($result);

		$this->_cleanUp();
	}

	public function testFind() {
		$this->library->find();

		$expected = <<<EOD
--------------------------------------------------------------------------------
lab.li3.me > li3_lab
--------------------------------------------------------------------------------
the li3 plugin client/server
Version: 1.0
Created: 2009-11-30
--------------------------------------------------------------------------------
lab.li3.me > library_test_plugin
--------------------------------------------------------------------------------
an li3 plugin example
Version: 1.0
Created: 2009-11-30

EOD;
	}

	public function testFindNotFound() {
		$this->request->params += array('server' => null);
		$library = new Library(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$library->conf = $this->testConf;
		$library->config('server', 'localhost');
		$library->find();
		$expected = "No plugins at localhost\n";
		$result = $library->response->output;
		$this->assertEqual($expected, $result);
	}

	public function testForceArchive() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->skipIf(
			ini_get('phar.readonly') === '1',
			'INI setting phar.readonly = On'
		);
		$result = $this->library->extract('plugin', $this->_testPath . '/library_test_plugin');
		$this->assertTrue($result);

		$this->library->response->output = null;
		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertTrue($result);

		$expected = "library_test_plugin.phar.gz created in {$this->_testPath} from ";
		$expected .= "{$this->_testPath}/library_test_plugin\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		$this->library->response->output = null;
		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertFalse($result);

		$expected = "library_test_plugin.phar already exists in {$this->_testPath}\n";
		$result = $this->library->response->error;
		$this->assertEqual($expected, $result);

		$this->library->force = true;
		$this->library->response->output = null;
		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertTrue($result);

		$expected = "library_test_plugin.phar.gz created in {$this->_testPath} from ";
		$expected .= "{$this->_testPath}/library_test_plugin\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		unlink($this->_testPath . '/library_test_plugin.phar');

		$this->library->force = false;
		$this->library->response->output = null;
		$this->library->response->error = null;
		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertFalse($result);

		$expected = "library_test_plugin.phar.gz already exists in {$this->_testPath}\n";
		$result = $this->library->response->error;
		$this->assertEqual($expected, $result);

		$this->library->force = true;
		$this->library->response->output = null;
		$this->library->response->error = null;
		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertTrue($result);

		$expected = "library_test_plugin.phar.gz created in {$this->_testPath} from ";
		$expected .= "{$this->_testPath}/library_test_plugin\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		Phar::unlinkArchive($this->_testPath . '/library_test_plugin.phar');
		Phar::unlinkArchive($this->_testPath . '/library_test_plugin.phar.gz');
		$this->_cleanUp();
	}

	public function testPushWithAuth() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->skipIf(
			ini_get('phar.readonly') === '1',
			'INI setting phar.readonly = On'
		);
		$result = $this->library->extract('plugin', $this->_testPath . '/library_test_plugin');
		$this->assertTrue($result);

		$result = file_put_contents(
			$this->_testPath . '/library_test_plugin/config/library_test_plugin.json',
			json_encode(array(
				'name' => 'library_test_plugin',
				'version' => '1.0',
				'summary' => 'something',
				'sources' => array(
					'phar' => 'http://somewhere.com/download/library_test_plugin.phar.gz'
				)
			))
		);
		$this->assertNotEmpty($result);

		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertTrue($result);

		$this->assertFileExists($this->_testPath . '/library_test_plugin.phar.gz');

		$this->library->response->output = null;
		$this->library->username = 'gwoo';
		$this->library->password = 'password';
		$result = $this->library->push('library_test_plugin');
		$this->assertNotEmpty($result);

		$expected = "library_test_plugin added to {$this->library->server}.\n";
		$expected .= "See http://{$this->library->server}/lab/plugins/view/{$result->id}\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		$this->assertFileExists($this->_testPath . '/library_test_plugin');

		$this->library->response->error = null;
		$this->library->response->output = null;
		$this->library->username = 'bob';
		$this->library->password = 'password';
		$result = $this->library->push('library_test_plugin');
		$this->assertFalse($result);

		$expected = "Invalid username/password.\n";
		$result = $this->library->response->error;
		$this->assertEqual($expected, $result);

		$this->assertFileExists($this->_testPath . '/library_test_plugin');

		Phar::unlinkArchive($this->_testPath . '/library_test_plugin.phar');
		Phar::unlinkArchive($this->_testPath . '/library_test_plugin.phar.gz');
		$this->_cleanUp();
	}

	public function testPushNotValid() {
		$this->skipIf(!extension_loaded('zlib'), 'The zlib extension is not loaded.');
		$this->skipIf(
			ini_get('phar.readonly') === '1',
			'INI setting phar.readonly = On'
		);
		$this->library->library = 'library_plugin_test';
		$path = $this->_testPath;

		$result = $this->library->extract('plugin', "{$path}/library_test_plugin");
		$this->assertTrue($result);
		$this->library->response->output = null;

		$file = $this->_testPath . '/library_test_plugin/config/library_test_plugin.json';
		$result = file_put_contents(
			$file,
			json_encode(array(
				'name' => 'library_test_plugin',
				'version' => '1.0',
				'summary' => 'something'
			))
		);
		$this->assertNotEmpty($result);

		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertTrue($result);

		$expected = "library_test_plugin.phar.gz created in {$this->_testPath} from ";
		$expected .= "{$this->_testPath}/library_test_plugin\n";
		$result = $this->library->response->output;
		$this->assertEqual($expected, $result);

		$this->assertFileExists($this->_testPath . '/library_test_plugin.phar.gz');
		$this->library->response->output = null;

		$result = $this->library->push('library_test_plugin');
		$this->assertFalse($result);

		$expected = "/The formula for library_test_plugin is not valid./";
		$result = $this->library->response->error;
		$this->assertPattern($expected, $result);

		$this->assertFileExists($this->_testPath . '/library_test_plugin');

		Phar::unlinkArchive($this->_testPath . '/library_test_plugin.phar');
		Phar::unlinkArchive($this->_testPath . '/library_test_plugin.phar.gz');
		$this->_cleanUp();
	}

	public function testNoArchive() {
		$this->skipIf(
			ini_get('phar.readonly') === '1',
			'INI setting phar.readonly = On'
		);
		$result = $this->library->archive(
			$this->_testPath . '/library_test_plugin',
			$this->_testPath . '/library_test_plugin'
		);
		$this->assertFalse($result);

		$expected = "/Could not create archive from/";
		$result = $this->library->response->error;
		$this->assertPattern($expected, $result);
	}
}

?>