<?php
/**
* 邮件发送主体
*
* 邮件发送类
*
* @author Deathmoon <jjlxljjadm@msn.com>
* @version 2.0
* @filesource mail.class.php
*/
/**
* 邮件发送说明
* 支持发送纯文本邮件和HTML格式的邮件，可以多收件人，多抄送，多秘密抄送，带附件(单个或多个附件),支持到服务器的ssl连接
* 需要的php扩展：sockets、Fileinfo和openssl。
* @example
* $mail = new MySendMail();
* $mail->setServer("XXXXX", "XXXXX@XXXXX", "XXXXX"); //设置smtp服务器
* $mail->setServer("XXXXX", "XXXXX@XXXXX", "XXXXX", 465, true); //设置smtp服务器，到服务器的ssl连接
* $mail->setFrom("XXXXX"); //设置发件人
* $mail->setReceiver("XXXXX"); //设置收件人，多个收件人，调用多次
* $mail->setCc("XXXX"); //设置抄送，多个抄送，调用多次
* $mail->setBcc("XXXXX"); //设置秘密抄送，多个秘密抄送，调用多次
* $mail->addAttachment("XXXX"); //添加附件，多个附件，调用多次
* $mail->setMail("test", "<b>test</b>"); //设置邮件主题、内容
* $mail->sendMail(); 发送
*/
class m_mail {

	/**
	* construct
	*/
	public function __construct(
		private $_userName = '', //邮件传输代理用户名
		private $_password = '', //邮件传输代理密码
		private $_sendServer = '', //邮件传输代理服务器地址
		private $_port = 0, //邮件传输代理服务器端口
		protected $_from = '', //发件人
		protected $_to = array(), //收件人
		protected $_cc = array(), //抄送
		protected $_bcc = array(), //秘密抄送
		protected $_subject = '', //主题
		protected $_body = '', //邮件正文
		protected $_attachment = '', //附件
		protected $_autoattachment = array(), //自动附件
		protected $_socket = null, //socket资源
		protected $_isSecurity = null, //是否是安全连接
		protected $_errorMessage = '' //错误信息
	) {
		setlocale(LC_ALL, 'zh_CN.UTF-8');
	}

    /**
	* setServer
	*
    * 设置邮件传输代理，如果是可以匿名发送有邮件的服务器，只需传递代理服务器地址就行
	*
    * @access public
    * @param string $server 代理服务器的ip或者域名
    * @param string $username 认证账号
    * @param string $password 认证密码
    * @param int $port 代理服务器的端口，smtp默认25号端口
    * @param boolean $isSecurity 到服务器的连接是否为安全连接，默认false
    * @return boolean
    */
	public function setServer($server, $username = '', $password = '', $port = 25, $isSecurity = false) {
		$this->_sendServer = $server;
		$this->_port = $port;
		$this->_isSecurity = $isSecurity;
		$this->_userName = empty($username) ? "" : base64_encode($username);
		$this->_password = empty($password) ? "" : base64_encode($password);
		return true;
	}

    /**
	* setFrom
	*
    * 设置发件人
	*
    * @access public
    * @param string $from 发件人地址
    * @return boolean
    */
    public function setFrom($from) {
        $this->_from = $from;
        return true;
    }

 
    /**
	* setReceiver
	*
    * 设置收件人，多个收件人，调用多次.
	*
    * @access public
    * @param string $to 收件人地址
    * @return boolean
    */
	public function setReceiver($to) {
		if(!empty($to)) {
			if(is_array($to)) {
				foreach($to AS $key => $value) {
					$to[$key] = $this->strip_comment($value);
				}
				unset($key, $value);
				$this->_to = array_merge($this->_to, $to);
			} else {
				$this->_to = array_merge($this->_to, explode(",", $this->strip_comment($to)));
			}
			return true;
		}
		return false;
	}

    /**
	* setCc
	*
    * 设置抄送，多个抄送，调用多次.
	*
    * @access public
    * @param string $cc 抄送地址
    * @return boolean
    */
	public function setCc($cc) {
		if(!empty($cc)) {
			if(is_array($cc)) {
				foreach($cc AS $key => $value) {
					$cc[$key] = $this->strip_comment($value);
				}
				$this->_cc = array_merge($this->_cc, $cc);
				unset($key, $value);
			} else {
				$this->_cc = array_merge($this->_cc, explode(",", $this->strip_comment($cc)));
			}
			return true;
		}
		return false;
	}
 
    /**
	* setBcc
	*
    * 设置秘密抄送，多个秘密抄送，调用多次
	*
    * @access public
    * @param string $bcc 秘密抄送地址
    * @return boolean
    */
    public function setBcc($bcc) {
		if(!empty($bcc)) {
			if(is_array($bcc)) {
				foreach($bcc AS $key => $value) {
					$bcc[$key] = $this->strip_comment($value);
				}
				$this->_bcc = array_merge($this->_bcc, $bcc);
				unset($key, $value);
			} else {
				$this->_bcc = array_merge($this->_bcc, explode(",", $this->strip_comment($bcc)));
			}
			return true;
		}
		return false;
    }
 
    /**
	* addAttachment
	*
    * 设置邮件附件，多个附件，调用多次
	*
    * @access public
    * @param string $file 文件地址
    * @return boolean
    */
	public function addAttachment($file) {
		$filecode = mb_detect_encoding($file, "ASCII, UTF-8, GB2312, GBK, BIG5, LATIN1");
		if($filecode != 'UTF-8') {
			$file = mb_convert_encoding($file, 'UTF-8', $filecode);
		}
		
		if(!file_exists($file)) {
			$this->_errorMessage = 'file '.$file.' dose not exist.';
			return false;
		}
		if(empty($this->_attachment)) {
			$this->_attachment = $file;
			return true;
		} else {
			if(is_string($this->_attachment)) {
				$this->_attachment = array($this->_attachment);
				$this->_attachment[] = $file;
				return true;
			} elseif(is_array($this->_attachment)) {
				$this->_attachment[] = $file;
				return true;
			} else {
				return false;
			}
		}

	}
 
    /**
	* addAutoAttachment
	*
    * 设置邮件附件，多个附件，调用多次(流附件,系统自动生成附件专用)
	*
    * @access public
    * @param string $file 文件地址
	* @param string $fileconnect
    * @return boolean
    */
	public function addAutoAttachment($filename, $fileconnect='') {
		if(empty($filename)) {
			$this->_errorMessage = 'autofile does not exist filename.';
			return false;
		}
		$filename = trim($filename);
		$this->_autoattachment[] = array('filename' => $filename, 'fileconnect' => $fileconnect);
		return true;
	}
 
    /**
	* setMail
	*
    * 设置邮件信息
	*
    * @access public
    * @param string $body 邮件主题
    * @param string $subject 邮件主体内容，可以是纯文本，也可是是HTML文本
    * @return boolean
    */
	public function setMail($subject, $body) {
		$this->_subject = trim($subject);
		$pattern = "/(^|(\r\n))(\.)/";
		if(preg_match($pattern, $body)) {
			$body = preg_replace($pattern, "\1.\3", $body);
		}
		$this->_body = base64_encode($body.'<hr>morz system<br>此邮件为系统自动发出的邮件，请勿直接回复。');
		return true;
	}
 
    /**
	* sendMail
	*
    * 发送邮件
	*
    * @access public
    * @return boolean
    */
	public function sendMail() {
		$command = $this->getCommand();
         
        $this->_isSecurity ? $this->socketSecurity() : $this->socket();
         
        foreach ($command as $value) {
            $result = $this->_isSecurity ? $this->sendCommandSecurity($value[0], $value[1]) : $this->sendCommand($value[0], $value[1]);
            if($result) {
                continue;
            } else{
                return false;
            }
        }
         
        //其实这里也没必要关闭，smtp命令：QUIT发出之后，服务器就关闭了连接，本地的socket资源会自动释放
        $this->_isSecurity ? $this->closeSecutity() : $this->close();
        return true;
	}
 
    /**
	* getCommand()
	*
    * 返回mail命令
	*
    * @access protected
    * @return array
    */
	protected function getCommand() {
		$separator = "----=_Part_" . md5($this->_from . time()) . uniqid(); //分隔符

		$command = array(
				array("HELO sendmail\r\n", 250)
			);
		if(!empty($this->_userName)) {
			$command[] = array("AUTH LOGIN\r\n", 334);
			$command[] = array($this->_userName . "\r\n", 334);
			$command[] = array($this->_password . "\r\n", 235);
		}

		//设置发件人
		$command[] = array("MAIL FROM: <" . $this->_from . ">\r\n", 250);
		$header = "FROM: <" . $this->_from . ">\r\n";

		//设置收件人
		$to_str = '';
		if(!empty($this->_to)) {
			foreach($this->_to AS $to) {
				if(strlen($to) > 0) {
					$to_str .= ",<$to>";
					$command[] = array("RCPT TO: <$to>\r\n", 250);
				}
			}
			$to_str =  substr($to_str, 1);
			$to_str = "TO: $to_str \r\n";
		}
		$header .= $to_str;

		//设置抄送
		$cc_str = '';
		if(!empty($this->_cc)) {
			foreach($this->_cc AS $cc) {
				if(strlen($cc) > 0) {
					$cc_str .= ",<$cc>";
					$command[] = array("RCPT TO: <$cc>\r\n", 250);
				}
			}
			$cc_str = substr($cc_str, 1);
			$cc_str = "CC: $cc_str \r\n";
		}
		$header .= $cc_str;

        //设置秘密抄送
 		$bcc_str = '';
		if(!empty($this->_bcc)) {
			foreach($this->_bcc AS $bcc) {
				if(strlen($bcc) > 0) {
					$bcc_str .= ",<$bcc>";
					$command[] = array("RCPT TO: <$bcc>\r\n", 250);
				}
			}
			$bcc_str = substr($bcc_str, 1);
			$bcc_str = "BCC: $bcc_str \r\n";
		}
		$header .= $bcc_str;
 
        //主题
        $header .= "Subject: " . $this->_subject ."\r\n";
        if(isset($this->_attachment)) {
            //含有附件的邮件头需要声明成这个
            $header .= "Content-Type: multipart/mixed;\r\n";
        } elseif(false) {
            //邮件体含有图片资源的需要声明成这个
            $header .= "Content-Type: multipart/related;\r\n";
        } else {
            //html或者纯文本的邮件声明成这个
            $header .= "Content-Type: multipart/alternative;\r\n";
        }
 
        //邮件头分隔符
        $header .= "\t" . 'boundary="' . $separator . '"';
        $header .= "\r\nMIME-Version: 1.0\r\n";
        $header .= "\r\n--" . $separator . "\r\n";
        $header .= "Content-Type:text/html; charset=utf-8\r\n";
        $header .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $header .= $this->_body . "\r\n";
        $header .= "--" . $separator . "\r\n";
  
        //加入附件
        if(isset($this->_attachment) && !empty($this->_attachment)) {
            if(is_array($this->_attachment)) {
                $count = count($this->_attachment);
                for($i=0; $i<$count; $i++) {
                    $header .= "\r\n--" . $separator . "\r\n";
                    $header .= "Content-Type: " . $this->getMIMEType($this->_attachment[$i]) . '; name="' . basename($this->_attachment[$i]) . '"' . "\r\n";
                    $header .= "Content-Transfer-Encoding: base64\r\n";
                    $header .= 'Content-Disposition: attachment; filename="' . basename($this->_attachment[$i]) . '"' . "\r\n";
                    $header .= "\r\n";
                    $header .= $this->readFile($this->_attachment[$i]);
                    $header .= "\r\n--" . $separator . "\r\n";
                }
            } else {
                $header .= "\r\n--" . $separator . "\r\n";
                $header .= "Content-Type: " . $this->getMIMEType($this->_attachment) . '; name="' . basename($this->_attachment) . '"' . "\r\n";
                $header .= "Content-Transfer-Encoding: base64\r\n";
                $header .= 'Content-Disposition: attachment; filename="' . basename($this->_attachment) . '"' . "\r\n";
                $header .= "\r\n";
                $header .= $this->readFile($this->_attachment);
                $header .= "\r\n--" . $separator . "\r\n";
            }

        }

         //加入自动附件
        if(!empty($this->_autoattachment)) {
			if(is_array($this->_autoattachment)) {
				foreach($this->_autoattachment AS $autofile) {
					$header .= "\r\n--" . $separator . "\r\n";

					$type = $this->getFileType($autofile['filename']);

					$path_parts = pathinfo($autofile['filename']);
					$filename = $path_parts["basename"];

					$filecode = mb_detect_encoding($filename, "ASCII, UTF-8, GB2312, GBK, BIG5, LATIN1");
					if($filecode != 'UTF-8') {
						$filename = mb_convert_encoding($filename, 'GBK', $filecode);
					}
					//$filename = iconv('UTF-8', 'GBK', $filename);

					$header .= 'Content-Type: '.$type.'; name="' . $filename . '"' . "\r\n";
					$header .= "Content-Transfer-Encoding: base64\r\n";
					$header .= "Content-Disposition: attachment; filename='" . $filename . "'" . "\r\n";
					$header .= "\r\n";
					$header .= base64_encode($autofile['fileconnect']);
					$header .= "\r\n--" . $separator . "\r\n";
				}
			}
		}

        //结束邮件数据发送
        $header .= "\r\n.\r\n";
 
        $command[] = array("DATA\r\n", 354);
        $command[] = array($header, 250);
        $command[] = array("QUIT\r\n", 221);
         
        return $command;
	}
 
    /**
	* socketSecurity
	*
    * 建立到服务器的SSL网络连接
	*
    * @access private
    * @return boolean
    */
    private function socketSecurity() {
        $remoteAddr = "tcp://" . $this->_sendServer . ":" . $this->_port;
        $this->_socket = stream_socket_client($remoteAddr, $errno, $errstr, 30);
        if(!$this->_socket){
            $this->_errorMessage = $errstr;
            return false;
        }
 
        //设置加密连接，默认是ssl，如果需要tls连接，可以查看php手册stream_socket_enable_crypto函数的解释
        stream_socket_enable_crypto($this->_socket, true, STREAM_CRYPTO_METHOD_SSLv23_CLIENT);
 
        stream_set_blocking($this->_socket, 1); //设置阻塞模式
        $str = fread($this->_socket, 1024);
        if(!strpos($str, "220")){
            $this->_errorMessage = $str;
            return false;
        }
 
        return true;
    }
  
    /**
	* readFile
	*
    * 读取附件文件内容，返回base64编码后的文件内容
	*
    * @access protected
    * @param string $file 文件
    * @return mixed
    */
    protected function readFile($file) {
        if(file_exists($file)) {
            $file_obj = file_get_contents($file);
            return base64_encode($file_obj);
        } else {
            $this->_errorMessage = "file " . $file . " dose not exist";
            return false;
        }
    }

    /**
	* socket
	*
    * 建立到服务器的网络连接
	*
    * @access private
    * @return boolean
    */
    private function socket() {
        //创建socket资源
        $this->_socket = socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));
         
        if(!$this->_socket) {
            $this->_errorMessage = socket_strerror(socket_last_error());
            return false;
        }
 
        socket_set_block($this->_socket);//设置阻塞模式
        //连接服务器
        if(!socket_connect($this->_socket, $this->_sendServer, $this->_port)) {
            $this->_errorMessage = socket_strerror(socket_last_error());
            return false;
        }
        $str = socket_read($this->_socket, 1024);
        if(!strpos($str, "220")){
            $this->_errorMessage = $str;
            return false;
        }
         
        return true;
    }
 
    /**
	* sendCommand
	*
    * 发送命令
	*
    * @access protected
    * @param string $command 发送到服务器的smtp命令
    * @param int $code 期望服务器返回的响应吗
    * @return boolean
    */
    protected function sendCommand($command, $code) {
        //发送命令给服务器
        try{
            if(socket_write($this->_socket, $command, strlen($command))) {
 
                //当邮件内容分多次发送时，没有$code，服务器没有返回
                if(empty($code))  {
                    return true;
                }
 
                //读取服务器返回
                $data = trim(socket_read($this->_socket, 1024));
 
                if($data) {
                    $pattern = "/^".$code."/";
                    if(preg_match($pattern, $data)) {
                        return true;
                    } else {
                        $this->_errorMessage = "Error:" . $data . "|**| command:";
                        return false;
                    }
                } else {
                    $this->_errorMessage = "Error:" . socket_strerror(socket_last_error());
                    return false;
                }
            } else {
                $this->_errorMessage = "Error:" . socket_strerror(socket_last_error());
                return false;
            }
        } catch(Exception $e) {
            $this->_errorMessage = "Error:" . $e->getMessage();
        }
    }
 
    /**
	* sendCommandSecurity
	*
    * 发送命令
	*
    * @access protected
    * @param string $command 发送到服务器的smtp命令
    * @param int $code 期望服务器返回的响应吗
    * @return boolean
    */
    protected function sendCommandSecurity($command, $code) {
        try {
            if(fwrite($this->_socket, $command)){
                //当邮件内容分多次发送时，没有$code，服务器没有返回
                if(empty($code))  {
                    return true;
                }
                //读取服务器返回
                $data = trim(fread($this->_socket, 1024));
 
                if($data) {
                    $pattern = "/^".$code."/";
                    if(preg_match($pattern, $data)) {
                        return true;
                    } else{
                        $this->_errorMessage = "Error:" . $data . "|**| command:";
                        return false;
                    }
                } else{
                    return false;
                }
            } else{
                $this->_errorMessage = "Error: " . $command . " send failed";
                return false;
            }
        }catch(Exception $e) {
            $this->_errorMessage = "Error:" . $e->getMessage();
        }
    } 
 
 
    /**
	* close
	*
    * 关闭socket
	*
    * @access private
    * @return boolean
    */
    private function close() {
        if(isset($this->_socket) && is_object($this->_socket)) {
            $this->_socket->socket_close($this->_socket);
            return true;
        }
        $this->_errorMessage = "No resource can to be close";
        return false;
    }
 
    /**
	* closeSecutity
	*
    * 关闭安全socket
	*
    * @access private
    * @return boolean
    */
    private function closeSecutity() {
        if(isset($this->_socket) && is_object($this->_socket)) {
            stream_socket_shutdown($this->_socket, STREAM_SHUT_WR);
            return true;
        }
        $this->_errorMessage = "No resource can to be close";
        return false;
    }

    /**
	* clearMail
	*
    * 清空邮件
	*
    * @access public
    * @return boolean
    */
	public function clearMail() {
		$this->_subject = '';
		$this->_body = '';
		$this->_from = '';
		$this->_to = array();
		$this->_cc = array();
		$this->_bcc = array();
		$this->_attachment = array();
		$this->_autoattachment = array();
		return true;
	}
 
    /**
	* error
	*
    * 返回错误信息
	*
    * @return string
    */
    public function error(){
        if(!isset($this->_errorMessage)) {
            $this->_errorMessage = "";
        }
        return $this->_errorMessage;
    }

	/**
	* strip_comment
	*
	* 字符串转义
	*
	* @param string $address
	* @return string
	*/
	function strip_comment($address) {
		$comment = "/\([^()]*\)/";
		if(preg_match($comment, $address)) {
			$address = preg_replace($comment, "", $address);
		}
		return $address;
	}
 
    /**
	* getMINEType
	*
    * 获取附件MIME类型
	*
    * @access protected
    * @param string $file 文件
    * @return mixed
    */
    protected function getMIMEType($file) {
        if(file_exists($file)) {
			$path_parts = pathinfo($file);
			$mime = $path_parts["extension"];
			switch ($mime) { 
				case "exe" : 
				$ctype = "application/octet-stream"; 
				break; 
				case "zip" : 
				$ctype = "application/zip"; 
				break; 
				case "rar" : 
				$ctype = "application/octet-stream"; 
				break; 
				case "doc" : 
				$ctype = "application/msword"; 
				break; 
				case "xls" : 
				$ctype = "application/vnd.ms-excel"; 
				break; 
				default : 
				$ctype = "application/force-download"; 
			} 
            return $ctype;
        } else {
            return false;
        }
    }
 
    /**
	* getFileType
	*
    * 获取附件MIME类型
	*
    * @access protected
    * @param string $file 文件
    * @return mixed
    */
    protected function getFileType($file) {
		$path_parts = pathinfo($file);
		$mime = $path_parts["extension"];
		switch ($mime) { 
			case "exe" : 
			$ctype = "application/octet-stream"; 
			break; 
			case "zip" : 
			$ctype = "application/zip"; 
			break; 
			case "rar" : 
			$ctype = "application/octet-stream"; 
			break; 
			case "doc" : 
			$ctype = "application/msword"; 
			break; 
			case "xls" : 
			$ctype = "application/vnd.ms-excel"; 
			break; 
			default : 
			$ctype = "application/force-download"; 
		} 
		return $ctype;
    }

}
?>