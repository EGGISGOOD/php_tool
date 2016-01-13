<?php
abstract class File {
	protected static $instance;

	public static function getInstance() {
		if (!self::$instance) {
			$file_system = Yaf_Registry::get('config')->file->file_system;
			if (!empty($file_system) && file_exists(dirname(__FILE__) . '/' . $file_system . '.php')) {
				Yaf_Loader::import(dirname(__FILE__) . '/' . $file_system . '.php');
				self::$instance = new $file_system();
			}
		}

		return self::$instance;
	}

	abstract protected function _del($filekey);

	abstract protected function _get($filekey);

	abstract protected function _save();

	abstract protected function _exists();

	abstract protected function _checkFilekey($filekey);

	public function get($filekey) {
		return $this->_get($filekey);
	}

	public function save() {
		return $this->_save();
	}

	public function del($filekey) {
		return $this->_del($filekey);
	}

	public function checkFilekey($filekey) {
		return $this->_checkFilekey($filekey);
	}
}
