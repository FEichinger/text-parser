<?php
	require_once("ParserConfig.class.php");
	
	abstract class ParserStackItem {
		protected $parserConfig;
		
		protected function __construct(ParserConfig &$parserConfig) {
			$this->parserConfig = $parserConfig;
		}
		
		public abstract function emit();
	}
