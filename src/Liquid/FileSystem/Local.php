<?php

/*
 * This file is part of the Liquid package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Liquid
 */

namespace Liquid\FileSystem;

use Liquid\Exception\NotFoundException;
use Liquid\Exception\ParseException;
use Liquid\FileSystem;
use Liquid\Regexp;
use Liquid\Liquid;

/**
 * This implements an abstract file system which retrieves template files named in a manner similar to Rails partials,
 * ie. with the template name prefixed with an underscore. The extension ".liquid" is also added.
 *
 * For security reasons, template paths are only allowed to contain letters, numbers, and underscore.
 */
class Local implements FileSystem
{
	/**
	 * The root path
	 *
	 * @var string
	 */
	private $root;
	private $include_path;
	private $section_path;
	private $template_path;

	/**
	 * Constructor
	 *
	 * @param string $root The root path for templates
	 * @throws \Liquid\Exception\NotFoundException
	 */
	public function __construct($root, $include_path = null, $section_path = null , $template_path = null )
	{
		// since root path can only be set from constructor, we check it once right here
		if (!empty($root)) {
			$realRoot = realpath($root);
			$realInclude = realpath($include_path);
			$realSection = realpath($section_path);
			$realTemplate = realpath($template_path);
			if ($realRoot === false) {
				throw new NotFoundException("Root path could not be found: '$root'");
			}
			$root = $realRoot;
			$include_path = $include_path == null ? $realRoot : $realInclude;
			$section_path = $section_path == null ? $realRoot : $realSection;
			$template_path = $template_path == null ? $realRoot : $realTemplate;
		}

		$this->root = $root;
		$this->include_path = $include_path;
		$this->section_path = $section_path;
		$this->template_path = $template_path;
	}

	/**
	 * Retrieve a template file
	 *
	 * @param string $templatePath
	 *
	 * @return string template content
	 */
	public function readTemplateFile($templatePath, $type = "")
	{
		return file_get_contents($this->fullPath($templatePath, $type));
	}

	/**
	 * Resolves a given path to a full template file path, making sure it's valid
	 *
	 * @param string $templatePath
	 *
	 * @throws \Liquid\Exception\ParseException
	 * @throws \Liquid\Exception\NotFoundException
	 * @return string
	 */
	public function fullPath($templatePath, $type = "")
	{
		if (empty($templatePath)) {
			throw new ParseException("Empty template name");
		}

		$nameRegex = Liquid::get('INCLUDE_ALLOW_EXT')
		? new Regexp('/^[^.\/][a-zA-Z0-9_\.\/-]+$/')
		: new Regexp('/^[^.\/][a-zA-Z0-9_\/-]+$/');

		if (!$nameRegex->match($templatePath)) {
			throw new ParseException("Illegal template name '$templatePath'");
		}

		$templateDir = dirname($templatePath);
		$templateFile = basename($templatePath);

		if (!Liquid::get('INCLUDE_ALLOW_EXT')) {
			$templateFile = $templateFile . '.' . Liquid::get('INCLUDE_SUFFIX');
		}

		if ($type == "include") {
			$fullPath = join(DIRECTORY_SEPARATOR, array($this->include_path, $templateDir, $templateFile));
		}else if ($type == "section") {
			$fullPath = join(DIRECTORY_SEPARATOR, array($this->section_path, $templateDir, $templateFile));
		}else if ($type == "template") {
			$fullPath = join(DIRECTORY_SEPARATOR, array($this->template_path, $templateDir, $templateFile));
		}else{
			$fullPath = join(DIRECTORY_SEPARATOR, array($this->root, $templateDir, $templateFile));
		}

		$realFullPath = realpath($fullPath);
		if ($realFullPath === false) {
			throw new NotFoundException($type.$this->template_path."File not found: $fullPath");
		}

		if (strpos($realFullPath, $this->root) !== 0) {
			throw new NotFoundException("Illegal template full path: {$realFullPath} not under {$this->root}");
		}

		return $realFullPath;
	}
}
