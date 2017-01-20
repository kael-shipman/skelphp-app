<?php

use PHPUnit\Framework\TestCase;

class AppTests extends TestCase {
  public function testCanCreateBasicApp() {
    $app = new \Skel\App(new TestAppConfig(), new TestDb);
    $this->assertTrue(($app instanceof \Skel\Interfaces\App) && ($app instanceof \Skel\App), "Should have returned a valid implementation of a Skel App");
  }
}

