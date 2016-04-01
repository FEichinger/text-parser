TEXT-PARSER
==================================================

v1.1.0
------
**Additions**
 - Parser Variables [currCharacter, currPosition, currInput] now instance attributes
     May be used for resolving parser errors or giving detailed information about error
 - TextParser and TagDefinition now throw Exceptions (ParserException and TagDefinitionException respectively) instead
    of PHP errors. Both contain references to their throwing object, however only ParserException may return the TextParser.
     TagDefinitions also no longer trigger errors upon invalid attributes.

**Bugfixes**
 - Adding a TagDefinition with tag-name-attribute no longer causes Fatal Error
 - TagDefinition checks for tag brackets matching whitespace or `=`
 - Parser no longer expects attributes for closing tags
 - Closing OpCodeItems now receive a copy of the opening OpCodeItem's attributes
     This is necessary to allow conditional replacement of closing tags.
 - Parser no longer treats undefined and NOPARSE states as MAINFLOW

**Documentation**
 - A few comments have received word-wrap to achieve a maximum length of 128 characters per line of comments

v1.0.0
------
Initial Version
