<?php
	require_once("ParserStackItem.class.php");
	
	class ParserStack {
		protected $stack;
		
		public function __construct() {
			$this->stack = array();
		}
		
		public function push(ParserStackItem $item) {
			array_push($this->stack, $item);
		}
		
		public function shypop() {
			return $this->stack[count($this->stack) - 1];
		}
		
		public function pop() {
			return array_pop($this->stack);
		}
		
		public function shift() {
			return array_shift($this->stack);
		}
		
		public function clear() {
			$this->stack = array();
		}
		
		public function size() {
			return count($this->stack);
		}
	}