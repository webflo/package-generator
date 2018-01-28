<?php

namespace PackageGenerator\Commands;

use Gitonomy\Git\Reference\Branch;
use Gitonomy\Git\Reference\Tag;
use Gitonomy\Git\Repository;
use PackageGenerator\BuilderInterface;
use PackageGenerator\Dumper;
use PackageGenerator\PackageBuilder;
use Robo\Tasks;
use Symfony\Component\Yaml\Yaml;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends Tasks
{

  /**
   * @return \Robo\Collection\CollectionBuilder
   */
  public function generate($filepath)
  {
    if (!file_exists($filepath)) {
      throw new \LogicException('File not found.');
    }
    $config = Yaml::parse(file_get_contents($filepath));
    $collection = $this->collectionBuilder();
    foreach ($config['packages'] as $job) {
      $collection->addTask($this->buildPackage($job));
    }
    return $collection;
  }

  protected function buildPackage($config)
  {
    $collection = $this->collectionBuilder();
    $source = 'tmp/' . hash('sha256', $config['source']);
    $target = 'tmp/' . hash('sha256', $config['target']);

    if (!file_exists($source)) {
      $collection->taskGitStack()
        ->cloneRepo($config['source'], $source);
    }

    if (!file_exists($target)) {
      $collection->taskGitStack()
        ->cloneRepo($config['target'], $target);
    }

    $collection->addCode(function () use ($source, $target, $config) {
      /**
       * @var Branch[] $branches
       */
      $branches = [];

      /**
       * @var Tag[] $tags
       */
      $tags = [];

      $repository = new Repository($source);
      $repository->run('fetch');

      $branches = array_filter($repository->getReferences()->getRemoteBranches(), function (Branch $branch) {
        if ($branch->isRemote() && preg_match('/^origin\/8\./', $branch->getName(), $matches)) {
          return TRUE;
        }
        return FALSE;
      });

      $tags = array_filter($repository->getReferences()->getTags(), function (Tag $tag) {
        return preg_match('/^8\.[0-9]+\.[0-9]+/', $tag->getName());
      });

      $refs = $tags + $branches;
      $refs_array = [];

      foreach ($refs as $ref) {
        $name = str_replace('origin/', '', $ref->getName());
        if ($ref instanceof Branch) {
          $name .= '-dev';
        }
        $refs_array[$name] = $ref;
      }

      $sorted = \Composer\Semver\Semver::sort(array_keys($refs_array));

      foreach ($sorted as $version) {
        /** @var \Gitonomy\Git\Reference\Tag|\Gitonomy\Git\Reference\Branch $ref */
        $ref = $refs_array[$version];
        $repository->run('reset', ['--hard', $ref->getCommitHash()]);

        $composerJson = $repository->getPath() . '/composer.json';
        $composerJsonData = [];
        $composerLock = $repository->getPath() . '/composer.lock';
        $composerLockData = [];

        if (file_exists($composerJson)) {
          $composerJsonData = json_decode(file_get_contents($repository->getPath() . '/composer.json'), TRUE);
        }
        if (file_exists($composerLock)) {
          $composerLockData = json_decode(file_get_contents($repository->getPath() . '/composer.lock'), TRUE);
        }

        // Create a new repository object so local references are up-to-date.
        $metapackage_repository = new Repository($target);
        $metapackage_repository->run('config', ['user.name', $config['git']['author']['name']]);
        $metapackage_repository->run('config', ['user.email', $config['git']['author']['email']]);

        /** @var BuilderInterface $builder */
        $builder = new $config['builder']($composerJsonData, $composerLockData, $ref);
        $dump = new Dumper($ref, $builder->getPackage(), $metapackage_repository, $builder->getCommitMessage());
        $dump->write();
      }
    });

    $collection->progressMessage($target);

    $collection->taskGitStack()
      ->dir($target)
      ->exec(['push', '--all'])
      ->exec(['push', '--tags']);

    return $collection;
  }

}
