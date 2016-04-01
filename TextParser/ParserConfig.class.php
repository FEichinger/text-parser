<?php
	require_once("TagDefinition.class.php");
	
	class ParserConfig {
		protected $maxOpCode;
		
		protected $tagDefinitions;
		protected $charReplaceMap;
		
		protected $lookupBeginBracket;
		protected $lookupEndBracket;
		protected $lookupName;
		protected $lookupTagNameAttributes;
		
		/**
		 * @param array $tagDefinition optional
		 *   An array containing objects of TagDefinition
		 *   Default: array()
		 * 
		 * @param array $charReplaceMap optional
		 *   An array containing keys to be replaced in all TextItems with values
		 *     via `str_replace(array_keys($charReplaceMap), array_values($charReplaceMap))`
		 *   Default: array()
		 * 
		 */
		public function __construct($tagDefinitions = array(), $charReplaceMap = array()) {
			$this->tagDefinitions = array();
			$this->lookupBeginBracket = array();
			$this->lookupEndBracket = array();
			$this->lookupName = array();
			$this->lookupTagNameAttributes = array();
			$this->maxOpCode = -1;
			
			foreach($tagDefinitions as $tagDefinition) {
				$this->addTagDefinition($tagDefinition);
			}
			
			$this->charReplaceMap = $charReplaceMap;
		}
		
		public function __get($name) {
			return $this->$name;
		}
		
		/**
		 * @param TagDefinition $tagDefinition
		 *   A TagDefinition object to add to the ParserConfig. It will be assigned a new OpCode and added to the lookup
		 *     tables.
		 * 
		 */
		public function addTagDefinition(TagDefinition $tagDefinition) {
			$this->maxOpCode += 2;
			$this->tagDefinitions[$this->maxOpCode] = $tagDefinition;
			
			if(!isset($this->lookupBeginBracket[$tagDefinition->tagBrackets["begin"]])) $this->lookupBeginBracket[$tagDefinition->tagBrackets["begin"]] = array();
			if(!isset($this->lookupEndBracket[$tagDefinition->tagBrackets["end"]])) $this->lookupEndBracket[$tagDefinition->tagBrackets["end"]] = array();
			if(!isset($this->lookupName[$tagDefinition->name])) $this->lookupName[$tagDefinition->name] = array();
			
			$this->lookupBeginBracket[$tagDefinition->tagBrackets["begin"]][] = $this->maxOpCode;
			$this->lookupEndBracket[$tagDefinition->tagBrackets["end"]][] = $this->maxOpCode;
			$this->lookupName[$tagDefinition->name][] = $this->maxOpCode;
			if($tagDefinition->hasAttribute($tagDefinition->name)) $this->lookupTagNameAttributes[] = $this->maxOpCode;
		}
		
		/**
		 * @param char $beginBracket
		 *   The opening bracket of the searched-for tag.
		 * 
		 * @param string $name optional
		 *   The name of the searched-for tag. This will be matched exactly unless `null`. Substrings will not be considered.
		 *   Default: null
		 * 
		 * @param char $endBracket optional
		 *   The closing bracket of the searched-for tag.
		 *   Default: null
		 * 
		 * @param bool $isTagNameAttribute optional
		 *   Whether or not to filter for tags with attributes identical to the tag name.
		 *   Default: false
		 * 
		 * @retval array
		 *   The array containing the potential OpCodes matching this filter.
		 * 
		 */
		public function filter($beginBracket, $name = null, $endBracket = null, $isTagNameAttribute = false) {
			$returnset = isset($this->lookupBeginBracket[$beginBracket]) ? $this->lookupBeginBracket[$beginBracket] : array();
			if(!is_null($name)) $returnset = array_intersect($returnset, isset($this->lookupName[$name]) ? $this->lookupName[$name] : array(0));
			if(!is_null($endBracket)) $returnset = array_intersect($returnset, isset($this->lookupEndBracket[$endBracket]) ? $this->lookupEndBracket[$endBracket] : array(0));
			if($isTagNameAttribute) $returnset = array_intersect($returnset, $this->lookupTagNameAttributes);
			return array_values($returnset);
		}
		
		/**
		 * @param char $beginBracket
		 *   The opening bracket of the searched-for tag.
		 * 
		 * @param string $name optional
		 *   The name of the searched-for tag. This function specifically matches for substrings of `$name` in the name
		 *     lookup table. Performance is decreased heavily (unless `$name == null`). This should only be used for
		 *     determining whether to give up parsing a non-viable tag.
		 *   Default: null
		 * 
		 * @param char $endBracket optional
		 *   The closing bracket of the searched-for tag.
		 *   Default: null
		 * 
		 * @param bool $isTagNameAttribute optional
		 *   Whether or not to filter for tags with attributes identical to the tag name.
		 *   Default: false
		 * 
		 * @retval array
		 *   The array containing the potential OpCodes matching this filter.
		 * 
		 */
		public function filter_viable($beginBracket, $name = null, $endBracket = null, $isTagNameAttribute = false) {
			$returnset = isset($this->lookupBeginBracket[$beginBracket]) ? $this->lookupBeginBracket[$beginBracket] : array();
			
			if(!is_null($name)) {
				$viable = array();
				foreach($this->lookupName as $n => $opCode) {
					if(substr($n, 0, strlen($name)) == $name) $viable = array_merge($viable, $opCode);
				}
				$returnset = array_intersect($returnset, $viable);
			}
			
			if(!is_null($endBracket)) $returnset = array_intersect($returnset, isset($this->lookupEndBracket[$endBracket]) ? $this->lookupEndBracket[$endBracket] : array(0));
			if($isTagNameAttribute) $returnset = array_intersect($returnset, $this->lookupTagNameAttributes);
			return array_values($returnset);
		}
		
		/**
		 * @param int $opCode
		 *   The OpCode for which the TagDefinition is requested. Note: This returns the same TagDefinition for an opening tag
		 *     and its closing tag, make sure to maintain this difference on the receiving end.
		 * 
		 * @retval TagDefinition or null
		 *   Returns the TagDefinition if one is found, returns `null` if there is no TagDefinition for this OpCode.
		 * 
		 */
		public function getTagDefinition($opCode) {
			if($opCode%2 && isset($this->tagDefinitions[$opCode])) return $this->tagDefinitions[$opCode];
			else if(!($opCode%2) && isset($this->tagDefinitions[$opCode-1]) && $this->tagDefinitions[$opCode-1]->hasClosingTag) return $this->tagDefinitions[$opCode-1];
			else return null;
		}
	}
