<?php
namespace Skel;

/** Coordinates the various components that comprise an application */

abstract class App implements Interfaces\AccessControlledApp {
  //Execution Profiles
  const PROFILE_PROD = 1;
  const PROFILE_BETA = 2;
  const PROFILE_TEST = 4;

  protected $localizer;
  protected $authorizer;
  protected $config;
  protected $router;
  protected $db;
  protected $request;
  protected $uiManager;
  protected $executionProfile;
  protected $_listeners = array();
  protected $_strings;

  public function abort(Interfaces\Response $r) {
    $r->prepareFromRequest($this->request);
    $r->send();
    die();
  }



  public function getConfig() { return $this->config; }

  public function __construct(Interfaces\Config $config=null) {
    $this->executionProfile = static::PROFILE_PROD;
    if ($config) $this->config = $config;
  }



  //TODO: Remove
  public function getCanonicalPath() {
    if (!$this->request) throw new \RuntimeException('err-request-missing');
    $uri = $this->request->getUri();

    if ($this->localizer) return $this->localizer->getCanonicalPath($this->db, $uri);
    else return $uri->getPath();
  }

  //TODO: Remove
  public function getContent($key, array $content=array()) {
    return implode("\n",$content);
  }

  public function getDb() { return $this->db; }

  public function getErrorResponse(int $code=404, $str=null) {
    $r = new Response('', $code);
    if (!$str) $str = $this->str('err-404');
    $str = array('content' => $str);

    if ($code == 404) $r->setContent($this->getContent('404', $str));
    else $r->setContent($this->getContent('generic-error', $str));
    return $r;
  }

  public function getExecutionProfile() { return $this->executionProfile; }

  public function getUiManager() { return $this->uiManager; }

  public function getRedirectToHomeResponse($msg=null) {
    //TODO: Do something with the message
    //if ($msg) 
    return new Response(null, 301, array('Location: '.$this->router->getPath('home')));
  }

  public function getRedirectToLoginResponse() {
    return new Response(null, 301, array('Location: '.$this->router->getPath('login')));
  }

  public function getResponse() {
    $response = null;
    try {
      $response = $this->router->routeRequest($this->request, $this);
      if (!$response) throw new Http404Exception();
      elseif (is_string($response)) $response = new Response($response);
    } catch (Http404Exception $e) {
      $this->notifyListeners('Http404Exception', array('app' => $this));
      $response = $this->getErrorResponse(404);
    } catch (UnauthenticatedUserException $e) {
      // User isn't yet authenticated. Redirect to login
      $this->notifyListeners('UnauthenticatedUserException', array('app' => $this));
      $response = $this->getRedirectToLoginResponse();
    } catch (UnauthorizedActionException $e) {
      // User isn't allowed to use this functionality
      $this->notifyListeners('UnauthorizedActionException', array('app' => $this));
      $response = $this->getRedirectToHomeResponse($this->str('err-access-denied'));
    }
    if (!$response) $this->getErrorResponse(500, $this->str('err-misc-system-error'));

    // If nothing else, return an error
    $response->prepareFromRequest($this->request);
    $this->notifyListeners('ResponseCreated', array('app' => $this, 'response' => $response));
    return $response;
  }

  public function getRouter() { return $this->router; }



  public function notifyListeners(string $event, $data=null) {
    if (!isset($this->_listeners[$event])) return true;
    foreach ($this->_listeners[$event] as $l) {
      $listener = $l[0];
      $handler = $l[1];
      $result = $listener->$handler($data);

      // Optionally halt event propagation
      if ($result === false) return false;
    }
    return true;
  }



  public function registerListener(string $event, $listener, string $handler) {
    if (!isset($this->_listeners[$event])) $this->_listeners[$event] = array();
    foreach ($this->_listeners[$event] as $l) {
      if ($l[0] == $listener && $l[1] == $handler) return $this;
    }
    $this->_listeners[$event][] = array($listener, $handler);
    $this->notifyListeners('RegisterListener', array('app' => $this, 'event' => $event, 'listener' => $listener, 'handler' => $handler));
    return $this;
  }

  public function removeListener(string $event, $listener, string $handler) {
    if (!isset($this->_listeners[$event])) return $this;
    foreach ($this->_listeners[$event] as $k => $l) {
      if ($l[0] == $listener && $l[1] == $handler) {
        unset($this->_listeners[$event][$k]);
        $this->notifyListeners('RemoveListener', array('app' => $this, 'event' => $event, 'listener' => $listener, 'handler' => $handler));
        return $this;
      }
    }
    return $this;
  }

  public function requestIsAuthorized($action) {
    if ($this->authorizer === null) return true;
    else return $this->authorizer->requestIsAuthorized($this->request, $action);
  }

  public function requireAuthorization($action) {
    if (!$this->requestIsAuthorized($action)) throw new UnauthorizedFunctionAccessException($this->str('err-access-denied'));
  }



  public function setAuthorizer(Interfaces\Authorizer $authorizer) {
    $this->authorizer = $authorizer;
    $this->notifyListeners('SetAuthorizer', array('context' => $this, 'authorizer' => $authorizer));
    return $this;
  }

  public function setConfig(Interfaces\Config $config) {
    $this->config = $config;
    $this->notifyListeners('SetConfig', array('context' => $this, 'config' => $config));
    return $this;
  }

  /** Set DB */
  public function setDb(Interfaces\DB $db) {
    $this->db = $db;
    $this->notifyListeners('SetDb', array('context' => $this, 'db' => $db));
    return $this;
  }

  public function setExecutionProfile(int $profile) {
    $this->executionProfile = $profile;
    $this->notifyListeners('SetExecutionProfile', array('context' => $this, 'executionProfile' => $profile));
    return $this;
  }

  public function setUiManager(\Skel\Interfaces\UiManager $im) {
    $this->uiManager = $im;
    $this->notifyListeners('SetUiManager', array('context' => $this, 'uiManager' => $im));
    return $this;
  }

  /** Sets the localizer for this app */
  public function setLocalizer(Interfaces\Localizer $localizer) {
    $this->localizer = $localizer;
    $this->notifyListeners('SetLocalizer', array('context' => $this, 'localizer' => $localizer));
    return $this;
  }

  /** Associate a request object with this application */
  public function setRequest(Interfaces\Request $request) {
    $this->request = $request;
    $this->notifyListeners('SetRequest', array('context' => $this, 'request' => $request));
    return $this;
  }

  public function setRouter(Interfaces\Router $router) {
    $this->router = $router;
    $this->notifyListeners('SetRouter', array('context' => $this, 'router' => $router));
    return $this;
  }

  /**
   * Get string by key
   *
   * @return string
   * @param string $key - the (arbitrary) key by which the string was stored
   */
  public function str(string $key, string $default='') {
    if (!$this->_strings) {
      $default_strings = array(
        'err-access-denied' => '<h1>Sorry, you can\'t do that</h1><p>You\'ve tried to access a part of the system that requires more privileges than you have.</p>',
        'err-request-missing' => '<h1>System Error</h1><p>You must associate a request with this app instance before using this function</p>',
        'err-404' => '<h1>404</h1><p>Sorry, we can\'t find the page you\'re looking for :(</p>',
        'err-misc-system-error' => '<h1>Error!</h1><p>Sorry, something went wrong, and we\'re not sure what :(</p>',
      );
      $this->_strings = array_merge($default_strings, $this->db->getStrings());
    }

    return $this->_strings[$key] ?: $default;
  }
}

?>
