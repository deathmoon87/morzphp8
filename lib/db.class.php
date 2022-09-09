<?php
/**
* 数据框架主体
*
* 数据库类
*
* @author Deathmoon <jjlxljjadm@msn.com>
* @version 2.0
* @filesource db.class.php
*/
class m_db{
	/**
	* construct
	*/
	public function __construct(
		public $querynum = 0,
		public $mysqli = null,
		public $histories = null,

		public $dbhost = '',
		public $dbuser = '',
		public $dbpw = '',
		public $dbname = '',
		public $dbcharset = '',
		public $dbport = 0,
		public $tablepre = '',
		public $time = null,
		
		private $queries = null,
		private $goneaway = 5
	){}

	/**
	* Connect
	*
	* 创建数据库连接
	*
    * @param string	$dbhost	数据库主机地址
    * @param string	$dbuser	连接数据库用户名
    * @param string	$dbpw	连接数据库密码 
    * @param string	$dbname	数据库名 
    * @param string	$dbcharset	数据库编码 
    * @param integer $dbport	端口号 
    * @param string	$tablepre	数据表前缀 
    * @param string	$time 
    */
	function connect($dbhost, $dbuser, $dbpw, $dbname = '', $dbcharset = '', $dbport = 3306, $tablepre = '', $time = 0) {
		$this->dbhost = $dbhost;
		$this->dbuser = $dbuser;
		$this->dbpw = $dbpw;
		$this->dbname = $dbname;
		$this->dbcharset = $dbcharset;
		$this->dbport = $dbport;
		$this->tablepre = $tablepre;
		$this->time = $time;

		$this->mysqli = new mysqli($dbhost, $dbuser, $dbpw, "", $dbport);
		if(mysqli_connect_errno()){
			$this->halt('Can not connect to MySQL server');
		} else {
			echo "connect mysql success\n";
		}
		if($dbcharset) {
			$this->mysqli->set_charset($dbcharset);
		}
		if($dbname) {
			$this->mysqli->select_db($dbname);
		}
	}

	/**
	* Fetch first
	*
	* 查询返回结果中的第一行
	*
	* @param string $sql 查询语句
	* @return array $array 查询结果 
	*/
	function fetch_first($sql) {
		$this->query($sql);
		return $this->queries->fetch_array(MYSQLI_ASSOC);

	}

	/**
	* Fetch array
	*
	* 查询返回所有结果集
	*
	* @param string $sql 查询语句
	* @param string $id 以$id字段名为二维数组列名，空即为数字列0开始列名
	* @return array $arr 结果集
	*/
	function fetch_array($sql, $id = '') {
		$arr = array();
		$this->query($sql);
		while($data = $this->queries->fetch_array(MYSQLI_ASSOC)) {
			$id ? $arr[$data[$id]] = $data : $arr[] = $data;
		}
		return $arr;

	}

	/**
	* Fetch all
	*
	* 查询返回所有结果集
	*
	* @param string $sql 查询语句
	* @return array $arr 结果集
	*/
	function fetch_all($sql) {
		$arr = array();
		$this->query($sql);
		$arr = $this->queries->fetch_all(MYSQLI_ASSOC);
		return $arr;
	}

	/**
	* Queries
	*
	* 执行sql
	*
	* @param string $sql 查询语句
	* @return array $query 结果集
	*/
	function query($sql) {
		if(!($query = $this->mysqli->query($sql))) {
			$this->halt('MySQL Query Error', $sql);
		}
		$this->querynum++;
		$this->histories[] = $sql;
		$this->queries = $query;
		return $query;
	}

	/**
	* Resultes
	*
	* 执行sql，返回一字段值
	*
	* @param string $query 查询语句
	* @param string $row 行号
	* @return array $query 结果字段
	*/
	function result_first($sql) {
		$this->query($sql);
		$data = $this->queries->fetch_column(0);
		return $data;
	}

	/**
	* Halt
	* 
	* 显示数据库错误
	*
	* @param string $message 信息
	* @param string $sql 查询语句
	* @return string $s 错误信息
	*/
	function halt($message = '', $sql = '') {
		$error = $this->mysqli->error;
		$errorno = $this->mysqli->errno;
		if($errorno == 2006 && $this->goneaway-- > 0) {
			$this->connect($this->dbhost, $this->dbuser, $this->dbpw, $this->dbname, $this->dbcharset, $this->dbport, $this->tablepre, $this->time);
			$this->query($sql);
		} else {
			$s = '';
			if($message) {
				$s = "<b>Mts info:</b> $message<br />";
			}
			if($sql) {
				$s .= '<b>SQL:</b>' . htmlspecialchars($sql). '<br />';
			}
			$s .= '<b>Error:</b>' . $error . '<br />';
			$s .= '<b>Errno:</b>' . $errorno . '<br />';
			$s = str_replace(LOCAL_DBTABLEPRE, '[Table]', $s);
			exit($s);
		}
	}

	/**
	* Cache_gc
	*
	* 清空缓存语句
	*/
	function cache_gc() {
		$this->query("DELET FROM {$this->tablepre}sqlcaches WHERE expiry<$this->time");
	}

	/**
	* Affected rows
	*
	* 获取上一次执行insert,update,delete影响的记录行数
	*
	* @return integer 影响行数
	*/
	function affected_rows() {
		return $this->mysqli->affected_rows;
	}

	/**
	* Num rows
	*
	* 获取上一次执行select影响的记录行数
	*
	* @return integer 影响行数
	*/
	function num_rows() {
		return $this->queries ? $this->queries->num_rows : 0;
	}

	/**
	* Num Fields
	*
	* 返回结果字段总数
	*
	* @return integer 结果字段数
	*/
	function num_fields() {
		return $this->queries ? $this->queries->field_count : 0;
	}

	/**
	* Insert id
	*
	* 返回上一次查询的自增长id
	*
	* @return integer $id
	*/
	function insert_id() {
		return ($id = $this->mysqli->insert_id) >= 0 ? $id : $this->result_first("SELECT last_insert_id()");
	}

	/**
	* Close
	*
	* 关闭连接
	*
	* @return boolean true成功/false失败 
	*/
	function close() {
		return $this->mysqli->close();
	}

	/**
	* Free
	*
	* 释放结果集
	*/
	function free() {
		if($this->queries) {
			$this->queries->free();
		}
	}

	/**
	* Fetch row
	*
	* 从结果集中取得一行枚举数组
	*
	* return array $query
	*/
	function fetch_row() {
		return $this->queries ? $this->queries->fetch_row() : false;
	}

	/**
	* Fetch_field
	*
	* 返回结果集中的下一行对象
	*
	* @return object
	*/
	function fetch_field() {
		return $this->queries ? $this->queries->fetch_field() : false;
		
	}

	/**
	* Version
	*
	* 返回MySQL服务器信息
	*
	* @return string 信息
	*/
	function version() {
		return $this->mysqli->server_info;
	}

	/**
	* Get list
	*
	* 获得详细列表
	*
	* @param string $table 表名
	* @param string $where 查询条件语句
	* @param string $order 排列语句
	* @return array $arr 结果数组
	*/
	function get_list($table, $where = '', $order = '') {
		if($table == '') {return false;}
		$arr = $this->fetch_all('SELECT * FROM '.$this->tablepre.$table.' '.$where.' '.$order);
		return $arr;
	}

	/**
	* Get total num
	*
	* 获取共几条记录
	*
	* @param string $table 表名
	* @param stting $where 查询语句
	* @return intger $data 行数
	*/
	function get_total_num($table, $where = '') {
		if($table == '') {return false;}
		$data = $this->result_first('SELECT COUNT(*) FROM '.$this->tablepre.$table.' '.$where);
		return $data;
	}

	/**
	* Get list page
	*
	* 含分页搜索记录
	*
	* @param intger $page 页码
	* @param intger $ppp 每页条数
	* @param intger $totalnum 总行数
	* @param string $table 表名
	* @param string $where 查询语句
	* @param string $order 排列语句
	* @return array $arr 结果数组
	*/
	function get_list_page($page, $ppp, $totalnum, $table, $where = '', $order = '') {
		if($table == '') {return false;}
		$start = $this->page_get_start($page, $ppp, $totalnum);
		$arr = $this->fetch_all('SELECT * FROM '.$this->tablepre.$table.' '.$where.' '.$order." LIMIT $start, $ppp");
		return $arr;
	}

	/**
	* Page get start
	*
	* 生成翻页插件
	*
	* @param intger $page 页码
	* @param intger $ppp 页数
	* @param intger $totalnum 总行数
	* @return intger 起始页数
	*/
	function page_get_start($page, $ppp, $totalnum) {
		$totalpage = ceil($totalnum / $ppp);
		$page = max(1, min($totalpage, intval($page)));
		return ($page - 1) * $ppp;
	}

	/**
	* Add msg
	*
	* 插入数据行
	*
	* @param string $sqladd 添加数据
	* @param string $table 表名
	* @return data $id 自增id
	*/
	function add_msg($table, $sqladd) {
		if($table == '' || $sqladd == '') {return false;}
		$this->query('INSERT INTO '.$this->tablepre.$table.' SET '.$sqladd);
		$id = $this->insert_id();
		return $id;
	}

	/**
	* Edit msg
	*
	* 修改数据行
	*
	* @param string $table 表名
	* @param string $sqladd 修改语句
	* @return intger $rows 影响行数
	*/
	function edit_msg($table, $sqladd) {
		if($table == '' || $sqladd == '') {return false;}
		$this->query('UPDATE '.$this->tablepre.$table.' SET '.$sqladd);
		$rows = $this->affected_rows();
		return $rows;
	}

	/**
	* Del msg
	*
	* 删除数据行
	*
	* @param string $sqladd 删除语句
	* @param string $table 表名
	* @return intger $rows 影响行数
	*/
	function del_msg($table, $sqladd) {
		if($table == '' || $sqladd == '') {return false;}
		$this->query('DELETE FROM '.$this->tablepre.$table.' '.$sqladd);
		$rows = $this->affected_rows();
		return $rows;
	}
}

?>
