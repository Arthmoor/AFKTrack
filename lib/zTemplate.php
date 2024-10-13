<?php
/**
 * zTemplate
 * 
 * This is a class which emulates XTemplate (http://www.phpxtemplate.org)
 * All methods work exactly as they do with XTemplate, however this template engine performs far better
 * 
 * http://opensourceame.com
 * 
 * @package 		opensourceame
 * @author			David Kelly
 * @copyright		David Kelly 2007
 * @description		zTemplate is a high performance replacement for xTemplate
 * @version			1.1.8
 * 
 * distributed under the GPL 2.0, licensing info at http://www.gnu.org/licenses/gpl-2.0.html
 */

if ( !defined('AFKTRACK') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

if(! class_exists("xtemplate"))
{
	// extend the ztemplate class with some backwards compatible functions for xtemplate
	//
	// note that at 2 dec 2005 these are the only functions used by sugar
	// determined by matching /xtpl->.*(/U in all source pages
	
	class xtemplate extends ztemplate
	{
      private $mainblock;

		function __construct($file, $alt_include = null, $mainblock='main')
		{
			$this->mainblock = $mainblock;
			
			parent::__construct($file);
		}
		
		function var_exists($block, $var)
		{
			return isset($this->parselines->vbindex[$var]);
		}

		function exists($block)
		{
			return (! empty($this->parsed[$block])) || (! empty($this->blocks[$block]));
		}
		
	
		function append ($varname, $name, $val = null)
		{
			$this->assign($varname.'.'.$name, $val);
		}

	}
}

/**
 * zTemplate class
 */
class ztemplate
{
	const		version			= '1.1.8';			// the version of this class
	
	private		$template		= true;
	private		$cachedblocks	= array();			// an array of cached blocks
	private		$blocks			= array();			// an array of blocks
	private		$parsed			= array();			// an array of parsed blocks
   private     $delim;
   private     $_parse_options;
   private     $count;
   private     $block_order;
   private     $options;
   private     $parselines;
   private     $log;
   private     $contents;
   private     $parents;
   private     $vars;

	protected	$stblocks		= array();
	
	/**
	 * Class constructor
	 * 
	 * @param	string		$file		the filename to open and parse
	 * @param	array		$options	an array of config options
	 * @return	boolean 				true on successul load and parse of the template, otherwise false
	 */
	public	function __construct($file = null, $options = null)
	{
		// block delimeters kept in an object
		$this->delim					= new stdClass;
		$this->delim->block				= new stdClass;
		$this->delim->start				= '<!--';
		$this->delim->end				= '-->';
		$this->delim->block->start		= 'BEGIN:';
		$this->delim->block->end		= 'END:';

		$this->_parse_options			= new stdClass;
		$this->_parse_options->line_end	= "\n";
		
		$this->count					= new stdClass;
		$this->count->parsefile 		= false;
		$this->block_order 				= array();
		
		$this->options					= new stdClass;
		$this->options->condensed 		= false;
		$this->options->debug 			= false;
		$this->options->cache 			= false;
		$this->options->strict 			= false;

		$this->parselines				= new stdClass;
		$this->parselines->subtemplates	= array();
		
		$this->log						= new stdClass;
		$this->log->errors				= array();
		$this->log->debug				= array();
		
		if(! empty($options))
			$this->set_option($options);
		
		if($file != null)
		{
			return $this->parse_template_file($file);
		}

		return true;
	}

	/**
	 * used to to manually load a template if one is not specified when constructing
	 */
	public	function load_template($file)
	{
		$this->parse_template_file($file);
	}

	/**
	 * Set an option
	 */
	public	function set_option($opt, $val = null)
	{
		if( is_array($opt) or is_object($opt))
			foreach($opt as $key=>$val)
				$this->set_option($key, $val);
				
		switch($opt)
		{
			// condense by stripping out unnecessary whitespace
			case "condensed":
				$this->_parse_options->line_end = null;
				$this->options->condensed = true;
				break;
			
			// cache parsed results
			case "cache":
				$this->options->cache = true;
				break;
			
			// add debugging
			case "debug":
				$this->options->debug = true;
				$this->debug = array();
				break;

			// break on any error (e.g. reference to an unassigned template var)
			case "strict":
				$this->options->strict = true;
				break;
		}
		
		return true;
		
	}
	
	/**
	 * @return array a list of errors that have occurred
	 */
	public	function	errors()
	{
		return $this->log->errors;
	}
	
	/**
	 * log an error
	 */
	private	function add_error($message)
	{
		$this->log->errors[] = $message;
	}
	
	/**
	 * clear a cached block
	 */
	private	function clear_cache($block)
	{
		$this->add_debug("clear cache called for $block");
		
		// recursively clear blocks			
		// remove any preparsed block this will affect
		if( isset($this->cachedblocks[$block]))
		{
			{
				$this->add_debug("delete cache block $block");
				unset($this->cachedblocks[$block]);
				
				while( isset($this->cachedblocks[$this->parents[$block]]))
				{
					$this->add_debug("delete cache block $block parent {$this->parents[$block]}");
					unset($this->cachedblocks[$this->parents[$block]]);
					$block = $this->parents[$block];
				}
			}
		} else {
			// return false if no blocks to delete
			return false;
		}
		
		
	}
	
	/**
	 * assign a variable
	 */
	public	function assign($var, $val)
	{
		if(is_array($val) or is_object($val))
		{
			foreach($val as $k=>$v)
				$this->assign("$var.$k", $v);
				
			return true;
		}
		

		if( ($this->options->strict) and ! in_array($var, $this->parselines->vars))
		{
			$this->add_error("tried to assign non-existent var $var");
			return false;
		}
		
		// assign the var		
		settype($val, 'string');				// force string to fix problems with 0 values interpreted as null
		$this->vars[$var] = $val;

		// recursively delete cached blocks if we are doing caching		
		if($this->options->cache)
		{
			foreach($this->parselines->vbindex[$var] as $block)
				$this->clear_cache($block);
		}

		return true;
	}
	
	/**
	 * assign a file that can be included into the current template
	 */
	public	function assign_file($var, $file)
	{
		$this->parse_template_file($file, $this->stblocks[$var]);
		$this->filevars[$var] = $file;
	}

	/**
	 * output a block, optionally to a file
	 * 
	 * @param		string		$blockname		name of the block to output
	 * @param		string		$file			optional filename to write output to
	 */
	public	function out($blockname = null, $file = null)
	{
		if( isset($this->parsed[$blockname]))
		{
			if($file == null)
			{
				echo($this->parsed[$blockname]);
				return true;
			} else {
				$fh = fopen($file, 'w');
				
				if($fh === false)
				{
					$this->add_error("could not write $blockname to $file");
					return false;
				}
					
				fwrite($fh, $this->parsed[$blockname]);
				//fclose($fh);
				
				return true;
			}
		} else {
			$this->add_error("tried to output unparsed block $blockname");
		}
		
		return false;
	}
	
	/**
	 * get a parsed block
	 * 
	 * @param string $blockname the name of the block to return
	 */
	public	function get($blockname = null)
	{
		return($this->parsed[$blockname]);
	}

	/**
	 * print errors inside preformatted tags
	 */
	public	function print_errors()
	{
		if(! is_array($this->log->errors))
			return false;
		
		echo '<pre>';
		foreach($this->log->errors as $line)
		{
			print($line."\n");
		}
		echo "</pre>";
	}
	
	/**
	 * log a debugging message
	 */
	private	function add_debug($message)
	{
		if($this->options->debug)
			$this->log->debug[] = $message;
	}
	
	/**
	 * parse a block
	 * 
	 * @param		string		$blockname		name of the block to parse
	 * @return		boolean
	 */
	public	function parse($blockname)
	{
		if(! is_string($blockname))
		{
			print_r($blockname);
			exit;
		}
		
		// parse blocks
		if(! isset($this->parsed[$blockname]))
			$this->parsed[$blockname] = null;
			
		if( isset($this->cachedblocks[$blockname]))
		{
			// cached block exists, use it
			$this->parsed[$blockname] .= $this->cachedblocks[$blockname];
			$this->add_debug("using cached $blockname");
			return true;
		}
		
		if( empty($this->blocks[$blockname]))
		{
			$this->add_error("could not parse missing block $blockname");
			return false;
		}
			
		foreach($this->blocks[$blockname] as $key=>$line)
		{
			$append = $this->_parse_options->line_end;
			
			if( isset($this->parselines->subtemplates[$key]))
			{
				$v =& $this->parselines->subtemplates[$key];
				if( isset($this->filevars[$v]))
				{
					$this->reset($this->filevars[$v]);
					$this->parse($this->filevars[$v]);
					$this->parsed[$blockname] .= $this->parsed[$this->filevars[$v]];				
				} else {
					$this->add_error("missing included template $v while parsing block $blockname");
				}

				$line = null;

			}
			
			if( isset($this->parselines->vars[$key + 1]))
				$append = null;

			if( isset($this->parselines->vars[$key]))
			{
				if( isset($this->vars[$this->parselines->vars[$key]]))
				{
					$line = $this->vars[$this->parselines->vars[$key]];	
					$append = null;
				} else {
					$line = null;
					$append = null;
					$this->add_debug("missing var {$this->parselines->vars[$key]} while parsing block {$blockname}");
				}	
			}
			
			if( isset($this->parselines->blocks[$key]))
			{
				if( isset($this->parsed[$this->parselines->blocks[$key]]))
				{
					$this->parsed[$blockname] .= $this->parsed[$this->parselines->blocks[$key]];
					unset($this->parsed[$this->parselines->blocks[$key]]);
				} else {
					//$this->add_error("no block parsed $match[1]");
				}	
				$line = null;
			}
			
			if($line != null)
				$this->parsed[$blockname] .= $line . $append;
		}
		
		if($this->options->cache)
		{
			$this->cachedblocks[$blockname] = $this->parsed[$blockname];
			$this->add_debug("cache block $blockname");
		}
		
		$this->add_debug("parsed block $blockname");
		
		return true;
	}

	/**
	 * Reset a block
	 * 
	 * @param		string		$block		name of the block
	 * @return		boolean
	 */
    public	function reset ($block)
	{
		if(! isset($this->parsed[$block]))
			return false;
			
		$this->parsed[$block] = null;
		
		return true;
    }
	
    /**
     * Check if a variable has been set
     *
     * @param		string		$var			variable name
     * @return		boolean
     */
	public	function var_is_set($var)
	{
		return ! empty($this->vars[$var]);
	}
	
	/**
	 *  Get variables from lines in each block
	 */
	private	function parser_get_vars()
	{
		foreach(array_keys($this->blocks) as $blockname)
			if(is_array($this->blocks[$blockname]))
				foreach($this->blocks[$blockname] as $count=>$line)
				{
					do
					{
						$reparse = false;
						
						if(preg_match("/^(.*)\{([\w_\.]*)\}(.*)$/U", $line, $match))
						{
							// found a var, get busy
							$this->blocks[$blockname][$count] = $match[1];				// replace the line with the left side match
							$this->blocks[$blockname][++$count] = $match[2];			// new line with the var name
	
							$this->parselines->vars[$count] = $match[2];				// keep track of var position
							$this->parselines->vbindex[$match[2]][$blockname] = $blockname;			// variable block index
							$line = $match[3];
	
							$reparse = true;
							$count++;
	
						} else {
							$this->blocks[$blockname][$count] = $line;
						}
						
					} while($reparse);
					
					ksort($this->blocks[$blockname]);
				}
	}
	
	/**
	 * Add a line to the parsed block
	 * 
	 * @param		string		$block		the block name
	 * @param		string		$line		the line of text to add
	 * @return 		boolean
	 */
	private	function parse_add_line($block, $line)
	{
		if(empty($line))
			return false;
		
		if($this->options->condensed)
		{
			$line=preg_replace("/^\s*/", null, $line);
			$line=preg_replace("/\s*$/", null, $line);
		}

		$this->count->parsefile += 100;
		
		$this->blocks[$block][$this->count->parsefile] = $line;
		
		return true;
	}
	
	/**
	 * Parse a template
	 * 
	 * @param		string		$file		filename to open and parse
	 * @param		string		$bp			the block path (e.g. main.block1.block2)
	 * @return		boolean
	 */
	private	function parse_template_file($file, $bp = null)
	{
		if(! is_readable($file))
		{
			$this->add_error("could not read $file to parse it");
			return false;
		}
		
		if(filesize($file) == 0)
		{
			$this->add_error("file has zero size");
			return false;
		}
		
		// init vars
		$blockpath = null;
		
		// read the file
		$handle = fopen($file, "r");
		
		if($handle == false)
		{
			die("couldn't open $file");
		}
		
		$this->contents[$file] = explode("\n", fread($handle, filesize($file)));
		fclose($handle);
		
		// break file into blocks
		foreach($this->contents[$file] as $line)
		{
			do
			{
				$blockprefix = $bp;
				if( ($blockpath != null) and ($bp != null))
					$blockprefix = "{$blockprefix}.";
	
				$reparse = false;

				if(preg_match("/(.*)\{FILE {(.*)\}\}(.*)$/U", $line, $match))
				{
					if($blockpath == null)
					{
						$this->parse_add_line($file, $match[1]);
						$this->parse_add_line($file, "%$match[2]%");
						
					} else {
						
						$this->parse_add_line($blockprefix.$blockpath, $match[1]);
						$this->parse_add_line($blockprefix.$blockpath, "%$match[2]%");
					}
					
					$this->parselines->subtemplates[$this->count->parsefile] = $match[2];

					$this->stblocks[$match[2]] = $blockprefix.$blockpath;
					
					$line = $match[3];
					
					$reparse = true;
				}

				// start a block						
				if(preg_match("/^(.*)".$this->delim->start." ".$this->delim->block->start." (.*) ".$this->delim->end."(.*)$/U", $line, $match))
				{
					if($blockpath == null)
					{
						$this->parse_add_line($file, $match[1]);
						$this->parse_add_line($file, "@{$blockprefix}{$blockpath}.$match[2]@");
					} else {
						$this->parse_add_line($blockprefix.$blockpath, $match[1]);
						$this->parse_add_line($blockprefix.$blockpath, "@{$blockprefix}{$blockpath}.$match[2]@");
					}
					
					if($blockpath != null)
					{
						$this->parents["{$blockpath}.{$match[2]}"] = $blockprefix.$blockpath;
						$blockpath .= ".";
					}
					$blockpath .= $match[2];

					$blockprefix = $bp;
					if( ($blockpath != null) and ($bp != null))
						$blockprefix = "{$blockprefix}.";

					$this->parselines->blocks[$this->count->parsefile] = $blockprefix.$blockpath;
					$this->parselines->bindex[$blockprefix.$blockpath] = $this->count->parsefile;
					
					array_unshift($this->block_order, $blockprefix.$blockpath);
					$line = $match[3];

					$reparse = true;
				}

				// match a block end	
				if(preg_match("/^(.*)".$this->delim->start." ".$this->delim->block->end." (.*) ".$this->delim->end."(.*)$/U", $line, $match))
				{
					if(preg_match("/$match[2]$/", $blockpath))
					{
						if($blockpath == null)						
							$this->parse_add_line($file, $match[1]);
						else
							$this->parse_add_line($blockprefix.$blockpath, $match[1]);
						
						$blockpath = substr($blockpath, 0, -(strlen($match[2]))-1);
						
						$line = $match[3];
					} else {
						$this->add_error("tried to end block $match[2] within {$blockprefix}{$blockpath}");
						$this->print_errors();
						die;
					}
					$reparse = true;
				}
			
			} while($reparse);

			if($blockpath == null)	
				$this->parse_add_line($file, $line);				
			else
				$this->parse_add_line($blockprefix.$blockpath, $line);
		}

		// add file block to block order list
		$this->block_order[]= $file;			
			
		// now get the variables
		$this->parser_get_vars();
		
		// free up some ram if not debugging
		if($this->options->debug)
			unset($this->contents[$file]);
	}

	/**
	 * Return the text of a block
	 * 
	 * @param		string		$blockname			name of the block
	 * @return		string
	 */
	public	function text($blockname)
	{
		return $this->parsed[ isset($blockname) ? $blockname : $this->mainblock ];
	}

}
