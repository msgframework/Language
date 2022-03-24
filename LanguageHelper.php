<?php

namespace Msgframework\Lib\Language;

/**
 * Language helper class
 *
 * @since  1.0.0
 */
class LanguageHelper
{
	/**
	 * Tries to detect the language.
	 *
	 * @return  string  locale or null if not found
	 *
	 * @since   1.0.0
	 */
	public static function detectLanguage(array $languages = array()): string
    {
		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
		{
			$browserLangs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);

			foreach ($browserLangs as $browserLang)
			{
				// Slice out the part before ; on first step, the part before - on second, place into array
				$browserLang = substr($browserLang, 0, strcspn($browserLang, ';'));
				$primary_browserLang = substr($browserLang, 0, 2);

				foreach ($languages as $lang)
				{
					// Take off 3 letters iso code languages as they can't match browsers' languages and default them to en

					if (\strlen($lang->lang_code) < 6)
					{
						if (strtolower($browserLang) == strtolower(substr($lang->lang_code, 0, \strlen($browserLang))))
						{
							return $lang->lang_code;
						}
						elseif ($primary_browserLang == substr($lang->lang_code, 0, 2))
						{
							$primaryDetectedLang = $lang->lang_code;
						}
					}
				}

				if (isset($primaryDetectedLang))
				{
					return $primaryDetectedLang;
				}
			}
		}

        return 'en';
	}

	/**
	 * Parse strings from a language file.
	 *
	 * @param string $fileName  The language ini file path.
	 * @param boolean $debug     If set to true debug language ini file.
	 *
	 * @return  array  The strings parsed.
	 *
	 * @since   1.0.0
	 */
	public static function parseIniFile(string $fileName, bool $debug = false): array
    {
		// Check if file exists.
		if (!is_file($fileName))
		{
			return array();
		}

		// Capture hidden PHP errors from the parsing.
		if ($debug === true)
		{
			// See https://www.php.net/manual/en/reserved.variables.phperrormsg.php
			$php_errormsg = null;

			$trackErrors = ini_get('track_errors');
			ini_set('track_errors', true);
		}

		// This was required for https://github.com/joomla/joomla-cms/issues/17198 but not sure what server setup
		// issue it is solving
		$disabledFunctions = explode(',', ini_get('disable_functions'));
		$isParseIniFileDisabled = \in_array('parse_ini_file', array_map('trim', $disabledFunctions));

		if (!\function_exists('parse_ini_file') || $isParseIniFileDisabled)
		{
			$contents = file_get_contents($fileName);
			$strings = @parse_ini_string($contents);
		}
		else
		{
			$strings = @parse_ini_file($fileName);
		}

		// Restore error tracking to what it was before.
		if ($debug === true)
		{
			ini_set('track_errors', $trackErrors);
		}

		return \is_array($strings) ? $strings : array();
	}

	/**
	 * Save strings to a language file.
	 *
	 * @param string $fileName  The language ini file path.
	 * @param string[]   $strings   The array of strings.
	 *
	 * @return  boolean  True if saved, false otherwise.
	 *
	 * @since   1.0.0
	 */
	public static function saveToIniFile(string $fileName, array $strings): bool
    {
		// Escape double quotes.
		foreach ($strings as $key => $string)
		{
			$strings[$key] = addcslashes($string, '"');
		}

        return file_put_contents($fileName, IniHelper::objectToString($strings));
	}

	/**
	 * Checks if a language exists.
	 *
	 * This is a simple, quick check for the directory that should contain language files for the given user.
	 *
	 * @param string $lang      Language to check.
	 * @param string $basePath  Optional path to check.
	 *
	 * @return  boolean  True if the language exists.
	 *
	 * @since   1.0.0
	 */
	public static function exists(string $lang, string $basePath): bool
    {
		static $paths = array();

		// Return false if no language was specified
		if (!$lang)
		{
			return false;
		}

		$path = $basePath . '/language/' . $lang;

		// Return previous check results if it exists
		if (isset($paths[$path]))
		{
			return $paths[$path];
		}

		// Check if the language exists
		$paths[$path] = is_dir($path);

		return $paths[$path];
	}

	/**
	 * Returns a list of known languages for an area
	 *
	 * @param string $basePath  The basepath to use
	 *
	 * @return  array  key/value pair with the language file and real name.
	 *
	 * @since   1.0.0
	 */
	public static function getKnownLanguages(string $basePath): array
    {
		return self::parseLanguageFiles(self::getLanguagePath($basePath));
	}

	/**
	 * Get the path to a language
	 *
	 * @param string $basePath  The basepath to use.
	 * @param string|null $language  The language tag.
	 *
	 * @return  string  language related path or null.
	 *
	 * @since   1.0.0
	 */
	public static function getLanguagePath(string $basePath, string $language = null): string
    {
		return $basePath . '/language' . (!empty($language) ? '/' . $language : '');
	}

	/**
	 * Searches for language directories within a certain base dir.
	 *
	 * @param   string  $dir  directory of files.
	 *
	 * @return  array  Array holding the found languages as filename => real name pairs.
	 *
	 * @since   1.0.0
	 */
	public static function parseLanguageFiles($dir = null)
	{
		$languages = array();

		// Search main language directory for subdirectories
		foreach (glob($dir . '/*', GLOB_NOSORT | GLOB_ONLYDIR) as $directory)
		{
			// But only directories with lang code format
			if (preg_match('#/[a-z]{2,3}-[A-Z]{2}$#', $directory))
			{
				$dirPathParts = pathinfo($directory);
				$file         = $directory . '/metadata.json';

				if (!is_file($file))
				{
					continue;
				}

				try
				{
					// Get installed language metadata from xml file and merge it with lang array
					if ($metadata = self::parseMetadataFile($file))
					{
						$languages = array_replace($languages, array($dirPathParts['filename'] => $metadata));
					}
				}
				catch (\RuntimeException $e)
				{
					// Ignore it
				}
			}
		}

		return $languages;
	}

    /**
     * Parse XML file for language information.
     *
     * @param string $path  Path to the XML files.
     *
     * @return  array  Array holding the found metadata as a key => value pair.
     *
     * @throws  \RuntimeException
     *@since   1.0.0
     */
    public static function parseMetadataFile(string $path): array
    {
        if (!is_readable($path))
        {
            throw new \RuntimeException('File not found or not readable');
        }
        $data = file_get_contents($path);
        $metadata = $data ? json_decode($data, true) : null;

        if ($metadata === null) {
            throw new \RuntimeException(sprintf('Language metadata file "%s" contains invalid JSON', $path));
        }

        return $metadata;
    }
}
