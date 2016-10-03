<?php
namespace Skel;

/**
 * Coordinates the various components that comprise an application
 *
 * Components include
 * * DB
 * * Localizable strings
 * * User authentication
 * * Routing
 *
 * */
class App implements Interfaces\App {
  protected $localizer;
  protected $authenticator;
  protected $authorizer;
  protected $router;
  protected $db;
  protected $request;
  protected $_listeners = array();

  /**
   * Constructor takes optional default language
   */
  public function __construct(Interfaces\DB $db, Interfaces\Router $router) {
    $this->db = $db;
    $this->router = $router;
  }

  /** Set DB */
  public function setDb(Interfaces\DB $db) {
    $this->db = $db;
    return $this;
  }

  /** Sets the localizer for this app */
  public function setLocalizer(Interfaces\Localizer $localizer) {
    $this->localizer = $localizer;
    return $this;
  }

  /**
   * Get string by key
   *
   * @return string
   * @param string $key - the (arbitrary) key by which the string was stored
   */
  public function str(string $key) {
    if ($this->localizer === null) throw new \RuntimeException('You must set a Localizer to use the `str` function.');
    return $this->localizer->getString($key);
  }

  public function abort(Interfaces\Response $r) {
    $r->prepareFromRequest($this->request);
    $r->send();
    die();
  }

  public function getDb() { return $this->db; }

  public function getCanonicalPath() {
    if (!$this->request) throw new \RuntimeException('You must associate a request with this app instance before using this function');
    $uri = $this->request->getUri();

    if ($this->localizer) return $this->localizer->getCanonicalPath($this->db, $uri);
    else return $uri->getPath();
  }

  public function getContent($key, array $content=array()) {
    return implode("\n",$content);
  }

  /** Associate a request object with this application */
  public function setRequest(Interfaces\Request $request) {
    $this->request = $request;
    return $this;
  }

  /** Set a router for this app */
  public function setRouter(Interfaces\Router $router) {
    $this->router = $router;
    return $this;
  }

  /** Set an authenticator for this app */
  public function setAuthenticator(Interfaces\Authenticator $authenticator) {
    $this->authenticator = $authenticator;
    return $this;
  }

  public function getResponse() {
    if (!$this->router) throw new \RuntimeException('You must set a router object to use for routing requests! You do this by passing an object that implements \\Skel\\Interfaces\\Router to your app\'s `setRouter` method.');
    return $this->router->routeRequest($this->request, $this)->prepareFromRequest($this->request);
  }

  public function generateError($str=null, int $code=404) {
    $r = new Response('', $code);
    if (!$str) $str = 'Sorry, we can\'t find the page you\'re looking for :(';
    $str = array('content' => $str);

    if ($code == 404) $r->setContent($this->getContent('404', $str));
    else $r->setContent($this->getContent('generic-error', $str));
    $this->abort($r);
  }

  public function registerListener(string $event, \object $listener, string $handler) {
    if (!isset($this->_listeners[$event])) $this->_listeners[$event] = array();
    foreach ($this->_listeners[$event] as $l) {
      if ($l[0] == $listener && $l[1] == $handler) return true;
    }
    $this->_listeners[$even][] = array($listener, $handler);
  }

  public function removeListener(string $event, \object $listener, string $handler) {
    if (!isset($this->_listeners[$event])) return true;
    foreach ($this->_listeners[$event] as $k => $l) {
      if ($l[0] == $listener && $l[1] == $handler) {
        unset($this->_listeners[$event][$k]);
        return true;
      }
    }
  }

  public function notifyListeners(string $event, $data=null) {
    if (!isset($this->_listeners[$event])) return true;
    foreach ($this->_listeners[$event] as $l) {
      $l[0]->$l[1]($data);
    }
  }
}

?>
