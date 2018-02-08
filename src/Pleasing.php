<?php
/**
 * Pleasing.php
 */

namespace XQ\Pleasing;

use Assetic\Filter\FilterInterface;
use Assetic\Asset\FileAsset;
use Assetic\Asset\AssetCollection;

/**
 * Class Pleasing
 *
 * @author  Aaron M Jones <aaron@jonesiscoding.com>
 * @version Pleasing v2.1.3 (https://github.com/exactquery/pleasing)
 * @license MIT (https://github.com/exactquery/pleasing/blob/master/LICENSE)
 *
 * @package XQ\Pleasing
 */
class Pleasing
{
  /** @var string                   The application environment. */
  protected $env;
  /** @var array                    An array of important paths */
  protected $_paths;
  /** @var  PleasingFilterFactory  An instance of this object, instantiated by Pleasing */
  protected $_FilterFactory;
  /** @var  array                   An array of configuration values */
  protected $_config = array();
  /** @var  array                   An array of configured assets */
  protected $_assets = array();
  /** @var  array                   An array of configured FilterInterface objects  */
  protected $_filters = array();
  /** @var  bool                    A flag indicating whether the class is destructing */
  protected $_shutdown = false;
  /** @var  array                   The minimum paths required to operate */
  protected $requiredPaths = array('%kernel.cache_dir%', '%kernel.root_dir');
  /** @var array                    The cached list of assets managed by this Pleasing instance. */
  public $assetList = array();
  /** @var array                    Cached values indicating whether an asset has been updated. */
  private $isUpdated = array();
  /** @var array                    Cached list of the children of each asset file managed by this Pleasing instance */
  private $assetChildren = array();

  // region //////////////////////////////////////////////// Init & Alias Methods

  /**
   * Pleasing constructor.
   *
   * @param array|string  $config
   * @param string        $env
   * @param array         $paths
   */
  public function __construct( $config, $env = 'dev', $paths = array() )
  {
    // Set the Environment
    $this->env = $env;
    // Set the Config Array (or Path)
    $this->_config = $config;
    // Set the Paths
    $this->_paths = $paths;

    if ( $this->env != "prod" )
    {
      $dataFile =  $this->getCachePath() . "pleasing/cache.json";
      if ( file_exists( $dataFile ) )
      {
        $dataContents = json_decode(file_get_contents( $dataFile ),true);
        $this->assetList = $dataContents[ 'assetList' ];
        $this->assetChildren = $dataContents[ 'assetChildren' ];
      }
    }
  }

  public function __destruct()
  {
    $this->_shutdown = true;
    $this->save();
  }

  public function save()
  {
    if ( $this->env == "dev" )
    {
      $dataPath = $this->getCachePath() . "pleasing" . DIRECTORY_SEPARATOR;
      $dataFilePath = $dataPath."cache.json";
      $dataContents = json_encode(
        array(
          'assetList' => $this->assetList,
          'assetChildren' => $this->assetChildren
        )
      );

      if ( file_exists( $dataFilePath ) ) {
        if ( $this->_shutdown )
        {
          @unlink( $dataFilePath );
        }
        else
        {
          unlink( $dataFilePath );
        }
      }

      if (!file_exists($dataPath)) { mkdir($dataPath, 0777, true);}

      if ( $this->_shutdown )
      {
        @file_put_contents( $dataFilePath, $dataContents, 0777 );
      }
      else
      {
        file_put_contents( $dataFilePath, $dataContents, 0777 );
      }
    }

    return $this;
  }

  /**
   * @return PleasingFilterFactory
   */
  public function FilterFactory()
  {
    if ( !$this->_FilterFactory )
    {
      $this->_FilterFactory = new PleasingFilterFactory( $this->_paths );
    }

    return $this->_FilterFactory;
  }

  // endregion ///////////////////////////////////////////// End Init & Alias Methods

  // region //////////////////////////////////////////////// Path Methods

  public function getCachePath($trailing = true)
  {
    $paths = $this->getPaths();
    return ( $trailing ) ? $paths[ '%kernel.cache_dir%' ] . DIRECTORY_SEPARATOR : $paths[ '%kernel.cache_dir%' ];
  }

  public function getRootPath($trailing = true)
  {
    $paths = $this->getPaths();
    if ( !isset( $paths[ '%app.root_dir%' ] ) )
    {
      $paths['%app.root_dir%'] = realpath($paths[ '%kernel.root_dir%' ] . DIRECTORY_SEPARATOR . '..');
    }

    return ( $trailing ) ? $paths[ '%app.root_dir%' ] . DIRECTORY_SEPARATOR : $paths[ '%app.root_dir%' ];
  }

  public function getWebPath($trailing = true)
  {
    return ($trailing) ? $this->getRootPath(). 'web' . DIRECTORY_SEPARATOR : $this->getRootPath() . 'web' ;
  }

  /**
   * @return array        The array of paths needed by
   * @throws \Exception   If one of the required paths is not set.
   */
  public function getPaths()
  {
    if ( empty( $this->_paths ) )
    {
      $this->_paths = $this->getConfig( 'paths' );
      foreach ( $this->requiredPaths as $requiredPath )
      {
        if ( !isset( $this->_paths[ $requiredPath ] ) )
        {
          throw new \Exception( 'The path "' . $requiredPath . '" must be set in the provided configuration.' );
        }
      }
    }

    return $this->_paths;
  }

  // endregion ///////////////////////////////////////////// End Path Methods


  // region //////////////////////////////////////////////// Main Public Methods

  /**
   * Changes a relative path to the appropriate production mode path for the same asset, assuming that the asset
   * will be minified.
   *
   * @param  string $output   The relative path to the asset.
   *
   * @return string           The production mode URL for the asset.
   */
  public function getProdURL($output)
  {
    $ext = pathinfo( $output, PATHINFO_EXTENSION );
    $output = str_replace( "." . $ext, ".min." . $ext, $output );
    $cachePath = $this->getWebPath() . $output;
    $cacheTime = "";

    if ( file_exists( $cachePath ) )
    {
      // Add Query String, return URL
      $cacheTime = filemtime( $cachePath );
    }

    return "/".$output . "?v=" . $cacheTime;
  }

  /**
   * Adds an asset file to the object for later retrieval.
   *
   * @param string $output    The theoretical relative URL to the output file.
   * @param array  $inputs    An array of named inputs.
   * @param bool   $resolve   Whether or not to resolve the named inputs into a list of absolute file paths.
   *
   * @return string           The hash for the new asset.
   */
  public function addAsset( $output, $inputs, $resolve = true )
  {
    $hash = $this->getHash( $output, $inputs );
    if ( $resolve )
    {
      $config = $this->resolveConfig( $inputs );
      $config[ 'cached' ] = $this->getPleasingPath( $hash, $output );
      $config[ 'output' ] = $output;
      $this->_assets[$hash] = $config;
    }
    else
    {
      $this->_assets[$hash] = array(
        'output' => $output,
        'inputs' => $inputs,
        'cached' => $this->getPleasingPath($hash,$output),
      );
    }

    return $hash;
  }

  /**
   * Generates the proper URL to insert into the HTML markup for the given asset file.
   *
   * @param string $output The relative path to the output file.
   * @param array  $inputs An array of named inputs.
   *
   * @return string        The proper URL to use for this asset, as is appropriate for production or dev mode.
   * @throws \Exception    If an unknown asset type is given.
   */
  public function getURL( $output, $inputs )
  {
    if ($this->env== "prod") {
      return $this->getProdURL($output);
    }

    $hash = $this->addAsset( $output, $inputs );

    if (!$this->isCached($hash)) {

      $ext = pathinfo( $output, PATHINFO_EXTENSION );
      switch ( $ext )
      {
        case "js":
          $this->cacheJSAsset( $hash );
          break;
        case "css":
          $this->cacheCSSAsset( $hash );
          break;
        default:
          throw new \Exception( "Unknown Asset Type '" . $ext . "'' given." );
      }
    }

    $assetURL = str_replace($this->getCachePath()."pleasing","/app_dev.php/pleasing",$this->_assets[$hash]['cached']);
    $assetURL .= "?v=" . filemtime($this->_assets[$hash]['cached']);

    return $assetURL;
  }

  // endregion ///////////////////////////////////////////// End Main Public Methods

  // region //////////////////////////////////////////////// Data Generation Methods

  /**
   * Generates a unique hash for an asset based on the inputs and output.
   *
   * @param string $output   The output URL (relative) for the asset.
   * @param array  $inputs   The array of inputs of for the asset.
   *
   * @return string          The unique hash.
   */
  public function getHash( $output, $inputs )
  {
    return md5(json_encode(array($output,$inputs)));
  }

  /**
   * Gets the filesystem path to the dev-mode cached file for the given asset.
   *
   * @param string $hash    The unique hash for the asset, as generated by the getHash method.
   * @param string $output  The relative URL for the asset.
   *
   * @return string         The absolute path to the location of the cached asset.
   */
  public function getPleasingPath($hash,$output)
  {
    $ext = pathinfo( $output, PATHINFO_EXTENSION );
    $output = str_replace( "." . $ext, "-" . $hash . "." . $ext, $output );

    return $this->getCachePath() . "pleasing/" . $output;
  }

  /**
   * Generates the production URL for an asset, assuming the asset is minified and named accordingly.  This method does
   * not verify that the file exists, it only provides the web-based path that the file should exist at.
   *
   * @param  string $output The configured relative URL for the asset.
   *
   * @return string         The resulting relative URL for the production asset.
   */
  public function getProdPath( $output )
  {
    $ext = pathinfo( $output, PATHINFO_EXTENSION );
    $output = str_replace( "." . $ext, ".min." . $ext, $output );

    return $this->getWebPath() . $output;
  }

  /**
   * Retrieves the pleasing configuration from the main application's config.yml.
   *
   * @param null|string $key   If desired, the specific configuration key to retrieve.
   *
   * @return array|null        The configuration array.
   */
  public function getConfig($key = null)
  {
    if (empty($this->_config) || !is_array($this->_config)) {
      // Read Config File
      $this->_config = $this->getYaml( $this->_config );
    }

    if ( $key )
    {
      return ( array_key_exists( $key, $this->_config[ 'pleasing' ] ) ) ? $this->_config[ 'pleasing' ][ $key ] : null;
    }
    else
    {
      return $this->_config;
    }
  }

  /**
   * Parses a YAML file using built in Symfony 2.x tools.
   *
   * @param  string         $file   The absolute path to the file.
   *
   * @return array                  The parsed YAML
   * @throws \Exception             If the YAML file does not exist, a proper parser cannot be found, or if there are
   *                                errors in the provided YAML file.
   */
  public function getYaml($file)
  {
    if ( $contents = file_get_contents($file) )
    {
      if ( function_exists( 'yaml_parse' ) )
      {
        $value = yaml_parse($contents);
      }
      elseif ( class_exists( 'sfYaml' ) )
      {
        /** @noinspection PhpUndefinedClassInspection */
        $value = sfYaml::load( $contents );
      }
      elseif ( class_exists( "\\Symfony\\Component\\Yaml\\Parser" ) )
      {
        /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
        $yaml = new \Symfony\Component\Yaml\Parser();
        /** @noinspection PhpUndefinedMethodInspection */
        $value = $yaml->parse( $contents );
      }
      else
      {
        throw new \Exception( "No YAML parser could be found.  Pleasing can use PECL's YAML extension, Symfony 1.x sfYaml, or Symfony 2.x Parser." );
      }
    }
    else
    {
      throw new \Exception( "The YAML file " . $file . " does not seem to exist." );
    }

    return $value;
  }

  // endregion ///////////////////////////////////////////// End Data Generation Methods

  // region //////////////////////////////////////////////// Caching Methods

  /**
   * Caches a JS asset for use in dev mode by combining the input files into a single file.
   *
   * @param  string $hash The unique hash for this JS asset.
   *
   * @return $this
   */
  public function cacheJSAsset($hash)
  {
    $assetBundle = $this->_assets[$hash];
    foreach ($assetBundle['inputs'] as $input) {
      $js[] = file_get_contents($input);
    }

    if (isset($js) && !empty($js)) {
      $assetCode = implode(PHP_EOL, $js);

      $cacheDir = pathinfo($assetBundle['cached'], PATHINFO_DIRNAME);
      if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0777, true);
      }
      file_put_contents( $assetBundle['cached'],$assetCode );
    }

    return $this;
  }

  /**
   * Compiles a LESS/SCSS file into CSS, and saves it to the Symfony cache.  The asset must be previously added to
   * this Pleasing instance via the addAsset method.
   *
   * @param   string      $hash   The unique hash for this CSS asset.
   *
   * @throws  \Exception          If the cache folder cannot be created, or the file is not valid SCSS/LESS.
   * @return $this
   */
  public function cacheCSSAsset($hash)
  {
    $assetBundle = $this->_assets[$hash];
    foreach ($assetBundle['inputs'] as $input) {
      $filters = (isset($assetBundle['filters'])) ? $assetBundle['filters'] : array();
      $collection[] = $this->buildFileAsset( $input, $filters );
    }

    if (isset($collection) && !empty($collection)) {
      $AssetCollection = $this->buildAssetCollection($collection, $assetBundle['output'],false);

      if ( $assetCode = $AssetCollection->dump() )
      {
        $cacheDir = pathinfo($assetBundle['cached'], PATHINFO_DIRNAME);
        if (!file_exists($cacheDir)) {
          mkdir($cacheDir, 0777, true);
        }
        file_put_contents( $assetBundle['cached'], $assetCode );
      }
    }

    return $this;
  }

  /**
   * Determines if the given asset is cached and up to date.
   *
   * @param  string $hash The unique hash for the asset, as determined by the getHash method.
   *
   * @return bool         TRUE if the asset is cached and up to date.  FALSE if not.
   */
  public function isCached($hash)
  {
    $assetBundle = $this->_assets[$hash];
    $cachePath = $assetBundle['cached'];
    if (!file_exists($cachePath)) {
      return false;
    } else {
      $cacheModified = filemtime($cachePath);
      $resolvedInputs = $assetBundle['inputs'];

      foreach ($resolvedInputs as $asset) {

        $ext = pathinfo($asset, PATHINFO_EXTENSION);
        $this->assetList[$asset] = filemtime($asset);

        // If this input is newer than the cache file
        if ($this->assetList[$asset] > $cacheModified) {
          return false;
        } elseif ($ext == "less" || $ext == "sass" || $ext == 'scss') {
          // With LESS/SASS files, we can have imports, so we need to check deeper.
          $getDeep[] = $asset;
        }
      }

      if ( isset( $getDeep ) )
      {
        foreach ( $getDeep as $asset )
        {
          if ($this->areImportedFilesUpdated($asset, $cacheModified)) {
            return false;
          }
        }
      }

      return true;
    }
  }

  // endregion ///////////////////////////////////////////// End Cache Management Methods

  // region //////////////////////////////////////////////// Assetic Methods

  /**
   * Builds the FileAsset object from Assetic, including any passed filters, as well as any filters
   * configured for this file type.
   *
   * @param string  $input        The fully resolved path to the asset input file.
   * @param array   $addFilters   An array of filter names to add to any filters configured for this file type.
   *
   * @return FileAsset            The Assetic FileAsset object.
   */
  public function buildFileAsset($input, $addFilters = array())
  {
    return new FileAsset( $input, $this->getFiltersForFile( $input, $addFilters ) );
  }

  /**
   * Builds an Assetic AssetCollection object from an array of FileAsset objects, including the appropriate filter for
   * minification if indicated.
   *
   * @param FileAsset[] $FileAssets   An array of FileAsset objects to place in the asset collection.
   * @param bool        $output       The output filename.  Relative path is optional.
   * @param array       $addFilters   An array of filter names to add to any filters configured for this file type.
   *
   * @return AssetCollection          The Assetic AssetCollection object.
   */
  public function buildAssetCollection( $FileAssets, $output, $addFilters = array() )
  {
    // BC Arguments -- will be removed in 3.0
    if( is_bool( $output ) )
    {
      $filters[] = $this->getFilter( 'pleasing_minify' );
    }
    else
    {
      $filters = $this->getFiltersForFile( $output, $addFilters );
    }

    if ( !is_array( $FileAssets ) )
    {
      $FileAssets = array( $FileAssets );
    }

    return new AssetCollection( $FileAssets, $filters );
  }

  /**
   * Gets the appropriate filters configured for the file type of the given file.  Also adds any additional filters
   * given by name in the $addFilters parameter.
   *
   * @param string $file        The file name.  Path is optional.
   * @param array  $addFilters  An array of filter names to add to any filters configured for this file type.
   *
   * @return array
   */
  private function getFiltersForFile( $file, $addFilters = array() )
  {
    $filters = array();

    $addFilters = array_unique(array_merge( $addFilters, $this->getFilterNames( $file ) ));
    // Build Filters
    foreach ( $addFilters as $addFilter )
    {
      $filters[] = $this->getFilter( $addFilter );
    }

    return $filters;
  }

  /**
   * Gets the name of any filters configured to match the given filename, typically by extension.
   *
   * @param string $filename  The file name.  Path is optional.
   *
   * @return array
   */
  private function getFilterNames( $filename )
  {
    $addFilters = array();
    $filtersConfig = $this->getConfig( 'filters' );

    // Add Filters By Extension
    if( !empty( $filtersConfig ) )
    {
      foreach( $filtersConfig as $filterName => $filterConfig )
      {
        $applyTo = ( isset( $filterConfig[ 'apply_to' ] ) ) ? $filterConfig[ 'apply_to' ] : null;
        if( $applyTo && preg_match( '#' . $applyTo . '#i', $filename ) )
        {
          $addFilters[] = $filterName;
        }
      }
    }

    return $addFilters;
  }

  /**
   * Retrieves or creates a filter object based on the given name.  Named filters must be configured via the pleasing
   * -> filters key in the application's main config.yml.
   *
   * @param  string $filterName   The name of the filter object to create.
   *
   * @return FilterInterface
   */
  private function getFilter( $filterName )
  {
    if ( !isset( $this->_filters[ $filterName ] ) )
    {
      $config = $this->getConfig();
      $filterConfig = (isset( $config[ 'pleasing' ][ 'filters' ][ $filterName ] )) ? $config[ 'pleasing' ][ 'filters' ][ $filterName ] : null;
      if($filterConfig)
      {
        $this->_filters[$filterName] = $this->FilterFactory()->build( $filterConfig );
      }
    }

    return $this->_filters[$filterName];
  }

  // endregion ///////////////////////////////////////////// End Assetic Methods

  // region //////////////////////////////////////////////// Other Methods

  /**
   * Retrieves and resolves the configuration of an array of named assets.
   *
   * @param  array $inputs An array of named assets.
   *
   * @return array         The configuration array, with all %foo% and @bar variables resolved and any relative paths
   *                       resolved to absolute.
   */
  public function resolveConfig($inputs)
  {
    $resolved = array();
    $config = $this->getConfig();
    foreach($inputs as $input) {
      $input = str_replace("@", "", $input);

      $asset = (isset($config['pleasing']['assets'][$input])) ? $config['pleasing']['assets'][$input] : array();

      if (!empty($asset)) {
        foreach ( $asset as $key => $value )
        {
          if ( $key == 'inputs' )
          {
            $value = (is_array($value)) ? $value : array($value);
            foreach ( $value as $k => $v )
            {
              $resolvedInputs[] = $this->FilterFactory()->resolveConfigValue( $v );
            }
          }
          else
          {
            if ( is_array( $value ) )
            {
              foreach ( $value as $k => $v )
              {
                $resolved[ $key ][ $k ] = $this->FilterFactory()->resolveConfigValue( $v );
              }
            }
            else
            {
              $resolved[ $key ] = $this->FilterFactory()->resolveConfigValue( $value );
            }
          }
        }
      }
    }
    if ( isset( $resolvedInputs ) )
    {
      $resolved[ 'inputs' ] = $resolvedInputs;
    }
    return $resolved;
  }

  public function areImportedFilesUpdated($assetPath,$cacheTime = null)
  {
    // Short circuit return if we've already checked this file on this run.
    if ( !isset( $this->isUpdated[$assetPath] ) )
    {
      if(array_key_exists($assetPath,$this->assetList))
      {
        $mTime = filemtime( $assetPath );
        // Should we trust the cached children?
        if ( $mTime != $this->assetList[ $assetPath ] )
        {
          unset($this->assetChildren[$assetPath]);
          // Update the time since we have it
          $this->assetList[ $assetPath ] = $mTime;
          // If this file's modified time is greater than the compiled file's modified time, just return
          if($cacheTime && $mTime > $cacheTime) {
            $isUpdated = true;
          }
        }
      }
      else
      {
        $this->assetList[ $assetPath ] = filemtime( $assetPath );
      }

      if ( $cacheTime && ($this->assetList[ $assetPath ] > $cacheTime )) { $isUpdated = true; }

      if ( !isset( $isUpdated ) )
      {
        // If we don't already have children, get them.
        if ( !array_key_exists( $assetPath, $this->assetChildren ) )
        {
          $this->assetChildren[ $assetPath ] = $this->parseFileForCssImports( $assetPath );
        }

        foreach ( $this->assetChildren[ $assetPath ] as $childPath )
        {
          if($isUpdated = $this->areImportedFilesUpdated( $childPath, $cacheTime )) {
            break;
          }
        }
      }

      $this->isUpdated[$assetPath] = (isset($isUpdated)) ? $isUpdated : false;
    }

    return $this->isUpdated[$assetPath];
  }

  private function parseFileForCssImports( $filePath )
  {
    $children = array();
    // Determine Paths
    $importPath = pathinfo( $filePath, PATHINFO_DIRNAME );
    $parentExt = pathinfo( $filePath, PATHINFO_EXTENSION );

    // Get Contents & Parse
    $lines = explode(PHP_EOL, file_get_contents($filePath));
    foreach ( $lines as $line )
    {
      // Exclude things that are commented out.
      if ( substr( $line, 0, 2 ) != "//" )
      {
        if ( preg_match( '/@import\s*(url|\(reference\)|\(inline\))?\s*\(?([^;]+?)\)?;/', $line, $matches ) )
        {
          $tryPaths = array();
          // Exclude Remote Imports
          if ( $matches[ 1 ] != "url" )
          {
            // .LESS/.SCSS extensions are optional in their syntax, but not when we try to find the file.
            $childPath = trim( str_replace( array( '"', "'" ), "", $matches[ 2 ] ) );

            $tryPaths[] = $importPath . DIRECTORY_SEPARATOR . $childPath;
            $tryPaths[] = $importPath . DIRECTORY_SEPARATOR . $childPath . '.' . $parentExt;
            if ( $parentExt == 'scss' )
            {
              $parts = explode( DIRECTORY_SEPARATOR, $childPath );
              $oldFileName = end( $parts );
              $newFileName = '_' . $oldFileName . '.' . $parentExt;
              $tryPaths[] = $importPath . DIRECTORY_SEPARATOR . str_replace( $oldFileName, $newFileName, $childPath );
            }

            foreach ( $tryPaths as $tryPath )
            {
              if ( $realTryPath = realpath( $tryPath ) )
              {
                if ( !in_array( $realTryPath, $children ) )
                {
                  $children[] = $realTryPath;
                }
                break;
              }
            }
          }
        }
      }
    }

    return $children;
  }

  // endregion ///////////////////////////////////////////// End Other Methods

}