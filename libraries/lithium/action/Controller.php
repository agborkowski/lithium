<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\action;

use \Exception;
use \lithium\util\Inflector;

/**
 * The `Controller` class is the fundamental building block of your application's request/response
 * cycle. Controllers are organized around a single logical entity, usually one or more model
 * classes (i.e. `lithium\data\Model`) and are tasked with performing operations against that
 * entity.
 *
 * Each controller has a series of 'actions' which are defined as class methods of the `Controller`
 * classes. Each action has a specific responsibility, such as listing a set of objects, updating an
 * object, or deleting an object.
 *
 * A controller object is instanciated by the `Dispatcher` (`lithium\action\Dispatcher`), and is
 * given an instance of the `lithium\action\Request` class, which contains all necessary request
 * state, including routing information, `GET` & `POST` data, and server variables. The controller
 * is then invoked (using PHP's magic `__invoke()` syntax), and the proper action is called,
 * according to the routing information stored in the `Request` object.
 *
 * A controller then returns a response (i.e. using `redirect()` or `render()`) which includes HTTP
 * headers, and/or a serialized data response (JSON or XML, etc.) or HTML webpage.
 *
 * For more information on returning serialized data responses for web services, or manipulating
 * template rendering from within your controllers, see the settings in `$_render` and the
 * `lithium\net\http\Media` class.
 *
 * @see lithium\net\http\Media
 * @see lithium\action\Dispatcher
 * @see lithium\action\Controller::$_render
 */
class Controller extends \lithium\core\Object {

	/**
	 * Contains an instance of the `Request` object with all the details of the HTTP request that
	 * was dispatched to the controller object. Any parameters captured in routing, such as
	 * controller or action name are accessible as properties of this object, i.e.
	 * `$this->request->controller` or `$this->request->action`.
	 *
	 * @see lithium\action\Request
	 * @var object
	 */
	public $request = null;

	/**
	 * Contains an instance of the `Response` object which aggregates the headers and body content
	 * to be written back to the client (browser) when the result of the request is rendered.
	 *
	 * @see lithium\action\Response
	 * @var object
	 */
	public $response = null;

	/**
	 * Lists the rendering control options for responses generated by this controller.
	 *
	 * The `'type'` key is the content type that will be rendered by default, unless another is
	 * explicitly specified (defaults to `'html'`).
	 *
	 * The `'data'` key contains an associative array of variables to be sent to the view, including
	 * any variables created in `set()`, or if an action returns any variables (as an associative
	 * array).
	 *
	 * When an action is invoked, it will by default attempt to render a response, set the `'auto'`
	 * key to `false` to prevent this behavior.
	 *
	 * If you manually call `render()` within an action, the `'hasRendered'` key stores this state,
	 * so that responses are not rendered multiple times, either manually or automatically.
	 *
	 * The `'layout'` key specifies the name of the layout to be used (defaults to `'default'`).
	 * Typically, layout files are looked up as `<app-path>/views/layouts/<layout-name>.<type>.php`.
	 * Based on the default settings, the actual path would be `app/views/layouts/default.html.php`.
	 *
	 * Though typically introspected from the action that is executed, the `'template'` key can be
	 * manually specified. This sets the template to be rendered, and is looked up (by default) as
	 * `<app-path>/views/<controller>/<action>.<type>.php`, i.e.: `app/views/posts/index.html.php`.
	 *
	 * To change the inner-workings of these settings (template paths, default render settings for
	 * individual content types), see the `lithium\net\http\Media` class.
	 *
	 * @var array
	 * @see lithium\net\http\Media::type()
	 * @see lithium\net\http\Media::render()
	 */
	protected $_render = array(
		'type'        => 'html',
		'data'        => array(),
		'auto'        => true,
		'layout'      => 'default',
		'template'    => null,
		'hasRendered' => false
	);

	/**
	 * Lists `Controller`'s class dependencies. For details on extending or replacing a class,
	 * please refer to that class's API.
	 *
	 * @var array
	 */
	protected $_classes = array(
		'media' => '\lithium\net\http\Media',
		'router' => '\lithium\net\http\Router',
		'response' => '\lithium\action\Response'
	);

	protected $_autoConfig = array('render' => 'merge', 'classes' => 'merge');

	public function __construct(array $config = array()) {
		$defaults = array(
			'request' => null, 'response' => array(), 'render' => array(), 'classes' => array()
		);
		parent::__construct($config + $defaults);
	}

	protected function _init() {
		parent::_init();
		$this->request = $this->request ?: $this->_config['request'];

		if ($this->request) {
			$this->_render['type'] = $this->request->type();
		}

		$config = $this->_config['response'] + array('request' => $this->request);
		$this->response = new $this->_classes['response']($config);
	}

	/**
	 * Called by the Dispatcher class to invoke an action.
	 *
	 * @param object $request The request object with URL and HTTP info for dispatching this action.
	 * @param array $dispatchParams The array of parameters that will be passed to the action.
	 * @param array $options The dispatch options for this action.
	 * @return object Returns the response object associated with this controller.
	 * @todo Implement proper exception catching/throwing
	 * @filter This method can be filtered.
	 */
	public function __invoke($request, $dispatchParams, array $options = array()) {
		$render =& $this->_render;
		$params = compact('request', 'dispatchParams', 'options');

		return $this->_filter(__METHOD__, $params, function($self, $params) use (&$render) {
			$request = $params['request'];
			$dispatchParams = $params['dispatchParams'];
			$options = $params['options'];

			$action = $dispatchParams['action'];
			$args = isset($dispatchParams['args']) ? $dispatchParams['args'] : array();
			$result = null;

			if (substr($action, 0, 1) == '_' || method_exists(__CLASS__, $action)) {
				throw new Exception('Private method!');
			}
			$render['template'] = $render['template'] ?: $action;

			try {
				$result = $self->invokeMethod($action, $args);
			} catch (Exception $e) {
				throw $e;
			}

			if ($result) {
				if (is_string($result)) {
					$self->render(array('text' => $result));
				} elseif (is_array($result)) {
					$self->set($result);
				}
			}

			if (!$render['hasRendered'] && $render['auto']) {
				$self->render();
			}
			return $self->response;
		});
	}

	/**
	 * This method is used to pass along any data from the controller to the view and layout
	 *
	 * @param array $data sets of <variable name> => <variable value> to pass to view layer.
	 * @return void
	 */
	public function set($data = array()) {
		$this->_render['data'] += (array) $data;
	}

	/**
	 * Uses results (typically coming from a controller action) to generate content and headers for
	 * a Response object.
	 *
	 * @param string|array $options A string template name (see the 'template' option below), or an
	 *        array of options, as follows:
	 *        - `'data'`: An associative array of variables to be assigned to the template. These
	 *          are merged on top of any variables set in `Controller::set()`.
	 *        - `'head'`: If true, only renders the headers of the response, not the body. Defaults
	 *          to false.
	 *        - `'template'`: The name of a template, which usually matches the name of the action.
	 *          By default, this template is looked for in the views directory of the current
	 *          controller, i.e. given a `PostsController` object, if template is set to `'view'`,
	 *          the template path would be `views/posts/view.html.php`. Defaults to the name of the
	 *          action being rendered.
	 * @return void
	 */
	public function render($options = array()) {
		if (is_string($options)) {
			$options = array('template' => $options);
		}
		$class = get_class($this);
		$media = $this->_classes['media'];

		$defaults = array('status' => null, 'location' => false, 'data' => null, 'head' => false);
		$options += $defaults + array(
			'controller' => Inflector::underscore(
				preg_replace('/Controller$/', '', substr($class, strrpos($class, '\\') + 1))
			)
		);


		if (!empty($options['data'])) {
			$this->set($options['data']);
			unset($options['data']);
		}
		$options = $options + $this->_render;
		$type = key($options);
		$types = array_flip($media::types());

		if (isset($types[$type])) {
			$options['type'] = $type;
			$this->set(current($options));
			unset($options[$type]);
		}

		$this->_render['hasRendered'] = true;
		$this->response->type($options['type']);
		$this->response->status($options['status']);
		$this->response->headers('Location', $options['location']);

		if ($options['head']) {
			return;
		}
		$data = $this->_render['data'];
		$data = (isset($data[0]) && count($data) == 1) ? $data[0] : $data;
		$media::render($this->response, $data, $options + array('request' => $this->request));
	}

	/**
	 * Creates a redirect response.
	 *
	 * @param mixed $url
	 * @param array $options
	 * @return void
	 * @filter This method can be filtered.
	 */
	public function redirect($url, array $options = array()) {
		$router = $this->_classes['router'];
		$defaults = array('location' => null, 'status' => 302, 'head' => true, 'exit' => true);
		$options += $defaults;
		$options['location'] = $options['location'] ?: $router::match($url, $this->request);

		$this->_filter(__METHOD__, compact('options'), function($self, $params, $chain) {
			$self->render($params['options']);
		});

		if ($options['exit']) {
			$this->response->render();
			$this->_stop();
		}
		return $this->response;
	}
}

?>