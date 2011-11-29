<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Doctrine filter form generator.
 *
 * This class generates a Doctrine filter forms.
 *
 * @package    symfony
 * @subpackage generator
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id: sfDoctrine2FormFilterGenerator.class.phpp 11675 2008-09-19 15:21:38Z fabien $
 */
class sfDoctrine2FormFilterGenerator extends sfDoctrine2FormGenerator
{
  /**
   * Initializes the current sfGenerator instance.
   *
   * @param sfGeneratorManager $generatorManager A sfGeneratorManager instance
   */
  public function initialize(sfGeneratorManager $generatorManager)
  {
    parent::initialize($generatorManager);

    $this->setGeneratorClass('sfDoctrineFormFilter');
  }

  /**
   * Generates classes and templates in cache.
   *
   * @param array $params The parameters
   *
   * @return string The data to put in configuration cache
   */
  public function generate($params = array())
  {
    $this->params = $params;
    $this->databaseManager = $params['database_manager'];

    if (!isset($this->params['model_dir_name']))
    {
      $this->params['model_dir_name'] = 'model';
    }

    if (!isset($this->params['filter_dir_name']))
    {
      $this->params['filter_dir_name'] = 'filter';
    }

    $metadatas = $this->loadMetadatas();

    // create the project base class for all forms
    $file = sfConfig::get('sf_lib_dir').'/filter/doctrine2/BaseFormFilterDoctrine2.class.php';
    if (!file_exists($file))
    {
      if (!is_dir(sfConfig::get('sf_lib_dir').'/filter/doctrine2/base'))
      {
        mkdir(sfConfig::get('sf_lib_dir').'/filter/doctrine2/base', 0777, true);
      }

      file_put_contents($file, $this->evalTemplate('sfDoctrineFormFilterBaseTemplate.php'));
    }

    $pluginPaths = $this->generatorManager->getConfiguration()->getAllPluginPaths();

    // create a form class for every Doctrine class
    foreach ($metadatas as $metadata)
    {
      $this->metadata = $metadata;
      $this->modelName = $metadata->name;
			$this->formName = str_replace('\\', '', $this->modelName);

      $baseDir = sfConfig::get('sf_lib_dir') . '/filter/doctrine2';

      $isPluginModel = $this->isPluginModel($metadata->name);
      if ($isPluginModel)
      {
        $pluginName = $this->getPluginNameForModel($metadata->name);
        $baseDir .= '/' . $pluginName;
      }

      if (!is_dir($baseDir.'/base'))
      {
        mkdir($baseDir.'/base', 0777, true);
      }

      $path = $baseDir.'/base/Base'.str_replace('\\', '', $this->modelName).'FormFilter.class.php';
      $dir = dirname($path);
      if (!is_dir($dir))
      {
        mkdir($dir, 0777, true);
      }
      file_put_contents($path, $this->evalTemplate(null === $this->getParentModel() ? 'sfDoctrineFormFilterGeneratedTemplate.php' : 'sfDoctrineFormFilterGeneratedInheritanceTemplate.php'));

      if ($isPluginModel)
      {
        $path = $pluginPaths[$pluginName].'/lib/filter/doctrine2/Plugin'.$this->modelName.'FormFilter.class.php';
        $path = str_replace('\\', '', $path);
        if (!file_exists($path))
        {
          $dir = dirname($path);
          if (!is_dir($dir))
          {
            mkdir($dir, 0777, true);
          }
          file_put_contents($path, $this->evalTemplate('sfDoctrineFormFilterPluginTemplate.php'));
        }
      }
      $path = $baseDir.'/'.$this->modelName.'FormFilter.class.php';
      $path = str_replace('\\', '', $path);
      $dir = dirname($path);
      if (!is_dir($dir))
      {
        mkdir($dir, 0777, true);
      }
      if (!file_exists($path))
      {
        if ($isPluginModel)
        {
           file_put_contents($path, $this->evalTemplate('sfDoctrinePluginFormFilterTemplate.php'));
        } else {
           file_put_contents($path, $this->evalTemplate('sfDoctrineFormFilterTemplate.php'));
        }
      }
    }
  }

  /**
   * Returns a sfWidgetForm class name for a given column.
   *
   * @param  sfDoctrineColumn $column
   * @return string    The name of a subclass of sfWidgetForm
   */
  public function getWidgetClassForColumn($column)
  {
    switch ($column->getDoctrineType())
    {
      case 'boolean':
        $name = 'Choice';
        break;
      case 'date':
      case 'datetime':
      case 'timestamp':
        $name = 'FilterDate';
        break;
      case 'enum':
        $name = 'Choice';
        break;
      default:
        $name = 'FilterInput';
    }

    if ($column->isForeignKey())
    {
      $name = 'DoctrineChoice';
    }

    return sprintf('sfWidgetForm%s', $name);
  }

  /**
   * Returns a PHP string representing options to pass to a widget for a given column.
   *
   * @param  sfDoctrineColumn $column
   * @return string    The options to pass to the widget as a PHP string
   */
  public function getWidgetOptionsForColumn($column)
  {
    $options = array();

		$isForeignKey = $column->isForeignKey();
    $withEmpty = $column->isNotNull() && !$isForeignKey ? array("'with_empty' => false") : array();
    switch ($column->getDoctrineType())
    {
      case 'boolean':
        $options[] = "'choices' => array('' => 'yes or no', 1 => 'yes', 0 => 'no')";
        break;
      case 'date':
      case 'datetime':
      case 'timestamp':
        $options[] = "'from_date' => new sfWidgetFormDate(), 'to_date' => new sfWidgetFormDate()";
        $options = array_merge($options, $withEmpty);
        break;
      case 'enum':
        $values = array('' => '');
        $values = array_merge($values, $column['values']);
        $values = array_combine($values, $values);
        $options[] = "'choices' => ".$this->arrayExport($values);
        break;
      default:
        $options = array_merge($options, $withEmpty);
    }

    if ($column->isForeignKey())
    {
      $options[] = sprintf('\'model\' => \'%s\', \'add_empty\' => true', $column->getForeignMetadata()->name);
    }

    return ($isForeignKey ? '$this->em, ' : '').(count($options) ? sprintf('array(%s)', implode(', ', $options)) : 'array()');
  }

  /**
   * Returns a sfValidator class name for a given column.
   *
   * @param  sfDoctrineColumn $column
   * @return string    The name of a subclass of sfValidator
   */
  public function getValidatorClassForColumn($column)
  {
    switch ($column->getDoctrineType())
    {
      case 'boolean':
        $name = 'Choice';
        break;
      case 'float':
      case 'decimal':
        $name = 'Number';
        break;
      case 'integer':
        $name = 'Integer';
        break;
      case 'date':
      case 'datetime':
      case 'timestamp':
        $name = 'DateRange';
        break;
      case 'enum':
        $name = 'Choice';
        break;
      default:
        $name = 'Pass';
    }

    if ($column->isPrimarykey() || $column->isForeignKey())
    {
      $name = 'DoctrineChoice';
    }

    return sprintf('sfValidator%s', $name);
  }

  /**
   * Returns a PHP string representing options to pass to a validator for a given column.
   *
   * @param  sfDoctrineColumn $column
   * @return string    The options to pass to the validator as a PHP string
   */
  public function getValidatorOptionsForColumn($column)
  {
    $options = array('\'required\' => false');
		$isForeignKey = $column->isForeignKey();
		$requiresEm = ($isForeignKey || $column->isPrimaryKey());
    if ($isForeignKey)
    {
  		foreach ($column->getForeignMetadata()->fieldMappings as $name => $fieldMapping)
      {
        if (isset($fieldMapping['id']) && $fieldMapping['id'])
        {
          break;
        }
      }

      $options[] = sprintf('\'model\' => \'%s\', \'column\' => \'%s\'', $column->getForeignMetadata()->name, $column->getName());
    }
    else if ($column->isPrimaryKey())
    {
      $options[] = sprintf('\'model\' => \'%s\', \'column\' => \'%s\'', $this->modelName, $column->getName());
    }
    else
    {
      switch ($column->getDoctrineType())
      {
        case 'boolean':
          $options[] = "'choices' => array('', 1, 0)";
          break;
        case 'date':
          $options[] = "'from_date' => new sfValidatorDate(array('required' => false)), 'to_date' => new sfValidatorDateTime(array('required' => false))";
          break;
        case 'datetime':
        case 'timestamp':
          $options[] = "'from_date' => new sfValidatorDateTime(array('required' => false, 'datetime_output' => 'Y-m-d 00:00:00')), 'to_date' => new sfValidatorDateTime(array('required' => false, 'datetime_output' => 'Y-m-d 23:59:59'))";
          break;
        case 'enum':
          $values = array_combine($column['values'], $column['values']);
          $options[] = "'choices' => ".$this->arrayExport($values);
          break;
      }
    }

    return ($requiresEm ? '$this->em, ' : '').(count($options) ? sprintf('array(%s)', implode(', ', $options)) : '');
  }

  public function getValidatorForColumn($column)
  {
    $format = 'new %s(%s)';
    if (in_array($class = $this->getValidatorClassForColumn($column), array('sfValidatorInteger', 'sfValidatorNumber')))
    {
      $format = 'new sfValidatorSchemaFilter(\'text\', new %s(%s))';
    }

    return sprintf($format, $class, $this->getValidatorOptionsForColumn($column));
  }

  public function getType($column)
  {
    if ($column->isForeignKey())
    {
      return 'ForeignKey';
    }

    switch ($column->getDoctrineType())
    {
      case 'enum':
        return 'Enum';
      case 'boolean':
        return 'Boolean';
      case 'date':
      case 'datetime':
      case 'timestamp':
        return 'Date';
      case 'integer':
      case 'decimal':
      case 'float':
        return 'Number';
      default:
        return 'Text';
    }
  }

  /**
   * Array export. Export array to formatted php code
   *
   * @param array $values
   * @return string $php
   */
  protected function arrayExport($values)
  {
    $php = var_export($values, true);
    $php = str_replace("\n", '', $php);
    $php = str_replace('array (  ', 'array(', $php);
    $php = str_replace(',)', ')', $php);
    $php = str_replace('  ', ' ', $php);
    return $php;
  }

  /**
   * Get the name of the form class to extend based on the inheritance of the model
   *
   * @return string
   */
  public function getFormClassToExtend()
  {
    return null === ($model = $this->getParentModel()) ? 'BaseFormFilterDoctrine2' : sprintf('%sFormFilter', $model);
  }
}
