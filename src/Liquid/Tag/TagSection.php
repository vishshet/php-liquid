<?php

/*
 * This file is part of the Liquid package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Liquid
 */

namespace Liquid\Tag;

use Liquid\AbstractTag;
use Liquid\Document;
use Liquid\Context;
use Liquid\Exception\MissingFilesystemException;
use Liquid\Exception\ParseException;
use Liquid\Liquid;
use Liquid\LiquidException;
use Liquid\FileSystem;
use Liquid\Regexp;
use Liquid\Template;

/**
 * Includes another, partial, template
 *
 * Example:
 *
 *     {% include 'foo' %}
 *
 *     Will include the template called 'foo'
 *
 *     {% include 'foo' with 'bar' %}
 *
 *     Will include the template called 'foo', with a variable called foo that will have the value of 'bar'
 *
 *     {% include 'foo' for 'bar' %}
 *
 *     Will loop over all the values of bar, including the template foo, passing a variable called foo
 *     with each value of bar
 */
class TagSection extends AbstractTag
{
	/**
	 * @var string The name of the template
	 */
	private $templateName;

	/**
	 * @var bool True if the variable is a collection
	 */
	private $collection;

	/**
	 * @var mixed The value to pass to the child template as the template name
	 */
	private $variable;

	/**
	 * @var Document The Document that represents the included template
	 */
	private $document;

	/**
	 * @var string The Source Hash
	 */
	protected $hash;

	/**
	 * Constructor
	 *
	 * @param string $markup
	 * @param array $tokens
	 * @param FileSystem $fileSystem
	 *
	 * @throws \Liquid\Exception\ParseException
	 */
	public function __construct($markup, array &$tokens, FileSystem $fileSystem = null)
	{
		$regex = new Regexp('/("[^"]+"|\'[^\']+\'|[^\'"\s]+)(\s+(with|as)\s+(' . Liquid::get('QUOTED_FRAGMENT') . '+))?/');

		if (!$regex->match($markup)) {
			throw new ParseException("Error in tag 'section' - Valid syntax: section '[template]' (with|as) [object|collection]");
		}

		$unquoted = (strpos($regex->matches[1], '"') === false && strpos($regex->matches[1], "'") === false);

		$start = 1;
		$len = strlen($regex->matches[1]) - 2;

		if ($unquoted) {
			$start = 0;
			$len = strlen($regex->matches[1]);
		}

		$this->templateName = substr($regex->matches[1], $start, $len);

		if (isset($regex->matches[1])) {
			$this->collection = (isset($regex->matches[3])) ? ($regex->matches[3] == "as") : null;
			$this->variable = (isset($regex->matches[4])) ? $regex->matches[4] : null;
		}
		//$this->variable = $fileSystem->getGlobalVariable("sections")[$this->templateName];
		$this->extractAttributes($markup);
		//$this->setAttribute('section', $fileSystem->getGlobalVariable("sections")[$this->templateName]);
		parent::__construct($markup, $tokens, $fileSystem);
	}

	/**
	 * Parses the tokens
	 *
	 * @param array $tokens
	 *
	 * @throws \Liquid\Exception\MissingFilesystemException
	 */
	public function parse(array &$tokens)
	{
		if ($this->fileSystem === null) {
			throw new MissingFilesystemException("No file system");
		}

		// read the source of the template and create a new sub document
		$source = $this->fileSystem->readTemplateFile($this->templateName, "section");
		$include_tag = preg_match('/{% include (.*?) %}/s', $source, $matches);
		$context = Template::getContext();
		if (isset($matches[1])) {
			if (isset($this->variable)) {
				$key = "settings.sections.".$this->variable.".settings.".str_replace("section.", "", $matches[1]);
			}else{
				$key = "settings.sections.".$this->templateName.".settings.".str_replace("section.", "", $matches[1]);	
			}
			if ($context->get($key) !== null) {
				$source = str_replace($matches[1], $context->get($key), $source);
			}
		}
		$cache = Template::getCache();

		if (!$cache) {
			// tokens in this new document
			$templateTokens = Template::tokenize($source);
			$this->document = new Document($templateTokens, $this->fileSystem);
			return;
		}

		$this->hash = md5($source);
		$this->document = $cache->read($this->hash);

		if ($this->document == false || $this->document->hasIncludes() == true) {
			$templateTokens = Template::tokenize($source);
			$this->document = new Document($templateTokens, $this->fileSystem);
			$cache->write($this->hash, $this->document);
		}
	}

	/**
	 * Check for cached includes; if there are - do not use cache
	 *
	 * @see Document::hasIncludes()
	 * @return boolean
	 */
	public function hasIncludes()
	{
		if ($this->document->hasIncludes() == true) {
			return true;
		}

		$source = $this->fileSystem->readTemplateFile($this->templateName);

		if (Template::getCache()->exists(md5($source)) && $this->hash === md5($source)) {
			return false;
		}

		return true;
	}

	/**
	 * Renders the node
	 *
	 * @param Context $context
	 *
	 * @return string
	 */
	public function render(Context $context)
	{
		$result = '';
		$variable = $context->get($this->variable);
		$section = $context->get('section');
		$settings = $context->get('settings');
		//dd($section['settings']['section']);

		// if(array_key_exists($this->templateName, $section['settings']['sections'])){
		// 	//$section['settings'] = [];
		// 	foreach ($section['settings']['sections'][$this->templateName]['settings'] as $key => $value) {
		// 		$section['settings'][$key] = $value;
		// 	}
		// }
		if (!is_null($settings)) {
			if(array_key_exists('sections', $settings)){
				//echo $this->templateName."<br>";
				if(isset($variable)){
					$context->set('section', $settings['sections']["".$variable]['settings']);
				}else if(array_key_exists($this->templateName, $settings['sections'])){
					$context->set('section', $settings['sections'][$this->templateName]['settings']);
				}
			}
		}
		//$context->set('section', $section);

		$context->push();

		foreach ($this->attributes as $key => $value) {
			$context->set($key, $context->get($key));
		}

		if ($this->collection) {
			foreach ($variable as $item) {
				$context->set($this->templateName, $item);
				$result .= $this->document->render($context);
			}
		} else {
			if (!is_null($this->variable)) {
				$context->set($this->templateName, $variable);
			}


			$result .= $this->document->render($context);
		}

		//dd($result);

		$context->pop();

		$source = $this->fileSystem->readTemplateFile($this->templateName, "section");
		$schema = preg_match('/{% schema %}(.*?){% endschema %}/s', $source, $matches);
		$classes = "";
		if (isset($matches[1])) {
            $schema = json_decode($matches[1],true);
            if(array_key_exists('class', $schema)){
                $classes = $schema['class'];
                //echo $this->templateName."  ".$classes."<br>";
            }
        }
        if(isset($variable)){
        	$final_result = "<div id='cartx-section-".$variable."' class='cartx-section ".$classes."'>".$result."</div>";
    	}else{
    		$final_result = "<div id='cartx-section-".$this->templateName."' class='cartx-section ".$classes."'>".$result."</div>";
    	}

		return $final_result;
	}
}
