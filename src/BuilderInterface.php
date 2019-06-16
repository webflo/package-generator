<?php

namespace PackageGenerator;

use Gitonomy\Git\Reference;
use Gitonomy\Git\Repository;

interface BuilderInterface {

  public function __construct(array $composerJson, array $composerLock, Reference $gitObject, array $config, Repository $metapackage_repository);

  /**
   * @return string
   */
  public function getCommitMessage();

  /**
   * @return array
   */
  public function getPackage();

}
