<?php

namespace CodeCommerce\ModuleSkeleton\Controller;

use CodeCommerce\ModuleSkeleton\Core\ComposerGenerator;
use CodeCommerce\ModuleSkeleton\Core\FileStructorGenerator;
use CodeCommerce\ModuleSkeleton\Core\MetadataGenerator;
use CodeCommerce\ModuleSkeleton\Model\ComposerVendorSettings;
use CodeCommerce\ModuleSkeleton\Model\ComposerVendorFile;
use CodeCommerce\ModuleSkeleton\Model\MetadataFile;
use CodeCommerce\ModuleSkeleton\Model\SkeletonConfiguration;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Request;

/**
 * Class ModuleGenerator
 * @package CodeCommerce\ModuleSkeleton\Core
 */
class ModuleGeneratorController
{
    /**
     * const
     */
    const FILE_SEPERATOR = "/";

    /**
     * @var SkeletonConfiguration
     */
    protected $oSkeletonConfiguration;

    /**
     * @var ComposerVendorFile
     */
    protected $oComposerVendorFile;
    /**
     * @var FileStructorGenerator
     */
    protected $oFileStructor;
    /**
     * @var string
     */
    protected $sVendorpath;

    /**
     * ModuleGenerator constructor.
     * @param SkeletonConfiguration $oSkeletonConfig
     * @param ComposerVendorFile    $oComposerVendorFile
     */
    public function __construct(SkeletonConfiguration $oSkeletonConfig, ComposerVendorFile $oComposerVendorFile)
    {
        $this->oSkeletonConfiguration = $oSkeletonConfig;
        $this->oComposerVendorFile = $oComposerVendorFile;

        /**
         * @todo refactoring
         */
        $sComposerRelativePath = $this->getModulePath(false) . self::FILE_SEPERATOR . 'composer.json';
        $this->oComposerVendorFile->setFilePath($sComposerRelativePath);
    }

    /**
     *
     */
    public function generateModule()
    {
        try {
            $this->createFileStructur();
            $this->generateEmptyFiles();
            $this->generateModuleComposerFile();
            $this->updateProjectComposerFile();
            if ($this->oSkeletonConfiguration->getComposerUpdate()) {
                $this->updateComposer();
            }
            $this->generateMetadata();
        } catch (\Exception $e) {
            die($e->getMessage());
        }
    }

    protected function updateComposer()
    {
        $sRootPath = __DIR__ . '/' . $this->oSkeletonConfiguration->getBasePath();
        exec('cd ' . $sRootPath . ' && composer update');
    }

    /**
     * @return FileStructorGenerator
     */
    protected function getFileStructor()
    {
        if (null == $this->oFileStructor) {
            $this->oFileStructor = new FileStructorGenerator();
        }

        return $this->oFileStructor;
    }

    /**
     * @return bool
     */
    protected function createFileStructur(): bool
    {
        if (!$this->oSkeletonConfiguration->getGenerateFileStructur()) {
            return false;
        }

        $bGenerated = $this->getFileStructor()->generateFileStructur($this->getModulePath());

        if ($aDirectories = $this->oSkeletonConfiguration->getFileStructurDirectories()) {
            foreach ($aDirectories as $sDirectory) {
                $this->createSubModuleDir($sDirectory);
            }
        }

        return $bGenerated;
    }

    /**
     * @param $sDir
     */
    protected function createSubModuleDir($sDir)
    {
        $this->getFileStructor()->generateFileStructur($this->getModulePath() . self::FILE_SEPERATOR . $sDir);
    }

    /**
     *
     */
    protected function generateEmptyFiles()
    {
        if ($this->oSkeletonConfiguration->getGenerateFiles()) {
            if ($aFiles = $this->oSkeletonConfiguration->getFileToGenerate()) {
                foreach ($aFiles as $sFilePath) {
                    $this->getFileStructor()->generateFile($this->getModulePath() . self::FILE_SEPERATOR . $sFilePath);
                }
            }
        }
    }

    /**
     * @param bool $bFullPath
     * @return string
     */
    protected function getModulePath($bFullPath = true): string
    {
        $sPath = '';
        if ($bFullPath) {
            $sPath .= __DIR__ . "/";
        }
        $sPath .= $this->oSkeletonConfiguration->getBasePath();
        $sPath .= $this->oSkeletonConfiguration->getModulBasePath();
        $sPath .= $this->oComposerVendorFile->getVendorNamespace() . self::FILE_SEPERATOR;
        $sPath .= $this->oComposerVendorFile->getModulName();

        $this->sVendorpath = str_replace('//', '/', $sPath);

        return $this->sVendorpath;
    }

    /**
     *
     */
    protected function generateModuleComposerFile()
    {
        $sModuleComposerFilePath = $this->getModulePath() . self::FILE_SEPERATOR . 'composer.json';
        $oComposerGenerator = new ComposerGenerator($sModuleComposerFilePath);
        $oComposerGenerator->addContent($this->oComposerVendorFile->toObject());
        $oComposerGenerator->saveComposerFile();
    }

    /**
     *
     */
    protected function updateProjectComposerFile()
    {
        $sPath = __DIR__ . '/';
        $sPath .= $this->oSkeletonConfiguration->getBasePath();
        $sPath .= self::FILE_SEPERATOR . 'composer.json';
        $sPath = str_replace("//", "/", $sPath);

        $oComposerGenerator = new ComposerGenerator($sPath);
        $oComposerGenerator->updateComposerArrayField(
            [
                'require'      => [
                    $this->oComposerVendorFile->getComposerName() => $this->oComposerVendorFile->getComposerVersion(),
                ],
                'repositories' => [
                    $this->oComposerVendorFile->getComposerName() => [
                        'type' => 'path',
                        'url'  => "./" . $this->oSkeletonConfiguration->getModulBasePath() . $this->oComposerVendorFile->getComposerName(),
                    ],
                ],
            ]
        );
        $oComposerGenerator->saveComposerFile();
    }

    protected function generateMetadata()
    {
        $oMetadataFile = new MetadataFile();
        $oMetadataFile
            ->setName($this->oComposerVendorFile->getModulName())
            ->setTitle($this->getModuleName())
            ->setModuleVersion($this->oSkeletonConfiguration->getMetadata()['modul_version'])
            ->setVersion($this->oSkeletonConfiguration->getMetadata()['metadata_version'])
            ->setThumbnail($this->oSkeletonConfiguration->getMetadata()['thumbnail'])
            ->setPath($this->getModulePath())
            ->setAuthor($this->oComposerVendorFile->getAuthorName())
            ->setUrl($this->oComposerVendorFile->getAuthorHomepage())
            ->setMail($this->oComposerVendorFile->getAuthorEmail())
            ->setDescription($this->oComposerVendorFile->getComposerDescription());

        $oMetadataGenerator = new MetadataGenerator($oMetadataFile);
    }

    protected function getModuleName()
    {
        return strtolower($this->oComposerVendorFile->getVendorNamespace() . "_" . $this->oComposerVendorFile->getModulName());
    }

    public function checkIfModuleExists()
    {
        if ($this->getFileStructor()->isDir($this->getModulePath())) {
            return true;
        }
        return false;
    }
}