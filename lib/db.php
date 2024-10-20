<?php

class db
{
	private $m_dsn = null;
	private $m_user = null;
	private $m_pass = null;

	private $m_pdo = null;
	private $m_last_id = null;

	function __construct($dsn = '', $user = '', $pass = '')
	{
		if ($dsn) {
			$this->m_dsn = $dsn;
			$this->m_user = $user;
			$this->m_pass = $pass;
		}
	}

	function __destruct()
	{
		$this->close();
	}

	function open($dsn = '', $user = '', $pass = '')
	{
		$this->close();

		if (!$dsn) {
			if (!$this->m_dsn) {
				die('DBのOpenに失敗しました。（DSNが不明です）');
			}

			$dsn = $this->m_dsn;
			$user = $this->m_user;
			$pass = $this->m_pass;
		}

		try {
			$this->m_pdo = new PDO($dsn, $user, $pass);
		} catch (PDOException $e) {
			$this->m_pdo = null;
			die('DBのOpenに失敗しました。（' . $e->getMessage() . '）');
		}

		$this->m_pdo->setAttribute(PDO::ATTR_TIMEOUT, 30);
		$this->m_pdo->query("SET NAMES utf8");
	}

	function close()
	{
		$this->m_pdo = null;
		$this->m_last_id = null;
	}

	function exec($sql, $vals = array())
	{
		if (!$this->m_pdo) {
			$this->open();
		}

		$this->m_last_id = null;

		if (!is_array($vals)) {
			if (func_num_args() > 2) {
				// 可変長引数
				$vals = func_get_args();
				array_shift($vals);
			} else {
				// １つの値
				$vals = array($vals);
			}
		}

		$ret = $this->m_pdo->prepare($sql);
		
		if (!$ret) {
			$err = $this->m_pdo->errorInfo();
			die('プリペアの実行に失敗しました。（' . $err[0] . ':' . $err[1] . ':' . $err[2] . '）');
		}

		if (!$ret->execute($vals)) {
			$err = $ret->errorInfo();
			die('クエリの実行に失敗しました。（' . $err[0] . ':' . $err[1] . ':' . $err[2] . '）');
		}

		return $ret->rowCount();
	}

	function query_get($sql, $vals = array())
	{
		if (!$this->m_pdo) {
			$this->open();
		}

		if (!is_array($vals)) {
			if (func_num_args() > 2) {
				// 可変長引数
				$vals = func_get_args();
				array_shift($vals);
			} else {
				// １つの値
				$vals = array($vals);
			}
		}

		if ($ret = $this->query($sql, $vals)) {
			$row = $ret->fetch();
			$ret = null;
			return $row;
		}

		return false;
	}

	function query_get_array($sql, $vals = array())
	{
		if (!$this->m_pdo) {
			$this->open();
		}

		if (!is_array($vals)) {
			if (func_num_args() > 2) {
				// 可変長引数
				$vals = func_get_args();
				array_shift($vals);
			} else {
				// １つの値
				$vals = array($vals);
			}
		}

		if ($ret = $this->query($sql, $vals)) {
			$row = $ret->fetch();
			$ret = null;
			return array_values($row);
		}

		return false;
	}

	function query_get_one($sql, $vals = array())
	{
		if (!$this->m_pdo) {
			$this->open();
		}

		if (!is_array($vals)) {
			if (func_num_args() > 2) {
				// 可変長引数
				$vals = func_get_args();
				array_shift($vals);
			} else {
				// １つの値
				$vals = array($vals);
			}
		}

		if ($ret = $this->query($sql, $vals)) {
			$row = $ret->fetch();
			$ret = null;
			return @array_shift($row);
		}

		return false;
	}

	function query_get_all($sql, $vals = array())
	{
		if (!$this->m_pdo) {
			$this->open();
		}

		if (!is_array($vals)) {
			if (func_num_args() > 2) {
				// 可変長変数
				$vals = func_get_args();
				array_shift($vals);
			} else {
				// １つの値
				$vals = array($vals);
			}
		}

		if ($ret = $this->query($sql, $vals)) {
			$rows = $ret->fetchAll();
			$ret = null;
			return $rows;
		}

		return false;
	}

	function get_last_id($name = null)
	{
		if (!$this->m_pdo) {
			$this->open();
		}

		if ($this->m_last_id)
			return $this->m_last_id;

		try {
			$this->m_last_id = $this->m_pdo->lastInsertId($name);
			return $this->m_last_id;
		} catch (PDOException $e) {
			die('このドライバは、lastInsertId()に対応していません。');
		}
	}

	private function prepare($sql = '')
	{
		$ret = $this->pdo->prepare($sql);
		if ($ret === false) {
			$err = $this->pdo->errorInfo();
			die('プリペアの実行に失敗しました。（' . $err[0] . ':' . $err[1] . ':' . $err[2] . '）');
		}

		$ret->setFetchMode(PDO::FETCH_ASSOC);

		return $ret;
	}

	private function query($sql = '', $vals = array())
	{
		if (count($vals)) {
			$ret = $this->m_pdo->prepare($sql);            
            if (!$ret) {
				$err = $this->m_pdo->errorInfo();
				die('プリペアの実行に失敗しました。（' . $err[0] . ':' . $err[1] . ':' . $err[2] . '）');
            }

            if (!$ret->execute($vals)) {
				$err = $ret->errorInfo();
				die('クエリの実行に失敗しました。（' . $err[0] . ':'.$err[1] . ':' . $err[2] . '）');
			}
		} else {
			$ret = $this->m_pdo->query($sql);
			if ($ret === false) {
				$err = $this->m_pdo->errorInfo();
				die('クエリの実行に失敗しました。（' . $err[0] . ':' . $err[1] . ':' . $err[2] . '）');
			}
		}

		$ret->setFetchMode(PDO::FETCH_ASSOC);

		return $ret;
	}
}
