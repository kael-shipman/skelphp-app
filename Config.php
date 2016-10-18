<?php
namespace Skel;

class Config implements Interfaces\Config {
  protected $config;

  public function __construct(string $baseFilename) {
    $global = "$baseFilename.php";
    $local = "$baseFilename.local.php";
    if (!is_file($global)) throw new NonexistentFileException("You must have a global configurations file named `$baseFilename.php` (even if it's empty).");
    if (!is_file($local)) throw new NonexistentFileException("You must have a local configurations file named `$baseFilename.local.php` (even if it's empty). Configurations in this file will overrided configurations in the global configurations file.");

    $global = require $global;
    $local = require $local;

    if (!is_array($global) || !is_array($local)) throw new \RuntimeException("Configuration files should return an array of configurations");

    $this->config = array_replace($global, $local);
  }

  public function get(string $key) {
    if (!isset($this->config[$key])) throw new \InvalidArgumentException("Your configuration doesn't have a value for the key `$key`");
    return $this->config[$key];
  }

  public function set(string $key, $val) {
    $this->config[$key] = $val;
    return $this;
  }
}

?>    
