<?php
/**
 * CompileTrait.php
 */

namespace XQ\Pleasing\Traits;

use XQ\Pleasing\Pleasing;


/**
 * Contains functions designed to ease the compiling of one-off assets with Pleasing.
 *
 * @author  Aaron M Jones <aaron@jonesiscoding.com>
 * @version Pleasing v2.0.0 (https://github.com/exactquery/pleasing)
 * @license MIT (https://github.com/exactquery/pleasing/blob/master/LICENSE)
 *
 * Class PleasingCompileTrait
 * @package XQ\Pleasing
 */
trait PleasingCompileTrait
{
  /** @var array            List of file extensions to consider as images */
  protected $imageFileTypes = array( 'gif', 'jpg', 'png', 'svg', 'tif' );
  /** @var array            List of file extensions to consider as web fonts */
  protected $fontFileTypes = array( 'otf', 'eot', 'svg', 'ttf', 'woff', 'woff2' );
  /** @var  Pleasing */
  protected $_Pleasing;

  /**
   * @return Pleasing
   */
  protected function Pleasing()
  {
    return $this->_Pleasing;
  }

  /**
   * @param $inputFilePath
   * @param $outputPath
   * @param bool $minify
   * @return bool|string
   */
  protected function compileAsset($inputFilePath, $outputPath, $minify = true)
  {
    $outputPath = $this->validatedPath( $outputPath );
    $inputFilePath = realpath( $inputFilePath );

    if ( $inputFilePath && $outputPath ) {

      // Determine Input Specifics
      $inputPathInfo = pathinfo( $inputFilePath );
      $ext = $inputPathInfo[ 'extension' ];
      $inputFileName = $inputPathInfo[ 'basename' ];

      // Determine Output Specifics
      if ($ext == "less" || $ext == 'scss')
      {
        $newExt = ( $minify ) ? "min.css" : "css";
        $outputFile = $outputPath . str_replace( $ext, $newExt, $inputFileName );
      }
      elseif ( in_array( $ext, $this->imageFileTypes ) || in_array($ext, $this->fontFileTypes) )
      {
        $outputFile = $outputPath . $inputFileName;
      }
      else
      {
        $outputFile = ( $minify ) ? $outputPath . str_replace( $ext, "min." . $ext, $inputFileName ) : $outputPath.$inputFileName;
      }

      // Build the File Asset & Output
      $collection[] = $this->Pleasing()->buildFileAsset($inputFilePath);

      if (isset($collection) && !empty($collection)) {
        $AssetCollection = $this->Pleasing()->buildAssetCollection($collection, $minify);

        if ( $assetCode = $AssetCollection->dump() )
        {
          file_put_contents( $outputFile, $assetCode );

          return $outputFile;
        }
      }
    }

    return false;
  }

  /**
   * Resolves and validates a path, or creates the path if it does not exist.  The path is returned with a trailing
   * slash for convenience.
   *
   * @param string $path    The path to validate
   *
   * @return string         The validated path, including trailing slash if it is a directory.
   * @throws \Exception     If the path cannot be created.
   */
  protected function validatedPath( $path )
  {
    if ( !$outputPath = realpath( $path ) ) {
      // Create the path and test again.
      if ( !mkdir( $path, 0777, true ) || !$outputPath = realpath( $path ) ) {
        throw new \Exception( 'Could not create output path "' . $path . '".  Please check the path\'s permissions.' );
      }
    }

    // Make sure we have a trailing slash
    if ( substr( $outputPath, -1, 1 ) != DIRECTORY_SEPARATOR ) {
      $outputPath .= DIRECTORY_SEPARATOR;
    }

    return $outputPath;
  }
}