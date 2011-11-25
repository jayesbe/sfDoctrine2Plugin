<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * (c) Jonathan H. Wage <jonwage@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once(dirname(__FILE__).'/sfDoctrine2BaseTask.class.php');

/**
 * Generates code based on your schema.
 *
 * @package    sfDoctrinePlugin
 * @subpackage task
 * @author     Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 * @version    SVN: $Id: sfDoctrineBuildTask.class.php 21403 2009-08-24 17:45:19Z Kris.Wallsmith $
 */
class sfDoctrineBuildTask extends sfDoctrine2BaseTask
{
  const
    BUILD_MODEL   = 1,
    BUILD_FORMS   = 2,
    BUILD_FILTERS = 4,
    BUILD_SQL     = 8,
    BUILD_DB      = 16,

    OPTION_MODEL       = 1,
    OPTION_FORMS       = 3,  // model, forms
    OPTION_FILTERS     = 5,  // model, filters
    OPTION_SQL         = 9,  // model, sql
    OPTION_DB          = 16,
    OPTION_ALL_CLASSES = 7,  // model, forms, filters
    OPTION_ALL         = 31; // model, forms, filters, sql, db

  /**
   * @see sfTask
   */
  protected function configure()
  {
    $this->addOptions(array(
      new sfCommandOption('application', null, sfCommandOption::PARAMETER_OPTIONAL, 'The application name', true),
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'dev'),
      new sfCommandOption('no-confirmation', null, sfCommandOption::PARAMETER_NONE, 'Whether to force dropping of the database'),
      new sfCommandOption('all', null, sfCommandOption::PARAMETER_NONE, 'Build everything and reset the database'),
      new sfCommandOption('all-classes', null, sfCommandOption::PARAMETER_NONE, 'Build all classes'),
      new sfCommandOption('model', null, sfCommandOption::PARAMETER_NONE, 'Build model classes'),
      new sfCommandOption('forms', null, sfCommandOption::PARAMETER_NONE, 'Build form classes'),
      new sfCommandOption('filters', null, sfCommandOption::PARAMETER_NONE, 'Build filter classes'),
      new sfCommandOption('sql', null, sfCommandOption::PARAMETER_NONE, 'Build SQL'),
      new sfCommandOption('db', null, sfCommandOption::PARAMETER_NONE, 'Drop, create, and insert SQL'),
      new sfCommandOption('and-load', null, sfCommandOption::PARAMETER_OPTIONAL | sfCommandOption::IS_ARRAY, 'Load fixture data'),
      new sfCommandOption('and-append', null, sfCommandOption::PARAMETER_OPTIONAL | sfCommandOption::IS_ARRAY, 'Append fixture data'),
      new sfCommandOption('and-update-schema', null, sfCommandOption::PARAMETER_NONE, 'Update schema after rebuilding all classes'),
      new sfCommandOption('dump-sql', null, sfCommandOption::PARAMETER_NONE, 'Whether to output the generated sql instead of executing'),
    ));

    $this->namespace = 'doctrine2';
    $this->name = 'build';

    $this->briefDescription = 'Generate code based on your schema';

    $this->detailedDescription = <<<EOF
The [doctrine2:build|INFO] task generates code based on your schema:

  [./symfony doctrine2:build|INFO]

You must specify what you would like built. For instance, if you want model
and form classes built use the [--model|COMMENT] and [--forms|COMMENT] options:

  [./symfony doctrine2:build --model --forms|INFO]

You can use the [--all|COMMENT] shortcut option if you would like all classes and
SQL files generated and the database rebuilt:

  [./symfony doctrine2:build --all|INFO]

This is equivalent to running the following tasks:

  [./symfony doctrine2:drop-db|INFO]
  [./symfony doctrine2:create-db|INFO]
  [./symfony doctrine2:build-model|INFO]
  [./symfony doctrine2:build-forms|INFO]
  [./symfony doctrine2:build-filters|INFO]
  [./symfony doctrine2:create-schema|INFO]

You can also generate only class files by using the [--all-classes|COMMENT] shortcut
option. When this option is used alone, the database will not be modified.

  [./symfony doctrine2:build --all-classes|INFO]

The [--and-load|COMMENT] option will load data from the project and plugin
[data/fixtures/|COMMENT] directories:

To specify what fixtures are loaded, add a parameter to the [--and-load|COMMENT] option:

  [./symfony doctrine2:build --all --and-load="data/fixtures/dev/"|INFO]

To append fixture data without erasing any records from the database, include
the [--and-append|COMMENT] option:

  [./symfony doctrine2:build --all --and-append|INFO]

To update your database schema after rebuilding all your classes, include 
the [--and-update-schema|COMMENT] option:

  [./symfony doctrine2:build --all-classes --and-update-schema|INFO]
EOF;
  }

  /**
   * @see sfTask
   */
  protected function execute($arguments = array(), $options = array())
  {
    if (!$mode = $this->calculateMode($options))
    {
      throw new InvalidArgumentException(sprintf("You must include one or more of the following build options:\n--%s\n\nSee this task's help page for more information:\n\n  php symfony help doctrine2:build", join(', --', array_keys($this->getBuildOptions()))));
    }

    if (self::BUILD_DB == (self::BUILD_DB & $mode))
    {
      $task = new sfDoctrineDropDbTask($this->dispatcher, $this->formatter);
      $task->setCommandApplication($this->commandApplication);
      $task->setConfiguration($this->configuration);
      $ret = $task->run(array(), array('no-confirmation' => $options['no-confirmation']));

      if ($ret)
      {
        return $ret;
      }

      $task = new sfDoctrineCreateDbTask($this->dispatcher, $this->formatter);
      $task->setCommandApplication($this->commandApplication);
      $task->setConfiguration($this->configuration);
      $ret = $task->run();

      if ($ret)
      {
        return $ret;
      }

      // :insert-sql will also be run, below
    }

    if (self::BUILD_MODEL == (self::BUILD_MODEL & $mode))
    {
      $task = new sfDoctrineBuildModelTask($this->dispatcher, $this->formatter);
      $task->setCommandApplication($this->commandApplication);
      $task->setConfiguration($this->configuration);
      $ret = $task->run();

      if ($ret)
      {
        return $ret;
      }
    }

    if (self::BUILD_FORMS == (self::BUILD_FORMS & $mode))
    {
      $task = new sfDoctrineBuildFormsTask($this->dispatcher, $this->formatter);
      $task->setCommandApplication($this->commandApplication);
      $task->setConfiguration($this->configuration);
      $ret = $task->run();

      if ($ret)
      {
        return $ret;
      }
    }

    if (self::BUILD_FILTERS == (self::BUILD_FILTERS & $mode))
    {
      $task = new sfDoctrineBuildFiltersTask($this->dispatcher, $this->formatter);
      $task->setCommandApplication($this->commandApplication);
      $task->setConfiguration($this->configuration);
      $ret = $task->run();

      if ($ret)
      {
        return $ret;
      }
    }

    if (self::BUILD_DB == (self::BUILD_DB & $mode))
    {
      $task = new sfDoctrineCreateSchemaTask($this->dispatcher, $this->formatter);
      $task->setCommandApplication($this->commandApplication);
      $task->setConfiguration($this->configuration);
      $ret = $task->run(array(), array('dump-sql' => $options['dump-sql']));

      if ($ret)
      {
        return $ret;
      }
    }

    if (isset($options['and-update-schema']) && $options['and-update-schema'])
    {
      $task = new sfDoctrineUpdateSchemaTask($this->dispatcher, $this->formatter);
      $task->setCommandApplication($this->commandApplication);
      $task->setConfiguration($this->configuration);
      $task->run(array(), array('dump-sql' => $options['dump-sql']));
    }

    if (count($options['and-load']) || count($options['and-append']))
    {
      $task = new sfDoctrineLoadDataFixturesTask($this->dispatcher, $this->formatter);
      $task->setCommandApplication($this->commandApplication);
      $task->setConfiguration($this->configuration);

      if (count($options['and-load']))
      {
        $ret = $task->run(array(), array(
          'dir' => in_array(array(), $options['and-load'], true) ? null : $options['and-load'],
        ));

        if ($ret)
        {
          return $ret;
        }
      }

      if (count($options['and-append']))
      {
        $ret = $task->run(array(), array(
          'dir'    => in_array(array(), $options['and-append'], true) ? null : $options['and-append'],
          'append' => true,
        ));

        if ($ret)
        {
          return $ret;
        }
      }
    }
  }

  /**
   * Calculates a bit mode based on the supplied options.
   *
   * @param  array $options
   *
   * @return integer
   */
  protected function calculateMode($options = array())
  {
    $mode = 0;
    foreach ($this->getBuildOptions() as $name => $value)
    {
      if (isset($options[$name]) && true === $options[$name])
      {
        $mode = $mode | $value;
      }
    }

    return $mode;
  }

  /**
   * Returns an array of valid build options.
   *
   * @return array An array of option names and their mode
   */
  protected function getBuildOptions()
  {
    $options = array();
    foreach ($this->options as $option)
    {
      if (defined($constant = __CLASS__.'::OPTION_'.str_replace('-', '_', strtoupper($option->getName()))))
      {
        $options[$option->getName()] = constant($constant);
      }
    }

    return $options;
  }
}