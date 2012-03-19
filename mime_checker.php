<?php
class MimeTypeDefinition {
	var $start;
	var $type;
	var $pattern;
	var $mime;

	function  __construct($start, $type, $pattern, $mime)
	{
		$this->start = $start;
		$this->type = $type;
		$this->pattern = $pattern;
		$this->mime = $mime;
	}
}
class MimeChecker {
	var $data = null;
	var $types = array();
	
	function  __construct()
	{
	}
	
	function parse($filepath)
	{
		if((!file_exists($filepath)) || (is_dir($filepath)))
		{
			return "mimeタイプの定義ファイルが見つかりません。";
		}
		
		$this->data = file($filepath);

		$types = array(
			'byte','short','long','string','date',
			'beshort','belong','bedate',
			'leshort','lelong','ledate'
		);

		$ptn_types = implode('|', $types);
		
		foreach($this->data as $line)
		{
			if(preg_match('/^#/', $line))
			{	
				continue;
			}
			
			if(preg_match(
				'/^(>?\d+)\s+(' . $ptn_types . ')\s+((\x5c |[^\s])*)\s+([^\s]+)?/',
				 $line, $match) == 0)
			{
				continue;
			}
			else
			{
				if(empty($match[5]))
				{
					$match[5] = null;
				}
				
				$this->types[] = new MimeTypeDefinition($match[1], $match[2], $match[3], $match[5]);
			}
		}
		
		return false;
	}
	
	function getMime($path)
	{
		if(empty($this->data))
		{
			return false;
		}
		
		if((!file_exists($path)) || (is_dir($path)))
		{
			return false;
		}
		
		$mime = null;
		$fp = fopen($path, "rb");
		
		$count = count($this->types);

		for($i = 0; $i < $count;)
		{
			$start = $this->types[$i]->start;
			
			if(preg_match('/^\d+$/', $start) > 0)
			{
				$mime = $this->checkMime($fp, $i);
				$i++;
				
				if($mime)
				{
					return trim($mime);
				}
			}
			else
			{
				$i++;
			}
		}
		
		if(!$mime)
		{
			return 'application/octet-stream';
		}
	}
	
	function checkMime($fp, $i)
	{
		$ismatch = false;
		
		switch($this->types[$i]->type)
		{
			case "byte" : 
				$ismatch = $this->checkByte($fp, $this->types[$i]);
			break;
			case "short" : 
				$ismatch = $this->checkShort($fp, $this->types[$i]);
			break;
			case "long" : 
				$ismatch = $this->checkLong($fp, $this->types[$i]);
			break;
			case "string" : 
				$ismatch = $this->checkStr($fp, $this->types[$i]);
			break;
			case "date" : 
				$ismatch = $this->checkTime($fp, $this->types[$i]);
			break;
			case "beshort" : 
				$ismatch = $this->checkBeShort($fp, $this->types[$i]);
			break;
			case "belong" : 
				$ismatch = $this->checkBeLong($fp, $this->types[$i]);
			break;
			case "bedate" : 
				$ismatch = $this->checkBeTime($fp, $this->types[$i]);
			break;
			case "leshort" : 
				$ismatch = $this->checkLeShort($fp, $this->types[$i]);
			break;
			case "lelong" : 
				$ismatch = $this->checkLeLong($fp, $this->types[$i]);
			break;
			case "ledate" : 
				$ismatch = $this->checkLeTime($fp, $this->types[$i]);
			break;
		}
		
		if((!$ismatch) && (preg_match('/^\d+$/', $this->types[$i]->start) > 0))
		{
			return null;
		}
		
		if(isset($this->types[$i]->mime))
		{
			return $this->types[$i]->mime;
		}
		
		$count = count($this->types);
		
		for($i++; $i < $count; $i++)
		{
			if(preg_match('/^\d+$/', $this->types[$i]->start) > 0)
			{
				return null;
			}
			else
			{
				$mime = $this->checkMime($fp, $i);
				
				if($mime)
				{
					return $mime;
				}
			}
		}
	}
	
	function checkByte($fp, $type)
	{
		$start = $type->start;
		
		if($type->pattern == "x")
		{
			return true;
		}
		else if(preg_match('/^(=|<|>|&|^|~)?(0?x?[\da-f]+)$/i', $type->pattern, $match) == 0)
		{
			return false;
		}
		
		if(empty($match[1]))
		{
			$match[1] = null;
		}
		
		fseek($fp, $start);
		$data = fread($fp, 1);
		
		return $this->checkInt($match[1], $match[2], $data);
		
	}
	
	function checkShort($fp, $type)
	{
		$start = $type->start;
		
		if($type->pattern == "x")
		{
			return true;
		}
		else if(preg_match('/^(=|<|>|&|^|~)?(0?x?[\da-f]+)$/i', $type->pattern, $match) == 0)
		{
			return false;
		}
		
		if(empty($match[1]))
		{
			$match[1] = null;
		}
		
		fseek($fp, $start);
		$data = fread($fp, 2);
		
		return $this->checkInt($match[1], $match[2], $data);
		
	}
	
	function checkLong($fp, $type)
	{
		$start = $type->start;
		
		if($type->pattern == "x")
		{
			return true;
		}
		else if(preg_match('/^(=|<|>|&|^|~)?(0?x?[\da-f]+)$/i', $type->pattern, $match) == 0)
		{
			return false;
		}
		
		if(empty($match[1]))
		{
			$match[1] = null;
		}
		
		fseek($fp, $start);
		$data = fread($fp, 4);
		
		return $this->checkInt($match[1], $match[2], $data);
		
	}
	
	function checkInt($mode, $num, $data, $packfmt = null)
	{
		if(preg_match('/^0x([\da-f]+)$/i', $num, $match) > 0)
		{
			$num = intval($match[1], 16);
		}
		else if(preg_match('/^0(\d+)$/', $num, $match) > 0)
		{
			$num = intval($match[1], 8);
		}
		else if(preg_match('/^(\d+)$/', $num, $match) > 0)
		{
			$num = intval($num, 10);
		}
		else
		{
			return false;
		}
		
		if($packfmt)
		{
			switch($packfmt)
			{
				case "n" :
				break;
				
				case "N" :
				break;
				
				case "v" :
				break;
				
				case "V" :
				break;
				
				default:
				 return false;
			}
			
			$num = pack($packfmt, $num);
		}
		
		if(empty($mode) || ($mode == "="))
		{
			if($data == $num)
			{
				return true;
			}
		}
		else if($mode == "<")
		{
			if($data < $num)
			{
				return true;
			}
		}
		else if($mode == ">")
		{
			if($data > $num)
			{
				return true;
			}
		}
		else if($mode == "&")
		{
			if(($data & $num) == $num)
			{
				return true;
			}
		}
		else if($mode == "^")
		{
			if(($data & $num) != $num)
			{
				return true;
			}
		}
		else if($mode == "~")
		{
			if($data == ~$num)
			{
				return true;
			}
		}
		
		return false;
	}
	
	function checkStr($fp, $type)
	{
		$start = $type->start;

		if(preg_match('/^(=|<|>)?((\x5c |[^\s])*)$/i', $type->pattern, $match) == 0)
		{
			return false;
		}
		
		if(empty($match[1]))
		{
			$match[1] = null;
		}
		
		$match[2] = preg_replace('/\x5c /', ' ', $match[2]);
		$match[2] = preg_replace('/\x5c</', '<', $match[2]);
		$match[2] = preg_replace('/\x5c>/', '>', $match[2]);
		$match[2] = preg_replace('/\x5c=/', '=', $match[2]);
		
		$ptn = $this->unescape($match[2]);
		$len = strlen($ptn);
		
		fseek($fp, $start);
		$data = fread($fp, $len);
		
		if(empty($match[1]) || ($match[1] == "="))
		{
			if(($match[0] == '=\0') && ($data == ""))
			{
				return true;
			}
			else if($data == $ptn)
			{
				return true;
			}
			else
			{
				return false;
			}
			
		}
		else if($match[1] == "<")
		{
			if(strcmp($data, $ptn) < 0)
			{
				return true;
			}
			else
			{
				return false;
			}
		}
		else if($match[1] == ">")
		{
			if(($match[0] == '>\0') && ($data != ""))
			{
				return true;
			}
			else if(strcmp($data, $ptn) > 0)
			{
				return true;
			}
			else
			{
				return false;
			}
		}
	}
	
	function checkTime($fp, $type)
	{
		$start = $type->start;

		fseek($fp, $start);
		$data = fread($fp, 4);

		if(intval($data) == intval($this->pattern))
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	function checkBeShort($fp, $type)
	{
		$start = $type->start;
		
		if($type->pattern == "x")
		{
			return true;
		}
		else if(preg_match('/^(=|<|>|&|^|~)?(0?x?[\da-f]+)$/i', $type->pattern, $match) == 0)
		{
			return false;
		}
		
		if(empty($match[1]))
		{
			$match[1] = null;
		}
		
		fseek($fp, $start);
		$data = fread($fp, 2);
		
		return $this->checkInt($match[1], $match[2], $data, "n");
		
	}
	
	function checkBeLong($fp, $type)
	{
		$start = $type->start;
		
		if($type->pattern == "x")
		{
			return true;
		}
		else if(preg_match('/^(=|<|>|&|^|~)?(0?x?[\da-f]+)$/i', $type->pattern, $match) == 0)
		{
			return false;
		}
		
		if(empty($match[1]))
		{
			$match[1] = null;
		}
		
		fseek($fp, $start);
		$data = fread($fp, 4);
		
		return $this->checkInt($match[1], $match[2], $data, "N");
		
	}
	
	function checkBeTime($fp, $type)
	{
		$start = $type->start;

		fseek($fp, $start);
		$data = fread($fp, 4);

		if(pack("N", intval($data)) == intval($this->pattern))
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	function checkLeShort($fp, $type)
	{
		$start = $type->start;
		
		if($type->pattern == "x")
		{
			return true;
		}
		else if(preg_match('/^(=|<|>|&|^|~)?(0?x?[\da-f]+)$/i', $type->pattern, $match) == 0)
		{
			return false;
		}
		
		if(empty($match[1]))
		{
			$match[1] = null;
		}
		
		fseek($fp, $start);
		$data = fread($fp, 2);
		
		return $this->checkInt($match[1], $match[2], $data, "v");
		
	}
	
	function checkLeLong($fp, $type)
	{
		$start = $type->start;
		
		if($type->pattern == "x")
		{
			return true;
		}
		else if(preg_match('/^(=|<|>|&|^|~)?(0?x?[\da-f]+)$/i', $type->pattern, $match) == 0)
		{
			return false;
		}
		
		if(empty($match[1]))
		{
			$match[1] = null;
		}
		
		fseek($fp, $start);
		$data = fread($fp, 4);
		
		return $this->checkInt($match[1], $match[2], $data, "V");
		
	}
	
	function checkLeTime($fp, $type)
	{
		$start = $type->start;

		fseek($fp, $start);
		$data = fread($fp, 4);

		if(pack("V", intval($data)) == intval($this->pattern))
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	function unescape($str)
	{
		$ptn = array(
			'\d{3}', 'x[\da-f]{2}', '0', '01', '02', '03', '04', '05', '06',
			'a', 'b', 't', 'n', 'v', 'f','r', '\x5c', '\x3f', '"', '\x27', 
		);
		$ptn = implode('|', $ptn);
		
		return preg_replace_callback('/\x5c(' . $ptn . ')/i', //'\\'と記述しても正常に動作しないため、\x5cと記述
			create_function('$match', 'return MimeChecker::unescapesub($match[1]);'),
			$str);
	}
	
	static function unescapesub($match)
	{
		switch($match)
		{
			case "0" :
				return "\0";
			break;
			case "01" :
				return "\01";
			break;
			case "02" :
				return "\02";
			break;
			case "03" :
				return "\03";
			break;
			case "04" :
				return "\04";
			break;
			case "05" :
				return "\05";
			break;
			case "06" :
				return "\06";
			break;
			case "a" :
				return "\a";
			break;
			case "b" :
				return "\b";
			break;
			case "t" :
				return "\t";
			break;
			case "n" :
				return "\n";
			break;
			case "v" :
				return "\v";
			break;
			case "f" :
				return "\f";
			break;
			case "r" :
				return "\r";
			break;
			case "\x5c" :
				return "\x5c";
			break;
			case "?" :
				return "?";
			break;
			case "'" :
				return "'";
			break;
			case '"' :
				return '"';
			break;
		}
		
		if(preg_match('/^(\d{3})$/', $match, $submatch) > 0)
		{
			return pack("C", intval($submatch[1], 8));
		}
		else if(preg_match('/^x([\da-f]{2})$/i', $match, $submatch) > 0)
		{
			return pack("C", intval($submatch[1], 16));
		}
	}
}
?>