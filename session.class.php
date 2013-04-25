<?php
/*
// SISTEMA DE SESSAO COM MYSQL
// CRIADO POR RANIELLY FERREIRA
// WWW.RFS.NET.BR
// raniellyferreira@rfs.net.br
// v 3.2.0 EXTENDED
// ULTIMA MODIFICAÇÃO: 25/04/2013

*ACEITO SUGESTÕES


ESTRUTURA DO BANCO DE DADOS

CREATE TABLE IF NOT EXISTS `sess_session` (
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(16) NOT NULL,
  `user_agent` varchar(120) NOT NULL,
  `last_activity` int(11) NOT NULL,
  `user_data` longtext NULL,
  PRIMARY KEY (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


Array com as configurações pre definidas
$params = array('create_table_db' => FALSE,
  			'sess_encrypt_cookie' => TRUE,
				'sess_match_ip' => TRUE,
				'sess_match_useragent' => TRUE,
				'sess_expiration' => 28800,
				'sess_cookie_name' => 'sess_cookie_name',
				'cookie_prefix' => '',
				'cookie_path' => '/',
				'cookie_domain' => '',
				'sess_table_name' => 'sess_session',
				'old_sess_probability' => 5,
				'one_only_session_per_ip' => FALSE,
				'sess_time_to_update' => 300,
				'connection' => NULL,
				'reforce_security' => TRUE,
				'encryption_key' => 'set key',
				'expire_on_close' => FALSE);

*/



class Session 
{
	public $create_table_db				= FALSE;					//CRIAR TABELA AO INICIAR SESSAO, CASO NAO EXISTA
	public $sess_encrypt_cookie			= TRUE;						//CRIPTOGRAFAR COOKIE
	public $sess_match_ip				= TRUE;						//VALIDAR IP
	public $sess_match_useragent		= TRUE;						//VALIDAR NAVEGADOR
	public $sess_expiration				= 28800; 					//TEMPO QUE DURA A SESSAO EM SEGUNDOS 28800 = 8 HORAS. 0 PARA NAO EXPIRAR
	public $sess_cookie_name 			= 'sess_cookie_name'; 		//NOME DO COOKIE
	public $cookie_prefix				= '';						//PREFIXO DO COOKIE
	public $cookie_path					= '/';						//PATCH DO COOKIE
	public $cookie_domain				= '';						//DOMINIO DO COOKIE
	public $sess_table_name				= 'sess_session'; 			//NOME DA TABELA DO BANCO DE DADOS
	public $old_sess_probability 		= 5;						//CHANCE PARA EXCLUIR SESSÕES VELHAS
	public $one_only_session_per_ip		= FALSE;					//APENAS UMA SESSAO POR IP
	public $sess_time_to_update			= 300;						//TEMPO PARA RENOVAÇÃO DO ID DA SESSÃO
	public $reforce_security			= TRUE;						//ADICIONA PROTEÇÃO CONTRA MANIPULAÇÃO DA SESSÃO
	public $encryption_key				= '';						//GERE UMA KEY EM rfs.net.br/gerarkey.php
	public $expire_on_close				= FALSE;					//SESSÃO EXPIRA AO FECHAR O NAVEGADOR
	
	private $cookie 					= ''; 						//NAO ALTERE
	private $id_session 				= ''; 						//NAO ALTERE
	private $new_session_id				= '';						//NAO ALTERE
	private $ip 						= ''; 						//NAO ALTERE
	private $user_agent					= '';						//NAO ALTERE
	private $now						= 0;						//NAO ALTERE
	private $userdata 					= array();					//NAO ALTERE
	private $info 						= array();					//NAO ALTERE
	
	
	/* Query's */
	public $connection 	= NULL;			// Caso não use conexão com o banco por essa classe, adicione a essa variavel o id da conexão,
										// isso irá agilizar as consultas com o banco de dados.
	private $where 		= NULL;			//NÃO ALTERAR
	private $limit		= NULL;			//NÃO ALTERAR
	private $select		= NULL;			//NÃO ALTERAR
	private $from		= NULL;			//NÃO ALTERAR
	
	function __construct($params = array())
	{
		$this->load($params);
		
		if($this->create_table_db)
		{
			$this->query("CREATE TABLE IF NOT EXISTS `$this->sess_table_name` (
				  `session_id` varchar(40) NOT NULL,
				  `ip_address` varchar(20) NOT NULL,
				  `user_agent` varchar(120) NOT NULL,
				  `last_activity` int(11) NOT NULL,
				  `user_data` longtext NULL,
				  PRIMARY KEY (`session_id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8;") or die('Ao criar tabela: '. mysql_error());
		}
		
		$this->sess_cookie_name = $this->cookie_prefix.$this->sess_cookie_name;
		
		$this->now = time();
		$this->ip = $this->ip_address();
		$this->user_agent = $this->user_agent();
		
		if($this->expire_on_close === FALSE)
		{
			if ($this->sess_expiration == 0)
			{
				$this->sess_expiration = (60*60*24*365*2);
			}
		} else
		{
			$this->sess_expiration = 0;
		}
		
		
		if ( ! $this->sess_read() )
		{
			if($this->one_only_session_per_ip)
			{
				$this->where('`ip_address` =',$this->ip);
				$this->limit($this->old_sess_probability);
				$this->delete($this->sess_table_name);
			}
			
			$this->sess_create();
		}
		else
		{
			$this->sess_update();
		}
		
		$this->clear();

	}
	
	public function load($array = array())
	{
		if((bool) ! $array)
		{
			return FALSE;
		}
		
		foreach($array as $k => $v)
		{
			if(isset($this->$k))
			{
				$this->$k = $v;
			}
		}
	}
	
	public function alldata($object = FALSE)
	{
		if(!$object) return $this->userdata;
		
		return $this->ArrayToObject($this->userdata);
	}
	
	public function info($data)
	{
		return $this->info[$data];
	}
	
	public function allinfo($object = FALSE)
	{
		if(!$object) return $this->info;
		
		return $this->ArrayToObject($this->info);
	}
	
	public function userdata($data)
	{
		if(isset($this->userdata[$data]{0}))
		{
			return $this->userdata[$data];
		}
		return FALSE;
	}
	
	public function set_data($nome,$dados = NULL)
	{
		if(is_array($nome))
		{
			foreach ( $nome as $k => $v )
			{
				$this->userdata[$k] = $v;
			}
			
			$this->sess_write();
			return;
		}
		
		$this->userdata[$nome] = $dados;
		$this->sess_write();
		return;
	}
	
	public function set_userdata($nome,$dados = NULL)
	{
		return $this->set_data($nome,$dados);
	}
	
	public function unset_data($newdata = array())
	{
		if (is_string($newdata))
		{
			$newdata = array($newdata);
		}

		if ((bool) $newdata)
		{
			foreach ($newdata as $key)
			{
				unset($this->userdata[$key]);
			}
		}
		
		$this->sess_write();
		return;
	}
	
	private function sess_write()
	{
		$this->where('`session_id` =',$this->id_session);
		$this->limit(1);
		$this->update($this->sess_table_name,array('user_data' => $this->_serialize($this->userdata)));
		return TRUE;
	}
	
	
	private function sess_read()
	{
		if($this->cookie($this->sess_cookie_name) === FALSE) return FALSE;
		
		$info = $this->_unserialize($this->cookie($this->sess_cookie_name));
		
		if ( ! is_array($info) OR ! isset($info['session_id']) OR ! isset($info['ip_address']) OR ! isset($info['user_agent']) OR ! isset($info['last_activity']))
		{
			$this->sess_destroy();
			return FALSE;
		}
		
		if($this->sess_match_ip and $info['ip_address'] =! $this->ip)
		{
			$this->sess_destroy();
			return FALSE;
		}
		
		if($this->sess_match_useragent and $info['user_agent'] =! $this->user_agent)
		{
			$this->sess_destroy();
			return FALSE;
		}
		
		if (($info['last_activity'] + $this->sess_expiration) < $this->now)
		{
			$this->sess_destroy();
			return FALSE;
		}
		
		$this->id_session = $info['session_id'];
		
		$where = array();
		$where['`session_id` ='] = $this->id_session;
		
		if($this->sess_match_ip)
		{
			$where['`ip_address` ='] = $this->ip;
		}
		
		if($this->sess_match_useragent)
		{
			$where['`user_agent` ='] = $this->user_agent;
		}
		
		$this->select('`user_data`');
		$this->where($where);
		$this->limit(1);
		$query = $this->get($this->sess_table_name);
		if($this->num_rows($query) == 0)
		{
			$this->sess_destroy();
			return FALSE;
		}
		
		$db_data = $this->item($query,'user_data');
		
		if($this->notnull($db_data) and $db_data != '')
		{
			$custom_data = $this->_unserialize($db_data);
			unset($db_data);

			if (is_array($custom_data))
			{
				foreach ($custom_data as $key => $val)
				{
					$session[$key] = $val;
				}
			}
		} else
		{
			$session = NULL;
		}
		
		$this->userdata = $session;
		
		$this->info = $info;
		
		unset($session,$info);
		return TRUE;
	}
	
	private function sess_update()
	{
		if(($this->info['last_activity'] + $this->sess_expiration) < $this->now)
		{
			$this->sess_destroy();
			return FALSE;
		}
		

		if(($this->info['last_activity'] + $this->sess_time_to_update) < $this->now)
		{
			$this->new_session_id = $this->new_id();
		}
		
		$this->info['last_activity'] = $this->now;
		
		$dados = array();
		
		if((bool) !$this->new_session_id)
		{
			$dados['last_activity'] = $this->now;
			$where = array('`session_id` =' => $this->id_session);
		} else
		{
			$dados['last_activity'] = $this->now;
			$dados['session_id'] = $this->new_session_id;
			$where = array('`session_id` =' => $this->id_session);
			
			$this->id_session = $this->new_session_id;
			$this->info['session_id'] = $this->new_session_id;
			$this->new_session_id = NULL;
		}
		
		$this->where($where);
		$this->limit(1);
		$this->update($this->sess_table_name,$dados);
		
		$this->_set_cookie();
		
		return;
	}
	
	private function sess_create()
	{
		//GERA ID DA SESSAO
		$this->id_session = $this->new_id();
		
		$this->info = array(
		'session_id' => $this->id_session,
		'ip_address' => $this->ip,
		'user_agent' => $this->user_agent,
		'last_activity' => $this->now,
		'created' => $this->now
		);
		
		$dados = array();
		$dados['session_id'] = $this->id_session;
		$dados['ip_address'] = $this->ip;
		$dados['user_agent'] = $this->user_agent;
		$dados['last_activity'] = $this->now;
		
		if($this->insert($this->sess_table_name,$dados))
		{
			$this->_set_cookie();
			
			return TRUE;
		}
		return FALSE;
	}
	
	public function sess_destroy()
	{
		if(!$this->notnull($this->id_session)) return FALSE;
		
		$this->where('`session_id` =',$this->id_session);
		$this->limit(1);
		$this->delete($this->sess_table_name);
		
		$this->userdata = NULL;
		$this->info = NULL;
		setcookie(
					$this->sess_cookie_name,
					addslashes(serialize(array())),
					($this->now - 31500000),
					$this->cookie_path,
					$this->cookie_domain,
					0
				);
		return TRUE;
	}
	
	public function destroy()
	{
		return $this->sess_destroy();
	}
	
	private function clear()
	{
		if((rand() % 100) < $this->old_sess_probability)
		{
			$tempo = $this->now - $this->sess_expiration;
			$this->where('`last_activity` <',$tempo);
			$this->delete($this->sess_table_name);
		}
		return;
	}
	
	function new_id()
	{
		return sha1(time().$this->gerarkey(60,3).uniqid(time().rand(),TRUE));
	}
	
	function ip_address()
	{
		if(isset($_SERVER['REMOTE_ADDR']{5})) return $_SERVER['REMOTE_ADDR'];
		return '127.0.0.1';
	}
	
	function decode($var)
	{
		return base64_decode($var);
	}
	
	function encode($var)
	{
		return base64_encode($var);
	}
	
	function cookie($name = NULL)
	{
		if($name === NULL) $name = $this->sess_cookie_name;
		if(!isset($_COOKIE[$name])) return FALSE;
		
		$theCookie = $_COOKIE[$name];
		
		if($this->reforce_security)
		{
			$this->_valid_security();
			
			$thekey	 	= substr($theCookie, strlen($theCookie)-40);
			$theCookie 	= substr($theCookie, 0, strlen($theCookie)-40);
			
			if($thekey != sha1($theCookie.$this->encryption_key))
			{
				$this->destroy();
				return FALSE;
			}
		}
		
		if($this->sess_encrypt_cookie)
		{
			return $this->decode($theCookie);
		}
		
		return $theCookie;
		
	}
	
	function _set_cookie($data = NULL)
	{
		if($data === NULL)
		{
			$data = $this->info;
		}
		
		
		if ($this->sess_encrypt_cookie == TRUE)
		{
			$cookie_data = $this->encode($this->_serialize($data));
		} else
		{
			$cookie_data = $this->_serialize($data);
		}
		
		if($this->reforce_security)
		{
			$this->_valid_security();
			$cookie_data .= sha1($cookie_data.$this->encryption_key);
		}
		
		setcookie(
					$this->sess_cookie_name,
					$cookie_data,
					($this->now + $this->sess_expiration),
					$this->cookie_path,
					$this->cookie_domain
		);
	}
	
	private function _valid_security()
	{
		if($this->reforce_security === FALSE) return FALSE;
		
		if((bool) ! $this->encryption_key)
		{
			return exit("Please, set the encryption key in encryption_key parameter.");
		}
	}
	
	
	function _serialize($data)
	{
		if (is_array($data))
		{
			foreach ($data as $key => $val)
			{
				if (is_string($val))
				{
					$data[$key] = str_replace('\\', '{{slash}}', $val);
				}
			}
		}
		else
		{
			if (is_string($data))
			{
				$data = str_replace('\\', '{{slash}}', $data);
			}
		}

		return serialize($data);
	}
	
	function _unserialize($data)
	{
		$data = @unserialize($this->strip_slashes($data));

		if (is_array($data))
		{
			foreach ($data as $key => $val)
			{
				if (is_string($val))
				{
					$data[$key] = str_replace('{{slash}}', '\\', $val);
				}
			}

			return $data;
		}

		return (is_string($data)) ? str_replace('{{slash}}', '\\', $data) : $data;
	}
	
	function strip_slashes($str)
	{
		if (is_array($str))
		{
			foreach ($str as $key => $val)
			{
				$str[$key] = $this->strip_slashes($val);
			}
		}
		else
		{
			$str = stripslashes($str);
		}

		return $str;
	}
	
	function notnull($var)
	{
		if((bool) trim($var)) return TRUE; else	return FALSE;
	}
	
	
	function gerarkey($length = 40,$type = 1) 
	{
		$key = NULL;
		switch($type)
		{
			case 2:
			$pattern = '!#$%&()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~';
			$len = 91;
			break;
			
			case 3:
			$pattern = '!"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~¡¢£¤¥¦§¨©ª«¬­®¯°±²³´µ¶·¸¹º»¼½¾¿ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõö÷øùúûüýþÿ';
			$len = 284;
			break;
			
			case 1:
			default:
			$pattern = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRTWXYZ';
			$len = 58;
			break;
		}
		
		for( $i = 0; $i < $length; ++$i )
		{
			$key .= $pattern{rand(0,$len)};
		}
		return $key;
	}
	
	function ArrayToObject($array)
	{
		$return = new stdClass();
		foreach ($array as $k => $v) 
		{
			if (is_array($v)) 
			{
				$return->$k = $this->ArrayToObject($v);
			}
			else 
			{
				$return->$k = $v;
			}
		}
	}
	
	function user_agent()
	{
		return substr($_SERVER['HTTP_USER_AGENT'],0,120);
	}
	
	/*********** QUERYS FUNCTIONS ***********/
	
	public function query($sql)
	{
		if($this->connection === NULL)
		{
			return mysql_query($sql);
		} else
		{
			return mysql_query($sql,$this->connection);
		}
	}
	
	public function item($query,$column,$line = 0)
	{
		return mysql_result($query,$line,$column);
	}
	
	public function num_rows($query)
	{
		return mysql_num_rows($query);
	}
	
	/*********** FIM QUERYS FUNCTIONS ***********/
	
	
	public function insert($table,$dados = NULL)
	{
		if(is_null($dados))
		{
			return FALSE;
		}
		
		$sqlk = NULL;
		$sqlv = NULL;
		$first = TRUE;
		
		foreach($dados as $k => $v)
		{
			if(!$first)
			{
				$sqlk .= ', `'.$k.'`';
				$sqlv .= ", '".$v."'";
				
			} else
			{
				$sqlk .= '`'.$k.'`';
				$sqlv .= "'".$v."'";
				$first = FALSE;
			}
		}
		
		return $this->query($this->sql($table,'insert',$sqlk,$sqlv));
	}
	
	public function update($table,$dados,$where = NULL)
	{
		
		if(!is_array($dados))
		{
			return FALSE;
		}
		
		if(is_array($where) and (bool) $where)
		{
			foreach($where as $k => $v)
			{
				$this->where($k,$v);
			}
		}
		
		$set = NULL;
		$first = TRUE;
		foreach($dados as $k => $v)
		{
			if(!$first)
			{
				$set .= ', `'.$k."` = '".$v."'";
			} else
			{
				$set .= ' `'.$k."` = '".$v."'";
				$first = FALSE;
			}
		}
		
		return $this->query($this->sql($table,'update',$set));
	}
	
	public function get($table = NULL,$where = NULL)
	{
		if(is_null($table) and is_null($this->from))
		{
			return FALSE;
		}
		
		if(is_array($where) and (bool) $where)
		{
			foreach($where as $k => $v)
			{
				$this->where($k,$v);
			}
		}
		
		if(is_null($table))
		{
			$table = $this->from;
		}
		
		return $this->query($this->sql($table,'select'));
	}
	
	public function delete($table,$where = NULL)
	{
		if(is_array($where) and (bool) $where)
		{
			foreach($where as $k => $v)
			{
				$this->where($k,$v);
			}
		}
		return $this->query($this->sql($table,'delete'));
	}
	
	private function sql($table,$action,$first = NULL,$second = NULL)
	{
		$action = strtoupper(trim($action));
		$sql = NULL;
		switch($action)
		{
			case 'UPDATE';
			{
				$sql .= "UPDATE (`".$table."`) SET ".$first;
			}
			break;
			
			case 'SELECT':
			{
				if(is_null($this->select))
				{
					$sql .= 'SELECT * FROM (`'.$table.'`)';
				} else
				{
					$sql .= 'SELECT '.$this->select.' FROM (`'.$table.'`)';
				}
			}
			break;
			
			case 'INSERT':
			{
				$sql .= 'INSERT INTO `'.$table.'` ('.$first.') VALUES ('.$second.')';
				$this->clean();
				return $sql;
			}
			break;
			
			case 'DELETE':
			{
				$sql .= 'DELETE FROM `'.$table.'`';
			}
			break;
			
			default:
			return FALSE;
			break;
		}
		
		if(!is_null($this->where))
		{
			$sql .= "\nWHERE ".$this->where;
		}
		
		if(!is_null($this->limit))
		{
			$sql .= "\n".$this->limit;
		}
		$this->clean();
		return $sql;
	}
	
	public function from($table = NULL)
	{
		$this->from = preg_replace("/([^a-zA-Z0-9_-])/is",NULL,$table);
		return $this;
	}
	
	public function select($tables)
	{
		if($tables == '*')
		{
			$this->select = '*';
			return $this;
		}
		
		
		if($this->select === NULL)
		{
			$this->select = $tables;
		} else
		{
			$this->select .= ','.$tables;
		}
		return $this;
	}
	
	public function limit($limite,$inicio = 0)
	{
		$this->limit = 'LIMIT '.$limite;
		return TRUE;
	}
	
	public function where($key,$value = NULL,$fetch = 'AND')
	{
		if(!is_array($key))
		{
			if($this->where === NULL)
			{
				$this->where = $key." '".$value."'";
			} else
			{
				$this->where .= "\n".$fetch.' '.$key." '".$value."'";
			}
		} else
		{
			foreach($key as $k => $v)
			{
				$this->where($k,$v,$fetch);
			}
		}
		return $this;
	}
	
	public function clean()
	{
		$this->where 	= NULL;
		$this->limit	= NULL;
		$this->select	= NULL;
		$this->from		= NULL;
		return $this;
	}
}

?>
