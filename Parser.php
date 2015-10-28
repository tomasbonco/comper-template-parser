<?php

/**
 * COMPER Template Parser Class
 *
 * Parsing templates
 *
 * @framework	CodeIgniter
 * @subpackage	Libraries
 * @author		Tomáš Bončo
 * @category	Parser
 * @link		https://github.com/TomasBonco/comper-template-parser/
 * @year        2014
 * @version     3
 *
 *
 * Special thanks to God for talent, family for supporting me and community for motivating me and also:
 *		Character Art: http://bit.ly/MBTCVq
 */
 
class parser
{
	private $ci;	// CodeIgniter instance
	private $data;
	private $config;
	public  $content; // Output
	
	function __construct()
	{
		$this->classname = get_class($this);
		
		$this->CI =& get_instance();
		
		$this->CI->load->helper('file');	// read_file()
		$this->CI->load->helper('string');  // reduce_double_slashes()
		
		$this->_default();
		
		log_message('debug', 'CTP Class initialized');
	}
	
	/**
	 * parse()
	 * 
	 * @access public
	 * @param string
	 * @param array
	 * @param array
	 * @return string
	 * 
	 * Parses file or string
	 * 
	 * */
	
	public function parse($input, $data = array(), $config = array())
	{
		if (empty($data)) $data = array();
		if (empty($config)) $config = array();
		
		if ( ! empty( $data ) && is_object( $data) )
		{
			$data = json_decode(json_encode($data), TRUE );
		}

		if ( is_string($input) && strlen($input) > 0 && is_array($data) && is_array($config))
		{
			$this->_set_config($config);
			
			@$this->data->append['config'] = (array) $this->CI->config->config; // actual config
			
			if ($this->config->is_string == TRUE)	// ... is first parameter string (or file) ?
			{
				if ( strlen(trim($input)) > 0 )
				{
					$this->data->input = $input;
				}
				
				else
			 	{
			 		return FALSE;
			 	}
			}
			
			else
			{
				if ($this->config->suffix_theme_only)
				{
					if (empty($this->config->theme))
					{
						$this->config->template_suffix = NULL;
					}
				}
				
				$this->config->file = $this->_path(TRUE) . $this->config->theme . '/' .  $this->config->template_suffix . '/' . $input . '.' . $this->config->extension;
        		$this->config->folder = $this->_path() . $this->config->theme;
        		
        		if ( ! $this->data->input = (string) read_file($this->config->file) )
        		{
        			show_error('Template not found: '. reduce_double_slashes( $this->config->file ));
        			return FALSE;
        		}
        		
        		if ( ! (strlen($this->data->input) > 0))
        		{
        			return FALSE;
        		}
			}
			
			$this->input = new stdClass;

			$this->input->data = $data;
			$this->input->config = $config;

			foreach ($data as $data_key => $data_value) // let's prepare our data
	        {
	            if (is_array($data_value))
	            {
	                $this->data->cycles[$data_key] = $data_value;
	            }
	            
	            elseif (is_bool($data_value))
	            {
	                $this->data->conditions[$data_key] = $data_value;
	                $this->data->variables[$data_key] = $data_value;
	            }
	            
	            else
	            {
	                $this->data->variables[$data_key] = $data_value; 
	            }
	        }
	        
	        foreach ($this->config->append as $app_name => $app_content)
	        {
	        	
	        	if (isset($app_name) && is_string($app_name) && isset($app_content) && is_array($app_content))
	        	{
	        		$this->data->append[$app_name] = $app_content;
	        	}
	        	
	        }

	        $this->_no_code();

			if ($this->config->disable_cycles === FALSE) $this->_cycles();
			if ($this->config->disable_conditions === FALSE) $this->_conditions();
			if ($this->config->disable_variables === FALSE) $this->_variables();
			if ($this->config->disable_includes === FALSE) $this->data->input = $this->_includes( $this->data->input, TRUE );
	        
	        $this->_show();

	        $append = $this->data->append;	        
	        $this->_default();
	        $this->data->append = $append;
	        
	        return $this->content;
		}
		
		else
		{
        	return FALSE;
		}
	}
	
	/**
	 * theme()
	 * 
	 * @access public
	 * @param string
	 * @return bool
	 * 
	 * Sets color theme
	 * 
	 * */
	
	public function theme($value)
	{    	
        if (is_dir(APPPATH . 'views/' . $value))
        {
            $this->config->theme = $value;
            
            $this->CI->config->set_item('comper_parser', array('theme' => $value));
            
            return TRUE;
        }

        return FALSE;
	}
	
	/**
	 * append()
	 * 
	 * @access public
	 * @param string
	 * @param array
	 * @return bool
	 * 
	 * Adds some extra variables
	 * 
	 * */
	
	public function append($name, $array = array())
    {    	
    	if ( ! empty($name))
		{
			if ( is_array ($name))
			{
				$this->data->append += $name;
			}

			elseif ( is_array( $array ) )
			{
    			$this->data->append[$name] = $array;
			}
    	}
    }


	private function _no_code()
	{
		$this->data->input = preg_replace_callback('#<!--\s*NOCODE\s*-->(.*?)<!--\s*/NOCODE\s*-->#si', array($this->classname, '_no_code_content'), $this->data->input);

	}

	private function _no_code_content( $matches )
	{
		return sprintf('{nocode:%s}', base64_encode( $matches[1] ));
	}
    
	
	/**
	 * _includes()
	 * 
	 * @access private
	 * @param string
	 * @param bool
	 * @return string
	 * 
	 * Loads another template inside current template
	 *
	 *
	 *	                          ,,                   ,,                  
	 *	`7MMF'                  `7MM                 `7MM                  
	 *	  MM                      MM                   MM                  
	 *	  MM  `7MMpMMMb.  ,p6"bo  MM `7MM  `7MM   ,M""bMM  .gP"Ya  ,pP"Ybd 
	 *	  MM    MM    MM 6M'  OO  MM   MM    MM ,AP    MM ,M'   Yb 8I   `" 
	 *	  MM    MM    MM 8M       MM   MM    MM 8MI    MM 8M"""""" `YMMMa. 
	 *	  MM    MM    MM YM.    , MM   MM    MM `Mb    MM YM.    , L.   I8 
	 *	.JMML..JMML  JMML.YMbmd'.JMML. `Mbod"YML.`Wbmd"MML.`Mbmmd' M9mmmP' 
     *                                                              
     *                                                              
	 * */
	
	private function _includes( $context, $run_parser = FALSE )
    {
    	## Sending data to callback function

    	$this->include = new stdClass;
    	$this->include->run_parser = $run_parser;

    	## Performing regular expressions

		$context = preg_replace_callback('#<!--\s*INCLUDE\s*(.+?)\s*-->#i', array($this->classname, '_include_content'), $context, -1, $_replace_count);          
		if ( $_replace_count > 0 && preg_match('#<!--\s*INCLUDE\s*(.+?)\s*-->#i',  $context) > 0) $context = $this->_includes( $context );	

		return $context;
	}


    /**
	 * _include_content()
	 * 
	 * @access private
	 * @param array
	 * @return string
	 * @usage _include()
	 * 
	 * Does including
	 * 
	 * */
	 
	function _include_content( $matches )
    {
    	$file = $matches[1];
    	$filename = $this->_path(TRUE) . $this->config->theme . '/' .  $this->config->template_suffix . '/' . $file . '.' . $this->config->extension;

    	if ( $this->include->run_parser )
    	{
    		$parser = new $this->classname;
    		return $parser->parse( $file, $this->input->data, array( 'show' => FALSE, 'append' => $this->data->append, 'is_string' => FALSE ));
    	}

    	else
    	{
    		return (string) read_file( $filename );
    	}
    }
	
	/**
	 * _cycles()
	 * 
	 * @access private
	 * @return ---
	 * 
	 * Provides cycling inside template
	 * 
	 *                                            
     *		                          ,,                  
	 * 	  .g8"""bgd                 `7MM                  
	 *	.dP'     `M                   MM                  
	 *	dM'       ``7M'   `MF',p6"bo  MM  .gP"Ya  ,pP"Ybd 
	 *	MM           VA   ,V 6M'  OO  MM ,M'   Yb 8I   `" 
	 *	MM.           VA ,V  8M       MM 8M"""""" `YMMMa. 
	 *	`Mb.     ,'    VVV   YM.    , MM YM.    , L.   I8 
  	 *   `"bmmmd'     ,V      YMbmd'.JMML.`Mbmmd' M9mmmP' 
	 *               ,V                                  
     * 		      OOb"                                   
	 *
	 *
	 * */
	
	private function _cycles()
    {
		$this->data->input = preg_replace_callback('#<!--\s*BEGIN\s*([^!]+?)(\sAS\s*([^\>!\-]+?))?\s*-->(.*?)<!--\s*END\s*(\\1|\\3)\s*-->#si', array($this->classname, '_cycle_content'), $this->data->input, -1, $_replace_count);
		if ( $_replace_count > 0 && preg_match('#<!--\s*BEGIN\s*([^!]+?)(\sAS\s*([^\>!\-]+?))?\s*-->(.*?)<!--\s*END\s*(\\1|\\3)\s*-->#si', $this->data->input) > 0) $this->_cycles();
	}

	 /**
	 * _cycle_content()
	 * 
	 * @access private
	 * @param array
	 * @return string
	 * @usage _cycle()
	 * 
	 * Does cycling                                                                                    
	 *
	**/
	 
	function _cycle_content($matches)
	{
		list($cycles, $subject,, $name, $content) = $matches;

		$subject = $this->smart( $subject );

		## We can cycle random array given

		if ( strpos( $subject, '{array:') !== FALSE )
		{
			$array = json_decode( urldecode( substr( $subject, 7, -1 ) ), TRUE);
		}

		else
		{
			$array = (array) @$this->data->cycles[$subject];
		}

		if ( count($array) > 0 )
		{
			$buffer = '';
			$i = 0;

			foreach ( $array as $iteration )
			{
				$_content = $content;
				$_alternative = array();

				if ( ! empty( $iteration ) && is_object( $iteration) )
				{
					$iteration = json_decode(json_encode($iteration), TRUE );
				}

				$iteration['__i'] = $i;

				## User can specify which cycle this variable belongs to like {cycle.name},
				## this can be done by AS parater: <!-- BEGIN xyz AS cycle -->
				## or just by creating cycle <!-- BEGIN cycle -->
				## This code makes it work:

				foreach ( $iteration as $item => $value)
				{
					$_alternative[ sprintf('%s.%s', ( ! empty($name) ? $name : $subject ), $item) ] = $value;
				}

				## Inside cycle there can be some Include calling, and it should work :)
				$_content = $this->_includes( $_content );

				## Time for smart engine :)
				$_content = $this->smart( $_content, $iteration + $_alternative );

				$buffer .= $_content;
				$i++;
			}

			return $buffer;
		}

		return FALSE;
    }


	
	/**
	 * _conditions()
	 * 
	 * @access private
	 * @return ---
	 * 
	 * Provides conditions inside template
	 * 
	 *                                                                                      
	 *	                                     ,,    ,,          ,,                             
	 *	  .g8"""bgd                        `7MM    db   mm     db                             
	 *	.dP'     `M                          MM         MM                                    
	 *	dM'       ` ,pW"Wq.`7MMpMMMb.   ,M""bMM  `7MM mmMMmm `7MM  ,pW"Wq.`7MMpMMMb.  ,pP"Ybd 
	 *	MM         6W'   `Wb MM    MM ,AP    MM    MM   MM     MM 6W'   `Wb MM    MM  8I   `" 
	 *	MM.        8M     M8 MM    MM 8MI    MM    MM   MM     MM 8M     M8 MM    MM  `YMMMa. 
	 *	`Mb.     ,'YA.   ,A9 MM    MM `Mb    MM    MM   MM     MM YA.   ,A9 MM    MM  L.   I8 
	 *	  `"bmmmd'  `Ybmd9'.JMML  JMML.`Wbmd"MML..JMML. `Mbmo.JMML.`Ybmd9'.JMML  JMML.M9mmmP' 
	 *
	 *
	 * */

	private function _conditions()
	{
		preg_match_all("#<!--\s*(IF|ELSEIF|ELSE|END)(.*?)\s*-->#i", $this->data->input, $options, PREG_OFFSET_CAPTURE );
		
		if ( $options > 0 )
		{
			## Creates an array, when 0 is whole statement, 1 is IF | ELSEIF | ELSE or END and 2 is parameter
			## Let's make it together
			
			foreach ( $options[0] as $i => $option)
			{
				## Each of following arrays has two indexes: 0 - content, 1 - position
				
				$this->_statement = $options[0][$i];
				$_type = $options[1][$i];
				$_parameter = $options[2][$i];
				
				$type = strtoupper(trim($_type[0])); // contains IF, ELSEIF, ELSE or END
						
				$_param ['statement'] = trim( $this->_statement[0] );		// <!-- IF is_admin -->
				$_param ['type'] = trim( strtoupper( $_type[0] ));	// IF
				$_param ['param'] = trim( $_parameter[0] );			// is_admin
				
				$_param ['start_position'] = $this->_statement[1];		// position of first char (<) of statement
				$_param ['length'] = strlen( $_param['statement'] );	// length of statement
				$_param ['end_position'] = $_param['start_position'] + $_param['length']; // position of last char (>) of statement

				## When END keywords has some parameter, it's for cycles (BEGIN - END), not conditions
				## that's why we need to ignore it

				if ( $_param['type'] != 'END' || empty( $_param['param'] ))
				{
					$statement->{$type}[] = (object) $_param;
				}
			}
			
			if ( isset($statement->IF) && count( $statement->IF ) > 0 )
			{
				## Reverses IFs, because will be matching last IF with nearest END
				
				$statement->IF = array_reverse( $statement->IF );
				
				## Let's do all dirty hob
				
				foreach ( $statement->IF as $current_if )
				{
					## Matches IF with END and finds ELSEIFs and ELSE inside
					
					$_current_data ['IF'] = $current_if;
					$_current_data ['END'] = $this->_find_nearest( $statement->END, $_current_data['IF']->start_position );
					$_current_data ['ELSE'] = ( isset($statement->ELSE) && count($statement->ELSE) > 0 ) ? $this->_find_nearest ( $statement->ELSE, $_current_data['IF']->start_position, $_current_data['END']->start_position ) : FALSE;
					$_current_data ['ELSEIF'] = ( isset($statement->ELSEIF) && count($statement->ELSEIF) > 0 ) ? $this->_find_between ( $statement->ELSEIF, $_current_data['IF']->start_position, $_current_data['END']->start_position ) : FALSE;
					
					$current = (object) $_current_data;
					
					## Is IF is NOT true, try first ELSEIF if any

					if ( ! $this->_statement( $current->IF->param ))
					{
						## If there are some ELSEIF check them, if not find ELSE. If there is not ELSE too, then return FALSE
						
						$_elseif_match_found = FALSE; // Used as identifier. When ELSEIF with TRUE will be found, this will change to TRUE and ELSE will be skipped. I can't make it another way. 
						
						if ( $current->ELSEIF && count( $current->ELSEIF ) > 0 )
						{
							foreach ( $current->ELSEIF as $n => $current_elseif)
							{					
								if ( $this->_statement( $current_elseif->param) )
								{
									$result_object = $current_elseif;
									
									## Search for next object, so we can get content inside
									
									if ( $current_elseif == end( $current->ELSEIF ) ) // if last ELSEIF
									{
										if ( $current->ELSE )
										{
											$next_object = $current->ELSE;
										}
										
										else
										{
											$next_object = $current->END;
										}
									}
									
									else
									{
										$next_object = $current->ELSEIF[ $n+1 ]; // next elseif
									}
									
									$_elseif_match_found = TRUE;
								}
								
								$this->_destroy($statement->ELSEIF, $current_elseif);
								
								unset($current_elseif);
							}
						}
						
						## If we didn't found ELSEIF, but there is ELSE do it! If there is no ELSE, than return FALSE
						
						if ( ! $_elseif_match_found )
						{
							if ( $current->ELSE )
							{
								$result_object = $current->ELSE;
								$next_object = $current->END;
								
								$this->_destroy($statement->ELSE, $current->ELSE);
							}
							
							else
							{
								$result_object = FALSE;
							}
						}
					}
					
					else
					{
						## When IF is TRUE
						
						$result_object = $current->IF;
						
						if ( $current->ELSEIF && count($current->ELSEIF) > 0)
						{
							$next_object = $current->ELSEIF[0];
						}
						
						elseif ( $current->ELSE)
						{
							$next_object = $current->ELSE;
						}
						
						else
						{
							$next_object = $current->END;
						}
					}
					
					## Let's select content and $this->_destroy whole condition
					
					if ( $result_object )
					{
						$_content = substr( $this->data->input, $result_object->end_position, ( $next_object->start_position - $result_object->end_position ));	
					}
					
					else
					{
						$_content = '';
					}
					
					$_gap = ( $current->END->end_position - $current->IF->start_position ) - strlen( $_content ); // difference between old and new legth of document
					$_from_position = $current->IF->start_position; // needed for updating positions. We want update positions after conditions. (Becoause these before conditions weren't affected by current condition) 
					
					## Replaces content of whole condition from <!-- IF --> to  <!-- END --> with content of result object
					
					$this->data->input = $this->_replace_content( $current->IF->start_position, $current->END->end_position, $_content, $this->data->input);
					
					## Updates position of all elements
					
					$this->_destroy($statement->IF, $current->IF);
					$this->_destroy($statement->END, $current->END);
					$this->_destroy($statement->ELSE, $current->ELSE); 
					$this->_destroy($statement->ELSEIF, $current->ELSEIF); 
					
					$this->_update_positions( $statement, $_gap, $_from_position );
				
					unset( $result_object );
					unset( $next_object );
					unset( $current );
				}
				
				return $this->data->input;
			}
		}
	}

	/**
	 * _find_between()
	 * 
	 * @access private
	 * @param array
	 * @param int
	 * @param int
	 * @return array
	 * @usage _conditions()
	 * 
	 * Searches for elements between start and end position
	 * 
	 * */
		
	private function _find_between( $array, $start_position, $end_position = FALSE )
	{
		$return = array();
		
		foreach ( $array as $object )
		{
			if ( $object->start_position > $start_position )
			{
				if ( $end_position )
				{
					if ( $object->end_position >= $end_position )
					{
						continue;
					}
				}
				
				$return[] = $object;
			}
		}
		
		return $return;
	}
	
	/**
	 * _find_nearest()
	 * 
	 * @access private
	 * @param array
	 * @param int
	 * @param int
	 * @return array
	 * @usage _conditions()
	 * 
	 * Searches for elements between start and end position and returns the first one
	 * 
	 * */
	
	function _find_nearest( $arr, $sp, $ep = FALSE )
	{
		return reset( $this->_find_between( $arr, $sp, $ep ) );
	}
	
	/**
	 * _destroy()
	 * 
	 * @access private
	 * @param array
	 * @param array
	 * @param mixed
	 * @return ---
	 * @usage _conditions()
	 * 
	 * Unsets used statements in conditions
	 * 
	 * */
	
	function _destroy( &$haystack, $needle )
	{
		if ( is_array($needle) )
		{
			foreach ( $needle as $item )
			{
				$this->_destroy($haystack, $item);
			}
		}
		
		else
		{
			if ( ! empty( $haystack ))
			{
				foreach ( $haystack as $id => $element )
				{		
					if ( $needle == $element )
					{
						unset( $haystack[$id] );
						break;
					}
				}
			}
		}
	}
	
	/**
	 * _replace_content()
	 * 
	 * @access private
	 * @param int
	 * @param int
	 * @param string
	 * @param string
	 * @return array
	 * @usage _conditions()
	 * 
	 * Replaces content of whole condition, with part marked as TRUE
	 * 
	 * */
	
	function _replace_content( $start_position, $end_position, $needle, $haystack )
	{
		$content_before = substr( $haystack, 0, $start_position);
		$content_after  = substr( $haystack, $end_position );
		
		return $content_before . $needle . $content_after;
	}
	
	/**
	 * _update_positions()
	 * 
	 * @access private
	 * @param object
	 * @param int
	 * @param int
	 * @usage _conditions()
	 * 
	 * Updates positions of elements after starting position (after condition's start)
	 * 
	 * */
	
	function _update_positions( &$object, $gap, $start_position )
	{
		foreach ( array( 'IF', 'ELSEIF', 'ELSE', 'END') as $type )
		{
			if ( isset($object->$type) && count($object->$type) >0 )
			{
				foreach ( $object->$type as $id => $obj )
				{
					if ( $obj->start_position > $start_position )
					{
						$object->{$type}[$id]->start_position = $obj->start_position - $gap; 
						$object->{$type}[$id]->end_position = $obj->end_position - $gap;
					}
				}
			}
		}
	}
	
	/**
	 * _statement()
	 * 
	 * @access private
	 * @param string
	 * @return bool
	 * @usage _conditions()
	 * 
	 * Is statement TRUE?
	 * 
	 * */
	
	function _statement( $code )
	{
		## Replace pseudo-variables & appends

		$code = $this->smart($code, $this->data->conditions, TRUE);

		$code = preg_replace_callback('#{condition:(.*?)}#', create_function( '$matches', '
			list(, $value ) = $matches;

			if ( is_bool( $value ) || empty( $value ))
			{
				return ( $value == TRUE && ! empty($value)) ? \'1\' : \'0\';
			}

			else
			{
				return "\'" . json_decode(urldecode($value)) . "\'";
			}'), $code);


		## Is the condition all right? If not, it needs some adjustements

		if ( $this->_try_parse( $code )->error )
		{

			## Replace everything that seems to be true or false to true or false :)
			
			$code = str_replace( array( 'true', 'TRUE', '"TRUE"', '"true"', '\'TRUE\'', '\'true\'' ), '1', $code );
			$code = str_replace( array( 'false', 'FALSE', '"FALSE"', '"false"', '\'FALSE\'', '\'false\'' ), '0', $code );
			
			## Is it all right now?
			
			if ( $this->_try_parse($code)->error )
			{
				## Let's extract all words/chars that seems interesting
				
				preg_match_all('#[a-zA-Z0-9_\'\-\"\.\+\*\/{}:]+#', $code, $words );
				
				if ( count($words) > 0 )
				{
					## Exceptions are not interesting!
					
					$exceptions = array('and', 'or', '&&', '||', '*', '/', '+', '-', '>', '<', '!');

					foreach ( $words[0] as $current => $word )
					{
						## If it's not a number, not a function, not an exception, it's not starting with quotes than what it is?
						
						if ( ! is_numeric( $word ) AND ! in_array( $word, $exceptions ) AND ! function_exists( $word ) AND ! ( substr($word, 0, 1) == '"' && substr($word, -1) == '"' )  AND ! ( substr($word, 0, 1) == "'" && substr($word, -1) == "'") )
						{

							## Is it a TRUE/FALSE condition variable from parser?
							
							if ( isset( $this->data->conditions[$word] ))
							{
								$value = ($this->data->conditions[$word] == TRUE)? '1' : '0' ;
								$code = str_replace($word, $value, $code); // I gotcha!!! Haha ...
							}

							else
							{
								## We don't know what it is, but it's a word, let's treat with it like a word (create quotes)

								## Situation: {mode} == 'edit', will produce edit == 'edit', and replacing will cause "edit" == "'edit'",
								## lets protect from such situation

								$code = str_replace(sprintf('"%s"', $word), $word, $code);
								$code = str_replace(sprintf("'%s'", $word), $word, $code);

								$code = str_replace($word, sprintf('"%s"', $word), $code);
							}
						}
					}

					## If it's just a text like "test2", it means that variable is not defined, and that's why we return FALSE

					if ( substr( trim($code), 0, 1) == '"' && substr( trim($code), -1) == '"' && ( strstr( $code, ' ' ) == FALSE ) )
					{
						return FALSE;
					}

					if ( $this->_try_parse($code)->error )
					{
						## There is something unexpecting. Notice developer and return FALSE.

						log_message( 'error', 'Parser don\'t understand your condition. Please fix it or write to helpdesk. Condition after parsing: '. $code );
						return FALSE;
					}
				}
			}
		}

		return $this->_try_parse($code)->result;
	}
	
	/**
	 * _try_parse()
	 * 
	 * @access private
	 * @param string
	 * @return object
	 * @usage _conditions()
	 * 
	 * Tryies to parse statement
	 * 
	 * */
	
	function _try_parse( $code )
	{
		ob_start(); // Opens buffer
		
		$cond = NULL;
		
		echo $rand = sha1(microtime()); // Creates watchpoint

		eval( 'error_reporting(-1); $cond = (bool) (' . $code . '); $x = ( 1 == $cond); $y = $code + 1;'); // Let's try if it's valid
		
		$output = ob_get_clean(); // Deletes error messages
		
		echo substr($output, 0, strpos($output, $rand)); // Outputs everything from buffer from start to watchpoint

		$return = new stdClass;

		$return->error = (strlen(trim(substr($output, strpos($output, $rand)))) > strlen($rand)) ? TRUE : FALSE; // analyse output - is there any error message?
		$return->result = (( $cond === TRUE ) && $return->error == 0) ? TRUE : FALSE;
		
		$this->report[] = array('condition' => $code, 'statement' => print_r($cond, TRUE), 'error' => print_r($return->error, TRUE),'result' => print_r($return->result, TRUE));

		return $return;
	}



	
	/**
	 * _variables()
	 * 
	 * @access private
	 * @return ---
	 * 
	 * Provides pseudo-variables replacement
	 * 
	 *
	 *                                                                          
	 *	                               ,,           ,,        ,,                  
	 *	`7MMF'   `7MF'                 db          *MM      `7MM                  
	 *	  `MA     ,V                                MM        MM                  
	 *	   VM:   ,V ,6"Yb.  `7Mb,od8 `7MM   ,6"Yb.  MM,dMMb.  MM  .gP"Ya  ,pP"Ybd 
	 *	    MM.  M'8)   MM    MM' "'   MM  8)   MM  MM    `Mb MM ,M'   Yb 8I   `" 
	 *	    `MM A'  ,pm9MM    MM       MM   ,pm9MM  MM     M8 MM 8M"""""" `YMMMa. 
	 *	     :MM;  8M   MM    MM       MM  8M   MM  MM.   ,M9 MM YM.    , L.   I8 
	 *	      VF   `Moo9^Yo..JMML.   .JMML.`Moo9^Yo.P^YbmdP'.JMML.`Mbmmd' M9mmmP'			& Appends
     *                                                                   
     *                                                                   
	 * */
	
	private function _variables()
    {
    	## Replace pseudo-variables
		$this->data->input = $this->smart( $this->data->input );

    	## Treat with exceptions
        
        foreach ($this->config->exceptions as $exception)
        {        	
        	if (is_string($exception))
            $this->data->input = str_replace('{'. $exception .'}', '&&#123&&;' . $exception . '&&#125&&;', $this->data->input);
        }
        
		if ( ! is_bool($this->config->clean)) $this->config->clean = TRUE;
        if ($this->config->clean)
    	{
    		## Clean unused variables

    		$this->data->input = preg_replace('#{[^0-9]{1}[a-zA-Z0-9_\-\.]+(\|([^\(\);]+?))?}#', '', $this->data->input);

    		## Clean unused appends

    		$this->data->input = preg_replace('#{([a-zA-Z0-9_\.\-]+(->)*)+\s*(\|([^\(\);]+?))?}#', '', $this->data->input);

    		## Clean nocode

    		$this->data->input = preg_replace_callback('#{nocode:(.+?)}#', create_function('$m', 'return base64_decode($m[1]);'), $this->data->input);

    		## Clean arrays

    		$this->data->input = preg_replace('#{array:(.+?)(|(.+?))?}#', 'Array', $this->data->input);
    	}
        
		$this->data->input = str_replace('&&#123&&;', '{', $this->data->input);
		$this->data->input = str_replace('&&#125&&;', '}', $this->data->input);
		
	}


	function smart( $content, $array = array(), $condition = FALSE )
	{
		$haystack = $this->data->append + $this->input->data + $array;
		$haystack['T_Folder'] = $this->config->folder;

		preg_match_all('#\{([^\{\}]+?)\}#', $content, $matches);

		$matches[0] = array_unique( $matches[0] );

		$file_size_start = strlen( $content );

		foreach ( $matches[0] as $id => $original )
		{

			## Prepare

			$value = new stdClass;
			$value->original = $original;
			$value->content = $matches[1][$id];

			## Filter

			if ( preg_match('#[^\\\\]([=\:\(\)\/\;\+])#', $value->content) )
			{
				continue;
			}

			## Arguments

			$explode = explode( '|', $value->content );

			$value->content = trim($explode[0]);

			array_shift($explode);
			$value->arguments = $explode;

			## Append
			$tree = explode( '->', $value->content );
			$value->result = $haystack;

			try
			{
				foreach ( $tree as $branch )
				{
					$branch = trim( $branch );

					if ( ! empty( $value->result[$branch] ) && is_object( $value->result[$branch]) )
					{
						$value->result[$branch] = json_decode(json_encode($value->result[$branch]), TRUE );
					}

					if ( isset( $value->result[$branch] ))
					{
						$value->result = $value->result[$branch];
					}

					else
					{
						throw new Exception('Unknown value.');
					}
				}
			}

			catch (Exception $e)
			{
				## If we are inside condition, we have to make decision

				if ( $condition )
				{
					$value->result = NULL;
				}

				## But it we are not, let cleaning solve that
				else
				{
					continue;
				}
			}

			## Applying modificators

			if ( ! empty( $value->arguments ) && $this->config->allow_modificators )
			{
				$local_result = $value->result;

				foreach( $value->arguments as $argument )
				{
					preg_match("#(.+?)\[(.*?)\]#", $argument, $parameters);

					if ( ! empty( $parameters[2] ))
					{
						preg_match_all( '#"(.*?[^\\\\])"|\'(.*?[^\\\\])\'|(\&)|(TRUE)|(FALSE)|([0-9]+)#', $parameters[2], $parameter);

						$pr = array();

						foreach ( $parameter as $p )
						{	
							$pr += array_filter( $p );
						}

						$parameter = $pr;

						foreach ( $parameter as &$item )
						{
							if (  $item == '&' ) $item = $value->result;
						}
						
						$fn = $parameters[1];
						$parameter = $parameter;
					}

					else
					{
						$fn = $argument;
						$parameter = array( $value->result );
					}

					if (is_callable( $fn ))
					{
						$value->result = call_user_func_array( $fn, $parameter );
					}
				}
			}

			## If array

			
			if ( is_array( $value->result ))
			{
				$value->result = sprintf('{array:%s}', urlencode(json_encode( $value->result )));
			}

			if ( $condition )
			{
				$value->result = sprintf('{condition:%s}', urlencode(json_encode( $value->result )));
			}

			if ( is_object( $value->result ))
			{
				$value->result = 'Object';
			}

			$content = str_replace( $value->original, $value->result, $content );
		}

		if ( strlen( $content ) != $file_size_start )
		{
			$content = $this->smart($content, $array, $condition);
		}

		return $content;
	}



	/**
	 *
	 *
	 *
	 *                  ,,                   
	 *	`7MMM.     ,MMF'  db                   
	 *	  MMMb    dPMM                         
	 *	  M YM   ,M MM  `7MM  ,pP"Ybd  ,p6"bo  
	 *	  M  Mb  M' MM    MM  8I   `" 6M'  OO  
	 *	  M  YM.P'  MM    MM  `YMMMa. 8M       
	 *	  M  `YM'   MM    MM  L.   I8 YM.    , 
	 *	.JML. `'  .JMML..JMML.M9mmmP'  YMbmd'                                  
	 *
     *
     * */

    /**
	 * _show()
	 * 
	 * @access private
	 * @return ---
	 * 
	 * Displays output
	 * 
	 * */
    
    private function _show()
    {
    	$this->content = $this->data->input;
	 	if ($this->config->show) $this->CI->output->append_output($this->data->input);
    }
	
	/**
	 * _default()
	 * 
	 * @access private
	 * @param ---
	 * @return ---
	 * @usage parser(), parse()
	 * 
	 * Resets parser
	 * 
	 * */
	
	private function _default()
	{
		if ( ! $this->config) $this->config = new stdClass;
		if ( ! $this->data) $this->data = new stdClass;

		$this->config->allow_modificators = TRUE;
		$this->config->append = array(); // you can set append from config
		$this->config->clean = TRUE;
		$this->config->disable_appends = FALSE;
		$this->config->disable_conditions = FALSE;
		$this->config->disable_cycles = FALSE;
		$this->config->disable_includes = FALSE;
		$this->config->disable_variables = FALSE;
		$this->config->exceptions = array('memory_usage', 'elapsed_time');
		$this->config->extension = 'tpl';
		$this->config->file = NULL;
		$this->config->folder = NULL;
		$this->config->is_string = FALSE;
		$this->config->path = '%path%/views/';
		$this->config->show = TRUE;
		$this->config->suffix_theme_only = TRUE;
		$this->config->template_suffix = 'tpl';
		$this->config->theme = NULL;
		$this->config->quote_string = TRUE;
		
		$this->data->append = array();
		$this->data->conditions = array();
		$this->data->content = NULL;
		$this->data->cycles = array();
		$this->data->input = NULL;
		$this->data->input_length = 0;
		$this->data->variables = array();
	}
	
	/**
	 * _set_config()
	 * 
	 * @access private
	 * @param array
	 * @return ---
	 * @usage parse()
	 * 
	 * Sets configurations for parser
	 * 
	 * */
	 
	private function _set_config($config)
	{		
		@$config = (array) $config + (array) $this->CI->config->item('comper_parser');
		
		foreach ($config as $cfg_key => $cfg_value)
		{
			if (is_array($cfg_value))
			{
				if ( isset($this->config->$cfg_key->{key($cfg_value)} ))
				$this->config->$cfg_key->{key($cfg_value)} = current($cfg_value);
			}
			
			else
			{
				$this->config->$cfg_key = $cfg_value;
			}
		}
		
		if ( ! empty($config['append'])) $this->config->append = $config['append'];
	}

	/**
	 * _path()
	 * 
	 * @access private
	 * @param bool
	 * @return string
	 * @usage parse()
	 * 
	 * Locates template's position
	 * 
	 * */
	
	private function _path($is_file = FALSE)
    {
    	if ($is_file)
    	{
    		$apppath = APPPATH;
    	}
    	
    	else
    	{
    		$apppath = (strpos(APPPATH, BASEPATH) !== FALSE) ?
		        $this->CI->config->item('base_url') . end(explode('/', substr(BASEPATH, 0, -1))) . '/' . end(explode('/', substr(APPPATH, 0, -1))) :
	        	$this->CI->config->item('base_url') . trim(APPPATH, '/');
    	}
        	
        return str_replace(array('%path%', '%apppath%', '%basepath%'), array($apppath, APPPATH, BASEPATH), $this->config->path);	
    }
}

// END Parser Class

/* End of file Parser.php */
/* Location: ./application/libraries/Parser.php */