<?php
namespace Skel;

/** Coordinates the various components that comprise an application */

// TODO: Document various event triggers
class App implements Interfaces\App {
  use ObservableTrait;

  protected $config;
  protected $router;
  protected $db;
  protected $_strings;
  protected $request;
  protected $executionProfile;

  public function __construct(Interfaces\AppConfig $config, Interfaces\AppDb $db, Interfaces\Router $router) {
    $this->config = $config;
    $this->executionProfile = $config->getExecutionProfile();
    $this->db = $db;
    $this->notifyListeners('SetDatabase', array($db));
    $this->router = $router;
    $this->notifyListeners('SetRouter', array($router));
  }



  public function clearRequest() {
    $this->notifyListeners('BeforeClearRequest', array($this->request));
    $request = $this->request;
    $this->request = null;
    $this->notifyListeners('ClearRequest', array($request));
    return $request;
  }



  public function getError(int $code=404, string $header=null, string $text=null) {
    if (!$header) $header = $this->str('err-'.$code.'-header', 'Error!');
    if (!$text) $text = $this->str('err-'.$code.'-text', 'Sorry, there was an error processing your request.');
    $c = new Component(
      array('errorHeader' => $header, 'errorText' => $text),
      new StringTemplate('<h1>##errorHeader##</h1><p>##errorText##</p>', false)
    );

    $this->notifyListeners('Error', array($c, $code));
    return $c;
  }

  public function getExecutionProfile() { return $this->executionProfile; }

  public function getResponse(Interfaces\Request $request=null) {
    if ($request) $this->setRequest($request);
    if (!$this->request) throw new InvalidArgumentException("No request was provided and no request has been set. You must either set a Request via `setRequest` or provide a request in the method call");

    $this->notifyListeners('BeforeRouting', array($this->request));
    $response = null;
    $component = null;
    try {
      $component = $this->router->routeRequest($this->request, $this);
      if (!$component) throw new Http404Exception();
      elseif (!($component instanceof Interfaces\Component)) throw new InvalidControllerReturnException('The object returned by a controller method MUST be an instance of \Skel\Interfaces\Component or `false`');
    } catch (Http404Exception $e) {
      $component = $this->getError(404);
      // Don't have to notify listeners here because getError notifies on Error
    } catch (UnauthenticatedUserException $e) {
      // User isn't yet authenticated. Redirect to login
      // TODO: figure out how to send a message to the user
      $this->notifyListeners('UnauthenticatedUserException');
      $this->redirect('/');
    } catch (UnauthorizedActionException $e) {
      // User isn't allowed to use this functionality
      // TODO: figure out how to send a message to the user
      $this->notifyListeners('UnauthorizedActionException');
      $this->redirect('/');
    }

    $this->notifyListeners('ComponentCreated', array($component));
    $response = $this->createResponseFromComponent($component);

    $response->prepareFromRequest($this->request);
    $this->notifyListeners('ResponseCreated', array($response));
    return $response;
  }

  public function getPublicRoot() { return $this->config->getPublicRoot(); }

  public function getRequest() { return $this->request; }

  public function getRouter() { return $this->router; }

  public function getTemplate(string $name) {
    $path = $this->config->getTemplateDir()."/$name";
    $type = substr($path, strrpos($path, '.')+1);
    if ($type == 'html') $t = new \Skel\StringTemplate($path);
    elseif ($type == 'php') $t = new \Skel\PowerTemplate($path);
    else throw new \InvalidArgumentException("Template `$name` not found at `$path`!");
    return $t;
  }




  public function redirect(string $url, int $code=303) {
    switch($code) {
    case 301: $description = 'Moved Permanently'; break;
    case 302: $code = 303;
    case 303: $description = 'See Other'; break;
    case 307: $description = 'Temporary Redirect'; break;
    case 308: $description = 'Permanent Redirect'; break;
    default: throw new \RuntimeException("Unknown HTTP Status code `$code` for redirect");
    }

    $this->notifyListeners('Redirect', array($url, $code));
    header("HTTP/1.1 $code $description");
    header("Location: $url");
    die();
  }




  /** Associate a request object with this application */
  public function setRequest(Interfaces\Request $request) {
    $this->request = $request;
    $this->notifyListeners('SetRequest', array($request));
    return $this;
  }

  /**
   * Get string by key with optional default value
   *
   * @return string
   * @param string $key - the (arbitrary) key by which the string was stored
   */
  public function str(string $key, string $default='') {
    if (!$this->_strings) {
      $default_strings = array(
        'err-access-denied' => '<h1>Sorry, you can\'t do that</h1><p>You\'ve tried to access a part of the system that requires more privileges than you have.</p>',
        'err-request-missing' => '<h1>System Error</h1><p>You must associate a request with this app instance before using this function</p>',
        'err-404-header' => '404 - Not Found',
        'err-404-text' => 'Sorry, we can\'t find the page you\'re looking for :(',
        'err-500-header' => 'Error!',
        'err-500-text' => 'Sorry, something went wrong, and we\'re not sure what :(',
      );
      $this->_strings = array_merge($default_strings, $this->db->getStrings());
    }

    return $this->_strings[$key] ?: $default;
  }


  public function debugComponent(\Skel\Interfaces\Context $context, \Skel\Interfaces\Component $component) {
    $r = function(\Skel\Interfaces\Component $comp, $space="\t") use (&$r) {
      $headingSpace = substr($space,1);
      echo "\n{$headingSpace}Component----------------------------------------";
      $t = str_replace("\n",'\n', (string)$comp->getTemplate());
      if (strlen($t) > 100) $t = substr($t, 0, 50).'...'.substr($t, -50);
      echo "\n{$headingSpace}Template: $t\n";

      foreach($comp as $e => $c) {
        if ($c instanceof \Skel\Interfaces\Component){
          echo "\n$space$e:";
          $r($c, $space."\t");
        }elseif (!is_numeric($c) && !is_array($c) && !is_string($c) && !is_bool($c)) echo "\n$space$e:\n$space(Object)";
        else echo "\n$space$e:\n$space$c";
        echo "\n$space.........................\n";
      }
    };
    echo "Site: ";
    $r($component);
  }











  // Protected functions

  protected function createResponseFromComponent(Interfaces\Component $component) { 
    if ($this->getResponseType() == 'json') return new JsonResponse($component);
    else return new Response($component->render());
  }

  protected function getResponseType() {
    $accept = $this->request->headers->get('Accept');
    if (strpos($accept, 'json') !== false) return 'json';
    else return 'html';
  }
}

?>
