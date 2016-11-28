<?php
/**
 * PleasingFilterFactory.php
 */

namespace XQ\Pleasing\Assetic\Filter;

use Assetic\Filter\FilterInterface;

/**
 * Class PleasingFilterFactory
 *
 * @author  Aaron M Jones <aaron@jonesiscoding.com>
 * @version Pleasing v1.3.0 (https://github.com/exactquery/pleasing)
 * @license MIT (https://github.com/exactquery/pleasing/blob/master/LICENSE)
 *
 * @package XQ\Pleasing\Assetic\Filter
 */
class PleasingFilterFactory
{
  protected $paths;

  public function __construct($paths)
  {
    $this->paths = $paths;
  }

  public function build( $filterConfig )
  {
    $className = (isset($filterConfig[ 'class' ])) ? $filterConfig[ 'class' ] : null;
    $binName = (isset($filterConfig['bin'])) ? $filterConfig['bin']: null;
    if ( $className )
    {
      if ( !class_exists( $className ) )
      {
        throw new \Exception( $className . ' does not exist.' );
      }
      elseif ( !is_subclass_of( $className, FilterInterface::class ) )
      {
        throw new \Exception( $className . ' does not implement ' . FilterInterface::class );
      }
      else
      {
        /** @var FilterInterface $filter */
        $filter = new $className();

        // Apply Parameters
        unset( $filterConfig[ 'class' ], $filterConfig[ 'apply_to' ] );
        foreach ( $filterConfig as $key => $value )
        {
          $methodName = 'set' . (str_replace(" ", "", ucwords(strtr($key, "_-", "  "))));
          $value = $this->resolveConfigValue( $value );
          try
          {
            $filter->$methodName( $value );
          }
          catch (\Exception $e)
          {
            throw new \Exception('Could not set the option "' . $key . '" to "' . $value . ' (' . $e->getMessage() . ')');
          }
        }

        return $filter;
      }
    }
    elseif ( $binName )
    {
      throw new \Exception( 'Binary only filters are not supported in this build.  A PHP class is required.' );
    }
    else
    {
      throw new \Exception( 'The filter must have either a class or bin key.' );
    }
  }

  public function resolveConfigValue( $resolve )
  {
    if ( is_array( $resolve ) )
    {
      foreach ( $resolve as $key => $value )
      {
        $resolve[ $key ] = $this->resolveConfigValue( $value );
      }
    }
    else
    {
      if ( false !== strpos( $resolve, "@" ) || false !== strpos( $resolve, "%" ) || false !== strpos($resolve, '..') )
      {
        $resolve = str_replace( array_keys( $this->paths ), array_values( $this->paths ), $resolve );

        if ( $resolved = realpath( $resolve ) )
        {
          return $resolved;
        }
        else
        {
          throw new \Exception( "The resource '" . $resolve . "' could not be located'" );
        }
      }
    }
    return $resolve;
  }
}