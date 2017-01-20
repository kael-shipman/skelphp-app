<?php

require "vendor/autoload.php";


// Implement test classes for testing purposes

class TestAppConfig implements \Skel\Interfaces\AppConfig {
  //Execution Profiles
  const PROFILE_PROD = 1;
  const PROFILE_BETA = 2;
  const PROFILE_TEST = 4;

  function checkConfig() { return true; }
  function get(string $key) {
    $cwd = getcwd();
    $config = array(
      'ctx-root' => $cwd,
      'ex-prf' => static::PROFILE_TEST,
      'pub-rt' => $cwd
    );
    if (!array_key_exists($key, $config)) throw new \Skel\NonexistentConfigException("Config key `$key` doesn't exist");
    return $config[$key];
  }
  function getContextRoot() { $this->get('ctx-root'); }
  function getExecutionProfile() { $this->get('ex-prf'); }
  function getPublicRoot() { $this->get('pub-rt'); }
  function set(string $key, $val) { echo "not impelemented"; }
  function dump() { echo "Not implemented"; }
}

class TestDb implements \Skel\Interfaces\AppDb {
  public function getString(string $key) {
    return '';
  }
  public function getStrings() {
    return array();
  }
  public function getTemplate(string $name) {
    return new TestTemplate();
  }
}

class TestTemplate implements \Skel\Interfaces\Template {
  public function render(array $elements) {
    $output = array();
    foreach($elements as $k=>$e) $output = "$k: `$e`";
    return implode("\n", $output);
  }

  static public function renderInto(string $template, array $elements, bool $templateIsFileName) {
    $t = new static();
    return $t->render($elements);
  }
}

