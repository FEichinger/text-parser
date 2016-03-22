<?php
	require_once("ParserStackItem.class.php");
	
	class OpCodeItem extends ParserStackItem {
		protected $opCode;
		protected $attributes = array();
		protected $lastSetAttribute;
		
		public function __construct(ParserConfig &$parserConfig, $opCode) {
			parent::__construct($parserConfig);
			$this->opCode = $opCode;
		}
		
		public function __get($name) {
			return $this->$name;
		}
		
		public function setAttribute($name, $value = true) {
			$this->lastSetAttribute = $name;
			$this->attributes[$name] = $value;
		}
		
		public function emit() {
			$tagDefinition = $this->parserConfig->getTagDefinition($this->opCode);
			return $tagDefinition->emit(!($this->opCode%2), $this->attributes);
		}
	}
