<?php
	class ParserException extends Exception {
		protected $parser;
		
		public function __construct(TextParser &$parser, $message) {
			parent::__construct($message." on character ".$parser->currCharacter." at position ".$parser->currPosition);
			
			$this->parser = $parser;
		}
	
		public function getParser() {
			return $parser->parser;
		}
	}
	
	class TagDefinitionException extends Exception {
		protected $tagDefinition;
		
		public function __construct($message) {
			parent::__contruct($message);
		}
	}
