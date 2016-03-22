<?php
	require_once("ParserStackItem.class.php");
	
	class TextItem extends ParserStackItem {
		protected $text;
		
		public function __construct(ParserConfig &$parserConfig, $text) {
			parent::__construct($parserConfig);
			$this->text = $text;
		}
		
		public function emit() {
			return str_replace(array_keys($this->parserConfig->charReplaceMap), array_values($this->parserConfig->charReplaceMap), $this->text);
		}
	}
