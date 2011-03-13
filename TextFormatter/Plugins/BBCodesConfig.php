<?php

/**
* @package   s9e\Toolkit
* @copyright Copyright (c) 2010 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Toolkit\TextFormatter\Plugins;

use DOMDocument,
    DOMXPath,
    InvalidArgumentException,
    RuntimeException,
    s9e\Toolkit\TextFormatter\ConfigBuilder,
    s9e\Toolkit\TextFormatter\PluginConfig;

class BBCodesConfig extends PluginConfig
{
	const ALLOW_INSECURE_TEMPLATES = 1;
	const PRESERVE_WHITESPACE      = 2;

	/**
	* @var array Pre-filter and post-filter callbacks we allow in BBCode definitions.
	*            We use a whitelist approach because there are so many different risky callbacks
	*            that it would be too easy to let something dangerous slip by, e.g.: unlink,
	*            system, etc...
	*/
	public $BBCodeFiltersAllowedCallbacks = array(
		'strtolower',
		'strtoupper',
		'mb_strtolower',
		'mb_strtoupper',
		'ucfirst',
		'ucwords',
		'ltrim',
		'rtrim',
		'trim',
		'htmlspecialchars',
		'htmlentities',
		'html_entity_decode',
		'addslashes',
		'stripslashes',
		'addcslashes',
		'stripcslashes',
		'intval',
		'strtotime'
	);

	/**
	* @var PredefinedBBCodes
	*/
	protected $predefinedBBCodes;

	/**
	* @var array Array where keys are BBCode names and values are the tag they map to
	*/
	protected $bbcodes = array();

	/**
	* Create a new BBCode
	*
	* Will automatically create the corresponding tag. Attributes to be created can be passed via
	* the $options array, using "attributes" as key. Same for "rules".
	*
	* @param string $bbcodeName
	* @param array  $options
	*/
	public function add($bbcodeName, array $options = array())
	{
		$bbcodeName = $this->normalizeBBCodeName($bbcodeName);

		$this->cb->addTag($bbcodeName, $options);

		$this->bbcodes[$bbcodeName] = $bbcodeName;
	}

	/**
	* Create a new BBCode that maps to an existing tag
	*
	* @param string $bbcodeName
	* @param string $tagName
	*/
	public function addAlias($bbcodeName, $tagName)
	{
		$bbcodeName = $this->normalizeBBCodeId($bbcodeName);
		$tagName    = $this->normalizeBBCodeId($tagName);

		if (!$this->cb->tagExists($tagName))
		{
			throw new InvalidArgumentException("Unknown tag '" . $tagName . "'");
		}

		if (!isset($this->bbcodes[$bbcodeName]))
		{
			throw new InvalidArgumentException("Unknown BBCode '" . $bbcodeName . "'");
		}

		if (isset($this->bbcodes[$bbcodeName]))
		{
			throw new InvalidArgumentException("Cannot create alias '" . $bbcodeName . "' - a BBCode using that name already exists");
		}

		$this->bbcodes[$bbcodeName] = $tagName;
	}

	public function getConfig()
	{
		/**
		* Sort the BBCodes by name. It doesn't serve any real purpose but help canonicalize the
		* output of this method
		*/
		ksort($this->bbcodes);

		/**
		* Build the regexp that matches all the BBCode names, and remove the extraneous
		* non-capturing expression around it
		*/
		$regexp = self::buildRegexpFromList(array_keys($this->bbcodes));
		$regexp = preg_replace('#^\\(\\?:(.*)\\)$#D', '$1', $regexp);

		return array(
			'bbcodes' => $this->bbcodes,
			'regexp'  => '#\\[/?(' . $regexp . ')(?=[\\] =:/])#i'
		);
	}

	public function addPredefinedBBCode($bbcodeName)
	{
		$bbcodeName = $this->normalizeBBCodeName($bbcodeName);

		if (!isset($this->predefinedBBCodes))
		{
			if (!class_exists('PredefinedBBCodes'))
			{
				include_once __DIR__ . '/PredefinedBBCodes.php';
			}

			$this->predefinedBBCodes = new PredefinedBBCodes($this);
		}

		$callback = array(
			$this->predefinedBBCodes,
			'add' . $bbcodeName
		);

		if (!is_callable($callback))
		{
			throw new InvalidArgumentException('Unknown BBCode ' . $bbcodeName);
		}

		call_user_func_array($callback, array_slice(func_get_args(), 1));
	}

	public function addBBCodeFromExample($def, $tpl, $flags = 0, array $options = array())
	{
		$def = $this->parseBBCodeDefinition($def);

		if ($def === false)
		{
			throw new InvalidArgumentException('Cannot interpret the BBCode definition');
		}

		$tpl = $this->convertTemplate($tpl, $def, $flags);

		// Options set via $options override the ones we have parsed from the definition
		$this->add($def['bbcodeName'], $options + $def['options']);

		foreach ($def['params'] as $paramName => $paramConf)
		{
			$this->cb->addAttribute(
				$def['bbcodeName'],
				$paramName,
				$paramConf['type'],
				$paramConf
			);
		}

		$this->setBBCodeTemplate($def['bbcodeName'], $tpl, $flags);
	}

	protected function convertTemplate($tpl, array $def, $flags)
	{
		/**
		* Generate a random tag name so that the user cannot inject stuff outside of that template.
		* For instance, if the tag was <t>, one could input </t><xsl:evil-stuff/><t>
		*/
		$t = 't' . md5(microtime(true) . mt_rand());

		$useErrors = libxml_use_internal_errors(true);

		$dom = new DOMDocument;
		$dom->formatOutput = false;
		$dom->preserveWhiteSpace = false;

		$res = $dom->loadXML(
			'<?xml version="1.0" encoding="utf-8" ?>
			<' . $t . ' xmlns:xsl="http://www.w3.org/1999/XSL/Transform">' . $tpl . '</' . $t . '>'
		);

		libxml_use_internal_errors($useErrors);

		if (!$res)
		{
			$error = libxml_get_last_error();
			throw new InvalidArgumentException('Invalid XML in template - error was: ' . $error->message);
		}

		$bbcodeName     = $def['bbcodeName'];
		$params       = $def['params'];
		$placeholders = $def['placeholders'];
		$options      = $def['options'];

		/**
		* Replace placeholders in attributes
		*/
		$xpath = new DOMXPath($dom);
		foreach ($xpath->query('//@*') as $attr)
		{
			$attr->value = htmlspecialchars(preg_replace_callback(
				'#\\{[A-Z]+[0-9]*?\\}#',
				function ($m) use ($placeholders, $flags)
				{
					$identifier = substr($m[0], 1, -1);

					if (!isset($placeholders[$identifier]))
					{
						throw new InvalidArgumentException('Unknown placeholder ' . $identifier . ' found in template');
					}

					if (!($flags & ConfigBuilder::ALLOW_INSECURE_TEMPLATES)
					 && preg_match('#^TEXT[0-9]*$#D', $identifier))
					{
						throw new RuntimeException('Using {TEXT} inside HTML attributes is inherently insecure and has been disabled. Please pass ' . __CLASS__ . '::ALLOW_INSECURE_TEMPLATES as a third parameter to addBBCodeFromExample() to enable it');
					}

					return '{' . $placeholders[$identifier] . '}';
				},
				$attr->value
			));
		}

		/**
		* Replace placeholders everywhere else: the lazy version
		*/
		$tpl = preg_replace_callback(
			'#\\{[A-Z]+[0-9]*\\}#',
			function ($m) use ($placeholders)
			{
				$identifier = substr($m[0], 1, -1);

				if (!isset($placeholders[$identifier]))
				{
					throw new InvalidArgumentException('Unknown placeholder ' . $identifier . ' found in template');
				}

				if ($placeholders[$identifier][0] !== '@')
				{
					return '<xsl:apply-templates/>';
				}

				return '<xsl:value-of select="' . $placeholders[$identifier] . '"/>';
			},
			substr(trim($dom->saveXML($dom->documentElement)), 84, -36)
		);

		return $tpl;
	}

	public function parseBBCodeDefinition($def)
	{
		/**
		* The various regexps used to parse the definition
		*/
		$r = array(
			'bbcodeName'  => '[a-zA-Z_][a-zA-Z_0-9]*',
			'paramName' => '[a-zA-Z_][a-zA-Z_0-9]*',
			'type' => array(
				'regexp' => 'REGEXP[0-9]*=(?P<regexp>/.*?/i?)',
				'range'  => 'RANGE[0-9]*=(?P<min>-?[0-9]+),(?P<max>-?[0-9]+)',
				'choice' => 'CHOICE[0-9]*=(?P<choices>.+?)',
				'other'  => '[A-Z_]+[0-9]*'
			),
			'paramOptions' => '[A-Z_]+=[^;]+?'
		);
		$r['placeholder'] =
			  '\\{'
			. '(?P<type>' . implode('|', $r['type']) . ')'
			. '(?P<paramOptions>;(?:' . $r['paramOptions'] . '))*;?'
			. '\\}';

		// we remove all named captures from the placeholder for the global regexp to avoid dupes
		$placeholder = preg_replace('#\\?P<[a-zA-Z]+>#', '?:', $r['placeholder']);

		$regexp = '#\\['
		        // (BBCODE)(=paramval)?
		        . '(?P<bbcodeName>' . $r['bbcodeName'] . ')'
		        . '(?P<defaultParam>=' . $placeholder . ')?'
		        // (foo=fooval bar=barval)
		        . '(?P<attrs>(?:\\s+' . $r['paramName'] . '=' . $placeholder . ')*)'
		        // ]({TEXT})[/BBCODE]
		        . '(?:\\s*/?\\]|\\](?P<content>' . $placeholder . ')?(?P<endTag>\\[/\\1]))'
		        . '$#D';

		if (!preg_match($regexp, trim($def), $m))
		{
			return false;
		}

		$bbcodeName   = $m['bbcodeName'];
		$options      = array();
		$params       = array();
		$placeholders = array();
		$content      = (isset($m['content'])) ? $m['content'] : '';
		$attrs        = $m['attrs'];

		/**
		* Auto-close the BBCode if no end tag is specified
		*/
		if (empty($m['endTag']))
		{
			$options['auto_close'] = true;
		}

		/**
		* If we have a default param in $m[2], we prepend the definition to the attribute pairs.
		* e.g. [a href={URL}]           => $attrs = "href={URL}"
		*      [url={URL} title={TEXT}] => $attrs = "url={URL} title={TEXT}"
		*/
		if ($m['defaultParam'])
		{
			$attrs = $m['bbcodeName'] . $m['defaultParam'] . $attrs;

			$options['defaultParam'] = strtolower($m['bbcodeName']);
		}

		/**
		* Here we process the content's placeholder
		*
		* e.g. [spoiler]{TEXT}[/spoiler] => {TEXT}
		*      [img]{URL}[/img]          => {URL}
		*
		* {TEXT} doesn't require validation, so we don't copy its content into an attribute in order
		* to save space. Instead, templates will rely on the node's textContent, which we adjust to
		* ignore the node's <st/> and <et/> children
		*/
		if ($content !== '')
		{
			preg_match('#^' . $r['placeholder'] . '$#', $content, $m);

			if (preg_match('#^TEXT[0-9]*$#D', $m['type']))
			{
				/**
				* Use substring() to exclude the <st/> and <et/> children
				*/
				$placeholders[$m['type']] =
					'substring(., 1 + string-length(st), string-length() - (string-length(st) + string-length(et)))';
			}
			else
			{
				/**
				* We need to validate the content, means we should probably disable BBCodes,
				* e.g. [email]{EMAIL}[/email]
				*/
				$paramName = strtolower($bbcodeName);

				$options['defaultRule']     = 'deny';
				$options['defaultParam']    = $paramName;
				$options['content_as_param'] = true;

				/**
				* We append the placeholder to the attributes, using the BBCode's name as param name
				*/
				$attrs .= ' ' . $paramName . '=' . $content;
			}
		}

		preg_match_all(
			'#(' . $r['paramName'] . ')=' . $r['placeholder'] . '#',
			$attrs,
			$matches,
			\PREG_SET_ORDER
		);

		foreach ($matches as $m)
		{
			$paramName  = strtolower($m[1]);
			$identifier = $m['type'];

			if (isset($params[$paramName]))
			{
				throw new InvalidArgumentException('Param ' . $paramName . ' is defined twice');
			}

			$paramConf = array(
				'isRequired' => true
			);

			if (isset($m['paramOptions']))
			{
				foreach (explode(';', trim($m['paramOptions'], ';')) as $pair)
				{
					$pos = strpos($pair, '=');

					$optionName  = strtolower(substr($pair, 0, $pos));
					$optionValue = substr($pair, 1 + $pos);

					switch ($optionName)
					{
						case 'pre_filter':
						case 'post_filter':
							foreach (explode(',', $optionValue) as $callback)
							{
								if (!in_array($callback, $this->BBCodeFiltersAllowedCallbacks))
								{
									throw new \RuntimeException('Callback ' . $callback . ' is not allowed');
								}

								$paramConf[$optionName][] = (strpos($callback, '::') !== false)
								                          ? explode('::', $callback)
								                          : $callback;
							}
							break;

						default:
							$paramConf[$optionName] = $optionValue;
					}
				}
			}

			/**
			* Make sure the param type cannot be set via param options. I can't think of any way
			* to exploit that but better safe than sorry
			*/
			unset($paramConf['type']);

			foreach ($r['type'] as $type => $regexp)
			{
				if (!preg_match('#^' . $regexp . '$#D', $identifier, $m))
				{
					continue;
				}

				switch ($type)
				{
					case 'regexp':
						$paramConf['type']   = 'regexp';
						$paramConf['regexp'] = $m['regexp'];
						break;

					case 'choice':
						$choices = explode(',', $m['choices']);
						$regexp  = '/^' . self::buildRegexpFromList($choices) . '$/iD';

						if (preg_match('#[\\x80-\\xff]#', $regexp))
						{
							// Unicode mode needed
							$regexp .= 'u';
						}

						$paramConf['type']   = 'regexp';
						$paramConf['regexp'] = $regexp;
						break;

					case 'range':
						$paramConf['type'] = 'range';
						$paramConf['min']  = (int) $m['min'];
						$paramConf['max']  = (int) $m['max'];
						break;

					default:
						$paramConf['type'] = rtrim(strtolower($identifier), '1234567890');
				}

				// exit the loop once we've got a hit
				break;
			}

			// @codeCoverageIgnoreStart
			if (!isset($paramConf['type']))
			{
				throw new RuntimeException('Cannot determine the param type of ' . $identifier);
			}
			// @codeCoverageIgnoreEnd

			if ($pos = strpos($identifier, '='))
			{
				$identifier = substr($identifier, 0, $pos);
			}

			if (isset($placeholders[$identifier]))
			{
				throw new InvalidArgumentException('Placeholder ' . $identifier . ' is used twice');
			}

			$placeholders[$identifier] = '@' . $paramName;

			$params[$paramName] = $paramConf;
		}

		return array(
			'bbcodeName'   => $bbcodeName,
			'options'      => $options,
			'params'       => $params,
			'placeholders' => $placeholders
		);
	}

	/**
	* Validate and normalize a BBCode name
	*
	* @param  string $bbcodeName Original BBCode name
	* @return string             Normalized BBCode name, in uppercase
	*/
	protected function normalizeBBCodeName($bbcodeName)
	{
		if (!$this->isValidBBCodeName($bbcodeName))
		{
			throw new InvalidArgumentException ("Invalid BBCode name '" . $bbcodeName . "'");
		}

		return strtoupper($bbcodeName);
	}

	/**
	* Return whether a string is a valid BBCode name
	*
	* @param  string $bbcodeName
	* @return bool
	*/
	static public function isValidName($bbcodeName)
	{
		return (bool) preg_match('#^(?:[a-z_][a-z_0-9]*|\\*)$#Di', $bbcodeName);
	}
}