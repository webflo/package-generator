<?php

namespace PackageGenerator;

use Gitonomy\Git\Reference;

interface BuilderInterface {

  public function __construct(array $composerJson, array $composerLock, Reference $gitObject);

  /**
   * @return string
   */
  public function getCommitMessage();

  /**
   * @return array
   */
  public function getPackage();

}
