<?php
/**
 * PleasingRenderTrait.php
 */

namespace XQ\Pleasing;
/**
 * Uses the PHP header function to set the proper headers, then returns the contents of a CSS or JS file.
 *
 * Class PleasingRenderTrait
 *
 * @author  Aaron M Jones <aaron@jonesiscoding.com>
 * @version Pleasing v1.3.0 (https://github.com/exactquery/pleasing)
 * @license MIT (https://github.com/exactquery/pleasing/blob/master/LICENSE)
 *
 * @package XQ\Pleasing
 */
trait PleasingRenderTrait
{
  /** @var array An array of extension => max_age_integer */
  protected $cacheAge = array();
  /** @var array The mime types to use for specific extensions */
  protected $mimeType = array('js' => 'text/javascript', 'css' => 'text/css');

  /**
   * @return Pleasing
   */
  abstract public function Pleasing();

  /**
   * @param array $options  An array of unresolved options.
   *
   * @return array          The array of resolved options.
   * @throws \Exception     If a required option is not set.
   */
  public function resolveRenderOptions( $options )
  {
    // Deal with the options REQUIRED to find the OPTIONAL options
    $requiredOptions = array('hash','web_path');
    foreach ( $requiredOptions as $required )
    {
      if ( !isset( $options[ $required ] ) )
      {
        throw new \Exception( 'The option "' . $required . '" was not supplied to ' . __CLASS__ . '::' . __METHOD__ . '.' );
      }
    }

    // Deal with the OPTIONAL options
    if ( !isset( $options[ 'filename' ] ) ) { $options[ 'filename' ] = pathinfo( $options[ 'web_path' ], PATHINFO_BASENAME ); }
    if ( !isset( $options[ 'ext' ] ) ) { $options[ 'ext' ] = pathinfo( $options[ 'web_path' ], PATHINFO_BASENAME ); }
    if ( !isset( $options[ 'type' ] ) ) { $options[ 'type' ] = end( explode( '.', $options[ 'ext' ] ) ); }
    if ( !isset( $options[ 'cache_path' ] ) )
    {
      $this->Pleasing()->getPleasingPath( $options[ 'hash' ], $options[ 'type' ] . "/" . $options[ 'filename' ] . "." . $options[ 'ext' ] );
    }

    // These options cannot be supplied, but are set here for convenience.
    $options['modified'] = filemtime( $options['cache_path'] );
    $options['etag'] = md5( $options['hash'] . "_" . $options['modified'] );

    // Deal with the last two REQUIRED options
    if ( !isset( $options[ 'cache_age' ] ) ) {
      if ( !$options[ 'cache_age' ] = $this->getCacheAge( $options[ 'ext' ] ) )
      {
        throw new \Exception('The option "cacheage" was not supplied to ' . __CLASS__ . '::' . __METHOD__ . ' and could not be determined from the extension.' );
      }
    }
    if ( !isset( $options[ 'mime_type' ] ) ) {
      if ( !$options[ 'mime_type' ] = $this->getMimeType( $options[ 'ext' ] ) )
      {
        throw new \Exception('The option "mime_type" was not supplied to ' . __CLASS__ . '::' . __METHOD__ . ' and could not be determined from the extension.' );
      }
    }

    return $options;
  }

  /**
   * Sets the max age for files with a specific extension.
   *
   * @param string  $ext
   * @param int     $value
   *
   * @return $this
   */
  public function setCacheAge( $ext, $value = 0 )
  {
    $this->cacheAge[$ext] = $value;

    return $this;
  }

  /**
   * Gets the proper mime type for a given file extension.
   *
   * @param string $ext
   *
   * @return string|null
   */
  public function getMimeType( $ext )
  {
    return (isset($this->mimeType[$ext])) ? $this->mimeType[$ext] : null;
  }

  /**
   * Returns the specified max-age for for a given file extension.
   * @param string $ext
   *
   * @return int
   */
  public function getCacheAge( $ext )
  {
    return (isset($this->cacheAge[$ext])) ? $this->cacheAge[$ext] : 0;
  }

  /**
   * Set the proper headers and returns either the contents of the requested CSS/JS file, or a Symfony Response object
   * (if you are using Symfony or it's components)
   *
   * @param  array $options An array of unresolved options.
   *
   * @return mixed|string   Returns a string, unless using Symfony, then returns a Response object.
   */
  public function render( $options )
  {
    $options = $this->resolveRenderOptions( $options );

    if ( !is_callable(array($this,'renderUsingSymfony')))
    {
      header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $options[ 'modified' ] ) . ' GMT' );
      // ETags for Modern Browsers & to allow for 304's
      header( "Etag: " . $options[ 'etag' ] );
      // Set cache validation policy
      header( 'Cache-Control: public; max-age '.$options['cache_age'] );

      // Determine if not modified
      if (@strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $options['modified'] || trim($_SERVER['HTTP_IF_NONE_MATCH']) == $options['etag'])
      {
        header( "HTTP/1.1 304 Not Modified" );
        return '';
      }

      // Finish Response
      header( 'Content-Type: ', $options[ 'mime_type' ] );
      header( 'Content-Disposition: inline; filename="' . $options[ 'filename' ] . '.' . $options[ 'ext' ] . '"' );

      return file_get_contents( $options[ 'cache_path' ] );
    }
    else
    {
      return $this->renderUsingSymfony( $options );
    }
  }
}