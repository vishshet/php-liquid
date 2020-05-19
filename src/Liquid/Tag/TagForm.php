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

use Liquid\AbstractBlock;
use Liquid\Context;
use Liquid\Exception\ParseException;
use Liquid\FileSystem;
use Liquid\Regexp;

class TagForm extends AbstractBlock
{
	/**
	 * The variable to assign to
	 *
	 * @var string
	 */
	private $to;

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
		$syntaxRegexp = new Regexp('/(\w+)/');
		if ($syntaxRegexp->match($markup)) {
			$this->to = $markup;
			parent::__construct($markup, $tokens, $fileSystem);
		} else {
			throw new ParseException("Syntax Error in 'form'");
		}
	}

	/**
	 * Renders the block
	 *
	 * @param Context $context
	 *
	 * @return string
	 */
	public function render(Context $context)
	{	
		$form_attr = explode(',', $this->to);
		$output = parent::render($context);

		$action = '';
		if($form_attr[0] === "'product'" ){
			$action = '/cart/add';
		}else if($form_attr[0] === "'customer'" ){
			$action = '/account';
		}

		$form = "<form enctype='multipart/form-data' action='".$action."' method='post' ";

		foreach ($form_attr as $k => $v) {
			if($v == 'product' || $v == 'customer' || $k == 0){
				continue;
			}
			if(strpos($v, ":") !== false){
				$datart = explode(':', $v);
				$form .= $datart[0]."=".$datart[1];
			}
		}


		$form .= '>'.$output."</form>";

		return $form;
	}
}
