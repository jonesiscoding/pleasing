<?php
/**
 * PleasingMinifyFilter.php
 */

namespace XQ\Pleasing\Assetic\Filter;

use Assetic\Asset\AssetInterface;
use Assetic\Filter\FilterInterface;
use JShrink\Minifier;

/**
 * Assetic filter to minify JS and CSS files.  CSS files are minified internally.  For JS files, JShrink is used.
 *
 * Class PleasingMinifyFilter
 *
 * @author  Aaron M Jones <aaron@jonesiscoding.com>
 * @version Pleasing Filters v1.2.7 (https://github.com/exactquery/pleasing-filters)
 * @license MIT (https://github.com/exactquery/pleasing-filters/blob/master/LICENSE)
 *
 * @package XQ\Pleasing\Assetic\Filter
 */
class PleasingMinifyFilter implements FilterInterface
{

  private $comments = null;

  public function filterLoad(AssetInterface $asset)
  {
  }

  public function filterDump(AssetInterface $asset)
  {
    $assetPath = $asset->getSourcePath();
    $ext = ($assetPath) ? pathinfo($assetPath, PATHINFO_EXTENSION) : false;

    $content = $asset->getContent();

    switch ($ext)
    {
      case "css":
      case "less":
        $output = $this->minifyCSS($content);
        break;
      case "js":
        $output = $this->minifyJS($content);
        break;
      default:
        // Extension not recognized, or could not be read.  Leave content alone
        $output = $content;
        break;
    }

    $asset->setContent($output);
  }

  /**
   * Minify JS using JShrink::Minifier.
   *
   * @param   string         $input  The JS code to minify.
   *
   * @return  bool|string            The minified JS code, or false if an error occurs.
   * @throws  \Exception             If the JS code cannot be minified, an Exception is thrown
   */
  private function minifyJS($input)
  {
    $input = $this->removeComments( $input );
    return Minifier::minify($input);
  }

  /**
   * Removes comments from a multi line string while preserving any comments that mention licenses, authors, copyrights
   * or the source URL of the code.  This ensures that minification does not break most open source licenses which
   * require attribution.
   *
   * @param  string  $input
   * @param  bool   $proxy
   *
   * @return string
   */
  private function removeComments( $input, $proxy = false )
  {
    if ( preg_match_all("/\/\*([^*]|[\r\n]|(\*+([^*\/]|[\r\n])))*\*+\//", $input, $comments) )
    {
      foreach ( $comments[ 0 ] as $comment )
      {
        $lines = explode(PHP_EOL, $comment);
        $newComment = implode(PHP_EOL,preg_grep("/copyright|license|author|preserve|credit|http|\/\*\!/i",$lines));

        if($newComment)
        {
          // Add proper comment encapsulation
          if ( count( $lines ) > 1 )
          {
            $newComment = "/*!" . PHP_EOL . $newComment . PHP_EOL . " */";
          }
          else
          {
            $newComment = str_replace( "/* ", "/*! ", $newComment );
          }

          if($proxy) { $this->comments[] = $newComment; }

          $input = str_replace( $comment, $newComment, $input );

        }
        else
        {
          $input = str_replace( $comment, "", $input );
        }
      }

    }

    return $input;
  }

  private function stashComments( $input )
  {
    $this->comments = array();
    $input = $this->removeComments( $input, true );
    foreach ( $this->comments as $key => $value )
    {
      $input = str_replace( $value, "||" . $key . "||", $input );
    }

    return $input;

  }

  private function retrieveComments( $input )
  {
    if ( !empty( $this->comments) && is_array( $this->comments ) )
    {
      foreach ( $this->comments as $key => $value )
      {
        $input = str_replace( "||" . $key . "||", $value . PHP_EOL, $input );
      }
    }

    $input = str_replace( "}/*", "}" . PHP_EOL . "/*", $input );

    return $input;
  }

  /**
   * Minify CSS by removing unneeded code.
   *
   * Regex references from:
   *    http://stackoverflow.com/questions/15195750/minify-compress-css-with-regex
   *    http://stackoverflow.com/questions/2167793/how-to-convert-all-color-code-xxyyzz-to-shorter-3-character-version-xyz-of-who
   *
   * @param  string $input  The string of CSS code to minify
   *
   * @return string         The minified CSS code.
   */
  private function minifyCSS($input)
  {
    $keepComments = null;
    $output = $this->stashComments( $input );

    // Remove Comments, preserving comments marked as important
    $output = preg_replace('!/\*[^*\!]*\*+([^/][^*]*\*+)*/!', '', $output);

    $output = $this->minifySpaceCSS($output);

    $output = $this->shortenHex($output);

    // Replace common units after 0's - they aren't needed
    $output = preg_replace('/((?<!\\\\)\:|\s)\-?0(?:em|cm|mm|in|px|pt|%)/iS', '${1}0', $output);

    // Extra whitespace
    $output = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $output);

    // Add line breaks around remaining comments
    $output = $this->retrieveComments( $output );

    return $output;
  }

  /**
   * Shorthand hex color codes, for example #ffffff = #fff, or #112233 = #123
   *
   * Intended for use with CSS only.
   *
   * @param  string $input The CSS string in which to shorten hex codes.
   * @return string        The modified CSS code.
   */
  private function shortenHex($input)
  {
    $output = preg_replace('/(?<![\'"])#([a-z0-9])\\1([a-z0-9])\\2([a-z0-9])\\3(?![\'"])/i', '#$1$2$3', $input);
    return $output;
  }

  /**
   * Remove space from CSS files, including whitespaces around !important, parentheses, brackets, colons, and semicolons
   * (that are followed by a closing bracket).
   *
   * @param  string  $input  The CSS from which to remove spacing.
   *
   * @return string          The modified CSS.
   */
  private function minifySpaceCSS($input)
  {
    // Whitespace around !important;
    $output = preg_replace('/\s*([\*$~^|]?+=|[{};,>~]|!important\b)\s*/', '$1', $input);
    // Whitespace around parenthesis and brackets.
    $output = preg_replace('/\s+([\]\)])/', '$1', $output);
    // Whitespace around :, except in selectors
    $output = preg_replace('/([\[(:])\s+/', '$1', $output);
    $output = preg_replace('/\s+(:)(?![^\}]*\{)/', '$1', $output);
    // Whitespace & semicolon followed by closing bracket
    $output = preg_replace('/;}/', '}', $output);

    return $output;
  }

}