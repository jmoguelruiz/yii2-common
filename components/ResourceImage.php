<?php

namespace jmoguelruiz\yii2\common\components;

use Imagine\Image\Box;
use Imagine\Image\ManipulatorInterface;
use Yii;
use yii\base\Component;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\imagine\Image;
use const YII_ENV;

/**
 * Expects:
 * 
 * * Habilitar componente en diferentes entornos. - YA
 *   * Manejar tres entornos predefinidos. - YA
 *      * dev, test, prod. - YA
 *          * Si el usuario tiene mas enviroments modificar el ResourceImage local 
 *            para agregar las propiedades de cada enviroment faltantes. - YA
 *   * Poder cambiar base paths de cada entorno. - YA
 *   * Donde se guardarán las imagenes. (local o s3)
 * * Manejar tipos de recursos. - YA
 *   * avatar, galeria, logo - YA
 * * Guardar imagen en el servidor local. - YA
 * * Guardar imagen en el servidor de amazon s3.
 * * Manejo de directorios temporales avatar_temp. - YA
 * * Manejo de directorios finales avatar. - YA
 * * Generar nombre para guardar el archivo. - YA
 * * Procesar imagen antes de guardar, crop , resize etc. - YA
 * * Guardar diferentes tamaños del archivo, original, thumb, etc. - YA
 * * Obtener url del directorio temporal - YA
 * * Obtener url del directorio final. - YA
 * * Habilitar base_path donde se guardaran las imagenes. - YA
 * * Guardar imagen desde una url.
 * * Subir la imagen a un directorio temporal para procesarla despues. - YA
 * * Renombrar un archivo. 
 * * Eliminar un archivo. 
 * * Optimización de imagenes al subir.
 * 
 */
class ResourceImage extends Component
{
    /**
     * Enviroments
     */
    const ENVIROMENT_DEV = 'dev';
    const ENVIROMENT_TEST = 'test';
    const ENVIROMENT_PROD = 'prod';

    /**
     * Servers to save final images.
     */
    const SERVER_LOCAL = 'local';
    const SERVER_S3 = 's3';

    /**
     * Sizes to images.
     */
    const SIZE_ORIGINAL = 'original';
    const SIZE_THUMB = 'thumb';

    /**
     * Base paths to configure in each enviroment.
     * Default: 
     * [
     *    'dev' => 'images/dev',
     *    'test' => 'images/test',
     *    'prod' => 'images'
     * ]
     * @var arr
     */
    public $basePaths = [];

    /**
     * Type of server, to save final images.
     * Default. SERVER_LOCAL.
     * @var string
     */
    public $serverType = self::SERVER_LOCAL;

    /**
     * Prefix temp.
     * @var type 
     */
    public $prefixTemp = 'temp';

    /**
     * Symbol to concatenate the time with the name.
     * @var string
     */
    public $symbolConcatTime = '_';

    /**
     * Container base path;
     * @var type 
     */
    public $containerBasePath = "@frontend/web";

    /**
     * Url to cdn.
     * @var string 
     */
    public $cdn;

    /**
     * Base configuration of url.
     * @var arr
     */
    private $baseConfigUrl;

    /**
     * Concat elements neccesarys to complete the url.
     * @var string 
     */
    private $stringUrl = [
        'root' => '',
        'basePath' => '',
        'resource' => '',
        'size' => '',
        'name' => ''
    ];

    /**
     * Src path to process.
     */
    private $srcTemp = null;
    
    /**
     * init
     */
    public function init()
    {
        parent::init();
        
        $this->configRoot();
        $this->configBasePath();
        $this->configResource();
        $this->configSize();
        $this->configName();

        $this->mergeBasePaths();
    }
    
    public function workWithSrc($src){
        $this->srcTemp = $src;
    }
    
    public function getSrcTemp(){
        $this->srcTemp;
    }

    /**
     * Upload a image to server local.
     * @param File $file File to upload.
     * @param string $targetPath Path to upload.
     */
    public function upload($file, $src = null)
    {
        $src = !empty($src) ? $src :  !empty($this->getSrcTemp()) ? $this->getSrcTemp() : $this->getstringUrl();
        
        try {
            $file->saveAs($src);
        } catch (Exception $ex) {
            throw new Exception($ex);
        }

        return $this;
    }

    /**
     * Save image.
     */
    public function save($dst, $src = null)
    {
        
        $src = !empty($src) ? $src :  !empty($this->getSrcTemp()) ? $this->getSrcTemp() : $this->getstringUrl();

        if ($this->serverType == self::SERVER_LOCAL) {
            copy($src, $dst);
        }

        /**
         * @todo: Implementar modo en s3.
         */
    }

    public function thumbnail($dst = null, $src = null, $options = [])
    {
        try {

            $src = !empty($src) ? $src : !empty($this->getSrcTemp()) ? $this->getSrcTemp() : $this->getstringUrl();
            $dst = !empty($dst) ? $dst : $src;
            
            $options = ArrayHelper::merge([
                        'width' => 200,
                        'height' => 200,
                        'ext' => ''
                            ], $options);

            Image::thumbnail($src, $options['width'], $options['height'], ManipulatorInterface::THUMBNAIL_INSET)
                    ->save($dst);
            
        } catch (Exception $exc) {
            throw new Exception($exc);
        }

        return $this;
    }

    /**
     * 
     * @param type $options
     */
    public function crop($dst = null, $src = null, $options = [])
    {

        $src = !empty($src) ? $src : !empty($this->getSrcTemp()) ? $this->getSrcTemp() : $this->getstringUrl();
        $dst = !empty($dst) ? $dst : $src;

        $options = ArrayHelper::merge([
                    'width' => 200,
                    'height' => 200,
                    'points' => [0, 0],
                    'box' => [200, 200]
                        ], $options);

        try {

        $points[0] = $points[0] < 0 ? 0 : $points[0];
        $points[1] = $points[1] < 0 ? 0 : $points[1];

            Image::crop($src, $options['width'], $options['height'], $options['points'])
                    ->resize(new Box($options['box'][0], $options['box'][1]))
                    ->save($dst);
        } catch (Exception $ex) {
            throw new Exception;
        }

        return $this;
    }

    public function createPath($options)
    {
        $this->baseConfigUrl = ArrayHelper::merge($this->baseConfigUrl, $options);

        $this->configRoot($this->baseConfigUrl['root']);
        $this->configBasePath($this->baseConfigUrl['basePath']);
        $this->configResource($this->baseConfigUrl['resource']);
        $this->configSize($this->baseConfigUrl['size']);
        $this->configName($this->baseConfigUrl['name']);
        
        return $this->getStringUrl();

    }

    /**
     * Delete file.
     * @param string $pathFile File to delete.
     */
    public function deleteFile($pathFile)
    {

        if (file_exists($pathFile)) {
            unlink($pathFile);
        }

        /**
         * @todo: implement in mode s3.
         */
    }

    /**
     * Get string without temp directory.
     */
    public function getStringWithoutTemp($string = null)
    {
        return str_replace('_temp', '', empty($string) ? $this->getStringUrl() : $string);
    }

    /**
     * Generate the name path.
     * 
     * @param string $name Name of the image.
     * @param arr $options Config to base path.
     * 
     * - No option yet.
     * 
     * Example:
     * [
     *  "concatTime" => false, If your wish concat time in the name of image, resolve problems with cache, if is True you. should pass a ext.
     *  "ext" => null
     * ]
     * @return $this
     */
    public function configName($options = [])
    {
        $this->baseConfigUrl['name'] = ArrayHelper::merge([
                    'title' => '',
                    'concatTime' => true,
                    'ext' => null
                        ], $options);

        if (empty($this->baseConfigUrl['name']['title'])) {
            $name = $this->getDefaultResource($this->getActiveResource());
        }

        if ($this->baseConfigUrl['name']['concatTime']) {
            $name = $this->baseConfigUrl['name']['title'] . $this->symbolConcatTime . time() . "." . $this->baseConfigUrl['name']['ext'];
        }

        $this->concatName($name);

        return $this->getStringUrl();
    }

    /**
     * 
     * Generate the size path.
     * 
     * @param string $size Size of the image, thumb, original.
     * @param arr $options Config to base path.
     * 
     * - No option yet.
     * 
     * Example:
     * [
     * ]
     * @return $this
     */
    public function configSize($options = [])
    {
        $this->baseConfigUrl['size'] = ArrayHelper::merge([
                    'type' => self::SIZE_ORIGINAL,
                        ], $options);

        $this->concatSize($this->baseConfigUrl['size']['type']);

        return $this->getStringUrl();
    }

    /**
     * 
     * Generate the resource path.
     * 
     * @param string $resource Path of resource of the image.
     * @param arr $options Config to base path.
     * 
     * isTemp - True to get temporal path of resource. Example. avatar_temp.
     *          False in reverse.
     * 
     * Example:
     * [
     *  'isTemp' => false 
     * ]
     * @return $this
     */
    public function configResource($options = [])
    {
        $this->baseConfigUrl['resource'] = ArrayHelper::merge([
                    'type' => '',
                    'isTemp' => false
                        ], $options);

        $this->baseConfigUrl['resource']['isTemp'] ? $this->concatTempResource($this->baseConfigUrl['resource']['type']) : $this->concatResource($this->baseConfigUrl['resource']['type']);

        return $this->getStringUrl();
    }

    /**
     * 
     * Generate the base path.
     * 
     * @params arr $options Config to base path.
     * 
     * enviroment - Enviroment running in the application.
     * 
     * Example:
     * [
     *  'enviroment' => 'dev' 
     * ]
     * @return $this
     */
    public function configBasePath($options = [])
    {
        $this->baseConfigUrl['basePath'] = ArrayHelper::merge([
                    'enviroment' => $this->getEnviromentRunning()
                        ], $options);

        $this->concatBasePath($this->baseConfigUrl['basePath']['enviroment']);

        return $this->getStringUrl();
    }

    /**
     * 
     * Generate the root path.
     * 
     * @param arr $options Config to root path.
     * 
     * isWebUrl - True to get cdn, False to get Absolute path.
     *            Default True.
     * 
     * Example:
     * [
     *  'isWebUrl' => true 
     * ]
     */
    public function configRoot($options = [])
    {
        $this->baseConfigUrl['root'] = ArrayHelper::merge([
                    'isWebUrl' => true
                        ], $options);

        $this->baseConfigUrl['root']['isWebUrl'] ? $this->concatCDN() : $this->concatAbsoluteDirectory();

        return $this->getStringUrl();
    }

    /**
     * Get the directory absolute depending of
     * enviroment.
     * @return type
     */
    public function getAbsoluteDirectory()
    {
        return Yii::getAlias($this->containerBasePath);
    }

    /**
     * Get the cdn url.
     * @return type
     */
    public function getCDN()
    {
        return $this->cdn;
    }

    /**
     * Get the base path by enviroment specified, if is null get the
     * enviroment running in application.
     * @param string $enviroment Enviroment.
     * @return string 
     */
    public function getBasePath($enviroment = null)
    {

        if (empty($enviroment)) {
            $enviroment = $this->getEnviromentRunning();
        }

        if (!empty($this->basePaths[$enviroment])) {
            return $this->basePaths[$enviroment];
        }
    }

    /**
     * Get the resource specified.
     * @param string $type Type of resource.
     */
    public function getResource($type)
    {

        $resources = $this->resources();

        if (!empty($resources[$type])) {
            return $resources[$type];
        }
    }

    /**
     * Get the resource specified.
     * @param string $type Type of resource.
     */
    public function getDefaultResource($type)
    {

        $resources = $this->resourcesDefault();

        if (!empty($resources[$type])) {
            return $resources[$type];
        }
    }

    /**
     * Get temporal folder to resource.
     * @param type $type
     */
    public function getTempResource($type)
    {
        return $this->getResource($type) . "_" . $this->prefixTemp;
    }

    /**
     * Get the size specified.
     * @param int $size Type of size;
     * @return string
     */
    public function getSize($size)
    {

        $sizes = $this->sizes();

        if (!empty($sizes[$size])) {
            return $sizes[$size];
        }
    }

    /**
     * Get Name
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the enviroment running.
     * @return string
     */
    public function getEnviromentRunning()
    {
        return YII_ENV;
    }

    /**
     * Add size to stringUrl
     * @return $this
     */
    public function concatAbsoluteDirectory()
    {
        $absoluteDirectory = $this->getAbsoluteDirectory();
        $this->setStringUrl($absoluteDirectory, 'root');
        return $this;
    }

    /**
     * Add cdn to stringUrl
     * @return $this
     */
    public function concatCDN()
    {
        $cdn = $this->getCDN();
        $this->setStringUrl($cdn, 'root');
        return $this;
    }

    /**
     * Add base path to stringUrl
     * @return $this
     */
    public function concatBasePath($enviroment = null)
    {
        $basePath = $this->getBasePath($enviroment);
        $this->setStringUrl($basePath, 'basePath');
        return $this;
    }

    /**
     * Add size to stringUrl
     * @return $this
     */
    public function concatResource($type)
    {
        $this->setStringUrl($this->getResource($type), 'resource');
        return $this;
    }

    /**
     * Add tempResourcer to stringUrl
     * @return $this
     */
    public function concatTempResource($type)
    {
        $this->setStringUrl($this->getTempResource($type), 'resource');
        return $this;
    }

    /**
     * Add size to stringUrl
     * @return $this
     */
    public function concatSize($size = self::SIZE_ORIGINAL)
    {
        if (!empty($size) && $size != self::SIZE_ORIGINAL) {
            $this->setStringUrl($this->getSize($size), 'size');
        }

        return $this;
    }

    public function concatName($name)
    {
        $this->setStringUrl($name, 'name');
    }

    /**
     * Get string url
     * @return string
     */
    public function getStringUrl()
    {

        $arrayUrls = array_filter($this->stringUrl, function($path) {
            if (!empty($path)) {
                return $path;
            }
        });

        return implode(DIRECTORY_SEPARATOR, array_values($arrayUrls));
    }

    /**
     * Set string url
     * @param string $path Path to concatenate.
     * @param string $separator Separator.
     */
    public function setStringUrl($path, $element)
    {
        $this->stringUrl[$element] = $path;
    }

    /**
     * Set name.
     * @param string $name Name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Resources common override in each module.
     * @return arr
     */
    public function resources()
    {
        return [];
    }

    /**
     * Url to images by default.
     * @return []
     */
    public function resourcesDefault()
    {
        return [];
    }

    /**
     * Sizes of the system.
     * @return string
     */
    public function sizes()
    {

        return [
            self::SIZE_ORIGINAL => '',
            self::SIZE_THUMB => 'thumb'
        ];
    }

    /**
     * Clear property stringUrl
     */
    public function clearStringUrl()
    {
        $this->stringUrl = "";
    }

    /**
     * Get active root;
     */
    public function getActiveRoot()
    {
        return !empty($this->stringUrl['root']) ? $this->stringUrl['root'] : null;
    }

    /**
     * Get active activeBasePath;
     */
    public function getActiveBasePath()
    {
        return !empty($this->stringUrl['basePath']) ? $this->stringUrl['basePath'] : null;
    }

    /**
     * Get active activeResource;
     */
    public function getActiveResource()
    {
        return !empty($this->stringUrl['resource']) ? str_replace('_temp', '', $this->stringUrl['resource']) : null;
    }

    /**
     * Get active activeSize;
     */
    public function getActiveSize()
    {
        return !empty($this->stringUrl['size']) ? $this->stringUrl['size'] : null;
    }

    /**
     * Get active activeName;
     */
    public function getActiveName()
    {
        return !empty($this->stringUrl['name']) ? $this->stringUrl['name'] : null;
    }

    /**
     * Integrate the basePaths defaults with the user basepaths;
     */
    private function mergeBasePaths()
    {
        $this->basePaths = ArrayHelper::merge([
                    'dev' => 'images/dev',
                    'test' => 'images/test',
                    'prod' => 'images'
                        ], $this->basePaths);
    }

}
