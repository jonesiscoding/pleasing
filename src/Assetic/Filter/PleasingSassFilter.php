<?php
/**
 * PleasingSassFilter.php
 */

namespace XQ\Pleasing\Assetic\Filter;

use Assetic\Asset\AssetInterface;
use Assetic\Filter\FilterInterface;
use XQ\Drivers\AbstractSassDriver;
use XQ\Drivers\PhpSassDriver;
use XQ\Drivers\SasscDriver;
use XQ\Drivers\ScssphpDriver;

/**
 * Loads SASS/SCSS files using a driver for Sassc, PHP Sass, or Leafo's scssphp.
 *
 * Class PleasingSassFilter
 *
 * @author  Aaron M Jones <aaron@jonesiscoding.com>
 * @version Pleasing v1.3.0 (https://github.com/exactquery/pleasing)
 * @license MIT (https://github.com/exactquery/pleasing/blob/master/LICENSE)
 *
 * @package XQ\Pleasing\Assetic\Filter
 */
class PleasingSassFilter implements FilterInterface
{
  /** @var string Path to the sassc binary, if available. */
  private $sasscPath = null;
  /** @var string Temporary path to use if needed. */
  private $tmpPath = "/tmp";
  /** @var SasscDriver */
  protected $_sc;

  public function __construct($sasscPath = null, $tmpPath = null)
  {
    $this->sasscPath = $sasscPath;
    if ( $tmpPath )
    {
      $this->tmpPath = $tmpPath . DIRECTORY_SEPARATOR . "pleasing";
    }
  }

  public function setSourceMap( $sourceMap )
  {
    $this->sc()->setSourceMap( $sourceMap );
  }

  public function setOutputStyle( $style )
  {
    $this->sc()->setOutputStyle( $style );
  }

  public function setImportPaths( array $paths )
  {
    $this->sc()->setImportPaths( $paths );
  }

  public function addImportPath( $path, $prepend = false)
  {
    $this->sc()->addImportPath( $path, $prepend);
  }

  public function setPluginPaths( array $paths )
  {
    $this->sc()->setPluginPaths( $paths );
  }

  public function addPluginPath( $path, $prepend = false )
  {
    $this->sc()->addPluginPath( $path, $prepend );
  }

  public function filterLoad( AssetInterface $asset )
  {
    // Add the source folder as the FIRST import path
    if ( $dir = $asset->getSourceDirectory() )
    {
      $this->addImportPath( $dir, true );
    }

    // Run the Process
    $asset->setContent( $this->sc()->compile( $asset->getContent() ) );
  }

  public function filterDump( AssetInterface $asset )
  {
  }

  // region //////////////////////////////////////////////// Helper Methods

  /**
   * @return AbstractSassDriver
   * @throws \Exception
   */
  private function sc()
  {
    if ( !$this->_sc )
    {
      if ( $this->hasSassc() )
      {
        $this->_sc = new SasscDriver( $this->sasscPath );
      }
      elseif ( $this->hasSassExtension() )
      {
        $this->_sc = new PhpSassDriver();
      }
      elseif ( $this->hasLeafoScssPhp() )
      {
        $this->_sc = new ScssphpDriver();
      }
      else
      {
        throw new \Exception( 'No supported SASS compiler was found.' );
      }
    }

    return $this->_sc;
  }

  private function hasSassc()
  {
    return ( class_exists( SasscDriver::class ) && file_exists($this->sasscPath) );
  }

  private function hasSassExtension()
  {
    return ( class_exists( '\\Sass' ) );
  }

  private function hasLeafoScssPhp()
  {
    return class_exists( 'Leafo\ScssPhp\Compiler' );
  }

  // endregion ///////////////////////////////////////////// End Private Helper Methods
}