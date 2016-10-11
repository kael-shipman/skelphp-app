<?php
namespace Skel;

/** Coordinates the various components that comprise an application */

class App implements Interfaces\AccessControlledApp {
  protected $localizer;
  protected $authorizer;
  protected $router;
  protected $db;
  protected $request;
  protected $_listeners = array();

  public function abort(Interfaces\Response $r) {
    $r->prepareFromRequest($this->request);
    $r->send();
    die();
  }



  public function __construct(Interfaces\DB $db, Interfaces\Router $router) {
    $this->db = $db;
    $this->router = $router;
  }



  public function getCanonicalPath() {
    if (!$this->request) throw new \RuntimeException('err-request-missing');
    $uri = $this->request->getUri();

    if ($this->localizer) return $this->localizer->getCanonicalPath($this->db, $uri);
    else return $uri->getPath();
  }

  public function getContent($key, array $content=array()) {
    return implode("\n",$content);
  }

  public function getDb() { return $this->db; }

  /** Set DB */
  public function setDb(Interfaces\DB $db) {
    $this->db = $db;
    return $this;
  }

  public function getErrorResponse(int $code=404, $str=null) {
    $r = new Response('', $code);
    if (!$str) $str = $this->str('err-404');
    $str = array('content' => $str);

    if ($code == 404) $r->setContent($this->getContent('404', $str));
    else $r->setContent($this->getContent('generic-error', $str));
    return $r;
  }

  public function getRedirectToHomeResponse($msg=null) {
    //TODO: Do something with the message
    //if ($msg) 
    return new Response(null, 301, array('Location: '.$this->router->getPath('home')));
  }

  public function getRedirectToLoginResponse() {
    return new Response(null, 301, array('Location: '.$this->router->getPath('login')));
  }

  public function getResponse() {
    try {
      $response = $this->router->routeRequest($this->request, $this);
      if (!$response) $response = $this->getErrorResponse(404);
      $response->prepareFromRequest($this->request);
      $this->notifyListeners('ResponseCreated', array('app' => $this, 'response' => $response));
      return $response;
    } catch (UnauthenticatedUserException $e) {
      // User isn't yet authenticated. Redirect to login
      $this->notifyListeners('UnauthenticatedUserException', array('app' => $this));
      return $this->getRedirectToLoginResponse();
    } catch (UnauthorizedActionException $e) {
      // User isn't allowed to use this functionality
      $this->notifyListeners('UnauthorizedActionException', array('app' => $this));
      return $this->getRedirectToHomeResponse($this->str('err-access-denied'));
    }

    // If nothing else, return an error
    return $this->getErrorResponse(500, $this->str('err-misc-system-error'));
  }



  public function notifyListeners(string $event, $data=null) {
    if (!isset($this->_listeners[$event])) return true;
    foreach ($this->_listeners[$event] as $l) {
      $result = $l[0]->$l[1]($data);
      // Halt event propagation
      if ($result == false) return false;
    }
    return true;
  }



  public function registerListener(string $event, $listener, string $handler) {
    if (!isset($this->_listeners[$event])) $this->_listeners[$event] = array();
    foreach ($this->_listeners[$event] as $l) {
      if ($l[0] == $listener && $l[1] == $handler) return true;
    }
    $this->_listeners[$even][] = array($listener, $handler);
  }

  public function removeListener(string $event, $listener, string $handler) {
    if (!isset($this->_listeners[$event])) return true;
    foreach ($this->_listeners[$event] as $k => $l) {
      if ($l[0] == $listener && $l[1] == $handler) {
        unset($this->_listeners[$event][$k]);
        return true;
      }
    }
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
    return $this;
  }

  /** Sets the localizer for this app */
  public function setLocalizer(Interfaces\Localizer $localizer) {
    $this->localizer = $localizer;
    return $this;
  }

  /** Associate a request object with this application */
  public function setRequest(Interfaces\Request $request) {
    $this->request = $request;
    return $this;
  }

  /**
   * Get string by key
   *
   * @return string
   * @param string $key - the (arbitrary) key by which the string was stored
   */
  public function str(string $key) {
    $default_strings = array(
      'err-access-denied' => 'Sorry, you\'re not allowed to do that',
      'err-request-missing' => 'You must associate a request with this app instance before using this function',
      'err-404' => 'Sorry, we can\'t find the page you\'re looking for :(',
      'err-misc-system-error' => 'Sorry, something went wrong :(',
    );
    if ($this->localizer !== null) $strings = $this->localizer->getStrings();
    else $strings = array();

    $strings = array_merge($default_strings, $strings);

    return isset($strings[$key]) ? $strings[$key] : '';
  }
}

?>
