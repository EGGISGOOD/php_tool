<?php

/**
 * @name   S3File
 * @author denis
 * @desc   默认控制器
 */
class FileTest extends File
{

    protected $uploadDir = '';
    protected $fileInput = '';
    protected $fileType = '';
    protected $fileMaxSize = 0;

    public function __construct($fileInput = '', $no_min_width = 0, $uploadDir = '', $fileType = '', $fileMaxSize = 0, $min_width = 0, $ratio = 0)
    {
	$file = Yaf_Registry::get('config')->file;
	$this->uploadDir = empty($uploadDir) ? $file->dir : $uploadDir;
	$this->fileInput = empty($fileInput) ? $file->fileInput : $fileInput;
	$this->fileType = empty($fileType) ? $file->file_type : $fileType;
	$this->fileMaxSize = empty($fileMaxSize) ? $file->max_size : $fileMaxSize;
	$this->min_width = empty($min_width) ? $file->min_width : $min_width;
	$this->ratio = empty($ratio) ? $file->ratio : $ratio;
	$this->no_min_width = $no_min_width;
	$this->fileRootDir = ROOT_PATH . "/public";
    }

    protected function _del($filekey)
    {
	echo $filekey;
    }

    protected function _get($filekey)
    {
	$ret = '';
	$sql = 'fileKey = "%s"';
	$row = M('Files')->where($sql, array($filekey))->getRow();
	if ($row)
	    $ret = $row['filePath'];
	return $ret;
    }

    protected function _save()
    {
	$return = $this->uploadfiles();
	return $return;
    }

    protected function _exists()
    {
	echo 2;
    }

    protected function _checkFilekey($filekey)
    {
	echo $filekey;
    }

    /**
     * 圖片或文件上傳函數
     *
     * $this->fileInput 表單需要用數組 例如 photo[0] 或者是http://網址
     *
     * @return array 返回已上傳的圖片名稱數組
     */
    protected function uploadfiles()
    {
	$return = array();
	if (strstr($this->fileInput, "http://") || strstr($this->fileInput, "https://")) {
	    $return = $this->uploadHttpPic();
	} else {
	    if ($_FILES[$this->fileInput]) {
		foreach ($_FILES[$this->fileInput]["error"] as $key => $error) {
		    $return[$key]['code'] = 0;
		    $return[$key]['msg'] = UPLOAD_PIC_FAILED;
		    if ($error == UPLOAD_ERR_OK) {
			$return = $this->uploadLocalPic($key);
		    }
		}
	    }
	}
	return $return;
    }

    protected function uploadLocalPic($key)
    {
	$return = array();
	$return[$key]['code'] = 0;
	$return[$key]['msg'] = UPLOAD_PIC_FAILED;

	$f_name = $_FILES[$this->fileInput]['name'][$key]; //獲取上傳源文件名
	$fileType = strtolower(substr(strrchr($f_name, "."), 1)); //獲取文件擴展名
	if (!strstr($this->fileType, $fileType)) {
	    $return[$key]['msg'] = FILETYPE_NOT_SUPPORT;
	    return $return;
	} if ($_FILES[$this->fileInput]['size'][$key] > $this->fileMaxSize || $_FILES[$this->fileInput]['size'][$key] == 0) {
	    $return[$key]['msg'] = OVER_FILE_MAX_SIZE . ($this->fileMaxSize / 1024 / 1024) . "MB";
	    return $return;
	}
	$size = getimagesize($_FILES[$this->fileInput]['tmp_name'][$key]);
	if (empty($size))
	    return $return;

	$is_width = 0;
	if (empty($this->no_min_width) && $fileType <> 'gif')
	    $is_width = 1;

	if ($size[0] < $this->min_width && $is_width) {
	    $return[$key]['msg'] = PIC_LESS_WIDTH;
	} elseif (($size[0] / $size[1] < $this->ratio && $is_width ) || ($size[1] / $size[0] < $this->ratio && $is_width )) {
	    $return[$key]['msg'] = PIC_LESS_RATIO;
	} else {
	    $ret = $this->upload($this->uploadDir, $key, $fileType, $this->fileInput);
	    if ($ret['code'] == 1) {
		$cropParam = '';
		$exif = exif_read_data($this->fileRootDir . $ret['path']);

		if (!empty($exif['Orientation'])) {
		    switch ($exif['Orientation']) {
			case 8:
			    $cropParam = "/filters:rotate(90)";
			    break;
			case 3:
			    $cropParam = "/filters:rotate(180)";
			    break;
			case 6:
			    $cropParam = "/filters:rotate(-90)";
			    break;
		    }
		    if (!empty($cropParam)) {
			$imgUrl = Yaf_Registry::get('config')->application->img_url;
			$url = $imgUrl . $cropParam . $ret["path"];
			$imgRet = uploadFile($url);
			$ret = $imgRet[0];
			$ret['size'] = $exif['FileSize'];
		    }
		}

		if ($ret['code'] == 1) {
		    $return[$key]['code'] = $ret['code'];
		    $return[$key]['filename'] = $f_name;
		    $return[$key]['path'] = $ret['path'];
		    $return[$key]['size'] = $ret['size'];
		    $return[$key]['fileKey'] = $ret['fileKey'];
		    $return[$key]['fileType'] = $fileType;
		    $return[$key]['msg'] = 'ok';
		} else {
		    $return[$key]['code'] = $ret['code'];
		    $return[$key]['msg'] = $ret['msg'];
		}
	    } else {
		$return[$key]['code'] = $ret['code'];
		$return[$key]['msg'] = $ret['msg'];
	    }
	}
	return $return;
    }

    protected function uploadHttpPic()
    {
	$return = array();
	$return[0]['code'] = 0;
	$return[0]['msg'] = FETCH_PIC_FAILED;
	$Curl = new Curl();
	$Curl->https = 1;
	$fileData = $Curl->fetchUrl($this->fileInput);
	if (!empty($fileData)) {
	    $url = parse_url($this->fileInput);
	    $fileType = strtolower(substr(strrchr($url['path'], "."), 1)); //獲取文件擴展名
	    $ret = $this->upload($this->uploadDir, 0, $fileType, $fileData, 1);
	    if ($ret['code'] == 1) {
		$return[0]['code'] = $ret['code'];
		$return[0]['filename'] = basename($this->fileInput);
		$return[0]['path'] = $ret['path'];
		$return[0]['fileKey'] = $ret['fileKey'];
	    } else {
		$return[0]['code'] = $ret['code'];
		$return[0]['msg'] = $ret['msg'];
	    }
	}
	return $return;
    }

    protected function upload($uploadDir, $key, $fileType, $fileInput, $isDownload = 0)
    {

	$attr = array();
	$return = array();
	$attr['addtime'] = dateTime();
	$attr['fileKey'] = uniqid();
	$attr['filePath'] = '';
	$id = M('Files')->add($attr);
	$pic_path = $uploadDir . '/' . date('Y') . '/' . date('m') . '/' . date('d');
	createDirs($pic_path);
	$uploadfile_path = $pic_path . '/' . $id . '_' . date("YmdHis") . '_' . uniqid() . '.' . $fileType;

	$isUploaded = 0;
	if ($isDownload) {
	    if (file_put_contents($uploadfile_path, $fileInput))
		$isUploaded = 1;
	} else {
	    $return['size'] = $_FILES[$fileInput]['size'][$key];
	    if (move_uploaded_file($_FILES[$fileInput]['tmp_name'][$key], $uploadfile_path))
		$isUploaded = 1;
	}
	if ($isUploaded) {
	    @chmod($uploadfile_path, 0777);
	    $return['code'] = 1;
	    $return['path'] = str_replace($this->fileRootDir, "", $uploadfile_path);
	    $return['fileKey'] = $attr['fileKey'];

	    M("Files")->where("id='%d'", array($id))->save(array('filePath' => $return['path']));
	} else {
	    $return['code'] = 0;
	    $return['msg'] = "對不起,文件上傳失敗!";
	    M("Files")->where("id='%d'", array($id))->save(array('status' => 0));
	}
	return $return;
    }



}