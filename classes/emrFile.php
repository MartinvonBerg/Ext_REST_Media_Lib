<?php
/**
 * File Helper functions for the replacer class. Taken from plugin enable_media_replace.
 *
 * PHP version 7.2.0 - 8.0.0
 *
 * @category   Rest_Api_Functions
 * @package    Rest_Api_Functions
 * @author     Martin von Berg <mail@mvb1.de>
 * @copyright  2021 Martin von Berg
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link       https://github.com/MartinvonBerg/Ext_REST_Media_Lib
 * @since      File available since Release 5.3.0
 */
namespace mvbplugins\extmedialib;

class emrFile
{

  protected $file; // the full file w/ path.
  protected $extension;
  protected $fileName;
  protected $filePath; // with trailing slash! not the image name.
  protected $fileURL;
  protected $fileMime;
  protected $permissions = 0;

  protected $exists = false;

  public function __construct($file)
  {
      clearstatcache($file);
      // file can not exist i.e. crashed files replacement and the lot.
     if ( file_exists($file))
     {
       $this->exists = true;
     }

     $this->file = $file;
     $fileparts = pathinfo($file);

     $this->fileName = isset($fileparts['basename']) ? $fileparts['basename'] : '';
     $this->filePath = isset($fileparts['dirname']) ? trailingslashit($fileparts['dirname']) : '';
     $this->extension = isset($fileparts['extension']) ? $fileparts['extension'] : '';
     if ($this->exists) // doesn't have to be.
      $this->permissions = fileperms($file) & 0777;

     $filedata = wp_check_filetype_and_ext($this->file, $this->fileName);
     // This will *not* be checked, is not meant for permission of validation!
     // Note: this function will work on non-existing file, but not on existing files containing wrong mime in file.
     $this->fileMime = (isset($filedata['type'])) ? $filedata['type'] : false;

  }

  public function checkAndCreateFolder()
  {
     $path = $this->getFilePath();
     if (! is_dir($path) && ! file_exists($path))
     {
       return wp_mkdir_p($path);
     }
  }

  public function getFullFilePath()
  {
    return $this->file;
  }

  public function getPermissions()
  {
    return $this->permissions;
  }

  public function setPermissions($permissions)
  {
    @chmod($this->file, $permissions);
  }

  public function getFileSize()
  {
      return filesize($this->file);
  }

  public function getFilePath()
  {
    return $this->filePath;
  }

  public function getFileName()
  {
    return $this->fileName;
  }

  public function getFileExtension()
  {
    return $this->extension;
  }

  public function getFileMime()
  {
    return $this->fileMime;
  }

  public function exists()
  {
    return $this->exists;
  }
}