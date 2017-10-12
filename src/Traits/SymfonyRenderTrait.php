<?php
/**
 * SymfonyRenderTrait.php
 */

namespace XQ\Pleasing\Traits;

use Symfony\Component\HttpFoundation as Http;

/**
 * Uses Symfony 2/3 to render a response object for a CSS or JS file.
 *
 * Class SymfonyRenderTrait
 *
 * @author  Aaron M Jones <aaron@jonesiscoding.com>
 * @version Pleasing v2.1.2 (https://github.com/exactquery/pleasing)
 * @license MIT (https://github.com/exactquery/pleasing/blob/master/LICENSE)
 *
 * @package XQ\Pleasing
 */
trait SymfonyRenderTrait
{
  use PleasingRenderTrait;

  /**
   * @param array $options
   *
   * @return Http\Response
   */
  public function renderUsingSymfony( $options )
  {

    $Response = new Http\Response();
    $Response->headers->set( 'Last-Modified', gmdate( 'D, d M Y H:i:s', $options[ 'modified' ] ) . ' GMT' );

    // ETags for Modern Browsers & to allow for 304's
    $Response->setEtag( $options['etag'] );
    // Set cache validation policy
    $Response->setPublic();
    $Response->setMaxAge($options['cache_age']);
    if ( $Response->isNotModified( $options['request'] ) ) { return $Response; }

    // Finish Response
    $Response->headers->set('Content-Type', $options['mime_type']);
    $disposition = $Response->headers->makeDisposition( Http\ResponseHeaderBag::DISPOSITION_INLINE, $options[ 'filename' ] . '.' . $options[ 'ext']);
    $Response->headers->set('Content-Disposition', $disposition);
    $Response->setContent( file_get_contents( $options['cache_path'] ) );

    return $Response;
  }
}