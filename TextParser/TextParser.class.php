<?php
	require_once("ParserConfig.class.php");
	require_once("ParserStack.class.php");
	require_once("TextItem.class.php");
	require_once("OpCodeItem.class.php");
	require_once("Exceptions.package.php");
	
	class TextParser {
		/* Class Constants */
		const STATE_NOPARSE					= 0x00;
		const STATE_MAINFLOW				= 0x01;
		const STATE_TAG_NAME				= 0x02;
		const STATE_TAG_ATTRIBUTE_NAME		= 0x03;
		const STATE_TAG_ATTRIBUTE_VALUE		= 0x04;
		private static $WHITESPACE_INLINE	= array(" ", "\t", "\n");
		
		/* Instance Variables */
		protected $parserConfig;
		
		protected $characterStack;
		protected $opCodeStack;
		protected $outputStack;
		
		protected $state;
		
		/* Parsing Variables only to be used when processing an exception */
		protected $currInput = "";
		protected $currCharacter = "";
		protected $currPosition = 0;
		
		/**
		 * @param $parserConfig ParserConfig
		 *   The ParserConfig instance to use
		 * 
		 */
		public function __construct(ParserConfig $parserConfig) {
			$this->parserConfig = $parserConfig;
			
			$this->characterStack = "";
			$this->opCodeStack = new ParserStack;
			$this->outputStack = new ParserStack;
			
			$this->state = self::STATE_NOPARSE;
		}
		
		/**
		 * @param $input string
		 *   The string to parse. Contained tags must be correctly nested. String must not contain `[name=(.*)` for which name
		 *     identifies a tag without tag-name-attribute.
		 * 
		 * @return ParserStack
		 *   The final OutputStack. Must be iterated over and all contained ParserStackItems should be processed
		 *     with `echo $parserStackItem->emit();`.
		 * 
		 * @throws ParserException if invalid nesting is detected.
		 * 
		 * @throws ParserException if a tag with no tag-name-attribute is being parsed [This will be replaced with proper
		 *   handling (ignore input, return to mainflow) for this case eventually.]
		 * 
		 * @throws ParserException if input ends unexpectedly - the OpCodeStack is not emptied. This exception can safely be
		 *   ignored and the final OutputStack used if consistency of I/O is not a requirement.
		 *  If consistency of I/O is a requirement, emitting the remaining OpCodeStack items as closing tags is also an option.
		 * 
		 * @throws ParserException if parser is in an invalid state - such as STATE_NOPARSE or an undefined state.
		 * 
		 */
		public function parse($input) {
			$this->currInput = $input;
			$this->currPosition = 0;
			$this->currCharacter = "";
			
			$this->characterStack = "";
			$this->opCodeStack->clear();
			$this->outputStack->clear();
			$this->state = self::STATE_MAINFLOW;
			
			for(; $this->currPosition < strlen($this->currInput); $this->currPosition++) {
				$this->currCharacter = $this->currInput[$this->currPosition];
				
				switch($this->state) {
					case self::STATE_TAG_NAME:
						// tag brackets MUST NOT be whitespace or `=`!
						// tag names MUST NOT contain whitespace or `=`!
						if(in_array($this->currCharacter, self::$WHITESPACE_INLINE) || $this->currCharacter == "=") {
							// Tag name *definitely* complete, check if we have a tag for this name
							if(strlen($this->characterStack) >= 2 && $this->characterStack[1] == "/") {
								$opCodes = $this->parserConfig->filter(substr($this->characterStack, 0, 1), substr($this->characterStack, 2));
								// If yes: Check if this is a closing tag for the element on top of OpCodeStack
								if(count($opCodes)) {
									$opCode = array_shift($opCodes);
									$stackItem = $this->opCodeStack->shypop();
									$tagDefinition = $this->parserConfig->getTagDefinition($opCode);
									if($opCode == $stackItem->opCode && $tagDefinition->hasClosingTag) {
										$this->opCodeStack->pop();
										$opCodeItem = new OpCodeItem($this->parserConfig, $opCode+1);
										foreach($stackItem->attributes as $attribute => $value) {
											$opCodeItem->setAttribute($attribute, $value);
										}
										$this->outputStack->push($opCodeItem);
									}
									else throw new ParserException($this, "Invalid nesting");
								}
							}
							else {
								$opCodes = $this->parserConfig->filter(substr($this->characterStack, 0, 1), substr($this->characterStack, 1));
								// If yes: Push to stack and continue with attribute list
								if(($this->currCharacter == "=" || $this->currCharacter == " ") && count($opCodes)) {
									$opCode = array_shift($opCodes);
									$opCodeItem = new OpCodeItem($this->parserConfig, $opCode);
									if($this->currCharacter == "=") {
										if($tagDefinition->hasAttribute($tagDefinition->name)) {
											$this->opCodeStack->push($opCodeItem);
											$this->outputStack->push($opCodeItem);
											$opCodeItem->setAttribute($tagDefinition->name);
											$this->characterStack = "";
											$this->state = self::STATE_TAG_ATTRIBUTE_VALUE;
										}
										else self::throw_parser_error("Unexpected `=` for TagDefinition with no tag-name-attribute");
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
								
								$opCodes = $this->parserConfig->filter(substr($this->characterStack, 0, 1), substr($this->characterStack, 2), $this->currCharacter);
								if(count($opCodes)) {
									$waitingOpCodeItem = $this->opCodeStack->pop();
									$tagDefinition = $this->parserConfig->getTagDefinition($opCodes[0]);
									if(in_array($waitingOpCodeItem->opCode, $opCodes) && $tagDefinition->hasClosingTag) {
										$opCodeItem = new OpCodeItem($this->parserConfig, $opCodes[0]+1);
										foreach($waitingOpCodeItem->attributes as $attribute => $value) {
											$opCodeItem->setAttribute($attribute, $value);
										}
										$this->outputStack->push($opCodeItem);
										$this->characterStack = "";
										$this->currCharacter = "";
										$this->state = self::STATE_MAINFLOW;
									}
									else throw new ParserException($this, "Invalid nesting");
								}
							}
							else {
								// Perhaps aborting after max(lengths_of_tag_names) would be better for performance here
								// We filter the same result on total success, which should be the ideal case!
								if(strlen($this->characterStack) >= 2 && !count($this->parserConfig->filter_viable(substr($this->characterStack, 0, 1), substr($this->characterStack, 1)))) {
									$this->storeCharacterStack();
									$this->state = self::STATE_MAINFLOW;
								}
								
								$opCodes = $this->parserConfig->filter(substr($this->characterStack, 0, 1), substr($this->characterStack, 1), $this->currCharacter);
								if(count($opCodes)) {
									$opCodeItem = new OpCodeItem($this->parserConfig, array_shift($opCodes));
									$this->opCodeStack->push($opCodeItem);
									$this->outputStack->push($opCodeItem);
									$this->characterStack = "";
									$this->currCharacter = "";
									$this->state = self::STATE_MAINFLOW;
								}
							}
						}
						break;
					
					case self::STATE_TAG_ATTRIBUTE_NAME:
						if($this->currCharacter == "=") {
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
						if($this->currCharacter == $tagDefinition->tagBrackets["end"]) {
							$opCodeItem->setAttribute($opCodeItem->lastSetAttribute, substr($this->characterStack, 1));
							$this->characterStack = "";
							$this->currCharacter = "";
							$this->state = self::STATE_MAINFLOW;
						}
						break;
					
					case self::STATE_MAINFLOW:
						$opCodes = $this->parserConfig->filter($this->currCharacter);
						if(count($opCodes)) {
							$this->storeCharacterStack();
							$this->state = self::STATE_TAG_NAME;
						}
						break;
					
					case self::STATE_NOPARSE:
					default:
						throw new ParserException("Invalid parser state");
				}
				$this->characterStack .= $this->currCharacter;
			}
			$this->storeCharacterStack();
			
			if($this->opCodeStack->size()) throw new ParserException($this, "Unexpected end of input");
			
			$this->state = self::STATE_NOPARSE;
			return $this->outputStack;
		}
		
		protected function storeCharacterStack() {
			if(strlen($this->characterStack)) $this->outputStack->push(new TextItem($this->parserConfig, $this->characterStack));
			$this->characterStack = "";
		}
	}
