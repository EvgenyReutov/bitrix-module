<?
namespace Instrum\Main\Tools;

class ftp{

    const UPLOAD_DIR = "/upload/ftp_tmp";
    public $conn; 

    public function __construct($url){ 
        $this->conn = ftp_connect($url); 
    } 
    
    public function __call($func,$a){ 
        if(strstr($func,'ftp_') !== false && function_exists($func)){ 
            array_unshift($a,$this->conn); 
            return call_user_func_array($func,$a); 
        }else{ 
            // replace with your own error handler. 
            die("$func is not a valid FTP function"); 
        } 
    }

    public function saveTmpFile($path, $file){
        $dir = $_SERVER["DOCUMENT_ROOT"].self::UPLOAD_DIR.$path;
        if(!file_exists($dir)){
            if(!mkdir($dir, 0755, true)){
                throw new \Exception("Не удалось создать директорию для загрузки файла ".$dir);
            }
        }

        if(!$this->ftp_get($dir.$file, $path.$file, FTP_BINARY)){
            throw new \Exception("Не удалось сохранить файл ".$path.$file);
        }

        return $dir.$file;
    }

    public function deleteTmpFile($path, $file){        
        $result = false;
        $tmpFile = $_SERVER["DOCUMENT_ROOT"].self::UPLOAD_DIR.$path.$file;
        if(file_exists($tmpFile)){
            $result = unlink($tmpFile);
        }

        return $result;
    }
}