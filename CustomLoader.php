<?php

namespace Sadekbaroudi\Gitorade;

use Symfony\Component\Finder\Finder;
use Sadekbaroudi\Gitorade\GitoradeConstants;

class CustomLoader
{
    /**
     * 
     */
    public function __construct()
    {
    }
    
    /**
     * get all the classes within the custom directory or any subdirectory within it
     * 
     * @param string $subPath the subdirectory within the custom directory to scan, empty string if all of custom
     * @throws Exception throws exception if \Symfony\Component\Finder\Finder can't process the directory
     * @return array an array of all the usable class (namespace) names
     */
    public function getClasses($subPath = '')
    {
        $subPath = $this->enforceTrailingSlash($subPath);
        $path = GitoradeConstants::CUSTOM_DIR_PREFIX . $subPath;
        
        $finder = new Finder();
        $files = array();
        
        try {
            $finder->files()->in($path);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), "directory does not exist") !== FALSE) {
                $logMe = (string)$e;
                return $files;
            } else {
                throw $e;
            }
        }
        
        foreach ($finder as $file) {
            // Skip any non-php files
            if (strtolower(pathinfo($file->getRelativePathname(), PATHINFO_EXTENSION)) != 'php') {
                continue;
            }
            
            $filePathNoExt = $path . pathinfo($file->getRelativePathname(), PATHINFO_FILENAME);
            $localNamespace = str_replace(DIRECTORY_SEPARATOR, GitoradeConstants::NAMESPACE_SEPARATOR, $filePathNoExt);
            $namespace = GitoradeConstants::NAMESPACE_BASE . GitoradeConstants::NAMESPACE_SEPARATOR . $localNamespace;
            
            $files[] = $namespace;
        }
        
        return $files;
    }
    
    protected function enforceTrailingSlash($path)
    {
        // Make sure the argument ends in a slash
        if (!empty($path) && substr($path, -1) != DIRECTORY_SEPARATOR) {
            $path .= DIRECTORY_SEPARATOR;
        }
        
        return $path;
    }
}