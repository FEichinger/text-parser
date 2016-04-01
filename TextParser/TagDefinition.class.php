<?php
	require_once("Exceptions.package.php");
	
	class TagDefinition {
		protected $tagBrackets;
		protected $name;
		protected $hasClosingTag;
		protected $attributes;
		protected $replaceFunction;
		
		/**
		 * @param string $name
		 *   The tag's name, identical for opening and closing tag, does not include bracktes.
		 *     Example: For BB-Code `[b]...[/b]` this will be "b".
		 * 
		 * @param array $tagBrackets optional
		 *   An array of keys "begin" and "end" matched to the brackets surrounding the tag. Must be a single character each.
		 *   Default: array("begin" => "[", "end" => "]")
		 * 
		 * @param bool $hasClosingTag optional
		 *   Whether the tag requires a matching closing tag.
		 *   Default: true
		 * 
		 * @param array $attributes optional
		 *   An array containing all valid attributes for this tag. If this is an associative array, any non-numeric
		 *     keys will be used as attribute names, while values are used as a regular expression for validation.
		 *    If an attribute name is the same as the tag name, the attribute can be parsed from the tag name.
		 *     Example: `[url={link}]{content}[/url]` is valid for tag name `url` and attribute name `url`.
		 *   Default: array()
		 * 
		 * @param callable $replaceFunction optional
		 *   A callable `function ($name, $closing, $attributes)` where `$name` is a string containing the tag name, `$closing`
		 *     is a bool that is true when a closing tag is to be emitted, and `$attributes` is an associative array of
		 *     attribute names to attribute values.
		 *    Attributes for which the attribute definition contains a regular expression for validation will have their
		 *     values validated against it before `$replaceFunction` is called and any attributes for
		 *     which `preg_match($validationRegExp, $attributeValue)` returns `0` or `false` will not be forwarded
		 *     to `$replaceFunction`.
		 *   Default: TagDefinition::defaultReplace
		 * 
		 * @throws TagDefinitionException if either defined tag bracket is longer than 1 character.
		 * 
		 * @throws TagDefinitionException if either defined tag bracket contains whitespace or `=`.
		 * 
		 */
		public function __construct($name, $tagBrackets = array("begin" => "[", "end" => "]"), $hasClosingTag = true, array $attributes = array(), callable $replaceFunction = null) {
			if(!isset($tagBrackets["begin"]) || !isset($tagBrackets["end"])
			|| mb_strlen($tagBrackets["begin"]) > 1 || mb_strlen($tagBrackets["end"]) > 1) {
				throw new TagDefinitionException("Tag brackets must be single character each");
			}
			if(in_array("=", $tagBrackets) || in_array(" ", $tagBrackets) || in_array("\n", $tagBrackets) || in_array("\t", $tagBrackets))
				throw new TagDefinitionException("Tag brackets must not be whitespace or `=`");
			
			$this->name = $name;
			$this->tagBrackets = $tagBrackets;
			$this->hasClosingTag = $hasClosingTag;
			$this->attributes = $attributes;
			$this->replaceFunction = (is_null($replaceFunction) ? array('TagDefinition', 'defaultReplace') : $replaceFunction);
		}
		
		public function __get($name) {
			return $this->$name;
		}
		
		/**
		 * @param bool $closing optional
		 *   Whether a closing tag should be emitted.
		 *   Default: false
		 * 
		 * @param array $attributes optional
		 *   An associative array of attribute keys to values which will be forwarded to `TagDefinition::$replaceFunction`
		 *     after potential validation over regular expressions for validation as per the attribute definition.
		 *    Attributes for which the key does not match the attribute definition or for which the value does not validate
		 *     against the given regex will be stripped.
		 *   Default: array()
		 * 
		 * @retval string
		 *   Returns the output of `TagDefinition::$replaceFunction` when called with this TagDefinition's `$name`, `$closing`,
		 *     and the `$attributes` array with all invalid attributes stripped.
		 * 
		 */
		public function emit($closing = false, array $attributes = array()) {
			// Strip all attributes for which a required RegExp validation fails
			foreach($this->attributes as $key => $value) {
				if(!is_numeric($key)) {
					if(isset($attributes[$key]) && !preg_match($value, $attributes[$key])) {
						unset($attributes[$key]);
					}
				}
			}
			// Strip all attributes for which the key is not in attribute definition
			foreach($attributes as $key => $value) {
				$valid = false;
				foreach($this->attributes as $k => $v) {
					if(is_numeric($k) && ($v == $key))	$valid = true;
					else if($k == $key)					$valid = true;
				}
				if(!$valid) {
					unset($attributes[$key]);
				}
			}
			// Call the replace function
			return call_user_func($this->replaceFunction, $this->name, $closing, $attributes);
		}
		
		public function hasAttribute($name) {
			// May return unexpected results if regex <=> name discrepancy
			return isset($this->attributes[$name]) || in_array($name, $this->attributes);
		}
		
		/**
		 * @param $name
		 *   The name of the HTML tag to return
		 * 
		 * @param $closing optional
		 *   Whether or not a closing HTML tag will be returned. Closing tags will omit the attribute string.
		 *   Default: false
		 * 
		 * @param $attributes optional
		 *   An associative array of attribute keys to attribute values.
		 *   Default: array()
		 * 
		 * @retval string
		 *   An HTML Tag in format `"<".($closing ? "/" : "").$name.$attributeString.">"` where `$attributeString` is a
		 *     space-joined list of attribute keys and values of format `$key="$value"` with a leading space.
		 * 
		 */
		private static function defaultReplace($name, $closing = false, array $attributes = array()) {
			$attributeString = "";
			foreach($attributes as $key => $value) {
				$attributeString .= " ".$key."=\"".$value."\"";
			}
			return "<".($closing ? "/" : "").$name.($closing ? "" : $attributeString).">";
		}
	}
