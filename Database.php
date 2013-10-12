<?php

class Database
{
	
	private static $connection	= FALSE; //connection to be opened
	private static $mysqli		= NULL;
	
	//Database Information
	private static $db_host			= NULL;
	private static $db_name			= NULL;
	private static $db_user			= NULL;
	private static $db_pass			= NULL;
	private static $db_table_prefix	= NULL;

	public static function init($db_host, $db_name, $db_user, $db_pass, $prefix)
	{
		self::$db_table_prefix	= $prefix;
		self::$mysqli			= new mysqli($db_host, $db_user, $db_pass, $db_name);
	}

	public static function query($query_string)
	{
		//performs query over alerady opened connection, if not open, it opens connection 1st
	}
	
	public static function get()
	{
		return self::$mysqli;
	}
	
	public static function prefix()
	{
		return self::$db_table_prefix;
	}

	public static function create($table = NULL, $query = NULL)
	{
		if($table == NULL || $query == NULL) return;

		$sql = "CREATE TABLE IF NOT EXISTS ".self::$db_table_prefix."$table ($query)";
		if(self::$mysqli->prepare($sql)->execute())	return TRUE;
		else										return FALSE;
	}

	public static function delete($table = NULL, $statement = NULL, $values = NULL)
	{
		if($table == NULL || $statement == NULL || $values == NULL ) return;
		
		if(!is_array($values)) $values	= (array)($values);
		
		$succ	= TRUE;
				
		$stmt	= self::$mysqli->prepare("DELETE FROM ".self::$db_table_prefix.$table." WHERE $statement");
		
		foreach($values as $value)
		{
								$type		= "s";
			if(is_int($value))	$type		= "i";

			$stmt->bind_param($type, $value);
			$succ &= $stmt->execute();
		}
		
		$stmt->close();
		
		return $succ;		
	}
	
	public static function join($table = NULL, $attrs = NULL, $joins = NULL/*$condition = NULL, $values = NULL*/)
	{		
		if($table == NULL || $attrs == NULL)		return  array();

		$result = array();
		
		if(!is_array($attrs)) $attrs	= (array)($attrs);
		if(!is_array($joins)) $joins	= (array)($joins);

		$key = NULL;
		
		foreach($attrs as $k => $value)
		{
			$search = strtr($value, array('[' => '', ']' => ''));
			
			if($search != $value)
			{
				$key		= $search;
				$attrs[$k]	= $search;
			}
			$attrs[$k]=str_replace("{prefix}", self::$db_table_prefix, $value);
		}

		$sql = "SELECT DISTINCT ".implode(", ", $attrs)." FROM ".self::$db_table_prefix.$table;
		
		foreach($joins as $from => $data)
		{
			$on					= $data[0];
			$values				= $data[1];
						
			if(!is_array($values)) $values = (array)($values);
			
			$condition_string	= str_replace(array("?","="," "), "", $on)." = ";
			$finds				= array_filter(explode('\?', $on));
			$values				= array_unique($values);
			asort($values);
			
			$values =	array_map
						(
							function ($el)
							{
								$strict = strtr($el, array('!' => '', '!' => ''));
								if($strict != $el)	return "{$strict}";
								else				return "'{$el}'";
							}
							, $values
						);
			
			if (count($finds) == 1)
				$finds[1] = implode(" OR $condition_string", $values);

			foreach($finds as $k => $find)
			{
				if($find == "''")	$finds[$k]="";
				else				$finds[$k] = str_replace("{prefix}", self::$db_table_prefix, $find);
			}

			$finds	= str_replace("?", "", implode('', $finds));

			$sql.=" INNER JOIN ".str_replace("{prefix}", self::$db_table_prefix, $from)." ON $finds";
		}
		
		//echo "<pre style='font-weight: bold;'>$sql</pre>";
	
		$stmt	= self::$mysqli->prepare($sql);
		
		$stmt->execute();
		$stmt->store_result();

		
		$variables	= array();
		$data		= array();
		$meta		= $stmt->result_metadata();

		while($field = $meta->fetch_field())
			$variables[] = &$data[$field->name];

		call_user_func_array(array($stmt, 'bind_result'), $variables);

		if($key==NULL)
		{

			$i = 0;
			while($stmt->fetch())
			{
				$result[$i] = array();
				foreach($data as $k=>$v)
					$result[$i][$k] = $v;
				$i++;
			}
		}
		else
		{
			$pointer = 0;
			while($stmt->fetch())
			{
				$temp = array();
				foreach($data as $k=>$v)
				{
					if($key==$k)
						$pointer=$v;
						
					$temp[$k] = $v;
				}
				$result[$pointer]=$temp;
			}
		}	
		
		$stmt->close();

		return $result;
	}
	
	
	public static function entryExists($table = NULL, $condition = NULL, $values = NULL)
	{
		if($table == NULL || $condition == NULL || $values == NULL) return FALSE;
		
		if(!is_array($values)) $values = (array)($values);

//		echo "<pre>SELECT * FROM ".self::$db_table_prefix.$table." WHERE $condition LIMIT 1</pre>";
		
		$stmt	= self::$mysqli->prepare("SELECT * FROM ".self::$db_table_prefix.$table." WHERE $condition LIMIT 1");
		
		foreach($values as $value)
		{
								$type		= "s";
			if(is_int($value))	$type		= "i";

			$stmt->bind_param($type, $value);
		}
		
		$stmt->execute();
		$stmt->store_result();
		$count=$stmt->num_rows;
		$stmt->close();

		return $count>0;
	}
	
	public static function select($table = NULL, $attrs = NULL, $condition = NULL, $values = NULL)
	{
		$heal_check = ($values == NULL || (is_array($values) && count($values)<1));
		
		if($table == NULL || $attrs == NULL)	return  array();
		if($condition != NULL && ($heal_check))	return array();
			
		$result = array();
		
		if(!is_array($attrs))
		{
			if($attrs==NULL)	$arrts  = array();
			else				$attrs	= (array)($attrs);	
		}
		if(!is_array($values))
		{
			if($values==NULL)	$values = array();
			else				$values	= (array)($values);
		}
		
		$key = NULL;
		
		foreach($attrs as $k => $value)
		{
			$search = strtr($value, array('[' => '', ']' => ''));
			if($search != $value)
			{
				$key=$search;
				$attrs[$k]=$search;
			}
		}
		
		if($condition == NULL || $heal_check == TRUE)
		{
			$stmt	= self::$mysqli->prepare("SELECT ".implode(", ", $attrs)." FROM ".self::$db_table_prefix.$table);
//			echo "<pre>YYY SELECT ".implode(", ", $attrs)." FROM ".self::$db_table_prefix.$table."</pre>";
		}
		else
		{
			$condition_string	= str_replace(array("?","="," "), "", $condition)." = ";
			$finds				= explode('\?', $condition);			
			$values				= array_unique($values);
			asort($values);
			
			$values = array_map(function ($el) { return "'{$el}'"; }, $values);
			
			if (count($finds) == 1)
				$finds[1] = implode(" OR $condition_string", $values);

			$finds	= str_replace("?", "", implode('', $finds));
			$stmt	= self::$mysqli->prepare("SELECT ".implode(", ", $attrs)." FROM ".self::$db_table_prefix.$table." WHERE $finds");
		//	echo "<pre>XXX SELECT ".implode(", ", $attrs)." FROM ".self::$db_table_prefix.$table." WHERE $finds"."</pre>";
		}
		
		$stmt->execute();

		$variables	= array();
		$data		= array();
		$meta		= $stmt->result_metadata();

		while($field = $meta->fetch_field())
			$variables[] = &$data[$field->name];

		call_user_func_array(array($stmt, 'bind_result'), $variables);

		if($key==NULL)
		{
			$i = 0;
			while($stmt->fetch())
			{
				$result[$i] = array();
				foreach($data as $k=>$v)
					$result[$i][$k] = $v;
				$i++;
			}
		}
		else
		{
			$pointer = 0;
			while($stmt->fetch())
			{
				$temp = array();
				foreach($data as $k=>$v)
				{
					if($key==$k)
						$pointer=$v;
						
					$temp[$k] = $v;
				}
				$result[$pointer]=$temp;
			}
		}	
		
		$stmt->close();

		return $result;
	}
	
	public static function insert($table = NULL, $values = NULL)
	{
		if($table == NULL || $values == NULL || !is_array($values) || count($values) < 1) return;
	
		$succ	= TRUE;
		
		if(count($values) != count($values, COUNT_RECURSIVE))
		{
			$stmt	= self::$mysqli->prepare("INSERT INTO ".self::$db_table_prefix.$table." (" . implode(", ", array_keys($values[0])) . ") VALUES (" . implode(", ", array_map(function($value) { return "?"; }, $values[0])) . ")");
			
			foreach($values as $value)
			{
				$bind		= new Param(); 
				$qArray		= array();
			
				foreach($value as $key => $line)
				{
										$qArray[]	= "$key = ?"; 
										$type		= "s";
					if(is_int($line))	$type		= "i";
				
					$bind->add($type, $line);
				}
				
				call_user_func_array( array($stmt, 'bind_param'), $bind->get());
				
				$succ &= $stmt->execute();
			}
		
			$stmt->close();
		}
		else
		{
			$stmt		= self::$mysqli->prepare("INSERT INTO ".self::$db_table_prefix.$table." (" . implode(", ", array_keys($values)) . ") VALUES (" . implode(", ", array_map(function($value) { return "?"; }, $values)) . ")");
			$bind		= new Param(); 
			$qArray		= array();

			foreach($values as $key => $line)
			{
									$qArray[]	= "$key = ?"; 
									$type		= "s";
				if(is_int($line))	$type		= "i";

				$bind->add($type, $line);
			}
				
			call_user_func_array( array($stmt, 'bind_param'), $bind->get());
			
			$succ &= $stmt->execute();

			$stmt->close();
		}
		
		return $succ;
	}
	
	public static function update($table = NULL, $where_statement = NULL, $where_values = NULL, $update_statement = NULL, $update_value = NULL)
	{

//		echo "0";
		if($table == NULL || $where_statement == NULL || $update_statement == NULL || $update_value == NULL || $where_values == NULL )
		{
//					echo "1";
//			echo ($table==NULL)?"<pre>table is null</pre>":"";
//			echo ($where_statement==NULL)?"<pre>where statement is null</pre>":"";
//			echo ($update_statement==NULL)?"<pre>update statement is null</pre>":"";
//			echo ($update_value==NULL)?"<pre>update value is null</pre>":"";
//			echo ($where_values==NULL)?"<pre>where values is null</pre>":"";
			return false;	
		}
		
	//	echo "A";
		if(!is_array($where_values)) $where_values = (array)($where_values);
	//	echo "B";		
		$succ	= TRUE;
	//	echo "C";
//		echo "<pre>UPDATE ".self::$db_table_prefix.$table." SET ".$update_statement." WHERE $where_statement</pre>";
		$stmt		= self::$mysqli->prepare("UPDATE ".self::$db_table_prefix.$table." SET ".$update_statement." WHERE $where_statement");
		$bind		= new Param(); 
		$qArray		= array();

									$update_type		= "s";
		if(is_int($update_value))	$update_type		= "i";
				
		foreach($where_values as $key => $line)
		{
								$qArray[]	= "$key = ?"; 
								$type		= "s";
			if(is_int($line))	$type		= "i";

			$bind->add($update_type, $update_value);
			$bind->add($type, $line);
		}
				
		call_user_func_array(array($stmt, 'bind_param'), $bind->get());
			
		$succ &= $stmt->execute();
		
		$stmt->close();
		
		return $succ;		
	}
	
	public static function alter($table = NULL, $alters = NULL)
	{
		if($table == NULL || $alters == NULL) return;

		$succ = TRUE;
		
		foreach($alters as $key => $value)
		{
			$succ &= self::$mysqli->query("ALTER TABLE ".self::$db_table_prefix."$table $key = $value");
		}
		
		return $succ;
	}
	
}

class Param
{ 
    private $values = array(), $types = ''; 

    public function add( $type, &$value )
	{
		$this->values[]	 = $value; 
		$this->types	.= $type; 
    } 

    public function get()
	{
		$arr	= array_merge(array($this->types), $this->values);
		$refs	= array();

		foreach ($arr as $key => $value)
		{
			$refs[$key] = &$arr[$key]; 
		}

		return $refs; 
    }

}

?>