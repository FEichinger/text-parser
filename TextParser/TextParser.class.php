<?php
	require_once("ParserConfig.class.php");
	require_once("ParserStack.class.php");
	require_once("TextItem.class.php");
	require_once("OpCodeItem.class.php");
	
	class TextParser {
		const STATE_NOPARSE					= 0x00;
		const STATE_MAINFLOW				= 0x01;
		const STATE_TAG_NAME				= 0x02;
		const STATE_TAG_ATTRIBUTE_NAME		= 0x03;
		const STATE_TAG_ATTRIBUTE_VALUE		= 0x04;
		private static $WHITESPACE_INLINE	= array(" ", "\t");
		
		protected $parserConfig;
		
		protected $characterStack;
		protected $opCodeStack;
		protected $outputStack;
		
		protected $state;
		
		public function __construct(ParserConfig $parserConfig) {
			$this->parserConfig = $parserConfig;
			
			$this->characterStack = "";
			$this->opCodeStack = new ParserStack;
			$this->outputStack = new ParserStack;
			
			$this->state = self::STATE_NOPARSE;
		}
		
		public function parse($input) {
			$this->characterStack = "";
			$this->opCodeStack->clear();
			$this->outputStack->clear();
			$this->state = self::STATE_MAINFLOW;
			
			for($readPosition = 0; $readPosition < strlen($input); $readPosition++) {
				$character = $input[$readPosition];
				
				switch($this->state) {
					case self::STATE_TAG_NAME:
						// tag brackets MUST NOT be whitespace or `=`!
						// tag names MUST NOT contain whitespace or `=`!
						if(in_array($character, self::$WHITESPACE_INLINE) || $character == "=") {
							// Tag name *definitely* complete, check if we have a tag for this name
							if(strlen($this->characterStack) >= 2 && $this->characterStack[1] == "/") {
								// Consider aborting here if closing tags ought not to have attributes
								$opCodes = $this->parserConfig->filter(substr($this->characterStack, 0, 1), substr($this->characterStack, 2));
								// If yes: Check if this is a closing tag for the element on top of OpCodeStack
								if(count($opCodes)) {
									$opCode = array_shift($opCodes);
									$stackItem = $this->opCodeStack->shypop();
									$tagDefinition = $this->parserConfig->getTagDefinition($opCode);
									if($opCode == $stackItem->opCode && $tagDefinition->hasClosingTag) {
										$this->opCodeStack->pop();
										$this->outputStack->push(new OpCodeItem($opCode+1));
										if($character == "=") {
											if($tagDefinition->hasAttribute($tagDefinition->name)) {
												$this->opCodeStack->push($opCodeItem);
												$this->outputStack->push($opCodeItem);
												$this->state = self::STATE_TAG_ATTRIBUTE_VALUE;
											}
											else self::throw_parser_error("Unexpected `=` for TagDefinition with no tag-name-attribute", $character, $readPosition);
										}
										else {
											$this->opCodeStack->push($opCodeItem);
											$this->outputStack->push($opCodeItem);
											$this->state = self::STATE_TAG_ATTRIBUTE_NAME;
										}
									}
									else self::throw_parser_error("Invalid nesting", $character, $readPosition);
								}
							}
							else {
								$opCodes = $this->parserConfig->filter(substr($this->characterStack, 0, 1), substr($this->characterStack, 1));
								// If yes: Push to stack and continue with attribute list
								if(($character == "=" || $character == " ") && count($opCodes)) {
									$opCode = array_shift($opCodes);
									$opCodeItem = new OpCodeItem($this->parserConfig, $opCode);
									if($character == "=") {
										if($tagDefinition->hasAttribute($tagDefinition->name)) {
											$this->opCodeStack->push($opCodeItem);
											$this->outputStack->push($opCodeItem);
											$opCodeItem->setAttribute($tagDefinition->name);
											$this->characterStack = "";
											$this->state = self::STATE_TAG_ATTRIBUTE_VALUE;
										}
										else self::throw_parser_error("Unexpected `=` for TagDefinition with no tag-name-attribute", $character, $readPosition);
									}
									else {
										$this->opCodeStack->push($opCodeItem);
										$this->outputStack->push($opCodeItem);
										$this->characterStack = "";
										$this->state = self::STATE_TAG_ATTRIBUTE_NAME;
									}
								}
								// else: Store as TextItem and continue as mainflow
								else {
									$this->storeCharacterStack();
									$this->state = self::STATE_MAINFLOW;
								}
							}
						}
						else {
							if(strlen($this->characterStack) >= 2 && $this->characterStack[1] == "/") {
								// Closing tag
								
								// Perhaps aborting after max(lengths_of_tag_names) would be better for performance here
								// We filter the same result on total success, which should be the ideal case!
								if(strlen($this->characterStack) >= 3 && !count($this->parserConfig->filter_viable(substr($this->characterStack, 0, 1), substr($this->characterStack, 2)))) {
									$this->storeCharacterStack();
									$this->state = self::STATE_MAINFLOW;
								}
								
								$opCodes = $this->parserConfig->filter(substr($this->characterStack, 0, 1), substr($this->characterStack, 2), $character);
								if(count($opCodes)) {
									$waitingOpCodeItem = $this->opCodeStack->pop();
									$tagDefinition = $this->parserConfig->getTagDefinition($opCodes[0]);
									if(in_array($waitingOpCodeItem->opCode, $opCodes) && $tagDefinition->hasClosingTag) {
										$opCodeItem = new OpCodeItem($this->parserConfig, $opCodes[0]+1);
										$this->outputStack->push($opCodeItem);
										$this->characterStack = "";
										$character = "";
										$this->state = self::STATE_MAINFLOW;
									}
									else self::throw_parser_error("Invalid nesting", $character, $readPosition);
								}
							}
							else {
								// Perhaps aborting after max(lengths_of_tag_names) would be better for performance here
								// We filter the same result on total success, which should be the ideal case!
								if(strlen($this->characterStack) >= 2 && !count($this->parserConfig->filter_viable(substr($this->characterStack, 0, 1), substr($this->characterStack, 1)))) {
									$this->storeCharacterStack();
									$this->state = self::STATE_MAINFLOW;
								}
								
								$opCodes = $this->parserConfig->filter(substr($this->characterStack, 0, 1), substr($this->characterStack, 1), $character);
								if(count($opCodes)) {
									$opCodeItem = new OpCodeItem($this->parserConfig, array_shift($opCodes));
									$this->opCodeStack->push($opCodeItem);
									$this->outputStack->push($opCodeItem);
									$this->characterStack = "";
									$character = "";
									$this->state = self::STATE_MAINFLOW;
								}
							}
						}
						break;
					
					case self::STATE_TAG_ATTRIBUTE_NAME:
						if($character == "=") {
							$opCodeItem = $this->outputStack->shypop();
							$tagDefinition = $this->parserConfig->getTagDefinition($opCodeItem->opCode);
							if($tagDefinition->hasAttribute(substr($this->characterStack, 1))) {
								$opCodeItem->setAttribute(substr($this->characterStack, 1));
								$this->characterStack = "";
								$this->state = self::STATE_TAG_ATTRIBUTE_VALUE;
							}
						}
						break;
					
					case self::STATE_TAG_ATTRIBUTE_VALUE:
						$opCodeItem = $this->outputStack->shypop();
						$tagDefinition = $this->parserConfig->getTagDefinition($opCodeItem->opCode);
						if($character == $tagDefinition->tagBrackets["end"]) {
							$opCodeItem->setAttribute($opCodeItem->lastSetAttribute, substr($this->characterStack, 1));
							$this->characterStack = "";
							$character = "";
							$this->state = self::STATE_MAINFLOW;
						}
						break;
					
					case self::STATE_MAINFLOW:
					default:
						$opCodes = $this->parserConfig->filter($character);
						if(count($opCodes)) {
							$this->storeCharacterStack();
							$this->state = self::STATE_TAG_NAME;
						}
				}
				$this->characterStack .= $character;
			}
			$this->storeCharacterStack();
			
			if($this->opCodeStack->size()) self::throw_parser_error("Unexpected end of input", $character, $readPosition);
			
			$this->state = self::STATE_NOPARSE;
			return $this->outputStack;
		}
		
		protected static function throw_parser_error($message, $character, $position) {
			trigger_error($message." at character '".str_replace(array("\n", "\r"), array("\\n", "\\r"), $character)."' at position '".$position."'", E_USER_ERROR);
		}
		
		protected function storeCharacterStack() {
			if(strlen($this->characterStack)) $this->outputStack->push(new TextItem($this->parserConfig, $this->characterStack));
			$this->characterStack = "";
		}
	}
