<?php
namespace Msgframework\Lib\Language;

/**
 * Caching factory for creating language objects. The requested languages are
 * cached in memory.
 *
 * @since  1.0.0
 */
class CachingLanguageFactory extends LanguageFactory
{
	/**
	 * Array of Language objects
	 *
	 * @var    Language[]
	 * @since  1.0.0
	 */
	private static array $languages = array();

	/**
	 * Method to get an instance of a language.
	 *
	 * @param string $lang   The language to use
	 * @param boolean $debug  The debug mode
	 *
	 * @return  Language
	 *
	 * @since   1.0.0
	 */
	public function createLanguage($app, string $lang, bool $debug = false): Language
	{
		if (!isset(self::$languages[$lang . $debug]))
		{
			self::$languages[$lang . $debug] = parent::createLanguage($app, $lang, $debug);
		}

		return self::$languages[$lang . $debug];
	}
}
